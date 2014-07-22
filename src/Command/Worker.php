<?php

namespace PrestaShop\Ptest\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Worker extends Command
{
    protected function configure()
    {
        $this
            ->setName('work')
            ->setDescription('Execute a test plan.')
            ->addArgument(
                'test_plan',
                InputArgument::REQUIRED,
                'JSON file containing plan description'
            )
            ->addArgument(
                'output_file',
                InputArgument::REQUIRED,
                'Where to store the output of the test'
            )
            ->addOption(
                'bootstrap', 'b', InputOption::VALUE_REQUIRED, 'Bootstrap file'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ini_set('display_errors', 'stderr');

        $runner = new \PrestaShop\Ptest\Runner(
            $input->getArgument('test_plan'),
            $input->getArgument('output_file'),
            $input->getOption('bootstrap')
        );

        $runner->run();
    }
}