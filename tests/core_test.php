<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for input and output helpers in includes/fn/core.php and the tax
 * rate parser in includes/fn/ecommerce.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A javascript: URL is not one of the accepted forms and is refused.
function test_core_escape_url_refuses_javascript()
{
	pg_assert_false(escape_url('javascript:alert(1)'));
}

// An absolute https URL is accepted and returned unchanged.
function test_core_escape_url_keeps_https()
{
	pg_assert_same('https://example.com/a', escape_url('https://example.com/a'));
}

// Anything other than asc/desc in an ORDER BY direction falls back to asc.
function test_core_sql_order_direction_whitelists()
{
	pg_assert_same('asc', sql_order_direction('DROP'));
}

// A comma decimal separator is read and the rate is stored with three places.
function test_core_parse_tax_rate_reads_comma()
{
	pg_assert_same('18.500', parse_tax_rate('18,5'));
}

// A parameter that arrived as an array is carried over in bracket form
// instead of stopping the page; a plain parameter is written as before.
function test_core_build_url_carries_array_parameter()
{
	pg_assert_same(
		'/members?a=1&q%5B0%5D=x&summary=most_recent',
		build_url(array('url' => '/members?a=1&q[]=x', 'parameters' => array('summary' => 'most_recent'))));
	pg_assert_same('/members?a=1&summary=x', build_url(array('url' => '/members?a=1&summary=y', 'parameters' => array('summary' => 'x'))));
}

// A NULL column or an unset value prints and escapes as nothing, without the
// PHP 8.1 "Passing null" deprecation (the runner turns it into an error).
function test_core_h_and_escape_take_null()
{
	pg_assert_same('', h(null));
	pg_assert_same('', escape(null));
	pg_assert_same('a &amp; &quot;b&quot;', h('a & "b"'));
	pg_assert_same('0', h(0));
}

// The JavaScript, CSV and URL path helpers do the same for null; other values
// are encoded as before.
function test_core_other_escape_helpers_take_null()
{
	pg_assert_same('', escape_javascript(null));
	pg_assert_same('', escape_csv(null));
	pg_assert_same('', encode_url_path(null));
	pg_assert_same("it\\'s", escape_javascript("it's"));
	pg_assert_same('a""b', escape_csv('a"b'));
	pg_assert_same('a%20b/c', encode_url_path('a b/c'));
}

// The short version in titles and the logo badge. The installer's VERSION is
// true, which names no version: '' then, and the title goes without one.
function test_core_short_version()
{
	pg_assert_same('26.11', pg_short_version('2026.11.0'), 'year and month');
	pg_assert_same('26.4', pg_short_version('2026.4.10'), 'a one-digit month');
	pg_assert_same('26', pg_short_version('2026'), 'the year alone');
	pg_assert_same('26', pg_short_version(2026), 'a number');
	pg_assert_same('26.11', pg_short_version('v2026.11.0'), 'a leading v');
	pg_assert_same('', pg_short_version(true), 'the installer\'s marker');
	pg_assert_same('', pg_short_version(null), 'nothing');
	pg_assert_same('', pg_short_version(array('2026')), 'not a version');
}
