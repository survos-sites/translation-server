<?php

namespace App\Controller;

use App\Service\BingTranslatorService;
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
        private BingTranslatorService $bingTranslatorService,
        private LibreTranslate $libreTranslate,
        private HttpClientInterface $httpClient, private string $token)
    {

    }
    #[Route('/', name: 'app_app')]
    public function index(
        #[MapQueryParameter] ?string $q = null,
    ): Response
    {
        $body = [];
        foreach (['Good morning', 'Good night', 'hello'] as $stringToTranslate) {
            $body[] = ['Text' => $stringToTranslate];
        }
        $from = 'en';
        $to = ['es', 'fr', 'de'];

        // see https://github.com/vanderlee/php-sentence for longer text
//        $sentenceService	= new Sentence();

        foreach ($body as $string) {
            foreach ($to as $t) {
                $libre = $this->libreTranslate->translate($string, $from, $t);
                dd($libre);
            }
        }
        $bing = $this->bingTranslatorService->translate($body, $from, $to); dd($bing);
        return $this->render('app/index.html.twig', [
            'data' => $data,
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
