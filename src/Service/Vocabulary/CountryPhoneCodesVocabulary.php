<?php

namespace Casebox\CoreBundle\Service\Vocabulary;

use Casebox\CoreBundle\Service\Cache;
use Symfony\Component\DependencyInjection\Container;

/**
 * Class CountryPhoneCodesVocabulary
 */
class CountryPhoneCodesVocabulary implements VocabularyInterface
{
    const CACHE_KEY = 'country_phone_codes';

    /**
     * @var Container
     */
    protected $container;

    /**
     * TimezoneVocabulary constructor
     */
    public function __construct(Container $container = null)
    {
        if ($container instanceof Container) {
            $this->container = $container;
        } else {
            $this->container = Cache::get('symfony.container');
        }
    }

    /**
     * Load and return all terms
     * @return array
     */
    public function getTerms()
    {
        $redis = $this->container->get('casebox_core.service.redis_service');

        if (empty($redis->get(self::CACHE_KEY))) {
            $file = __DIR__.'/mockup/country_phone_codes.json';
            $terms = json_decode(file_get_contents($file), true);

            $redis->set(self::CACHE_KEY, serialize($terms), 60 * 24);
        } else {
            $terms = unserialize($redis->get(self::CACHE_KEY));
        }

        return $terms;
    }

    /**
     * Find term by id
     * @param string $id
     *
     * @return array|null
     */
    public function getTermById($id)
    {
        $terms = $this->getTerms();

        $result = null;

        foreach ($terms as $key => $term) {
            if ($term['id'] == $id) {
                return $term;
            }
        }

        return $result;
    }

    /**
     * Find by contry code
     * @param string $code
     *
     * @return array|null
     */
    public function findByCountryCode($code)
    {
        $terms = $this->getTerms();

        $result = null;

        foreach ($terms as $key => $term) {
            if ($term['country_code'] == $code) {
                $result = $terms[$key];
            }
        }

        return $result;
    }
}
