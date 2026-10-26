<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a single HTML file brought in as a note
 * (includes/workspace/notes_html.php): the character set it is read in, its
 * title and dates, the Markdown its text becomes, the pictures it carries
 * inside it, and what hostile markup leaves behind.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_io.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_import.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_enex.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_html.php');

// The smallest PNG there is: one transparent pixel.
function workspace_notes_html_test_png()
{
	return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
}

function test_workspace_notes_html_extension_is_taken()
{
	pg_assert_true(in_array('html', ws_note_import_extensions(), true), '.html is offered');
	pg_assert_true(in_array('htm', ws_note_import_extensions(), true), '.htm is offered');
}

function test_workspace_notes_html_decode()
{
	pg_assert_same('<p>Ağaç</p>', ws_note_html_decode("\xEF\xBB\xBF<p>Ağaç</p>"), 'a byte order mark dropped');

	$legacy = ws_note_html_decode("<html><head><meta charset=\"windows-1254\"></head><body>A\xF0a\xE7</body></html>");
	pg_assert_contains('Ağaç', $legacy, 'the declared Turkish code page read');
	pg_assert_contains('charset="utf-8"', $legacy, 'the declaration now says what the text is');

	$equiv = ws_note_html_decode("<meta http-equiv=\"Content-Type\" content=\"text/html; charset=ISO-8859-1\"><p>caf\xE9</p>");
	pg_assert_contains('café', $equiv, 'an http-equiv declaration read');

	pg_assert_contains('Ağaç', ws_note_html_decode("<p>A\xF0a\xE7</p>"), 'no declaration, not UTF-8: the Turkish code page');
	pg_assert_contains('Ağaç', ws_note_html_decode("\xFF\xFE" . mb_convert_encoding('<p>Ağaç</p>', 'UTF-16LE', 'UTF-8')), 'UTF-16 with its mark');
	pg_assert_contains('Ağaç', ws_note_html_decode('<meta charset="no-such-set"><p>Ağaç</p>'), 'an unknown declared set falls back to UTF-8');
}

function test_workspace_notes_html_read_a_clip()
{
	$html = '<!DOCTYPE html><html><head><title>Page title | Site</title>'
		. '<meta property="og:title" content="Soup recipe">'
		. '<meta property="article:published_time" content="2024-03-05T10:00:00Z">'
		. '<meta property="article:modified_time" content="2024-03-06T11:30:00Z">'
		. '<link rel="canonical" href="https://example.com/soup?a=1&amp;b=2">'
		. '</head><body><nav><a href="/">Home</a> <a href="/menu">Menu</a></nav>'
		. '<header>Site banner</header>'
		. '<main><h1>Soup recipe</h1><h2>Ingredients</h2>'
		. '<ul><li>Two <b>onions</b></li><li>Salt</li></ul>'
		. '<p>See <a href="https://example.com/more">more</a> and <a href="mailto:chef@example.com">the chef</a>.</p>'
		. '<p><img src="https://example.com/soup.jpg" alt="Bowl"></p>'
		. '<form><input value="secret"><button>Subscribe</button><select><option>A</option></select></form>'
		. '</main><footer>Copyright</footer></body></html>';

	$read = ws_note_html_read($html);

	pg_assert_true($read['ok'], 'read');
	pg_assert_same('Soup recipe', $read['title'], 'the page\'s own title before the browser tab\'s');
	pg_assert_same(gmmktime(10, 0, 0, 3, 5, 2024), $read['created']);
	pg_assert_same(gmmktime(11, 30, 0, 3, 6, 2024), $read['updated']);
	pg_assert_same(array(), $read['files']);

	$body = $read['body'];
	pg_assert_contains("## Ingredients\n", $body, 'a heading left as Markdown for the import to flatten');
	pg_assert_contains("- Two **onions**\n- Salt", $body, 'a list and bold words');
	pg_assert_contains('[more](https://example.com/more)', $body, 'a web link kept');
	pg_assert_contains('[the chef](mailto:chef@example.com)', $body, 'a mail link kept');
	pg_assert_contains('[Bowl](https://example.com/soup.jpg)', $body, 'a web picture as a link');
	pg_assert_contains('[https://example.com/soup?a=1&b=2](https://example.com/soup?a=1&b=2)', $body, 'the page it was clipped from, at the end');
	pg_assert_false(strpos($body, 'Home') !== false || strpos($body, 'Site banner') !== false || strpos($body, 'Copyright') !== false, 'only the main part of a page is read');
	pg_assert_false(strpos($body, 'Subscribe') !== false || strpos($body, 'secret') !== false, 'form controls leave nothing');
}

