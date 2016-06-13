<?php
namespace Casebox\CoreBundle\Service\TreeNode;

use Casebox\CoreBundle\Service\Cache;
use Casebox\CoreBundle\Service\Templates;
use Casebox\CoreBundle\Service\DataModel as DM;

class MyCalendar extends Base
{
    protected function createDefaultFilter()
    {
        $this->fq = array();

        //select only task templates
        $taskTemplates = DM\Templates::getIdsByType('task');
        if (!empty($taskTemplates)) {
            $this->fq[] = 'template_id:('.implode(' OR ', $taskTemplates).')';
        }

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
            $rez = $this->getChildrenTasks();
        }

        return $rez;
    }

    public function getName($id = false)
    {
        if ($id === false) {
            $id = $this->id;
        }
        switch ($id) {
            case 1:
                return $this->trans('MyCalendar');
        }

        return 'none';
    }

    protected function getRootNodes()
    {
        return array(
            'data' => array(
                array(
                    'name' => $this->getName(1)
                    ,'id' => $this->getId(1)
                    ,'iconCls' => 'fa fa-calendar fa-fl'
                    ,'cls' => 'tree-header'
                    ,'view' => 'calendar'
                )
            )
        );
    }

    protected function getChildrenTasks()
    {
        $p = $this->requestParams;

        if (@$p['from'] == 'tree') {
            return array();
        }

        $p['fq'] = $this->fq;
        $p['fq'][] = 'task_u_assignee:'.Cache::get('session')->get('user')['id'];
        $p['fq'][] = 'task_status:[0 TO 2]';

        $s = new \Casebox\CoreBundle\Service\Search();
        $rez = $s->query($p);

        $rez['view'] = 'calendar';

        return $rez;
    }
}
