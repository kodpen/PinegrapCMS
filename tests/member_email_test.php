<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the address asked once on sign-up: the registration entrance
 * (pg_member_register()) and the membership entrance (pg_member_activate())
 * do not require the address a second time (email_verify, or
 * email_address_verify on the membership entrance's own screen), and a
 * second copy that was typed in still has to match the first
 * (pg_member_email_repeat_ok()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Posts $fields to pg_member_register() or pg_member_activate() in a PHP
// process of its own and returns the fields the function required and the
// messages the form ended with, by field ('' for a field marked without
// one). Its own process, because the two read CAPTCHA, STRONG_PASSWORD and
// MEMBER_ID_LABEL, which only a site's configuration defines and which the
// tests after this one must not find defined. Every example.com address is
// on the block list there, so each case stops at an error before the first
// query and no database is needed. Null when PHP cannot be started again.
function member_email_test_post($function, $form_name, $fields)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgmember');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, '<?php' . "\n"
		. "define('PG_TEST_RUNNER', true);\n"
		. 'require ' . var_export(PG_FUNCTIONS_DIR . '/functions.php', true) . ";\n"
		. 'require_once ' . var_export(PG_FUNCTIONS_DIR . '/liveform.class.php', true) . ";\n"
		. "define('CAPTCHA', false);\n"
		. "define('STRONG_PASSWORD', false);\n"
		. "define('MEMBER_ID_LABEL', 'Member ID');\n"
		. "define('BANNED_EMAIL_ADDRESSES', 'example.com');\n"
		. "class member_email_test_form extends liveform\n"
		. "{\n"
		. "\tpublic \$required = array();\n"
		. "\tfunction validate_required_field(\$field, \$error_message = '')\n"
		. "\t{\n"
		. "\t\t\$this->required[] = \$field;\n"
		. "\t\treturn parent::validate_required_field(\$field, \$error_message);\n"
		. "\t}\n"
		. "}\n"
		. "\$_SESSION = array();\n"
		. '$_REQUEST = ' . var_export($fields, true) . ";\n"
		. '$form = new member_email_test_form(' . var_export($form_name, true) . ");\n"
		. "\$form->add_fields_to_session();\n"
		. $function . "(\$form);\n"
		. "\$errors = array();\n"
		. 'foreach ($_SESSION[\'software\'][\'liveforms\'][' . var_export($form_name, true) . '][0] as $name => $field) {' . "\n"
		. "\tif (is_array(\$field) && !empty(\$field['error'])) {\n"
		. "\t\t\$errors[\$name] = (string) \$field['error_message'];\n"
		. "\t}\n"
		. "}\n"
		. "echo json_encode(array('required' => \$form->required, 'errors' => \$errors));\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $out), true);

	if (($code !== 0) || !is_array($result)) {
		return array('error' => implode("\n", $out));
	}

	return $result;
}

// The message on one field after a post, null when it has none.
function member_email_test_message($result, $field)
{
	return isset($result['errors'][$field]) ? $result['errors'][$field] : null;
}

// A second copy that is absent or left empty is no answer; one typed in is
// compared as typed (the form has trimmed both).
function test_member_email_repeat_ok()
{
	pg_assert_true(pg_member_email_repeat_ok('jane@example.com', null), 'no second field');
	pg_assert_true(pg_member_email_repeat_ok('jane@example.com', ''), 'left empty');
	pg_assert_true(pg_member_email_repeat_ok('jane@example.com', 'jane@example.com'), 'the same address');
	pg_assert_false(pg_member_email_repeat_ok('jane@example.com', 'jane@example.org'), 'another address');
	pg_assert_false(pg_member_email_repeat_ok('jane@example.com', 'Jane@example.com'), 'other capitals');
	pg_assert_false(pg_member_email_repeat_ok('jane@example.com', array('jane@example.com')), 'an array');
	pg_assert_false(pg_member_email_repeat_ok('', 'jane@example.com'), 'typed the second time only');
}

// Registration: the second field is not required, and the address goes on
// to its next check (here the block list) when the second copy is absent,
// empty or the same; a copy typed differently stops it there.
function test_member_register_asks_the_address_once()
{
	$base = array(
		'first_name' => 'Jane',
		'last_name' => 'Cooper',
		'email' => 'jane@example.com',
		'password' => 'a',
		'password_verify' => 'b',
	);
	$blocked = 'This email address cannot be used on this site.';

	$result = member_email_test_post('pg_member_register', 'register', $base);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_contains('email', $result['required'], 'the address is required');
	pg_assert_false(in_array('email_verify', $result['required'], true), 'the second copy is required');
	pg_assert_same($blocked, member_email_test_message($result, 'email'), 'no second field');
	pg_assert_same(null, member_email_test_message($result, 'email_verify'), 'no second field: marked');

	$result = member_email_test_post('pg_member_register', 'register', $base + array('email_verify' => ''));
	pg_assert_same($blocked, member_email_test_message($result, 'email'), 'left empty');
	pg_assert_same(null, member_email_test_message($result, 'email_verify'), 'left empty: marked');

	$result = member_email_test_post('pg_member_register', 'register', $base + array('email_verify' => 'jane@example.com'));
	pg_assert_same($blocked, member_email_test_message($result, 'email'), 'the same address');

	$result = member_email_test_post('pg_member_register', 'register', $base + array('email_verify' => 'jane@example.org'));
	pg_assert_same('The two email addresses you entered did not match.', member_email_test_message($result, 'email'), 'another address');
	pg_assert_same('', member_email_test_message($result, 'email_verify'), 'another address: the second field is marked');
}

// Membership activation reads email / email_verify, or the entrance screen's
// email_address / email_address_verify; neither second field is required.
function test_member_activate_asks_the_address_once()
{
	// No member number: the lookup of the membership, a query, is skipped.
	$base = array(
		'first_name' => 'Jane',
		'last_name' => 'Cooper',
		'password' => 'a',
		'password_verify' => 'b',
	);
	$blocked = 'This email address cannot be used on this site.';

	$result = member_email_test_post('pg_member_activate', 'membership_entrance', $base + array('email' => 'jane@example.com'));

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_contains('email', $result['required'], 'designed form: the address is required');
	pg_assert_false(in_array('email_verify', $result['required'], true), 'designed form: the second copy is required');
	pg_assert_same($blocked, member_email_test_message($result, 'email'), 'designed form: no second field');

	$result = member_email_test_post('pg_member_activate', 'membership_entrance', $base + array('email_address' => 'jane@example.com'));
	pg_assert_contains('email_address', $result['required'], 'entrance screen: the address is required');
	pg_assert_false(in_array('email_address_verify', $result['required'], true), 'entrance screen: the second copy is required');
	pg_assert_same($blocked, member_email_test_message($result, 'email_address'), 'entrance screen: no second field');

	$result = member_email_test_post('pg_member_activate', 'membership_entrance',
		$base + array('email_address' => 'jane@example.com', 'email_address_verify' => 'jane@example.org'));
	pg_assert_same('The two email addresses you entered did not match.', member_email_test_message($result, 'email_address'), 'entrance screen: another address');
}
