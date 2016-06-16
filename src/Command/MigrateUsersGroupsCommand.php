<?php
namespace Casebox\CoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Casebox\CoreBundle\Service\Browser;
use Casebox\CoreBundle\Service\Cache;
use Casebox\CoreBundle\Service\DataModel as DM;
use Casebox\CoreBundle\Service\Objects;
use Casebox\CoreBundle\Service\System;
use Casebox\CoreBundle\Service\Templates;
use Casebox\CoreBundle\Service\User;

/**
 * Class MigrateUsersGroupsCommand
 */
class MigrateUsersGroupsCommand extends ContainerAwareCommand
{
    /*
    "en"                <- first + last
    "initials"
    "sex"               <- gender
        "location"      <- country
        "position"
    "room"
    "email"             <- primary email
    "phone"             <- phone
    "language_id"       <- language
                        <- timezone
    "short_date_format" <- date format
                        <- groups
    "description"

     */
    private $userTemplateFields = [
        '_title' => [
            'data' => [
                '_title' => '_title',
                'en' => 'Username',
                'type' => 'varchar',
                'order' => 1,
            ]
        ],
        'en' => [
            'data' => [
                '_title' => 'en',
                'en' => 'Full name (en)',
                'type' => 'varchar',
                'order' => 2,
            ]
        ],
        'initials' => [
            'data' => [
                '_title' => 'initials',
                'en' => 'Initials',
                'type' => 'varchar',
                'order' => 5,
            ]
        ],
        'sex' => [
            'data' => [
                '_title' => 'sex',
                'en' => 'Sex',
                'type' => 'combo',
                'cfg' => '{"source":"sex"}',
                'order' => 10
            ]
        ],
        // "room"
        'email' => [
            'data' => [
                '_title' => 'email',
                'en' => 'Email',
                'type' => 'varchar',
                'cfg' => '{"validator":"email"}',
                'order' => 15
            ]
        ],
        'country' => [
            'data' => [
                '_title' => 'country',
                'en' => 'Country',
                'type' => 'combo',
                'cfg' => '{"source":"countries"}',
                'order' => 20
            ]
        ],
        'phone' => [
            'data' => [
                '_title' => 'phone',
                'en' => 'Phone',
                'type' => 'varchar',
                'order' => 25
            ]
        ],
        'language_id' => [
            'data' => [
                '_title' => 'language_id',
                'en' => 'Language',
                'type' => 'combo',
                'cfg' => '{"source":"languages", "required": true}',
                'order' => 30
            ]
        ],
        'timezone' => [
            'data' => [
                '_title' => 'timezone',
                'en' => 'Timezone',
                'type' => 'combo',
                'cfg' => '{"source":"timezones"}',
                'order' => 35
            ]
        ],
        'short_date_format' => [
            'data' => [
                '_title' => 'short_date_format',
                'en' => 'Date format',
                'type' => 'combo',
                'cfg' => '{"source":"shortDateFormats"}',
                'order' => 40
            ]
        ],
        'groups' => [
            'data' => [
                '_title' => 'groups',
                'en' => 'Groups',
                'type' => '_objects',
                'cfg' => '{"editor": "form", "scope":""}', //$this->groupsFolderId - will be set on create
                'order' => 45
            ]
        ],
        'description' => [
            'data' => [
                '_title' => 'description',
                'en' => 'Description',
                'type' => 'varchar',
                'order' => 50
            ]
        ],
    ];

    private $usersFolderId = null;

    private $groupsFolderId = null;

    private $userTemplateId = null;

    private $groupTemplateId = null;

    /**
     * Configure
     */
    protected function configure()
    {
        $this
            ->setName('casebox:migrate:usersgroups')
            ->setDescription('Migrate users and groups into tree.');
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

        $ids = DM\Templates::getIdsByType('user');
        $this->userTemplateId = array_shift($ids);

        Cache::set('is_admin' . User::getId(), true);

        $this->createTreeFolders();
        $output->writeln('<info>[x] Tree folders created.</info>');

        $this->userTemplateFields['groups']['data']['cfg'] = '{"editor":"form", "scope": "' . $this->groupsFolderId . '"}';

        $this->updateUserTemplate();
        $output->writeln('<info>[x] User template updated.</info>');

        $this->groupTemplateId = $this->getGroupTemplateId();
        $output->writeln('<info>[x] Groups template created.</info>');

        $this->addMenuRules();
        $output->writeln('<info>[x] Menu rules added.</info>');

        $this->migrateGroups();
        $output->writeln('<info>[x] Groups migrated.</info>');

        $this->migrateUsers();
        $output->writeln('<info>[x] Users migrated.</info>');

        return;
    }

