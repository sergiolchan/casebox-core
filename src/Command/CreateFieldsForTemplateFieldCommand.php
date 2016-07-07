<?php
namespace Casebox\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Casebox\CoreBundle\Service\Cache;
use Casebox\CoreBundle\Service\DataModel as DM;
use Casebox\CoreBundle\Service\Objects;
use Casebox\CoreBundle\Service\System;
use Casebox\CoreBundle\Service\Templates;
use Casebox\CoreBundle\Service\User;
use Casebox\CoreBundle\Service\Util;

/**
 * Class CreateFieldsForTemplateField
 *
 * command to create all necessary fields for template field, if not already exists
 */
class CreateFieldsForTemplateFieldCommand extends ContainerAwareCommand
{
    /**
     * existing fields:
     *     _title
     *     en
     *     fr
     *     ro
     *     ru
     *     ...
     *     type
     *     order
     *     cfg
     *     solr_column_name
     */

    /**
     * target field structure
     *     _title
     *     en
     *     fr
     *     ro
     *     ru
     *     ...
     *     hintIndexName
     *     readOnly:
     *     required:
     *     value: - default value
     *     multiValued:
     *     maxInstances:
     *     "dependency": {
     *         "pidValues": []
     *     }
     *     indexed: true/false
     *     solr_field:
     *     "type"
     *         source
     *         scope
     *         "descendants"
     *         renderer
     *         editor - specific for different field types
     *         format
     *         decimalPrecision
     *         height
     *         maxLength
     *         mentionUsers
     *    "manualConfig"
     */

