<?php

namespace App\Command;

use App\Entity\Source;
use App\Entity\Target;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use JsonMachine\Items;
use Psr\Log\LoggerInterface;
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
        private SourceRepository       $sourceRepository,
        private EntityManagerInterface $entityManager,
        private SerializerInterface    $serializer,
        private LoggerInterface        $logger,
    )
    {
        parent::__construct();
    }

    public function __invoke(
        IO                          $io,
        #[Autowire('%kernel.project_dir%/data/')] string $dataDir,

        #[Argument(description: 'path where the zip will be read?')]
        string                      $path = 'translations.json',

        #[Option(description: 'overwrite the database entry')]
        bool                        $overwrite = false,
        #[Option(description: 'batch size for flushing database')]
        int                         $batch = 1000,

        #[Option(description: 'limit import')]
        int                         $limit = 0,

        #[Option(description: 'start at')]
        int                         $start = 0,

        #[Option(description: 'purge first')]
        bool                        $purge = false,

    ): int
    {

//        $this->entityManager->getConfiguration()->setSQLLogger(null);

        if ($purge) {
            foreach ([Target::class, Source::class] as $class) {
                $qb = $this->entityManager->createQuery("DELETE FROM $class");
                $x = $qb->execute();
                $io->warning("Purged $x from $class");
            }
        }

        $meta = json_decode(file_get_contents('data/meta.json'), true);
        $count = $meta['count'];
        $progressBar = new ProgressBar($io->output(), $count);
        $progressBar->setFormat(
            "<fg=white;bg=cyan> %status:-45s%</>\n%current%/%max% [%bar%] %percent:3s%%\n🏁  %estimated:-21s% %memory:21s%"
        );
        $progressBar->setBarCharacter('<fg=green>⚬</>');
        $progressBar->setEmptyBarCharacter("<fg=red>⚬</>");
        $progressBar->setProgressCharacter("<fg=green>➤</>");

        $sources = Items::fromFile($dataDir . $path);
        $tempObjets = [];
//        $this->entityManager->beginTransaction();
        foreach ($sources as $idx => $row) {

            $progressBar->advance();
            if ($start && ($idx < $start)) {
                continue;
            }
            $source = $this->addRow($row);
            $tempObjets[] = $source;
            if ($idx % $batch === 0) {
//                $this->logger->warning("Flushing $idx, $batch");
//                $this->entityManager->commit();
                $this->entityManager->flush();
                // https://stackoverflow.com/questions/33427109/memory-usage-goes-wild-with-doctrine-bulk-insert/33476744#33476744
                array_walk($tempObjets, fn($entity) => $this->entityManager->detach($entity));
                $this->entityManager->clear();

                $tempObjets = [];
                gc_enable();
                gc_collect_cycles();
//                $this->entityManager->beginTransaction();
            }

            if ($limit && ($idx >= $limit)) {
                break;
            }

        }
        $progressBar->finish();
//        $this->entityManager->commit();
        $this->entityManager->flush();
        $io->success($this->getName() . ' success: ' . $this->sourceRepository->count());

        return self::SUCCESS;
    }

    private function addRow(object $row): Source
    {
//            if (!$source = $this->sourceRepository->findOneBy(['hash' => $row->hash])) {
//                $source = new Source($row->text, $row->locale, $row->hash);
//            }
//        $this->logger->warning($row->hash . ' / ' . $row->text);
        $source = new Source($row->text, $row->locale, $row->hash);
        $this->entityManager->persist($source);
        foreach ($row->targets as $targetData) {
            if (!property_exists($targetData, 'marking')) {
                dd($row, $targetData);
            }
            if ($targetData->marking <> Target::PLACE_UNTRANSLATED) {
                $target = new Target($source, $targetData->targetLocale, $targetData->engine);
                $target
                    ->setTargetText($targetData->targetText)
                    ->setMarking($targetData->marking);
            }
        }
        return $source;

    }
}
