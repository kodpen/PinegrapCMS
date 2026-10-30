<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the SMTP Settings boxes that open the right phone keyboard:
 * host names and the DKIM domain use the URL keyboard, user names and the
 * DKIM selector skip spellcheck and auto-capitalisation, and all of them stay
 * type=text (they take host:port or bare names).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function smtp_settings_keyboards_field($name)
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/smtp_settings.php');

	// [^\]] spans the multi-line DKIM arrays without crossing the previous call's closing bracket.
	if (!preg_match("/output_field\\(\\[[^\\]]*'name'\\s*=>\\s*'" . preg_quote($name, '/') . "'[^\\]]*\\]\\)/", $source, $match)) {
		return '';
	}

	return $match[0];
}

function test_smtp_settings_keyboards_hosts_and_domain()
{
	foreach (array('system_smtp_hostname', 'campaign_smtp_hostname', 'dkim_domain') as $name) {
		$field = smtp_settings_keyboards_field($name);
		pg_assert_contains("'type'=>'text'", $field, $name . ': still type=text');
		pg_assert_contains("'inputmode'=>'url'", $field, $name);
		pg_assert_contains("'spellcheck'=>'false'", $field, $name);
		pg_assert_contains("'autocapitalize'=>'off'", $field, $name);
	}
}

function test_smtp_settings_keyboards_user_names_and_selector()
{
	foreach (array('system_smtp_username', 'campaign_smtp_username', 'dkim_selector') as $name) {
		$field = smtp_settings_keyboards_field($name);
		pg_assert_contains("'type'=>'text'", $field, $name . ': still type=text');
		pg_assert_contains("'spellcheck'=>'false'", $field, $name);
		pg_assert_contains("'autocapitalize'=>'off'", $field, $name);
		pg_assert_false(strpos($field, 'inputmode') !== false, $name . ': no inputmode');
	}

	pg_assert_contains("'autocomplete'=>'new-password'", smtp_settings_keyboards_field('system_smtp_username'));
	pg_assert_contains("'autocomplete'=>'new-password'", smtp_settings_keyboards_field('campaign_smtp_username'));
}

function test_smtp_settings_keyboards_passwords_untouched()
{
	foreach (array('system_smtp_password', 'campaign_smtp_password') as $name) {
		$field = smtp_settings_keyboards_field($name);
		pg_assert_contains("'type'=>'password'", $field, $name);
		pg_assert_false(strpos($field, 'inputmode') !== false, $name . ': no inputmode');
		pg_assert_false(strpos($field, 'autocapitalize') !== false, $name . ': no autocapitalize');
	}
}
