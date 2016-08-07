<?php
namespace Casebox\CoreBundle\Service\Objects\Plugins;

use Casebox\CoreBundle\Service\Cache;
use Casebox\CoreBundle\Service\Objects;
use Casebox\CoreBundle\Service\Templates;
use Casebox\CoreBundle\Service\Util;

/**
 * Class Base
 */
class Base
{
    /**
     * Id of the objects for which the plugin is displayed
     * @var integer|null
     */
    protected $id = null;

    /**
     * Base constructor
     */
    public function __construct($config = [])
    {
        if (!empty($config['objectId'])) {
            $this->id = $config['objectId'];
            unset($config['objectId']);
        }

        $this->config = $config;

        $this->configService = Cache::get('symfony.container')->get('casebox_core.service.config');
    }

    /**
     * Get plugin data for given object id
     * @return array
     */
    public function getData($id = false)
    {
        if ($id === false) {
            $id = $this->id;
        } else {
            $this->setId($id);
        }

        if (!is_numeric($id) || !$this->isVisible()) {
            // ID was not specified
            return null;
        }

        $rez = array(
            'success' => true
        );

        $config = $this->config;

        if (!empty($config['header'])) {
            $h = $config['header'];
            $title = empty($h['title']) ? '' : Util\detectTitle($h['title']);

            if (!empty($h['showTotal'])) {
                $title .= ' ({total})';
            }

            $rez['title'] = $title;

            if (!empty($h['menu'])) {
                $rez['menu'] = $h['menu'];
            }
        }

        return $rez;
    }

    /**
     * check if current plugin is visible according to its config
     * @return boolean
     */
    protected function isVisible()
    {
        $rez = true;

        $config = $this->config;
        $vcfg = empty($config['visibility'])
            ? []
            : $config['visibility'];
        $obj = Objects::getCachedObject($this->id);

        if (!empty($vcfg['fn'])) {
            $rez = $this->getFunctionResult($vcfg['fn']);
        } elseif (!empty($vcfg['fields'])) {
            if (!empty($obj)) {
                foreach ($vcfg['fields'] as $fn => $fv) {
                    if (is_scalar($fv)) {
                        $fv = [$fv];
                    }

                    $val = @$obj->getFieldValue($fn, 0)['value'];
                    $fieldRez = false;

                    foreach ($fv as $v) {
                        if (is_numeric($v)) {
                            $arr = Util\toNumericArray($val);
                            $fieldRez = $fieldRez || in_array($v, $arr);
                        } else {
                            $fieldRez = $fieldRez || ($v == $val);
                        }
                    }

                    $rez = $rez && $fieldRez;
                }
            }
        }

        $template = false;
        if (!empty($obj)) {
            $template = $obj->getTemplate();
        } elseif (!empty($this->config['template_id'])) {
            $template = Templates\SingletonCollection::getInstance()->getTemplate($this->config['template_id']);
        }

        if (!empty($template)) {
            $ttype = $template->getType();

            //check if template_type is specified
            if ($rez && !empty($vcfg['template_type'])) {
                $tt = Util\toTrimmedArray($vcfg['template_type']);
                $rez = in_array($ttype, $tt);
            }

            //check if template_type negation is specified
            if ($rez && !empty($vcfg['!template_type'])) {
                $tt = Util\toTrimmedArray($vcfg['!template_type']);
                $rez = !in_array($ttype, $tt);
            }
        }

        //check if context is specified
        if ($rez && !empty($vcfg['context'])) {
            $context = Util\toTrimmedArray($vcfg['context']);
            $rez = in_array($config['context'], $context);
        }

        //check if context negation is specified
        if ($rez && !empty($vcfg['!context'])) {
            $context = Util\toTrimmedArray($vcfg['!context']);
            $rez = !in_array($config['context'], $context);
        }

        return $rez;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        if ($this->id != $id) {
            unset($this->objectClass);
        }
        $this->id = $id;
    }

    /**
     * @return null|object
     */
    protected function getObjectClass()
    {
        $rez = null;

        if (empty($this->objectClass) && !empty($this->id)) {
            $this->objectClass = Objects::getCachedObject($this->id);
            $rez = &$this->objectClass;
        }

        return $rez;
    }

    protected function getFunctionResult($fn)
    {
        $t = explode('.', $fn);

        if (!class_exists($t[0])) {
            return [];
        }

        $class = new $t[0];
        $method = $t[1];

        $params = [
            'id' => $this->id
            ,'config' => $this->config
        ];

        return $class->$method($params);
    }
}
