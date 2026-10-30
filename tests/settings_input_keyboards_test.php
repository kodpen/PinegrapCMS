<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the phone keyboard of the Site Settings and Workspace mailbox
 * boxes. Web addresses and host names open the URL keyboard, comma lists of
 * addresses the e-mail keyboard, the Merchant shipping rate a decimal pad and
 * the return window a number pad. Values stored or compared as typed (URLs,
 * host and user names, bucket, region, prefix, access key) turn off
 * auto-capitalisation and spell checking, since a phone would otherwise
 * write 'Https://' or 'Us-east-1'. Every box stays type="text": the saves
 * also take '/cookie-policy', 'www.example.com' and 'host:port'.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The opening tag of the input whose name attribute is the given name.
function settings_input_keyboards_test_tag($file, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

	if (!preg_match('/<input\b[^>]*\bname="' . preg_quote($name, '/') . '"[^>]*>/', $source, $match)) {
		return '';
	}

	return $match[0];
}

// Checks one box: still type="text", the given inputmode (or none) and,
// when asked, no auto-capitalisation and no spell checking.
function settings_input_keyboards_test_box($file, $name, $inputmode, $literal)
{
	$tag = settings_input_keyboards_test_tag($file, $name);
	$label = $file . ' ' . $name;

	pg_assert_true($tag !== '', $label . ' is found');
	pg_assert_contains('type="text"', $tag, $label . ' stays a text box');

	if ($inputmode !== '') {
		pg_assert_contains('inputmode="' . $inputmode . '"', $tag, $label . ' opens the ' . $inputmode . ' keyboard');
	}

	if ($literal) {
		pg_assert_contains('spellcheck="false"', $tag, $label . ' is not spell checked');
		pg_assert_contains('autocapitalize="off"', $tag, $label . ' is not auto-capitalised');
	}
}

function test_settings_input_keyboards_web_addresses_and_hosts()
{
	$boxes = array(
		array('includes/settings/seo.php', 'cookie_consent_policy_url'),
		array('includes/settings/seo.php', 'product_feed_seller_url'),
		array('includes/settings/seo.php', 'product_feed_privacy_policy_url'),
		array('includes/settings/seo.php', 'product_feed_terms_url'),
		array('includes/settings/seo.php', 'stats_url'),
		array('includes/settings/general.php', 'hostname'),
		array('includes/settings/general.php', 'proxy_address'),
		array('includes/settings/general.php', 'backup_ftp_host'),
		array('includes/settings/general.php', 'backup_s3_endpoint'),
		array('includes/settings/commerce.php', 'erp_web_address'),
		array('includes/settings/features.php', 'signature_tsa_url'),
		array('includes/workspace/mail_settings.php', 'auth_server'),
		array('includes/workspace/mail_settings.php', 'host'),
		array('includes/workspace/mail_settings.php', 'smtp_host'),
	);

	foreach ($boxes as $box) {
		settings_input_keyboards_test_box($box[0], $box[1], 'url', true);
	}
}

function test_settings_input_keyboards_names_kept_as_typed()
{
	$boxes = array(
		array('includes/settings/general.php', 'backup_ftp_user'),
		array('includes/settings/general.php', 'backup_ftp_path'),
		array('includes/settings/general.php', 'backup_s3_region'),
		array('includes/settings/general.php', 'backup_s3_bucket'),
		array('includes/settings/general.php', 'backup_s3_prefix'),
		array('includes/settings/general.php', 'backup_s3_access_key'),
		array('includes/settings/features.php', 'signature_tsa_username'),
		array('includes/workspace/mail_settings.php', 'user'),
	);

	foreach ($boxes as $box) {
		settings_input_keyboards_test_box($box[0], $box[1], '', true);
	}
}

function test_settings_input_keyboards_address_lists()
{
	settings_input_keyboards_test_box('includes/settings/commerce.php', 'erp_overdue_notify_recipients', 'email', true);
	settings_input_keyboards_test_box('includes/workspace/mail_route.php', 'route_match[]', 'email', true);
}

function test_settings_input_keyboards_merchant_numbers()
{
	settings_input_keyboards_test_box('includes/settings/prep.php', 'merchant_shipping_rate', 'decimal', false);
	settings_input_keyboards_test_box('includes/settings/prep.php', 'merchant_return_days', 'numeric', false);
}
