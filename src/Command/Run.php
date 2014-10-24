<?php

namespace PrestaShop\Ptest\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Run extends Command
{
    protected function configure()
    {
        $this
            ->setName('run')
            ->setDescription('Run one specific test case or everything within a folder')
            ->addArgument(
                'test_class_or_directory',
                InputArgument::REQUIRED,
                'Which test do you want to run?'
            )
            ->addOption('bootstrap', 'b', InputOption::VALUE_REQUIRED, 'Bootstrap file')
            ->addOption('processes', 'p', InputOption::VALUE_REQUIRED, 'Maximum number of parallel processes')
            ->addOption('info', 'i', InputOption::VALUE_NONE, 'Only display information, do not run any test')
            ->addOption('filter', 'f', InputOption::VALUE_REQUIRED, 'Filter tests by regular expression')
            ->addOption('data-provider-filter', 'z', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Filter datasets returned by the dataProviders')

        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filters = [];

        $runner = new \PrestaShop\Ptest\Runner();

        $runner->setMaxProcesses(max(1, (int)$input->getOption('processes')));

        $runner->setRoot(
            $input->getArgument('test_class_or_directory')
        );

        $runner->setDataProviderFilter(
            $input->getOption('data-provider-filter')
        );

        $runner->setInformationOnly(
            $input->getOption('info')
        );


        exit($runner->run());
    }
}