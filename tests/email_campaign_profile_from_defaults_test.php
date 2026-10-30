<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Create Campaign Profile From defaults: the organization name
 * and the user's e-mail address go to liveform raw, because liveform escapes
 * a text input's value itself.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!class_exists('liveform', false)) {
	require_once(PG_FUNCTIONS_DIR . '/liveform.class.php');
}

// The liveform output_field() call that names the field, up to the end of
// its options array.
function _test_profile_from_defaults_field($source, $name)
{
	if (preg_match('/output_field\(array\((?:(?!output_field\().)*?\'name\'\s*=>\s*\'' . preg_quote($name, '/') . '\'.*?\)\)/s', $source, $match)) {
		return $match[0];
	}

	return '';
}

// The form passes the From defaults raw, never through h().
function test_email_campaign_profile_from_defaults_are_raw()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/forms/email_campaign_profile_form.php');

	pg_assert_true(!preg_match('/\'value\'\s*=>\s*h\(/', $source), 'no liveform value is escaped before liveform escapes it');
	pg_assert_true(strpos(_test_profile_from_defaults_field($source, 'from_name'), "'value'=>ORGANIZATION_NAME,") !== false, 'From Name defaults to the raw organization name');

	foreach (array('from_email_address', 'reply_email_address') as $name) {
		pg_assert_true(strpos(_test_profile_from_defaults_field($source, $name), "'value'=>\$user['email_address'],") !== false, $name . ' defaults to the raw user e-mail address');
	}
}

// liveform escapes a text input's value once.
function test_email_campaign_profile_from_default_escaped_once()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();

	$liveform = new liveform('pg_test_from_defaults');
	$html = $liveform->output_field(array('type'=>'text', 'name'=>'from_name', 'value'=>'Ali & Veli'));

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}

	pg_assert_true(strpos($html, 'value="Ali &amp; Veli"') !== false, 'the value is escaped once');
	pg_assert_true(strpos($html, '&amp;amp;') === false, 'the value is not escaped twice');
}
