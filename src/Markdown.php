<?php

namespace Ayesh\Markdown;

use function end;
use function explode;
use function htmlspecialchars;
use function in_array;
use function is_string;
use function mb_strlen;
use function method_exists;
use function min;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function str_repeat;
use function str_replace;
use function str_starts_with;
use function strcspn;
use function stripos;
use function strlen;
use function strpbrk;
use function strpos;
use function strstr;
use function strtolower;
use function substr;
use function substr_replace;
use function trim;

class Markdown {
    public const string version = '2.0.0';
    protected bool $breaksEnabled = false;
    protected bool $markupEscaped = false;
    protected bool $urlsLinked = true;
    protected bool $safeMode = false;
    private const array safeLinksWhitelist = [
        'http://',
        'https://',
        'ftp://',
        'ftps://',
        'mailto:',
        'data:image/png;base64,',
        'data:image/gif;base64,',
        'data:image/jpeg;base64,',
        'irc:',
        'ircs:',
        'git:',
        'ssh:',
        'news:',
        'steam:',
    ];
    protected const array BlockTypes = [
        '#' => ['Header'],
        '*' => ['Rule', 'List'],
        '+' => ['List'],
        '-' => ['SetextHeader', 'Table', 'Rule', 'List'],
        '0' => ['List'],
        '1' => ['List'],
        '2' => ['List'],
        '3' => ['List'],
        '4' => ['List'],
        '5' => ['List'],
        '6' => ['List'],
        '7' => ['List'],
        '8' => ['List'],
        '9' => ['List'],
        ':' => ['Table'],
        '<' => ['Comment', 'Markup'],
        '=' => ['SetextHeader'],
        '>' => ['Quote'],
        '[' => ['Reference'],
        '_' => ['Rule'],
        '`' => ['FencedCode'],
        '|' => ['Table'],
        '~' => ['FencedCode'],
    ];
    private static array $instances = [];
    #
    # Fields
    #
    protected array $DefinitionData;
    #
    # Read-Only
    protected const array specialCharacters = [
        '\\',
        '`',
        '*',
        '_',
        '{',
        '}',
        '[',
        ']',
        '(',
        ')',
        '>',
        '#',
        '+',
        '-',
        '.',
        '!',
        '|',
    ];

    protected const array StrongRegex = [
        '*' => '/^[*]{2}((?:\\\\\*|[^*]|[*][^*]*[*])+?)[*]{2}(?![*])/s',
        '_' => '/^__((?:\\\\_|[^_]|_[^_]*_)+?)__(?!_)/us',
    ];

    protected const array EmRegex = [
        '*' => '/^[*]((?:\\\\\*|[^*]|[*][*][^*]+?[*][*])+?)[*](?![*])/s',
        '_' => '/^_((?:\\\\_|[^_]|__[^_]*__)+?)_(?!_)\b/us',
    ];

    protected const string regexHtmlAttribute = '[a-zA-Z_:][\w:.-]*(?:\s*=\s*(?:[^"\'=<>`\s]+|"[^"]*"|\'[^\']*\'))?';

    protected const array voidElements = [
        'area',
        'base',
        'br',
        'col',
        'command',
        'embed',
        'hr',
        'img',
        'input',
        'link',
        'meta',
        'param',
        'source',
    ];

    protected const array textLevelElements = [
        'a',
        'br',
        'bdo',
        'abbr',
        'blink',
        'nextid',
        'acronym',
        'basefont',
        'b',
        'em',
        'big',
        'cite',
        'small',
        'spacer',
        'listing',
        'i',
        'rp',
        'del',
        'code',
        'strike',
        'marquee',
        'q',
        'rt',
        'ins',
        'font',
        'strong',
        's',
        'tt',
        'kbd',
        'mark',
        'u',
        'xm',
        'sub',
        'nobr',
        'sup',
        'ruby',
        'var',
        'span',
        'wbr',
        'time',
    ];

    protected const array unmarkedBlockTypes = [
        'Code',
    ];

    protected const array InlineTypes = [
        '"' => ['SpecialCharacter'],
        '!' => ['Image'],
        '&' => ['SpecialCharacter'],
        '*' => ['Emphasis'],
        ':' => ['Url'],
        '<' => ['UrlTag', 'EmailTag', 'Markup', 'SpecialCharacter'],
        '>' => ['SpecialCharacter'],
        '[' => ['Link'],
        '_' => ['Emphasis'],
        '`' => ['Code'],
        '~' => ['Strikethrough'],
        '\\' => ['EscapeSequence'],
    ];

    protected string $inlineMarkerList = '!"*_&[:<>`~\\';

    public function text($text): string {
        # make sure no definitions are set
        $this->DefinitionData = [];

        # standardize line breaks
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        # remove surrounding line breaks
        $text = trim($text, "\n");

        # split text into lines
        $lines = explode("\n", $text);

        # iterate through lines to identify blocks
        $markup = $this->lines($lines);

        # trim line breaks
        return trim($markup, "\n");
    }