    protected function createTreeFolders()
    {
        $o = new Objects\Object();

        $rootId = Browser::getRootFolderId();

        $systemFolderId = Objects::getChildId($rootId, 'System');

        if (empty($systemFolderId)) {
            throw new Exception("Cannot find system folder", 1);
        }

        $folderTemplateId = DM\Templates::toId('folder');

        $securityFolderId = Objects::getChildId($systemFolderId, 'Security');
        if (empty($securityFolderId)) {
            $securityFolderId = $o->create(
                array(
                    'id' => null
                    ,'pid' => $systemFolderId
                    ,'template_id' => $folderTemplateId
                    ,'name' => 'Security'
                    ,'data' => array(
                        '_title'  => 'Security'
                    )
                )
            );
        }

        $this->usersFolderId = Objects::getChildId($securityFolderId, 'Users');

        if (empty($this->usersFolderId)) {
            $this->usersFolderId = $o->create(
                array(
                    'id' => null
                    ,'pid' => $securityFolderId
                    ,'template_id' => $folderTemplateId
                    ,'name' => 'Users'
                    ,'data' => array(
                        '_title'  => 'Users'
                    )
                )
            );
        }

        $this->groupsFolderId = Objects::getChildId($securityFolderId, 'Groups');

        if (empty($this->groupsFolderId)) {
            $this->groupsFolderId = $o->create(
                array(
                    'id' => null
                    ,'pid' => $securityFolderId
                    ,'template_id' => $folderTemplateId
                    ,'name' => 'Groups'
                    ,'data' => array(
                        '_title'  => 'Groups'
                    )
                )
            );
        }
    }

    protected function updateUserTemplate()
    {
        $ids = DM\Templates::getIdsByType('field');
        $fieldTemplateId = array_shift($ids);

        $tc = Templates\SingletonCollection::getInstance();
        $t = $tc->getTemplate($this->userTemplateId);
        $d = $t->getData();
        $fields = $t->getFields();

        $d['data']['cfg'] = json_encode(
            [
                'object_plugins' => [
                    'objectProperties' => [],
                    'userSecurity' => [],
                ]
            ]
        );
        $t->update($d);

        /*
          'headers' =>
          array (
            'en' => false,
            'initials' => false,
            'sex' => false,
            'location' => false,
            'position' => false,
            'room' => false,
            'email' => false,
            'phone' => false,
            'language_id' => false,
            'short_date_format' => false,
            'description' => false,
          ),
         */

        //iterating new field list and create fields that are missing from template
        $fieldClass = new Objects\TemplateField();
        foreach ($this->userTemplateFields as $key => $field) {
            $templateField = $t->getField($key);
            if (!empty($templateField)) {
                $fieldClass->load($templateField['id']);
                $d = $fieldClass->getData();
                $d['name'] = $key;
                $d['data'] = $field['data'];
                $d = $fieldClass->update($d);

                unset($fields[$templateField['id']]);
            } else {
                $fieldClass->create(
                    [
                        'id' => null,
                        'pid' => $this->userTemplateId,
                        'template_id' => $fieldTemplateId,
                        'name' => $key,
                        'data' => $field['data'],
                    ]
                );
            }
        }
    }

    protected function getGroupTemplateId()
    {
        $ids = DM\Templates::getIdsByType('group');
        if (!empty($ids)) {
            return array_shift($ids);
        }

        $o = new Objects\Template();

        $rootId = Browser::getRootFolderId();

        $pid = Objects::getChildId($rootId, 'Templates');
        if (empty($pid)) {
            $id = Objects::getChildId($rootId, 'System');
            if (!empty($id)) {
                $pid = $id;
                $id = Objects::getChildId($pid, 'Templates');
                if (!empty($id)) {
                    $pid = $id;
                    $id = Objects::getChildId($pid, 'Built-in');
                    if (!empty($id)) {
                        $pid = $id;
                    }
                }
            }
        }

        $rez = $o->create(
            array(
                'id' => null
                ,'pid' => $pid
                ,'template_id' => DM\Templates::toId('template', 'type')
                ,'name' => 'Group'
                ,'data' => array(
                    '_title' => 'group',
                    'en' => 'Group',
                    'type' => 'group',
                    'iconCls' => 'fa fa-group fa-fl',
                    'title_template' => '{en}',
                )
            )
        );

        $o = new Objects\TemplateField();
        $fieldTemplateId = DM\Templates::toId('field', 'type');
        $o->create(
            array(
                'id' => null
                ,'pid' => $rez
                ,'template_id' => $fieldTemplateId
                ,'name' => '_title'
                ,'data' => array(
                    '_title' => '_title',
                    'en' => 'Name',
                    'type' => 'varchar',
                    'order' => 1,
                )
            )
        );

        $o->create(
            array(
                'id' => null
                ,'pid' => $rez
                ,'template_id' => $fieldTemplateId
                ,'name' => 'en'
                ,'data' => array(
                    '_title' => 'en',
                    'en' => 'Name (en)',
                    'type' => 'varchar',
                    'order' => 2,
                )
            )
        );

        return $rez;
    }

