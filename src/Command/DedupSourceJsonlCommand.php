<?php
declare(strict_types=1);

namespace App\Command;

use Survos\JsonlBundle\IO\JsonlReader;
use Survos\JsonlBundle\IO\JsonlWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand('app:jsonl:dedup', 'Deduplicate source.jsonl by hash before import')]
class DedupSourceJsonlCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/data/')] private string $dataDir,
    ) {
        parent::__construct();
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $input  = $this->dataDir . 'source.jsonl';
        $output = $this->dataDir . 'source.dedup.jsonl';
        $dbFile = $this->dataDir . 'dedup-cache.sqlite';

        $io->title('Deduplicating source.jsonl');

        $reader = JsonlReader::open($input);
        $writer = JsonlWriter::open($output);

        // fast-dedupe using sqlite key table
        $db = new \SQLite3($dbFile);
        $db->exec('CREATE TABLE IF NOT EXISTS hashes (hash TEXT PRIMARY KEY)');

        $count = 0;
        $unique = 0;

        // count lines for progress
        $total = (int) trim(shell_exec("wc -l < " . escapeshellarg($input)));
        $progress = $io->createProgressBar($total);

        foreach ($reader as $row) {
            $progress->advance();
            $count++;

            $hash = $row['hash'];

            // lookup
            $stmt = $db->prepare('SELECT 1 FROM hashes WHERE hash = :h');
            $stmt->bindValue(':h', $hash, SQLITE3_TEXT);
            $exists = $stmt->execute()->fetchArray(SQLITE3_NUM);

            if ($exists) {
                continue;
            }

            // mark as seen
            $insert = $db->prepare('INSERT INTO hashes (hash) VALUES (:h)');
            $insert->bindValue(':h', $hash, SQLITE3_TEXT);
            $insert->execute();

            $writer->write($row);
            $unique++;
        }

        $progress->finish();
        $writer->close();

        $io->success("Dedup complete.");
        $io->writeln("Total rows: $count");
        $io->writeln("Unique rows: $unique");

        return Command::SUCCESS;
    }
}
