<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Markdown reader of the designer's Markdown component: the site's
 * pg_markdown_html() (includes/fn/markdown.php) and its browser twin
 * window.PGMarkdown.html() (assets/js/pg_markdown.js). Headings, lists,
 * quotes, code, tables, pictures and links are drawn; no HTML the author
 * wrote passes, and a link or a picture keeps only a safe address. The
 * twin is run in Node on the same texts and must answer the same markup;
 * that part is skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (is_file(PG_FUNCTIONS_DIR . '/includes/fn/markdown.php')) {
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/markdown.php');
}

function markdown_test_loaded()
{
	pg_assert_true(function_exists('pg_markdown_html'), 'includes/fn/markdown.php is there');

	return function_exists('pg_markdown_html');
}

// The texts both readers are given; the hostile ones included.
function markdown_test_samples()
{
	return array(
		"# Title\n\nSome **bold**, *italic*, __strong__, _em_, ~~gone~~ and `a < b` text.",
		"## Closed heading ##\n### Third\n#### Fourth\n##### Fifth\n###### Sixth\n####### Not a heading",
		"Line one  \nline two\\\nline three\nline four",
		"- one\n- two\n  - nested *a*\n  - nested b\n- three\n\n1. first\n2. second",
		"3. starts at three\n4. four\n\nAfter the list.",
		"> A quote\n> with **two** lines\n>\n> - and a list",
		"```php\n<?php echo '<b>'; ?>\n```\n\n~~~\nplain & simple\n~~~",
		"| Name | Price | Note |\n|:--|:-:|--:|\n| Tea | 2 | **hot** |\n| Cake \\| pie | 3 |\n\nAfter.",
		"![A cat](https://example.com/cat.png \"Cat\") and ![rel](/files/a.jpg)",
		"[Site](https://pinegrap.com) [Mail](mailto:a@b.c) [Phone](tel:+90123) [Page](/about) [Anchor](#top) [Rel](page.html?a=1&b=2)",
		"<https://example.com/a?b=1&c=2> and <mailto:x@y.z>",
		"***\n\n---\n\n_ _ _",
		"<script>alert(1)</script>\n\n<img src=x onerror=alert(1)>",
		"<b onclick=\"alert(1)\">x</b> &amp; &lt;b&gt; &#106;avascript: \" '",
		"[x](javascript:alert(1)) [y](JAVASCRIPT:alert(1)) [z](java\tscript:alert(1)) [w](vbscript:msgbox) [d](data:text/html,<b>)",
		"![p](javascript:alert(1)) ![q](data:image/png;base64,AAAA) ![r](mailto:a@b.c)",
		"[ok](https://a.b/\"onmouseover=\"alert(1)) [t](https://a.b \"a <b> title\") [u](https://a.b\" onclick=\"x)",
		"[&#106;avascript](&#106;avascript:alert(1)) [e](jav&#x61;script:alert(1))",
		"\\*not em\\* and \\[not a link\\](x) and \\<b\\>",
		"Text with a lone * star and snake_case_word and 2*3*4.",
		"",
		"   \n\n  ",
		"| a |\n| - |\n| <i>b</i> |",
		"- [link](https://x.y) in **item**\n- ![pic](https://x.y/p.png)",
		"Para\n# Heading after para\n- item after heading\nlazy line",
		"\x01\x02\x03 control \x0B characters",
		"Ünïcödé **çok güzel** _şey_ — “quotes”",
	);
}

