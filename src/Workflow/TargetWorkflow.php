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
        $engine = $target->engine;
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
        $response = $translator->translate(new TranslationRequest(
            $sourceText,
            $source->locale,
            $targetLocale,
        ));
        $translation = trim($response->translatedText);
        $target->targetText = $translation;
        $snippet = mb_substr($translation, 0, 30);

        $target->setMarking($translation === $sourceText ? TargetWorkflowInterface::PLACE_IDENTICAL : TargetWorkflowInterface::PLACE_TRANSLATED);
        $this->logger->info($target->getMarking() . " $from=>$targetLocale: '{$source->getText(30)}'=>{$snippet}");

        $this->entityManager->flush();
    }

}
