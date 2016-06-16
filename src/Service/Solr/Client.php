<?php
namespace Casebox\CoreBundle\Service\Solr;

use Casebox\CoreBundle\Event\BeforeSolrUpdateEvent;
use Casebox\CoreBundle\Event\SolrUpdateEvent;
use Casebox\CoreBundle\Service\Cache;
use Casebox\CoreBundle\Service\Config;
use Casebox\CoreBundle\Service\Templates\SingletonCollection;
use Casebox\CoreBundle\Service\Util;
use Casebox\CoreBundle\Service\DataModel as DM;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Process\Process;

/**
 * Class Client
 * Solr client class used by CaseBox to make changes into solr
 */
class Client extends Service
{
    /**
     * Running background cron for updating tree changes into solr
     *
     * This method is obsolete, should be reviewed
     */
    public static function runBackgroundCron()
    {
        /** @var Container $container */
        $container = Cache::get('symfony.container');
        $rootDir = $container->getParameter('kernel.root_dir');

        $config = Cache::get('platformConfig');

        $cmd = $rootDir.'/../bin/console'.' '.'casebox:solr:update --env='.$config['coreName'];

        $process = new Process($cmd);
        $process->run();
    }

    /**
     * prepare a record with data from database to be indexed in solr
     *
     * @param array $r
     *
     * @return void
     */
    private function prepareDBRecord(&$r)
    {
        // Set template data
        if (!empty($r['template_id'])) {
            $template = SingletonCollection::getInstance()->getTemplate($r['template_id']);
            $r['template_type'] = $template->getData()['type'];
            $r['iconCls'] = $template->getData()['iconCls'];
        }

        // Consider node type sort column (ntsc) equal to 1 unit more than total count of folder templates
        $r['ntsc'] = sizeof($this->folderTemplates) + 100;

        // Decrease ntsc (make 1 unit more important) in case of 'case' object types
        if (@$r['template_type'] == 'case') {
            $r['ntsc']--;
        }

        // If there is a folder template then set its ntsc equal to its index in folder_templates array
        if (in_array($r['template_id'], $this->folderTemplates)) {
            $r['ntsc'] = 1;
        }

        // Make some trivial type checks
        $r['ntsc'] = intval($r['ntsc']);
        $r['system'] = @intval($r['system']);

        if (empty($r['pids'])) {
            $r['pids'] = null;
            $r['path'] = null;
        } else {
            $r['pids'] = explode(',', $r['pids']);
            // Exclude itself from pids
            array_pop($r['pids']);
            $r['path'] = implode('/', $r['pids']);
        }

        if (!isset($r['content'])) {
            $r['content'] = null;
        }

        // Fill "ym" fields for date faceting by cdate, date, date_end
        $ym1 = str_replace('-', '', substr($r['cdate'], 2, 5));
        $ym2 = str_replace('-', '', substr($r['date'], 2, 5));
        $ym3 = str_replace('-', '', substr($r['date_end'], 2, 5));

        if (empty($ym3)) {
            $ym3 = $ym2;
        }

        if (!empty($ym1)) {
            $r['ym1'] = $ym1;
        }

        if (!empty($ym2)) {
            $r['ym2'] = $ym2;
        }

        if (!empty($ym3)) {
            $r['ym3'] = $ym3;
        }

        if (!empty($r['sys_data']['solr'])) {
            foreach ($r['sys_data']['solr'] as $k => $v) {
                $r[$k] = $v;
            }
        }

        // Encode special chars for string values
        foreach ($r as $k => $v) {
            if (is_string($v)) {
                $r[$k] = htmlspecialchars($v, ENT_COMPAT);
            }
        }

        // Add last_action_tdt field
        $la = empty($r['udate']) ? $r['cdate'] : $r['udate'];
        if (!empty($r['sys_data']['lastAction'])) {
            $la = $r['sys_data']['lastAction']['time'];
        } elseif (!empty($r['sys_data']['lastComment']) && ($r['sys_data']['lastComment'] > $la)) {
            $la = $r['sys_data']['lastComment']['date'];
        }
        $r['last_action_tdt'] = $la;

        $this->filterSolrFields($r);
    }

