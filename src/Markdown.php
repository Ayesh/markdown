<?php

namespace Ayesh\Markdown;

use Parsedown;
class Markdown extends Parsedown {

    protected function inlineImage($Excerpt): ?array {
		if (!isset($Excerpt['text'][1]) || $Excerpt['text'][1] !== '[') {
			return null;
		}

        $Excerpt['text']= substr($Excerpt['text'], 1);

        $Link = $this->inlineLink($Excerpt);

        if ($Link === null)
        {
            return null;
        }

        $Inline = array(
            'extent' => $Link['extent'] + 1,
            'element' => array(
                'name' => 'img',
                'attributes' => array(
                    'src' => $Link['element']['attributes']['href'],
                    'alt' => $Link['element']['text'],
                    'loading' => 'lazy',
                    'decoding' => 'async',
                ),
            ),
        );

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
						    'text' => $Inline['element']['attributes']['title']
					    ],
				    ],
			    ],
		    ];
	    }

        return $Inline;
    }

    protected function blockFencedCode($Line): ?array {
        if (!preg_match('/^['.$Line['text'][0].']{3,}[ ]*([^`]+)?[ ]*$/', $Line['text'], $matches)) {
            return null;
        }

        $Element = array(
            'name' => 'code',
            'text' => '',
        );

        $extra_attributes = [];

        if (isset($matches[1]))
        {
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

            $class = 'language-'.$language;
            $extras = str_replace($language, '', $matches[1]);
            if ($extras) {
                $extra_attributes['class'] = $extras;
            }
            $Element['attributes'] = array(
                'class' => $class,
            );
        }

        return array(
            'char' => $Line['text'][0],
            'element' => array(
                'name' => 'pre',
                'handler' => 'element',
                'text' => $Element,
                'attributes' => $extra_attributes,
            ),
        );
    }

    protected function blockHeader($Line): ?array {
        if (!isset($Line['text'][1])) {
            return null;
        }

        $level = 1;

        while (isset($Line['text'][$level]) and $Line['text'][$level] === '#')
        {
            $level ++;
        }

        if ($level > 6)
        {
            return null;
        }

        $text = trim($Line['text'], '# ');
        $h_id = null;
        if (preg_match('/^{([\w_-]+)}/', $text, $matches)) {
            $h_id = $matches[1];
            $text = '<a href="#'.$matches[1].'" class="anchor">'. str_replace($matches[0], '', trim($text)) . '</a>';
        }
        elseif (preg_match('/{#([A-z\d_.-]+)}$/', $text, $matches)) {
            $h_id = $matches[1];
            $text = '<a href="#'.$matches[1].'" class="anchor">'. str_replace($matches[0], '', trim($text)) . '</a>';
        }

        return array(
            'element' => array(
                'name' => 'h' . min(6, $level),
                'text' => $text,
                'handler' => 'line',
                'attributes' => [
                    'id' => $h_id,
                ],
            ),
        );
    }

    protected function blockTable($Line, array $Block = null)
    {
        if ( ! isset($Block) or isset($Block['type']) or isset($Block['interrupted']))
        {
            return;
        }

        if (strpos($Block['element']['text'], '|') !== false and rtrim($Line['text'], ' -:|') === '')
        {
            $alignments = array();

            $divider = $Line['text'];

            $divider = trim($divider);
            $divider = trim($divider, '|');

            $dividerCells = explode('|', $divider);

            foreach ($dividerCells as $dividerCell)
            {
                $dividerCell = trim($dividerCell);

                if ($dividerCell === '')
                {
                    continue;
                }

                $alignment = null;

                if ($dividerCell[0] === ':')
                {
                    $alignment = 'left';
                }

                if (substr($dividerCell, - 1) === ':')
                {
                    $alignment = $alignment === 'left' ? 'center' : 'right';
                }

                $alignments []= $alignment;
            }

            # ~

            $HeaderElements = array();

            $header = $Block['element']['text'];

            $header = trim($header);
            $header = trim($header, '|');

            $headerCells = explode('|', $header);

            foreach ($headerCells as $index => $headerCell)
            {
                $headerCell = trim($headerCell);

                $HeaderElement = array(
                    'name' => 'th',
                    'text' => $headerCell,
                    'handler' => 'line',
                );

                if (isset($alignments[$index]))
                {
                    $alignment = $alignments[$index];

                    $HeaderElement['attributes'] = array(
                        'align' => $alignment,
                    );
                }

                $HeaderElements []= $HeaderElement;
            }

            # ~

            $Block = array(
                'alignments' => $alignments,
                'identified' => true,
                'element' => array(
                    'name' => 'table',
                    'handler' => 'elements',
                ),
            );

            $Block['element']['text'] []= array(
                'name' => 'thead',
                'handler' => 'elements',
            );

            $Block['element']['text'] []= array(
                'name' => 'tbody',
                'handler' => 'elements',
                'text' => array(),
            );

            $Block['element']['text'][0]['text'] []= array(
                'name' => 'tr',
                'handler' => 'elements',
                'text' => $HeaderElements,
            );

            return $Block;
        }
    }

    protected function blockTableContinue($Line, array $Block)
    {
        if (isset($Block['interrupted']))
        {
            return;
        }

        if ($Line['text'][0] === '|' or strpos($Line['text'], '|'))
        {
            $Elements = array();

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

            foreach ($matches[0] as $index => $cell)
            {
                $cell = trim($cell);

                $Element = array(
                    'name' => 'td',
                    'handler' => 'line',
                    'text' => $cell,
                );

                if (isset($Block['alignments'][$index]))
                {
                    $Element['attributes'] = array(
                        'align' => $Block['alignments'][$index],
                    );
                }

                $Elements []= $Element;
            }

            $Element = array(
                'name' => 'tr',
                'handler' => 'elements',
                'text' => $Elements,
            );
            if (!empty($row_class)) {
                $Element['attributes']['class'] = $row_class;
            }

            if (isset($row_id, $Element['text'][0]['text']) && is_string($Element['text'][0]['text'])) {
                $Element['attributes']['id'] = $row_id;
                $Element['attributes']['class'] = 'anchor';
                $Element['text'][0]['text'] = '<a href="#' . $row_id . '" class="anchor"></a>' . $Element['text'][0]['text'];
            }
            $Block['element']['text'][1]['text'] []= $Element;

            return $Block;
        }
    }

    protected function blockQuote($Line) {
        if (preg_match('/^>[ ]?(.*)/', $Line['text'], $matches)) {
            $attr = [];
            if (preg_match('/^{\.([\w_-]+)}/', $matches[1], $class_attr)) {
                $matches[1] = str_replace($class_attr[0], '', $matches[1]);
                $attr['class'] = $class_attr[1];
            }

            $Block = array(
                'element' => array(
                    'name' => 'blockquote',
                    'handler' => 'lines',
                    'text' => (array) $matches[1],
                    'attributes' => $attr,
                ),
            );

            return $Block;
        }
    }

	protected function blockRule($Line)
	{
		if (preg_match('/^(['.$Line['text'][0].'])([ ]*\1){2,}[ ]*$/', $Line['text']))
		{
			$Block = array(
				'element' => array(
					'name' => 'hr'
				),
			);

			if ($Line['text'] === '***') {
				$Block['element']['attributes'] = array(
					'class' => 'type-minor',
				);
			}

			return $Block;
		}
	}
}
