<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Source;
use App\Entity\Target;
use App\Message\TranslateStrTr;
use App\Repository\SourceRepository;
use App\Repository\TargetRepository;
use App\Workflow\TargetWorkflowInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Survos\LinguaBundle\Dto\BatchRequest;
use Survos\LinguaBundle\Util\HashUtil;
use Survos\LinguaBundle\Workflow\StrTrWorkflowInterface;
use Survos\StateBundle\Message\TransitionMessage;
use Survos\StateBundle\Service\AsyncQueueLocator;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Workflow\WorkflowInterface;

final class TranslationIntakeService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SourceRepository       $sourceRepository,
        private TargetRepository       $targetRepository,
        private MessageBusInterface    $bus,
        private NormalizerInterface    $normalizer,
        private LoggerInterface        $logger,
        private AsyncQueueLocator $asyncQueueLocator,
    ) {}

    /** Process incoming texts: ensure Str/StrTr exist and queue translations as needed. */
    public function handle(BatchRequest $payload): array
    {
        $from      = trim($payload->source);
        $engine    = $payload->engine ?? 'libre'; // centralize default later
        $toLocales = array_values(array_unique(array_filter(array_map('trim', (array) $payload->target))));
        $rawTexts  = array_map('trim', $payload->texts);

        if ($from === '' || empty($toLocales) || empty($rawTexts)) {
            return [
                'queued'  => 0,
                'items'   => [],
                'missing' => $rawTexts ? array_values($rawTexts) : [],
                'error'   => 'Invalid payload: from/to/text required.',
            ];
        }

        // 1) normalize/dedupe → hash
        $byHash = []; // hash => string
        foreach ($rawTexts as $s) {
            if ($s === '') { continue; }
            if (preg_match('/^\d+$/', $s)) { continue; } // sample rule: skip pure numbers
            $h = HashUtil::calcSourceKey($s, $from);
            if (!isset($byHash[$h])) {
                $byHash[$h] = $s;
            }
        }

        $hashes = array_keys($byHash);
        if (!$hashes) {
            return ['queued' => 0, 'items' => [], 'missing' => []];
        }

        // 2) fetch existing Source by hash
        /** @var Source[] $existingSources */
        $existingSources = $this->sourceRepository->findBy(['hash' => $hashes]);
        $strByHash = [];
        foreach ($existingSources as $source) {
            $hash = $source->hash;
            $strByHash[$hash] = $source;
        }

        // 3) create missing Source if allowed
        $missingHashes = array_values(array_diff($hashes, array_keys($strByHash)));
        if ($payload->insertNewStrings && $missingHashes) {
            foreach ($missingHashes as $h) {
                $text = $byHash[$h];
                $str  = new Source(
                    text: $text,
                    locale: $from,
                    hash: $h
                );
                if ($str->hash !== $h) {
                    $this->logger->warning("Hash mismatch for '{$text}': expected $h, got {$str->hash}");
                }
                $this->em->persist($str);
                $strByHash[$h] = $str;
            }
        }
        $this->em->flush();

        // 4) build desired StrTr keys for ALL (from != to) combos
        $desiredKeys = [];
        foreach ($toLocales as $loc) {
            if ($loc === $from) { continue; }
            foreach ($strByHash as $str) {
                $desiredKeys[] = HashUtil::calcTranslationKey($str->hash, $loc);
            }
        }
        $desiredKeys = array_values(array_unique($desiredKeys));

        // 5) bulk fetch existing Target (StrTr) by hash
        /** @var Target[] $existingTrs */
        $existingTrs = $desiredKeys
            ? $this->targetRepository->findBy(['key' => $desiredKeys])
            : [];
        $trByKey = [];
        foreach ($existingTrs as $tr) {
            $trByKey[$tr->key] = $tr;
        }

        // 6) create missing Target, decide dispatch
        // Use a SET to avoid duplicate keys: key => true
        $toDispatch = [];
        $newTranslationKeys = [];

        foreach ($toLocales as $loc) {
            if ($loc === $from) { continue; }

            foreach ($strByHash as $str) {
                $key = HashUtil::calcTranslationKey($str->hash, $loc);

                $tr = $trByKey[$key] ?? null;
                if (!$tr) {
                    // create a new Target row for this source/locale/engine
                    $tr = new Target(
                        $str,
                        $loc,
                        $engine
                    );
                    $this->em->persist($tr);
                    $trByKey[$key] = $tr;
                    $newTranslationKeys[] = $tr->key;

                    // brand-new targets always need work
                    $toDispatch[$key] = true;
                    continue;
                }

                // Decide (re)dispatch policy
                if ($payload->forceDispatch
                    || $tr->getMarking() !== StrTrWorkflowInterface::PLACE_TRANSLATED
                ) {
                    $toDispatch[$key] = true;
                }
            }
        }

        $this->em->flush();

        // 7) dispatch messages (one per key)
        $stamps = [];
        if ($payload->transport) {
            $stamps[] = new TransportNamesStamp($payload->transport);
        }
//        dd($trByKey);
//        $trByKey[$key] = $tr;
//        foreach (array_keys($toDispatch) as $key) {
//            // locale is encoded in key; worker can parse it, or you can decode here if preferred
//            $this->bus->dispatch(new TranslateStrTr($key, $loc), $stamps);
//        }

        // 8) normalize response
            $normalized = $this->normalizer->normalize(
            array_values($strByHash),
            'array',
            ['groups' => ['source.read']]
        );
//        dd($strByHash, $normalized);

        $missingOut = $payload->insertNewStrings
            ? []
            : array_values(array_map(static fn($h) => $byHash[$h], $missingHashes));

//        foreach (array_keys($toDispatch) as $targetKey) {
        foreach ($newTranslationKeys as $targetKey) {
            $msg = new TransitionMessage($targetKey, Target::class,
                TargetWorkflowInterface::TRANSITION_TRANSLATE,
                TargetWorkflowInterface::WORKFLOW_NAME
            );
            $stamps = $this->asyncQueueLocator->stamps($msg);
            $envelope = $this->bus->dispatch($msg, $stamps);
        }
//        dd($newTranslationKeys);

        return [
            'queued'  => \count($newTranslationKeys),
            'items'   => $normalized,
            'missing' => $missingOut,
        ];
    }
}
