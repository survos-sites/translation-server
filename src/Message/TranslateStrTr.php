<?php
declare(strict_types=1);

namespace App\Message;

/** Translate a (hash, targetLocale) pair, using the given engine. */
final class TranslateStrTr
{
    public function __construct(
        public readonly string $hash,
        public readonly string $locale,
        public readonly ?string $engine = null,
    ) {

    }
}
