<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for initialize_order() (includes/fn/ecommerce.php): the ids the new
 * order row is written with, and when a signed-in user's order takes them.
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

// A user with no contact (the administrator an install creates has none) has
// USER_CONTACT_ID null, and the order row holds 0. Compared as text, '0' and
// null differ, so every cart change rewrote the order with contact_id '' and
// warning 1366. Compared and bound as numbers, nothing is rewritten.
function test_initialize_order_owner_update_compares_numbers()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php');

	pg_assert_contains("if (\$order && (((int) \$order['user_id'] !== (int) USER_ID) || ((int) \$order['contact_id'] !== (int) USER_CONTACT_ID))) {", $source, 'owner compared as numbers');
	pg_assert_contains("db_q(\"UPDATE orders SET user_id = ?, contact_id = ? WHERE id = ?\", array((int) USER_ID, (int) USER_CONTACT_ID, (int) \$_SESSION['ecommerce']['order_id']));", $source, 'owner written as numbers');
	pg_assert_false(strpos($source, "contact_id = '\" . e(USER_CONTACT_ID) . \"'") !== false, 'no text binding left');
}
