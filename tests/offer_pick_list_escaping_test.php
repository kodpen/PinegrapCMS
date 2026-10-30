<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that select_offer() prints an offer's description as text in its
 * <option> label: the description is truncated raw, then escaped, so < and &
 * survive and markup is never parsed inside the select.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_offer_pick_list_escapes_description()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/forms.php');
	$start = strpos($source, 'function select_offer(');
	pg_assert_true($start !== false, 'select_offer() exists');
	$end = strpos($source, "\nfunction ", $start + 1);
	$body = substr($source, $start, $end - $start);

	pg_assert_same(0, preg_match('/\$output_label \.= \$[^;]*;/', $body), 'description appended raw');
	pg_assert_same(1, preg_match('/\$output_label \.= h\(\$description\);/', $body), 'description appended through h()');
	pg_assert_same(1, preg_match('/\$output_label = h\(\$offer\[\'code\'\]\);/', $body), 'code through h()');
}