function test_markdown_draws_blocks()
{
	if (!markdown_test_loaded()) {
		return;
	}

	pg_assert_same("<h1>Title</h1>\n<p>Some <strong>bold</strong>, <em>italic</em>, <strong>strong</strong>, <em>em</em>, <del>gone</del> and <code>a &lt; b</code> text.</p>",
		pg_markdown_html(markdown_test_samples()[0]), 'headings and inline marks');
	pg_assert_same("<h2>Closed heading</h2>\n<h3>Third</h3>\n<h4>Fourth</h4>\n<h5>Fifth</h5>\n<h6>Sixth</h6>\n<p>####### Not a heading</p>",
		pg_markdown_html(markdown_test_samples()[1]), 'six heading levels, a seventh is text');
	pg_assert_same("<p>Line one<br>\nline two<br>\nline three\nline four</p>",
		pg_markdown_html(markdown_test_samples()[2]), 'two spaces or a backslash break a line');
	pg_assert_same("<ul>\n<li>one</li>\n<li>two\n<ul>\n<li>nested <em>a</em></li>\n<li>nested b</li>\n</ul></li>\n<li>three</li>\n</ul>\n<ol>\n<li>first</li>\n<li>second</li>\n</ol>",
		pg_markdown_html(markdown_test_samples()[3]), 'lists, a nested one');
	pg_assert_same("<ol start=\"3\">\n<li>starts at three</li>\n<li>four</li>\n</ol>\n<p>After the list.</p>",
		pg_markdown_html(markdown_test_samples()[4]), 'an ordered list keeps its first number');
	pg_assert_same("<blockquote class=\"blockquote\">\n<p>A quote\nwith <strong>two</strong> lines</p>\n<ul>\n<li>and a list</li>\n</ul>\n</blockquote>",
		pg_markdown_html(markdown_test_samples()[5]), 'a quote holds blocks');
	pg_assert_same("<pre><code class=\"language-php\">&lt;?php echo &#039;&lt;b&gt;&#039;; ?&gt;</code></pre>\n<pre><code>plain &amp; simple</code></pre>",
		pg_markdown_html(markdown_test_samples()[6]), 'fenced code is text');
	pg_assert_same("<hr>\n<hr>\n<hr>", pg_markdown_html(markdown_test_samples()[11]), 'three rules');
	pg_assert_same('', pg_markdown_html(''), 'nothing in, nothing out');
	pg_assert_same('', pg_markdown_html("   \n\n  "), 'blank lines draw nothing');
}

function test_markdown_draws_tables_and_pictures()
{
	if (!markdown_test_loaded()) {
		return;
	}

	pg_assert_same('<div class="table-responsive"><table class="table">'
		. '<thead><tr><th scope="col" class="text-start">Name</th><th scope="col" class="text-center">Price</th><th scope="col" class="text-end">Note</th></tr></thead>'
		. '<tbody><tr><td class="text-start">Tea</td><td class="text-center">2</td><td class="text-end"><strong>hot</strong></td></tr>'
		. '<tr><td class="text-start">Cake | pie</td><td class="text-center">3</td><td class="text-end"></td></tr></tbody>'
		. "</table></div>\n<p>After.</p>",
		pg_markdown_html(markdown_test_samples()[7]), 'a table with its alignments, a short row filled, an escaped pipe');
	pg_assert_same('<p><img src="https://example.com/cat.png" alt="A cat" title="Cat" class="img-fluid" loading="lazy"> and <img src="/files/a.jpg" alt="rel" class="img-fluid" loading="lazy"></p>',
		pg_markdown_html(markdown_test_samples()[8]), 'pictures with alt text and a title');
	pg_assert_same('<p><a href="https://pinegrap.com">Site</a> <a href="mailto:a@b.c">Mail</a> <a href="tel:+90123">Phone</a> <a href="/about">Page</a> <a href="#top">Anchor</a> <a href="page.html?a=1&amp;b=2">Rel</a></p>',
		pg_markdown_html(markdown_test_samples()[9]), 'links on safe addresses');
	pg_assert_same('<p><a href="https://example.com/a?b=1&amp;c=2">https://example.com/a?b=1&amp;c=2</a> and <a href="mailto:x@y.z">mailto:x@y.z</a></p>',
		pg_markdown_html(markdown_test_samples()[10]), 'addresses in angle brackets');
}

