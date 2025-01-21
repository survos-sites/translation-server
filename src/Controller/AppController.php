<?php

namespace App\Controller;

use App\Entity\Source;
use App\Entity\Target;
use App\Repository\SourceRepository;
use App\Repository\TargetRepository;
use App\Service\BingTranslatorService;
use Doctrine\ORM\EntityManagerInterface;
use Jefs42\LibreTranslate;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpClient;
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
        private ?LibreTranslate        $libreTranslate = null,
    )
    {
        if (null === $this->libreTranslate) {
            $this->libreTranslate = new LibreTranslate();
        }

    }

    #[Route('/', name: 'app_app')]
    public function index(
        #[MapQueryParameter] ?string $q = null,
    ): Response
    {
        $stringsToTranslate = ['Good morning', 'good afternoon', 'Good night', 'hello'];
        $body = [];
        foreach ($stringsToTranslate as $stringToTranslate) {
            $body[] = ['Text' => $stringToTranslate];
        }
        $from = 'en';

        // see https://github.com/vanderlee/php-sentence for longer text
//        $sentenceService	= new Sentence();

        $toTranslate = [];

        $to = ['es', 'fr', 'de'];
        $engine = 'libre';
        $sources = [];
        foreach ($toTranslate as $source) {
            $sources[] = $source;
            $sourceText = $source->getText();
            foreach (['libre', 'bing'] as $engine) {
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

        return $this->render('app/index.html.twig', [
            'controller_name' => 'AppController',
        ]);
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
