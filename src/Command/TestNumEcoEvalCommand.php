<?php

namespace GlpiPlugin\Carbon\Command;

use GlpiPlugin\Carbon\Impact\Embodied\NumEcoEval\Peripheral;
use Peripheral as GlpiPeripheral;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Override;

class TestNumEcoEvalCommand extends Command
{
    #[Override]
    protected function configure()
    {
        $this
            ->setName('plugins:carbon:test_numecoeval')
            ->setDescription('Run test NumEcoEval calculation');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("<info>Starting NumEcoEval test calculation...</info>");

        try {
            $count = Peripheral::evaluateAll(GlpiPeripheral::class);
            $output->writeln("<info>Success! Evaluated $count items.</info>");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln("<error>Error: " . $e->getMessage() . "</error>");
            $output->writeln($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
