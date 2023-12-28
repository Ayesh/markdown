<?php

namespace Ayesh\Markdown\Tests;

use Ayesh\Markdown\Markdown;

class TestMarkdown extends Markdown {
    public function getTextLevelElements(): array {
        return static::textLevelElements;
    }
}
