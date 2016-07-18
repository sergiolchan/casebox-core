<?php
namespace Casebox\CoreBundle\Service\Plugins;

use Casebox\CoreBundle\Service\Cache;
use Casebox\CoreBundle\Service\DataModel as DM;
use Casebox\CoreBundle\Service\User;
use Casebox\CoreBundle\Service\Util;

/**
 * Class SystemFolders
 */
class SystemFolders
{
    /**
     * Create system folders specified in created objects template config as system_folders property
     *
     * @param object $o
     *
     * @return void
     */
    public function onNodeDbCreate($o)
    {
        if (!is_object($o)) {
            return;
        }

        $template = $o->getTemplate();

        if (empty($template)) {
            return;
        }

        $templateData = $template->getData();
        if (empty($templateData['cfg']['system_folders'])) {
            return;
        }

        $folderIds = Util\toNumericArray($templateData['cfg']['system_folders']);

        if (empty($folderIds)) {
            return;
        }

        $dbs = Cache::get('casebox_dbs');

        $ownerId = User::getId();
        $pid = $o->getData()['id'];
        $copyIds = [];

        $res = $dbs->query(
            'SELECT id
             FROM tree
             WHERE pid in ('.implode(',', $folderIds).') AND dstatus = 0'
        );

        while ($r = $res->fetch()) {
            $copyIds[] = ['id' => $r['id'], 'pid' => $pid];
        }

        unset($res);

        while (!empty($copyIds)) {
            $r = array_shift($copyIds);
            $newId = DM\Tree::copy($r['id'], $r['pid'], $ownerId);
            DM\Objects::copy($r['id'], $newId);

            //collect children of copied element and add them to the end
            $res = $dbs->query(
                'SELECT id
                FROM tree
                WHERE pid = $1 AND dstatus = 0',
                $r['id']
            );
            while ($r = $res->fetch()) {
                $copyIds[] = ['id' => $r['id'], 'pid' => $newId];
            }
            unset($res);
        }
    }
}