function test_markdown_lets_no_html_through()
{
	if (!markdown_test_loaded()) {
		return;
	}

	pg_assert_same("<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>\n<p>&lt;img src=x onerror=alert(1)&gt;</p>",
		pg_markdown_html(markdown_test_samples()[12]), 'tags are text');
	pg_assert_same('<p>&lt;b onclick=&quot;alert(1)&quot;&gt;x&lt;/b&gt; &amp;amp; &amp;lt;b&amp;gt; &amp;#106;avascript: &quot; &#039;</p>',
		pg_markdown_html(markdown_test_samples()[13]), 'entities and quotes are text');
	pg_assert_same("<p>x y [z](java\tscript:alert(1)) w d</p>", pg_markdown_html(markdown_test_samples()[14]), 'script and data links keep only their words');
	pg_assert_same('<p>p q r</p>', pg_markdown_html(markdown_test_samples()[15]), 'a picture on an unsafe address keeps its alt text');
	pg_assert_same('<p><a href="https://a.b/&quot;onmouseover=&quot;alert(1)">ok</a> <a href="https://a.b" title="a &lt;b&gt; title">t</a> [u](https://a.b&quot; onclick=&quot;x)</p>',
		pg_markdown_html(markdown_test_samples()[16]), 'a quote cannot leave the attribute');
	pg_assert_same('<p><a href="&amp;#106;avascript:alert(1)">&amp;#106;avascript</a> <a href="jav&amp;#x61;script:alert(1)">e</a></p>',
		pg_markdown_html(markdown_test_samples()[17]), 'an entity in an address stays text, read by the browser as a page name');
	pg_assert_same('<p>*not em* and [not a link](x) and &lt;b&gt;</p>',
		pg_markdown_html(markdown_test_samples()[18]), 'a backslash keeps a mark as text');
	pg_assert_same('<p>Text with a lone * star and snake_case_word and 2<em>3</em>4.</p>',
		pg_markdown_html(markdown_test_samples()[19]), 'a lone star and inner underscores stay');
	pg_assert_same('<div class="table-responsive"><table class="table"><thead><tr><th scope="col">a</th></tr></thead><tbody><tr><td>&lt;i&gt;b&lt;/i&gt;</td></tr></tbody></table></div>',
		pg_markdown_html(markdown_test_samples()[22]), 'a cell is text too');
	pg_assert_same('<p>control  characters</p>', pg_markdown_html(markdown_test_samples()[25]), 'control characters are dropped');
	pg_assert_false(pg_markdown_safe_url('javascript:alert(1)', false), 'a script address is refused');
	pg_assert_same('https://a.b/c', pg_markdown_safe_url(" https://a.b/c\n", false), 'spaces around an address go');
	pg_assert_false(pg_markdown_safe_url('mailto:a@b.c', true), 'a picture takes only a web address');
}

// The browser twin answers the same markup for every sample.
function test_markdown_browser_twin_is_equal()
{
	if (!markdown_test_loaded()) {
		return;
	}

	if (!function_exists('exec')) {
		pg_assert_true(true, 'exec() is off: the twin is not run');
		return;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		pg_assert_true(true, 'Node is not installed: the twin is not run');
		return;
	}

	$samples = markdown_test_samples();
	$script = "var window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_markdown.js') . "\n"
		. 'var samples = ' . json_encode($samples, JSON_UNESCAPED_UNICODE) . ";\n"
		. "console.log(JSON.stringify(samples.map(function (s) { return window.PGMarkdown.html(s); })));";
	$file = @tempnam(sys_get_temp_dir(), 'pgmarkdown');

	if ($file === false) {
		pg_assert_true(true, 'no temporary file: the twin is not run');
		return;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);
	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$js = json_decode(implode("\n", $out), true);

	foreach ($samples as $i => $sample) {
		pg_assert_same(pg_markdown_html($sample), is_array($js) && isset($js[$i]) ? $js[$i] : null, 'the twin draws sample ' . $i . ' the same');
	}
}

// The component's element on the site: the wrapper with the operator's
// class, the text drawn inside.
function test_markdown_component_html()
{
	if (!markdown_test_loaded()) {
		return;
	}

	pg_assert_same("<div class=\"my-note\">\n<p><strong>Hi</strong></p>\n</div>",
		pg_markdown_component_html(array('markdown' => '**Hi**', 'cssClass' => 'my-note'), ''), 'wrapper and body');
	pg_assert_same('<div></div>', pg_markdown_component_html(array(), ''), 'an empty text is an empty element');
}
