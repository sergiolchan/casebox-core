<?php
namespace Casebox\CoreBundle\Service\Objects;

use Casebox\CoreBundle\Service\DataModel as DM;
use Casebox\CoreBundle\Service\Cache;
use Casebox\CoreBundle\Service\Objects;
use Casebox\CoreBundle\Service\Security;
use Casebox\CoreBundle\Service\Solr;
use Casebox\CoreBundle\Service\User as CBUser;
use Casebox\CoreBundle\Service\Util;

class User extends Object
{

    /**
     * internal function used by create method for creating custom data
     * @return void
     */
    protected function createCustomData()
    {
        parent::createCustomData();

        $d = &$this->data;
        $dd = &$d['data'];

        $p = array(
            'id' => $this->id
            ,'name' => empty($d['_title']) ? $d['name'] : $dd['_title']
            ,'first_name' => empty($dd['en']) ? '' : $dd['en']
            ,'sex' => empty($dd['sex']) ? null : $dd['sex']
            ,'email' => empty($dd['email']) ? null : $dd['email']
            ,'language_id' => empty($dd['language_id']) ? null : $dd['language_id']
            ,'cfg' => json_encode(
                [
                    'short_date_format' => empty($dd['short_date_format']) ? '' : $dd['short_date_format'],
                    'country_code' => empty($dd['country']) ? '' : $dd['country'],
                    'phone' => empty($dd['phone']) ? '' : $dd['phone'],
                    'timezone' => empty($dd['timezone']) ? '' : $dd['timezone'],
                ],
                JSON_UNESCAPED_UNICODE
            )
            ,'roles' => '{"ROLE_USER":"ROLE_USER"}'
        );

        DM\Users::create($p);

        $this->updateGroupsAssociation();
    }

    /**
     * update objects custom data
     * @return void
     */
    protected function updateCustomData()
    {
        parent::updateCustomData();

        $d = &$this->data;
        $dd = &$d['data'];

        $p = array(
            'id' => $this->id
            ,'name' => $dd['_title']
            ,'first_name' => empty($dd['en']) ? '' : $dd['en']
            ,'sex' => empty($dd['sex']) ? null : $dd['sex']
            ,'email' => empty($dd['email']) ? null : $dd['email']
            ,'language_id' => empty($dd['language_id']) ? null : $dd['language_id']
            ,'cfg' => json_encode(
                [
                    'short_date_format' => empty($dd['short_date_format']) ? '' : $dd['short_date_format'],
                    'country_code' => empty($dd['country']) ? '' : $dd['country'],
                    'phone' => empty($dd['phone']) ? '' : $dd['phone'],
                    'timezone' => empty($dd['timezone']) ? '' : $dd['timezone'],
                ],
                JSON_UNESCAPED_UNICODE
            )
        );

        if (DM\Users::exists($d['id'])) {
            $oldGroups = DM\UsersGroups::getMemberGroupIds($d['id']);
            DM\users::update($p);
            $this->updateGroupsAssociation($oldGroups);
        } else {
            DM\users::create($p);
        }
    }

    protected function deleteCustomData($permanent)
    {
        $d = &$this->data;

        if ($permanent) {
            DM\Users::delete($d['id']);
        } else {
            DM\Users::update(
                [
                    'id' => $d['id'],
                    'enabled' => 0,
                    'did' => CBUser::getId(),
                    'ddate' => 'CURRENT_TIMESTAMP',
                ]
            );

        }

        parent::deleteCustomData($permanent);
    }

    protected function updateGroupsAssociation($oldGroups = [])
    {
        $d = &$this->data;

        $newGroupsValue = $this->getFieldValue('groups', 0);

        $newGroups = empty($newGroupsValue['value']) ? [] : Util\toNumericArray($newGroupsValue['value']);

        $addGroups = array_diff($newGroups, $oldGroups);
        $deleteGroups = array_diff($oldGroups, $newGroups);

        $dbs = Cache::get('casebox_dbs');
        $userId = CBUser::getId();

        foreach ($addGroups as $groupId) {
            $dbs->query(
                'INSERT INTO users_groups_association (user_id, group_id, cid)
                VALUES($1, $2, $3) ON DUPLICATE KEY
                UPDATE uid = $3',
                [
                    $d['id'],
                    $groupId,
                    $userId,
                ]
            );
        }

        if (!empty($deleteGroups)) {
            $dbs->query(
                'DELETE FROM users_groups_association WHERE user_id = $1 AND group_id IN (' .
                implode(', ', $deleteGroups).')',
                $d['id']
            );
        }

        Security::calculateUpdatedSecuritySets($userId);

        Solr\Client::runBackgroundCron();
    }
}
