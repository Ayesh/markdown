<?php

namespace Ayesh\Markdown\Tests;

use Ayesh\Markdown\Markdown;

class SampleExtensions extends Markdown {
    protected function blockFencedCodeComplete($Block): array {
        $text = $Block['element']['element']['text'];
        unset($Block['element']['element']['text']);

        // WARNING: There is almost always a better way of doing things!
        //
        // This example is one of them, unsafe behaviour is NOT needed here.
        // Only use this if you trust the input and have no idea what
        // the output HTML will look like (e.g. using an external parser).
        $Block['element']['element']['rawHtml'] = "<p>$text</p>";

        return $Block;
    }
}