    protected function addMenuRules()
    {
        $o = new Objects\Object();

        $rootId = Browser::getRootFolderId();

        $systemFolderId = Objects::getChildId($rootId, 'System');

        if (empty($systemFolderId)) {
            throw new Exception("Cannot find system folder", 1);
        }

        $menusFolderId = Objects::getChildId($systemFolderId, 'Menus');

        $ids = DM\Templates::getIdsByType('menu');
        $menuTemplateId = array_shift($ids);

        $id = Objects::getChildId($menusFolderId, 'Groups folder rule');
        if (empty($id)) {
            $o->create(
                array(
                    'id' => null
                    ,'pid' => $menusFolderId
                    ,'template_id' => $menuTemplateId
                    ,'name' => 'Groups folder rule'
                    ,'data' => array(
                        '_title' => 'Groups folder rule',
                        'menu' => $this->groupTemplateId,
                        'node_ids' => $this->groupsFolderId,
                    )
                )
            );
        }

        $id = Objects::getChildId($menusFolderId, 'Users folder rule');
        if (empty($id)) {
            $o->create(
                array(
                    'id' => null
                    ,'pid' => $menusFolderId
                    ,'template_id' => $menuTemplateId
                    ,'name' => 'Users folder rule'
                    ,'data' => array(
                        '_title' => 'Users folder rule',
                        'menu' => $this->userTemplateId,
                        'node_ids' => $this->usersFolderId,
                    )
                )
            );
        }
    }

    protected function migrateGroups()
    {
        $recs = DM\UsersGroups::readAll();
        $o = new Objects\Group();

        foreach ($recs as $rec) {
            if ($rec['type'] == 1) {
                $id = Objects::getChildId($this->groupsFolderId, $rec['name']);
                if (empty($id)) {
                    DM\UsersGroups::delete($rec['id']);

                    $this->groupIds[$rec['id']] = $o->create(
                        [
                            'id' => null,
                            'pid' => $this->groupsFolderId,
                            'template_id' => $this->groupTemplateId,
                            'name' => $rec['name'],
                            'data' => [
                                '_title' => $rec['name'],
                                'en' => $rec['first_name'],
                            ],
                        ]
                    );
                }
            }
        }
    }

    protected function migrateUsers()
    {

        $recs = DM\UsersGroups::readAll();
        $o = new Objects\User();
        $dbs = Cache::get('casebox_dbs');

        foreach ($recs as $rec) {
            if ($rec['type'] == 2) {
                $id = Objects::getChildId($this->usersFolderId, $rec['name']);
                if (empty($id)) {
                    DM\UsersGroups::delete($rec['id']);
                    $cfg = &$rec['cfg'];
                    $data = &$rec['data'];

                    $data['_title'] = $rec['name'];

                    if (empty($data['en'])) {
                        $data['en'] = $rec['first_name'] . ' ' . $rec['last_name'];
                    }
                    if (empty($data['sex'])) {
                        $data['sex'] = $rec['sex'];
                    }
                    if (empty($data['email'])) {
                        $data['email'] = $rec['email'];
                    }
                    if (empty($data['language_id'])) {
                        $data['language_id'] = $rec['language_id'];
                    }
                    if (empty($data['phone']) && !empty($cfg['phone'])) {
                        $data['phone'] = $cfg['phone'];
                    }
                    if (empty($data['short_date_format']) && !empty($cfg['short_date_format'])) {
                        $data['short_date_format'] = $cfg['short_date_format'];
                    }
                    $id = $o->create(
                        [
                            'id' => null,
                            'pid' => $this->usersFolderId,
                            'template_id' => $this->userTemplateId,
                            'name' => $rec['name'],
                            'data' => $data,
                            'did' => $rec['did'],
                            'ddate' => $rec['ddate'],
                        ]
                    );

                    $res = $dbs->query(
                        'UPDATE users_groups SET password = :p2, roles = \'{"ROLE_USER":"ROLE_USER"}\' WHERE id = :p1 ',
                        [$id, $rec['password']]
                    );
                }
            }
        }
    }
}
