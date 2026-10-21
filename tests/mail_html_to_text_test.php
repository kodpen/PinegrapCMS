<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for convert_html_to_text() (includes/fn/mail.php), which writes the
 * plain-text part of an HTML e-mail: h1-h3, bold and table headings in
 * capitals, h4-h6 in title case, a link followed by its address. The case
 * changes are callbacks of the function itself, so calling it declares no
 * named function and a second call gives the same text.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_mail_html_to_text_big_headings_in_capitals()
{
	pg_assert_same("\n\nORDER RECEIVED\nThank you", convert_html_to_text('<h1>Order received</h1><p>Thank you</p>'), 'h1');
	pg_assert_same("\n\nÇOK GÜZEL\nTHIRD\n\n", convert_html_to_text('<h2 class="x">çok güzel</h2><h3>third</h3>'), 'h2 and h3, multibyte letters too');
}

function test_mail_html_to_text_small_headings_in_title_case()
{
	pg_assert_same("\n\nShipping Address\n\nMain street", convert_html_to_text('<h4>shipping address</h4>Main street'), 'h4');
	pg_assert_same("\n\nKüçük Başlık\n\n", convert_html_to_text('<h6 id="y">küçük başlık</h6>'), 'h6 with attributes');
}

function test_mail_html_to_text_bold_in_capitals()
{
	pg_assert_same("\n\nTotal: TEN LIRA and ŞOFÖR", convert_html_to_text('<p>Total: <b>ten lira</b> and <strong>şoför</strong></p>'));
}

function test_mail_html_to_text_table_headings_in_capitals()
{
	pg_assert_same(
		"\n\t\tÜRÜN\n\t\tQTY\n\t\tPen\n\t\t2\n",
		convert_html_to_text('<table><tr><th>ürün</th><th>qty</th></tr><tr><td>Pen</td><td>2</td></tr></table>'));
}

function test_mail_html_to_text_links_and_entities()
{
	pg_assert_same(
		"\n\nSee our site (https://example.com/x) & more text",
		convert_html_to_text('<p>See <a href="https://example.com/x">our site</a> &amp; more&nbsp;text</p>'));
}

// A campaign or a queue run converts many messages in one request.
function test_mail_html_to_text_repeat_call_declares_no_function()
{
	$html = '<h1>a</h1><h5>b c</h5><b>d</b><strong>e</strong><table><tr><th>f</th></tr></table>';
	$first = convert_html_to_text($html);

	pg_assert_same($first, convert_html_to_text($html), 'same text on the second call');

	foreach (array('heading_big', 'heading_small', 'bold', 'strong', 'table_heading') as $name) {
		pg_assert_false(function_exists('convert_html_to_text_' . $name . '_callback'), $name);
	}
}
