<?php
namespace Casebox\CoreBundle\Service\Objects;

use Casebox\CoreBundle\Service\Cache;
use Casebox\CoreBundle\Service\Util;
use Casebox\CoreBundle\Service\DataModel as DM;

/**
 * Template class
 */
class TemplateField extends Object
{

    /**
     * available table fields in templates table
     * @var array
     */
    protected $tableFields =  array(
        'id'
        ,'pid'
        ,'name'
        ,'type'
        ,'order'
        ,'cfg'
        ,'solr_column_name'
    );

    /**
     * internal function used by create method for creating custom data
     * @return void
     */
    protected function createCustomData()
    {
        parent::createCustomData();

        $data = $this->collectCustomModelData();

        $data['template_id'] = $this->detectParentTemplate();

        DM\TemplatesStructure::create($data);

        if ($this->isSolrConfigUpdated()) {
            $tpl = \Casebox\CoreBundle\Service\Objects::getCachedObject($data['template_id']);
            $tpl->setSysDataProperty('solrConfigUpdated', true);
        }
    }

    /**
     * load template custom data
     */
    protected function loadCustomData()
    {
        parent::loadCustomData();

        $r = DM\Objects::read($this->id);

        if (!empty($r)) {
            $oldCfg = @$r['data']['cfg'];
            $r['data']['cfg'] = Util\toJSONArray($oldCfg);
            $r['data'] = DM\TemplatesStructure::replaceBackwardCompatibleFieldOptions($r['data']);

            // if no changes made then assign old cfg value
            if (Util\toJSONArray($oldCfg) == $r['data']['cfg']) {
                $r['data']['cfg'] = $oldCfg;
            } else { // encoding json reformats the string
                $r['data']['cfg'] = Util\jsonEncode($r['data']['cfg']);
            }
        } else {
            //read from templates_structure if object not present in tree
            // for backward compatibility
            $r = DM\TemplatesStructure::read($this->id);
        }

        if (!empty($r)) {
            if (isset($r['cfg'])) {
                $r['cfg'] = Util\toJSONArray($r['cfg']);
            }
            $r['title'] = Util\detectTitle($r['data']);

            $this->data = array_merge($this->data, $r);
        } else {
            Cache::get('symfony.container')->get('logger')->error(
                'Template field load error: no field found with id = ' . $this->id
            );
            // throw new \Exception("Template field load error: no field found with id = ".$this->id);
        }
    }

    /**
     * update objects custom data
     * @return boolean
     */
    protected function updateCustomData()
    {
        parent::updateCustomData();

        /* saving template data to templates and templates_structure tables */
        $p = &$this->data;

        $data = $this->collectCustomModelData();

        $data['id'] = $this->id;

        $data['template_id'] = $this->detectParentTemplate();

        DM\TemplatesStructure::update($data);

        if ($this->isSolrConfigUpdated()) {
            $tpl = \Casebox\CoreBundle\Service\Objects::getCachedObject($data['template_id']);
            $tpl->setSysDataProperty('solrConfigUpdated', true);
        }
    }

