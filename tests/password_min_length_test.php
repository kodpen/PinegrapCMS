<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the password a person chooses while "Require Strong Password" is
 * off: at least pg_password_min_length() characters (pg_password_refusal(),
 * includes/fn/auth.php). Before, any length was taken, one character
 * included. With the setting on, the strength rules decide as before. And
 * every screen where a password is chosen asks the one function.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_password_min_length_off()
{
	pg_assert_same(8, pg_password_min_length());

	$short = pg_password_refusal('abc', false, 'strong');
	pg_assert_contains('8', $short, 'the minimum is named');
	pg_assert_same('The password must be at least 8 characters long.', $short);
	pg_assert_same($short, pg_password_refusal('', false, 'strong'), 'nothing is too short');
	pg_assert_same($short, pg_password_refusal('abcdefg', false, 'strong'), 'seven');
	pg_assert_same('', pg_password_refusal('abcdefgh', false, 'strong'), 'eight');
	pg_assert_same('', pg_password_refusal('correct horse battery staple', false, 'strong'), 'no other rule');
}

// Characters, not bytes: "şifreçöz" is eight letters in sixteen bytes; seven
// emoji are seven characters.
function test_password_min_length_counts_characters()
{
	pg_assert_same('', pg_password_refusal('şifreçöz', false, ''));
	pg_assert_true(pg_password_refusal(str_repeat("\u{1F511}", 7), false, '') !== '', 'seven emoji');
	pg_assert_same('', pg_password_refusal(str_repeat("\u{1F511}", 8), false, ''));
}

// Strong passwords on: the strength rules and the screen's own sentence.
function test_password_min_length_strong_on()
{
	pg_assert_same('Too weak.', pg_password_refusal('abcdefgh', true, 'Too weak.'), 'long enough is not strong');
	pg_assert_same('', pg_password_refusal('Abcdefgh12!x', true, 'Too weak.'));
	pg_assert_same(validate_password_strength('Abcdefgh12!x'), pg_password_refusal('Abcdefgh12!x', true, 'x') === '');
}

// Sign-up, membership activation, set password, change password and the
// installer's administrator ask pg_password_refusal(); no screen keeps its
// own strength check.
function test_password_min_length_every_screen()
{
	$auth = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/auth.php');
	pg_assert_same(2, substr_count($auth, 'pg_password_refusal($password, (STRONG_PASSWORD == true)'), 'pg_member_register() and pg_member_activate()');

	foreach (array('set_password.php', 'change_password.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_password_refusal(', $source, $file);
		pg_assert_false(strpos($source, 'validate_password_strength(') !== false, $file . ' has no check of its own');
	}

	$install = (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');
	pg_assert_contains("pg_password_refusal(\$liveform->get_field_value('admin_password'), false, '')", $install, 'the installer');
	pg_assert_contains('Array.from($("#admin_password").val()).length < \' . (int) pg_password_min_length() . \'', $install, 'and its step check');

	pg_assert_same(2, substr_count($auth, 'validate_password_strength($'), 'auth.php: the definition and pg_password_refusal()');
}
