<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\Source;
use App\Entity\Target;
use App\Repository\TargetRepository;
use App\Util\HashCache;
use Doctrine\ORM\EntityManagerInterface;
use JsonMachine\Items;
use Psr\Log\LoggerInterface;
use Survos\JsonlBundle\IO\JsonlReader;
use Survos\JsonlBundle\IO\JsonlWriter;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Ask;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Survos\BabelBundle\Util\HashUtil;
#[AsCommand(
    'app:legacy:translations',
    'Convert legacy translation dump to source.jsonl + target.jsonl (rejecting duplicate keys).'
)]
class ExportTranslationsCommand
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/data/')] private string $dataDir,
        private readonly LoggerInterface                                  $logger,
        private readonly EntityManagerInterface $entityManager,
        private readonly TargetRepository $targetRepository,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,

        #[Argument('Action'), Ask('convert to jsonl or import to doctrine? (convert/import)')]
        string $action,

        #[Argument(description: 'Path to the legacy JSON dump')]
        string $path = 'translations.json',

        #[Option(description: 'Limit how many items to process')]
        int $limit = 0,
    ): int {
        $inputFile = $this->dataDir . $path;

        if (!is_file($inputFile)) {
            $io->error("Input file not found: $inputFile");
            return Command::FAILURE;
        }

        if ($action === 'convert') {
            $this->export($io, $inputFile, $limit);
        } elseif ($action === 'import') {
            $this->import($io, $limit);
        }


        return Command::SUCCESS;
    }

    // ------------------------------------------------------------------
    // IMPORT: JSONL → Doctrine entities
    // ------------------------------------------------------------------
    private function import(SymfonyStyle $io, int $limit): int
    {
        $io->title("Importing JSONL into Doctrine…");

        $sourceFile = $this->dataDir . 'source.dedup.jsonl';
        $targetFile = $this->dataDir . 'target.jsonl';
        $cacheDb = $this->dataDir . 'import-cache.sqlite';
        if (file_exists($cacheDb)) {
            // delete this when we purge!
//            unlink($cacheDb);
        }
        $cache = new HashCache($cacheDb);

        $sourceReader = JsonlReader::open($sourceFile);
        $targetReader = JsonlReader::open($targetFile);

        // ----------------------------------------------
        // PHASE 1: IMPORT SOURCES
        // ----------------------------------------------
        $io->section("Importing sources…");

        $batch = 5000;
        $i = 0;
        assert(file_exists($sourceFile), "Missing $sourceFile");

        // Count lines for progress bar
        $totalSources = (int) trim(shell_exec("wc -l < " . escapeshellarg($sourceFile)));
        $progress = $io->createProgressBar($totalSources);

        foreach ($sourceReader as $row) {
            $progress->advance();
            $i++;

            if ($limit && $i > $limit) break;

            $hash = $row['hash'];

            // skip if exists in cache
            if ($cache->has($hash)) {
                $io->warning("Hash already exists: $hash");
                continue;
            }

            $source = new Source($row['text'], $row['locale'], $hash);
            $this->entityManager->persist($source);

            // mark it so future runs skip it
            $cache->add($hash);

            if ($i % $batch === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
        $progress->finish();



// ----------------------------------------------
// PHASE 2: IMPORT TARGETS
// ----------------------------------------------
        $io->section("Importing targets…");

        $sourceRepo = $this->entityManager->getRepository(Source::class);

// Count lines
        $totalTargets = (int) trim(shell_exec("wc -l < " . escapeshellarg($targetFile)));
        $progress = $io->createProgressBar($totalTargets);

        $batch = 5000;
        $i     = 0;

// In-run dedupe set (new key => true)
        $seen = [];

// Existing targets by key (so we don't conflict with old partial runs)
//        $existingKeys = $this->targetRepository->createQueryBuilder('t')
//            ->select('t.key')
//            ->getQuery()
//            ->getSingleColumnResult();
//
//        foreach ($existingKeys as $k) {
//            $seen[$k] = true;
//        }

        foreach ($targetReader as $row) {
            $progress->advance();
            $i++;

            if ($limit && $i > $limit) {
                break;
            }

            $sourceHash = $row['source_hash'];
            $targetLocale = $row['targetLocale'];

            $source = $sourceRepo->findOneBy(['hash' => $sourceHash]);
            if (!$source) {
                // Shouldn't happen if sources are imported correctly
                continue;
            }

            // New canonical key: ignores engine, matches HashUtil and Target::calcKey
            $key = HashUtil::calcTranslationKey($source->hash, $targetLocale, null);

            // Skip if already imported (either previously in DB or earlier in this run)
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            // Create Target using new key scheme; ignore JSONL key/engine
            $target = new Target(
                source:       $source,
                targetLocale: $targetLocale,
                engine:       null,
                key:          $key,
            );

            // Direct property updates, no setters
            $target->targetText = $row['text'] ?? null;
            $target->marking    = $row['marking'] ?? null;

            $this->entityManager->persist($target);

            if ($i % $batch === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();

                // NOTE: $seen is just an array of strings, unaffected by clear()
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
        $progress->finish();
        return Command::SUCCESS;
    }

    /**
     * @param SymfonyStyle $io
     * @param string $inputFile
     * @param int $limit
     * @return void
     * @throws \JsonMachine\Exception\InvalidArgumentException
     */
    final public function export(SymfonyStyle $io, string $inputFile, int $limit): void
    {
        $io->writeln("<info>Reading:</info> $inputFile");

        $sourceWriter = JsonlWriter::open($this->dataDir . 'source.jsonl');
        $targetWriter = JsonlWriter::open($this->dataDir . 'target.jsonl');

        $seenSourceKeys = [];
        $seenTargetKeys = [];

        $count = 0;

        foreach (Items::fromFile($inputFile) as $row) {
            $count++;

            if ($limit && $count > $limit) {
                break;
            }

            // -----------------------------
            // WRITE SOURCE
            // -----------------------------
            $sourceKey = $row->hash;

            if (isset($seenSourceKeys[$sourceKey])) {
                throw new \LogicException("Duplicate source hash detected: $sourceKey");
            }
            $seenSourceKeys[$sourceKey] = true;


            $sourceWriter->write([
                'hash' => $sourceKey,
                'text' => $row->text,
                'locale' => $row->locale,
            ]);

            // -----------------------------
            // WRITE TARGETS
            // -----------------------------
            foreach ($row->targets as $t) {
                if (!property_exists($t, 'targetLocale') || !property_exists($t, 'engine')) {
                    throw new \LogicException("Malformed target entry for hash $sourceKey");
                }

                // The fast-fail duplicate detection
                $targetKey = sprintf('%s-%s-%s', $sourceKey, $t->targetLocale, $t->engine);

                if (isset($seenTargetKeys[$targetKey])) {
                    throw new \LogicException("Duplicate target key detected: $targetKey");
                }
                $seenTargetKeys[$targetKey] = true;

                $targetWriter->write([
                    'key' => $targetKey,
                    'source_hash' => $sourceKey,
                    'targetLocale' => $t->targetLocale,
                    'engine' => $t->engine,
                    'text' => $t->targetText ?? null,
                    'marking' => $t->marking ?? null,
                ]);
            }
        }

        $sourceWriter->close();
        $targetWriter->close();

        $io->success("Export complete.");
        $io->writeln("✔ source.jsonl: " . count($seenSourceKeys));
        $io->writeln("✔ target.jsonl: " . count($seenTargetKeys));
    }
}
