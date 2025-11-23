<?php

namespace App\Controller;

use App\Entity\Source;
use App\Entity\Target;
use App\Message\TranslateTarget;
use App\Repository\SourceRepository;
use App\Repository\TargetRepository;
use App\Service\BingTranslatorService;
use App\Service\TranslationIntakeService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Survos\LinguaBundle\Dto\BatchRequest;
use Survos\LinguaBundle\Dto\BatchResponse;
use Survos\LinguaBundle\Service\LinguaClient;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class ApiController extends AbstractController
{
    public function __construct(
        private SourceRepository               $sourceRepository,
        private TargetRepository               $targetRepository,
        private EntityManagerInterface         $entityManager,
        private NormalizerInterface            $normalizer,
        private MessageBusInterface            $bus,
        private LoggerInterface $logger,
        private TranslationIntakeService $intake,
) {}


    #[Route('/get-translations', name: 'api_get_translations', methods: ['GET'])]
    #[Template('app/translations.html.twig')]
    public function getTranslations(
        #[MapQueryParameter] ?string $keys = null, // comma-delimited string
        #[MapQueryParameter] ?array  $hashes = null // array (so it can be reused)
    ): JsonResponse|array
    {
        if ($keys) {
            $hashes = explode(',', $keys);
        }

        $sources = $this->sourceRepository->findBy([
            'hash' => $hashes
        ]);
        foreach ($sources as $source) {
            foreach ($source->getTargets() as $target) {
                dd($target);
            }
            dd($source);
        }
        dd($sources, $hashes);
        return ['sources' => $sources, 'keys' => $keys, 'hashes' => $hashes];

    }

//    #[Route('/fetch-translation', name: 'api_fetch_translation', methods: ['POST'])]
//    public function fetch(
//        #[MapRequestPayload] BatchRequest $payload,
//    ): JsonResponse {
//        // Lookup-only: do not insert or queue
//        $payload->insertNewStrings = false;
//        $payload->forceDispatch    = false;
//        $result = $this->intake->handle($payload);
//        // client expects just the normalized sources in this endpoint
//        unset($result['queued']);
//        return $this->json($result);
//    }

    #[Route(LinguaClient::ROUTE_BATCH, name: 'api_queue_translation', methods: ['POST'])]
    public function batchRequest(
        #[MapRequestPayload] ?BatchRequest $payload=null,
    ): JsonResponse {
        // Full flow: create if needed (if allowed), create targets, queue jobs
        $result = $this->intake->handle($payload);
        $response = ['status' => 'ok', 'response' => $result];
        $this->logger->warning(json_encode($result, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES));
        return $this->json($response);
    }
    #[Route('/fetch-translationOLD', name: 'api_fetch_translation', methods: ['POST'])]
    public function OLDfetch(
        #[MapRequestPayload] ?TranslationPayload $payload = null,
    ): JsonResponse
    {
        $from = $payload->from;
        $to = $payload->to;
        $keys = [];
        foreach ($payload->text as $string) {
            $keys[] = TranslationClientService::calcHash($string, $from);
        }
        $keys = array_unique($keys);
//        foreach ($keys as $key) {
//            $source =  $this->sourceRepository->findOneBy(['hash' => $key]);
//            if (!$source) {
//                return $this->json(['invalid' => $key]);
//            }
//            $sources[] = $source;
//        }
        $sources = $this->sourceRepository->findBy([
            'hash' => array_values($keys),
        ]);
        $data = $this->normalizer->normalize($sources, 'array', ['groups' => ['source.read']]);
        if (count($sources) !== count($keys)) {
            $data = ['keys' => $keys, 'sources' => count($sources)];
        }
        return $this->json($data);
    }

//    #[Route('/translate/{source}/{target}', name: 'api_translate', methods: ['GET'])]
//    public function translate(
//        #[MapQueryParameter] string $source,
//        #[MapQueryParameter] string $target,
//        #[MapQueryParameter] string $text,
//    ): JsonResponse
//    {
//        return $this->json($this->translationService->translate($source, $target, $text));
//    }

//    #[Route(path: LinguaClient::ROUTE_BATCH, name: 'lingua_batch', methods: ['POST'])]
    private function receiveBatchRequest(Request $request, #[MapRequestPayload] BatchRequest $payload): JsonResponse
    {
        // Simple header check; for production prefer a Security authenticator.
        if (0)
        if ($this->serverApiKey) {
            $key = $request->headers->get('X-Api-Key');
            if (!$key || !\hash_equals($this->serverApiKey, $key)) {
                return $this->json(['status' => 'forbidden'], 403);
            }
        }

        // TODO: enqueue work (Messenger) and return job id. For now, echo shape.
        $jobId = 'job_'.substr(hash('xxh3', json_encode($payload)), 0, 10);

        $this->logger->info('Lingua intake', [
            'texts' => count($payload->texts),
            'source' => $payload->source,
            'target' => $payload->target,
            'enqueue' => $payload->enqueue,
            'force' => $payload->force,
        ]);

        return $this->json(new BatchResponse(status: $payload->enqueue ? 'queued' : 'ok', items: [], jobId: $jobId));
    }


}
