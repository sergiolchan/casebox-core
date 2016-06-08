<?php
namespace Casebox\CoreBundle\Service\TreeNode;

use Casebox\CoreBundle\Service\Config;
use Casebox\CoreBundle\Service\User;
use Casebox\CoreBundle\Service\DataModel as DM;

class RecycleBin extends Base
{
    protected function createDefaultFilter()
    {
        $this->fq = array('did:' . User::getId());
    }

    public function getChildren(&$pathArray, $requestParams)
    {

        $this->path = $pathArray;
        $this->lastNode = @$pathArray[sizeof($pathArray) - 1];
        $this->requestParams = $requestParams;

        if (!$this->acceptedPath($pathArray, $requestParams)) {
            return;
        }

        $ourPid = @intval($this->config['pid']);

        $this->createDefaultFilter();

        if (empty($this->lastNode) ||
            (($this->lastNode->id == $ourPid) && (get_class($this->lastNode) != get_class($this)))
        ) {
            $rez = $this->getRootNodes();
        } else {
            $rez = $this->getContentItems();
        }

        return $rez;

    }

    public function getName($id = false)
    {
        if ($id === false) {
            $id = $this->id;
        }

        $rez = $id;

        switch ($id) {
            case 'recycleBin':
                return $this->trans('RecycleBin');
            default:
                if (!empty($id) && is_numeric($id)) {
                    $rez = \Casebox\CoreBundle\Service\Objects::getName($id);
                }
                break;
        }

        return $rez;
    }

    protected function getRootNodes()
    {
        return array(
            'data' => array(
                array(
                    'name' => $this->getName('recycleBin')
                    ,'id' => $this->getId('recycleBin')
                    ,'glyph' => 0xf1f8
                    ,'iconCls' => 'fa fa-trash fa-lg'
                    ,'has_childs' => true
                )
            )
        );
    }

    public function getContentItems()
    {
        $p = &$this->requestParams;

        $folderTemplates = $this->configService->get('folder_templates');

        $p['fl'] = 'id,system,path,name,case,date,date_end,size,cid,oid,cdate,uid,udate,template_id,acl_count,cls,status,task_status,dstatus';

        if (@$p['from'] == 'tree') {
            $p['templates'] = $folderTemplates;
        }

        if (is_numeric($this->lastNode->id)) {
            $p['pid'] = $this->lastNode->id;
        }

        $p['dstatus'] = 1;

        $p['fq'] = $this->fq;

        $s = new \Casebox\CoreBundle\Service\Search();
        $rez = $s->query($p);

        if (!empty($rez['data'])) {
            for ($i=0; $i < sizeof($rez['data']); $i++) {
                $d = &$rez['data'][$i];

                $r = DM\Tree::read($d['id']);

                if (!empty($r)) {
                    $d['cfg'] = $r['cfg'];

                    $r = DM\Tree::getChildCount(
                        $d['id'],
                        ((@$p['from'] == 'tree')
                            ? $folderTemplates
                            : false
                        )
                    );

                    $d['has_childs'] = !empty($r[$d['id']]);
                }
            }
        }

        return $rez;
    }
}
