<?php
namespace Casebox\CoreBundle\Service;

use Casebox\CoreBundle\Traits\TranslatorTrait;
use Casebox\CoreBundle\Service\DataModel as DM;
use Casebox\CoreBundle\Service\Objects;
use Symfony\Component\DependencyInjection\Container;

/**
 * Class UsersGroups
 */
class UsersGroups
{
    use TranslatorTrait;

    /**
     * get display data for given ids
     *
     * @param string|array $ids
     *
     * @return array
     */
    public static function getDisplayData($ids)
    {
        $rez = [];

        $ids = Util\toNumericArray($ids);
        if (!empty($ids)) {
            if (Cache::exist('UsersGroupsDisplayData')) {
                $cdd = Cache::get('UsersGroupsDisplayData');
            } else {
                $cdd = DataModel\UsersGroups::getDisplayData();
                Cache::set('UsersGroupsDisplayData', $cdd);
            }

            $rez = array_intersect_key($cdd, array_flip($ids));
        }

        return $rez;
    }

    /**
     * Get an array of group ids for specified user.
     * If no user is passed then current logged user is analized.
     * @param bool $user_id
     *
     * @return array
     */
    public static function getGroupIdsForUser($user_id = false)
    {
        if ($user_id === false) {
            $user_id = User::getId();
        }

        $groups = [];

        $dbs = Cache::get('casebox_dbs');

        $res = $dbs->query(
            'SELECT group_id FROM users_groups_association WHERE user_id = $1',
            $user_id
        );

        while ($r = $res->fetch()) {
            $groups[] = $r['group_id'];
        }
        unset($res);

        return $groups;
    }

    /**
     * Change user password.
     *
     * @param array     $p
     * @param bool|true $verify
     *
     * @return array
     * @throws \Exception
     */
    public function changePassword(array $p, $verify = true)
    {
        if (!User::isVerified() && $verify) {
            return ['success' => false, 'verify' => true];
        }

        // Password could be changed by: admin, user owner, user himself
        if (empty($p['password']) || ($p['password'] != $p['confirmpassword'])) {
            throw new \Exception($this->trans('Wrong_input_data'));
        }
        $userId = $this->extractId($p['id']);

        $dbs = Cache::get('casebox_dbs');

        $container = Cache::get('symfony.container');
        $authService = $container->get('casebox_core.service_auth.authentication');

        $rec = DM\Users::read($userId);
        // Check for old password if users changes password for himself
        if (User::getId() == $userId) {
            if (!$authService->verifyUserPassword($rec['name'], $p['currentpassword'])) {
                throw new \Exception($this->trans('WrongCurrentPassword'));
            }
        }

        if (!Security::canEditUser($userId) && $verify) {
            throw new \Exception($this->trans('Access_denied'));
        }

        $em = $container->get('doctrine.orm.entity_manager');
        $user = $em->getRepository('CaseboxCoreBundle:UsersGroups')->findUserByUsername($rec['name']);
        $encoder = $container->get('security.encoder_factory')->getEncoder($user);
        $encodedPass = $encoder->encodePassword($p['password'], $user->getSalt());

        $dbs->query(
            'UPDATE users_groups SET `password` = $2, uid = $3 WHERE id = $1',
            [
                $userId,
                $encodedPass,
                User::getId(),
            ]
        );

        Session::clearUserSessions($userId);

        return ['success' => true];
    }

