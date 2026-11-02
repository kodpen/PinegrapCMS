<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the time stamp authority password of Site Settings › Features:
 * the box is drawn empty, an empty box keeps the stored password, the remove
 * tick clears it (pg_signature_tsa_password_to_store() in
 * includes/fn/signature.php), and the card never prints the stored value.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_signature_tsa_password_to_store()
{
	pg_assert_same('stored', pg_signature_tsa_password_to_store('', 'stored', false), 'an empty box keeps the stored password');
	pg_assert_same('new', pg_signature_tsa_password_to_store('new', 'stored', false), 'a typed password replaces it');
	pg_assert_same('', pg_signature_tsa_password_to_store('', 'stored', true), 'the remove tick clears it');
	pg_assert_same('', pg_signature_tsa_password_to_store('new', 'stored', true), 'the tick wins over a typed password');
	pg_assert_same('', pg_signature_tsa_password_to_store('', '', false), 'nothing stored, nothing typed');
}

function test_signature_tsa_password_never_printed()
{
	$card = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/features.php');
	pg_assert_false(strpos($card, 'h($signature_tsa_password)') !== false, 'the stored password is not printed');
	pg_assert_contains('name="signature_tsa_password" id="signature_tsa_password" maxlength="100" class="form-control" value=""', $card);
	pg_assert_contains('name="signature_tsa_password_clear"', $card);

	$save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/features.save.php');
	pg_assert_contains("signature_tsa_password = '\" . escape(\$signature_tsa_password_new) . \"'", $save);
	pg_assert_contains("'password' => \$signature_tsa_password_new,", $save, 'the test button uses the kept password');
}
