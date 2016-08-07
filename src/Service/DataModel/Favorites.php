<?php
namespace Casebox\CoreBundle\Service\DataModel;

use Casebox\CoreBundle\Service\Cache;

class Favorites extends Base
{
    /**
     * database table name
     * @var string
     */
    protected static $tableName = 'favorites';

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
        ,'user_id' => 'int'
        ,'node_id' => 'varchar'
        ,'data' => 'text'
    );

    protected static $decodeJsonFields = array('data');

    public static function readAll()
    {
        $rez = array();

        $dbs = Cache::get('casebox_dbs');

        $res = $dbs->query(
            'SELECT *
            FROM ' . static::getTableName() .
            ' WHERE user_id = $1',
            \Casebox\CoreBundle\Service\User::getId()
        );

        while ($r = $res->fetch()) {
            static::decodeJsonFields($r);
            $rez[] = $r;
        }
        unset($res);

        return $rez;
    }

    /**
     * check if a given node id is in favorites for current user
     * @param  varchar $id (could be also an id of a virtual node)
     * @return boolean
     */
    public static function isFavorite($id)
    {
        $rez = false;

        $dbs = Cache::get('casebox_dbs');

        $res = $dbs->query(
            'SELECT *
            FROM ' . static::getTableName() .
            ' WHERE user_id = $1' .
            ' AND node_id = $2',
            [
                \Casebox\CoreBundle\Service\User::getId(),
                $id
            ]
        );

        while ($r = $res->fetch()) {
            $rez = true;
        }
        unset($res);

        return $rez;
    }

    public static function deleteByNodeId($nodeId, $userId = false)
    {
        if ($userId == false) {
            $userId = \Casebox\CoreBundle\Service\User::getId();
        }

        $dbs = Cache::get('casebox_dbs');

        $res = $dbs->query(
            'DELETE FROM ' . static::getTableName() .
            ' WHERE user_id = $1 AND node_id = $2',
            array(
                $userId
                ,$nodeId
            )
        );

        $rez = ($res->rowCount() > 0);

        return $rez;
    }
}
