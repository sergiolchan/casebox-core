<?php
namespace Casebox\CoreBundle\Service\Objects;

use Casebox\CoreBundle\Service\DataModel as DM;
use Casebox\CoreBundle\Service\Objects;
use Casebox\CoreBundle\Service\User;

class Group extends Object
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
            ,'name' => $dd['_title']
            ,'first_name' => empty($dd['en'])
                ? ''
                : $dd['en']
        );

        DM\Group::create($p);
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
            'id' => $d['id']
            ,'name' => $dd['_title']
            ,'first_name' => empty($dd['en'])
                ? ''
                : $dd['en']
        );

        if (DM\Group::exists($d['id'])) {
            DM\Group::update($p);
        } else {
            DM\Group::create($p);
        }
    }

    protected function deleteCustomData($permanent)
    {
        $d = &$this->data;

        if ($permanent) {
            DM\Group::delete($d['id']);
        } else {
            DM\Group::update(
                [
                    'id' => $d['id'],
                    'enabled' => 0,
                    'did' => User::getId(),
                    'ddate' => 'CURRENT_TIMESTAMP',
                ]
            );

        }

        parent::deleteCustomData($permanent);
    }
}
