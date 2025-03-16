<?php

namespace App\Command;

use App\Entity\Source;
use App\Entity\Target;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\SerializerInterface;
use Zenstruck\Bytes;
use Zenstruck\Console\Attribute\Argument;
use Zenstruck\Console\Attribute\Option;
use Zenstruck\Console\InvokableServiceCommand;
use Zenstruck\Console\IO;
use Zenstruck\Console\RunsCommands;
use Zenstruck\Console\RunsProcesses;
use \ZipArchive;

#[AsCommand('app:export', 'Dump the entire database, and optionally zip it')]
final class AppExportCommand extends InvokableServiceCommand
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
        IO                                               $io,
        #[Autowire('%kernel.project_dir%/data/')] string $dataDir,

        #[Argument(description: 'path where the json file will be written')]
        string                                           $path = 'translations.json',

        #[Option(description: 'zip upon completion')]
        bool                                             $zip = true,

//        #[Option(description: 'migrate from version 1 target structure')]
//        ?bool   $legacy = null,

        #[Option(description: 'pretty-print the export')]
        bool   $pretty = false,

        #[Option(description: 'limit the number of records')]
        int $limit = 0,

        #[Option(description: 'start at')]
        int                         $start = 0,

        #[Option(description: 'batch size for reading rows')]
        int $batch = 1000
    ): int
    {

//        $legacy ??= true;
        $io->warning("Run this with no-debug to conserve memory");

        $count =  $this->sourceRepository->count();
        $progressBar = new ProgressBar($io->output(), $count);
//        $progressBar->setFormat('very_verbose');

        $progressBar->setFormat(
            "<fg=white;bg=cyan> %status:-45s%</>\n%current%/%max% [%bar%] %percent:3s%%\n🏁  %estimated:-21s% %memory:21s%"
        );
        $progressBar->setBarCharacter('<fg=green>⚬</>');
        $progressBar->setEmptyBarCharacter("<fg=red>⚬</>");
        $progressBar->setProgressCharacter("<fg=green>➤</>");

        $progressBar->setRedrawFrequency(100);
//        $qb =  $this->sourceRepository->createQueryBuilder('s')
//            ->getQuery()
//            ->toIterable();
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0777, true);
        }
        $f = fopen($filename = $dataDir . $path, 'w');
        fwrite($f, "[");
        /**
         * @var  $idx
         * @var Source $source
         */

        $count = 0;
        $sourceCount = 0;
        $targetCounts = [];
        $first = true;
        foreach ($this->iterate($batch) as $idx => $source) {
            if ($start && ($idx < $start))  continue;

//        foreach ($qb as $idx => $source) {
            $progressBar->advance();
            $sourceCount+=strlen($source->getText());
            $count++;
            foreach ($source->getTranslations() as $locale => $translation) {
                if (!array_key_exists($locale, $targetCounts)) {
                    $targetCounts[$locale] = 0;
                }
                $targetCounts[$locale]+=strlen($translation);
            }

            if (!$first) fwrite($f, "\n,\n");
            $first = false;
            $json = $this->serializer->serialize($source, 'json', ['groups' => ['source.export', 'marking', 'source.read']]);
            if ($pretty) {
                $json = json_encode(json_decode($json), JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE );
            } else {
                $json = json_encode(json_decode($json), JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE );
            }
            fwrite($f, $json);
            $this->entityManager->detach($source);
            $this->entityManager->clear();
            if ($limit && ($idx >= $limit)) {
                break;
            }
        }
        fwrite($f, "\n]");
        fclose($f);
        $progressBar->finish();

        file_put_contents($metaFilename = $dataDir . '/meta.json', json_encode([
            'count' => $count,
            'source_count' => $sourceCount,
            'target_counts' => $targetCounts,
        ], JSON_PRETTY_PRINT));

        if ($zip) {
            $zipFile = $dataDir . 'translations.zip';
            if (file_exists($zipFile)) {
                unlink($zipFile);
            }
            $zip = new ZipArchive;
            if ($zip->open($zipFile, ZipArchive::CREATE)) {

                // add the count so that we can use progressBar when importing.  Or add a meta file?
                $zip->addFile($metaFilename, 'meta.json');
                $zip->addFile($filename, 'translations.json');
                $zip->close();
                $io->success($zipFile . sprintf(" written with $idx records %s",  Bytes::parse(filesize($zipFile))));
            } else {
                $io->error('Failed to open zip file. '.$zipFile );
            }
        } else {
            $io->success($filename . " written with $idx records: " );
        }

        return self::SUCCESS;
    }

    // https://medium.com/@vitoriodachef/a-closer-look-at-doctrine-orm-query-toiterable-when-processing-large-results-ee813b6ec7d7
    private function iterate(int $batchSize): \Generator
    {
        $leftBoundary = 0;
        $queryBuilder = $this->sourceRepository->createQueryBuilder('c');

        do {
            $qb = clone $queryBuilder;
            $qb->andWhere('c.id > :leftBoundary')
                ->setParameter('leftBoundary', $leftBoundary)
                ->orderBy('c.id', 'ASC')
                ->setMaxResults($batchSize)
            ;

            $lastReturnedContract = null;
            foreach ($qb->getQuery()->toIterable() as $lastReturnedContract) {
                yield $lastReturnedContract;
            }

            if ($lastReturnedContract) {
                $leftBoundary = $lastReturnedContract->getId();
            }


        } while (null !== $lastReturnedContract);
    }
}
