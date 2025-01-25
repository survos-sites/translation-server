<?php

namespace App\MessageHandler;

use App\Entity\Target;
use App\Message\TranslateTarget;
use App\Repository\TargetRepository;
use App\Service\BingTranslatorService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Survos\LibreTranslateBundle\Service\LibreTranslateService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TranslateTargetHandler
{
    public function __construct(
        private readonly TargetRepository                   $targetRepository,
        private readonly EntityManagerInterface             $entityManager,
        private readonly LibreTranslateService              $libreTranslate,
        private BingTranslatorService                       $bingTranslatorService,
        private LoggerInterface                             $logger,
        #[Autowire('%env(bool:BING_BACKUP)%')] private bool $bingBackup = false,
    )
    {

    }

    #[AsMessageHandler]
    public function __invoke(TranslateTarget $message): void
    {
        if (!$target = $this->targetRepository->find($message->targetKey)) {
            $this->logger->info("Missing translate target '{$message->targetKey}'");
            return; // it's missing, database was probably refreshed
        };
        $source = $target->getSource();
        if ($target->isTranslated()) {
            $this->logger->info("Already translated '{$message->targetKey}'");
            return; // already translated, probably queued multiple times
        }
        $targetLocale = $target->getTargetLocale();
        $this->logger->info("translating '{$source->getText(30)}' to $targetLocale");
        $targetText = $target->getTargetText();
        $sourceText = $source->getText();
        $from = $source->getLocale();
//            if (!$targetText)
        {
            switch ($engine = $target->getEngine()) {
                case 'libre':
                    $translation = $this->libreTranslate->translate($sourceText, $from, $targetLocale);
                    break;
                case 'bing':
                    $bingData = $this->bingTranslatorService->translate($sourceText, $from, $targetLocale);
                    $translation = $bingData[0]['translations'][0]['text'];
                    break;
            }
            $this->logger->info(sprintf("%s->%s",
                substr($sourceText, 0, 30),
                substr($translation, 0, 30),
            ));
            $target->setTargetText($translation);
            $target->setMarking($translation == $sourceText ? Target::PLACE_IDENTICAL : Target::PLACE_TRANSLATED);
            // disable fallback during local testing.  @todo: import/export
            if ($target->isIdentical() && ($engine === 'libre')) {
                // could just swap it out
                if ($this->bingBackup) {
                    $bingData = $this->bingTranslatorService->translate($sourceText, $from, $targetLocale);
                    $translation = $bingData[0]['translations'][0]['text'];
                    $target->setMarking(Target::PLACE_TRANSLATED);
                    $target->setBingTranslation($translation);
                    $this->logger->info("replaced with bing '{$translation}'");
                }
            }
        }

        // move when we handle in batches
        $this->entityManager->flush();

    }
}
