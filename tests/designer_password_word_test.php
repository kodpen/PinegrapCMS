<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Turkish word the visual editor uses for a password: every
 * _sdT() text of the editor and the account e-mails says "parola", never
 * the old "şifre" (encryption words such as "şifreleme" and "şifreli"
 * stay).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

// "şifre" as a password word; "şifrele…" and "şifreli" are encryption.
function _dpw_old_word($text)
{
	return preg_match('/[Şş]ifre(?!le|li)/u', $text) === 1;
}

function test_designer_password_word_pattern()
{
	pg_assert_true(_dpw_old_word('Şifremi değiştir'), 'Şifremi');
	pg_assert_true(_dpw_old_word('parola yöneticinizden, şifreyle'), 'şifreyle');
	pg_assert_true(!_dpw_old_word('AES-256 şifreleme'), 'şifreleme stays');
	pg_assert_true(!_dpw_old_word('Uçtan uca şifreli veriler'), 'şifreli stays');
}

function test_designer_password_word_editor_texts_say_parola()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	preg_match_all("/_sdT\('((?:[^'\\\\]|\\\\.)*)'/", $js, $m);
	$keys = array_unique(array_map('stripslashes', $m[1]));
	pg_assert_true(count($keys) > 1000, 'the editor texts are found');
	pg_assert_true(in_array('Change my password', $keys, true), 'used: Change my password');
	$tr = pg_test_language_map('tr');
	foreach ($keys as $key) {
		if (isset($tr[$key])) {
			pg_assert_true(!_dpw_old_word($tr[$key]), 'Parola, not Şifre: ' . $tr[$key]);
		}
	}
}

function test_designer_password_word_account_mails_say_parola()
{
	$tr = pg_test_language_map('tr');
	foreach (array(
		'We received a request to reset your password. You can reset your password by clicking the link below.',
		'If you did not make this request, then you may safely ignore this email, and your password will remain the same.',
		'Sorry, password reset is not available on this website right now.',
		'To choose your password, use',
		'If you did not ask for it, change your password now: whoever asked for it knows your password and is signed in to your account.',
	) as $key) {
		pg_assert_true(isset($tr[$key]), 'translated: ' . $key);
		pg_assert_true(!_dpw_old_word($tr[$key]), 'Parola, not Şifre: ' . $tr[$key]);
	}
}
