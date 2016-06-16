<?php
namespace Casebox\CoreBundle\Service;

use Symfony\Component\DependencyInjection\Container;
use Casebox\CoreBundle\Service\Cache;
use Casebox\CoreBundle\Service\DataModel as DM;
use Casebox\CoreBundle\Service\Files;
use Casebox\CoreBundle\Traits\TranslatorTrait;

/**
 * Class Config
 */
class Config
{
    use TranslatorTrait;

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var array
     */
    protected $environmentVars = [];

    /**
     * @var array
     */
    protected $plugins = [];

    /**
     * @var int
     */
    public $CORESTATUS_DISABLED = 0;

    /**
     * @var int
     */
    public $CORESTATUS_ACTIVE = 1;

    /**
     * @var int
     */
    public $CORESTATUS_MAINT = 2;

    /**
     * @var array
     */
    protected $flags = [
        'disableTriggers' => false,
        'disableSolrIndexing' => false,
        'disableActivityLog' => false,
    ];

    /**
     * Config constructor
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Method for loading core config
     *
     * @param array $cfg default configuration
     *
     * @return array throw an exception if core is not defined in db
     */
    public function load($cfg = [])
    {
        $coreDBConfig = $this->getCoreDBConfig();

        $propertiesToMerge = ['files'];

        // Detect available languages
        $languages = empty($coreDBConfig['languages']) ? $cfg['languages'] : $coreDBConfig['languages'];

        // prepare language properties to be decoded and merged
        $languages = explode(',', $languages);
        foreach ($languages as $l) {
            $l = 'language_'.$l;
            if (isset($cfg[$l])) {
                $cfg[$l] = Util\toJSONArray($cfg[$l]);
            }
            if (isset($coreDBConfig[$l])) {
                $coreDBConfig[$l] = Util\toJSONArray($coreDBConfig[$l]);
            }
            $propertiesToMerge[] = $l;
        }

        $cfg = $this->mergeConfigs($cfg, $coreDBConfig, $propertiesToMerge);

        $this->config = $this->adjustConfig($cfg);
        $this->environmentVars = $this->getEnvironmentVars($this->config);

        // Set max file version count
        if (isset($this->config['files']['max_versions'])) {
            Files::setMFVC($this->config['files']['max_versions']);
        } elseif (isset($this->config['max_files_version_count'])) {
            // backward compatibility check
            Files::setMFVC($this->config['max_files_version_count']);
        }

        ini_set('error_log', $this->environmentVars['error_log']);

        return $this->config;
    }

    /**
     * Reading configuration file
     * @return array
     */
    public function loadConfigFile($filename)
    {
        if (file_exists($filename)) {
            $rez = parse_ini_file($filename);
        } else {
            throw new \Exception('Can\t load config file: '.$filename, 1);
        }

        return $rez;
    }

    /**
     * get core config stored in database
     *
     * TODO: remove this method after config migration
     * @return array
     */
    private function getCoreDBConfig()
    {
        $rez = [];

        $ref = [];
        $left = [];
        $lastLength = 0;

        $rows = DM\Config::readAll();

        //add root nodes
        foreach ($rows as &$r) {
            if (empty($r['pid'])) {
                $ref[$r['id']] = &$r;
            } else {
                $left[] = &$r;
            }
        }

        while (!empty($left) && (sizeof($left) != $lastLength)) {
            $rows = $left;
            $lastLength = sizeOf($left);
            $left = [];

            foreach ($rows as &$r) {
                if (isset($ref[$r['pid']])) {
                    $p = &$ref[$r['pid']];
                    if (!is_array($p['value'])) {
                        $p['value'] = Util\toJSONArray($p['value']);
                    }

                    $r['value'] = Util\toJSONArray($r['value']);
                    $p['value'][$r['param']] = &$r['value'];
                    $ref[$r['id']] = &$r;

                } else {
                    $left[] = &$r;
                }
            }
        }

        // iterate and collect resulting items
        foreach ($ref as &$r) {
            if (empty($r['pid'])) {
                $rez[$r['param']] = $r['value'];
            }
        }

        return $rez;
    }

    /**
     * get project name from config
     * if cannot be found - coreName is returned
     * @return string
     */
    public function getProjectName()
    {
        $locale = Cache::get('symfony.request')->getLocale();

        $rez = $this->get('project_name_' . $locale);

        if (empty($rez)) {
            $rez = $this->get('project_name');
        }

        if (empty($rez)) {
            $rez = $this->get('project_name_en');
        }

        if (empty($rez)) {
            $rez = $this->get('coreName');
        }

        return $rez;
    }

