<?php

namespace App\MessageHandler;

use App\Entity\Target;
use App\Message\TranslateTarget;
use App\Repository\TargetRepository;
use App\Service\BingTranslatorService;
use Doctrine\ORM\EntityManagerInterface;
use Jefs42\LibreTranslate;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TranslateTargetHandler
{
    public function __construct(
        private readonly TargetRepository $targetRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LibreTranslate $libreTranslate,
        private BingTranslatorService $bingTranslatorService,
        private LoggerInterface $logger,
    ) {

    }
    #[AsMessageHandler]
    public function __invoke(TranslateTarget $message): void
    {
        $target = $this->targetRepository->find($message->targetKey);
        $source = $target->getSource();
        assert($target !== null);
            $targetText = $target->getTargetText();
            $sourceText = $source->getText();
            $from = $source->getLocale();
            $targetLocale = $target->getTargetLocale();
            if (!$targetText) {
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
                $target->setMarking(Target::PLACE_TRANSLATED);
                if ($translation == $sourceText) {
                    if ($engine == 'libre') {
                        // could just swap it out
                        $bingData = $this->bingTranslatorService->translate($sourceText, $from, $targetLocale);
                        $translation = $bingData[0]['translations'][0]['text'];
                        if ($translation == $sourceText) {
                            $target->setMarking(Target::PLACE_IDENTICAL);
//                            dd($translation, $sourceText, $bingData, $from, $targetLocale);
                        } else {
                            $target->setBingTranslation($translation);
                        }
                    }
                }
            }

            // move when we handle in batches
            $this->entityManager->flush();

    }
}
