<?php

namespace Casebox\CoreBundle\Service\DataModel;

use Casebox\CoreBundle\Service\Cache;
use Casebox\CoreBundle\Service\Util;

class Base
{
    /**
     * database table name
     * @var string
     */
    protected static $tableName = 'table_name';

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
    );

    /**
     * decoded json fields on read operation
     *
     * @var array
     */
    protected static $decodeJsonFields = array();

    /**
     * allow read of all records in bulk
     * avoid setting this flag to true for big tables
     */
    protected static $allowReadAll = false;

    /**
     * add a record
     * @param  array $p associative array with table field values
     * @return int   created id
     */
    public static function create($p)
    {
        static::validateParamTypes($p);

        $cp = static::getCreateSqlParams($p);

        $dbs = Cache::get('casebox_dbs');

        //prepare sql
        $sql = 'INSERT INTO '.static::getTableName().' (`'.implode('`,`', $cp['fields']).'`) VALUES ('.implode(',', $cp['params']).')';

        //add database record
        $dbs->query($sql, $cp['values']);

        $rez = $dbs->lastInsertId();

        return $rez;
    }

    /**
     * get params for record creation
     * @param  array  $p associative array with table field values
     * @return array(
     *         array $fields
     *         array $params
     *         array $values
     *         )
     */
    public static function getCreateSqlParams($p)
    {
        $p = array_intersect_key($p, static::$tableFields);

        $fields = array_keys($p);
        $inValues = array_values($p);
        $outValues = [];

        // Prepare params
        $params = [];
        $k = 0;

        for ($i = 0; $i < sizeof($fields); $i++) {
            if ($inValues[$i] === 'CURRENT_TIMESTAMP') {
                $params[$i] = 'CURRENT_TIMESTAMP';

            } else {
                $k++;
                $params[$i] = '$' . $k;
                $outValues[] = $inValues[$i];
            }
        }

        return [
            'fields' => $fields,
            'params' => $params,
            'values' => $outValues
        ];
    }

    /**
     * read a record by id
     * @param  int   $id
     * @return array | null
     */
    public static function read($id)
    {
        $rez = null;

        static::validateParamTypes(array('id' => $id));

        $dbs = Cache::get('casebox_dbs');

        // Read
        $res = $dbs->query('SELECT * FROM ' . static::getTableName() . '  WHERE id = $1', $id);

        if ($r = $res->fetch()) {
            $rez = $r;
        }
        unset($res);

        static::decodeJsonFields($rez);

        return $rez;
    }

    /**
     * read records by ids
     * @param  array   $ids   comma separated string or numeric array
     * @param  boolean $assoc to return result as associative array by record id
     * @return array   records
     */
    public static function readByIds($ids, $assoc = false)
    {
        $rez = array();

        $ids = Util\toNumericArray($ids);

        $dbs = Cache::get('casebox_dbs');

        if (!empty($ids)) {
            $res = $dbs->query('SELECT * FROM ' . static::getTableName() . ' WHERE id in (' . implode(',', $ids) . ')');

            while ($r = $res->fetch()) {
                static::decodeJsonFields($r);

                if ($assoc) {
                    $rez[$r['id']] = $r;
                } else {
                    $rez[] = $r;
                }
            }
            unset($res);
        }

        return $rez;
    }

    /**
     * read all records from the table but only for derived classes that are allowed to
     * by default this method returns empty array it class doesnt have
     * allowReadAll flag set to true
     *
     * @return array
     */
    public static function readAll()
    {
        $rez = array();

        $dbs = Cache::get('casebox_dbs');

        if (static::$allowReadAll === true) {
            $res = $dbs->query(
                'SELECT *
                FROM ' . static::getTableName()
            );

            while ($r = $res->fetch()) {
                static::decodeJsonFields($r);
                $rez[] = $r;
            }
            unset($res);
        }

        return $rez;
    }

    /**
     * update a record
     * @param  array   $p array with table properties
     * @return boolean
     */
    public static function update($p)
    {
        Util\raiseErrorIf(
            empty($p['id']),
            'ErroneousInputData' //' no id given for update method
        );

        static::validateParamTypes($p);

        $up = static::getUpdateSqlParams($p);

        $dbs = Cache::get('casebox_dbs');

        //prepare sql
        $sql = 'UPDATE ' . static::getTableName() .
            ' SET ' . implode(',', $up['assignments']) .
            ' WHERE id = $1';

        //add database record
        $res = $dbs->query($sql, $up['values']);

        $rez = ($res->rowCount() > 0);

        return $rez;
    }

    /**
     * get params for record update
     * @param  array  $p associative array with table field values
     * @return array(
     *         array $fields
     *         array $assignments
     *         array $values
     *         )
     */
    public static function getUpdateSqlParams($p)
    {
        $p = array_intersect_key($p, static::$tableFields);

        $fields = array_values(array_diff(array_keys($p), array('id')));
        $assignments = array();
        $values = array($p['id']);

        $i = 2;

        foreach ($p as $k => $v) {
            if ($k !== 'id') {
                if ($v === 'CURRENT_TIMESTAMP') {
                    $assignments[] = "`$k` = CURRENT_TIMESTAMP";
                } else {
                    $assignments[] = "`$k` = \$" . $i++;
                    $values[] = $v;
                }
            }
        }

        return array(
            'fields' => $fields
            ,'assignments' => $assignments
            ,'values' => $values
        );
    }

    /**
     * delete a record by its id
     * @param  int | array $ids
     * @return boolean
     */
    public static function delete($ids)
    {
        $res = null;

        $dbs = Cache::get('casebox_dbs');

        $sql = 'DELETE from ' . static::getTableName() .
            ' WHERE id';

        if (is_scalar($ids)) {
            static::validateParamTypes(array('id' => $ids));

            $res = $dbs->query($sql . ' = $1', $ids);

        } else {
            $ids = Util\toNumericArray($ids);

            if (!empty($ids)) {
                $res = $dbs->query(
                    $sql . ' IN (' . implode(',', $ids) . ')'
                );
            }
        }

        $rez = ($res && ($res->rowCount() > 0));

        return $rez;
    }

    /**
     * check if a record exists by its id or name field
     * @param  string  $idOrName
     * @return boolean
     */
    public static function exists($idOrName)
    {
        $rez = false;
        try {
            $rez = static::read(static::toId($idOrName));
        } catch (\Exception $e) {

        }

        return !empty($rez);
    }

    /**
     * get name for given id or return same result if numeric
     * @param  string $idOrName
     * @param  string $nameField to search by
     * @param  int    $pid       filter by pid if set
     * @return int    | null
     */
    public static function toId($idOrName, $nameField = 'name', $pid = false)
    {
        $rez = null;

        $dbs = Cache::get('casebox_dbs');

        if (!is_numeric($idOrName)) {

            $sql = 'SELECT id
                FROM ' . static::getTableName() .
                ' WHERE ' . $nameField . ' = $1';

            if (($pid !== false) && is_numeric($pid)) {
                $sql .= ' AND pid = ' . intval($pid);
            }

            $res = $dbs->query($sql, $idOrName);

            if ($r = $res->fetch()) {
                $rez = $r['id'];
            }

            if (empty($rez)) {
                //trigger_error("Error on find ID: ".$sql.' $1 = '.$idOrName, E_USER_WARNING);
            }

            unset($res);

        } else {
            $rez = $idOrName;
        }

        return $rez;
    }

    /**
     * collect data from a given array corresponding to current table definition
     * also encodes json fields defined in static::$decodeJsonFields
     * @param  array $a
     * @return array associative array of fieldName => value
     */
    public static function collectData($a)
    {
        $rez = array_intersect_key($a, static::$tableFields);

        foreach (static::$decodeJsonFields as $fn) {
            if (isset($rez[$fn]) && !is_null($rez[$fn] && !is_scalar($rez[$fn]))) {
                $rez[$fn] = Util\jsonEncode($rez[$fn]);
            }
        }

        return $rez;
    }

    /**
     * validate param types
     * @param  array $p
     * @param  array $fields - default is $tableFields
     * @return void  |  throws an exception on error
     */
    protected static function validateParamTypes($p, $fields = false)
    {
        if ($fields === false) {
            $fields = static::$tableFields;
        }

        foreach ($fields as $fn => $ft) {
            $valid = true;

            if (!isset($p[$fn]) || is_null($p[$fn])) {
                continue;
            }

            switch ($ft) {
                case 'int':
                case 'smallint':
                case 'float':
                    $valid = is_numeric($p[$fn]);

                    break;

                // case 'bool':
                //     $valid = is_bool($p[$fn]);

                    break;

                case 'char':
                    $valid = is_string($p[$fn]) && (mb_strlen($p[$fn]) < 2);

                    break;

                case 'enum':
                case 'varchar':
                case 'text':
                    $valid = is_scalar($p[$fn]);

                    break;

                case 'time':
                case 'timestamp':
                case 'date':
                    if ($p[$fn] == 'CURRENT_TIMESTAMP') {
                        break;
                    }

                    $dt = explode(' ', $p[$fn]);

                    $valid = sizeof($dt) < 3;

                    if ($valid) {
                        $d = explode('-', $dt[0]);
                        $valid = (sizeof($d) == 3);

                        if ($valid) {
                            $valid = is_numeric($d[0]) &&
                                is_numeric($d[1]) &&
                                is_numeric($d[2]);
                        }
                    }

                    if ($valid && !empty($dt[1])) {
                        $t = explode(':', $dt[1]);
                        $valid = (sizeof($t) < 4);

                        if ($valid) {
                            $valid = is_numeric($t[0]) &&
                                is_numeric($t[1]) &&
                                (empty($t[2]) || is_numeric($t[2]));
                        }
                    }

                    break;
            }

            Util\raiseErrorIf(!$valid, 'ErroneousInputData'); //' Invalid value for field "' . $fn . '"'
        }
    }

    /**
     * decode json fields
     */
    protected static function decodeJsonFields(&$record)
    {
        foreach (static::$decodeJsonFields as $fn) {
            if (!empty($record[$fn])) {
                $record[$fn] = Util\toJSONArray($record[$fn]);
            }
        }

        return $record;
    }

    /**
     * get table name that current class operates with
     * @return [type] [description]
     */
    public static function getTableName()
    {
        return static::$tableName;
    }
}
