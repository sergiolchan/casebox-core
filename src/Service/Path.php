<?php
namespace Casebox\CoreBundle\Service;

use Casebox\CoreBundle\Service\Cache;
use Casebox\CoreBundle\Service\DataModel as DM;
use Casebox\CoreBundle\Service\Util;

class Path
{
    /* get last element id from a path or return root folder id if no int element is found */
    public static function getId($path = '')
    {
        $path = trim($path);

        while (!empty($path) && (substr($path, -1) == '/')) {
            $path = substr($path, 0, strlen($path) - 1);
        }

        $id = explode('/', $path);
        $id = array_pop($id);
        $id = is_numeric($id) ? $id : Browser::getRootFolderId();

        return $id;
    }

    public static function getPath($id, $excludeItself = false)
    {
        $rez = ['success' => false];
        if (!is_numeric($id)) {
            return $rez;
        }

        $r = DM\Tree::getBasicInfo($id);

        if (!empty($r)) {
            $p = explode(',', $r['pids']);

            if ($excludeItself) {
                array_pop($r['pids']);
            }

            $p = implode('/', $p);

            $rez = [
                'success' => true,
                'id' => $id,
                'name' => $r['name'],
                'path' => $p,
            ];
        }

        return $rez;
    }

    public static function getPidPath($id)
    {
        return static::getPath($id, true);
    }

    /**
     * create node classes for given node configs
     *
     * @param array $nodeConfigs
     *
     * @return array
     */
    public static function getNodeClasses($nodeConfigs)
    {
        $rez = [];

        $guids = static::getGUIDs(array_keys($nodeConfigs));

        foreach ($nodeConfigs as $p => $cfg) {
            $class = empty($cfg['class']) ? '\\Casebox\\CoreBundle\\Service\\TreeNode\\'.$p : $cfg['class'];

            $cfg['guid'] = $guids[$p];
            $cfg['class'] = $class;

            try {
                if (class_exists($class)) {
                    $class = new $class($cfg);
                    $rez[$cfg['guid']] = $class;
                }
            } catch (\Exception $e) {
                Cache::get('symfony.container')->get('logger')->error('error creating class '.$class);
            }
        }

        return $rez;
    }

    /**
     * create an array of node classes for given path and nodeConfigs
     *
     * @param string $path
     * @param array  $treeNodeGUIDConfigs
     *
     * @return array
     */
    public static function createNodesPath($path, $treeNodeGUIDConfigs)
    {
        $rez = [];
        $path = explode('/', $path);

        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');

        $rootNodeCfg = Util\toJSONArray($configService->get('rootNode'));

        while (!empty($path)) {
            $npid = null;
            $nodeId = null;

            $el = array_shift($path);
            if (strlen($el) < 1) {
                continue;
            }

            //analize virtual root node
            if (!empty($rootNodeCfg) && ($el == $rootNodeCfg['id']) && (intval($el) == 0)) {
                $rootNodeCfg['class'] = 'Casebox\\CoreBundle\\Service\\TreeNode\\Base';
                $rootNodeCfg['guid'] = 0;
                $class = new \Casebox\CoreBundle\Service\TreeNode\Base($rootNodeCfg, $el);
                array_push($rez, $class);

                continue;
            }

            $el = explode('-', $el);
            if (sizeof($el) > 1) {
                $npid = array_shift($el);
                $nodeId = implode('-', $el);
            } else {
                $npid = static::getGUID('Dbnode');
                $nodeId = $el[0];
            }

            $cfg = $treeNodeGUIDConfigs[$npid];
            if (empty($treeNodeGUIDConfigs[$npid])) {
                $cfg = ['class' => 'Casebox\\CoreBundle\\Service\\TreeNode\\Dbnode', 'guid' => $npid];
            }

            $class = new $cfg['class']($cfg, $nodeId);
            // Set parent node
            if (!empty($rez)) {
                $class->parent = $rez[sizeof($rez) - 1];
            }

            array_push($rez, $class);
        }

        return $rez;
    }

    /**
     * Get GUID for a given virtual tree node name
     *
     * @param  string $name
     * @return int
     */
    public static function getGUID($name)
    {
        $rez = static::getGUIDs([$name]);
        $rez = empty($rez[$name]) ? null : $rez[$name];

        return $rez;
    }

    /**
     * Get GUIDs virtual tree node names array
     *
     * @param  array $names
     * @return int
     */
    public static function getGUIDs($names)
    {
        $rez = [];
        $guids = Cache::get('GUIDS', []);

        if (!empty($guids)) {
            foreach ($names as $name) {
                if (!empty($guids[$name])) {
                    $rez[$name] = $guids[$name];
                }
            }

            // Remove names retreived from cache
            $names = array_diff($names, array_keys($rez));
        }

        // Get remained names from db
        if (!empty($names)) {
            $dbNames = DM\GUID::readNames($names);
            $guids = array_merge($guids, $dbNames);
            $rez = array_merge($rez, $dbNames);

            //remove names retreived from db
            $names = array_diff($names, array_keys($rez));
        }

        // Create guids for remained names
        foreach ($names as $name) {
            $rez[$name] = DM\GUID::create(['name' => $name]);

            $guids[$name] = $rez[$name];
        }

        // Update cache variable
        Cache::set('GUIDS', $guids);

        return $rez;
    }

    /**
     * Try to detect real target id from a given path/path element
     * @param  string|null $p Path or path element
     * @return int         | null
     */
    public static function detectRealTargetId($p)
    {
        $rootId = Browser::getRootFolderId();
        $rez = $rootId;
        if (empty($p)) {
            return $rez;
        }

        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');

        $treeNodeConfigs = $configService->get('treeNodes', ['Dbnode' => []]);
        $GUIDConfigs = [];
        $guids = static::getGUIDs(array_keys($treeNodeConfigs));
        foreach ($treeNodeConfigs as $plugin => $cfg) {
            $class = empty($cfg['class']) ? 'Casebox\\CoreBundle\\Service\\TreeNode\\'.$plugin : $cfg['class'];
            $cfg['guid'] = $guids[$plugin]; //static::getGUID($plugin);
            $cfg['class'] = $class;
            $GUIDConfigs[$cfg['guid']] = $cfg;
        }

        $path = explode('/', @$p);
        while (!empty($path) && empty($path[0])) {
            array_shift($path);
        }
        while (!empty($path) && empty($path[sizeof($path) - 1])) {
            array_pop($path);
        }
        if (empty($path)) {
            return $rez;
        }

        $rez = null;
        while (is_null($rez) && !empty($path)) {
            $el = array_pop($path);
            if (is_numeric($el)) { //it's a real node id
                $rez = $el;
            } else {
                $arr = explode('-', $el);
                $guid = $arr[0];
                if (!empty($GUIDConfigs[$guid]['realNodeId'])) {
                    $rez = $GUIDConfigs[$guid]['realNodeId'];
                }
            }
        }

        if (empty($rez) || ($rez == 'root')) {
            $rez = $rootId;
        }

        return $rez;
    }
}
