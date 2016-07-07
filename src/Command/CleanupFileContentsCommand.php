<?php
namespace Casebox\CoreBundle\Command;

use Casebox\CoreBundle\Service\Cache;
use Casebox\CoreBundle\Service\System;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CleanupTrashBinCommand
 */
class CleanupFileContentsCommand extends ContainerAwareCommand
{
    /**
     * Configure
     */
    protected function configure()
    {
        $this
            ->setName('casebox:cleanup:filecontents')
            ->setDescription('Remove unused file contents. Use with "sudo" command to avoid file permission exceptions.')
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

        $configService = $container->get('casebox_core.service.config');
        $filesDir = realpath($configService->get('files_dir')) . '/';

        $dbs->query('CALL p_update_files_content__ref_count()');

        $iterator = new \RecursiveDirectoryIterator($filesDir);

        $bytes = 0;
        $count = 0;
        foreach (new \RecursiveIteratorIterator($iterator) as $fileName => $cur) {
            if (is_file($fileName)) {
                $id = basename($fileName);

                $res = $dbs->query('SELECT id from files_content where id = $1', $id);
                if (!$res->fetch()) {
                    $output->writeln($fileName);
                    $count++;
                    $bytes += $cur->getSize();
                    unlink($fileName);
                }
            }
        }

        $output->writeln("Total deleted: $count files, " . number_format($bytes) . " bytes\n");
    }
}