    private $fieldTemplateFields = [
        '_title' => [
            'updateOnly' => true
            ,'data' => [
                'order' => 10
            ]
        ],
        'en' => [
            'updateOnly' => true
            ,'data' => [
                'indexed' => true,
                'solrField' => 'title_en_t',
                'solr_column_name' => 'title_en_t',
                'order' => 20
            ]
        ],
        'fr' => [
            'updateOnly' => true
            ,'data' => [
                'order' => 30
            ]
        ],
        'ru' => [
            'updateOnly' => true
            ,'data' => [
                'order' => 40
            ]
        ],
        'hintIndexName' => [
            'data' => [
                '_title' => 'hintIndexName',
                'en' => 'Hint translation index name',
                'type' => 'varchar',
                'order' => 50,
            ]
        ],
        'readOnly' => [
            'data' => [
                '_title' => 'readOnly',
                'en' => 'Read only',
                'type' => 'combo',
                'cfg' => [
                    'source' => 'yesno'
                ],
                'order' => 60
            ]
        ],
        'required' => [
            'data' => [
                '_title' => 'required',
                'en' => 'Required',
                'type' => 'combo',
                'cfg' => [
                    'source' => 'yesno'
                ],
                'order' => 70
            ]
        ],
        'value' => [
            'data' => [
                '_title' => 'value',
                'en' => 'Default value',
                'type' => 'varchar',
                'order' => 80,
            ]
        ],
        'multiValued' => [
            'data' => [
                '_title' => 'multiValued',
                'en' => 'Multivalued',
                'type' => 'combo',
                'cfg' => '{"source":"yesno"}',
                'order' => 90
            ]
        ],
        'maxInstances' => [
            'data' => [
                '_title' => 'maxInstances',
                'en' => 'Max instances',
                'type' => 'int',
                'order' => 100
            ]
        ],
        'dependency' => [
            'data' => [
                '_title' => 'dependency',
                'en' => 'Dependent field',
                'type' => 'combo',
                'cfg' => '{"source":"yesno"}',
                'order' => 110
            ]
            ,'children' => [
                'pidValues' => [
                    'data' => [
                        '_title' => 'pidValues',
                        'en' => 'Display for parent values',
                        'type' => 'varchar',
                        'cfg' => [
                            "dependency" => [
                                "pidValues" => 1
                            ]
                        ],
                        'order' => 120
                    ]
                ],

            ]
        ],
        'indexed' => [
            'data' => [
                '_title' => 'indexed',
                'en' => 'Indexed in solr',
                'type' => 'combo',
                'cfg' => '{"source":"yesno"}',
                'order' => 130
            ]
        ],
        'solrField' => [
            'data' => [
                '_title' => 'solrField',
                'en' => 'Solr field name',
                'type' => 'varchar',
                'order' => 140
            ]
        ],
        'type' => [
            'data' => [
                '_title' => 'type',
                'en' => 'Type',
                'type' => 'combo',
                'cfg' => '{"source":"fieldTypes"}',
                'order' => 150
            ]
            ,'children' => [
                'scope' => [
                    'data' => [
                        '_title' => 'scope',
                        'en' => 'Values scope',
                        'type' => 'combo',
                        'cfg' => [
                            "source" => "objectFieldScopes",
                            "dependency" => [
                                "pidValues" => ["_objects"]
                            ]
                        ],
                        'order' => 160
                    ]
                    ,'children' => [
                        'customIds' => [
                            'data' => [
                                '_title' => 'customIds',
                                'en' => 'Ids (comma separated)',
                                'type' => 'varchar',
                                'cfg' => [
                                    "dependency" => [
                                        "pidValues" => ["custom"]
                                    ]
                                ],
                                'order' => 165
                            ]
                        ]
                    ]
                ],
                'descendants' => [
                    'data' => [
                        '_title' => 'descendants',
                        'en' => 'Descendants',
                        'type' => 'combo',
                        'cfg' => [
                            "source" => "yesno",
                            "dependency" => [
                                "pidValues" => ["_objects"]
                            ]
                        ],
                        'order' => 170
                    ]
                ],
                'source' => [
                    'data' => [
                        '_title' => 'source',
                        'en' => 'Values source',
                        'type' => 'combo',
                        'cfg' => [
                            "source" => "objectFieldSources",
                            "dependency" => [
                                "pidValues" => ["_objects", "combo"]
                            ]
                        ],
                        'order' => 180
                    ]
                    ,'children' => [
                        'facetName' => [
                            'data' => [
                                '_title' => 'facetName',
                                'en' => 'Facet config name',
                                'type' => 'varchar',
                                'cfg' => [
                                    "dependency" => [
                                        "pidValues" => ["facet"]
                                    ],
                                ],
                                'order' => 185
                            ]
                        ],
                        'templateIds' => [
                            'data' => [
                                '_title' => 'templateIds',
                                'en' => 'Filter value templates',
                                'type' => '_objects',
                                'cfg' => [
                                    "multiValued" => true,
                                    "editor" => "form",
                                    "template_types" => "template",
                                    "dependency" => [
                                        "pidValues" => ["tree", "facet"]
                                    ],
                                ],
                                'order' => 190
                            ]
                        ],
                        'templateTypes' => [
                            'data' => [
                                '_title' => 'templateTypes',
                                'en' => 'Filter value template types',
                                'type' => '_objects',
                                'cfg' => [
                                    "multiValued" => true,
                                    "editor" => "form",
                                    "source" => "templateTypes",
                                    "dependency" => [
                                        "pidValues" => ["tree", "facet"]
                                    ],
                                ],
                                'order' => 200
                            ]
                        ],
                        'solQuery' => [
                            'data' => [
                                '_title' => 'solQuery',
                                'en' => 'Solr query (default: "*:*")',
                                'type' => 'varchar',
                                'cfg' => [
                                    "dependency" => [
                                        "pidValues" => ["tree", "facet"]
                                    ],
                                ],
                                'order' => 210
                            ]
                        ],
                        'solrFq' => [
                            'data' => [
                                '_title' => 'solrFq',
                                'en' => 'Solr filter query',
                                'type' => 'memo',
                                'cfg' => [
                                    "validator" => 'json',
                                    "dependency" => [
                                        "pidValues" => ["tree", "facet"]
                                    ],
                                ],
                                'order' => 220
                            ]
                        ],
                        'solrOrder' => [
                            'data' => [
                                '_title' => 'solrOrder',
                                'en' => 'Solr ordering ("name ASC")',
                                'type' => 'varchar',
                                'cfg' => [
                                    "dependency" => [
                                        "pidValues" => ["tree", "facet"]
                                    ],
                                ],
                                'order' => 230
                            ]
                        ],
                        'fieldName' => [
                            'data' => [
                                '_title' => 'fieldName',
                                'en' => 'Field name',
                                'type' => 'varchar',
                                'cfg' => [
                                    "dependency" => [
                                        "pidValues" => ["field"]
                                    ],
                                ],
                                'order' => 240
                            ]
                        ],
                        'customUrl' => [
                            'data' => [
                                '_title' => 'customUrl',
                                'en' => 'Url',
                                'type' => 'varchar',
                                'cfg' => [
                                    "dependency" => [
                                        "pidValues" => ["custom"]
                                    ],
                                ],
                                'order' => 250
                            ]
                        ],
                        'customFn' => [
                            'data' => [
                                '_title' => 'customFn',
                                'en' => 'Function name (\namespace\Class.functionName)',
                                'type' => 'varchar',
                                'cfg' => [
                                    "dependency" => [
                                        "pidValues" => ["custom"]
                                    ],
                                ],
                                'order' => 260
                            ]
                        ],
                        'customSource' => [
                            'data' => [
                                '_title' => 'customSource',
                                'en' => 'Custom source name (defined in CB.DB namespace)',
                                'type' => 'varchar',
                                'cfg' => [
                                    "dependency" => [
                                        "pidValues" => ["custom"]
                                    ],
                                ],
                                'order' => 270
                            ]
                        ],
                    ]
                ],
                'renderer' => [
                    'data' => [
                        '_title' => 'renderer',
                        'en' => 'Renderer',
                        'type' => 'combo',
                        'cfg' => [
                            "source" => "objectFieldRenderers",
                            "dependency" => [
                                "pidValues" => ["_objects"]
                            ]
                        ],
                        'order' => 280
                    ]
                ],
                'objectsEditor' => [
                    'data' => [
                        '_title' => 'objectsEditor',
                        'en' => 'Editor',
                        'type' => 'combo',
                        'cfg' => [
                            "source" => "objectFieldEditors",
                            "dependency" => [
                                "pidValues" => ["_objects"]
                            ]
                        ],
                        'order' => 290
                    ]
                ],
                'timeFormat' => [
                    'data' => [
                        '_title' => 'timeFormat',
                        'en' => 'Format',
                        'type' => 'varchar',
                        'cfg' => [
                            "dependency" => [
                                "pidValues" => ["time"]
                            ]
                        ],
                        'order' => 300
                    ]
                ],
                'floatPrecision' => [
                    'data' => [
                        '_title' => 'floatPrecision',
                        'en' => 'Decimal precision',
                        'type' => 'int',
                        'cfg' => [
                            "value" => 2,
                            "dependency" => [
                                "pidValues" => ["float"]
                            ]
                        ],
                        'order' => 310
                    ]
                ],
                'memoHeight' => [
                    'data' => [
                        '_title' => 'memoHeight',
                        'en' => 'Height',
                        'type' => 'int',
                        'cfg' => [
                            "dependency" => [
                                "pidValues" => ["memo"]
                            ]
                        ],
                        'order' => 320
                    ]
                ],
                'memoMaxLength' => [
                    'data' => [
                        '_title' => 'memoMaxLength',
                        'en' => 'Max length',
                        'type' => 'int',
                        'cfg' => [
                            "dependency" => [
                                "pidValues" => ["memo"]
                            ]
                        ],
                        'order' => 330
                    ]
                ],
                'memoMentionUsers' => [
                    'data' => [
                        '_title' => 'memoMentionUsers',
                        'en' => 'Use prugin for mentioning users',
                        'type' => 'combo',
                        'cfg' => [
                            "source" => "yesno",
                            "dependency" => [
                                "pidValues" => ["memo"]
                            ]
                        ],
                        'order' => 340
                    ]
                ],
                'textEditor' => [
                    'data' => [
                        '_title' => 'textEditor',
                        'en' => 'Editor',
                        'type' => 'combo',
                        'cfg' => [
                            "source" => "textFieldEditors",
                            "dependency" => [
                                "pidValues" => ["text"]
                            ]
                        ],
                        'order' => 350
                    ]
                ],
                'geoPointEditor' => [
                    'data' => [
                        '_title' => 'geoPointEditor',
                        'en' => 'Editor',
                        'type' => 'combo',
                        'cfg' => [
                            "source" => "geoPointFieldEditors",
                            "dependency" => [
                                "pidValues" => ["geoPoint"]
                            ]
                        ],
                        'order' => 360
                    ]
                    ,'children' => [
                        'geoPointTilesUrl' => [
                            'data' => [
                                '_title' => 'geoPointTilesUrl',
                                'en' => 'Tiles url (https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png)',
                                'type' => 'varchar',
                                'cfg' => [
                                    "dependency" => [
                                        "pidValues" => ["form"]
                                    ]
                                ],
                                'order' => 370
                            ]
                        ],
                        'geoPointDefaultLocation' => [
                            'data' => [
                                '_title' => 'geoPointDefaultLocation',
                                'en' => 'Default location',
                                'type' => 'H',
                                'cfg' => [
                                    "dependency" => [
                                        "pidValues" => ["form"]
                                    ]
                                ],
                                'order' => 380
                            ]
                            ,'children' => [
                                'geoPointDefaultLat' => [
                                    'data' => [
                                        '_title' => 'geoPointDefaultLat',
                                        'en' => 'Latitude',
                                        'type' => 'float',
                                        'cfg' => [
                                            "floatPrecision" => 5
                                        ],
                                        'order' => 390
                                    ]
                                ],
                                'geoPointDefaultLng' => [
                                    'data' => [
                                        '_title' => 'geoPointDefaultLng',
                                        'en' => 'Longitude',
                                        'type' => 'float',
                                        'cfg' => [
                                            "floatPrecision" => 5
                                        ],
                                        'order' => 400
                                    ]
                                ],
                                'geoPointDefaultZoom' => [
                                    'data' => [
                                        '_title' => 'geoPointDefaultZoom',
                                        'en' => 'Zoom',
                                        'type' => 'int',
                                        'cfg' => [
                                            "value" => 3
                                        ],
                                        'order' => 410
                                    ]
                                ],
                            ]
                        ],

                    ]
                ],
            ]
        ],
        'order' => [
            'data' => [
                '_title' => 'order',
                'en' => 'Order',
                'type' => 'int',
                'order' => 420
            ]
        ],
        'editMode' => [
            'data' => [
                '_title' => 'editMode',
                'en' => 'Editing mode',
                'type' => 'combo',
                'cfg' => [
                    "source" => 'fieldEditModes'
                ],
                'order' => 430
            ]
        ],
        'validator' => [
            'data' => [
                '_title' => 'validator',
                'en' => 'Validator',
                'type' => 'combo',
                'cfg' => [
                    "source" => 'fieldValidators'
                ],
                'order' => 440
            ]
        ],
        'cfg' => [
            'data' => [
                '_title' => 'cfg',
                'en' => 'Manual configurations',
                'type' => 'memo',
                'cfg' => [
                    "heigth" => 100,
                    "editMode" => "standalone",
                    "validator" => "json",
                ],
                'order' => 450
            ]
        ],
    ];

