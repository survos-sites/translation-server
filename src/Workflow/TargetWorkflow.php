<?php

namespace App\Workflow;

use App\Entity\Target;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Survos\CoreBundle\Service\SurvosUtils;
use Survos\TranslatorBundle\Model\TranslationRequest;
use Survos\TranslatorBundle\Service\TranslatorManager;
use Survos\StateBundle\Attribute\Workflow;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;
use App\Workflow\TargetWorkflowInterface as WF;

// See events at https://symfony.com/doc/current/workflow.html#using-events

// @todo: add the entity class to attach this to.
final class TargetWorkflow
{

    public function __construct(
        private readonly EntityManagerInterface             $entityManager,
        private readonly TranslatorManager $manager,
        private LoggerInterface                             $logger
    )
    {
    }

    #[AsGuardListener(WF::WORKFLOW_NAME)]
    public function onGuard(GuardEvent $event): void
    {
        // switch ($event->getTransition()) { ...
    }
    private function getTarget(Event $event): Target
    {
        /** @var Target */ return $event->getSubject();
    }


    #[AsTransitionListener(WF::WORKFLOW_NAME, TargetWorkflowInterface::TRANSITION_TRANSLATE)]
    public function onTransition(TransitionEvent $event): void
    {
        $target = $this->getTarget($event);

        if ($target->isTranslated) {
            $this->logger->info("Already translated '{$target->key}'");
//            return; // already translated, probably queued multiple times
        }

        $source = $target->source;
        $engine = $message->engine ?? $target->engine ?? 'libre';
//        dd($this->manager->registry, $this->manager->names());
        SurvosUtils::assertInArray($engine, $this->manager->names(), __CLASS__);
        $translator = $this->manager->by($engine);
        assert($translator, "missing translator");
        if (!$translator) {
            return;
        }
        $targetLocale = $target->targetLocale;
        $sourceText = $source->getText();
        $from = $source->locale;
        $this->logger->warning("Translating " . $sourceText . " to '{$target->targetLocale}'");
        $response = $translator->translate($req = new TranslationRequest(
            $sourceText,
            $source->locale,
            $targetLocale,
        ));
        $translation = trim($response->translatedText);
        $this->logger->warning($translation);
        $target->targetText  = $translation;
        $snippet = mb_substr($translation, 0, 30);
        if ($target->key === '46dNL588d50b8ac0d8-es') {
            dump($target, $req, $source, $response, $translator);
        }

        // boo
        $target->setMarking($translation == $sourceText ? TargetWorkflowInterface::PLACE_IDENTICAL : TargetWorkflowInterface::PLACE_TRANSLATED);
        $msg = $target->getMarking() . " $from=>$targetLocale: '{$source->getText(30)}'=>{$snippet}";
        // disable fallback during local testing.  @todo: import/export
        if ( (($translation === '') || $target->isIdentical) && ($engine === 'libre')) {
//            dd($translation, $req);
            // could just swap it out
            if (false && $this->bingBackup) {
                $bingData = $this->bingTranslatorService->translate($sourceText, $from, $targetLocale);
                $translation = $bingData[0]['translations'][0]['text'];
                $target->setMarking(TargetWorkflowInterface::PLACE_TRANSLATED);
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
