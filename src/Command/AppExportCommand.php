<?php

namespace App\Command;

use App\Entity\Source;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\SerializerInterface;

#[AsCommand('app:export', 'Dump the entire database as NDJSON, optionally compressed/sharded')]
final class AppExportCommand extends Command
{
    public function __construct(
        private SourceRepository $sourceRepository,
        private EntityManagerInterface $em,
        private SerializerInterface $serializer,
        #[Autowire('%kernel.project_dir%/data/')] private string $dataDir,
    ) { parent::__construct(); }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('output path (base name or directory)')]
        string $path = 'translations',

        #[Option('gzip on write (translations-*.ndjson.gz)')]
        bool $gzip = true,

        #[Option('pretty-print JSON (larger files)')]
        bool $pretty = false,

        #[Option('limit number of records (0 = all)')]
        int $limit = 0,

        #[Option('start id offset (skip ids <= start)')]
        int $start = 0,

        #[Option('db read batch size')]
        int $batch = 2000,

        #[Option('lines per shard (0 = single file)')]
        int $shardSize = 100_000
    ): int {
        $io->warning('Run with APP_DEBUG=0 and high memory_limit for best throughput.');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0777, true);
        }

        // Resolve base path & create file openers
        $base = rtrim($this->dataDir . $path, '/');
        $ext  = $gzip ? '.ndjson.gz' : '.ndjson';
        $makeShardName = fn(int $n) => sprintf('%s-%06d%s', $base, $n, $ext);
        $singleName    = $base . $ext;

        $total = $this->sourceRepository->count();
        $io->note("Estimated total rows: $total");

        $bar = new ProgressBar($io, $limit > 0 ? $limit : $total);
        $bar->setFormat("<fg=white;bg=cyan> Exporting… %-45s</>\n%current%/%max% [%bar%] %percent:3s%%\n🏁  %estimated:-21s% %memory:21s%");
        $bar->setBarCharacter('<fg=green>⚬</>');
        $bar->setEmptyBarCharacter("<fg=red>⚬</>");
        $bar->setProgressCharacter("<fg=green>➤</>");
        $bar->setRedrawFrequency(200);

        $written = 0;
        $inShard = 0;
        $shardNo = 1;
        $manifest = [
            'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'base' => basename($base),
            'gzip' => $gzip,
            'pretty' => $pretty,
            'shard_size' => $shardSize,
            'shards' => [],
        ];

        $open = function (string $filename) use ($gzip) {
            return $gzip
                ? gzopen($filename, 'wb9')  // gzip level 9
                : fopen($filename, 'wb');
        };
        $write = function ($handle, string $line) use ($gzip): void {
            $gzip ? gzwrite($handle, $line) : fwrite($handle, $line);
        };
        $close = function (&$handle) use ($gzip): void {
            if (!$handle) return;
            $gzip ? gzclose($handle) : fclose($handle);
            $handle = null;
        };

        $currentFile = null;
        $currentName = null;
        $openNewShard = function () use (&$currentFile, &$currentName, $shardNo, $makeShardName, $open, &$manifest) {
            $currentName = $makeShardName($shardNo);
            $currentFile = $open($currentName);
            $manifest['shards'][] = ['file' => basename($currentName), 'lines' => 0, 'sha256' => null];
        };

        if ($shardSize > 0) {
            $openNewShard();
        } else {
            $currentName = $singleName;
            $currentFile = $open($currentName);
            $manifest['shards'][] = ['file' => basename($currentName), 'lines' => 0, 'sha256' => null];
        }

        $hashCtx = hash_init('sha256');

        foreach ($this->iterate($batch, $start) as $row) {
            /** @var Source $row */
            if ($limit && $written >= $limit) break;

            $json = $this->serializer->serialize($row, 'json', [
                'groups' => ['source.export', 'marking', 'source.read']
            ]);
            $json = json_encode(json_decode($json, true, flags: JSON_THROW_ON_ERROR),
                ($pretty ? JSON_PRETTY_PRINT : 0) | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            // NDJSON: one object per line
            $write($currentFile, $json . "\n");
            hash_update($hashCtx, $json . "\n");
            $written++;
            $inShard++;
            $manifest['shards'][count($manifest['shards']) - 1]['lines']++;

            // Batch EM clear
            if (($written % $batch) === 0) {
                $this->em->clear();
            }

            // Shard rollover
            if ($shardSize > 0 && $inShard >= $shardSize) {
                $close($currentFile);

                // finalize checksum for shard
                $cksum = hash_final($hashCtx, false);
                $manifest['shards'][count($manifest['shards']) - 1]['sha256'] = $cksum;

                // reset for next shard
                $hashCtx = hash_init('sha256');
                $inShard = 0;
                $shardNo++;
                $openNewShard();
            }

            $bar->advance();
        }

        // finalize last shard
        $close($currentFile);
        $cksum = hash_final($hashCtx, false);
        $manifest['shards'][count($manifest['shards']) - 1]['sha256'] = $cksum;
        $manifest['total_lines'] = $written;

        // Write manifest
        $manifestFile = $this->dataDir . basename($base) . '.manifest.json';
        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $bar->finish();
        $io->newLine(2);
        $io->success(sprintf('Exported %d records to %s* (manifest: %s)', $written, $base, basename($manifestFile)));

        return Command::SUCCESS;
    }

    private function iterate(int $batchSize, int $startId): \Generator
    {
        $left = $startId;
        $qbBase = $this->sourceRepository->createQueryBuilder('s')
            ->orderBy('s.id', 'ASC')
            ->setMaxResults($batchSize);

        do {
            $qb = clone $qbBase;
            $qb->andWhere('s.id > :left')->setParameter('left', $left);
            $last = null;

            foreach ($qb->getQuery()->toIterable() as $row) {
                yield $last = $row;
            }
            if ($last) {
                $left = $last->getId();
            }
        } while ($last !== null);
    }
}