    /**
     * Configure
     */
    protected function configure()
    {
        $this
            ->setName('casebox:migrate:templatefieldconfigs')
            ->setDescription('Migrate template field configurations into separate fields.');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();

        $system = new System();
        $system->bootstrap($container);

        $configService = $container->get('casebox_core.service.config');

        $configService->setFlag('disableActivityLog', 1);

        //set preudo user id because we get into error for some queries without creator id
        Cache::get('session')->set('user', ['id' => 1]);

        Cache::set('is_admin' . User::getId(), true);

        $ids = DM\Templates::getIdsByType('field');
        $this->fieldTemplateId = array_shift($ids);
        if ($this->fieldTemplateId) {
            $output->writeln('<info>[x] Template field found.</info>');
        } else {
            $output->writeln('<info>[x] Cant find field template.</info>');
            die();
        }

        $this->updateFieldsTemplate();
        $output->writeln('<info>[x] Fields template created/updated.</info>');

        // $this->migrateTemplateFieldConfigs();
        // $output->writeln('<info>[x] Template fields configs migrated to new structure.</info>');
        return;
    }

    protected function updateFieldsTemplate()
    {
        //rename solr_column_name field into solrField
        $id = Objects::getChildId($this->fieldTemplateId, 'solr_column_name');
        if ($id) {
            $fieldClass = new Objects\TemplateField();
            $fieldClass->load($id);
            $d = $fieldClass->getData();
            $d['name'] = 'solrField';
            $d['data']['_title'] = 'solrField';
            $fieldClass->update($d);
        }

        // iterate and create/update fields
        $this->recursiveCreateFieldsIf($this->fieldTemplateFields, $this->fieldTemplateId);
    }

