<?php
namespace Casebox\CoreBundle\Service\Auth;

use Casebox\CoreBundle\Entity\UsersGroups as UsersGroupsEntity;
use Casebox\CoreBundle\Service\User;
use Casebox\CoreBundle\Service\UsersGroups;
use Casebox\CoreBundle\Service\Security;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

/**
 * Class CaseboxAuth
 */
class CaseboxAuth
{
    /**
     * @var Container
     */
    private $container;

    /**
     * Authentication constructor
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @return EntityManager
     * @throws \Exception
     */
    public function getEm()
    {
        return $this->container->get('doctrine.orm.entity_manager');
    }

    /**
     * @return EncoderFactoryInterface
     * @throws \Exception
     */
    public function getEncoderFactoryInterface()
    {
        return $this->container->get('security.encoder_factory');
    }

    /**
     * @return TokenStorageInterface
     * @throws \Exception
     */
    public function getSecurityContext()
    {
        return $this->container->get('security.token_storage');
    }

    /**
     * @return Session
     * @throws \Exception
     */
    public function getSession()
    {
        return $this->container->get('session');
    }

    /**
     * @param string $username
     * @param string $password
     *
     * @return bool
     * @throws \Exception
     */
    public function authenticate($username, $password)
    {
        if (!$this->verifyUserPassword($username, $password)) {
            return null;
        }

        $user = $this->getEm()->getRepository('CaseboxCoreBundle:UsersGroups')->findUserByUsername($username);

        $roles =  $user->getRoles();
        if (empty($roles)) {
            $roles = [UsersGroupsEntity::ROLE_USER => UsersGroupsEntity::ROLE_USER];
        }

        $this->getSession()->start();

        $env = $this->container->getParameter('kernel.environment');
        $token = new UsernamePasswordToken($user, $password, $env, $roles);
        $this->getSecurityContext()->setToken($token);

        $session = $this->getSession();
        $session->set('_security_main', serialize($token));

        $data = $this->getSessionData($user->getId());

        $session->set('user', $data);

        $session->save();

        return $user;
    }

    /**
     * verify user password
     * @param  string $username
     * @param  string $password
     * @return bool
     */
    public function verifyUserPassword($username, $password)
    {
        $user = $this->getEm()->getRepository('CaseboxCoreBundle:UsersGroups')->findUserByUsername($username);

        if (!$user instanceof UsersGroupsEntity) {
            return false;
        }

        if (strlen($user->getPassword()) <= 32) {
            // Old password behavior
            $encodedPass = md5('aero'.$password);
        } else {
            $encoder = $this->getEncoderFactoryInterface()->getEncoder($user);
            $encodedPass = $encoder->encodePassword($password, $user->getSalt());
        }

        return ($encodedPass == $user->getPassword());
    }

    /**
     * @return bool|true
     */
    public function logout()
    {
        $anonToken = new AnonymousToken('theTokensKey', 'anon.', []);
        $this->getSecurityContext()->setToken($anonToken);
        $this->getSession()->invalidate();

        return true;
    }

    public function getSessionData($userId)
    {
        $rez = User::getPreferences($userId);

        if (!empty($rez)) {
            $rez['admin'] = Security::isAdmin($userId);
            $rez['manage'] = Security::canManage($userId);

            $rez['first_name'] = htmlentities($rez['first_name'], ENT_QUOTES, 'UTF-8');
            $rez['last_name'] = htmlentities($rez['last_name'], ENT_QUOTES, 'UTF-8');

            //set default theme
            if (empty($rez['cfg']['theme'])) {
                $rez['cfg']['theme'] = 'classic';
            }

            // do not expose security params
            unset($rez['cfg']['security']);

            // set user groups
            $rez['groups'] = UsersGroups::getGroupIdsForUser();
        }

        return $rez;
    }

    /**
     * @param bool $throw Throw an exception if user nor found
     *
     * @return UsersGroupsEntity
     * @throws \Exception
     */
    public function isLogged($throw = true)
    {
        $user = null;

        if (!$this->getSecurityContext() instanceof TokenStorage) {
            throw new \Exception('User not authenticated.', 401);
        }

        $token = $this->getSecurityContext()->getToken();

        if (!is_null($token)) {
            $user = $token->getUser();
        }

        if ($throw && !$user instanceof UsersGroupsEntity) {
            throw new \Exception('User not authenticated.', 401);
        }

        if (!$user instanceof UsersGroupsEntity) {
            $user = null;
        }

        return $user;
    }

    /**
     * @param  string      $password Plain password
     * @param  string|null $salt
     * @return array
     */
    public function getEncodedPasswordAndSalt($password, $salt = null)
    {
        if (empty($salt)) {
            $salt = $this->generateSalt();
        }
        $encoder = $this->getEncoderFactoryInterface()->getEncoder(new UsersGroupsEntity());

        return $encoder->encodePassword($password, $salt);
    }

    /**
     * @return string
     */
    public function generateSalt()
    {
        return md5(uniqid(null, true));
    }
}