function test_workspace_notes_html_read_an_export()
{
	// An app's export: no main part, the title in <title> and an <h1>.
	$read = ws_note_html_read('<html><head><title>Shopping</title></head><body><h1>Shopping</h1><div>Milk</div><div>Bread &amp; butter</div></body></html>');

	pg_assert_same('Shopping', $read['title']);
	pg_assert_same("# Shopping\n\nMilk\nBread & butter", $read['body'], 'divs are lines; the repeated title is left for the import to take out');
	pg_assert_same(0, $read['created'], 'no date');

	$untitled = ws_note_html_read('<p>Only words</p>');
	pg_assert_same('', $untitled['title'], 'no title: the stage names it after the file');
	pg_assert_same('Only words', $untitled['body']);

	$h1 = ws_note_html_read('<body><h1>  First   heading </h1><p>x</p></body>');
	pg_assert_same('First heading', $h1['title'], 'no <title>: the first heading');

	$one = ws_note_html_read('<body><nav>Menu</nav><article><p>Story</p></article><aside>Ads</aside></body>');
	pg_assert_same('Story', $one['body'], 'a single article is the content');
}

function test_workspace_notes_html_read_hostile_markup()
{
	$html = '<html><head><script>alert(1)</script><style>p{color:red}</style>'
		. '<base href="javascript:alert(1)//"></head>'
		. '<body onload="alert(2)">'
		. '<p onclick="alert(3)" style="background:url(javascript:alert(4))">Hello <b onmouseover="x()">there</b></p>'
		. '<a href="javascript:alert(5)">click me</a> '
		. '<a href="JaVaScRiPt:alert(6)">two</a> '
		. '<a href="data:text/html,<script>alert(7)</script>">three</a> '
		. '<a href="vbscript:msgbox(8)">four</a> '
		. '<a href="https://ok.example/x)(y">five</a>'
		. '<img src="javascript:alert(9)" onerror="alert(10)">'
		. '<img src="data:image/svg+xml;base64,' . base64_encode('<svg onload="alert(11)"/>') . '">'
		. '<iframe src="https://evil.example"></iframe><object data="x.swf"></object><embed src="x">'
		. '<svg><script>alert(12)</script><text>svgtext</text></svg>'
		. '<template><p>templated</p></template><noscript>noscript text</noscript>'
		. '<div hidden>hidden words</div><div style="display: none">invisible words</div>'
		. '<p>&lt;script&gt;alert(13)&lt;/script&gt;</p>'
		. '<en-media hash="0123"></en-media><en-crypt>abc</en-crypt>'
		. '<p>Tag <@user:12></p>'
		. '</body></html>';

	$read = ws_note_html_read($html);
	$body = $read['body'];

	pg_assert_true($read['ok']);
	pg_assert_same(array(), $read['files'], 'an SVG picture is never kept');

	foreach (array('alert(1)', 'alert(2)', 'alert(3)', 'alert(4)', 'alert(5)', 'alert(6)', 'alert(7)', 'msgbox', 'alert(9)', 'alert(10)', 'alert(11)', 'alert(12)', 'onclick', 'onload', 'onerror', 'javascript', 'JaVaScRiPt', 'vbscript', 'data:', 'evil.example', 'x.swf', 'svgtext', 'templated', 'noscript text', 'hidden words', 'invisible words', 'color:red', '[encrypted text]') as $gone) {
		pg_assert_false(stripos($body, $gone) !== false, 'nothing of "' . $gone . '" is left');
	}

	pg_assert_contains('Hello **there**', $body, 'the words of a hostile paragraph stay');
	pg_assert_contains('click me two three four', $body, 'a link to anything but the web or mail keeps its words only');
	pg_assert_contains('[five](https://ok.example/x%29%28y)', $body, 'a bracket in an address cannot end the link');
	pg_assert_contains('<script>alert(13)</script>', $body, 'text that reads as markup stays text, as any note keeps it');
}

function test_workspace_notes_html_read_refusals()
{
	$empty = ws_note_html_read('<html><head><title>T</title><script>x()</script></head><body> </body></html>');
	pg_assert_false($empty['ok'], 'no text and no picture');
	pg_assert_same('The web page holds no text.', $empty['error']);

	$large = ws_note_html_read(str_repeat('a', 2000), null, null, 1000);
	pg_assert_false($large['ok'], 'past the HTML file\'s ceiling');
	pg_assert_contains('larger', $large['error']);

	// An entity bomb in a document type: the HTML parser never expands it.
	$bomb = '<!DOCTYPE html [<!ENTITY a "aaaaaaaaaa"><!ENTITY b "&a;&a;&a;&a;&a;&a;&a;&a;&a;&a;"><!ENTITY c "&b;&b;&b;&b;&b;&b;&b;&b;&b;&b;">]><p>&c;&c;&c;</p>';
	$read = ws_note_html_read($bomb);
	pg_assert_false(strpos((string) $read['body'], 'aaaaaaaaaaaaaaaaaaaa') !== false, 'an entity of the document type is not expanded');

	// An external entity is never fetched or read.
	$external = ws_note_html_read('<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><p>&e;</p>');
	pg_assert_false(strpos((string) $external['body'], 'root:') !== false, 'no local file read');
}

