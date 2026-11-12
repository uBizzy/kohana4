<?php
/**
 * Markdown parser of the user guide.
 *
 * It understands the part of Markdown that the guide pages and the
 * comments in the code use, as GitHub shows it:
 *
 * - headings (`# Title`, or a line of `===` / `---` under the title)
 * - paragraphs, `**bold**`, `*emphasis*`, `~~struck~~`, `` `code` ``
 * - links `[text](page)`, images `![text](file.png)`, `<https://...>`
 * - lists (`-`, `*`, `+`, `1.`), also inside each other
 * - code blocks: fenced with three backticks or tildes, or indented
 * - tables, quotes (`>`), lines (`---`)
 *
 * and two things of its own:
 *
 * - `[!!] text` is a note
 * - `[Class]`, `[Class::method]` and `[Class::$property]` link to the API browser
 *
 * HTML in the text is not passed on: it is shown as text.
 *
 *     $html = Kodoc_Markdown::markdown($text);
 *
 * @package    Kohana/Userguide
 * @category   Base
 * @copyright  (c) since 2026 Konine Team
 * @license    https://github.com/uBizzy/kohana4/blob/main/LICENSE.md
 */
class Kohana_Kodoc_Markdown {

	/**
	 * @var  string  put before the address of links to other pages
	 */
	public static string $base_url = '';

	/**
	 * @var  string  put before the address of images
	 */
	public static string $image_url = '';

	/**
	 * @var  array<int, array<string, mixed>>  headings of the last text that was parsed
	 */
	protected static array $_toc = [];

	/**
	 * @var  array<string, mixed>  ids already given to headings of the text being parsed
	 */
	protected static array $_ids = [];

	/**
	 * @var  array<int|string, string>  pieces of finished HTML, kept out of the text while the rest is parsed
	 */
	protected static array $_kept = [];

	/**
	 * Converts Markdown text to HTML.
	 *
	 *     echo Kodoc_Markdown::markdown("# Title\n\nSome *text*.");
	 *
	 * @param   ?string  $text  Markdown text
	 * @return  string   HTML
	 */
	public static function markdown(?string $text): string
	{
		static::$_toc = static::$_ids = static::$_kept = [];

		// The same line endings everywhere
		$text = str_replace(["\r\n", "\r"], "\n", (string) $text);

		return static::_blocks(explode("\n", $text));
	}

	/**
	 * Returns the headings of the text parsed last, to build the "on this
	 * page" list: each has `level`, `name` and `id`.
	 *
	 * @return  array<int, array<string, mixed>>
	 */
	public static function toc(): array
	{
		return static::$_toc;
	}

