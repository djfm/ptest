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
            );
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $test_case = $input->getArgument('test_class_or_directory');

        $runner = new \PrestaShop\Ptest\Runner($test_case);

        //$output->writeln($test_case);
    }
}