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

	pg_assert_same(0, strpos($sealed, 'pgenc2:'));
	pg_assert_false(strpos($sealed, 'Tk-12345') !== false);
	pg_assert_same('Tk-12345', pg_gateway_secret_open($sealed, 'key-a'));
	pg_assert_same(null, pg_gateway_secret_open($sealed, 'key-b'));
	pg_assert_same(null, pg_gateway_secret_open($sealed, ''));
	pg_assert_same(null, pg_gateway_secret_open('pgenc2:not-base64', 'key-a'));
	pg_assert_same(null, pg_gateway_secret_open('pgenc2:' . base64_encode('short'), 'key-a'));
}

// A wrong key never opens a sealed value. Unauthenticated CBC let about one
// try in 256 through its padding check with garbage; 2,000 seals make such a
// pass all but certain to show.
function test_gateway_secret_wrong_key_never_opens()
{
	for ($i = 0; $i < 2000; $i++) {
		pg_assert_same(null, pg_gateway_secret_open(pg_gateway_secret_seal('Tk-' . $i, 'key-a'), 'key-b'));
	}
}

// A sealed value whose tag, ciphertext or IV was changed does not open.
function test_gateway_secret_tampered_does_not_open()
{
	$sealed = pg_gateway_secret_seal('Tk-12345', 'key-a');
	$raw = base64_decode(substr($sealed, 7), true);

	foreach (array(0, 12, strlen($raw) - 1) as $offset) {
		$tampered = $raw;
		$tampered[$offset] = chr(ord($tampered[$offset]) ^ 1);
		pg_assert_same(null, pg_gateway_secret_open('pgenc2:' . base64_encode($tampered), 'key-a'));
	}

	pg_assert_same(null, pg_gateway_secret_open('pgenc2:' . base64_encode(substr($raw, 0, -1)), 'key-a'));
}

// A value sealed in the first form (AES-256-CBC, "pgenc1:<cipher>:<iv>")
// still opens with its key.
function test_gateway_secret_first_form_still_opens()
{
	pg_assert_same('Tk-12345', pg_gateway_secret_open(gateway_secret_test_first_form('Tk-12345', 'key-a'), 'key-a'));
	pg_assert_same(null, pg_gateway_secret_open('pgenc1:not-base64', 'key-a'));
}

// A save that keeps a first-form secret seals it again in the authenticated
// form, unless that form would not fit its column.
function test_gateway_secret_for_save_upgrades_first_form()
{
	$first = gateway_secret_test_first_form('Tk-12345', 'key-a');
	$saved = pg_gateway_secret_for_save('', $first, 'key-a', 'key-a', 100);

	pg_assert_same(0, strpos($saved, 'pgenc2:'));
	pg_assert_same('Tk-12345', pg_gateway_secret_open($saved, 'key-a'));

	$long = gateway_secret_test_first_form(str_repeat('c', 45), 'key-a');
	pg_assert_same($long, pg_gateway_secret_for_save('', $long, 'key-a', 'key-a', 100));
}

// A value in the first sealed form, built the way it was written.
function gateway_secret_test_first_form($plain, $key)
{
	$iv = random_bytes(16);
	$cipher = openssl_encrypt($plain, 'aes-256-cbc', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);

	return 'pgenc1:' . base64_encode($cipher) . ':' . base64_encode($iv);
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
	pg_assert_same(0, strpos($upgraded, 'pgenc2:'));
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

// The sealed form must fit the column: up to 41 characters fit a 100-wide
// column, 42 do not; a kept plain value too long to seal stays plain. The
// longest value that is allowed seals into exactly the width or less.
function test_gateway_secret_fits_column_width()
{
	pg_assert_true(pg_gateway_secret_fits(str_repeat('a', 41), 100));
	pg_assert_false(pg_gateway_secret_fits(str_repeat('a', 42), 100));
	pg_assert_true(pg_gateway_secret_fits(str_repeat('a', 100), 255));
	pg_assert_true(pg_gateway_secret_fits('', 100));
	pg_assert_same(41, pg_gateway_secret_max_length(100));
	pg_assert_same(158, pg_gateway_secret_max_length(255));

	foreach (array(100, 255) as $width) {
		$max = pg_gateway_secret_max_length($width);
		pg_assert_true(strlen(pg_gateway_secret_seal(str_repeat('a', $max), 'key-a')) <= $width);
		pg_assert_true(strlen(pg_gateway_secret_seal(str_repeat('a', $max + 1), 'key-a')) > $width);
	}

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
