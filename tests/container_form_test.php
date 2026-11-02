<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the container form (includes/forms/container_form.php): which
 * posted length, width and height a container may be saved with.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/container_form.php');

function test_container_dimension_must_be_a_positive_number()
{
	foreach (array('12', '0.5', '12.25', ' 3 ', 7, 1.5) as $value) {
		pg_assert_true(pg_container_dimension_positive($value), var_export($value, true) . ' is kept');
	}

	// The columns are unsigned decimals under a non-strict sql_mode: a
	// negative or non-numeric value was stored as 0, a container no item
	// fits in.
	foreach (array('0', '0.0', '-5', '-0.1', 'abc', '', '1e', array('3'), null) as $value) {
		pg_assert_same(false, pg_container_dimension_positive($value), var_export($value, true) . ' is refused');
	}
}
