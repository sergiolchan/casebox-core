<?php

namespace Casebox\CoreBundle\Service\DataModel;

use Casebox\CoreBundle\Service\Cache;
use Casebox\CoreBundle\Service\Util;

class Users extends UsersGroups
{
    /**
     * db value for type field
     * @var integer
     */
    protected static $type = 2;

    /**
     * add a record
     * @param  array $p associative array with table field values
     * @return int   created id
     */
    public static function create($p)
    {
        $p['type'] = static::$type;

        return parent::create($p);
    }

    /**
     * update a record
     * @param  array   $p array with properties (id field is required for update)
     * @return boolean
     */
    public static function update($p)
    {
        $p['type'] = static::$type;

        $rez = parent::update($p);

        return $rez;
    }

    public static function getUpdateSqlParams($p)
    {
        $rez = parent::getUpdateSqlParams($p);

        $passIdx = array_search('password', $rez['fields']);
        if ($passIdx !== false) {
            $a = explode('=', $rez['assignments'][$passIdx]);
            $rez['assignments'][$passIdx] = $a[0] . '= MD51(CONCAT(\'aero\', ' . $a[1] . '))';
        }

        return $rez;
    }

    /**
     * update a record by username param
     * @param  array   $p array with properties
     * @return boolean
     */
    public static function updateByName($p)
    {
        Util\raiseErrorIf(
            empty($p['name']),
            'ErroneousInputData' //' no username specified for updateByName function'
        );

        $p['id'] = static::toId($p['name']);

        return static::update($p);
    }

    /**
     * delete a record by its id
     * @param  []int   $ids
     * @return boolean
     */
    public static function delete($ids)
    {
        $res = null;

        $dbs = Cache::get('casebox_dbs');

        $sql = 'DELETE from ' . static::getTableName() .
            ' WHERE `type` = $1 and id';

        if (is_scalar($ids)) {
            static::validateParamTypes(array('id' => $ids));

            $res = $dbs->query($sql . ' = $2', array(static::$type, $ids));

        } else {
            $ids = Util\toNumericArray($ids);

            if (!empty($ids)) {
                $res = $dbs->query(
                    $sql . ' IN (' . implode(',', $ids) . ')',
                    static::$type
                );
            }
        }

        $rez = ($res && ($res->rowCount() > 0));

        return $rez;
    }

    /**
     * check if a given user id exists
     * @param  int     $id
     * @param  int     $onlyActive
     * @return boolean
     */
    public static function idExists($id, $onlyActive = true)
    {
        $rez = false;

        $dbs = Cache::get('casebox_dbs');

        $sql = 'SELECT id
            FROM `' . static::getTableName() . '`
            WHERE id = $1  AND `type` = $2' .
            ($onlyActive
                ? ' AND enabled = 1'
                : ''
            );

        $res = $dbs->query(
            $sql,
            array($id, static::$type)
        );

        if ($res->fetch()) {
            $rez = true;
        }
        unset($res);

        return $rez;
    }

    /**
     * get user id by username
     * @param  string $username
     * @param  int    $onlyActive
     * @return int
     */
    public static function getIdByName($username, $onlyActive = true)
    {
        $rez = null;

        $dbs = Cache::get('casebox_dbs');

        $sql = 'SELECT id
            FROM `' . static::getTableName() . '`
            WHERE name = $1  AND `type` = $2' .
            ($onlyActive
                ? ' AND enabled = 1'
                : ''
            );

        $res = $dbs->query(
            $sql,
            array($username, static::$type)
        );

        if ($r = $res->fetch()) {
            $rez = $r['id'];
        }
        unset($res);

        return $rez;
    }

    /**
     * get user id by email
     * @param  string $email
     * @return int    | null
     */
    public static function getIdByEmail($email)
    {
        $rez = null;

        $dbs = Cache::get('casebox_dbs');

        $res = $dbs->query(
            'SELECT id
                ,email
            FROM users_groups
            WHERE email LIKE $1
                AND enabled = 1
                AND did IS NULL',
            "%$email%"
        );

        while (($r = $res->fetch()) && empty($rez)) {
            $mails = Util\toTrimmedArray($r['email']);

            for ($i=0; $i < sizeof($mails); $i++) {
                if (mb_strtolower($mails[$i]) == $email) {
                    $rez = $r['id'];
                }
            }
        }

        unset($res);

        return $rez;
    }

    /**
     * get user id by recovery hash
     * @param  string $hash
     * @return int    | null
     */
    public static function getIdByRecoveryHash($hash)
    {
        $rez = null;

        $dbs = Cache::get('casebox_dbs');

        $res = $dbs->query(
            'SELECT id
            FROM users_groups
            WHERE recover_hash = $1',
            $hash
        );

        if ($r = $res->fetch()) {
            $rez = $r['id'];
        }
        unset($res);

        return $rez;
    }

    /**
     * get user owner id
     * @param  int $userId
     * @return int
     */
    public static function getOwnerId($userId)
    {
        $rez = null;

        $dbs = Cache::get('casebox_dbs');

        $res = $dbs->query(
            'SELECT cid
            FROM users_groups
            WHERE id = $1',
            $userId
        );

        if ($r = $res->fetch()) {
            $rez = $r['cid'];
        }
        unset($res);

        return $rez;
    }
}
