<?php

namespace App\Controller;

use App\Entity\Source;
use App\Entity\Target;
use App\Message\TranslateTarget;
use App\Repository\SourceRepository;
use App\Repository\TargetRepository;
use App\Service\BingTranslatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
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
        private MessageBusInterface $bus,

    )
    {

    }

    #[Route('/queue-translation/{engine}/{from}', name: 'api_queue_translation', methods: ['GET', 'POST'])]
    public function dispatch(
        string                     $engine,
        string                     $from,
        #[MapQueryParameter] array $to,
        #[MapQueryParameter] array $text = [],
    ): JsonResponse
    {

        assert(count($to) > 0);
        foreach ($text as $string) {
            // dispatch? Or just add to source?
            $key = Source::calcHash($string, $from);
            if (!$source = $this->sourceRepository->find($key)) {
                $source = new Source($string, $from);
                $this->entityManager->persist($source);
            }
            // check source for existing translations?
            $toTranslate[] = $source;
        }
        $this->entityManager->flush();

        $engine = 'libre';
        $sources = [];
        foreach ($toTranslate as $source) {
            foreach ($to as $targetLocale) {
                // skip same languages
                if ($targetLocale === $source->getLocale()) {
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
                if ($target->getMarking() === $target::PLACE_UNTRANSLATED) {
                    // @dispatch
                    $this->bus->dispatch(new TranslateTarget($target->getKey()));
                }
            }
        }

        $data = $this->normalizer->normalize($toTranslate, 'array', ['groups' => ['source.read']]);
        return $this->json($data);
    }
}
