<?php

namespace Ayesh\Markdown\Tests;

use Ayesh\Markdown\Markdown;
use DirectoryIterator;
use PHPUnit\Framework\TestCase;

class MarkdownTest extends TestCase {

    protected static array $dirs = [];
    protected Markdown $markdown;

    final public function __construct($name = null, array $data = [], $dataName = '') {
        self::$dirs = static::initDirs();
        $this->markdown = $this->initMarkdown();

        parent::__construct($name, $data, $dataName);
    }

    /**
     * @return array
     */
    protected static function initDirs(): array {
        $dirs [] = __DIR__ . '/data/';

        return $dirs;
    }

    /**
     * @return Markdown
     */
    protected function initMarkdown(): Markdown {
        return new Markdown();
    }

    /**
     * @dataProvider data
     *
     * @param $test
     * @param $dir
     */
    public function testFixtures($test, $dir): void {
        $markdown = file_get_contents($dir . $test . '.md');

        $expectedMarkup = file_get_contents($dir . $test . '.html');

        $expectedMarkup = str_replace("\r\n", "\n", $expectedMarkup);
        $expectedMarkup = str_replace("\r", "\n", $expectedMarkup);
        $expectedMarkup = rtrim($expectedMarkup);

        $this->markdown->setSafeMode(str_starts_with($test, 'xss'));

        $actualMarkup = $this->markdown->text($markdown);

        $this->assertEquals($expectedMarkup, $actualMarkup);
    }

    public function testRawHtml(): void {
        $markdown = "```php\nfoobar\n```";
        $expectedMarkup = '<pre><code class="language-php">foobar</code></pre>';
        $expectedSafeMarkup = '<pre><code class="language-php">foobar</code></pre>';

        $unsafeExtension = new SampleExtensions;
        $actualMarkup = $unsafeExtension->text($markdown);

        $this->assertEquals($expectedMarkup, $actualMarkup);

        $unsafeExtension->setSafeMode(true);
        $actualSafeMarkup = $unsafeExtension->text($markdown);

        $this->assertEquals($expectedSafeMarkup, $actualSafeMarkup);
    }

    public function testTrustDelegatedRawHtml(): void {
        $markdown = "```php\nfoobar\n```";
        $expectedMarkup = '<pre><code class="language-php">foobar</code></pre>';
        $expectedSafeMarkup = $expectedMarkup;

        $unsafeExtension = new TrustDelegatedExtension();
        $actualMarkup = $unsafeExtension->text($markdown);

        $this->assertEquals($expectedMarkup, $actualMarkup);

        $unsafeExtension->setSafeMode(true);
        $actualSafeMarkup = $unsafeExtension->text($markdown);

        $this->assertEquals($expectedSafeMarkup, $actualSafeMarkup);
    }

    public static function data(): array {
        $data = [];
        $dirs = static::initDirs();

        foreach ($dirs as $dir) {
            $Folder = new DirectoryIterator($dir);

            foreach ($Folder as $File) {
                if (!$File->isFile()) {
                    continue;
                }

                $filename = $File->getFilename();

                $extension = pathinfo($filename, PATHINFO_EXTENSION);

                if ($extension !== 'md') {
                    continue;
                }

                $basename = $File->getBasename('.md');

                if (file_exists($dir . $basename . '.html')) {
                    $data [] = [$basename, $dir];
                }
            }
        }

        return $data;
    }

    public function test_no_markup(): void {
        $markdownWithHtml = <<<MARKDOWN_WITH_MARKUP
<div>_content_</div>

sparse:

<div>
<div class="inner">
_content_
</div>
</div>

paragraph

<style type="text/css">
    p {
        color: red;
    }
</style>

comment

<!-- html comment -->
MARKDOWN_WITH_MARKUP;

        $expectedHtml = <<<EXPECTED_HTML
<p>&lt;div&gt;<em>content</em>&lt;/div&gt;</p>
<p>sparse:</p>
<p>&lt;div&gt;
&lt;div class=&quot;inner&quot;&gt;
<em>content</em>
&lt;/div&gt;
&lt;/div&gt;</p>
<p>paragraph</p>
<p>&lt;style type=&quot;text/css&quot;&gt;
p {
color: red;
}
&lt;/style&gt;</p>
<p>comment</p>
<p>&lt;!-- html comment --&gt;</p>
EXPECTED_HTML;

        $markdownWithNoMarkup = new Markdown();
        $markdownWithNoMarkup->setMarkupEscaped(true);
        $this->assertEquals($expectedHtml, $markdownWithNoMarkup->text($markdownWithHtml));
    }

    public function testLateStaticBinding(): void {
        $markdown = Markdown::instance();
        $this->assertInstanceOf(Markdown::class, $markdown);

        // After instance is already called on Markdown
        // subsequent calls with the same arguments return the same instance
        $sameMarkdown = Markdown::instance();
        $this->assertInstanceOf(Markdown::class, $sameMarkdown);
        $this->assertSame($markdown, $sameMarkdown);

        $testMarkdown = Markdown::instance('test late static binding');
        $this->assertInstanceOf(Markdown::class, $testMarkdown);

        $sameInstanceAgain = Markdown::instance('test late static binding');
        $this->assertSame($testMarkdown, $sameInstanceAgain);
    }
}