    public function setBreaksEnabled(bool $breaksEnabled): static {
        $this->breaksEnabled = $breaksEnabled;
        return $this;
    }


    public function setMarkupEscaped($markupEscaped): Markdown {
        $this->markupEscaped = $markupEscaped;
        return $this;
    }

    public function setUrlsLinked($urlsLinked): Markdown {
        $this->urlsLinked = $urlsLinked;
        return $this;
    }

    public function setSafeMode($safeMode): Markdown {
        $this->safeMode = (bool)$safeMode;
        return $this;
    }

    #
    # Blocks
    #

    protected function lines(array $lines): string {
        $CurrentBlock = null;

        foreach ($lines as $line) {
            if (rtrim($line) === '') {
                if (isset($CurrentBlock)) {
                    $CurrentBlock['interrupted'] = true;
                }

                continue;
            }

            if (str_contains($line, "\t")) {
                $parts = explode("\t", $line);

                $line = $parts[0];

                unset($parts[0]);

                foreach ($parts as $part) {
                    $shortage = 4 - mb_strlen($line, 'utf-8') % 4;

                    $line .= str_repeat(' ', $shortage);
                    $line .= $part;
                }
            }

            $indent = 0;

            while (isset($line[$indent]) and $line[$indent] === ' ') {
                $indent++;
            }

            $text = $indent > 0 ? substr($line, $indent) : $line;

            $Line = ['body' => $line, 'indent' => $indent, 'text' => $text];

            if (isset($CurrentBlock['continuable'])) {
                $Block = $this->{'block' . $CurrentBlock['type'] . 'Continue'}($Line, $CurrentBlock);

                if (isset($Block)) {
                    $CurrentBlock = $Block;

                    continue;
                }

                if ($this->isBlockCompletable($CurrentBlock['type'])) {
                    $CurrentBlock = $this->{'block' . $CurrentBlock['type'] . 'Complete'}($CurrentBlock);
                }
            }

            $marker = $text[0];

            $blockTypes = static::unmarkedBlockTypes;

            if (isset(static::BlockTypes[$marker])) {
                foreach (static::BlockTypes[$marker] as $blockType) {
                    $blockTypes [] = $blockType;
                }
            }

            foreach ($blockTypes as $blockType) {
                $Block = $this->{'block' . $blockType}($Line, $CurrentBlock);

                if (isset($Block)) {
                    $Block['type'] = $blockType;

                    if (!isset($Block['identified'])) {
                        $Blocks [] = $CurrentBlock;

                        $Block['identified'] = true;
                    }

                    if ($this->isBlockContinuable($blockType)) {
                        $Block['continuable'] = true;
                    }

                    $CurrentBlock = $Block;

                    continue 2;
                }
            }


            if (isset($CurrentBlock) && !isset($CurrentBlock['type']) && !isset($CurrentBlock['interrupted'])) {
                $CurrentBlock['element']['text'] .= "\n" . $text;
            } else {
                $Blocks [] = $CurrentBlock;

                $CurrentBlock = $this->paragraph($Line);

                $CurrentBlock['identified'] = true;
            }
        }

        if (isset($CurrentBlock['continuable']) && $this->isBlockCompletable($CurrentBlock['type'])) {
            $CurrentBlock = $this->{'block' . $CurrentBlock['type'] . 'Complete'}($CurrentBlock);
        }

        $Blocks [] = $CurrentBlock;

        unset($Blocks[0]);

        $markup = '';

        foreach ($Blocks as $Block) {
            if (isset($Block['hidden'])) {
                continue;
            }

            $markup .= "\n";
            $markup .= $Block['markup'] ?? $this->element($Block['element']);
        }

        $markup .= "\n";

        return $markup;
    }

    protected function isBlockContinuable($Type): bool {
        return method_exists($this, 'block' . $Type . 'Continue');
    }

    protected function isBlockCompletable($Type): bool {
        return method_exists($this, 'block' . $Type . 'Complete');
    }

    #
    # Code

    protected function blockCode($Line, $Block = null) {
        if (isset($Block) && !isset($Block['type']) && !isset($Block['interrupted'])) {
            return;
        }

        if ($Line['indent'] >= 4) {
            $text = substr($Line['body'], 4);

            return [
                'element' => [
                    'name' => 'pre',
                    'handler' => 'element',
                    'text' => [
                        'name' => 'code',
                        'text' => $text,
                    ],
                ],
            ];
        }
    }

    protected function blockCodeContinue($Line, $Block) {
        if ($Line['indent'] >= 4) {
            if (isset($Block['interrupted'])) {
                $Block['element']['text']['text'] .= "\n";

                unset($Block['interrupted']);
            }

            $Block['element']['text']['text'] .= "\n";

            $text = substr($Line['body'], 4);

            $Block['element']['text']['text'] .= $text;

            return $Block;
        }
    }

    protected function blockCodeComplete($Block): array {
        $text = $Block['element']['text']['text'];

        $Block['element']['text']['text'] = $text;

        return $Block;
    }

    #
    # Comment

