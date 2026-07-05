<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Source;
use App\Entity\Target;
use App\Repository\SourceRepository;
use App\Repository\TargetRepository;
use App\Workflow\TargetWorkflowInterface;
use Doctrine\ORM\EntityManagerInterface;
use Survos\Lingua\Core\Identity\HashUtil;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Seeds Source/Target rows from already human-translated, third-party UI string catalogs,
 * so lingua never re-translates (via an MT engine) strings someone already vetted.
 */
final class SeedService
{
    public function __construct(
        private readonly SourceRepository $sourceRepository,
        private readonly TargetRepository $targetRepository,
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%/vendor/easycorp/easyadmin-bundle/translations/')]
        private readonly string $easyAdminTranslationsDir,
    ) {
    }

    #[AsCommand('lingua:seed:easyadmin', 'Seed Source/Target rows from EasyAdminBundle\'s vendored, human-translated locale files')]
    public function seedEasyAdmin(
        SymfonyStyle $io,
        #[Option('Minimum word count for a source string to be seeded; shorter strings are too context-dependent to trust out of context (e.g. "Dashboard")')]
        int $minWords = 2,
        #[Option('Only seed this target locale (e.g. fr); default is all locales EasyAdmin ships')]
        ?string $locale = null,
    ): int {
        $enFile = $this->easyAdminTranslationsDir . 'EasyAdminBundle.en.php';
        if (!is_file($enFile)) {
            $io->error("Missing reference file: $enFile");
            return Command::FAILURE;
        }

        $en = $this->flatten(require $enFile);

        $localeFiles = glob($this->easyAdminTranslationsDir . 'EasyAdminBundle.*.php') ?: [];
        $created = 0;
        $updated = 0;
        $skippedShort = 0;
        $skippedUntranslated = 0;

        // Source lookup is by hash via a DB query, not Doctrine's identity map (unlike Target's
        // assigned-PK lookup), so a same-request cache is needed: many EasyAdmin keys across
        // (and within) locale files share identical English text (e.g. "Yes", "Edit").
        $sourceByHash = [];

        foreach ($localeFiles as $file) {
            if (!preg_match('/EasyAdminBundle\.([a-zA-Z_-]+)\.php$/', $file, $m)) {
                continue;
            }
            $targetLocale = HashUtil::normalizeLocale($m[1]);
            if ($targetLocale === 'en') {
                continue;
            }
            if ($locale !== null && $targetLocale !== HashUtil::normalizeLocale($locale)) {
                continue;
            }

            $translations = $this->flatten(require $file);

            foreach ($translations as $key => $translatedText) {
                $sourceText = $en[$key] ?? null;
                if (!is_string($sourceText) || $sourceText === '' || !is_string($translatedText) || $translatedText === '') {
                    continue;
                }
                if ($translatedText === $sourceText) {
                    // not actually translated in this locale file (fallback/untranslated entry)
                    $skippedUntranslated++;
                    continue;
                }
                if (str_word_count($sourceText) < $minWords) {
                    // too short/context-free to trust reuse outside EasyAdmin's own admin-UI context
                    $skippedShort++;
                    continue;
                }

                $hash = HashUtil::calcSourceKey($sourceText, 'en');
                $source = $sourceByHash[$hash] ??= $this->sourceRepository->findOneBy(['hash' => $hash]);
                if (!$source) {
                    $source = new Source(text: $sourceText, locale: 'en', hash: $hash);
                    $this->em->persist($source);
                    $sourceByHash[$hash] = $source;
                }

                $engine = 'seedEA';
                $targetKey = Target::calcKey($source, $targetLocale, $engine);
                $target = $this->targetRepository->find($targetKey);
                if (!$target) {
                    $target = new Target(source: $source, targetLocale: $targetLocale, engine: $engine);
                    $this->em->persist($target);
                    $created++;
                } else {
                    $updated++;
                }

                $target->targetText = $translatedText;
                $target->setMarking(TargetWorkflowInterface::PLACE_TRANSLATED);
            }

            $this->em->flush();
        }

        $io->success(sprintf(
            'Seeded from EasyAdminBundle: %d created, %d updated, %d skipped (too short), %d skipped (untranslated in that locale).',
            $created,
            $updated,
            $skippedShort,
            $skippedUntranslated,
        ));

        return Command::SUCCESS;
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<string, mixed>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $flatKey = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $out += $this->flatten($value, $flatKey);
            } else {
                $out[$flatKey] = $value;
            }
        }

        return $out;
    }
}
