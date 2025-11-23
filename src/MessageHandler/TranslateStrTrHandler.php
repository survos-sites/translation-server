<?php

namespace App\MessageHandler;

use App\Entity\Str;
use App\Entity\StrTranslation;
use App\Entity\Target;
use App\Message\TranslateStrTr;
use App\Repository\StrRepository;
use App\Repository\StrTranslationRepository;
use App\Repository\TargetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

use Survos\CoreBundle\Service\SurvosUtils;
use Survos\LinguaBundle\Workflow\StrTrWorkflowInterface;
use Survos\TranslatorBundle\Model\TranslationRequest;
use Survos\TranslatorBundle\Service\TranslatorManager;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TranslateStrTrHandler
{
    public function __construct(
        private readonly StrTranslationRepository                   $strTranslationRepository,
        private readonly StrRepository                  $strRepository,
        private readonly EntityManagerInterface             $entityManager,
        private readonly TranslatorManager $manager,
        private LoggerInterface                             $logger
    )
    {

    }

    #[AsMessageHandler]
    public function __invoke(TranslateStrTr $message): void
    {
        assert(false, "Back to Target and a workflow transition");
        /** @var StrTranslation $strTr */
        if (!$strTr = $this->strTranslationRepository->find($message->hash)) {
            // already removed
            return;
        }
        // skip if it's already translated, duplicate in queue
        if ($strTr->isTranslated) {
            return;
        }
        /** @var Str $str */
        $str = $this->strRepository->find($strTr->strHash);
        assert($str, "Missing str for " . $message->hash);
        // @todo: engine preference
        $engine = 'libre';
        $req = new TranslationRequest($str->original, $str->srcLocale, $strTr->locale);
        $translation = $this->manager->by($engine)->translate($req);
        $strTr->text = $translation->translatedText;
        $strTr->setMarking(StrTrWorkflowInterface::PLACE_TRANSLATED);
        $str->localeStatuses[$strTr->locale] = StrTrWorkflowInterface::PLACE_TRANSLATED;
        // put the actual translation here instead?
        return;
        dd($req, $translation);


        $input = new ArgvInput();
        $output = new ConsoleOutput();
        $symfonyStyle = new SymfonyStyle($input, $output);
        if (!$target = $this->targetRepository->find($message->targetKey)) {
            $this->logger->info("Missing translate target '{$message->targetKey}'");
            return; // it's missing, database was probably refreshed
        };
        if ($target->isTranslated()) {
            $this->logger->info("Already translated '{$message->targetKey}'");
//            return; // already translated, probably queued multiple times
        }

        $source = $target->getSource();
        $engine = $message->engine ?? $target->getEngine() ?? 'libre';
//        dd($this->manager->registry, $this->manager->names());
        SurvosUtils::assertInArray($engine, $this->manager->names(), __CLASS__);
        $translator = $this->manager->by($engine);
        $targetLocale = $target->getTargetLocale();
        $sourceText = $source->getText();
        $from = $source->getLocale();
        $response = $translator->translate(new TranslationRequest(
            $sourceText,
            $source->getLocale(),
            $targetLocale,
        ));
        $translation = trim($response->translatedText);
        $target->setTargetText($translation);
        $snippet = mb_substr($translation, 0, 30);
        // boo
            $target->setMarking($translation == $sourceText ? Target::PLACE_IDENTICAL : Target::PLACE_TRANSLATED);
            $msg = $target->getMarking() . " $from=>$targetLocale: '{$source->getText(30)}'=>{$snippet}";
            $symfonyStyle->writeln($msg);
            // disable fallback during local testing.  @todo: import/export
            if ( (($translation === '') || $target->isIdentical()) && ($engine === 'libre')) {
                // could just swap it out
                if (false && $this->bingBackup) {
                    $bingData = $this->bingTranslatorService->translate($sourceText, $from, $targetLocale);
                    $translation = $bingData[0]['translations'][0]['text'];
                    $target->setMarking(Target::PLACE_TRANSLATED);
                    $target->setBingTranslation($translation);
                    $msg = "replaced with bing '{$translation}'";
                    $this->logger->info($msg);
                    $symfonyStyle->writeln($msg);
                }
            }
        // move when we handle in batches
        $this->entityManager->flush();

    }
}
