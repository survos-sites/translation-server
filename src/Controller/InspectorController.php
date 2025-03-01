<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function PHPUnit\Framework\stringContains;

final class InspectorController extends AbstractController
{

    public function __construct(
        private HttpClientInterface $httpClient,
    )
    {
    }

    private function get(string $url, array $params = [], string $method = 'GET')
    {
        $key = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIyIiwianRpIjoiOGYzMjQ5NWRjYjk5OGQzMTkzMWU0OWUyNzVhYjI1MjE0MzI5ZjZiMDA0YTQ1NTAzMWZkZWUzNTNiYzlkYTdiY2EzZDU5NmQzMGYxODQzM2EiLCJpYXQiOjE3Mzg3NjkxNjEuNzg3NTAyLCJuYmYiOjE3Mzg3NjkxNjEuNzg3NTA1LCJleHAiOjQ4OTQ0NDI3NjEuNzc1Nzg5LCJzdWIiOiI4NjgyIiwic2NvcGVzIjpbXX0.oSQ6bCLSQwDojBWOQfSv1VDfTlvOmguTx-gxoFcN5qgayfuzGSu7ruxSZvT1FDbRpEVJhU5gg3hvUB70gaLNg2LXmsh2RwtWAsboqaKal5HbFqLWBOex8S7K6fm_RI6yU-Vak6CUQOL9c4fPpVd2ba2Jh_cBHjjH8Pt9nSSY-_F8cI2Bmp4u3c7mslmPbjLFKoKfjPU6K2lkxNVIs4GhvitmTZ9ovw-_JGpnPWzRFKW9WwJaEQMYgiBzTAxp2dyqvkzhYVdSOA550aPkDC3kUDk7BUQVIpRIjLd15ihniErjcXBQi3LsMTuONz3Pkf7Aampg-nlc0uMcsvAiuCsDIR7JNPL5vuaCeogYXu8D4BKtrQxWyFV4TB3-4LLDZROFnyt9eYECwpWpYLI0V5zCam4kV-0pXZtOpCI74yqfISfbV-aErI6q382frIOAYA73HFo01uP1sFKvAWFu7WtzFTC_HEaG2gBzr6BsghQfq378cj-MNF9rl4XhpD7uPJJe8A_4y_544PY0loIYV0kVUC1sUTLaAYsaYuiL36EIELyL-jCo8VnEaizVLBfHb0q4S7BlhymqeiJi3-sGWjE3K1LVrlwneaaK9o6Q7W3ojKErZMMXPXq9TX5e91mdLzy-LHpB4kdqiU-SVSQKV29YFhEpVLtQaVHsh6wu64PlHUw';
//        $url = 'https://app.inspector.dev/api/platforms';
        $request = $this->httpClient->request($method, $url, [
            'body' => $params,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer $key"
            ]
        ]);
        if ($request->getStatusCode() !== Response::HTTP_OK) {
            throw $this->createAccessDeniedException();
        }
        $response = json_decode($request->getContent());

        return $response;


    }

    #[Route('/inspector', name: 'app_inspector')]
    public function index(): Response
    {
//        $apps = $this->get(url: 'https://app.inspector.dev/api/apps'); dump($apps);

        $symfonyPlatformId = 6;
        // https://docs.inspector.dev/rest-api/application#create-app
        $create = $this->get('https://app.inspector.dev/api/apps',
            [
                'name' => 'MyTestAppViaAPI',
                'platform_id' => $symfonyPlatformId,
            ], 'POST');
//        dd($create);

        $apps = $this->get(url: 'https://app.inspector.dev/api/apps');

        foreach ($apps as $app) {
            if (preg_match('/test/i', $app->name)) {
                $x = $this->get('https://app.inspector.dev/api/apps/' . $app->id, method: 'DELETE');
                dump($x);
            }
        }
        $apps = $this->get(url: 'https://app.inspector.dev/api/apps');
        $platforms = $this->get(url: 'https://app.inspector.dev/api/platforms');
        return $this->render('inspector/index.html.twig', [
            'platforms' => $platforms,
            'apps' => $apps,
        ]);
    }
}
