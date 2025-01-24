<?php

namespace App\Controller;

use App\Entity\Source;
use App\Entity\Target;
use App\Message\TranslateTarget;
use App\Repository\SourceRepository;
use App\Repository\TargetRepository;
use App\Service\BingTranslatorService;
use Doctrine\ORM\EntityManagerInterface;
use Survos\LibreTranslateBundle\Dto\TranslationPayload;
use Survos\LibreTranslateBundle\Service\LibreTranslateService;
use Survos\LibreTranslateBundle\Service\TranslationClientService;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class ApiController extends AbstractController
{
    public function __construct(
        private BingTranslatorService  $bingTranslatorService,
        private SourceRepository       $sourceRepository,
        private TargetRepository       $targetRepository,
        private EntityManagerInterface $entityManager,
        private NormalizerInterface    $normalizer,
        private MessageBusInterface    $bus,
        private readonly LibreTranslateService $libreTranslate,

    )
    {

    }


    #[Route('/get-translations', name: 'api_get_translations', methods: ['GET'])]
    #[Template('app/translations.html.twig')]
    public function getTranslations(
        #[MapQueryParameter] ?string $keys=null, // comma-delimited string
        #[MapQueryParameter] ?array $hashes = null // array (so it can be reused)
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

    #[Route('/fetch-translation', name: 'api_fetch_translation', methods: ['POST'])]
    public function fetch(
        Request                                  $request,
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
            $data =  ['keys' => $keys, 'sources' => count($sources)];
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

    #[Route('/translation-payload', name: 'api_translate', methods: ['GET'])]
    public function translatePayload(
        #[MapRequestPayload] TranslationPayload $payload,
    ): JsonResponse
    {
        return $this->json($this->translationService->translate($source, $target, $text));
    }

    #[Route('/queue-translation', name: 'api_queue_translation', methods: ['GET', 'POST'])]
    public function dispatch(
        #[MapRequestPayload] ?TranslationPayload $payload = null,
        #[MapQueryParameter] bool $force = false,
    ): JsonResponse
    {
        $from = $payload->from;
        $to = $payload->to;
        $toTranslate=[];

        foreach ($payload->text as $string) {
            $string = trim($string);
            if (!$string) {
                continue;
            }
            // hmm, don't translate?  Mark as literal?  Or translate as itself?
            if (preg_match('/\d+/', $string)) {
                //
            }
            // dispatch? Or just add to source?
            $key = TranslationClientService::calcHash($string, $from);
            // we could batch this lookup with the keys then persist the new ones
            if (!$source = $this->sourceRepository->findOneBy(['hash' => $key])) {
                $source = new Source($string, $from);
                $this->entityManager->persist($source);
            }
            // check source for existing translations?
            if ($source) {
                $toTranslate[] = $source;
            }
        }
        $this->entityManager->flush();

        $engine = 'libre';
        foreach ($toTranslate as $source) {
            foreach ($to as $targetLocale) {
                // skip same languages
                if ($targetLocale === $source->getLocale()) {
                    continue;
                }

                if (!$force && in_array($targetLocale, $source->getExistingTranslations())) {
                    // it's already in targets, a --force option could re-dispatch the translation request
                    continue;
                }
                $key = Target::calcKey($source, $targetLocale, $engine);

                if (!$target = $this->targetRepository->find($key)) {

//                }
//                if (!$target = $this->targetRepository->findOneBy(
//                    [
//                        'targetLocale' => $targetLocale,
//                        'source' => $source,
//                        'engine' => $engine,
//                    ])) {
                    $target = new Target($source, $targetLocale, $engine);
                    $this->entityManager->persist($target);
                }
                $this->entityManager->flush();
                if ($force || $target->getMarking() === $target::PLACE_UNTRANSLATED) {
                    // @dispatch
                    $envelope = $this->bus->dispatch(new TranslateTarget($target->getKey()));
                }
            }
        }

        $data = $this->normalizer->normalize($toTranslate, 'array', ['groups' => ['source.read']]);
        return $this->json($data);
    }
}