    /**
     * get environment variables from given config
     * @return array
     */
    private function getEnvironmentVars($config)
    {
        $coreName = $config['coreName'];
        $ds = DIRECTORY_SEPARATOR;

        //$this->container->getParameter(
        $filesDir = $config['cb_files_dir'].$ds;

        $rez = [
            'db_name' => empty($config['db_name']) ? $config['prefix'].$coreName : $config['db_name'],
            'solr_core' => empty($config['solr_core']) ? $config['prefix'].$coreName : $config['solr_core'],
            // path to files folder
            'files_dir' => $filesDir,
            'files_preview_dir' => $filesDir.'preview'.$ds,
            'photos_path' => $filesDir.'_photo'.$ds,
            'core_url' => $config['server_name'].'c/'.$coreName.'/',
            'core_uri' => '/c/'.$coreName.'/',
            //'core_dir' => ??
            'upload_temp_dir' => $filesDir.'_temp',
            /* path to incomming folder. In this folder files are stored when just uploaded
            and before checking existance in target.
            If no user intervention is required then files are stored in db. */
            'incomming_files_dir' => $filesDir.'incomming'.$ds,
            'error_log' => $config['cb_logs_dir'].'/'.$coreName.'_custom.log',
            // custom Error log per Core, use it for debug/reporting purposes
            'debug_log' => $config['cb_logs_dir'].'/'.$coreName.'_debug.log',
        ];

        // Define folder templates
        $rez['folder_templates'] = empty($config['folder_templates']) ? [] : explode(',', $config['folder_templates']);

        $rez['default_folder_template'] = empty($rez['folder_templates']) ? 0 : $rez['folder_templates'][0];

        if (empty($config['default_file_template'])) {
            $a = DM\Templates::getIdsByType('file');
            $rez['default_file_template'] = array_shift($a);
        } else {
            $rez['default_file_template'] = $config['default_file_template'];
        }

        if (empty($config['default_shortcut_template'])) {
            $a = DM\Templates::getIdsByType('shortcut');
            $rez['default_shortcut_template'] = array_shift($a);
        } else {
            $rez['default_shortcut_template'] = $config['default_shortcut_template'];
        }

        foreach ($config as $k => $v) {
            if (( strlen($k) == 11 ) && ( substr($k, 0, 9) == 'language_')) {
                $rez['language_settings'][substr($k, 9)] = Util\toJSONArray($v);
            }
        }

        /* Define Core available languages */
        $rez['languages'] = implode(',', array_keys($rez['language_settings']));

        if (!empty($config['languages'])) {
            $rez['languages'] = Util\toTrimmedArray($config['languages']);

            // define default core language
            if (empty($config['default_language']) || !in_array($config['default_language'], $rez['languages'])) {
                $rez['language'] = $rez['languages'][0];
            } else {
                $rez['language'] = $config['default_language'];
            }
        }

        if (empty($config['languagesUI'])) {
            $rez['languagesUI'] = $rez['languages'];
        } else {
            $rez['languagesUI'] = Util\toTrimmedArray($config['languagesUI']);
        }

        $rez['max_rows'] = empty($config['max_rows']) ? 50 : $config['max_rows'];

        return $rez;
    }

    /**
     * set an environment core varibale
     *
     * @param string $varName
     * @param array  $value
     */
    public function setEnvVar($varName, $value)
    {
        $this->environmentVars[$varName] = $value;
    }

    /**
     * return default columns available for griv view
     * @return array
     */
    public function getDefaultGridViewColumns()
    {
        if (empty($this->defaultGridViewColumns)) {
            $this->defaultGridViewColumns = [
                'nid' => 'ID',
                'name' => $this->trans('Name'),
                'path' => $this->trans('Path'),
                'case' => $this->trans('Project'),
                'date' => $this->trans('Date'),
                'size' => $this->trans('Size'),
                'cid' => $this->trans('Creator'),
                'oid' => $this->trans('Owner'),
                'uid' => $this->trans('UpdatedBy'),
                'comment_user_id' => $this->trans('CommentedBy'),
                'cdate' => $this->trans('CreatedDate'),
                'udate' => $this->trans('UpdatedDate'),
                'comment_date' => $this->trans('CommentedDate'),
                'date_end' => $this->trans('EndDate'),
            ];
        }

        return $this->defaultGridViewColumns;
    }

