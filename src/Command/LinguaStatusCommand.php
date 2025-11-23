<?php
declare(strict_types=1);

namespace App\Command;

use App\Repository\StrRepository;
use App\Repository\StrTranslationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand('app:status')]
final class LinguaStatusCommand
{
    public function __construct(
        private StrRepository $strRepo,
        private StrTranslationRepository $strTrRepo,
        #[Autowire('%kernel.enabled_locales%')] private array $enabledLocales = [],
    ) {}

    /**
     * Show translation completion by target locale for each source locale.
     */
    public function __invoke(
        SymfonyStyle $io,
        #[\Symfony\Component\Console\Attribute\Option('source')] ?string $source = null,
        #[\Symfony\Component\Console\Attribute\Option('locales')] ?string $locales = null,
        #[\Symfony\Component\Console\Attribute\Option('engine')] ?string $engine = null,
        #[\Symfony\Component\Console\Attribute\Option('all')] ?bool $all = null,
    ): int
    {
        $io->title('Lingua Translation Status');

        $sourceLocales = $source ? [$source] : $this->strRepo->listSourceLocales();
        if (!$sourceLocales) {
            $io->warning('No source locales found.');
            return Command::SUCCESS;
        }

        // determine target locales to display
        $explicitTargets = $locales
            ? array_values(array_filter(array_map('trim', explode(',', $locales))))
            : [];

        foreach ($sourceLocales as $src) {
            $totalStr = $this->strRepo->countBySourceLocale($src);
            $io->section(sprintf('Source %s — %d source strings', $src, $totalStr));

            if ($totalStr === 0) {
                continue;
            }

            if ($explicitTargets) {
                $targets = $explicitTargets;
            } elseif ($all) {
                // Query DB for all target locales seen for this source
                // We use a lightweight native SQL for speed; adjust table/column if needed.
                $conn = $this->strRepo->getEntityManager()->getConnection();
                $rows = $conn->fetchFirstColumn(
                    'SELECT DISTINCT t.locale FROM str_tr tr
                     JOIN str s ON tr.str_hash = s.hash
                     WHERE s.src_locale = :src',
                    ['src' => $src]
                );
                $targets = array_values(array_filter(array_map('strval', $rows)));
            } else {
                $targets = $this->enabledLocales;
            }

            // render table
            $rows = [];
            foreach ($targets as $loc) {
                if ($loc === $src) { continue; }

                $translated = -1; // $this->strTrRepo->countTranslatedForSourceTarget($src, $loc, $engine);
                $pct = $totalStr ? round(($translated / $totalStr) * 100, 1) : 0.0;
                $missing = max(0, $totalStr - $translated);
                $rows[] = [
                    $loc,
                    number_format($translated),
                    number_format($totalStr),
                    $pct.'%',
                    number_format($missing),
                    $engine ?? 'any',
                ];
            }

            $io->table(
                ['Target', 'Translated', 'Total', '% Complete', 'Missing', 'Engine'],
                $rows
            );
        }

        $io->success('Done.');
        return Command::SUCCESS;
    }
}
