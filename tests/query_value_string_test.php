<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that every screen reading a form field default from ?value_<id>
 * accepts it only as one string: an array (value_<id>[]=) reaching trim()
 * throws a TypeError on PHP 8 and stops the page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_query_value_defaults_are_read_only_as_strings()
{
	$files = array_merge(
		glob(PG_FUNCTIONS_DIR . '/*.php'),
		glob(PG_FUNCTIONS_DIR . '/includes/fn/*.php')
	);
	$unchecked = array();
	foreach ($files as $file) {
		foreach (file($file) as $number => $line) {
			if (strpos($line, "trim(") !== false
				&& strpos($line, "\$_GET['value_'") !== false
				&& strpos($line, 'is_string(') === false) {
				// A trim() inside a block already guarded by is_string() on the line above is fine.
				$lines = file($file);
				$previous = isset($lines[$number - 1]) ? $lines[$number - 1] : '';
				if (strpos($previous, 'is_string(') === false) {
					$unchecked[] = basename($file) . ':' . ($number + 1);
				}
			}
		}
	}
	pg_assert_same(array(), $unchecked, 'trim() of ?value_<id> without is_string()');
}