    /**
     * Append file contents to content field for file records
     *
     * @param array &$records
     *
     * @return void
     */
    protected function appendFileContents(&$records)
    {
        $fileRecords = [];

        foreach ($records as &$r) {
            if (!empty($r['template_type']) && ($r['template_type'] == 'file')) {
                $fileRecords[$r['id']] = &$r;
            }
        }

        if (!empty($fileRecords)) {
            $filesDir = $this->configService->get('files_dir');

            $cpaths = DM\Files::getContentPaths(array_keys($fileRecords));

            foreach ($cpaths as $id => $cpath) {
                $r = &$fileRecords[$id];

                if (!isset($r['content'])) {
                    $r['content'] = '';
                }

                $filename = $filesDir.$cpath.'.gz';

                if (file_exists($filename)) {
                    $content = file_get_contents($filename);
                    $r['content'] .= "\n".mb_substr(gzuncompress($content), 0, 1024 * 1024); //max 1MB
                }
                unset($content);
                unset($r);
            }
        }
    }

    /**
     * Update tree nodes into solr
     *
     * @param string[ $p {
     *
     * @type boolean $all if true then all nodes will be updated into solr,
     *          otherwise - only the nodes marked as updated will be reindexed in solr
     * @type int[] $id id or array of object ids to update
     * @type string $cron_id when this function is called by a cron then cron_id should be passed
     * @type boolean $nolimit if true then no limit will be applied to maximum indexed nodes (default 2000)
     * }
     */
    public function updateTree($p = [])
    {
        // Connect to solr service
        $this->connect();

        $eventParams = [
            'class' => &$this,
            'params' => &$p,
        ];
        $this->folderTemplates = $this->configService->get('folder_templates');

        /** @var EventDispatcher $dispatcher */
        $dispatcher = Cache::get('symfony.container')->get('event_dispatcher');
        $dispatcher->dispatch('onBeforeSolrUpdate', new BeforeSolrUpdateEvent($eventParams));

        $dbs = Cache::get('casebox_dbs');

        // @type int the last processed document id
        $lastId = 0;
        $indexedDocsCount = 0;
        $all = !empty($p['all']);
        $nolimit = !empty($p['nolimit']);
        $this->deleteNestedDocs = true;

        // Prepare where condition for sql depending on incomming params
        $where = '(t.updated > 0) AND (t.draft = 0) AND (t.id > $1)';

        if ($all) {
            $this->deleteNestedDocs = false;
            $this->deleteByQuery('*:*');
            $where = '(t.id > $1) AND (t.draft = 0) ';

            SingletonCollection::getInstance()->loadAll();

        } elseif (!empty($p['id'])) {
            $ids = Util\toNumericArray($p['id']);
            $where = '(t.id in (0'.implode(',', $ids).')) and (t.id > $1)';
        }

        $sql = 'SELECT t.id,
                t.id doc_id,
                t.pid,
                ti.pids,
                ti.case_id,
                ti.acl_count,
                ti.security_set_id,
                t.name,
                t.system,
                t.template_id,
                t.target_id,
                t.size,
                DATE_FORMAT(t.`date`, \'%Y-%m-%dT%H:%i:%sZ\') `date`,
                DATE_FORMAT(t.`date_end`, \'%Y-%m-%dT%H:%i:%sZ\') `date_end`,
                t.oid,
                t.cid,
                DATE_FORMAT(t.cdate, \'%Y-%m-%dT%H:%i:%sZ\') `cdate`,
                t.uid,
                DATE_FORMAT(t.udate, \'%Y-%m-%dT%H:%i:%sZ\') `udate`,
                t.did,
                DATE_FORMAT(t.ddate, \'%Y-%m-%dT%H:%i:%sZ\') `ddate`,
                t.dstatus,
                t.updated,
                o.sys_data
            FROM tree t
            LEFT JOIN tree_info ti ON t.id = ti.id
            LEFT JOIN objects o ON o.id = t.id
            where '.$where.'
            ORDER BY t.id
            LIMIT 500';

        $docs = true;

        while (!empty($docs) && ($nolimit || ($indexedDocsCount < 2000))) {
            $docs = [];

            $res = $dbs->query($sql, $lastId);
            while ($r = $res->fetch()) {
                $lastId = $r['id'];

                /* process full object update only if:
                    - updated = 1
                    - specific ids are specified
                    - if $all parameter is true
                */
                if ($all || !empty($p['id']) || ($r['updated'] & 1)) {
                    $r['sys_data'] = Util\toJsonArray($r['sys_data']);

                    $this->prepareDBRecord($r);

                    $docs[$r['id']] = $r;
                }
            }
            unset($res);

            if (!empty($docs)) {
                // Append file contents for files to content field
                $this->appendFileContents($docs);

                $this->deleteByQuery('id:('.implode(' OR ', array_keys($docs)).')');

                $this->addDocuments($docs);

                $this->commit();

                // Reset updated flag into database for processed documents
                $dbs->query(
                    'UPDATE
                        tree,
                        tree_info
                    SET
                        tree.updated = 0,
                        tree_info.updated = 0
                    WHERE tree.id in ('.implode(',', array_keys($docs)).')
                        AND tree_info.id = tree.id'
                );

                $indexedDocsCount += sizeof($docs);
            }
        }

        $this->updateTreeInfo($p);

        $dispatcher->dispatch('onSolrUpdate', new SolrUpdateEvent($eventParams));
    }

    /**
     * Updating modified nodes info into solr from tree)info table
     */
    private function updateTreeInfo($p)
    {
        // Connect to solr service
        $this->connect();

        // @type int the last processed document id
        $lastId = 0;

        // Prepare $where condition for sql
        $where = 'ti.id > $1';
        if (!empty($p['id'])) {
            $ids = \Casebox\CoreBundle\Service\Util\toNumericArray($p['id']);
            $where = 'ti.id in (0'.implode(',', $ids).')';
        }

        $dbs = Cache::get('casebox_dbs');

        $sql = 'SELECT ti.id
                    ,ti.id doc_id
                    ,ti.pids
                    ,ti.case_id
                    ,ti.acl_count
                    ,ti.security_set_id
                    ,t.name `case`
            FROM tree_info ti
            LEFT JOIN tree t
                ON ti.case_id = t.id
            WHERE '.$where.'
                AND ti.updated = 1
            ORDER BY ti.id
            LIMIT 200';

        $docs = true;
        while (!empty($docs)) {
            $docs = [];

            $res = $dbs->query($sql, $lastId);
            while ($r = $res->fetch()) {
                $lastId = $r['id'];
                $r['update'] = true;

                if (empty($r['pids'])) {
                    $r['pids'] = null;
                    $r['path'] = null;
                } else {
                    $r['pids'] = explode(',', $r['pids']);
                    // Exclude itself from pids
                    array_pop($r['pids']);
                    $r['path'] = implode('/', $r['pids']);
                }

                // Encode special chars for string values
                foreach ($r as $k => $v) {
                    if (is_string($v)) {
                        $r[$k] = htmlspecialchars($v, ENT_COMPAT);
                    }
                }

                $docs[$r['id']] = $r;
            }
            unset($res);

            if (!empty($docs)) {
                $this->addDocuments($docs);

                // Reset updated flag into database for processed documents info
                $dbs->query('UPDATE tree_info SET updated = 0 WHERE id IN ('.implode(', ', array_keys($docs)).')');

                $this->commit();
            }
        }
    }

    /**
     * @param array $doc
     */
    private function filterSolrFields(&$doc)
    {
        $some_fields = ['iconCls', 'updated', 'sys_data'];

        foreach ($doc as $fn => $fv) {
            if (in_array($fn, $some_fields) || empty($fn) || (($fv !== false) && ((!is_scalar($fv) && empty($fv)) || (is_scalar($fv) && (strlen($fv) == 0))))) {
                unset($doc[$fn]);
            }
        }
    }

    /**
     * Escape Lucene special chars
     *
     * Lucene characters that need escaping with \ are + - && || ! ( ) { } [ ] ^ " ~ * ? : \
     *
     * @param array $v incoming string
     *
     * @return array escaped variable
     */
    public static function escapeLuceneChars($v)
    {
        $luceneReservedChars = preg_quote('+-&|!(){}[]^"~*?:\\');
        $v = preg_replace_callback(
            '/(['.$luceneReservedChars.'])/',
            function ($matches) {
                return '\\'.$matches[0];
            },
            $v
        );

        return $v;
    }
}
