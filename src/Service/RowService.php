<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Target;
use Psr\Log\LoggerInterface;
use Survos\StateBundle\Event\RowEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class RowService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    #[AsEventListener()]
    public function onRowEvent(RowEvent $event): void
    {
        if (!$event->isRowLoad()) {
            return;
        }

        /** @var Target $target */
        $target = $event->getEntity();
        $text = $target->targetText ?? '';

        // look for a translation that's just the same word repeated (likely a bad translation)
        $allValues = explode(' ', trim($text));
        if (count($allValues) > 1 && count(array_unique($allValues, SORT_REGULAR)) === 1) {
            $this->logger->warning('Target has repeated-word translation', [
                'key' => $target->key,
                'sourceText' => $target->source->getText(),
                'targetText' => $text,
            ]);
        }
    }
}