    protected function blockComment($Line) {
        if ($this->markupEscaped || $this->safeMode) {
            return;
        }

        if (isset($Line['text'][3]) && $Line['text'][3] === '-' && $Line['text'][2] === '-' && $Line['text'][1] === '!') {
            $Block = [
                'markup' => $Line['body'],
            ];

            if (str_ends_with($Line['text'], '-->')) {
                $Block['closed'] = true;
            }

            return $Block;
        }
    }

    protected function blockCommentContinue($Line, array $Block) {
        if (isset($Block['closed'])) {
            return;
        }

        $Block['markup'] .= "\n" . $Line['body'];

        if (str_ends_with($Line['text'], '-->')) {
            $Block['closed'] = true;
        }

        return $Block;
    }

    #
    # Fenced Code

    protected function blockFencedCode($Line): ?array {
        if (!preg_match('/^[' . $Line['text'][0] . ']{3,} *([^`]+)? *$/', $Line['text'], $matches)) {
            return null;
        }

        $Element = [
            'name' => 'code',
            'text' => '',
        ];

        $extra_attributes = [];

        if (isset($matches[1])) {
            /**
             * https://www.w3.org/TR/2011/WD-html5-20110525/elements.html#classes
             * Every HTML element may have a class attribute specified.
             * The attribute, if specified, must have a value that is a set
             * of space-separated tokens representing the various classes
             * that the element belongs to.
             * [...]
             * The space characters, for the purposes of this specification,
             * are U+0020 SPACE, U+0009 CHARACTER TABULATION (tab),
             * U+000A LINE FEED (LF), U+000C FORM FEED (FF), and
             * U+000D CARRIAGE RETURN (CR).
             */
            $language = substr($matches[1], 0, strcspn($matches[1], " \t\n\f\r"));

            $class = 'language-' . $language;
            $extras = str_replace($language, '', $matches[1]);
            if ($extras) {
                $extra_attributes['class'] = $extras;
            }
            $Element['attributes'] = [
                'class' => $class,
            ];
        }

        return [
            'char' => $Line['text'][0],
            'element' => [
                'name' => 'pre',
                'handler' => 'element',
                'text' => $Element,
                'attributes' => $extra_attributes,
            ],
        ];
    }

    protected function blockFencedCodeContinue($Line, $Block) {
        if (isset($Block['complete'])) {
            return;
        }

        if (isset($Block['interrupted'])) {
            $Block['element']['text']['text'] .= "\n";

            unset($Block['interrupted']);
        }

        if (preg_match('/^' . $Block['char'] . '{3,} *$/', $Line['text'])) {
            $Block['element']['text']['text'] = substr($Block['element']['text']['text'], 1);

            $Block['complete'] = true;

            return $Block;
        }

        $Block['element']['text']['text'] .= "\n" . $Line['body'];

        return $Block;
    }

    protected function blockFencedCodeComplete($Block): array {
        $text = $Block['element']['text']['text'];

        $Block['element']['text']['text'] = $text;

        return $Block;
    }

    #
    # Header

    protected function blockHeader($Line): ?array {
        if (!isset($Line['text'][1])) {
            return null;
        }

        $level = 1;

        while (isset($Line['text'][$level]) and $Line['text'][$level] === '#') {
            $level++;
        }

        if ($level > 6) {
            return null;
        }

        $text = trim($Line['text'], '# ');
        $h_id = null;
        if (preg_match('/^{([\w_-]+)}/', $text, $matches)) {
            $h_id = $matches[1];
            $text = '<a href="#' . $matches[1] . '" class="anchor">' . str_replace(
                    $matches[0],
                    '',
                    trim($text)
                ) . '</a>';
        } elseif (preg_match('/{#([A-Za-z\d_.-]+)}$/', $text, $matches)) {
            $h_id = $matches[1];
            $text = '<a href="#' . $matches[1] . '" class="anchor">' . str_replace(
                    $matches[0],
                    '',
                    trim($text)
                ) . '</a>';
        }

        return [
            'element' => [
                'name' => 'h' . min(6, $level),
                'text' => $text,
                'handler' => 'line',
                'attributes' => [
                    'id' => $h_id,
                ],
            ],
        ];
    }

    #
    # List

    protected function blockList($Line) {
        [$name, $pattern] = $Line['text'][0] <= '-' ? ['ul', '[*+-]'] : ['ol', '[0-9]+[.]'];

        if (preg_match('/^(' . $pattern . ' +)(.*)/', $Line['text'], $matches)) {
            $Block = [
                'indent' => $Line['indent'],
                'pattern' => $pattern,
                'element' => [
                    'name' => $name,
                    'handler' => 'elements',
                ],
            ];

            if ($name === 'ol') {
                $listStart = strstr($matches[0], '.', true);

                if ($listStart !== '1') {
                    $Block['element']['attributes'] = ['start' => $listStart];
                }
            }

            $Block['li'] = [
                'name' => 'li',
                'handler' => 'li',
                'text' => [
                    $matches[2],
                ],
            ];

            $Block['element']['text'] [] = &$Block['li'];

            return $Block;
        }
    }

