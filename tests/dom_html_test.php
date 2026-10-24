<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_dom_html() in includes/fn/core.php: the HTML of one DOM node
 * as it stands in the tree, without the line breaks libxml's pretty-printer
 * adds. DOMDocument::saveHTML($node) adds them before PHP 7.3 whatever
 * formatOutput says, and on every version once formatOutput is on, so these
 * tests turn it on and run the same path on every version.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The <div> around $html, read the way pg_sanitize_rich_text() reads stored
// HTML, in a document with formatOutput on.
function _test_dom_html_wrapper($html)
{
	$document = new DOMDocument('1.0', 'UTF-8');
	$previous = libxml_use_internal_errors(true);
	$document->loadHTML('<?xml encoding="UTF-8"><html><body><div>' . $html . '</div></body></html>', LIBXML_NONET);
	libxml_clear_errors();
	libxml_use_internal_errors($previous);
	$document->formatOutput = true;

	return $document->getElementsByTagName('body')->item(0)->firstChild;
}

// Blocks after blocks, a list, a rule, a block of inline elements, a table,
// text, a comment: every node's HTML is its markup, no line break added.
function test_dom_html_adds_no_line_breaks()
{
	$html = '<p>A</p><p>B</p><ul><li>a</li><li>b</li></ul><hr><div><b>x</b><i>y</i></div>'
		. '<table><tr><td>1</td><td>2</td></tr></table><p>T</p>tail<!--c--><p>Ü &lt;</p>';
	$wrapper = _test_dom_html_wrapper($html);
	$out = '';

	foreach ($wrapper->childNodes as $child) {
		$out .= pg_dom_html($child);
	}

	pg_assert_same($html, $out, 'child by child');
	pg_assert_same('<div>' . $html . '</div>', pg_dom_html($wrapper), 'a node with its own tags');
	pg_assert_same('<div><script>if (a < b) go();</script></div>', pg_dom_html(_test_dom_html_wrapper('<script>if (a < b) go();</script>')), 'script text stays as it is');
}

// The empty text goes into a copy: the caller's tree keeps its nodes.
function test_dom_html_leaves_the_tree_alone()
{
	$wrapper = _test_dom_html_wrapper('<ul><li>a</li><li>b</li></ul><p>c</p>');
	pg_dom_html($wrapper);

	pg_assert_same(2, $wrapper->childNodes->length, 'the wrapper');
	pg_assert_same(2, $wrapper->firstChild->childNodes->length, 'the list');
}
