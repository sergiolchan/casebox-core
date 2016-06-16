<?php
namespace Casebox\CoreBundle\Service;

use Casebox\CoreBundle\Service\Vocabulary\CountryPhoneCodesVocabulary;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class System
 */
class System
{
    /**
     * @param Container $container
     * @param Request   $request
     */
    public function bootstrap(Container $container, Request $request = null)
    {
        if (empty($request)) {
            $request = new Request();
            $session = $container->get('session');
            $coreName = $container->getParameter('kernel.environment');

        } else {
            $session = $request->getSession();
            $coreName = $request->attributes->get('coreName');
        }

        if (!empty($coreName)) {
            $session->set('coreName', $coreName);

            // We need to access config so we can connect to DB the old way
            // while we're not moved to Doctrine yet
            $platformConfig = $container->getParameterBag()->all();

            $platformConfig['coreName'] = $coreName;

            if (empty($platformConfig['prefix'])) {
                $platformConfig['prefix'] = 'cb';
            }

            $platformConfig['prefix'] .= '_';
            $platformConfig['is_windows'] = (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN');

            Cache::set('platformConfig', $platformConfig);
            Cache::set('session', $session);
            Cache::set('symfony.container', $container);
            Cache::set('symfony.request', $request);

            require_once dirname(__DIR__).'/Service/Util.php';

            // Database connection
            $dbs = $container->get('casebox_core.service.db');
            $dbs->connectWithParams($platformConfig);

            // Set database service handler into cache for quick access
            Cache::set('casebox_dbs', $dbs);

            $configService = Cache::get('symfony.container')->get('casebox_core.service.config');

            // Loading full config of the core
            $configs = $configService->load($platformConfig);
            foreach ($configs as $key => $config) {
                Cache::set($key, $config);
            }

            // Process user locale
            $user = $session->get('user');
            $language = (!empty($user['language'])) ? $user['language'] : $request->getLocale();
            $request->setLocale($language);
        }
    }

    /**
     * Get countries list with their phone codes
     * This function returns an array of records for arrayReader
     *     first column is id
     *     second is name
     *     third is phone code
     * @return array
     */
    public function getCountries()
    {
        $voc = new CountryPhoneCodesVocabulary();
        $countries = $voc->getTerms();

        return ['success' => true, 'data' => $countries];
    }

    /**
     * Get defined timezones
     * Returns an array of records for arrayReader
     * record contains three fields: timezone, gmt offset, caption
     * @return array
     */
    public function getTimezones()
    {

        $timezones = [];
        $offsets = [];
        $now = new \DateTime();

        foreach (\DateTimeZone::listIdentifiers() as $timezone) {
            $now->setTimezone(new \DateTimeZone($timezone));
            $offsets[] = $offset = $now->getOffset();
            $timezones[$timezone] = [
                $timezone,
                $offset,
                '(GMT'.$this->formatGmtOffset($offset).') '.$this->formatTimezoneName($timezone),
            ];
        }

        array_multisort($offsets, $timezones);

        return ['success' => true, 'data' => array_values($timezones)];
    }

    /**
     * Check a given timezon to be valid
     *
     * @param tring $timezone valid php timezone
     *
     * @return boolean
     */
    public static function isValidTimezone($timezone)
    {
        return in_array($timezone, \DateTimeZone::listIdentifiers());
    }

    /**
     * Get gmt offset in minutes
     *
     * @param string $timezone php compatible timezone
     *
     * @return int
     */
    public static function getGmtOffset($timezone)
    {
        $now = new \DateTime();
        if (System::isValidTimezone($timezone)) {
            $now->setTimezone(new \DateTimeZone($timezone));
        }

        return ($now->getOffset() / 60);
    }

    /**
     * @param float|integer $offset
     *
     * @return string
     */
    public function formatGmtOffset($offset)
    {
        $hours = intval($offset / 3600);
        $minutes = abs(intval($offset % 3600 / 60));

        return ($offset ? sprintf('%+03d:%02d', $hours, $minutes) : '');
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function formatTimezoneName($name)
    {
        $name = str_replace('/', ', ', $name);
        $name = str_replace('_', ' ', $name);
        $name = str_replace('St ', 'St. ', $name);

        return $name;
    }

    /**
     * Admin notification by mail method
     *
     * @param string $subject
     * @param string $body
     *
     * @return boolean
     */
    public static function notifyAdmin($subject, $body)
    {
        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');

        return static::sendMail($configService->get('admin_email'), $subject, $body);
    }

    /**
     * Common send mail function
     *
     * @param string $email
     * @param string $subject
     * @param string $body
     *
     * @return boolean
     */
    public static function sendMail($email, $subject, $body)
    {
        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');
        $coreName = $configService->get('core_name');
        $sender = $configService->get('sender_email');
        $sender = "\"$sender ($coreName)\" <$sender>";
        $header = "Content-type: text/html; charset=utf-8\r\nFrom: ".$sender."\r\n";

        return mail($email, $subject, $body, $header);
    }
}
