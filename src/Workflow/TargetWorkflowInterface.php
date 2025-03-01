<?php

namespace App\Workflow;

use Survos\WorkflowBundle\Attribute\Transition;

// See events at https://symfony.com/doc/current/workflow.html#using-events

interface TargetWorkflowInterface
{
    // This name is used for injecting the workflow into a service
    // #[Target(TargetWorkflowInterface::WORKFLOW_NAME)] private WorkflowInterface $workflow
    public const WORKFLOW_NAME = 'TargetWorkflow';

    const PLACE_UNTRANSLATED='u';
    const PLACE_TRANSLATED='t';
    const PLACE_IDENTICAL='i';
    const PLACES = [self::PLACE_UNTRANSLATED, self::PLACE_TRANSLATED, self::PLACE_IDENTICAL];

    #[Transition([self::PLACE_UNTRANSLATED], self::PLACE_TRANSLATED)]
    public const TRANSITION_TRANSLATE = 'translate';
}