    protected function blockListContinue($Line, array $Block) {
        if ($Block['indent'] === $Line['indent'] && preg_match(
                '/^' . $Block['pattern'] . '(?: +(.*)|$)/',
                $Line['text'],
                $matches
            )) {
            if (isset($Block['interrupted'])) {
                $Block['li']['text'] [] = '';

                $Block['loose'] = true;

                unset($Block['interrupted']);
            }

            unset($Block['li']);

            $text = $matches[1] ?? '';

            $Block['li'] = [
                'name' => 'li',
                'handler' => 'li',
                'text' => [
                    $text,
                ],
            ];

            $Block['element']['text'] [] = &$Block['li'];

            return $Block;
        }

        if ($Line['text'][0] === '[' && $this->blockReference($Line)) {
            return $Block;
        }

        if (!isset($Block['interrupted'])) {
            $text = preg_replace('/^ {0,4}/', '', $Line['body']);

            $Block['li']['text'] [] = $text;

            return $Block;
        }

        if ($Line['indent'] > 0) {
            $Block['li']['text'] [] = '';

            $text = preg_replace('/^ {0,4}/', '', $Line['body']);

            $Block['li']['text'] [] = $text;

            unset($Block['interrupted']);

            return $Block;
        }
    }

    protected function blockListComplete(array $Block): array {
        if (isset($Block['loose'])) {
            foreach ($Block['element']['text'] as &$li) {
                if (end($li['text']) !== '') {
                    $li['text'] [] = '';
                }
            }
        }

        return $Block;
    }

    #
    # Quote

    protected function blockQuote($Line) {
        if (preg_match('/^> ?(.*)/', $Line['text'], $matches)) {
            $attr = [];
            if (preg_match('/^{\.([\w_-]+)}/', $matches[1], $class_attr)) {
                $matches[1] = str_replace($class_attr[0], '', $matches[1]);
                $attr['class'] = $class_attr[1];
            }

            return [
                'element' => [
                    'name' => 'blockquote',
                    'handler' => 'lines',
                    'text' => (array)$matches[1],
                    'attributes' => $attr,
                ],
            ];
        }
    }

    protected function blockQuoteContinue($Line, array $Block) {
        if ($Line['text'][0] === '>' && preg_match('/^> ?(.*)/', $Line['text'], $matches)) {
            if (isset($Block['interrupted'])) {
                $Block['element']['text'] [] = '';

                unset($Block['interrupted']);
            }

            $Block['element']['text'] [] = $matches[1];

            return $Block;
        }

        if (!isset($Block['interrupted'])) {
            $Block['element']['text'] [] = $Line['text'];

            return $Block;
        }
    }

    #
    # Rule

    protected function blockRule($Line) {
        if (preg_match('/^([' . $Line['text'][0] . '])( *\1){2,} *$/', $Line['text'])) {
            $Block = [
                'element' => [
                    'name' => 'hr',
                ],
            ];

            if ($Line['text'] === '***') {
                $Block['element']['attributes'] = [
                    'class' => 'type-minor',
                ];
            }

            return $Block;
        }
    }

    #
    # Setext

    protected function blockSetextHeader($Line, ?array $Block = null) {
        if (!isset($Block) || isset($Block['type']) || isset($Block['interrupted'])) {
            return;
        }

        if (rtrim($Line['text'], $Line['text'][0]) === '') {
            $Block['element']['name'] = $Line['text'][0] === '=' ? 'h1' : 'h2';

            return $Block;
        }
    }

    #
    # Markup

    protected function blockMarkup($Line) {
        if ($this->markupEscaped || $this->safeMode) {
            return;
        }

        if (preg_match('/^<(\w[\w-]*)(?: *' . static::regexHtmlAttribute . ')* *(\/)?>/', $Line['text'], $matches)) {
            $element = strtolower($matches[1]);

            if (in_array($element, static::textLevelElements)) {
                return;
            }

            $Block = [
                'name' => $matches[1],
                'depth' => 0,
                'markup' => $Line['text'],
            ];

            $length = strlen($matches[0]);

            $remainder = substr($Line['text'], $length);

            if (trim($remainder) === '') {
                if (isset($matches[2]) || in_array($matches[1], static::voidElements)) {
                    $Block['closed'] = true;

                    $Block['void'] = true;
                }
            } else {
                if (isset($matches[2]) || in_array($matches[1], static::voidElements)) {
                    return;
                }

                if (preg_match('/<\/' . $matches[1] . '> *$/i', $remainder)) {
                    $Block['closed'] = true;
                }
            }

            return $Block;
        }
    }

    protected function blockMarkupContinue($Line, array $Block) {
        if (isset($Block['closed'])) {
            return;
        }

        if (preg_match(
            '/^<' . $Block['name'] . '(?: *' . static::regexHtmlAttribute . ')* *>/i',
            $Line['text']
        )) # open
        {
            $Block['depth']++;
        }

        if (preg_match('/(.*?)<\/' . $Block['name'] . '> *$/i', $Line['text'])) # close
        {
            if ($Block['depth'] > 0) {
                $Block['depth']--;
            } else {
                $Block['closed'] = true;
            }
        }

        if (isset($Block['interrupted'])) {
            $Block['markup'] .= "\n";

            unset($Block['interrupted']);
        }

        $Block['markup'] .= "\n" . $Line['body'];

        return $Block;
    }

