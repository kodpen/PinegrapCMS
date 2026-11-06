<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the legacy gateway keys that are stored plain but never printed
 * back into the Commerce settings form (includes/fn/payments.php): a blank or
 * masked box keeps the stored key; the Givex password is kept only while
 * both Givex hostnames stay the same; and, read from the source, the screen
 * prints the mask, never the stored key.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every key printed on the card before is listed.
function test_gateway_masked_fields_listed()
{
	$fields = pg_gateway_masked_fields();

	foreach (array(
		'ecommerce_clearcommerce_password',
		'ecommerce_paypal_payments_pro_api_signature',
		'ecommerce_sage_merchant_key',
		'ecommerce_stripe_api_key',
		'ecommerce_paypal_express_checkout_api_signature',
		'ecommerce_givex_password',
	) as $column) {
		pg_assert_true(in_array($column, $fields, true), $column);
	}
}

// A blank or masked box keeps the stored key; a typed one replaces it.
function test_gateway_masked_for_save()
{
	pg_assert_same('sk_live_abc', pg_gateway_masked_for_save('', 'sk_live_abc'));
	pg_assert_same('sk_live_abc', pg_gateway_masked_for_save('  ', 'sk_live_abc'));
	pg_assert_same('sk_live_abc', pg_gateway_masked_for_save(pg_gateway_secret_mask(), 'sk_live_abc'));
	pg_assert_same('sk_live_new', pg_gateway_masked_for_save(' sk_live_new ', 'sk_live_abc'));
	pg_assert_same('', pg_gateway_masked_for_save('', ''));
	pg_assert_same('', pg_gateway_masked_for_save(null, ''));
}

// The Givex password follows only the hostnames it was saved for.
function test_givex_password_for_save()
{
	$old = array('Primary.Givex.com', 'secondary.givex.com');

	// Same hosts (case and spaces ignored): kept.
	pg_assert_same(array('value' => 'pw', 'refused' => false), pg_givex_password_for_save('', 'pw', $old, array(' primary.givex.com ', 'SECONDARY.givex.com')));
	pg_assert_same(array('value' => 'pw', 'refused' => false), pg_givex_password_for_save(pg_gateway_secret_mask(), 'pw', $old, $old));

	// A changed or added host with an empty box: refused, nothing changes.
	pg_assert_same(array('value' => 'pw', 'refused' => true), pg_givex_password_for_save('', 'pw', $old, array('evil.example', 'secondary.givex.com')));
	pg_assert_same(array('value' => 'pw', 'refused' => true), pg_givex_password_for_save('', 'pw', $old, array('primary.givex.com', 'evil.example')));
	pg_assert_same(array('value' => 'pw', 'refused' => true), pg_givex_password_for_save('', 'pw', array('primary.givex.com', ''), array('primary.givex.com', 'evil.example')));

	// Both hosts cleared: the password is dropped, there is nowhere to send it.
	pg_assert_same(array('value' => '', 'refused' => false), pg_givex_password_for_save('', 'pw', $old, array('', ' ')));

	// A typed password goes with any host; nothing stored, nothing kept.
	pg_assert_same(array('value' => 'new', 'refused' => false), pg_givex_password_for_save('new', 'pw', $old, array('evil.example', '')));
	pg_assert_same(array('value' => '', 'refused' => false), pg_givex_password_for_save('', '', $old, array('evil.example', '')));
}

// The card prints the mask for every listed key, never the stored value, and
// the save keeps them through pg_gateway_masked_for_save().
function test_gateway_masked_screen_source()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/commerce.php');
	$save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/commerce.save.php');

	foreach (pg_gateway_masked_fields() as $column) {
		pg_assert_contains('value="\' . h(pg_gateway_secret_shown($' . $column . ')) . \'"', $screen, $column);
		pg_assert_false(strpos($screen, 'value="\' . h($' . $column . ') . \'"') !== false, $column);
		pg_assert_false(strpos($save, "escape(trim(post_value('" . $column . "')))") !== false, $column);
	}

	pg_assert_contains('pg_givex_password_for_save(', $save);
}
