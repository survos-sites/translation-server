<?php
declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:volume', 'Show character volume per source/target locale, to gauge paid-engine translation cost.')]
final class LinguaVolumeCommand
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->title('Lingua Character Volume');

        $io->section('Source strings (content available to translate)');
        $sourceRows = $this->connection->fetchAllAssociative(
            'SELECT locale,
                    COUNT(*) AS n,
                    SUM(LENGTH(text)) AS chars
               FROM source
              GROUP BY locale
              ORDER BY chars DESC'
        );

        $totalSourceChars = 0;
        $rows = [];
        foreach ($sourceRows as $r) {
            $chars = (int) $r['chars'];
            $totalSourceChars += $chars;
            $rows[] = [
                $r['locale'],
                number_format((int) $r['n']),
                number_format($chars),
                number_format($chars / 1_000_000, 2),
            ];
        }
        $io->table(['Source locale', 'Strings', 'Characters', 'M chars'], $rows);

        $io->section('Targets: translated vs. pending, per target locale + engine');
        $targetRows = $this->connection->fetchAllAssociative(
            "SELECT t.target_locale,
                    t.engine,
                    COUNT(*) FILTER (WHERE t.target_text IS NOT NULL AND t.target_text <> '') AS translated_n,
                    COALESCE(SUM(LENGTH(t.target_text)) FILTER (WHERE t.target_text IS NOT NULL AND t.target_text <> ''), 0) AS translated_chars,
                    COUNT(*) FILTER (WHERE t.target_text IS NULL OR t.target_text = '') AS pending_n,
                    COALESCE(SUM(LENGTH(s.text)) FILTER (WHERE t.target_text IS NULL OR t.target_text = ''), 0) AS pending_chars
               FROM target t
               JOIN source s ON s.id = t.source_id
              GROUP BY t.target_locale, t.engine
              ORDER BY t.target_locale, translated_chars DESC"
        );

        $totalTranslatedChars = 0;
        $totalPendingChars = 0;
        $rows = [];
        foreach ($targetRows as $r) {
            $translatedChars = (int) $r['translated_chars'];
            $pendingChars = (int) $r['pending_chars'];
            $totalTranslatedChars += $translatedChars;
            $totalPendingChars += $pendingChars;
            $rows[] = [
                $r['target_locale'],
                $r['engine'],
                number_format((int) $r['translated_n']),
                number_format($translatedChars / 1_000_000, 2),
                number_format((int) $r['pending_n']),
                number_format($pendingChars / 1_000_000, 2),
            ];
        }
        $io->table(['Target locale', 'Engine', 'Translated', 'Translated M chars', 'Pending', 'Pending M chars'], $rows);

        $io->section('Totals');
        $io->table(['Metric', 'M chars'], [
            ['Source content', number_format($totalSourceChars / 1_000_000, 2)],
            ['Already translated', number_format($totalTranslatedChars / 1_000_000, 2)],
            ['Pending (would need translating)', number_format($totalPendingChars / 1_000_000, 2)],
        ]);

        $io->success('Done.');
        return Command::SUCCESS;
    }
}
