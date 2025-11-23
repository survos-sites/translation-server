<?php

namespace App\Tests;

use App\Controller\ApiController;
use Survos\LinguaBundle\Dto\BatchRequest;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BatchRequestTest extends KernelTestCase
{
    public function testSomething(): void
    {
        $kernel = self::bootKernel();

        $this->assertSame('test', $kernel->getEnvironment());
        // $routerService = static::getContainer()->get('router');

        /** @var ApiController $apiController */
         $apiController = static::getContainer()->get(ApiController::class);
         $batchRequest = new BatchRequest([
             'hello'
         ],
         source: 'en',
         target: 'es',
         );
         $response = $apiController->batchRequest($batchRequest);
         $this->assertNull($response);
    }
}
