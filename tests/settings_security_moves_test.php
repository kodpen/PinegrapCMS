<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Accounts and sign-in, Firewall and HTTPS: the two categories keep their
 * keys under the new names; the encryption key, the trusted proxies and the
 * blocked e-mail addresses sit on their own cards with the same post names,
 * and each is written by the save file of the pane that draws it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function settings_security_moves_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// One category's entry in pg_settings_categories(), cut out of the source:
// the list asks the database, so it is read rather than called.
function settings_security_moves_test_category($key)
{
	$source = settings_security_moves_test_source('includes/settings/registry.php');
	$start = strpos($source, "\n        '" . $key . "' => array(");

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n        ),\n", $start);

	return substr($source, $start, $end - $start);
}

// The section ids of a category entry, in the order they are listed.
function settings_security_moves_test_sections($key)
{
	$entry = settings_security_moves_test_category($key);
	$start = strpos($entry, "'sections' => array(");
	$end = strpos($entry, "'keywords' => array(");
	preg_match_all("/'(pgset-[a-z0-9-]+)'\\s*=>/", substr($entry, $start, $end - $start), $match);

	return $match[1];
}

// One card of a category file: from its id to the next card.
function settings_security_moves_test_card($file, $id)
{
	$source = settings_security_moves_test_source($file);
	$start = strpos($source, '<div id="' . $id . '" class="pg-set-card">');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, '$pg_settings_cards[]', $start);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_settings_security_moves_labels_and_keys()
{
	pg_assert_contains("'label' => lang('Accounts and sign-in')", settings_security_moves_test_category('security'), 'security is Accounts and sign-in');
	pg_assert_contains("'label' => lang('Firewall and HTTPS')", settings_security_moves_test_category('firewall'), 'firewall is Firewall and HTTPS');

	$source = settings_security_moves_test_source('includes/settings/registry.php');
	$order = array();

	foreach (array('contact', 'security', 'firewall', 'commerce') as $key) {
		$order[$key] = strpos($source, "\n        '" . $key . "' => array(");
		pg_assert_true($order[$key] !== false, $key . ' is still a category key');
	}

	pg_assert_true(($order['contact'] < $order['security']) && ($order['security'] < $order['firewall']) && ($order['firewall'] < $order['commerce']), 'the categories keep their place');
}

function test_settings_security_moves_card_order()
{
	pg_assert_same(
		array('pgset-session', 'pgset-captcha', 'pgset-device', 'pgset-mfa', 'pgset-audit', 'pgset-signin', 'pgset-membership', 'pgset-encryption'),
		settings_security_moves_test_sections('security'));
	pg_assert_same(
		array('pgset-waf', 'pgset-proxies', 'pgset-bots', 'pgset-iplists', 'pgset-countries', 'pgset-headers'),
		settings_security_moves_test_sections('firewall'));

	// The pane draws the cards in the order the registry lists them.
	foreach (array('security' => 'includes/settings/security.php', 'firewall' => 'includes/settings/firewall.php') as $key => $file) {
		$source = settings_security_moves_test_source($file);
		$last = -1;

		foreach (settings_security_moves_test_sections($key) as $id) {
			$at = strpos($source, '<div id="' . $id . '" class="pg-set-card">');
			pg_assert_true(($at !== false) && ($at > $last), $id . ' is drawn in its place in ' . $file);
			$last = (int) $at;
		}
	}
}

function test_settings_security_moves_encryption_key()
{
	$card = settings_security_moves_test_card('includes/settings/security.php', 'pgset-encryption');
	pg_assert_contains('name="ecommerce_reset_encryption_key"', $card, 'the switch keeps its post name');

	pg_assert_false(strpos(settings_security_moves_test_source('includes/settings/commerce.php'), 'ecommerce_reset_encryption_key') !== false, 'Payment Methods no longer draws the switch');
	pg_assert_false(strpos(settings_security_moves_test_source('includes/settings/commerce.save.php'), 'ecommerce_reset_encryption_key') !== false, 'the Ecommerce save no longer resets the key');
	pg_assert_false(strpos(settings_security_moves_test_source('includes/settings/commerce.save.php'), 'generate_encryption_key(') !== false, 'the Ecommerce save makes no key');

	$save = settings_security_moves_test_source('includes/settings/security.save.php');
	pg_assert_contains("post_value('ecommerce_reset_encryption_key')", $save, 'the Accounts and sign-in save reads the switch');
	pg_assert_contains('generate_encryption_key()', $save, 'and makes the key');
	pg_assert_contains('encrypt_credit_card_number(', $save, 'the stored card numbers follow the new key');
	pg_assert_contains('pg_gateway_secret_for_save(', $save, 'the gateway secrets follow the new key');
}

function test_settings_security_moves_mfa_hint()
{
	$card = settings_security_moves_test_card('includes/settings/security.php', 'pgset-mfa');
	pg_assert_false(strpos($card, 'Payment Methods') !== false, 'the hint no longer sends to Payment Methods');
	pg_assert_contains("pg_settings_link('security', 'pgset-encryption')", $card, 'the hint links the Encryption Key card');
}

function test_settings_security_moves_trusted_proxies()
{
	pg_assert_false(strpos(settings_security_moves_test_card('includes/settings/firewall.php', 'pgset-waf'), 'waf_trusted_proxies') !== false, 'Trusted Proxies is no longer under Enable Firewall');
	pg_assert_contains('name="waf_trusted_proxies"', settings_security_moves_test_card('includes/settings/firewall.php', 'pgset-proxies'), 'its own card keeps the post name');
	pg_assert_contains("post_value('waf_trusted_proxies')", settings_security_moves_test_source('includes/settings/firewall.save.php'), 'the Firewall save still writes it');
}

function test_settings_security_moves_blocked_email_addresses()
{
	pg_assert_false(strpos(settings_security_moves_test_source('includes/settings/firewall.php'), 'banned_email_addresses') !== false, 'IP Lists no longer draws the blocked e-mail addresses');
	pg_assert_false(strpos(settings_security_moves_test_source('includes/settings/firewall.save.php'), 'banned_email_addresses') !== false, 'the Firewall save no longer writes them');
	pg_assert_contains('name="banned_email_addresses"', settings_security_moves_test_card('includes/settings/security.php', 'pgset-captcha'), 'Spam Protection draws them under the same post name');

	$save = settings_security_moves_test_source('includes/settings/security.save.php');
	pg_assert_contains("post_value('banned_email_addresses')", $save, 'the Accounts and sign-in save reads them');
	pg_assert_contains('UPDATE config SET banned_email_addresses', $save, 'and writes the same column');
	pg_assert_contains('pg_auth_token_revoke_user(', $save, 'a blocked member still loses the open sessions');

	pg_assert_contains("'pgset-captcha'", settings_security_moves_test_category('security'), 'Spam Protection is in Accounts and sign-in');
	pg_assert_false(strpos(settings_security_moves_test_category('firewall'), 'blocked email') !== false, 'IP Lists keywords no longer name blocked e-mail');
}