    #
    # Reference

    protected function blockReference($Line) {
        if (preg_match('/^\[(.+?)]: *<?(\S+?)>?(?: +["\'(](.+)["\')])? *$/', $Line['text'], $matches)) {
            $id = strtolower($matches[1]);

            $Data = [
                'url' => $matches[2],
                'title' => null,
            ];

            if (isset($matches[3])) {
                $Data['title'] = $matches[3];
            }

            $this->DefinitionData['Reference'][$id] = $Data;

            return [
                'hidden' => true,
            ];
        }
    }

    #
    # Table

    protected function blockTable($Line, ?array $Block = null) {
        if (!isset($Block) || isset($Block['type']) || isset($Block['interrupted'])) {
            return;
        }

        if (str_contains($Block['element']['text'], '|') && rtrim($Line['text'], ' -:|') === '') {
            $alignments = [];

            $divider = $Line['text'];

            $divider = trim($divider);
            $divider = trim($divider, '|');

            $dividerCells = explode('|', $divider);

            foreach ($dividerCells as $dividerCell) {
                $dividerCell = trim($dividerCell);

                if ($dividerCell === '') {
                    continue;
                }

                $alignment = null;

                if ($dividerCell[0] === ':') {
                    $alignment = 'left';
                }

                if (str_ends_with($dividerCell, ':')) {
                    $alignment = $alignment === 'left' ? 'center' : 'right';
                }

                $alignments [] = $alignment;
            }

            $HeaderElements = [];

            $header = $Block['element']['text'];

            $header = trim($header);
            $header = trim($header, '|');

            $headerCells = explode('|', $header);

            foreach ($headerCells as $index => $headerCell) {
                $headerCell = trim($headerCell);

                $HeaderElement = [
                    'name' => 'th',
                    'text' => $headerCell,
                    'handler' => 'line',
                ];

                if (isset($alignments[$index])) {
                    $alignment = $alignments[$index];

                    $HeaderElement['attributes'] = [
                        'align' => $alignment,
                    ];
                }

                $HeaderElements [] = $HeaderElement;
            }

            $Block = [
                'alignments' => $alignments,
                'identified' => true,
                'element' => [
                    'name' => 'table',
                    'handler' => 'elements',
                ],
            ];

            $Block['element']['text'] [] = [
                'name' => 'thead',
                'handler' => 'elements',
            ];

            $Block['element']['text'] [] = [
                'name' => 'tbody',
                'handler' => 'elements',
                'text' => [],
            ];

            $Block['element']['text'][0]['text'] [] = [
                'name' => 'tr',
                'handler' => 'elements',
                'text' => $HeaderElements,
            ];

            return $Block;
        }
    }

    protected function blockTableContinue($Line, array $Block) {
        if (isset($Block['interrupted'])) {
            return;
        }

        if ($Line['text'][0] === '|' || strpos($Line['text'], '|')) {
            $Elements = [];

            $row = $Line['text'];

            $row = trim($row);
            $row = trim($row, '|');

            preg_match('/^(?:{(?:\.(?<class>[\w_-]+?))?\s*(#(?<id>[\w_-]+))?})/', $row, $row_ids);
            if (!empty($row_ids[0])) {
                $row = str_replace($row_ids[0], '', $row);
                if (!empty($row_ids['id'])) {
                    $row_id = $row_ids['id'];
                }
                if (!empty($row_ids['class'])) {
                    $row_class = $row_ids['class'];
                }
            }


            preg_match_all('/(?:(\\\\[|])|[^|`]|`[^`]+`|`)+/', $row, $matches);

            foreach ($matches[0] as $index => $cell) {
                $cell = trim($cell);

                $Element = [
                    'name' => 'td',
                    'handler' => 'line',
                    'text' => $cell,
                ];

                if (isset($Block['alignments'][$index])) {
                    $Element['attributes'] = [
                        'align' => $Block['alignments'][$index],
                    ];
                }

                $Elements [] = $Element;
            }

            $Element = [
                'name' => 'tr',
                'handler' => 'elements',
                'text' => $Elements,
            ];
            if (!empty($row_class)) {
                $Element['attributes']['class'] = $row_class;
            }

            if (isset($row_id, $Element['text'][0]['text']) && is_string($Element['text'][0]['text'])) {
                $Element['attributes']['id'] = $row_id;
                $Element['attributes']['class'] = 'anchor';
                $Element['text'][0]['text'] = '<a href="#' . $row_id . '" class="anchor"></a>' . $Element['text'][0]['text'];
            }
            $Block['element']['text'][1]['text'] [] = $Element;

            return $Block;
        }
    }

    protected function paragraph($Line): array {
        return [
            'element' => [
                'name' => 'p',
                'text' => $Line['text'],
                'handler' => 'line',
            ],
        ];
    }

    #
    # Inline Elements
    #


