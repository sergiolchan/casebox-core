<?php

namespace Casebox\CoreBundle\Service\DataModel;

class TreeAcl extends Base
{
    /**
     * database table name
     * @var string
     */
    protected static $tableName = 'tree_acl';

    /**
     * available table fields
     *
     * associative array of fieldName => type
     * that is also used for trivial validation of input values
     *
     * @var array
     */
    protected static $tableFields = array(
        'id' => 'int'
        ,'node_id' => 'int'
        ,'user_group_id' => 'int'
        ,'allow' => 'int'
        ,'deny' => 'int'
        ,'cid' => 'int'
        ,'cdate' => 'datetime'
        ,'uid' => 'int'
        ,'udate' => 'datetime'
    );

    protected static $allowReadAll = true;
}
