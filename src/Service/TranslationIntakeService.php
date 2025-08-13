<?php

namespace App\Service;

use App\Entity\Source;
use App\Entity\Target;
use App\Message\TranslateTarget;
use App\Repository\SourceRepository;
use App\Repository\TargetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Survos\LibreTranslateBundle\Dto\TranslationPayload;
use Survos\LibreTranslateBundle\Service\TranslationClientService;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class TranslationIntakeService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SourceRepository $sourceRepo,
        private TargetRepository $targetRepo,
        private MessageBusInterface $bus,
        private NormalizerInterface $normalizer,
        private LoggerInterface $logger,
    ) {}

    /**
     * Process a payload: ensure Sources/Targets exist, and queue translations.
     * Returns a normalized response payload (array) ready for JSON.
     */
    public function handle(TranslationPayload $payload): array
    {
        // 0) Validate basic inputs
        $from = trim($payload->from);
        $engine = $payload->engine; // already validated by DTO
        $toLocales = array_values(array_unique(array_filter(array_map('trim', (array)$payload->to))));
        $rawTexts  = array_map('trim', (array)$payload->text);

        if ($from === '' || empty($toLocales) || empty($rawTexts)) {
            return [
                'queued'   => 0,
                'sources'  => [],
                'missing'  => $rawTexts ? array_values($rawTexts) : [],
                'error'    => 'Invalid payload: from/to/text required.',
            ];
        }

        // 1) Normalize & de-duplicate strings -> hashes
        $byHash = [];  // hash => string
        foreach ($rawTexts as $s) {
            if ($s === '') continue;
            // Example rule: skip pure numbers
            if (preg_match('/^\d+$/', $s)) { continue; }

            $h = TranslationClientService::calcHash($s, $from);
            $byHash[$h] ??= $s;
        }
        $hashes = array_keys($byHash);
        if (!$hashes) {
            return ['queued' => 0, 'sources' => [], 'missing' => []];
        }

        $this->em->beginTransaction();
        try {
            // 2) Fetch existing sources in one go
            $existingSources = $this->sourceRepo->findBy(['hash' => $hashes]);
            $sourceByHash = [];
            foreach ($existingSources as $src) {
                $sourceByHash[$src->getHash()] = $src;
            }

            // 3) Create missing sources if allowed
            $missingHashes = array_values(array_diff($hashes, array_keys($sourceByHash)));
            if ($payload->insertNewStrings && $missingHashes) {
                foreach ($missingHashes as $h) {
                    $text = $byHash[$h];
                    $src = new Source($text, $from); // assumes ctor sets hash(text, from)
                    if ($src->getHash() !== $h) {
                        $this->logger->warning("Hash mismatch for '{$text}': expected $h, got ".$src->getHash());
                        // keep going; use entity’s own hash moving forward
                    }
                    $this->em->persist($src);
                    $sourceByHash[$h] = $src;
                }
            }

            $this->em->flush();

            // 4) Work out desired Target keys
            $desiredKeys = [];
            foreach ($toLocales as $loc) {
                if ($loc === $from) continue;
                foreach ($sourceByHash as $src) {
                    // Skip if already translated unless we force dispatch
                    if (!$payload->forceDispatch && array_key_exists($loc, $src->getTranslations())) {
                        continue;
                    }
                    $desiredKeys[] = Target::calcKey($src, $loc, $engine);
                }
            }
            $desiredKeys = array_values(array_unique($desiredKeys));

            // 5) Bulk fetch existing targets
            $existingTargets = $desiredKeys
                ? $this->targetRepo->createQueryBuilder('t')
                    ->where('t.key IN (:keys)')
                    ->setParameter('keys', $desiredKeys)
                    ->getQuery()->getResult()
                : [];

            $existingByKey = [];
            foreach ($existingTargets as $t) {
                $existingByKey[$t->getKey()] = $t;
            }

            // 6) Create missing targets
            $toDispatch = []; // keys to dispatch
            foreach ($toLocales as $loc) {
                if ($loc === $from) continue;
                foreach ($sourceByHash as $src) {
                    if (!$payload->forceDispatch && array_key_exists($loc, $src->getTranslations())) {
                        continue;
                    }
                    $key = Target::calcKey($src, $loc, $engine);
                    $target = $existingByKey[$key] ?? null;
                    if (!$target) {
                        $target = new Target($src, $loc, $engine);
                        $this->em->persist($target);
                        $existingByKey[$key] = $target;
                    }
                    // Queue only if not already translated OR if forcing
                    if ($payload->forceDispatch || $target->getMarking() !== Target::PLACE_TRANSLATED) {
                        $toDispatch[] = $key;
                    }
                }
            }

            $this->em->flush();

            // 7) Dispatch messages
            $stamps = [];
            if ($payload->transport) {
                // supports a single transport name
                $stamps[] = new TransportNamesStamp($payload->transport);
            }
            foreach ($toDispatch as $key) {
                $this->bus->dispatch(new TranslateTarget($key), $stamps);
            }

            $this->em->commit();

            // 8) Response
            $normalized = $this->normalizer->normalize(
                array_values($sourceByHash),
                'array',
                ['groups' => ['source.read']]
            );

            // If we didn't insert, tell the caller which weren’t found
            $missingOut = $payload->insertNewStrings
                ? []
                : array_values(array_map(fn($h) => $byHash[$h], $missingHashes));

            return [
                'queued'  => count($toDispatch),
                'sources' => $normalized,
                'missing' => $missingOut,
            ];

        } catch (\Throwable $e) {
            $this->em->rollback();
            $this->logger->error('[translation intake] ' . $e->getMessage(), ['exception' => $e]);
            return [
                'queued'  => 0,
                'sources' => [],
                'missing' => [],
                'error'   => 'Failed to process payload.',
            ];
        }
    }
}
