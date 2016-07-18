<?php

namespace Casebox\CoreBundle\Service\DataModel;

use Casebox\CoreBundle\Service\Cache;
use Casebox\CoreBundle\Service\Util;
use Casebox\CoreBundle\Service\User;

class Tree extends Base
{
    /**
     * database table name
     * @var string
     */
    protected static $tableName = 'tree';

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
        ,'pid' => 'int'
        ,'system' => 'int'
        ,'draft' => 'int'
        ,'draft_pid' => 'varchar'
        ,'template_id' => 'int'
        ,'name' => 'varchar'
        ,'target_id' => 'int'
        ,'name' => 'varchar'
        ,'date' => 'datetime'
        ,'date_end' => 'datetime'
        ,'size' => 'int' //..
        ,'cfg' => 'text'
        ,'inherit_acl' => 'int'
        ,'cid' => 'int'
        ,'cdate' => 'datetime'
        ,'uid' => 'int'
        ,'udate' => 'datetime'
        ,'updated' => 'int'
        ,'oid' => 'int'
        ,'did' => 'int'
        ,'ddate' => 'datetime'
        ,'dstatus' => 'int'
    );

    protected static $decodeJsonFields = array('cfg');

    /**
     * delete a record by its id
     * @param  int | array $ids
     * @param  boolean     $persistent
     * @return void
     */
    public static function delete($ids, $persistent = false)
    {
        $ids = Util\toNumericArray($ids);
        $userId = User::getId();

        $dbs = Cache::get('casebox_dbs');

        foreach ($ids as $id) {
            if ($persistent) {
                $dbs->query(
                    'CALL p_delete_tree_node($1)',
                    $id
                );

            } else {
                static::update(
                    array(
                        'id' => $id
                        ,'dstatus' => 1
                        ,'did' => $userId
                        ,'ddate' => 'CURRENT_TIMESTAMP'
                        ,'updated' => 1
                    )
                );

                $dbs->query(
                    'CALL p_mark_all_childs_as_deleted($1, $2)',
                    array(
                        $id
                        ,$userId
                    )
                );
            }
        }
    }

    /**
     * mark records as active (not deleted)
     * @param  int | array $ids
     * @return void
     */
    public static function restore($ids)
    {
        $ids = Util\toNumericArray($ids);
        // $userId = User::getId();

        $dbs = Cache::get('casebox_dbs');

        foreach ($ids as $id) {
            static::update(
                array(
                    'id' => $id
                    ,'did' => null
                    ,'dstatus' => 0
                    ,'ddate' => null
                    ,'updated' => 1

                )
            );

            $dbs->query('CALL p_mark_all_childs_as_active($1)', $id);
        }

    }

    /**
     * get base properties for a givent record id
     * @param  int   $id
     * @return array
     */
    public static function getProperties($id)
    {
        $rez = array();

        $dbs = Cache::get('casebox_dbs');

        $res = $dbs->query(
            'SELECT t.id `nid`
                ,t.`system`
                ,t.`name`
                ,t.`cfg`
                ,ti.acl_count
            FROM tree t
            JOIN tree_info ti on t.id = ti.id
            WHERE t.id = $1',
            $id
        );

        if ($r = $res->fetch()) {
            $rez = $r;
            static::decodeJsonFields($r);
        }
        unset($res);

        return $rez;
    }

    /**
     * Get basic info
     * should be reviewed
     * @param  int   $id
     * @return array
     */
    public static function getBasicInfo($id)
    {
        $rez = array();

        $dbs = Cache::get('casebox_dbs');

        $res = $dbs->query(
            'SELECT t.id
                ,t.`system`
                ,t.name
                ,ti.pids
                ,ti.case_id
                ,t.`template_id`
                ,t.dstatus
                ,tt.`type` template_type
            FROM tree t
            JOIN tree_info ti on t.id = ti.id
            LEFT JOIN templates tt ON t.template_id = tt.id
            WHERE t.id = $1',
            $id
        );

        if ($r = $res->fetch()) {
            $rez = $r;
        }
        unset($res);

        return $rez;
    }

    /**
     * Get case id for a given item
     * backward compatibility function
     * should be reviewed
     * @param  int $id
     * @return int
     */
    public static function getCaseId($id)
    {
        $rez = null;

        $dbs = Cache::get('casebox_dbs');

        $res = $dbs->query(
            'SELECT coalesce(ti.case_id, t.pid) `pid`
            FROM tree t
            JOIN tree_info ti ON t.id = ti.id
            WHERE t.id = $1',
            $id
        );

        if ($r = $res->fetch()) {
            $rez = $r['pid'];
        }
        unset($res);

        return $rez;
    }

    /**
     * Update owner for ginev ids
     * @param  int  $ids
     * @param  int  $ownerId
     * @return void
     */
    public static function updateOwner($ids, $ownerId)
    {
        $ids = Util\toNumericArray($ids);

        $dbs = Cache::get('casebox_dbs');

        if (!empty($ids)) {
            $dbs->query(
                'UPDATE tree
                SET oid = $1
                    ,uid = $2
                    ,updated = 1
                WHERE id IN (' . implode(',', $ids) . ')
                    AND `system` = 0',
                array(
                    $ownerId
                    ,User::getId()
                )
            );
        }
    }

    public static function getRootId()
    {
        $rez = null;

        $dbs = Cache::get('casebox_dbs');

        $res = $dbs->query(
            'SELECT min(id) `id`
            FROM tree
            WHERE pid IS NULL
                AND `system` = 1
                AND dstatus = 0'
        );

        if ($r = $res->fetch()) {
            $rez = $r['id'];
        }
        unset($res);

        return $rez;
    }

    /**
     * get child record by name
     * @param  int    $pid
     * @param  string $name
     * @return array
     */
    public static function getChildByName($pid, $name)
    {
        $rez = null;

        $dbs = Cache::get('casebox_dbs');

        $res = $dbs->query(
            'SELECT *
            FROM tree
            WHERE pid = $1
                AND name = $2
                AND dstatus = 0',
            array($pid, $name)
        );

        if ($r = $res->fetch()) {
            $rez = $r;
        }
        unset($res);

        return $rez;
    }

    /**
     * get child names under given pid that start with $name and have same extension as $ext
     * @param  int    $pid
     * @param  string $name
     * @param  string $ext
     * @return array
     */
    public static function getChildNames($pid, $name, $ext)
    {
        $rez = array();

        $dbs = Cache::get('casebox_dbs');

        $res = $dbs->query(
            'SELECT name
            FROM tree
            WHERE pid = $1
                AND name like $2
                AND dstatus = 0',
            array(
                $pid
                ,$name . '%' . '.'.$ext
            )
        );

        while ($r = $res->fetch()) {
            $rez[] = $r['name'];
        }
        unset($res);

        return $rez;
    }

    /**
     * get children count for given item ids
     * @param  array $ids
     * @param  array $templateIds filter children by template ids
     * @return array associative array of children per id
     */
    public static function getChildCount($ids, $templateIds = false)
    {
        $rez = array();

        $ids = Util\toNumericArray($ids);

        if (empty($ids)) {
            return $rez;
        }

        $dbs = Cache::get('casebox_dbs');

        if (empty($templateIds)) {
            $templateIds = '';
        } else {
            $templateIds = Util\toNumericArray($templateIds);
            if (!empty($templateIds)) {
                $templateIds = ' AND template_id in (' . implode(',', $templateIds) . ')';
            }
        }

        $sql = 'SELECT pid, count(*) `children`
            FROM tree
            WHERE pid in (' . implode(',', $ids) . ')
                AND dstatus = 0' . $templateIds . '
            GROUP BY pid';

        $res = $dbs->query($sql);

        while ($r = $res->fetch()) {
            $rez[$r['pid']] = $r['children'];
        }
        unset($res);

        return $rez;
    }

    /**
     * activate child drafts
     * @param  array $id
     * @return void
     */
    public static function activateChildDrafts($id)
    {
        $dbs = Cache::get('casebox_dbs');

        $dbs->query(
            'call `p_mark_all_child_drafts_as_active`($1)',
            $id
        );
    }

    /**
     * assign draft children to real id
     * @param  string $draftId
     * @param  array  $id
     * @return void
     */
    public static function assignChildDrafts($draftId, $targetId)
    {
        $dbs = Cache::get('casebox_dbs');

        $res = $dbs->query(
            'SELECT id
            FROM tree
            WHERE draft = 1
                AND draft_pid = $1
                AND cid = $2
                AND (cdate > (CURRENT_TIMESTAMP - INTERVAL 1 HOUR))',
            array(
                $draftId
                ,User::getId()
            )
        );

        while ($r = $res->fetch()) {
            $children[] = $r['id'];
        }
        unset($res);

        if (!empty($children)) {
            $dbs->query(
                'UPDATE tree
                SET draft = 0
                    ,draft_pid = null
                    ,pid = $1
                    ,updated = 1
                WHERE id in (' . implode(',', $children) . ')',
                $targetId
            );
        }
    }

    /**
     * copy a source record under given $pid
     * @param  array $sourceId
     * @param  int   $pid
     * @param  int   $ownerId  - optional owner id
     * @return int   created record id
     */
    public static function copy($sourceId, $pid, $ownerId = null)
    {
        $dbs = Cache::get('casebox_dbs');

        $dbs->query(
            'INSERT INTO `tree`
                (`id`
                ,`pid`
                ,`system`
                ,`template_id`
                ,`tag_id`
                ,`target_id`
                ,`name`
                ,`date`
                ,`date_end`
                ,`size`
                ,`cfg`
                ,`inherit_acl`
                ,`cid`
                ,`cdate`
                ,`uid`
                ,`udate`
                ,`updated`
                ,`oid`
                ,`did`
                ,`ddate`
                ,`dstatus`)
            SELECT
                NULL
                ,$2
                ,`system`
                ,`template_id`
                ,`tag_id`
                ,`target_id`
                ,`name`
                ,`date`
                ,`date_end`
                ,`size`
                ,`cfg`
                ,`inherit_acl`
                ,$3
                ,CURRENT_TIMESTAMP
                ,NULL
                ,NULL
                ,1
                ,COALESCE($4, `oid`)
                ,`did`
                ,`ddate`
                ,`dstatus`
            FROM `tree` t
            WHERE id = $1',
            array(
                $sourceId
                ,$pid
                ,User::getId()
                ,$ownerId
            )
        );

        return $dbs->lastInsertId();
    }

    /**
     * move active (non deleted) children to other parent
     * @param  array $sourceId
     * @param  array $targetId
     * @return bool
     */
    public static function moveActiveChildren($sourceId, $targetId)
    {
        $dbs = Cache::get('casebox_dbs');

        $res = $dbs->query(
            'UPDATE tree
            SET updated = 1
                ,pid = $2
            WHERE pid = $1 AND
                dstatus = 0',
            array(
                $sourceId
                ,$targetId
            )
        );

        return ($res->rowCount() > 0);
    }
}
