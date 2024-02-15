<?php

namespace App\Command;

use App\Dto\Constraints;
use App\Repository\ElasticRepository;
use LogicException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Stopwatch\Stopwatch;

#[AsCommand(name: 'docsearch:index:delete', description: 'Removes from index manuals by given constraints')]
class IndexCleaner extends Command
{
    public function __construct(private readonly ElasticRepository $elasticRepository)
    {
        parent::__construct();
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function configure(): void
    {
        $this->addOption('manual-slug', 'ms', InputArgument::OPTIONAL, 'Manula path', '');
        $this->addOption('manual-version', 'mv', InputArgument::OPTIONAL, 'Manual version', '');
        $this->addOption('manual-type', 'mt', InputArgument::OPTIONAL, 'Manual type', '');
        $this->addOption('manual-language', 'ml', InputArgument::OPTIONAL, 'Manual language', '');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws LogicException
     * @throws RuntimeException
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timer = new Stopwatch();
        $timer->start('importer');

        $io = new SymfonyStyle($input, $output);
        $io->title('Removing from index documents by provided criteria');

        $constraints = new Constraints(
            $input->getOption('manual-slug'),
            $input->getOption('manual-version'),
            $input->getOption('manual-type'),
            $input->getOption('manual-language')
        );

        $deletedManualsCount = $this->elasticRepository->deleteByConstraints($constraints);

        $totalTime = $timer->stop('importer');
        $io->info('Finished after ' . $this->formatMilliseconds($totalTime->getDuration()) . '. Total of ' . $deletedManualsCount . ' manuals were removed.');

        return Command::SUCCESS;
    }

    private function formatMilliseconds(int $milliseconds): string
    {
        $t = intdiv($milliseconds, 1000);
        return sprintf('%02d:%02d:%02d', (int)($t / 3600), (int)($t / 60) % 60, $t % 60);
    }
}
