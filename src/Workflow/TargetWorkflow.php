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

        if ($target->isTranslated()) {
            $this->logger->info("Already translated '{$target->getKey()}'");
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
