<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * pg_cf_field_pattern_regex(): the PCRE custom_form.php checks a field's
 * validation pattern (the input's HTML pattern) with on the server. A slash
 * the pattern already escapes stays one escaped slash, so the expression
 * compiles and a value that does not match is refused.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/custom_form.php');

function test_cf_field_pattern_escaped_slashes()
{
	$re = pg_cf_field_pattern_regex('\d{2}\/\d{2}\/\d{4}');
	pg_assert_same(1, @preg_match($re, '12/05/2026'), 'escaped slashes: compiles and matches a date');
	pg_assert_same(0, @preg_match($re, 'x'), 'escaped slashes: refuses other text');
}

function test_cf_field_pattern_bare_slashes()
{
	$re = pg_cf_field_pattern_regex('\d{2}/\d{2}');
	pg_assert_same(1, @preg_match($re, '12/05'), 'bare slash: compiles and matches');
	pg_assert_same(0, @preg_match($re, 'x'), 'bare slash: refuses other text');
}

function test_cf_field_pattern_plain_and_whole_value()
{
	pg_assert_same('/^(?:[a-z]+)$/u', pg_cf_field_pattern_regex('[a-z]+'), 'no slash: the pattern as it is, anchored');
	pg_assert_same(0, @preg_match(pg_cf_field_pattern_regex('\d{4}'), 'a1234b'), 'the whole value must match');
	pg_assert_same(1, @preg_match(pg_cf_field_pattern_regex('[a-zğüşıöç]+'), 'güneş'), 'UTF-8 letters');
}

// An escaped backslash before a slash leaves the slash bare: it is escaped.
function test_cf_field_pattern_escaped_backslash_then_slash()
{
	$re = pg_cf_field_pattern_regex('a\\\\/b');
	pg_assert_same(1, @preg_match($re, 'a\\/b'), 'a literal backslash, then a slash');
}