    /**
     * return default configs for known grid columns
     * @return array
     */
    public function getDefaultGridColumnConfigs()
    {
        if (empty($this->defaultGridColumnConfigs)) {
            $userConfig = Cache::get('session')->get('user')['cfg'];
            $dateFormat = $userConfig['short_date_format'];
            $dateTimeFormat = $dateFormat.' '.$userConfig['time_format'];

            $this->defaultGridColumnConfigs = [
                'nid' => [
                    'title' => 'ID',
                    'width' => 80,
                ],
                'name' => [
                    'title' => $this->trans('Name'),
                    'width' => 300,
                ],
                'path' => [
                    'title' => $this->trans('Path'),
                    'width' => 150,
                ],
                'case' => [
                    'title' => $this->trans('Project'),
                    "solr_column_name" => "case_id",
                    "fieldType" => "_objects",
                    'width' => 150,
                ],
                'date' => [
                    'title' => $this->trans('Date'),
                    'width' => 130,
                    'xtype' => 'datecolumn',
                    'format' => $dateTimeFormat,
                ],
                'size' => [
                    'title' => $this->trans('Size'),
                    'width' => 80,
                ],
                'cid' => [
                    'title' => $this->trans('Creator'),
                    'width' => 200,
                ],
                'oid' => [
                    'title' => $this->trans('Owner'),
                    'width' => 200,
                ],
                'uid' => [
                    'title' => $this->trans('UpdatedBy'),
                    'width' => 200,
                ],
                'did' => [
                    'title' => $this->trans('DeletedBy'),
                    'width' => 200,
                ],
                'comment_user_id' => [
                    'title' => $this->trans('CommentedBy'),
                    'width' => 200,
                ],
                'cdate' => [
                    'title' => $this->trans('CreatedDate'),
                    'width' => 130,
                    'xtype' => 'datecolumn',
                    'format' => $dateTimeFormat,
                ],
                'udate' => [
                    'title' => $this->trans('UpdatedDate'),
                    'width' => 130,
                    'xtype' => 'datecolumn',
                    'format' => $dateTimeFormat,
                ],
                'ddate' => [
                    'title' => $this->trans('DeletedDate'),
                    'width' => 130,
                    'xtype' => 'datecolumn',
                    'format' => $dateTimeFormat,
                ],
                'comment_date' => [
                    'title' => $this->trans('CommentedDate'),
                    'width' => 130,
                    'xtype' => 'datecolumn',
                    'format' => $dateTimeFormat,
                ],
                'date_end' => [
                    'title' => $this->trans('EndDate'),
                    'width' => 130,
                    'xtype' => 'datecolumn',
                    'format' => $dateTimeFormat,
                ],
                'order' => [
                    'title' => $this->trans('Order'),
                    //we shouldnt set solr_column_name by default
                    //because there are templates that could extract values from objects
                    // ,'solr_column_name' => 'order'
                    "align" => "center",
                    "width" => 10,
                    "columnWidth" => 10,

                ],
                'task_u_assignee' => [
                    'title' => $this->trans('Assignee'),
                    'width' => 200,
                ],
                'task_u_started' => [
                    'title' => $this->trans('StartedBy'),
                    'width' => 200,
                ],
                'task_u_ongoing' => [
                    'title' => $this->trans('Ongoing'),
                    'width' => 200,
                ],
                'task_u_done' => [
                    'title' => $this->trans('DoneBy'),
                    'width' => 200,
                ],
                'task_u_blocker' => [
                    'title' => $this->trans('Blocker'),
                    'width' => 200,
                ],
                'task_u_all' => [
                    'title' => $this->trans('All'),
                    'width' => 200,
                ],
                'task_d_closed' => [
                    'title' => $this->trans('ClosedDate'),
                    "solr_column_name" => "task_d_closed",
                    'width' => 130,
                    'xtype' => 'datecolumn',
                    'format' => $dateTimeFormat,
                ],
                'task_status' => [
                    'title' => $this->trans('Status'),
                    'width' => 70,
                ],
            ];
        }

        return $this->defaultGridColumnConfigs;
    }

