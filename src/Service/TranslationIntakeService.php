<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Source;
use App\Entity\Target;
use App\Repository\SourceRepository;
use App\Repository\TargetRepository;
use App\Workflow\TargetWorkflowInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Survos\Lingua\Contracts\Dto\BatchRequest;
use Survos\Lingua\Core\Identity\HashUtil;
use Survos\StateBundle\Message\TransitionMessage;
use Survos\StateBundle\Service\AsyncQueueLocator;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class TranslationIntakeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SourceRepository       $sourceRepository,
        private readonly TargetRepository       $targetRepository,
        private readonly MessageBusInterface    $bus,
        private readonly NormalizerInterface    $normalizer,
        private readonly LoggerInterface        $logger,
        private readonly AsyncQueueLocator      $asyncQueueLocator,
    ) {}

    /**
     * Process incoming texts:
     * - ensure Source rows exist (if allowed)
     * - ensure Target rows exist for (source, targetLocale, engine)
     * - queue translations for new + eligible existing Targets
     *
     * @return array{
     *   queued:int,
     *   items:array<mixed>,
     *   missing:list<string>,
     *   error?:string
     * }
     */
    public function handle(BatchRequest $payload): array
    {
        $fromRaw   = trim((string) $payload->source);
        $engineRaw = trim((string) ($payload->engine ?? 'libre'));

        $toLocalesRaw = array_values(array_filter(array_map(
            static fn($v) => trim((string) $v),
            (array) $payload->target
        )));

        $rawTexts = array_values(array_filter(array_map(
            static fn($v) => trim((string) $v),
            (array) $payload->texts
        )));

        if ($fromRaw === '' || $toLocalesRaw === [] || $rawTexts === []) {
            return [
                'queued'  => 0,
                'items'   => [],
                'missing' => $rawTexts,
                'error'   => 'Invalid payload: source/target/texts required.',
            ];
        }

        // Normalize inputs once
        $from      = HashUtil::normalizeLocale($fromRaw);
        $engine    = HashUtil::normalizeEngine($engineRaw);
        $toLocales = array_values(array_unique(array_map([HashUtil::class, 'normalizeLocale'], $toLocalesRaw)));

        // Remove degenerate targets
        $toLocales = array_values(array_filter($toLocales, static fn(string $l) => $l !== '' && $l !== $from));

        if ($toLocales === []) {
            return [
                'queued'  => 0,
                'items'   => [],
                'missing' => [],
                'error'   => 'No target locales after normalization (or only equals source).',
            ];
        }

        $insertNewStrings = (bool) $payload->insertNewStrings;
        $forceDispatch    = (bool) $payload->forceDispatch;

        $this->logger->info('Lingua intake: start', [
            'source'           => $from,
            'targets'          => $toLocales,
            'engine'           => $engine,
            'texts_in'         => \count($rawTexts),
            'insertNewStrings' => $insertNewStrings,
            'forceDispatch'    => $forceDispatch,
            'transport'        => $payload->transport ?? null,
        ]);

        // 1) Normalize/dedupe texts -> source hashes
        $byHash = []; // hash => original text
        $skipped = 0;

        foreach ($rawTexts as $s) {
            if ($s === '') { continue; }

            // Optional rule: skip pure numbers (keep if you truly want this)
            if (\preg_match('/^\d+$/', $s)) {
                $skipped++;
                continue;
            }

            $h = HashUtil::calcSourceKey($s, $from);
            $byHash[$h] ??= $s;
        }

        $hashes = array_keys($byHash);
        if ($hashes === []) {
            $this->logger->info('Lingua intake: nothing to do after normalization', ['skipped' => $skipped]);
            return ['queued' => 0, 'items' => [], 'missing' => []];
        }

        // 2) Fetch existing Sources by hash
        /** @var Source[] $existingSources */
        $existingSources = $this->sourceRepository->findBy(['hash' => $hashes]);

        $sourceByHash = [];
        foreach ($existingSources as $source) {
            $sourceByHash[(string) $source->hash] = $source;
        }

        // 3) Create missing Sources if allowed
        $missingHashes = array_values(array_diff($hashes, array_keys($sourceByHash)));
        $createdSources = 0;

        if ($missingHashes !== [] && $insertNewStrings) {
            foreach ($missingHashes as $h) {
                $text = $byHash[$h];

                $source = new Source(
                    text: $text,
                    locale: $from,
                    hash: $h
                );

                if ($source->hash !== $h) {
                    $this->logger->warning('Lingua intake: source hash mismatch (should not happen)', [
                        'expected' => $h,
                        'actual'   => $source->hash,
                        'source'   => $from,
                    ]);
                }

                $this->em->persist($source);
                $sourceByHash[$h] = $source;
                $createdSources++;
            }

            $this->em->flush();
        }

        // If we are not allowed to insert new strings, report missing texts back to caller.
        $missingOut = $insertNewStrings
            ? []
            : array_values(array_map(static fn(string $h) => $byHash[$h], $missingHashes));

        $sources = array_values($sourceByHash);
        if ($sources === []) {
            $this->logger->warning('Lingua intake: no sources found/created', [
                'hashes' => \count($hashes),
                'missingHashes' => \count($missingHashes),
            ]);

            return [
                'queued'  => 0,
                'items'   => [],
                'missing' => $missingOut,
            ];
        }

        // 4) Fetch existing Targets by tuple (source, locale, engine)
        /** @var Target[] $existingTargets */
        $existingTargets = $this->targetRepository->findExistingForSourcesAndLocales($sources, $toLocales, $engine);

        $targetByTuple = []; // "sourceId|locale|engine" => Target
        foreach ($existingTargets as $t) {
            $tuple = $t->source->getId().'|'.$t->targetLocale.'|'.$t->engine;
            $targetByTuple[$tuple] = $t;
        }

        // 5) Create missing targets + decide dispatch list
        $toDispatch = []; // targetKey => true
        $createdTargets = 0;
        $eligibleExisting = 0;

        foreach ($toLocales as $loc) {
            foreach ($sources as $source) {
                $tuple = $source->getId().'|'.$loc.'|'.$engine;

                $t = $targetByTuple[$tuple] ?? null;
                if (!$t) {
                    $t = new Target($source, $loc, $engine);
                    $this->em->persist($t);
                    $targetByTuple[$tuple] = $t;

                    $toDispatch[$t->key] = true;
                    $createdTargets++;
                    continue;
                }

                if ($forceDispatch || $t->getMarking() !== TargetWorkflowInterface::PLACE_TRANSLATED) {
                    $toDispatch[$t->key] = true;
                    $eligibleExisting++;
                }
            }
        }

        $this->em->flush();

        // 6) Dispatch messages (new + eligible existing)
        $stamps = [];
        if ($payload->transport) {
            $stamps[] = new TransportNamesStamp($payload->transport);
        }

        $queued = 0;
        foreach (array_keys($toDispatch) as $targetKey) {
            $msg = new TransitionMessage(
                $targetKey,
                Target::class,
                TargetWorkflowInterface::TRANSITION_TRANSLATE,
                TargetWorkflowInterface::WORKFLOW_NAME
            );

            $queueStamps = $this->asyncQueueLocator->stamps($msg);
            $this->bus->dispatch($msg, array_merge($stamps, $queueStamps));
            $queued++;
        }

        // 7) Normalize response (sources)
        $items = $this->normalizer->normalize(
            $sources,
            'array',
            ['groups' => ['source.read']]
        );

        $this->logger->info('Lingua intake: done', [
            'source'            => $from,
            'targets'           => $toLocales,
            'engine'            => $engine,
            'texts_in'          => \count($rawTexts),
            'hashes_unique'     => \count($hashes),
            'skipped'           => $skipped,
            'sources_existing'  => \count($existingSources),
            'sources_created'   => $createdSources,
            'targets_existing'  => \count($existingTargets),
            'targets_created'   => $createdTargets,
            'eligible_existing' => $eligibleExisting,
            'queued'            => $queued,
        ]);

        return [
            'queued'  => $queued,
            'items'   => \is_array($items) ? $items : [],
            'missing' => $missingOut,
        ];
    }
}
