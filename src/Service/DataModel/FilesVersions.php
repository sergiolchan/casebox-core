<?php
namespace Casebox\CoreBundle\Service\DataModel;

use Casebox\CoreBundle\Service\Cache;

class FilesVersions extends Base
{
    /**
     * database table name
     * @var string
     */
    protected static $tableName = 'files_versions';

    protected static $tableFields = array(
        'id' => 'int'
        ,'file_id' => 'int'
        ,'content_id' => 'int'
        ,'date' => 'date'
        ,'name' => 'varchar'
        // ,'title' => 'varchar'
        ,'cid' => 'int'
        ,'cdate' => 'datetime'
        ,'uid' => 'int'
        ,'udate' => 'datetime'
    );

    /**
     * get versions count for a file
     * @param  int $fileId
     * @return int
     */
    public static function getCount($fileId)
    {
        $rez = 0;

        $dbs = Cache::get('casebox_dbs');

        $res = $dbs->query(
            'SELECT count(*) `count`
            FROM files_versions v
            WHERE v.file_id = $1',
            $fileId
        );

        if ($r = $res->fetch()) {
            $rez = $r['count'];
        }
        unset($res);

        return $rez;
    }

    /**
     * get versions data for a file
     * @param  int   $fileId
     * @return array
     */
    public static function getFileVersions($fileId)
    {
        $rez = array();

        $dbs = Cache::get('casebox_dbs');

        $res = $dbs->query(
            'SELECT
                v.id
                ,v.`date`
                ,v.`name`
                ,v.cid
                ,v.uid
                ,v.cdate
                ,v.udate
                ,fc.size
                ,fc.pages
                ,fc.type content_type
            FROM files_versions v
                LEFT JOIN files_content fc on fc.id = v.content_id
            WHERE v.file_id = $1
            ORDER BY COALESCE(v.udate, v.cdate) DESC',
            $fileId
        );

        while ($r = $res->fetch()) {
            $rez[] = $r;
        }
        unset($res);

        return $rez;
    }

    /**
     * get oldest version ids after a given skipCount
     * @param  int   $fileId
     * @param  int   $skipCount
     * @return array
     */
    public static function getOldestIds($fileId, $skipCount)
    {
        $rez = array();

        $dbs = Cache::get('casebox_dbs');

        $res = $dbs->query(
            'SELECT id
            FROM files_versions
            WHERE file_id = $1
            ORDER BY id DESC
            LIMIT ' . $skipCount . ', 10',
            $fileId
        );

        while ($r = $res->fetch()) {
            $rez[] = $r['id'];
        }
        unset($res);

        return $rez;
    }

    /**
     * get oldest version ids after a given skipCount
     * @param  int    $fileId
     * @param  string $md5
     * @return array  | false
     */
    public static function getVersionByMD5($fileId, $md5)
    {
        $rez = array();

        $dbs = Cache::get('casebox_dbs');

        $res = $dbs->query(
            'SELECT f.*
            FROM files_versions f
            JOIN files_content c ON f.content_id = c.id
                AND c.md5 = $2
            WHERE f.file_id = $1',
            array(
                $fileId
                ,$md5
            )
        );

        if ($r = $res->fetch()) {
            $rez = $r;
        }

        return $rez;
    }
}
