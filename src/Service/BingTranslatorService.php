<?php

// https://azure.microsoft.com/en-us/pricing/details/cognitive-services/translator/

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Cache\CacheItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BingTranslatorService
{
    const ENDPOINT='https://api.cognitive.microsofttranslator.com/translate';

    public function __construct(
        private CacheInterface $scraperCache,
        private HttpClientInterface $httpClient,
        #[Autowire('%env(BING_KEY1)%')] private ?string $apiKey1 = null,
        #[Autowire('%env(BING_LOCATION)%')] private ?string $bingLocation = null,
        private array $headers = [],
    )
    {
        $this->headers = [
            'Ocp-Apim-Subscription-Key' => $apiKey1,
            'Ocp-Apim-Subscription-Region' => $bingLocation,
            'Content-Type' => 'application/json'
        ];
    }

    // all strings must be in the $to language
    public function translate(string|array $q, string $from, string|array $to): array
    {
//        $to ??= $this->to;

        $body = [];

        foreach (['Good morning', 'Good night', 'hello'] as $stringToTranslate) {
            $body[] = ['Text' => $stringToTranslate];
        }

        // limits: 1000 strings, 50000 chars.   https://learn.microsoft.com/en-us/azure/ai-services/translator/service-limits#text-translation
//        $body = json_encode($body);
        $url = self::ENDPOINT . '?' . http_build_query(
                [
                    'from' => $from,
                    'to' => $to,
                    'api-version' => '3.0'
                ]
            );

        $key = hash('xxh3', $url . json_encode($body));
        $data = $this->scraperCache->get($key, function (CacheItem $cacheItem)
        use ($url, $body) {
            $response = $this->httpClient->request('POST', $url, [
                'json' => $body,
                'headers' => $this->headers,
            ]);

            if (($statusCode = $response->getStatusCode()) !== 200) {
                dd($statusCode, $response);
            }
            return $response->toArray();
        });

        return $data;

    }
}