    public function line($text, $nonNestables = []): string {
        $markup = '';

        # $excerpt is based on the first occurrence of a marker

        while ($excerpt = strpbrk($text, $this->inlineMarkerList)) {
            $marker = $excerpt[0];

            $markerPosition = strpos($text, $marker);

            $Excerpt = ['text' => $excerpt, 'context' => $text];

            foreach (static::InlineTypes[$marker] as $inlineType) {
                # check to see if the current inline type is nestable in the current context

                if (!empty($nonNestables) && in_array($inlineType, $nonNestables)) {
                    continue;
                }

                $Inline = $this->{'inline' . $inlineType}($Excerpt);

                if (!isset($Inline)) {
                    continue;
                }

                # makes sure that the inline belongs to "our" marker

                if (isset($Inline['position']) && $Inline['position'] > $markerPosition) {
                    continue;
                }

                # sets a default inline position

                if (!isset($Inline['position'])) {
                    $Inline['position'] = $markerPosition;
                }

                # cause the new element to 'inherit' our non nestables

                foreach ($nonNestables as $non_nestable) {
                    $Inline['element']['nonNestables'][] = $non_nestable;
                }

                # the text that comes before the inline
                $unmarkedText = substr($text, 0, $Inline['position']);

                # compile the unmarked text
                $markup .= $this->unmarkedText($unmarkedText);

                # compile the inline
                $markup .= $Inline['markup'] ?? $this->element($Inline['element']);

                # remove the examined text
                $text = substr($text, $Inline['position'] + $Inline['extent']);

                continue 2;
            }

            # the marker does not belong to an inline

            $unmarkedText = substr($text, 0, $markerPosition + 1);

            $markup .= $this->unmarkedText($unmarkedText);

            $text = substr($text, $markerPosition + 1);
        }

        $markup .= $this->unmarkedText($text);

        return $markup;
    }

    protected function inlineCode($Excerpt) {
        $marker = $Excerpt['text'][0];

        if (preg_match(
            '/^(' . $marker . '+) *(.+?) *(?<!' . $marker . ')\1(?!' . $marker . ')/s',
            $Excerpt['text'],
            $matches
        )) {
            $text = $matches[2];
            $text = preg_replace("/ *\n/", ' ', $text);

            if (isset($text[0]) && $text[0] ==='^') {
                $codexText = substr($text, 1);
                $el = $this->inlineLink([
                    'text' => '[`' . $codexText. '`](%codex%/' . $codexText . ')',
                ]);
                $el['extent'] = strlen($matches[0]);
                return $el;
            }

            return [
                'extent' => strlen($matches[0]),
                'element' => [
                    'name' => 'code',
                    'text' => $text,
                ],
            ];
        }
    }

    protected function inlineEmailTag($Excerpt) {
        if (str_contains($Excerpt['text'], '>') && preg_match(
                '/^<((mailto:)?\S+?@\S+?)>/i',
                $Excerpt['text'],
                $matches
            )) {
            $url = $matches[1];

            if (!isset($matches[2])) {
                $url = 'mailto:' . $url;
            }

            return [
                'extent' => strlen($matches[0]),
                'element' => [
                    'name' => 'a',
                    'text' => $matches[1],
                    'attributes' => [
                        'href' => $url,
                    ],
                ],
            ];
        }
    }

    protected function inlineEmphasis($Excerpt) {
        if (!isset($Excerpt['text'][1])) {
            return;
        }

        $marker = $Excerpt['text'][0];

        if ($Excerpt['text'][1] === $marker && preg_match(static::StrongRegex[$marker], $Excerpt['text'], $matches)) {
            $emphasis = 'strong';
        } elseif (preg_match(static::EmRegex[$marker], $Excerpt['text'], $matches)) {
            $emphasis = 'em';
        } else {
            return;
        }

        return [
            'extent' => strlen($matches[0]),
            'element' => [
                'name' => $emphasis,
                'handler' => 'line',
                'text' => $matches[1],
            ],
        ];
    }

    protected function inlineEscapeSequence($Excerpt) {
        if (isset($Excerpt['text'][1]) && in_array($Excerpt['text'][1], static::specialCharacters)) {
            return [
                'markup' => $Excerpt['text'][1],
                'extent' => 2,
            ];
        }
    }

    protected function inlineImage($Excerpt): ?array {
        if (!isset($Excerpt['text'][1]) || $Excerpt['text'][1] !== '[') {
            return null;
        }

        $Excerpt['text'] = substr($Excerpt['text'], 1);

        $Link = $this->inlineLink($Excerpt);

        if ($Link === null) {
            return null;
        }

        $Inline = [
            'extent' => $Link['extent'] + 1,
            'element' => [
                'name' => 'img',
                'attributes' => [
                    'src' => $Link['element']['attributes']['href'],
                    'alt' => $Link['element']['text'],
                    'loading' => 'lazy',
                    'decoding' => 'async',
                ],
            ],
        ];

        $Inline['element']['attributes'] += $Link['element']['attributes'];

        unset($Inline['element']['attributes']['href']);

        if (!empty($Inline['element']['attributes']['title'])) {
            return [
                'extent' => $Link['extent'] + 1,
                'element' => [
                    'name' => 'figure',
                    'handler' => 'elements',
                    'text' => [
                        [
                            'name' => 'img',
                            'attributes' => [
                                'src' => $Link['element']['attributes']['href'],
                                'alt' => $Link['element']['text'],
                                'loading' => 'lazy',
                                'decoding' => 'async',
                            ],
                        ],
                        [
                            'name' => 'figcaption',
                            'text' => $Inline['element']['attributes']['title'],
                        ],
                    ],
                ],
            ];
        }

        return $Inline;
    }