    protected function collectCustomModelData()
    {
        $rez = parent::collectModelData();

        $ld = $this->getAssocLinearData();

        if (!empty($ld['type'][0]['value'])) {
            $rez['type'] = $ld['type'][0]['value'];
        }

        if (!empty($ld['order'][0]['value'])) {
            $rez['order'] = $ld['order'][0]['value'];
        }

        if (!empty($ld['solrField'][0]['value'])) {
            $rez['solr_column_name'] = $ld['solrField'][0]['value'];
        }

        //reflect all template fields in cfg
        $cfg = empty($rez['cfg'])
            ? []
            : Util\toJSONArray($rez['cfg']);

        if (!empty($ld['cfg'][0]['value'])) {
            $cfg = array_merge($cfg, Util\toJSONArray($ld['cfg'][0]['value']));
        }

        if (!empty($ld['readOnly'][0]['value'])) {
            $cfg['readOnly'] = ($ld['readOnly'][0]['value'] == 1);
        }

        if (!empty($ld['required'][0]['value'])) {
            $cfg['required'] = ($ld['required'][0]['value'] == 1);
        }

        if (!empty($ld['value'][0]['value'])) {
            $cfg['value'] = $ld['value'][0]['value'];
        }

        if (!empty($ld['multiValued'][0]['value'])) {
            $cfg['multiValued'] = ($ld['multiValued'][0]['value'] == 1);
        }

        if (!empty($ld['maxInstances'][0]['value'])) {
            $cfg['maxInstances'] = $ld['maxInstances'][0]['value'];
        }

        if (!empty($ld['dependency'][0]['value'])) {
            if ($ld['dependency'][0]['value'] == 1) {
                $cfg['dependency'] = [];
            }
        }

        if (!empty($ld['pidValues'][0]['value'])) {
            $cfg['dependency']['pidValues'] = Util\toTrimmedArray($ld['pidValues'][0]['value']);
        }

        if (!empty($ld['indexed'][0]['value'])) {
            $cfg['indexed'] = ($ld['indexed'][0]['value'] == 1);
        }

        if (!empty($ld['solrField'][0]['value'])) {
            $cfg['solr_column_name'] = $ld['solrField'][0]['value'];
        }

        if (!empty($ld['scope'][0]['value'])) {
            $cfg['scope'] = $ld['scope'][0]['value'];
        }

        if (!empty($ld['customIds'][0]['value'])) {
            $cfg['scope'] = Util\toTrimmedArray($ld['customIds'][0]['value']);
        }

        if (!empty($ld['descendants'][0]['value'])) {
            $cfg['descendants'] = ($ld['descendants'][0]['value'] == 1);
        }

        if (!empty($ld['facetName'][0]['value'])) {
            $cfg['source'] = ['facet' => $ld['facetName'][0]['value']];
        }

        if (!empty($ld['templateIds'][0]['value'])) {
            $cfg['templates'] = Util\toNumericArray($ld['templateIds'][0]['value']);
        }

        if (!empty($ld['templateTypes'][0]['value'])) {
            $cfg['template_types'] = Util\toTrimmedArray($ld['templateTypes'][0]['value']);
        }

        if (!empty($ld['solrQuery'][0]['value'])) {
            $cfg['query'] = $ld['solrQuery'][0]['value'];
        }

        if (!empty($ld['solrFq'][0]['value'])) {
            $cfg['fq'] = Util\toJSONArray($ld['solrFq'][0]['value']);
        }

        if (!empty($ld['solrOrder'][0]['value'])) {
            $cfg['order'] = $ld['solrOrder'][0]['value'];
        }

        if (!empty($ld['fieldName'][0]['value'])) {
            $cfg['field'] = $ld['fieldName'][0]['value'];
        }

        if (!empty($ld['customSource'][0]['value'])) {
            $cfg['source'] = $ld['customSource'][0]['value'];
        }

        if (!empty($ld['customFn'][0]['value'])) {
            $cfg['source'] = ['fn' => $ld['customFn'][0]['value']];
        }

        if (!empty($ld['renderer'][0]['value'])) {
            $cfg['renderer'] = $ld['renderer'][0]['value'];
        }

        if (!empty($ld['objectsEditor'][0]['value'])) {
            $cfg['editor'] = $ld['objectsEditor'][0]['value'];
        }

        if (!empty($ld['timeFormat'][0]['value'])) {
            $cfg['format'] = $ld['timeFormat'][0]['value'];
        }

        if (!empty($ld['floatPrecision'][0]['value'])) {
            $cfg['decimalPrecision'] = $ld['floatPrecision'][0]['value'];
        }

        if (!empty($ld['memoHeight'][0]['value'])) {
            $cfg['height'] = $ld['memoHeight'][0]['value'];
        }

        if (!empty($ld['memoLength'][0]['value'])) {
            $cfg['maxLength'] = $ld['memoLength'][0]['value'];
        }

        if (!empty($ld['memoMentionUsers'][0]['value'])) {
            $cfg['mentionUsers'] = ($ld['memoMentionUsers'][0]['value'] == 1);
        }

        if (!empty($ld['textEditor'][0]['value'])) {
            $cfg['editor'] = $ld['textEditor'][0]['value'];
        }

        if (!empty($ld['geoPointEditor'][0]['value'])) {
            $cfg['editor'] = $ld['geoPointEditor'][0]['value'];
        }

        if (!empty($ld['geoPointTilesUrl'][0]['value'])) {
            $cfg['url'] = $ld['geoPointTilesUrl'][0]['value'];
        }

        $defaultLocation = [];
        if (!empty($ld['geoPointDefaultLat'][0]['value'])) {
            $defaultLocation['lat'] = $ld['geoPointDefaultLat'][0]['value'];
        }

        if (!empty($ld['geoPointDefaultLng'][0]['value'])) {
            $defaultLocation['lng'] = $ld['geoPointDefaultLng'][0]['value'];
        }

        if (!empty($ld['geoPointDefaultZoom'][0]['value'])) {
            $defaultLocation['zoom'] = $ld['geoPointDefaultZoom'][0]['value'];
        }

        if (!empty($defaultLocation)) {
            $cfg['defaultLocation'] = $defaultLocation;
        }

        if (!empty($ld['editMode'][0]['value'])) {
            $cfg['editMode'] = $ld['editMode'][0]['value'];
        }

        if (!empty($ld['validator'][0]['value'])) {
            $cfg['validator'] = $ld['validator'][0]['value'];
        }

        $rez['cfg'] = Util\jsonEncode($cfg);

        return $rez;
    }

    protected function detectParentTemplate($targetPid = false)
    {
        $rez = ($targetPid === false)
            ? $this->data['pid']
            : $targetPid;

        if (empty($rez)) {
            return null;
        }

        $r = DM\TemplatesStructure::read($rez);

        if (!empty($r)) {
            $rez = $r['template_id'];
        }

        return $rez;
    }

    /**
     * check if current data updates solr configuration
     * @return boolean
     */
    protected function isSolrConfigUpdated()
    {
        $rez= false;

        $old = empty($this->oldObject)
            ? $this
            : $this->oldObject;
        $od = $old->getData();
        $nd = &$this->data;

        $d1 = &$od['data'];
        $d2 = &$nd['data'];

        $cfg1 = empty($d1['cfg'])
            ? array()
            : Util\toJSONArray($d1['cfg']);
        $cfg2 = empty($d2['cfg'])
            ? array()
            : Util\toJSONArray($d2['cfg']);

        $indexed1 = !empty($cfg1['indexed']) || !empty($cfg1['faceting']);
        $indexed2 = !empty($cfg2['indexed']) || !empty($cfg2['faceting']);

        $field1 = empty($d1['solr_column_name'])
            ? ''
            : $d1['solr_column_name'];

        $field2 = empty($d2['solr_column_name'])
            ? ''
            : $d2['solr_column_name'];

        $rez = (($indexed1 != $indexed2) || ($indexed1 && ($field1 != $field2)));

        return $rez;
    }

    /**
     * copy data from templates structure table
     * @param  int  $targetId
     * @return void
     */
    protected function copyCustomDataTo($targetId)
    {
        parent::copyCustomDataTo($targetId);

        DM\TemplatesStructure::copy(
            $this->id,
            $targetId
        );
    }

    protected function moveCustomDataTo($targetId)
    {
        DM\TemplatesStructure::move(
            $this->id,
            $targetId
        );
    }
}