    private function adjustConfig($cfg)
    {
        //facet definitions defined globally in casebox config
        $dfd = [];

        if (!empty($cfg['default_facet_configs'])) {
            $dfd = Util\toJSONArray($cfg['default_facet_configs']);
            unset($cfg['default_facet_configs']);
        }

        //check if have defined facets in core config
        if (!empty($cfg['facet_configs'])) {
            $dfd = array_merge($dfd, Util\toJSONArray($cfg['facet_configs']));
        }
        $cfg['facet_configs'] = $dfd;

        //transform boolean properties to boolean
        $boolProperties = [
            'allow_duplicates',
        ];

        foreach ($boolProperties as $property) {
            if (isset($cfg[$property])) {
                $cfg[$property] = in_array($cfg[$property], ['true', true, 'y', 1, '1'], true);
            }
        }
        //end of transform boolean properties to boolean

        // detect core plugins (use defined or default if set)
        $plugins = [];
        if (!empty($cfg['default_plugins'])) {
            $plugins = $cfg['default_plugins'];
        }
        if (!empty($cfg['plugins'])) {
            $plugins = Util\toJSONArray($cfg['plugins']);
        }
        $cfg['plugins'] = $plugins;
        // end of detect plugins

        //decode properties of the core config that should be json
        $jsonProperties = [
            'api',
            'css',
            'comments_config',
            'files',
            'js',
            'plugins',
            'listeners',
            'node_facets',
            'node_DC',
            'default_DC',
            'search_DC',
            'default_object_plugins',
            'treeNodes',
            'action_log',
            'maintenance',
            'leftRibbonButtons',
        ];

        foreach ($jsonProperties as $property) {
            if (!empty($cfg[$property])) {
                $arr = Util\toJSONArray($cfg[$property]);

                if (!empty($arr)) {
                    $cfg[$property] = $arr;

                } elseif (substr($property, -3) !== '_DC') { //_DC's could have reference to predefined config sets
                    Cache::get('symfony.container')->get('logger')->error(
                        $cfg['coreName'].': Error parsing json config for property "'.$property.'".'
                    );
                }
            }
        }

        return $cfg;
    }

    /**
     * extend the given $customization from value set in $cutomization["extends"] if present under container
     *
     * @param string $container
     * @param array  $customization
     *
     * @return array
     */
    public function extend($container, $customization)
    {
        $rez = $customization;

        if (!empty($rez['extends'])) {
            $container = $this->get($container);

            if (!empty($container[$rez['extends']])) {
                $rez = array_merge($container[$rez['extends']], $rez);
                unset($rez['extends']);
            }
        }

        return $rez;
    }

    /**
     *
     * @param string $optionName name of the option to get
     *
     * @return array | null
     */
    public function get($optionName, $defaultValue = null)
    {
        if (isset($this->environmentVars[$optionName])) {
            return $this->environmentVars[$optionName];
        }

        if (isset($this->config[$optionName])) {
            return $this->config[$optionName];
        }

        return $defaultValue;
    }

    public function getDCConfig($alias)
    {
        $rez = [];
        $conf = $this->get('DCConfigs');

        if (!empty($conf[$alias])) {
            $rez = $conf[$alias];
        }

        return $rez;
    }

    /**
     * get flag value
     *
     * @param string $name flag name
     *
     * @return array return false if not set
     */
    public function getFlag($name)
    {
        if (isset($this->flags[$name])) {
            return $this->flags[$name];
        }

        return false;
    }

    /**
     * set flag value
     *
     * @param string $name
     * @param array  $value
     *
     * @return array return false if not set
     */
    public function setFlag($name, $value)
    {
        $this->flags[$name] = $value;
    }

    /**
     * Custom function for merging two config arrays
     * This function takes as third param an array of properties that should be merged separately
     * It's evident that these properties should have array values in configs
     *
     * @param array $cfg1
     * @param array $cfg2
     * @param array $properties
     *
     * @return array
     */
    public function mergeConfigs($cfg1, $cfg2, $properties)
    {
        foreach ($cfg2 as $k => $v) {
            if (in_array($k, $properties) && is_array($v)) {
                if (empty($cfg1[$k])) {
                    $cfg1[$k] = [];
                }
                $cfg1[$k] = array_merge($cfg1[$k], $cfg2[$k]);
            } else {
                $cfg1[$k] = $v;
            }
        }

        return $cfg1;
    }
}
