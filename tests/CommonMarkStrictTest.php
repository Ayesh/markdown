<?php

namespace Ayesh\Markdown\Tests;

use Ayesh\Markdown\Markdown;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test Markdown against the CommonMark spec
 *
 * @link http://commonmark.org/ CommonMark
 */
class CommonMarkStrictTest extends TestCase {
    protected const string SPEC_URL = 'https://raw.githubusercontent.com/jgm/CommonMark/master/spec.txt';
    protected Markdown $markdown;

    protected function setUp(): void {
        $this->markdown = new TestMarkdown();
        $this->markdown->setUrlsLinked(false);
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
        $this->assertEquals($expectedHtml, $actualHtml);
    }

    /**
     * @return array
     */
    public static function data(): array {
        $spec = file_get_contents(self::SPEC_URL);

        if ($spec === false) {
            throw new RuntimeException('Unable to load CommonMark spec from ' . self::SPEC_URL);
        }

        $spec = str_replace("\r\n", "\n", $spec);
        $spec = strstr($spec, '<!-- END TESTS -->', true);

        $matches = [];
        preg_match_all(
            '/^`{32} example\n((?s).*?)\n\.\n(?:|((?s).*?)\n)`{32}$|^#{1,6} *(.*?)$/m',
            $spec,
            $matches,
            PREG_SET_ORDER
        );

        $data = [];
        $currentId = 0;
        $currentSection = '';
        foreach ($matches as $match) {
            if (isset($match[3])) {
                $currentSection = $match[3];
            } else {
                $currentId++;
                $markdown = str_replace('→', "\t", $match[1]);
                $expectedHtml = isset($match[2]) ? str_replace('→', "\t", $match[2]) : '';

                $data[$currentId] = [
                    'id' => $currentId,
                    'section' => $currentSection,
                    'markdown' => $markdown,
                    'expectedHtml' => $expectedHtml,
                ];
            }
        }

        return $data;
    }
}