	/**
	 * Converts lines of text to HTML, one block (paragraph, list, table,
	 * code block, ...) after the other.
	 *
	 * @param   array<int, string>  $lines  lines of Markdown
	 * @return  string
	 */
	protected static function _blocks(array $lines): string
	{
		$html = [];
		$count = count($lines);

		for ($i = 0; $i < $count; $i++)
		{
			$line = $lines[$i];

			if (trim($line) === '')
				continue;

			// Code between two lines of ``` or ~~~
			if (preg_match('/^(```+|~~~+)\s*([\w-]*)\s*$/', $line, $match))
			{
				$code = [];

				for ($i++; $i < $count AND ! str_starts_with(rtrim($lines[$i]), $match[1]); $i++)
				{
					$code[] = $lines[$i];
				}

				$html[] = static::_code(implode("\n", $code), $match[2]);
				continue;
			}

			// Code indented with a tab or four spaces
			if (preg_match('/^(\t| {4})/', $line))
			{
				$code = [];

				for (; $i < $count AND (trim($lines[$i]) === '' OR preg_match('/^(\t| {4})/', $lines[$i])); $i++)
				{
					$code[] = (string) preg_replace('/^(\t| {4})/', '', $lines[$i]);
				}

				$i--;
				$html[] = static::_code(rtrim(implode("\n", $code)), '');
				continue;
			}

			// # Heading
			if (preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*(?:\{#([\w-]+)\})?\s*$/', $line, $match))
			{
				$html[] = static::_heading(strlen($match[1]), $match[2], $match[3] ?? '');
				continue;
			}

			// Heading with a line of === or --- under it
			if (isset($lines[$i + 1]) AND preg_match('/^(=+|-+)\s*$/', $lines[$i + 1], $match) AND ! static::_starts_block($line))
			{
				$html[] = static::_heading(($match[1][0] === '=') ? 1 : 2, trim($line), '');
				$i++;
				continue;
			}

			// A line across the page
			if (preg_match('/^ {0,3}([-*_])( *\1){2,} *$/', $line))
			{
				$html[] = '<hr>';
				continue;
			}

			// > Quote
			if (str_starts_with($line, '>'))
			{
				$quote = [];

				for (; $i < $count AND trim($lines[$i]) !== ''; $i++)
				{
					$quote[] = (string) preg_replace('/^> ?/', '', $lines[$i]);
				}

				$html[] = "<blockquote>\n".static::_blocks($quote)."\n</blockquote>";
				continue;
			}

			// Table: a line with "|" and under it a line like |---|---|
			if (str_contains($line, '|') AND isset($lines[$i + 1]) AND preg_match('/^\s*\|?\s*:?-+:?\s*(\|\s*:?-+:?\s*)*\|?\s*$/', $lines[$i + 1]))
			{
				$rows = [];

				for (; $i < $count AND str_contains($lines[$i], '|'); $i++)
				{
					$rows[] = $lines[$i];
				}

				$i--;
				$html[] = static::_table($rows);
				continue;
			}

			// List
			if (static::_list_marker($line) !== null)
			{
				$items = [];

				for (; $i < $count; $i++)
				{
					// The list ends at a line that is not empty, not indented and not an item
					if (trim($lines[$i]) !== '' AND ! preg_match('/^(\t| {2,})/', $lines[$i]) AND static::_list_marker($lines[$i]) === null)
					{
						// ...unless it simply continues the text of the item above
						if (trim($lines[$i - 1]) === '' OR static::_starts_block($lines[$i]))
							break;
					}

					$items[] = $lines[$i];
				}

				$i--;
				$html[] = static::_list($items);
				continue;
			}

			// Everything else is a paragraph, up to an empty line or the start of another block
			$text = [$line];

			while (isset($lines[$i + 1]) AND trim($lines[$i + 1]) !== '' AND ! static::_starts_block($lines[$i + 1]))
			{
				$text[] = $lines[++$i];
			}

			$text = implode("\n", $text);

			if (preg_match('/^\[!!\]\s*(.*)$/s', $text, $match))
			{
				$html[] = '<p class="note">'.static::_inline($match[1]).'</p>';
			}
			else
			{
				$html[] = '<p>'.static::_inline($text).'</p>';
			}
		}

		return implode("\n", $html);
	}

	/**
	 * Tells whether a line starts something that is not plain text, so
	 * that a paragraph ends before it.
	 *
	 * @param   string  $line  line of Markdown
	 * @return  bool
	 */
	protected static function _starts_block(string $line): bool
	{
		return (bool) preg_match('/^(#{1,6}\s|```|~~~|>| {0,3}([-*_])( *\2){2,} *$)/', $line)
			OR static::_list_marker($line) !== null;
	}

	/**
	 * Returns the kind of list a line starts ("ul" or "ol"), or null when
	 * the line is not a list item.
	 *
	 * @param   string  $line  line of Markdown
	 * @return  ?string
	 */
	protected static function _list_marker(string $line): ?string
	{
		if (preg_match('/^ {0,3}(?:([-*+])|\d+\.)\s+\S/', $line, $match))
			return isset($match[1]) ? 'ul' : 'ol';

		return null;
	}

	/**
	 * Converts the lines of a list to HTML. The text of each item is
	 * parsed as blocks again, so an item can hold paragraphs, code and
	 * other lists.
	 *
	 * @param   array<int, string>  $lines  lines of the list
	 * @return  string
	 */
	protected static function _list(array $lines): string
	{
		$type = static::_list_marker($lines[0]);
		$items = [];

		// An item of this list starts where the first one does; an item
		// that starts two or more spaces further in belongs to a list inside it
		$indent = strspn($lines[0], ' ');

		foreach ($lines as $line)
		{
			if (preg_match('/^( {0,3})(?:[-*+]|\d+\.)\s+(.*)$/', $line, $match) AND strlen($match[1]) < $indent + 2)
			{
				// A new item
				$items[] = [$match[2]];
			}
			else
			{
				// More of the current item: take one level of indentation away
				$items[count($items) - 1][] = (string) preg_replace('/^(\t| {1,4})/', '', $line);
			}
		}

		$html = [];

		foreach ($items as $item)
		{
			// Empty lines at the end belong to nothing
			while ($item AND trim(end($item)) === '')
			{
				array_pop($item);
			}

			$content = static::_blocks($item);

			if (substr_count($content, '<p>') === 1 AND str_starts_with($content, '<p>') AND ! in_array('', array_map(fn ($line) => trim((string) $line), $item), true))
			{
				// One piece of text: no paragraph around it
				$content = (string) preg_replace('~^<p>(.*?)</p>~s', '$1', $content, 1);
			}

			$html[] = '<li>'.$content.'</li>';
		}

		return "<$type>\n".implode("\n", $html)."\n</$type>";
	}

	/**
	 * Converts the lines of a table to HTML. The first line holds the
	 * column titles, the second (`|---|:--:|--:|`) how each column is aligned.
	 *
	 * @param   array<int, string>  $lines  lines of the table
	 * @return  string
	 */
	protected static function _table(array $lines): string
	{
		// Cells of a line: "|" inside `code` or written as "\|" does not split
		$cells = function (string $line): array
		{
			$line = (string) preg_replace_callback('/`[^`]*`/', fn ($m) => str_replace('|', "\x1A", $m[0]), $line);
			$line = str_replace('\|', "\x1A", trim($line));
			$line = (string) preg_replace('/^\||\|$/', '', $line);

			return array_map(fn ($cell) => str_replace("\x1A", '|', trim($cell)), explode('|', $line));
		};

		$align = [];

		foreach ($cells($lines[1]) as $n => $rule)
		{
			$align[$n] = match (true)
			{
				str_starts_with($rule, ':') AND str_ends_with($rule, ':') => ' class="center"',
				str_ends_with($rule, ':') => ' class="right"',
				default => '',
			};
		}

		$row = function (array $cells, string $tag) use ($align): string
		{
			$html = '';

			foreach ($cells as $n => $cell)
			{
				$html .= "<$tag".($align[$n] ?? '').'>'.static::_inline(is_string($cell) ? $cell : '')."</$tag>";
			}

			return '<tr>'.$html.'</tr>';
		};

		$body = [];

		foreach (array_slice($lines, 2) as $line)
		{
			$body[] = $row($cells($line), 'td');
		}

		return "<table>\n<thead>\n".$row($cells($lines[0]), 'th')."\n</thead>\n<tbody>\n".implode("\n", $body)."\n</tbody>\n</table>";
	}

	/**
	 * Makes a heading, gives it an id and adds it to the list of headings
	 * of the page.
	 *
	 * @param   int     $level  1 to 6
	 * @param   string  $text   text of the heading
	 * @param   string  $id     id given in the text as `{#id}`, or ''
	 * @return  string
	 */
	protected static function _heading(int $level, string $text, string $id): string
	{
		$html = static::_inline($text);
		$name = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');

		if ($id === '')
		{
			$id = URL::title($name, '-', true) ?: 'section';
		}

		// The same title twice on a page: title, title-2, title-3
		for ($unique = $id, $n = 2; isset(static::$_ids[$unique]); $n++)
		{
			$unique = $id.'-'.$n;
		}

		static::$_ids[$unique] = true;
		static::$_toc[] = ['level' => $level, 'name' => $name, 'id' => $unique];

		return "<h$level id=\"$unique\">$html</h$level>";
	}

	/**
	 * Makes a block of code. PHP code is coloured.
	 *
	 * @param   string  $code      the code
	 * @param   string  $language  language named after the opening ```, or ''
	 * @return  string
	 */
	protected static function _code(string $code, string $language): string
	{
		$language = strtolower($language);

		// A block without a language is taken as PHP when it looks like PHP
		if ($language === 'php' OR ($language === '' AND preg_match('/\$\w|::|->|<\?php/', $code)))
		{
			return '<pre><code class="language-php">'.static::_highlight($code).'</code></pre>';
		}

		return '<pre><code'.($language ? ' class="language-'.$language.'"' : '').'>'
			.htmlspecialchars($code, ENT_NOQUOTES, 'UTF-8').'</code></pre>';
	}

	/**
	 * Colours PHP code with PHP's own highlighter. The colours are class
	 * names (`hl-keyword`, `hl-string`, `hl-comment`, `hl-default`,
	 * `hl-html`) that the style sheet of the guide defines.
	 *
	 * @param   string  $code  PHP code, with or without the opening tag
	 * @return  string  HTML
	 */
	protected static function _highlight(string $code): string
	{
		// The highlighter only colours what comes after an opening tag
		$tagged = (str_contains($code, '<?php') OR str_contains($code, '<?='));

		$old = [];

		foreach (['keyword', 'string', 'comment', 'default', 'html'] as $kind)
		{
			// The "colour" is the name of a class, replaced below
			$old[$kind] = ini_set('highlight.'.$kind, 'hl-'.$kind);
		}

		$html = highlight_string($tagged ? $code : "<?php\n".$code, true);

		foreach ($old as $kind => $colour)
		{
			ini_set('highlight.'.$kind, (string) $colour);
		}

		// Only the coloured text is wanted, with classes and not style attributes
		$html = (string) preg_replace(['~^<pre><code[^>]*>~', '~</code></pre>$~'], '', trim($html));
		$html = (string) preg_replace('/style="color: (hl-\w+);?"/', 'class="$1"', $html);

		if ( ! $tagged)
		{
			// Take the opening tag that was added away again
			$html = (string) preg_replace('/&lt;\?php\n/', '', $html, 1);
		}

		return $html;
	}

	/**
	 * Converts the text inside a block: code, links, images, bold and
	 * emphasis. Everything else is escaped.
	 *
	 * @param   string  $text  Markdown text
	 * @return  string  HTML
	 */
	protected static function _inline(string $text): string
	{
		// `code`: kept aside, nothing inside it is Markdown
		$text = (string) preg_replace_callback('/(`+)(.+?)\1/s', fn ($m) => static::_keep('<code>'.htmlspecialchars(trim($m[2]), ENT_NOQUOTES, 'UTF-8').'</code>'), $text);

		// \* is a plain *
		$text = (string) preg_replace_callback('/\\\\([\\\\`*_{}\[\]()#+.!|~<>-])/', fn ($m) => static::_keep(htmlspecialchars($m[1], ENT_NOQUOTES, 'UTF-8')), $text);

		// <https://example.com>
		$text = (string) preg_replace_callback('~<(https?://[^\s<>]+)>~', fn ($m) => static::_keep(static::_link($m[1], htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8'), '')), $text);

		$text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');

		// ![text](image.png "title")
		$text = (string) preg_replace_callback('/!\[([^\]]*)\]\(\s*(\S+?)(?:\s+"([^"]*)")?\s*\)/', function ($m) {
			$src = preg_match('~^(\w+:)?//|^/~', $m[2]) ? $m[2] : static::$image_url.$m[2];

			return static::_keep('<img src="'.htmlspecialchars(html_entity_decode($src), ENT_QUOTES, 'UTF-8').'" alt="'.str_replace('"', '&quot;', $m[1]).'"'
				.(isset($m[3]) ? ' title="'.str_replace('"', '&quot;', $m[3]).'"' : '').'>');
		}, $text);

		// [text](page "title"): the text may hold code, bold and emphasis
		$text = (string) preg_replace_callback('/\[((?:[^\[\]]|\[[^\[\]]*\])+)\]\(\s*(\S*?)(?:\s+"([^"]*)")?\s*\)/', fn ($m) => static::_keep(static::_link(html_entity_decode($m[2]), static::_emphasis($m[1]), $m[3] ?? '')), $text);

		// [Class], [Class::method] and [Class::$property] link to the API browser
		$text = (string) preg_replace_callback('/\[(([A-Z]\w*)(?:::(\$?\w+))?(?:\(\))?)\]/', function ($m) {
			if ( ! class_exists($m[2]) AND ! interface_exists($m[2]))
			{
				// Not a class: plain text in brackets
				return $m[0];
			}

			return static::_keep(Kodoc::link_class_member($m));
		}, $text);

		$text = static::_emphasis($text);

		// Two spaces at the end of a line start a new line
		$text = (string) preg_replace('/ {2,}\n/', "<br>\n", $text);

		// Put the kept pieces back (a kept piece can hold other kept pieces)
		while (preg_match('/\x02(\d+)\x03/', $text))
		{
			$text = (string) preg_replace_callback('/\x02(\d+)\x03/', fn ($m) => static::$_kept[$m[1]], $text);
		}

		return $text;
	}

	/**
	 * Converts `**bold**`, `*emphasis*`, `_emphasis_` and `~~struck~~`.
	 *
	 * @param   string  $text  escaped text
	 * @return  string
	 */
	protected static function _emphasis(string $text): string
	{
		$text = (string) preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/s', '<strong>$1</strong>', $text);
		$text = (string) preg_replace('/(?<![\w_])__(?=\S)(.+?)(?<=\S)__(?![\w_])/s', '<strong>$1</strong>', $text);
		$text = (string) preg_replace('/\*(?=\S)(.+?)(?<=\S)\*/s', '<em>$1</em>', $text);

		// An underscore inside a word (some_name) is not emphasis
		$text = (string) preg_replace('/(?<![\w_])_(?=\S)(.+?)(?<=\S)_(?![\w_])/s', '<em>$1</em>', $text);

		return (string) preg_replace('/~~(?=\S)(.+?)(?<=\S)~~/s', '<del>$1</del>', $text);
	}

	/**
	 * Makes a link. An address without a scheme is a page of the guide:
	 * [Kodoc_Markdown::$base_url] is put before it, and an ending `.md`
	 * is taken away, so that the same link works here and on GitHub.
	 *
	 * @param   string  $url    address
	 * @param   string  $html   HTML shown as the link
	 * @param   string  $title  text shown when the pointer is over the link, or ''
	 * @return  string
	 */
	protected static function _link(string $url, string $html, string $title): string
	{
		$external = (bool) preg_match('~^(https?:)?//|^mailto:~', $url);

		if ( ! $external AND preg_match('/^\s*[\w+.-]+:/', $url))
		{
			// No javascript: or data: addresses
			$url = '#';
		}
		elseif ( ! $external AND ! str_starts_with($url, '#') AND ! str_starts_with($url, '/'))
		{
			// "index" is the first page of a guide: its address is the directory itself
			$url = (string) preg_replace(['/\.md(?=#|$)/', '~(^|/)index(?=#|$)~'], ['', '$1'], $url);

			$url = rtrim(static::$base_url.$url, '/');
		}

		return '<a href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'"'
			.($title !== '' ? ' title="'.str_replace('"', '&quot;', $title).'"' : '')
			.($external ? ' rel="noopener"' : '').'>'.$html.'</a>';
	}

	/**
	 * Keeps a piece of finished HTML out of the text, so that later steps
	 * do not change it. Returns the marker that stands in its place.
	 *
	 * @param   string  $html  finished HTML
	 * @return  string
	 */
	protected static function _keep(string $html): string
	{
		static::$_kept[] = $html;

		return "\x02".(count(static::$_kept) - 1)."\x03";
	}

}
