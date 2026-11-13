<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the names the Visual Page Editor's save refuses as a page's
 * address (pg_designer_page_name_error(), includes/fn/designer.php): longer
 * than the page_name column, and "?", "%", "+" or a control character in a
 * name given now; and that pg_designer_save_page() asks it, so the editor,
 * the API's design resource and the MCP server refuse the same names.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_designer_page_name_length()
{
	pg_assert_true(function_exists('pg_designer_page_name_error'), 'the designer checks a page name for its address');
	if (!function_exists('pg_designer_page_name_error')) {
		return;
	}

	$fits = str_repeat('ş', 100);
	pg_assert_same('', pg_designer_page_name_error($fits, true), '100 characters fit, counted as characters');
	pg_assert_true(pg_designer_page_name_error($fits . 'a', true) !== '', '101 characters are refused');
	pg_assert_true(pg_designer_page_name_error($fits . 'a', false) !== '', 'a name kept is refused too: the column would cut it');
	pg_assert_contains('100', pg_designer_page_name_error(str_repeat('x', 117), true), 'the message names the limit');
}

function test_designer_page_name_address_characters()
{
	if (!function_exists('pg_designer_page_name_error')) {
		pg_assert_true(false, 'the designer checks a page name for its address');
		return;
	}

	foreach (array('pg-test a?b', 'yüzde%20', '50%', 'a+b', "a\tb", "a\nb", "a\x7fb") as $name) {
		pg_assert_true(pg_designer_page_name_error($name, true) !== '', 'refused when given now: ' . json_encode($name));
		pg_assert_same('', pg_designer_page_name_error($name, false), 'a page that has it keeps saving: ' . json_encode($name));
	}

	foreach (array('pg-test a#b', 'klasör/alt sayfa', 'a&b', 'çiçekçi', 'iletişim_formu', "o'neil") as $name) {
		pg_assert_same('', pg_designer_page_name_error($name, true), 'an address can carry it: ' . $name);
	}
}

function test_designer_save_page_asks_the_name_rules()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	$from = strpos($source, 'function pg_designer_save_page(');
	$to = ($from !== false) ? strpos($source, "\nfunction ", $from + 10) : false;
	pg_assert_true(($from !== false) && ($to !== false), 'the save function is found');
	if (($from === false) || ($to === false)) {
		return;
	}

	$body = substr($source, $from, $to - $from);
	pg_assert_contains('pg_designer_page_name_error($name, $renamed)', $body, 'the save refuses a name the address cannot carry');
}
