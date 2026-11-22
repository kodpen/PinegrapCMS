<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for initialize_order() (includes/fn/ecommerce.php): the ids the new
 * order row is written with.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// USER_ID and USER_CONTACT_ID are '' while nobody is signed in. Bound as
// text, every guest's first cart action wrote '' into the numeric user_id and
// contact_id columns: MariaDB warning 1366 ("Incorrect integer value"), the
// row kept 0 only because the connection runs without strict mode. Bound as
// numbers they are 0.
function test_initialize_order_binds_the_visitor_ids_as_numbers()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php');

	pg_assert_contains('$result = db_q($query, array((int) USER_ID, (int) USER_CONTACT_ID, (string) $reference_code,', $source, 'new order row');
	pg_assert_false(strpos($source, 'array((string) USER_ID, (string) USER_CONTACT_ID, (string) $reference_code') !== false, 'no text binding left');
}