    protected function inlineLink($Excerpt) {
        $Element = [
            'name' => 'a',
            'handler' => 'line',
            'nonNestables' => ['Url', 'Link'],
            'text' => null,
            'attributes' => [
                'href' => null,
                'title' => null,
            ],
        ];

        $extent = 0;

        $remainder = $Excerpt['text'];

        if (preg_match('/\[((?:[^][]++|(?R))*+)]/', $remainder, $matches)) {
            $Element['text'] = $matches[1];

            $extent += strlen($matches[0]);

            $remainder = substr($remainder, $extent);
        } else {
            return;
        }

        if (preg_match(
            '/^[(]\s*+((?:[^ ()]++|[(][^ )]+[)])++)(?: +("[^"]*"|\'[^\']*\'))?\s*[)]/',
            $remainder,
            $matches
        )) {
            $Element['attributes']['href'] = $matches[1];

            if (isset($matches[2])) {
                $Element['attributes']['title'] = substr($matches[2], 1, -1);
            }

            $extent += strlen($matches[0]);
        } else {
            if (preg_match('/^\s*\[(.*?)]/', $remainder, $matches)) {
                $definition = strlen($matches[1]) ? $matches[1] : $Element['text'];
                $definition = strtolower($definition);

                $extent += strlen($matches[0]);
            } else {
                $definition = strtolower($Element['text']);
            }

            if (!isset($this->DefinitionData['Reference'][$definition])) {
                return;
            }

            $Definition = $this->DefinitionData['Reference'][$definition];

            $Element['attributes']['href'] = $Definition['url'];
            $Element['attributes']['title'] = $Definition['title'];
        }

        return [
            'extent' => $extent,
            'element' => $Element,
        ];
    }

    protected function inlineMarkup($Excerpt) {
        if ($this->markupEscaped || $this->safeMode || !str_contains($Excerpt['text'], '>')) {
            return;
        }

        if ($Excerpt['text'][1] === '/' && preg_match('/^<\/\w[\w-]* *>/', $Excerpt['text'], $matches)) {
            return [
                'markup' => $matches[0],
                'extent' => strlen($matches[0]),
            ];
        }

        if ($Excerpt['text'][1] === '!' && preg_match('/^<!---?[^>-](?:-?[^-])*-->/', $Excerpt['text'], $matches)) {
            return [
                'markup' => $matches[0],
                'extent' => strlen($matches[0]),
            ];
        }

        if ($Excerpt['text'][1] !== ' ' && preg_match(
                '/^<\w[\w-]*(?: *' . static::regexHtmlAttribute . ')* *\/?>/s',
                $Excerpt['text'],
                $matches
            )) {
            return [
                'markup' => $matches[0],
                'extent' => strlen($matches[0]),
            ];
        }
    }

    protected function inlineSpecialCharacter($Excerpt) {
        if ($Excerpt['text'][0] === '&' && !preg_match('/^&#?\w+;/', $Excerpt['text'])) {
            return [
                'markup' => '&amp;',
                'extent' => 1,
            ];
        }

        $SpecialCharacter = ['>' => 'gt', '<' => 'lt', '"' => 'quot'];

        if (isset($SpecialCharacter[$Excerpt['text'][0]])) {
            return [
                'markup' => '&' . $SpecialCharacter[$Excerpt['text'][0]] . ';',
                'extent' => 1,
            ];
        }
    }

    protected function inlineStrikethrough($Excerpt) {
        if (!isset($Excerpt['text'][1])) {
            return;
        }

        if ($Excerpt['text'][1] === '~' && preg_match('/^~~(?=\S)(.+?)(?<=\S)~~/', $Excerpt['text'], $matches)) {
            return [
                'extent' => strlen($matches[0]),
                'element' => [
                    'name' => 'del',
                    'text' => $matches[1],
                    'handler' => 'line',
                ],
            ];
        }
    }

    protected function inlineUrl($Excerpt) {
        if ($this->urlsLinked !== true || !isset($Excerpt['text'][2]) || $Excerpt['text'][2] !== '/') {
            return;
        }

        if (preg_match('/\bhttps?:\/{2}[^\s<]+\b\/*/ui', $Excerpt['context'], $matches, PREG_OFFSET_CAPTURE)) {
            $url = $matches[0][0];

            return [
                'extent' => strlen($matches[0][0]),
                'position' => $matches[0][1],
                'element' => [
                    'name' => 'a',
                    'text' => $url,
                    'attributes' => [
                        'href' => $url,
                    ],
                ],
            ];
        }
    }

