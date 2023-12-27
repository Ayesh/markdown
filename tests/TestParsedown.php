<?php

namespace Ayesh\Markdown\Tests;

use Parsedown;

class TestParsedown extends Parsedown {
    public function getTextLevelElements() {
        return $this->textLevelElements;
    }
}
