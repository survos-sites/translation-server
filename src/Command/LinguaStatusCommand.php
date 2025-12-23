<?php
declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand('app:status', 'Show translation completion by target locale for each source locale.')]
final class LinguaStatusCommand
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.enabled_locales%')] private readonly array $enabledLocales = [],
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Filter by a single source locale (e.g. nl)')] ?string $source = null,
        #[Option('Comma-separated target locales to display (e.g. fr,es,en)')] ?string $locales = null,
        #[Option('Filter by engine (e.g. libre)')] ?string $engine = null,
        #[Option('Show all target locales present in Target for each source locale')] bool $all = false,
        #[Option('Include the source locale itself in the output')] bool $includeSource = false,
    ): int {
        $io->title('Lingua Translation Status');

        $sourceLocales = $source
            ? [trim((string) $source)]
            : array_values(array_filter(array_map('strval', $this->connection->fetchFirstColumn(
                'SELECT DISTINCT locale FROM source ORDER BY locale'
            ))));

        if ($sourceLocales === []) {
            $io->warning('No source locales found in the source table.');
            return Command::SUCCESS;
        }

        $explicitTargets = $locales
            ? array_values(array_filter(array_map('trim', explode(',', $locales))))
            : [];

        foreach ($sourceLocales as $src) {
            $totalSource = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM source WHERE locale = :src',
                ['src' => $src]
            );

            $io->section(sprintf('Source %s — %d source strings', $src, $totalSource));

            if ($totalSource === 0) {
                continue;
            }

            $targets = match (true) {
                $explicitTargets !== [] => $explicitTargets,
                $all => array_values(array_filter(array_map('strval', $this->connection->fetchFirstColumn(
                    'SELECT DISTINCT t.target_locale
                       FROM target t
                       JOIN source s ON s.id = t.source_id
                      WHERE s.locale = :src
                      ORDER BY t.target_locale',
                    ['src' => $src]
                )))),
                default => $this->enabledLocales,
            };

            // Be strict: if we have no targets, we cannot infer "everything" safely.
            if ($targets === []) {
                $io->warning('No targets specified and kernel.enabled_locales is empty. Pass --locales=fr,es,... or --all.');
                continue;
            }

            $rows = [];

            foreach ($targets as $loc) {
                $loc = trim((string) $loc);
                if ($loc === '') {
                    continue;
                }
                if (!$includeSource && $loc === $src) {
                    continue;
                }

                $params = ['src' => $src, 'loc' => $loc];
                $engineSql = '';

                if ($engine !== null && $engine !== '') {
                    $engineSql = ' AND t.engine = :engine';
                    $params['engine'] = $engine;
                }

                $translated = (int) $this->connection->fetchOne(
                    'SELECT COUNT(*)
                       FROM target t
                       JOIN source s ON s.id = t.source_id
                      WHERE s.locale = :src
                        AND t.target_locale = :loc
                        AND t.target_text IS NOT NULL
                        AND t.target_text <> \'\''
                    . $engineSql,
                    $params
                );

                $pct = round(($translated / $totalSource) * 100, 1);
                $missing = max(0, $totalSource - $translated);

                $rows[] = [
                    $loc,
                    number_format($translated),
                    number_format($totalSource),
                    $pct . '%',
                    number_format($missing),
                    $engine ?? 'any',
                ];
            }

            $io->table(['Target', 'Translated', 'Total', '% Complete', 'Missing', 'Engine'], $rows);
        }

        $io->success('Done.');
        return Command::SUCCESS;
    }
}
