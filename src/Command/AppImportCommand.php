<?php

namespace App\Command;

use App\Entity\Source;
use App\Entity\Target;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use JsonMachine\Items;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\SerializerInterface;
use Zenstruck\Console\Attribute\Argument;
use Zenstruck\Console\Attribute\Option;
use Zenstruck\Console\InvokableServiceCommand;
use Zenstruck\Console\IO;
use Zenstruck\Console\RunsCommands;
use Zenstruck\Console\RunsProcesses;

#[AsCommand('app:import', 'Import a json dump file to the database')]
final class AppImportCommand extends InvokableServiceCommand
{
    use RunsCommands;
    use RunsProcesses;

    public function __construct(
        private SourceRepository $sourceRepository,
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
    )
    {
        parent::__construct();
    }

    public function __invoke(
        IO $io,
        #[Autowire('%kernel.project_dir%/public/data/')] string $publicDir,
        #[Argument(description: 'path where the json file will be read')]
        string $path = 'dump.json',

        #[Option(description: 'overwrite the database entry')]
        bool $overwrite = false,
    ): int {
        $io->success($this->getName().' success.');

        $count =  $this->sourceRepository->count();
        $progressBar = new ProgressBar($io->output(), $count);
        $progressBar->setFormat('very_verbose');

        $sources = Items::fromFile($publicDir .  $path);
        foreach ($sources as $idx => $row) {
            $progressBar->advance();
            $source = $this->addRow($row);
            $this->entityManager->persist($source);

        }
        $progressBar->finish();
        $this->entityManager->flush();
        $io->success($this->getName().' success: ' . $this->sourceRepository->count());

        return self::SUCCESS;
    }

    private function addRow(object $row): Source
    {
//            if (!$source = $this->sourceRepository->findOneBy(['hash' => $row->hash])) {
//                $source = new Source($row->text, $row->locale, $row->hash);
//            }
        $source = new Source($row->text, $row->locale, $row->hash);
        foreach ($row->targets as $targetData) {
            $target = new Target($source, $targetData->targetLocale, $targetData->engine);
            $this->entityManager->persist($target);
            if (!property_exists($targetData, 'marking')) {
                dd($row, $targetData);
            }
            $target
                ->setTargetText($targetData->targetText)
                ->setMarking($targetData->marking);
        }
        return $source;

    }
}
