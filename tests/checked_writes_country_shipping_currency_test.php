<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Every db_q() write of Edit Country, Edit Shipping Method and the shared
 * currency form ends the request with output_error() when it fails, so a
 * failed delete or base/default reset is never logged and reported as done.
 * The currency form writes nothing now: the base reset is part of
 * pg_store_currency_save(), whose 0 the currency screens answer with
 * output_error().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * The db_q() statements of a file that write (DELETE, UPDATE, INSERT) and
 * do not carry the output_error() check.
 *
 * @param string $file
 * @return array
 */
function pg_test_unchecked_db_q_writes($file)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	preg_match_all('/db_q\((?:(?!;).)*;/s', $source, $matches);

	$unchecked = array();

	foreach ($matches[0] as $statement) {
		if (!preg_match('/^db_q\(\s*"\s*(DELETE|UPDATE|INSERT)\b/i', $statement)) {
			continue;
		}

		if (strpos($statement, "or output_error('Query failed.')") === false) {
			$unchecked[] = preg_replace('/\s+/', ' ', $statement);
		}
	}

	return $unchecked;
}

function test_checked_writes_country_shipping_currency()
{
	$expected = array(
		'edit_country.php' => 0,
		'edit_shipping_method.php' => 0,
		'includes/forms/currency_form.php' => 0,
	);

	foreach ($expected as $file => $count) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		preg_match_all('/db_q\(\s*"\s*(DELETE|UPDATE|INSERT)\b/i', $source, $writes);

		pg_assert_true(count($writes[0]) === $count, $file . ' has its ' . $count . ' db_q() writes');
		pg_assert_same(array(), pg_test_unchecked_db_q_writes($file), $file . ' checks every db_q() write');
	}
}
