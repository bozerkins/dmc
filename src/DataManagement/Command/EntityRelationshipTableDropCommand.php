<?php

namespace DataManagement\Command;

use DataManagement\Model\EntityRelationship\Table;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EntityRelationshipTableDropCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('dmc:er:table-drop')
            ->addArgument('table',  InputArgument::REQUIRED, 'Location of instructions file for the table')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $table = Table::newFromInstructionsFile($input->getArgument('table'));
        $table->storage()->remove();
    }
}