    /**
     * Send recovery password email for given user id so that the user can set new password and enter the system
     *
     * @param integer|string $userId
     * @param string         $template
     *
     * @return bool
     */
    public static function sendResetPasswordMail($userId, $template = 'recover')
    {
        if ($template !== 'recover') {
            if (!is_numeric($userId) || (User::isLogged() && !Security::canEditUser($userId))) {
                return false;
            }
        }

        $userData = User::getPreferences($userId);
        $userEmail = User::getEmail($userData);

        if (empty($userEmail)) {
            return false;
        }

        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');

        // generating invite hash and sending mail
        $hash = User::generateRecoveryHash($userId, $userId.$userEmail.date(DATE_ISO8601));

        /** @var Container $container */
        $container = Cache::get('symfony.container');

        $config = Cache::get('platformConfig');
        $env = $config['coreName'];
        $baseUrl = $config['server_name'];

        $href = $baseUrl.'c/'.$env.'/recover/reset-password?token='.$hash;

        // Replacing placeholders in template and subject
        $vars = [
            'projectTitle' => $configService->getProjectName(),
            'name' => User::getDisplayName($userData),
            'fullName' => User::getDisplayName($userData),
            'username' => User::getUsername($userData),
            'userEmail' => $userEmail,
            'creatorFullName' => User::getDisplayName(),
            'creatorUsername' => User::getUsername(),
            'creatorEmail' => User::getEmail(),
            'href' => $href,
            'link' => '<a href="'.$href.'" >'.$href.'</a>',
        ];

        $twig = $container->get('twig');

        switch ($template) {
            case 'invite':
                $mail = $twig->render('CaseboxCoreBundle:email:email_invite.html.twig', $vars);
                $subject = self::trans('MailInviteSubject');

                break;

            case 'recover':
                $mail = $twig->render('CaseboxCoreBundle:email:password_recovery_email_en.html.twig', $vars);
                $subject = self::trans('MailRecoverSubject');

                break;

            default:
                return false;
        }

        if (empty($mail)) {
            return false;
        }

        return @System::sendMail($userEmail, $subject, $mail);
    }

    /**
     * Shortcut to previous function to return json response
     *
     * @param int $userId
     *
     * @return array
     */
    public function sendResetPassMail($userId)
    {
        return [
            'success' => $this->sendResetPasswordMail($userId),
        ];
    }

    /**
     * @param integer $userId
     *
     * @return array
     * @throws \Exception
     */
    public function disableTSV($userId)
    {
        if (!User::isVerified()) {
            return ['success' => false, 'verify' => true];
        }

        if (is_nan($userId)) {
            throw new \Exception($this->trans('Wrong_input_data'));
        }

        if (!Security::canEditUser($userId)) {
            throw new \Exception($this->trans('Access_denied'));
        }

        return User::disableTSV($userId);
    }

    /**
     * Rename user
     *
     * @param array $p
     *
     * @return array
     * @throws \Exception
     */
    public function renameUser($p)
    {
        if (!User::isVerified()) {
            return ['success' => false, 'verify' => true];
        }

        /* username could be changed by: admin or user owner */
        $name = trim(strtolower(strip_tags($p['name'])));
        $matches = preg_match('/^[a-z0-9\._]+$/i', $name);

        if (empty($name) || empty($matches)) {
            throw new \Exception($this->trans('Wrong_input_data'));
        }

        $userId = $this->extractId($p['id']);

        if (!Security::canEditUser($userId)) {
            throw new \Exception($this->trans('Access_denied'));
        }

        $o = Objects::getCachedObject($userId);
        $d = $o->getData();
        $d['data']['name'] = $name;
        $d['data']['_title'] = $name;
        $o->update($d);

        $dispatcher = Cache::get('symfony.container')->get('event_dispatcher');
        $dispatcher->dispatch('onSolrTreeUpdate');

        return ['success' => true, 'name' => $name];
    }

    /**
     * Set user enabled or disabled
     *
     * @param array $p
     *
     * @return array
     * @throws \Exception
     */
    public function setUserEnabled($p)
    {
        if (!User::isVerified()) {
            return ['success' => false, 'verify' => true];
        }

        $userId = $this->extractId($p['id']);
        $enabled = !empty($p['enabled']);

        if (!Security::canEditUser($userId)) {
            throw new \Exception($this->trans('Access_denied'));
        }

        User::setEnabled($userId, $enabled);

        return ['success' => true, 'enabled' => $enabled];
    }

    /**
     * Extract numeric id from a tree node prefixed id
     *
     * @param integer $id
     *
     * @return mixed
     * @throws \Exception
     */
    private function extractId($id)
    {
        if (is_numeric($id)) {
            return $id;
        }
        $a = explode('-', $id);
        $id = array_pop($a);
        if (!is_numeric($id) || ($id < 1)) {
            throw new \Exception($this->trans('Wrong_input_data'));
        }

        return $id;
    }
}
