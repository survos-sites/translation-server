<?php

namespace App\Controller;

use App\Entity\Source;
use App\Entity\Target;
use App\Form\TranslationPayloadFormType;
use App\Repository\SourceRepository;
use App\Repository\TargetRepository;
use App\Service\BingTranslatorService;
use Doctrine\ORM\EntityManagerInterface;
use Survos\LibreTranslateBundle\Dto\TranslationPayload;
use Survos\LibreTranslateBundle\Service\LibreTranslateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Vanderlee\Sentence\Sentence;

final class AppController extends AbstractController
{

    public function __construct(
        private BingTranslatorService  $bingTranslatorService,
        private SourceRepository       $sourceRepository,
        private TargetRepository       $targetRepository,
        private EntityManagerInterface $entityManager,
        private ?LibreTranslateService        $libreTranslate = null,
    )
    {

    }

    #[Route('/test-api', name: 'app_test_api')]
    public function testApi(
        ApiController                                    $apiController,
        Request                                          $request,
        #[Autowire('%env(TRANSLATOR_ENDPOINT)%')] string $translationServer,
        #[MapQueryParameter] bool                        $force = false,

    ): Response
    {
        $payload = new TranslationPayload('en', 'bing', ['es'], ['good morning', 'hello, world']);
        $form = $this->createForm(TranslationPayloadFormType::class, $payload, [
//            'action' => $this->generateUrl('api_queue_translation'),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);
//        dd($form->getData());
        //  && $form->isValid()
        if ($form->isSubmitted()) {
//            $response = $this->forward(ApiController::class . '::dispatch',
//                ['payload' => $form->getData()]);
////            dd($response);
            $response = json_decode($apiController->dispatch($payload, $force)->getContent(), true);
//            dd($response, $payload, $force);
        }

        return $this->render("app/test-api.html.twig", [
            'translationServer' => $translationServer,
            'response' => $response??null,
            'form' => $form->createView()
        ]);
    }

    #[Route('/', name: 'app_app')]
    public function index(
        #[MapQueryParameter] ?string $q = null,
    ): Response
    {
        foreach ([Source::class, Target::class] as $class) {

            $counts[$class] = [
                'count' => $this->entityManager->getRepository($class)->count([]),
            ];
        }
        $stringsToTranslate = ['Good morning', 'good afternoon', 'Good night', 'hello'];
        $body = [];
        foreach ($stringsToTranslate as $stringToTranslate) {
            $body[] = ['Text' => $stringToTranslate];
        }
        $from = 'en';
        $recent = $this->entityManager->getRepository(Source::class)->findBy([], ['id' => 'DESC'], 4);
        return $this->render('app/index.html.twig', [
            'counts' => $counts,
            'recent' => $recent,
            'recentTargets' => $this->entityManager
                ->getRepository(Target::class)->findBy(['source' => $recent], limit: 10),
        ]);

        // see https://github.com/vanderlee/php-sentence for longer text
//        $sentenceService	= new Sentence();

        $toTranslate = [];

        $to = ['es', 'fr', 'de'];
        $engine = 'libre';
        $sources = [];
        foreach ($toTranslate as $source) {
            $sources[] = $source;
            $sourceText = $source->getText();
            foreach (['libre'] as $engine) {
                foreach ($to as $targetLocale) {
                    // check if target exists
                    if (!$target = $this->targetRepository->findOneBy(
                        [
                            'targetLocale' => $targetLocale,
                            'source' => $source,
                            'engine' => $engine,
                        ])) {
                        $target = new Target($source, $targetLocale, $engine);
                        $this->entityManager->persist($target);
                    }
                    // @workflow?
                }
            }
        }
        $this->entityManager->flush();

        return $this->render('app/index.html.twig', [
            'sources' => $sources,
            'body' => $body,
        ]);


        $client = HttpClient::create();
        $request = $this->getRequestFactory()->createRequest('POST', $url, [], $body);

        $request = $request
            ->withHeader('Ocp-Apim-Subscription-Key', $this->key)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-ClientTraceId', $this->createGuid())
            ->withHeader('Content-length', \strlen($body));


        $translator = new Translator();
        assert($apiKey1);
        if ($q) {

            $client = new HttpClient()::create();
            $translator->addTranslatorService(new BingTranslator($apiKey1, $client));

            echo $translator->translate($q, 'en', 'sv'); // "äpple"
        }

    }

    private function getUrl($from, $to)
    {
        return sprintf(
            '?api-version=3.0&to=%s&from=%s',
            $to,
            $from
        );
    }

}
