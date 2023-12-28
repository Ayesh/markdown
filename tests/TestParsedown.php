<?php

namespace Ayesh\Markdown\Tests;

use Ayesh\Markdown\Markdown;

class TestParsedown extends Markdown {
    public function getTextLevelElements(): array {
        return static::textLevelElements;
    }
}
