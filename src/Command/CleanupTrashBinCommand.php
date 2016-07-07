<?php
namespace Casebox\CoreBundle\Command;

use Casebox\CoreBundle\Service\Cache;
use Casebox\CoreBundle\Service\DataModel as DM;
use Casebox\CoreBundle\Service\Solr\Client;
use Casebox\CoreBundle\Service\System;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CleanupTrashBinCommand
 */
class CleanupTrashBinCommand extends ContainerAwareCommand
{
    /**
     * Configure
     */
    protected function configure()
    {
        $this
            ->setName('casebox:cleanup:trashbin')
            ->setDescription('Remove deleted records. Use with "sudo" command to avoid file permission exceptions.')
        ;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();

        $system = new System();
        $system->bootstrap($container);

        $coreName = $container->getParameter('kernel.environment');

        $dbs = Cache::get('casebox_dbs');

        $res = $dbs->query('SELECT id from tree where dstatus > 0');

        $count = 0;
        while ($r = $res->fetch()) {
            DM\Tree::delete($r['id'], true);
            $count++;
        }

        $output->writeln('Items deleted: ' . $count);

        $configService = $container->get('casebox_core.service.config');
        $filesDir = realpath($configService->get('files_dir')) . '/';

        $dbs->query('CALL p_update_files_content__ref_count()');
        $res = $dbs->query('SELECT id, path from files_content where ref_count < 1');

        $count = 0;
        while ($r = $res->fetch()) {
            $fileContent = $filesDir . $r['path'].'/'.$r['id'];
            if (file_exists($fileContent)) {
                unlink($fileContent);
            }
            DM\FilesContent::delete($r['id']);
            $count++;
        }

        $output->writeln('File contents deleted: ' . $count);

        $output->writeln('Reindexing solr');
        $solr = new Client();

        $params = [
            'core' => $coreName,
            'all' => true,
            'cron_id' => null,
            'nolimit' => true
        ];

        $solr->updateTree($params);

        $output->writeln('DONE!');
    }
}
