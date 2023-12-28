<?php

namespace Ayesh\Markdown\Tests;

/**
 * Test Markdown against the CommonMark spec, but less aggressive
 *
 * The resulting HTML markup is cleaned up before comparison, so examples
 * which would normally fail due to actually invisible differences (e.g.
 * superfluous whitespaces), don't fail. However, cleanup relies on block
 * element detection. The detection doesn't work correctly when an element's
 * `display` CSS property is manipulated. According to that this test is only
 * an interim solution on Markdown's way to full CommonMark compatibility.
 *
 * @link http://commonmark.org/ CommonMark
 */
class CommonMarkWeakTest extends CommonMarkStrictTest {
    protected string $textLevelElementRegex;

    protected function setUp(): void {
        parent::setUp();

        $textLevelElements = $this->markdown->getTextLevelElements();
        array_walk($textLevelElements, static function (&$element) {
            $element = preg_quote($element, '/');
        });
        $this->textLevelElementRegex = '\b(?:' . implode('|', $textLevelElements) . ')\b';
    }

    /**
     * @dataProvider data
     *
     * @param int $id
     * @param string $section
     * @param string $markdown
     * @param string $expectedHtml
     */
    public function testExample(int $id, string $section, string $markdown, string $expectedHtml): void {
        $actualHtml = $this->markdown->text($markdown);
        $this->assertEquals($this->cleanupHtml($expectedHtml), $this->cleanupHtml($actualHtml));
    }

    protected function cleanupHtml($markup): array|string|null {
        // invisible whitespaces at the beginning and end of block elements
        // however, whitespaces at the beginning of <pre> elements do matter
        return preg_replace(
            [
                '/(<(?!(?:' . $this->textLevelElementRegex . '|\bpre\b))\w+\b[^>]*>(?:<' . $this->textLevelElementRegex . '[^>]*>)*)\s+/s',
                '/\s+((?:<\/' . $this->textLevelElementRegex . '>)*<\/(?!' . $this->textLevelElementRegex . ')\w+\b>)/s',
            ],
            '$1',
            $markup
        );
    }
}
