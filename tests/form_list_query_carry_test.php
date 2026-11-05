<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_form_list_carry_query() in includes/fn/forms.php: the query
 * string a form list view's browse filter and pagination links carry over.
 * A nested list (?a[x][y]=1) made urlencode() throw a TypeError there and
 * took the page down.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Plain values and lists are carried; skipped names are not.
function test_form_list_carry_query_plain_and_lists()
{
	parse_str('a=1&b[]=x&b[]=y z&12_query=q&n=', $parameters);
	$skip = function ($name) { return strpos($name, '12_') === 0; };

	pg_assert_same('a=1&b%5B%5D=x&b%5B%5D=y+z&n=', pg_form_list_carry_query($parameters, $skip));
}

// A nested list is left out instead of throwing; its plain siblings stay.
function test_form_list_carry_query_nested_list()
{
	parse_str('a[x][y]=1&b[]=2&b[k][]=3&c=4', $parameters);
	$skip = function ($name) { return false; };

	pg_assert_same('b%5B%5D=2&c=4', pg_form_list_carry_query($parameters, $skip));
}

// No parameters: an empty string.
function test_form_list_carry_query_empty()
{
	pg_assert_same('', pg_form_list_carry_query(array(), function ($name) { return false; }));
}

// The view builds both link query strings with the helper, and reads the
// address's query only when there is one.
function test_form_list_carry_query_view_wiring()
{
	$source = file_get_contents(dirname(__DIR__) . '/pinegrap/get_form_list_view.php');
	pg_assert_same(false, strpos($source, 'urlencode($subvalue)'));
	pg_assert_same(2, substr_count($source, 'pg_form_list_carry_query('));
	pg_assert_same(false, strpos($source, "parse_str(\$url_parts['query'], \$query_string_parameters);\n                }"));
}
