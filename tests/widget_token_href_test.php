<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a record's value written as an address (pg_sw_apply_tokens(),
 * includes/fn/widgets.php): a submitted value that starts an href, src or
 * action keeps only a web, mail or phone address or a path of the site;
 * one with any other scheme (javascript:, data:, vbscript:) prints no
 * address, while the same value as text prints as before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _token_href_fill($template, $value, $type = 'text box')
{
	return pg_sw_apply_tokens($template, array(), array(
		'website' => array('data' => $value, 'type' => $type, 'wysiwyg' => 0),
	));
}

function test_widget_token_href_refuses_script_schemes()
{
	$tpl = '<a href="^^website^^" target="_blank">^^website^^</a>';
	foreach (array(
		'javascript:alert(document.cookie)',
		'JavaScript:alert(1)',
		"  javascript:alert(1)",
		"java\tscript:alert(1)",
		"java\nscript:alert(1)",
		"\x01javascript:alert(1)",
		'data:text/html;base64,PHNjcmlwdD4=',
		'vbscript:msgbox(1)',
	) as $value) {
		$html = _token_href_fill($tpl, $value);
		$label = var_export($value, true);
		pg_assert_contains('<a href="" target="_blank">', $html, $label . ': no address');
		if (strpos($value, "\n") === false) {
			pg_assert_contains('>' . h($value) . '</a>', $html, $label . ': text kept');
		}
	}

	pg_assert_same('<img src="">', _token_href_fill('<img src="^^website^^">', 'javascript:x'), 'src');
	pg_assert_same("<form action=''>", _token_href_fill("<form action='^^website^^'>", 'javascript:x'), 'single quotes');
	pg_assert_same('<a href = "">', _token_href_fill('<a href = "^^website^^">', 'javascript:x'), 'spaces around =');
}

function test_widget_token_href_keeps_safe_addresses()
{
	$tpl = '<a href="^^website^^">x</a>';
	foreach (array(
		'https://example.com/a?b=1' => 'https://example.com/a?b=1',
		'HTTP://example.com'        => 'HTTP://example.com',
		'mailto:a@example.com'      => 'mailto:a@example.com',
		'tel:+905551112233'         => 'tel:+905551112233',
		'/files/photo.jpg'          => '/files/photo.jpg',
		'//cdn.example.com/a.png'   => '//cdn.example.com/a.png',
		'page?x=1&y=2'              => 'page?x=1&amp;y=2',
		'#top'                      => '#top',
		''                          => '',
	) as $value => $expected) {
		pg_assert_same('<a href="' . $expected . '">x</a>', _token_href_fill($tpl, (string)$value), var_export($value, true));
	}

	// A scheme the designer wrote is theirs: the value fills its rest.
	pg_assert_same('<a href="mailto:javascript:x">x</a>',
		_token_href_fill('<a href="mailto:^^website^^">x</a>', 'javascript:x'), 'designer scheme');
	// The same value as text, or in another attribute, prints as before.
	pg_assert_same('<p title="javascript:x">javascript:x</p>',
		_token_href_fill('<p title="^^website^^">^^website^^</p>', 'javascript:x'), 'text and title');
	pg_assert_same('<a data-href="javascript:x">x</a>',
		_token_href_fill('<a data-href="^^website^^">x</a>', 'javascript:x'), 'data-href');
}
