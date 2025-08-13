<?php

namespace App\Workflow;

use Survos\WorkflowBundle\Attribute\Place;
use Survos\WorkflowBundle\Attribute\Transition;

// See events at https://symfony.com/doc/current/workflow.html#using-events

interface TargetWorkflowInterface
{
    // This name is used for injecting the workflow into a service
    // #[Target(TargetWorkflowInterface::WORKFLOW_NAME)] private WorkflowInterface $workflow
    public const WORKFLOW_NAME = 'TargetWorkflow';

    #[Place(initial: true, info: "pending")]
    const PLACE_UNTRANSLATED='u';

    #[Place(info: 'translated')]
    const PLACE_TRANSLATED='t';
    #[Place(info: 'identical', description: "source exists elsewhere?")]
    const PLACE_IDENTICAL='i';
    const PLACES = [self::PLACE_UNTRANSLATED, self::PLACE_TRANSLATED, self::PLACE_IDENTICAL];

    #[Transition([self::PLACE_UNTRANSLATED], self::PLACE_TRANSLATED)]
    public const TRANSITION_TRANSLATE = 'translate';
}
