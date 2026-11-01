<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the set password screen's query string (get_set_password.php):
 * a token or send_to sent as an array (?k[]=x, ?send_to[]=x) must not reach
 * hash() or the form, which throw a TypeError on PHP 8 and turn the screen
 * into a 500 page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_set_password_query_values_are_read_once_as_scalars()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_set_password.php');

	$reads = array(
		'k'       => "\$token = is_scalar(\$_GET['k'] ?? null) ? (string) \$_GET['k'] : '';",
		'send_to' => "\$send_to = is_scalar(\$_GET['send_to'] ?? null) ? (string) \$_GET['send_to'] : '';",
	);

	foreach ($reads as $name => $read) {
		pg_assert_contains($read, $source, 'an array ' . $name . ' counts as not given');
		pg_assert_same(0, substr_count(str_replace($read, '', $source), "\$_GET['" . $name . "']"), 'no other line reads ' . $name . ' from the query string');
	}
}