function test_workspace_notes_html_read_embedded_pictures()
{
	$png = workspace_notes_html_test_png();
	$data = 'data:image/png;base64,' . base64_encode($png);
	$gif = 'GIF89a' . str_repeat("\0", 200);
	$html = '<body><p>Before <img src="' . $data . '" alt="Pixel"> after</p>'
		. '<p><img src="' . $data . '"></p>'
		. '<p><img src="data:image/jpeg;base64,!!notbase64!!"></p>'
		. '<p><img src="data:text/html;base64,' . base64_encode('<script>x</script>') . '"></p>'
		. '<p><img src="data:image/png;base64,' . base64_encode('<html><script>x</script></html>') . '"></p>'
		. '<p><img src="data:image/gif;base64,' . base64_encode($gif) . '"></p></body>';

	$read = ws_note_html_read($html);

	pg_assert_same(2, count($read['files']), 'the same picture twice is one file; broken data, other types and bytes that are no picture are dropped');
	pg_assert_same('image-1.png', $read['files'][0]['name']);
	pg_assert_same($png, $read['files'][0]['bytes']);
	pg_assert_same('image-2.gif', $read['files'][1]['name']);
	pg_assert_same("Before [image-1.png](attachments/1/image-1.png) after\n\n[image-1.png](attachments/1/image-1.png)\n\n[image-2.gif](attachments/1/image-2.gif)", $read['body'], 'paragraphs set apart as the Evernote reader sets them');

	// Past what one file may be, or what the import may copy: dropped.
	$small = ws_note_html_read($html, strlen($png));
	pg_assert_same(1, count($small['files']), 'a picture past the file limit is dropped');
	pg_assert_same('image-1.png', $small['files'][0]['name']);

	$ceiling = ws_note_html_read($html, null, strlen($png) + 10);
	pg_assert_same(1, count($ceiling['files']), 'pictures past the import\'s byte ceiling are dropped');

	$only = ws_note_html_read('<img src="' . $data . '">');
	pg_assert_true($only['ok'], 'a page of one picture is a note');
}

function test_workspace_notes_html_stage()
{
	$dir = sys_get_temp_dir() . '/pg_html_test_' . bin2hex(random_bytes(4));
	mkdir($dir, 0755);
	$source = $dir . '/source.html';
	$png = workspace_notes_html_test_png();
	file_put_contents($source, '<html><head><meta name="date" content="2024-01-02T00:00:00Z"></head><body><p>Hi <img src="data:image/png;base64,' . base64_encode($png) . '"></p></body></html>');

	$read = ws_note_html_stage($dir, $source, 'My clip.html');

	pg_assert_true($read['ok'], 'staged');
	pg_assert_false(is_file($source), 'the source removed once read');
	pg_assert_same('My clip.md', $read['entries'][0][0], 'an untitled page named after its file');
	pg_assert_same(array('attachments/1/image-1.png', strlen($png)), array($read['entries'][1][0], $read['entries'][1][1]));
	pg_assert_same(1, $read['entries'][1][4], 'the picture is an attachment row');

	$stage = array('dir' => $dir, 'zip' => false, 'entries' => $read['entries']);
	pg_assert_same($png, ws_note_import_read($stage, 1, strlen($png)));
	pg_assert_same(array(0), ws_note_import_index($stage)['notes']);

	$note = ws_note_import_parse(ws_note_import_text(ws_note_import_read($stage, 0, WS_NOTE_IMPORT_TEXT_MAX)), 'My clip.md', 0);
	pg_assert_same(gmmktime(0, 0, 0, 1, 2, 2024), $note['created']);
	pg_assert_contains('Hi [image-1.png](attachments/1/image-1.png)', $note['body']);

	foreach (array_merge((array) glob($dir . '/files/*'), (array) glob($dir . '/*')) as $path) {
		if (is_file($path)) {
			unlink($path);
		}
	}

	rmdir($dir . '/files');
	rmdir($dir);

	// A page with nothing in it is refused, and its source removed.
	mkdir($dir, 0755);
	file_put_contents($source, '<html><body><script>x()</script></body></html>');
	$empty = ws_note_html_stage($dir, $source, 'empty.html');
	pg_assert_false($empty['ok']);
	pg_assert_false(is_file($source));
	@rmdir($dir . '/files');
	rmdir($dir);
}
