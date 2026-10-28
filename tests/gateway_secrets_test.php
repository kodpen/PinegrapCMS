<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the stored payment gateway secrets (includes/fn/payments.php): the
 * sealed form and the plain value older sites still hold both read back, a
 * blank or masked box keeps the stored secret, a key reset re-seals it, a
 * value whose sealed form would not fit its column is refused; and, read from
 * the source, the settings screen never prints a stored secret into value=.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A sealed secret opens with the key it was sealed with and with no other;
// the stored text never holds the secret itself.
function test_gateway_secret_seal_and_open_round_trip()
{
	$sealed = pg_gateway_secret_seal('Tk-12345', 'key-a');

	pg_assert_same(0, strpos($sealed, 'pgenc1:'));
	pg_assert_false(strpos($sealed, 'Tk-12345') !== false);
	pg_assert_same('Tk-12345', pg_gateway_secret_open($sealed, 'key-a'));
	pg_assert_same(null, pg_gateway_secret_open($sealed, 'key-b'));
	pg_assert_same(null, pg_gateway_secret_open($sealed, ''));
	pg_assert_same(null, pg_gateway_secret_open('pgenc1:not-base64', 'key-a'));
}

// A plain value saved before the change reads back as it is, so a running
// site keeps taking payments before anyone opens the settings screen.
function test_gateway_secret_open_keeps_a_plain_value()
{
	pg_assert_same('sandbox-abc:def', pg_gateway_secret_open('sandbox-abc:def', 'key-a'));
	pg_assert_same('plain', pg_gateway_secret_open('plain', ''));
	pg_assert_same('', pg_gateway_secret_open('', 'key-a'));
}

// Without a site key nothing can be sealed, so the value stays as it was
// stored before.
function test_gateway_secret_seal_without_key_stays_plain()
{
	pg_assert_same('Tk-12345', pg_gateway_secret_seal('Tk-12345', ''));
	pg_assert_same('', pg_gateway_secret_seal('', 'key-a'));
}

// The screen draws a mask for a stored secret and an empty box for none.
function test_gateway_secret_shown_is_masked()
{
	pg_assert_same(pg_gateway_secret_mask(), pg_gateway_secret_shown(pg_gateway_secret_seal('Tk-12345', 'key-a')));
	pg_assert_same(pg_gateway_secret_mask(), pg_gateway_secret_shown('legacy-plain'));
	pg_assert_same('', pg_gateway_secret_shown(''));
}

// A blank or masked box keeps the stored secret: a sealed one is written back
// unchanged (no new IV, no audit line), a plain one is sealed.
function test_gateway_secret_for_save_blank_or_mask_keeps()
{
	$sealed = pg_gateway_secret_seal('Tk-12345', 'key-a');

	pg_assert_same($sealed, pg_gateway_secret_for_save('', $sealed, 'key-a', 'key-a', 100));
	pg_assert_same($sealed, pg_gateway_secret_for_save('  ' . pg_gateway_secret_mask() . ' ', $sealed, 'key-a', 'key-a', 100));

	$upgraded = pg_gateway_secret_for_save('', 'Tk-12345', 'key-a', 'key-a', 100);
	pg_assert_same(0, strpos($upgraded, 'pgenc1:'));
	pg_assert_same('Tk-12345', pg_gateway_secret_open($upgraded, 'key-a'));

	pg_assert_same('', pg_gateway_secret_for_save('', '', 'key-a', 'key-a', 100));
}

// A new value replaces the stored one, sealed and trimmed.
function test_gateway_secret_for_save_new_value_replaces()
{
	$sealed = pg_gateway_secret_seal('Tk-12345', 'key-a');
	$saved = pg_gateway_secret_for_save(' New-Key-9 ', $sealed, 'key-a', 'key-a', 100);

	pg_assert_same('New-Key-9', pg_gateway_secret_open($saved, 'key-a'));
}

// A key reset in the same save seals the kept secret with the new key, so the
// gateway still reads it on the next request.
function test_gateway_secret_for_save_reseals_on_key_reset()
{
	$sealed = pg_gateway_secret_seal('Tk-12345', 'old-key');
	$saved = pg_gateway_secret_for_save('', $sealed, 'old-key', 'new-key', 100);

	pg_assert_same('Tk-12345', pg_gateway_secret_open($saved, 'new-key'));
}

// A sealed value that no longer opens (the key was lost) is left as it is
// rather than wiped by an unrelated save.
function test_gateway_secret_for_save_unreadable_is_left_alone()
{
	$sealed = pg_gateway_secret_seal('Tk-12345', 'lost-key');

	pg_assert_same($sealed, pg_gateway_secret_for_save('', $sealed, 'key-a', 'key-a', 100));
}

// The sealed form must fit the column: up to 47 characters fit a 100-wide
// column, 48 do not; a kept plain value too long to seal stays plain.
function test_gateway_secret_fits_column_width()
{
	pg_assert_true(pg_gateway_secret_fits(str_repeat('a', 47), 100));
	pg_assert_false(pg_gateway_secret_fits(str_repeat('a', 48), 100));
	pg_assert_true(pg_gateway_secret_fits(str_repeat('a', 100), 255));
	pg_assert_true(pg_gateway_secret_fits('', 100));
	pg_assert_same(47, pg_gateway_secret_max_length(100));
	pg_assert_same(159, pg_gateway_secret_max_length(255));

	$long = str_repeat('b', 60);
	pg_assert_same($long, pg_gateway_secret_for_save('', $long, 'key-a', 'key-a', 100));
}

// Every secret column is listed with its width.
function test_gateway_secret_fields_listed()
{
	$fields = pg_gateway_secret_fields();

	pg_assert_same(100, $fields['ecommerce_authorizenet_transaction_key']);
	pg_assert_same(100, $fields['ecommerce_paypal_payflow_pro_password']);
	pg_assert_same(100, $fields['ecommerce_paypal_payments_pro_api_password']);
	pg_assert_same(100, $fields['ecommerce_paypal_express_checkout_api_password']);
	pg_assert_same(255, $fields['ecommerce_iyzipay_secret_key']);
}

// The settings screen prints the mask, never the stored secret, and the
// constants the gateways read are opened from the stored form.
function test_gateway_secret_screen_and_init_source()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/commerce.php');
	$prep = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/prep.php');
	$init = (string) file_get_contents(PG_FUNCTIONS_DIR . '/init.php');

	foreach (array_keys(pg_gateway_secret_fields()) as $column) {
		pg_assert_contains('$' . $column . ' = pg_gateway_secret_shown($row[\'' . $column . '\']);', $prep);
		pg_assert_contains('define(\'' . strtoupper($column) . '\', pg_gateway_secret_value($row[\'' . $column . '\']));', $init);
		pg_assert_true(strpos($screen, 'id="' . $column . '"') !== false);
	}
}
