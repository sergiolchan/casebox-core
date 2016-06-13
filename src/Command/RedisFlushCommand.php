<?php

namespace Casebox\CoreBundle\Command;

use Casebox\CoreBundle\Service\System;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\Translator;

/**
 * Class RedisFlushCommand
 */
class RedisFlushCommand extends ContainerAwareCommand
{
    /**
     * Configure
     */
    protected function configure()
    {
        $this
            ->setName('casebox:redis:flush')
            ->setDescription('Flush redis cache.');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output = new SymfonyStyle($input, $output);
        $container = $this->getContainer();
        $system = new System();
        $system->bootstrap($container);
        $container->get('casebox_core.service.redis_service')->flushAll();
        $output->success("DONE!");
    }
}
