<?php
namespace Casebox\CoreBundle\Service\DataModel;

use Casebox\CoreBundle\Service\Cache;
use Casebox\CoreBundle\Service\Util;

class TemplatesStructure extends Base
{
    /**
     * database table name
     * @var string
     */
    protected static $tableName = 'templates_structure';

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
        ,'template_id' => 'int'
        // ,'tag' => 'varchar' //obsolete
        ,'level' => 'int'
        ,'name' => 'varchar'
        ,'type' => 'varchar'
        ,'order' => 'int'
        ,'cfg' => 'text'
        ,'solr_column_name' => 'varchar'
    );

    protected static $decodeJsonFields = array('cfg');

    public static function read($id)
    {
        $rez = parent::read($id);

        return static::replaceBackwardCompatibleFieldOptions($rez);
    }

    /**
     * get only active (not deleted fields) for given template
     * @param  int   $templateId optional, filter by a template
     * @param  bool  $onlyActive to return only active (nit deleted fields)
     * @return array
     */
    public static function getFields($templateId = false, $onlyActive = true)
    {
        $rez = array();

        $dbs = Cache::get('casebox_dbs');

        $sql = 'SELECT
                ts.id
                ,ts.pid
                ,ts.template_id
                ,ts.name
                ,ts.`level`
                ,ts.`type`
                ,ts.cfg
                ,ts.order
                ,ts.solr_column_name
                ,o.data

            FROM templates_structure ts
            LEFT JOIN objects o ON ts.id = o.id ';

        if ($onlyActive) {
            $sql .= 'JOIN tree t on ts.id = t.id AND t.dstatus = 0 ';
        }

        if (is_numeric($templateId)) {
            $sql .= 'WHERE ts.template_id = $1 ';
        }

        $sql .= 'ORDER BY ts.template_id, ts.`order` ';

        $res = $dbs->query($sql, $templateId);

        while ($r = $res->fetch()) {
            $data = Util\toJSONArray($r['data']);
            unset($r['data']);

            $r['cfg'] = Util\toJSONArray($r['cfg']);

            $r['title'] = Util\detectTitle($data);

            $rez[] = static::replaceBackwardCompatibleFieldOptions($r);
        }
        unset($res);

        return $rez;
    }

    public static function replaceBackwardCompatibleFieldOptions($f)
    {
        if (!empty($f['cfg']['showIn'])) {
            if ($f['cfg']['showIn'] == 'tabsheet') {
                $f['cfg']['placement'] = 'below';
            }

            unset($f['cfg']['showIn']);
        }

        if (!empty($f['cfg']['editMode'])) {
            if ($f['cfg']['editMode'] == 'standalone') {
                $f['cfg']['placement'] = 'below';
            }

            unset($f['cfg']['editMode']);
        }

        if (!empty($f['cfg']['mode'])) {
                $f['cfg']['highlighter'] = $f['cfg']['mode'];

            unset($f['cfg']['mode']);
        }

        switch ($f['type']) {
            case 'checkbox':
                $f['type'] = 'combo';
                $f['cfg']['source'] = 'yesno';
                break;

            case 'iconcombo':
                $f['type'] = 'combo';
                $f['cfg']['source'] = 'templatesIconSet';
                break;

            case '_language':
                $f['type'] = 'combo';
                $f['cfg']['source'] = 'languages';
                break;

            case '_sex':
                $f['type'] = 'combo';
                $f['cfg']['source'] = 'sex';
                break;

            case '_short_date_format':
                $f['type'] = 'combo';
                $f['cfg']['source'] = 'shortDateFormats';
                break;

            case '_fieldTypesCombo':
                $f['type'] = 'combo';
                $f['cfg']['source'] = 'fieldTypes';
                break;

            case '_templateTypesCombo':
                $f['type'] = 'combo';
                $f['cfg']['source'] = 'templateTypes';
                break;

            case 'timeunits':
                $f['type'] = 'combo';
                $f['cfg']['source'] = 'timeUnits';
                break;

            case 'memo':
                $f['type'] = 'text';
                break;

            case 'objects':
                //replace users and groups sources
                if (!empty($f['cfg']['source'])) {
                    switch ($f['cfg']['source']) {
                        case 'users':
                            $f['cfg']['source'] = 'tree';
                            $f['cfg']['fq'] = ["template_type:\"user\""];
                            break;

                        case 'groups':
                            $f['cfg']['source'] = 'tree';
                            $f['cfg']['fq'] = ["template_type:\"group\""];
                            break;

                        case 'usersgroups':
                            $f['cfg']['source'] = 'tree';
                            $f['cfg']['fq'] = ["template_type:(\"user\" OR \"group\")"];
                            break;
                    }
                }
        }

        return $f;
    }

    public static function copy($sourceId, $targetId)
    {
        $dbs = Cache::get('casebox_dbs');

        //detect target template
        $r = Tree::read($targetId);

        $tsr = static::read($r['pid']);

        $parentTemplate = empty($tsr)
            ? $r['pid']
            : $r['template_id'];

        //copying record
        $dbs->query(
            'INSERT INTO `templates_structure`
                (`id`
                ,`pid`
                ,`template_id`
                ,`name`
                ,`type`
                ,`order`
                ,`cfg`
                ,`solr_column_name`
                )
            SELECT
                t.id
                ,t.pid
                ,$3
                ,ts.name
                ,ts.type
                ,ts.order
                ,ts.cfg
                ,ts.solr_column_name
            FROM `tree` t
                ,templates_structure ts
            WHERE t.id = $2
                AND ts.id = $1
            ON DUPLICATE KEY UPDATE
                pid = t.pid
                ,template_id = $3
                ,name = ts.name
                ,`type` = ts.type
                ,`order` = ts.order
                ,`cfg` = ts.cfg
                ,solr_column_name = ts.solr_column_name',
            array(
                $sourceId
                ,$targetId
                ,$parentTemplate
            )
        );
    }

    public static function move($sourceId, $targetId)
    {
        $dbs = Cache::get('casebox_dbs');

        $dbs->query(
            'UPDATE templates_structure
            SET pid = $2
            WHERE id = $1',
            array(
                $sourceId
                ,$targetId
            )
        );
    }
}
