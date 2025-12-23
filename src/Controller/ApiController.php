<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Source;
use App\Entity\Target;
use App\Repository\SourceRepository;
use App\Repository\TargetRepository;
use App\Service\TranslationIntakeService;
use App\Workflow\TargetWorkflowInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Survos\Lingua\Contracts\Dto\BatchRequest;
use Survos\Lingua\Contracts\Dto\BatchResponse;
use Survos\LinguaBundle\Service\LinguaClient;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class ApiController extends AbstractController
{
    public function __construct(
        private readonly SourceRepository       $sourceRepository,
        private readonly TargetRepository       $targetRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly NormalizerInterface    $normalizer,
        private readonly LoggerInterface        $logger,
        private readonly TranslationIntakeService $intake,
    ) {}

    /**
     * Debug endpoint used during development to inspect Sources/Targets.
     * Keeps the same route, but removes dd() and returns stable output.
     */
    #[Route('/get-translations', name: 'api_get_translations', methods: ['GET'])]
    #[Template('app/translations.html.twig')]
    public function getTranslations(
        #[MapQueryParameter] ?string $keys = null,   // comma-delimited
        #[MapQueryParameter] ?array $hashes = null,  // array
    ): JsonResponse|array {
        if ($keys) {
            $hashes = array_values(array_filter(array_map('trim', explode(',', $keys))));
        }

        $hashes ??= [];
        if ($hashes === []) {
            return ['sources' => [], 'keys' => $keys, 'hashes' => []];
        }

        $sources = $this->sourceRepository->findBy(['hash' => $hashes]);

        // Let Twig template render if you use it; keep structure stable.
        return ['sources' => $sources, 'keys' => $keys, 'hashes' => $hashes];
    }

    /**
     * Lingua "pull" endpoint for babel-style hash lookups.
     *
     * Client sends *source hashes*; server returns translations keyed by those source hashes:
     *   { "<sourceHash>": "<translatedText>", ... }
     */
    #[Route('/babel/pull', name: 'lingua_babel_pull', methods: ['POST', 'GET'])]
    public function pullBabel(
        Request $request,
        EntityManagerInterface $em,
        #[MapQueryParameter] ?string $locale = null,
        #[MapQueryParameter] ?string $engine = null,
    ): JsonResponse {
        try {
            $payload = $request->toArray();
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Invalid JSON body.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $hashes = $payload['hashes'] ?? [];
        if (!is_array($hashes) || $hashes === []) {
            return new JsonResponse(['error' => 'Missing hashes[].'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $hashes = array_values(array_unique(array_filter(array_map('strval', $hashes))));
        if ($hashes === []) {
            return new JsonResponse(['error' => 'No valid hashes.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // IMPORTANT: hashes are Source.hash, not Target.key.
        // We join t.source and filter by s.hash, then return map keyed by s.hash.
        $qb = $em->createQueryBuilder()
            ->select('s.hash AS hash, t.targetText AS text')
            ->from(Target::class, 't')
            ->join('t.source', 's')
            ->andWhere('s.hash IN (:hashes)')
            ->andWhere('t.marking IN (:markings)')
            ->setParameter('hashes', $hashes)
            ->setParameter('markings', [TargetWorkflowInterface::PLACE_TRANSLATED]);

        if ($locale) {
            $qb->andWhere('t.targetLocale = :locale')
                ->setParameter('locale', $locale);
        }
        if ($engine) {
            $qb->andWhere('t.engine = :engine')
                ->setParameter('engine', $engine);
        }

        $rows = $qb->getQuery()->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $h = (string) ($row['hash'] ?? '');
            if ($h === '') {
                continue;
            }
            $text = $row['text'];
            $map[$h] = is_string($text) ? $text : (string) $text;
        }

        return new JsonResponse($map);
    }


    /**
     * Main Lingua intake endpoint (batch translate).
     *
     * Returns:
     *   { "status": "ok", "response": { queued, items, missing, ... } }
     */
    #[Route(LinguaClient::ROUTE_BATCH, name: 'api_queue_translation', methods: ['POST'])]
    public function batchRequest(
        #[MapRequestPayload] ?BatchRequest $payload = null,
    ): JsonResponse {
        if ($payload === null) {
            return $this->json(['status' => 'error', 'error' => 'Invalid or missing JSON body.'], 400);
        }

        $result = $this->intake->handle($payload);

        // Avoid noisy pretty-printed logs at warning level; keep one concise info line.
        $this->logger->info('Lingua batch handled', [
            'queued'  => $result['queued'] ?? null,
            'missing' => is_array($result['missing'] ?? null) ? count($result['missing']) : null,
            'items'   => is_array($result['items'] ?? null) ? count($result['items']) : null,
        ]);

        return $this->json(['status' => 'ok', 'response' => $result]);
    }

    /**
     * Legacy stub kept for now to avoid breaking any internal callers that might still
     * reference it indirectly. Not routed (private).
     *
     * IMPORTANT: contracts BatchRequest does NOT include enqueue/force fields.
     */
    private function receiveBatchRequest(Request $request, BatchRequest $payload): JsonResponse
    {
        $jobId = 'job_' . substr(hash('xxh3', json_encode([
            'source' => $payload->source,
            'target' => $payload->target,
            'count'  => count($payload->texts),
            'engine' => $payload->engine,
        ], JSON_THROW_ON_ERROR)), 0, 10);

        $this->logger->info('Lingua receiveBatchRequest (legacy helper)', [
            'texts'  => count($payload->texts),
            'source' => $payload->source,
            'target' => $payload->target, // array is fine in PSR-3 context
            'engine' => $payload->engine,
        ]);

        return $this->json(new BatchResponse(status: 'ok', queued: 0, items: [], missing: [], jobId: $jobId));
    }
}
