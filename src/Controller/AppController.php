<?php

namespace App\Controller;

use App\Entity\Source;
use App\Entity\Target;
use App\Form\TranslationPayloadFormType;
use App\Repository\SourceRepository;
use App\Repository\TargetRepository;
use App\Service\BingTranslatorService;
use Doctrine\ORM\EntityManagerInterface;
use Survos\CoreBundle\Service\SurvosUtils;
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
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use Vanderlee\Sentence\Sentence;

final class AppController extends AbstractController
{

    public function __construct(
        private BingTranslatorService  $bingTranslatorService,
        private SourceRepository       $sourceRepository,
        private TargetRepository       $targetRepository,
        private EntityManagerInterface $entityManager,
        private ChartBuilderInterface $chartBuilder,
        #[Autowire('%kernel.enabled_locales%')] private array $enabledLocales,
        private ?LibreTranslateService        $libreTranslate = null,
    )
    {

    }

    #[Route('/{locale}/test-api', name: 'app_test_api')]
    public function testApi(
        ApiController                                    $apiController,
        Request                                          $request,
        #[Autowire('%env(TRANSLATOR_ENDPOINT)%')] string $translationServer,
        #[MapQueryParameter] bool                        $force = false,
        string $locale='en'

    ): Response
    {
        $examples = [
            'en' => ['good morning', 'hello, world'],
            'es' => ['hola','taza'],
        ];
        $enabled = array_filter($this->enabledLocales, fn(string $x) => $x <> $locale);
        $payload = new TranslationPayload($locale, 'libre', to: $enabled, text: $examples[$locale]??[]);
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
            $response = json_decode($apiController->dispatch($payload)->getContent(), true);
//            dd($response, $payload, $force);
        }

        return $this->render("app/test-api.html.twig", [
            'translationServer' => $translationServer,
            'payload' => $payload,
            'response' => $response??null,
            'form' => $form->createView()
        ]);
    }

    private function createChart(array $labels, array $data): Chart
    {
        if (!array_is_list($data)) {
            $labels = array_keys($data);
            $pieData = array_values($data);
        } else {
            $pieData = array_map(fn($marking) => $data[$marking]??0, array_keys($colors));
        }
        $colors = [
            Target::PLACE_TRANSLATED => 'green',
            Target::PLACE_IDENTICAL => 'red',
            Target::PLACE_UNTRANSLATED => 'yellow',
            'total' => 'orange',
        ];
        $chart = $this->chartBuilder->createChart(Chart::TYPE_PIE);
        $pieColors = array_map(fn($marking) => $colors[$marking], $labels);
//        SurvosUtils::assertKeyExists('total', $data);
//        $data['total'] && dd($colors, $pieColors, $pieData, $data);
        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'legend' => [
                        'display' => false,
                    ],
                    'backgroundColor' => $pieColors,
//                    'label' => 'My First dataset',
//                    'backgroundColor' => 'rgb(255, 99, 132)',
//                    'borderColor' => 'rgb(255, 99, 132)',
                    'data' => $pieData,
                ],
            ],
        ]);
        $chart->setOptions([
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ]
        ]);
//        dd($data, $labels);

        return $chart;

    }
    #[Route('/', name: 'app_app')]
    public function index(
        #[MapQueryParameter] ?string $q = null,
    ): Response
    {
        $markingCounts = $this->targetRepository->getCounts('marking');
        $sourceCounts = $this->sourceRepository->getCounts('locale');
        $localeCounts = $this->targetRepository->getCounts('targetLocale');
        foreach ([Source::class, Target::class] as $class) {
            $counts[$class] = [
                'count' => $this->entityManager->getRepository($class)->count([]),
            ];
        }
        $markingCounts = array_fill_keys(array_merge(['total'], Target::PLACES), 0);
//        $markingCounts['total'] = 0;
        $sourceLocaleCounts = array_fill_keys($this->enabledLocales, 0);

        $results = $this->targetRepository->createQueryBuilder('t')
            ->join('t.source', 's')
            ->groupBy('t.targetLocale', 't.marking','s.locale')
            ->select(["t.targetLocale, t.marking, s.locale, count(t) as count"])
            ->getQuery()
            ->getArrayResult();

        $markingChart = $this->createChart(Target::PLACES, $markingCounts );
        $targetCounts = array_fill_keys($this->enabledLocales, $markingCounts);
        $s = array_fill_keys($this->enabledLocales, $targetCounts);
        foreach ($results as $result) {
            $markingCounts[$result['marking']]+= $result['count'];
            $s[$result['locale']][$result['targetLocale']]['total'] += $result['count'];
            $s[$result['locale']][$result['targetLocale']][$result['marking']]+= $result['count'];
//            dd($result, $s);
        }
        $globalCharts['marking'] = [
            'data' => $markingCounts,
            'chart' => $this->createChart([], $markingCounts)
            ];
        foreach ($s as $sourceLocale => $targets) {
            foreach ($targets as $targetLocale => $data) {
                if ($total = $data['total']) {
                    unset($data['total']);
                    $charts[$sourceLocale][$targetLocale] = $this->createChart(
                        array_keys($data),
                        $data,
                    );
                }
            }
        }
//        dd(s: $s, results: $results, sourceCounts: $sourceCounts, localeCounts: $localeCounts);

        $stringsToTranslate = ['Good morning', 'good afternoon', 'Good night', 'hello'];
        $body = [];
        foreach ($stringsToTranslate as $stringToTranslate) {
            $body[] = ['Text' => $stringToTranslate];
        }
        $recent = $this->entityManager->getRepository(Source::class)->findBy([], ['id' => 'DESC'], 4);
        return $this->render('app/index.html.twig', [
            'counts' => $counts,
            'grid' => $s,
            'results' => $results,
            'recent' => $recent,
            'charts' => $charts,
            'globalCharts' => $globalCharts,

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
