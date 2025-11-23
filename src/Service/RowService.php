<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Target;
use Survos\StateBundle\Event\RowEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class RowService
{
    public function __construct()
    {
    }

    #[AsEventListener()]
    public function onRowEvent(RowEvent $event): void
    {
        if (!$event->isRowLoad()) {
            return;
        }

        /** @var Target $target */
        $target = $event->getEntity();
        $text = $target->getTargetText();
        $sourceText = $target->getSource()->getText();
        // look for duplicated words
        $allValues = explode(' ',trim($text));
        if (count($allValues) > 1) {
            if (str_word_count($text) != 1) {
                return;
            }
            if ($allValuesAreTheSame = (count(array_unique($allValues, SORT_REGULAR)) === 1)) {
                dump($allValues, $sourceText, $target->getSource()->getTranslations());
            }
        }
    }
}
