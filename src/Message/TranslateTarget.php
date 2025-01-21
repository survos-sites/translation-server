<?php

namespace App\Message;

final class TranslateTarget
{
    /*
     * Add whatever properties and methods you need
     * to hold the data for this message class.
     */

     public function __construct(
         public readonly string $targetKey,
         public readonly ?string $engine=null, // ??
     ) {
     }
}