    protected function recursiveCreateFieldsIf($fields, $pid)
    {
        //iterating field list and create fields that are missing
        $fieldClass = new Objects\TemplateField();
        foreach ($fields as $key => $field) {
            $id = Objects::getChildId($pid, $key);

            if (!empty($field['data']['cfg']) && is_array($field['data']['cfg'])) {
                $field['data']['cfg'] = Util\jsonEncode($field['data']['cfg']);
            }

            if (!empty($id)) {
                $fieldClass->load($id);
                $d = $fieldClass->getData();
                $d['name'] = $key;
                $d['data'] = array_merge($d['data'], $field['data']);
                $fieldClass->update($d);

            } elseif (empty($field['updateOnly'])) {
                $id = $fieldClass->create(
                    [
                        'id' => null,
                        'pid' => $pid,
                        'template_id' => $this->fieldTemplateId,
                        'name' => $key,
                        'data' => $field['data'],
                    ]
                );
            }

            if (!empty($field['children'])) {
                $this->recursiveCreateFieldsIf($field['children'], $id);
            }
        }
    }

    protected function migrateTemplateFieldConfigs()
    {
        $fields = DM\TemplatesStructure::getFields();

        foreach ($fields as $r) {
            $cfg = $r['cfg'];

            $or = DM\Objects::read($r['id']);
            $data = $or['data'];

            if (!empty($cfg['readOnly'])) {
                $data['readOnly'] = 1;
            }
            unset($cfg['readOnly']);

            if (!empty($cfg['required'])) {
                $data['required'] = 1;
            }
            unset($cfg['required']);

            if (!empty($cfg['value'])) {
                $data['value'] = $cfg['value'];
            }
            unset($cfg['value']);

            if (!empty($cfg['multiValued'])) {
                $data['multiValued'] = 1;
            }
            unset($cfg['multiValued']);

            if (!empty($cfg['maxInstances'])) {
                $data['maxInstances'] = $cfg['maxInstances'];
            }
            unset($cfg['maxInstances']);

            if (!empty($cfg['dependency'])) {
                $data['dependency'] = 1;
            }

            if (!empty($cfg['dependency']['pidValues'])) {
                $data['dependency'] = [
                    'value' => 1,
                    'childs' => [
                        'pidValues' => is_array($cfg['dependency']['pidValues'])
                            ? implode(',', $cfg['dependency']['pidValues'])
                            : $cfg['dependency']['pidValues']
                    ]
                ];
            }
            unset($cfg['dependency']);

            if (!empty($cfg['indexed']) || !empty($cfg['faceted'])) {
                $data['indexed'] = 1;
            }
            unset($cfg['indexed']);
            unset($cfg['faceted']);

            if (!empty($r['solr_column_name'])) {
                $data['solrField'] = $r['solr_column_name'];
            }

            if (!empty($r['type'])) {
                $data['type'] = $r['type'];
            }

            switch ($data['type']) {
                case 'combo':
                case '_objects':
                    $data['type'] = [
                        'value' => $data['type']
                    ];

                    if (!empty($cfg['scope'])) {
                        $scope = Util\toNumericArray($cfg['scope']);
                        if (!empty($scope)) {
                            $data['type']['childs']['scope'] = [
                                'value' => 'custom',
                                'childs' => [
                                    'customIds' => implode(',', $scope)
                                ]
                            ];

                        } else {
                            $data['type']['childs']['scope'] = $cfg['scope'];
                        }
                    }

                    if (!empty($cfg['descendants'])) {
                        $data['type']['childs']['descendants'] = 1;
                    }

                    if (empty($cfg['source']) || ($cfg['source'] == 'tree') || !empty($cfg['source']['facet'])) {
                        if (!empty($cfg['source']['facet'])) {
                            $data['source']['childs']['facetName'] = $cfg['source']['facet'];
                        }

                        if (!empty($cfg['templates'])) {
                            $data['source']['childs']['templateIds'] = is_array($cfg['templates'])
                                ? implode(',', $cfg['templates'])
                                : $cfg['templates'];
                        }
                        if (!empty($cfg['template_types'])) {
                            $data['source']['childs']['templateTypes'] = is_array($cfg['template_types'])
                                ? implode(',', $cfg['template_types'])
                                : $cfg['template_types'];
                        }
                        if (!empty($cfg['query'])) {
                            $data['source']['childs']['solrQuery'] = $cfg['query'];
                        }
                        if (!empty($cfg['fq'])) {
                            $data['source']['childs']['solrFq'] = Util\jsonEncode($cfg['fq']);
                        }
                        if (!empty($cfg['order'])) {
                            $data['source']['childs']['solrOrder'] = is_array($cfg['order'])
                                ? implode(',', $cfg['order'])
                                : $cfg['order'];
                        }

                        if (!empty($data['source'])) {
                            $data['source']['value'] = empty($cfg['source']['facet'])
                                ? 'tree'
                                : 'facet';
                        }
                        unset($cfg['source']);
                    }

                    if (!empty($cfg['source'])) {
                        $data['type']['childs']['source'] = $cfg['source'];
                        switch ($cfg['source']) {
                            case 'field':
                                if (!empty($cfg['field'])) {
                                    $data['type']['childs']['source'] = [
                                        'value' => 'field',
                                        'childs' => [
                                            'fieldName' => $cfg['field']
                                        ]
                                    ];
                                }
                                break;

                            default: //custom source name from CB.DB namespace

                                if (is_scalar($cfg['source'])) {
                                    $data['type']['childs']['source'] = [
                                        'value' => 'custom',
                                        'childs' => [
                                            'customSource' => $cfg['source']
                                        ]
                                    ];
                                } else {
                                    if (!empty($cfg['source']['fn'])) {
                                        $data['type']['childs']['source'] = [
                                            'value' => 'custom',
                                            'childs' => [
                                                'customFn' => $cfg['source']['fn']
                                            ]
                                        ];
                                    }
                                }
                        }
                    }

                    if (!empty($cfg['renderer'])) {
                        $data['type']['childs']['renderer'] = ($cfg['renderer'] == 'listObjIcons')
                            ? 'listObjIcons'
                            : 'list';
                    }

                    if (!empty($cfg['editor'])) {
                        $data['type']['childs']['objectsEditor'] = ($cfg['editor'] == 'listObjIcons')
                            ? 'listObjIcons'
                            : 'list';
                    }

                    break;

                case 'time':
                    $data['type'] = empty($cfg['format'])
                        ? 'time'
                        : [
                            'value' => 'time',
                            'childs' => [
                                'timeFormat' => $cfg['format']
                            ]
                        ];
                    break;

                case 'float':
                    $data['type'] = empty($cfg['decimalPrecision'])
                        ? 'float'
                        : [
                            'value' => 'float',
                            'childs' => [
                                'floatPrecision' => $cfg['decimalPrecision']
                            ]
                        ];
                    break;

                case 'memo':
                    $childs = [];
                    if (!empty($cfg['height'])) {
                        $childs['memoHeight'] = $cfg['height'];
                    }
                    if (!empty($cfg['maxLength'])) {
                        $childs['memoLength'] = $cfg['maxLength'];
                    }
                    if (!empty($cfg['mentionUsers'])) {
                        $childs['memoMentionUsers'] = 1;
                    }

                    if (!empty($childs)) {
                        $data['type'] = [
                            'value' => 'memo',
                            'childs' => $childs
                        ];
                    }
                    break;

                case 'text':
                    if (!empty($cfg['editor'])) {
                        $data['type'] = [
                            'value' => 'text',
                            'childs' => [
                                'textEditor' => $cfg['editor']
                            ]
                        ];
                    }
                    break;

                case 'geoPoint':
                    if (!empty($cfg['editor'])) {
                        $data['type'] = [
                            'value' => 'geoPoint',
                            'childs' => [
                                'geoPointEditor' => $cfg['editor']
                            ]
                        ];
                        if (!empty($cfg['url'])) {
                            $data['type']['childs']['geoPointTilesUrl'] = $cfg['url'];
                        }
                        if (!empty($cfg['defaultLocation'])) {
                            $data['type']['childs']['geoPointDefaultLocation'] = [];
                            if (!empty($cfg['defaultLocation']['lat'])) {
                                $data['type']['childs']['geoPointDefaultLocation']['childs']['geoPointDefaultLat'] = $cfg['defaultLocation']['lat'];
                            }
                            if (!empty($cfg['defaultLocation']['lng'])) {
                                $data['type']['childs']['geoPointDefaultLocation']['childs']['geoPointDefaultLng'] = $cfg['defaultLocation']['lng'];
                            }
                            if (!empty($cfg['defaultLocation']['zoom'])) {
                                $data['type']['childs']['geoPointDefaultLocation']['childs']['geoPointDefaultZoom'] = $cfg['defaultLocation']['zoom'];
                            }
                        }
                    }
                    break;

            }
            unset($cfg['scope']);
            unset($cfg['descendants']);
            unset($cfg['templates']);
            unset($cfg['template_types']);
            unset($cfg['query']);
            unset($cfg['fq']);
            unset($cfg['order']);
            unset($cfg['field']);
            unset($cfg['source']);
            unset($cfg['renderer']);
            unset($cfg['editor']);
            unset($cfg['format']);
            unset($cfg['decimalPrecision']);
            unset($cfg['height']);
            unset($cfg['maxLength']);
            unset($cfg['mentionUsers']);
            unset($cfg['defaultLocation']);

            if (!empty($cfg['editMode'])) {
                $data['editMode'] = $cfg['editMode'];
            }
            unset($cfg['editMode']);

            if (!empty($cfg['validator'])) {
                $data['validator'] = $cfg['validator'];
            }
            unset($cfg['validator']);

            if (empty($cfg)) {
                unset($data['cfg']);
            } else {
                $data['cfg'] = Util\jsonEncode($cfg);
            }

            DM\Objects::update(
                [
                    'id' => $r['id'],
                    'data' => Util\jsonEncode($data)
                ]
            );
        }
    }
}