    protected function inlineUrlTag($Excerpt) {
        if (str_contains($Excerpt['text'], '>') && preg_match(
                '/^<(\w+:\/{2}[^ >]+)>/',
                $Excerpt['text'],
                $matches
            )) {
            $url = $matches[1];

            return [
                'extent' => strlen($matches[0]),
                'element' => [
                    'name' => 'a',
                    'text' => $url,
                    'attributes' => [
                        'href' => $url,
                    ],
                ],
            ];
        }
    }

    protected function unmarkedText($text): string {
        if ($this->breaksEnabled) {
            $text = preg_replace('/ *\n/', "<br />\n", $text);
        } else {
            $text = preg_replace('/(?:  +| *\\\\)\n/', "<br />\n", $text);
            $text = str_replace(" \n", "\n", $text);
        }

        return $text;
    }

    #
    # Handlers
    #

    protected function element(array $Element): string {
        if ($this->safeMode) {
            $Element = $this->sanitiseElement($Element);
        }

        $markup = '<' . $Element['name'];

        if (isset($Element['attributes'])) {
            foreach ($Element['attributes'] as $name => $value) {
                if ($value === null) {
                    continue;
                }

                $markup .= ' ' . $name . '="' . self::escape($value) . '"';
            }
        }

        $permitRawHtml = false;

        if (isset($Element['text'])) {
            $text = $Element['text'];
        }
        // very strongly consider an alternative if you're writing an
        // extension
        elseif (isset($Element['rawHtml'])) {
            $text = $Element['rawHtml'];
            $allowRawHtmlInSafeMode = isset($Element['allowRawHtmlInSafeMode']) && $Element['allowRawHtmlInSafeMode'];
            $permitRawHtml = !$this->safeMode || $allowRawHtmlInSafeMode;
        }

        if (isset($text)) {
            $markup .= '>';

            if (!isset($Element['nonNestables'])) {
                $Element['nonNestables'] = [];
            }

            if (isset($Element['handler'])) {
                $markup .= $this->{$Element['handler']}($text, $Element['nonNestables']);
            } elseif (!$permitRawHtml) {
                $markup .= self::escape($text, true);
            } else {
                $markup .= $text;
            }

            $markup .= '</' . $Element['name'] . '>';
        } else {
            $markup .= ' />';
        }

        return $markup;
    }

    protected function elements(array $Elements): string {
        $markup = '';

        foreach ($Elements as $Element) {
            $markup .= "\n" . $this->element($Element);
        }

        $markup .= "\n";

        return $markup;
    }

    protected function li($lines) {
        $markup = $this->lines($lines);

        $trimmedMarkup = trim($markup);

        if (!in_array('', $lines) && str_starts_with($trimmedMarkup, '<p>')) {
            $markup = $trimmedMarkup;
            $markup = substr($markup, 3);

            $position = strpos($markup, "</p>");

            $markup = substr_replace($markup, '', $position, 4);
        }

        return $markup;
    }

    #
    # Deprecated Methods
    #

    protected function sanitiseElement(array $Element): array {
        static $goodAttribute = '/^[a-zA-Z0-9][a-zA-Z0-9-_]*+$/';
        static $safeUrlNameToAtt = [
            'a' => 'href',
            'img' => 'src',
        ];

        if (isset($safeUrlNameToAtt[$Element['name']])) {
            $Element = $this->filterUnsafeUrlInAttribute($Element, $safeUrlNameToAtt[$Element['name']]);
        }

        if (!empty($Element['attributes'])) {
            foreach ($Element['attributes'] as $att => $val) {
                # filter out badly parsed attribute
                if (!preg_match($goodAttribute, $att)) {
                    unset($Element['attributes'][$att]);
                } # dump onevent attribute
                elseif (self::striAtStart($att, 'on')) {
                    unset($Element['attributes'][$att]);
                }
            }
        }

        return $Element;
    }

    protected function filterUnsafeUrlInAttribute(array $Element, $attribute): array {
        foreach (static::safeLinksWhitelist as $scheme) {
            if (self::striAtStart($Element['attributes'][$attribute], $scheme)) {
                return $Element;
            }
        }

        $Element['attributes'][$attribute] = str_replace(':', '%3A', $Element['attributes'][$attribute]);

        return $Element;
    }

    #
    # Static Methods
    #

    protected static function escape($text, $allowQuotes = false): string {
        return htmlspecialchars($text, $allowQuotes ? ENT_NOQUOTES : ENT_QUOTES, 'UTF-8');
    }

    protected static function striAtStart($string, $needle): ?bool {
        $len = strlen($needle);

        if ($len > strlen($string)) {
            return false;
        }

        return stripos($string, strtolower($needle)) === 0;
    }

    public static function instance($name = 'default') {
        if (isset(self::$instances[$name])) {
            return self::$instances[$name];
        }

        $instance = new static();

        self::$instances[$name] = $instance;

        return $instance;
    }

}
