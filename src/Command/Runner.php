<?php

namespace PrestaShop\Ptest\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Runner extends Command
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
        $test_case = $input->getArgument('test_class_or_directory');

        $discoverer = new \PrestaShop\Ptest\Discoverer(
            $test_case,
            $input->getOption('bootstrap'),
            $input->getOption('filter'),
            $input->getOption('data-provider-filter')
        );

        $test_plans = $discoverer->getTestPlans();

        $runner = new \PrestaShop\Ptest\RunnerManager($test_plans, [
            'bootstrap_file' => $input->getOption('bootstrap'),
            'max_processes' => $input->getOption('processes'),
            'only_display_info' => $input->getOption('info')
        ]);

        exit($runner->run());
    }
}