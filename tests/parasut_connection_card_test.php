<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Paraşüt card of the commerce settings and the connection it
 * makes (includes/fn/parasut.php): the owner stamp of a token from the
 * authorization code, the Client Secret and Password boxes drawn from what
 * is really stored, the company a code connection works in, the notice and
 * the warning Connect answers with, and the default product/service help.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of a function of includes/fn/parasut.php, read from the source.
function parasut_connection_card_test_body($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/parasut.php');
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

// Whether a source text holds a piece of code; a miss names the piece, not
// the whole file.
function parasut_connection_card_test_has($needle, $source, $label = '')
{
	pg_assert_true(strpos((string) $source, (string) $needle) !== false, (($label !== '') ? $label . ': ' : '') . 'the source holds ' . $needle);
}

// A token from the authorization code belongs to whoever signed in to
// Paraşüt; the user name box serves the password grant only. Its stamp
// leaves the user name out, so a user name typed, changed or cleared later
// does not make the token someone else's. A password token keeps the user
// name in its stamp.
function test_parasut_connection_card_code_token_owner_leaves_the_user_name_out()
{
	$code = _parasut_token_owner('830533', 'client-a', 'a@example.com', 'code');

	pg_assert_same(32, strlen($code));
	pg_assert_same($code, _parasut_token_owner('830533', 'client-a', '', 'code'));
	pg_assert_same($code, _parasut_token_owner('830533', 'client-a', 'b@example.com', 'code'));

	// The stamp a code token got while the user name box was empty: a store
	// connected that way reads its token with no change at all.
	pg_assert_same(_parasut_token_owner('830533', 'client-a', ''), $code);

	// Company and client still name the owner.
	pg_assert_true($code !== _parasut_token_owner('830534', 'client-a', '', 'code'));
	pg_assert_true($code !== _parasut_token_owner('830533', 'client-b', '', 'code'));

	// A password token: as before.
	pg_assert_same(_parasut_token_owner('830533', 'client-a', 'a@example.com'), _parasut_token_owner('830533', 'client-a', 'a@example.com', 'password'));
	pg_assert_true(_parasut_token_owner('830533', 'client-a', 'a@example.com', 'password') !== _parasut_token_owner('830533', 'client-a', 'b@example.com', 'password'));
}

// Save with a code: the user name box is written before the code is
// exchanged, so the request that connects still holds the user name from
// before. The next request reads the token with the new user name, and a
// code token is still its own.
function test_parasut_connection_card_code_token_survives_a_user_name_change()
{
	$token = array('grant' => 'code', 'access_token' => 'x', 'owner' => _parasut_token_owner('830533', 'client-a', 'old@example.com', 'code'));

	pg_assert_true(_parasut_token_owner_matches($token, '830533', 'client-a', 'me@example.com'), 'a user name typed with the code');
	pg_assert_true(_parasut_token_owner_matches($token, '830533', 'client-a', ''), 'the user name box cleared');
	pg_assert_false(_parasut_token_owner_matches($token, '830534', 'client-a', 'me@example.com'), 'another company');
	pg_assert_false(_parasut_token_owner_matches($token, '830533', 'client-b', 'me@example.com'), 'another client');

	// A password token belongs to its user name.
	$password = array('grant' => 'password', 'access_token' => 'x', 'owner' => _parasut_token_owner('830533', 'client-a', 'a@example.com'));
	pg_assert_true(_parasut_token_owner_matches($password, '830533', 'client-a', 'a@example.com'));
	pg_assert_false(_parasut_token_owner_matches($password, '830533', 'client-a', 'b@example.com'));

	// A token from before the grant was recorded is a password token.
	$bare = array('access_token' => 'x', 'owner' => _parasut_token_owner('830533', 'client-a', 'a@example.com'));
	pg_assert_true(_parasut_token_owner_matches($bare, '830533', 'client-a', 'a@example.com'));
	pg_assert_false(_parasut_token_owner_matches($bare, '830533', 'client-a', ''));

	pg_assert_false(_parasut_token_owner_matches(array(), '830533', 'client-a', ''), 'no token');
	pg_assert_false(_parasut_token_owner_matches(array('grant' => 'code', 'owner' => ''), '830533', 'client-a', ''), 'no stamp');
}

// A code token stamped before the stamp left the user name out (a store that
// had a user name saved when it connected) is still read while that user
// name is saved; the next renewal stamps it anew.
function test_parasut_connection_card_code_token_with_the_old_stamp_stays_readable()
{
	$old = array('grant' => 'code', 'access_token' => 'x', 'owner' => md5('830533|client-a|a@example.com'));

	pg_assert_true(_parasut_token_owner_matches($old, '830533', 'client-a', 'a@example.com'));
	pg_assert_false(_parasut_token_owner_matches($old, '830533', 'client-a', 'b@example.com'));

	// The write of a renewal stamps by the grant the token carries.
	$write = parasut_connection_card_test_body('_parasut_token_cache_write');
	parasut_connection_card_test_has("_parasut_token_owner(null, null, null, (string) (\$cache['grant'] ?? ''))", $write);

	// The read with no owner given matches by the token's grant.
	$read = parasut_connection_card_test_body('_parasut_token_cache_read');
	parasut_connection_card_test_has('_parasut_token_owner_matches($cache)', $read);
}

// Connect stamps the token it writes as a code token, and answers that stamp
// for the status line it reads in the same request.
function test_parasut_connection_card_connect_stamps_a_code_token()
{
	$body = parasut_connection_card_test_body('parasut_connect_with_code');

	pg_assert_true($body !== '', 'parasut_connect_with_code() is found');
	parasut_connection_card_test_has("\$owner = _parasut_token_owner(\$company_id, \$client_id, null, 'code');", $body);
	parasut_connection_card_test_has("'grant' => 'code',", $body);
}

// Each credential box says what is stored for it: after a connection by
// code only the secret is, and the Password box is empty with a help text
// that says no password is stored.
function test_parasut_connection_card_credential_boxes_follow_what_is_stored()
{
	$saved = lang('Saved');

	$secret_only = parasut_credential_boxes(array('client_secret' => 's3cret', 'password' => ''));
	pg_assert_same($saved, $secret_only['secret']['placeholder']);
	pg_assert_same(lang('The client secret is stored and is not shown. Leave the box empty to keep it.'), $secret_only['secret']['help']);
	pg_assert_same('', $secret_only['password']['placeholder']);
	pg_assert_same(lang('No password is stored. One typed here is stored encrypted and is never shown again after saving.'), $secret_only['password']['help']);

	$both = parasut_credential_boxes(array('client_secret' => 's3cret', 'password' => 'pw'));
	pg_assert_same($saved, $both['secret']['placeholder']);
	pg_assert_same($saved, $both['password']['placeholder']);
	pg_assert_same(lang('The password is stored and is not shown. Leave the box empty to keep it.'), $both['password']['help']);

	$none = parasut_credential_boxes(array('client_secret' => '', 'password' => ''));
	pg_assert_same('', $none['secret']['placeholder']);
	pg_assert_same('', $none['password']['placeholder']);
	pg_assert_same(lang('The client secret is stored encrypted and is never shown again after saving.'), $none['secret']['help']);

	$password_only = parasut_credential_boxes(array('password' => 'pw'));
	pg_assert_same('', $password_only['secret']['placeholder']);
	pg_assert_same($saved, $password_only['password']['placeholder']);

	// A blob that is not there, or cannot be read, holds nothing.
	pg_assert_same(array('client_secret' => '', 'password' => ''), parasut_credentials_decode(''));
	pg_assert_same(array('client_secret' => '', 'password' => ''), parasut_credentials_decode('no-iv-here'));
}

// The card draws each box with its own placeholder and help.
function test_parasut_connection_card_draws_each_box_from_its_own_value()
{
	$card = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/commerce.php');
	$prep = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/prep.php');

	parasut_connection_card_test_has('placeholder="\' . h($parasut_secret_placeholder) . \'"', $card);
	parasut_connection_card_test_has('placeholder="\' . h($parasut_password_placeholder) . \'"', $card);
	parasut_connection_card_test_has('h($parasut_secret_help)', $card);
	parasut_connection_card_test_has('h($parasut_password_help)', $card);
	pg_assert_same(false, strpos($card, '$parasut_credential_placeholder'));
	parasut_connection_card_test_has("parasut_credential_boxes(parasut_credentials_decode((string) (\$row['parasut_credentials_enc'] ?? '')))", $prep);
}

// The company box holds the saved number when the card is drawn, and Connect
// posts it. Another account whose one company is a different one takes that
// company instead of spending the code on a refusal, and the saved number
// given up is named - also when the box was emptied. A number typed in the
// box is still refused when the user cannot see it.
function test_parasut_connection_card_connect_company()
{
	$one = array('999' => 'Yeni Ltd.');

	$taken = parasut_connect_company('830533', '830533', $one);
	pg_assert_same('999', $taken['company_id']);
	pg_assert_same('830533', $taken['replaced']);
	pg_assert_same('', $taken['error']);

	$typed = parasut_connect_company('115', '830533', $one);
	pg_assert_same('', $typed['company_id']);
	pg_assert_contains('115', $typed['error']);
	pg_assert_contains('Yeni Ltd. (999)', $typed['error']);

	// The saved number among several companies the user cannot see: which
	// one is meant is the operator's call.
	$several = parasut_connect_company('830533', '830533', array('999' => 'A', '1000' => 'B'));
	pg_assert_same('', $several['company_id']);
	pg_assert_contains('A (999), B (1000)', $several['error']);

	// The saved number the user can see stays.
	$same = parasut_connect_company('830533', '830533', array('830533' => 'Demo', '999' => 'A'));
	pg_assert_same('830533', $same['company_id']);
	pg_assert_same('', $same['replaced']);

	// An empty box with nothing saved: the one company, or the list.
	$first = parasut_connect_company('', '', $one);
	pg_assert_same('999', $first['company_id']);
	pg_assert_same('', $first['replaced']);

	// An emptied box with a saved number the new user cannot see: the one
	// company is taken, and the switch is named as with the box as drawn.
	$emptied = parasut_connect_company('', '830533', $one);
	pg_assert_same('999', $emptied['company_id']);
	pg_assert_same('830533', $emptied['replaced']);
	pg_assert_same('', $emptied['error']);

	// An emptied box keeps the saved number the user can see, also among
	// several companies: the box's help says an empty box keeps it.
	$kept = parasut_connect_company('', '830533', array('999' => 'A', '830533' => 'Demo'));
	pg_assert_same('830533', $kept['company_id']);
	pg_assert_same('', $kept['replaced']);
	pg_assert_same('', parasut_connect_company('', '830533', array('830533' => 'Demo'))['replaced']);

	// Several companies without the saved number: the list, nothing taken.
	$listed = parasut_connect_company('', '830533', array('999' => 'A', '1000' => 'B'));
	pg_assert_same('', $listed['company_id']);
	pg_assert_same('', $listed['replaced']);
	pg_assert_contains('A (999), B (1000)', $listed['error']);

	pg_assert_same('', parasut_connect_company('', '', array())['company_id']);
	pg_assert_true(parasut_connect_company('', '', array())['error'] !== '');
	pg_assert_contains('A (999), 1000', parasut_connect_company(' ', '', array('999' => 'A', '1000' => ''))['error']);

	// No company list in the answer: the number is taken as given.
	pg_assert_same('830533', parasut_connect_company(' 830533 ', '830533', array())['company_id']);

	// Ids Paraşüt sends as numbers keep their digits.
	pg_assert_same('42', parasut_connect_company('', '', array(42 => 'Int'))['company_id']);
}

// Connect's notice says whom and which company. The saved company given up
// for the user's one company is a warning naming both numbers, as is the
// activity log line; the defaults set for the old company need a look.
function test_parasut_connection_card_connect_report_warns_of_a_company_change()
{
	$plain = parasut_connect_report(array('email' => 'a@example.com', 'company_id' => '830533', 'company_name' => 'Demo', 'replaced_company_id' => ''));
	pg_assert_same(1, count($plain['notices']));
	pg_assert_contains('a@example.com', $plain['notices'][0]);
	pg_assert_contains('Demo (830533)', $plain['notices'][0]);
	pg_assert_same(array(), $plain['warnings']);
	pg_assert_same(lang(array('string' => 'parasut connection was authorized for company {var:1}', 'vars' => '830533')), $plain['log']);

	$changed = parasut_connect_report(array('email' => '', 'company_id' => '999', 'company_name' => 'Yeni Ltd.', 'replaced_company_id' => '830533'));
	pg_assert_same(1, count($changed['notices']));
	pg_assert_contains(lang('the authorized user'), $changed['notices'][0]);
	pg_assert_same(1, count($changed['warnings']));
	pg_assert_contains('830533', $changed['warnings'][0]);
	pg_assert_contains('Yeni Ltd. (999)', $changed['warnings'][0]);
	pg_assert_contains('830533', $changed['log']);
	pg_assert_contains('999', $changed['log']);

	// Both ways in show the switch as a warning: the Connect button by the
	// answer's tone, Save with a code by the page's warning.
	$endpoint = (string) file_get_contents(PG_FUNCTIONS_DIR . '/parasut_connect.php');
	parasut_connection_card_test_has('parasut_connect_report($result)', $endpoint);
	parasut_connection_card_test_has("\$tone = \$ok ? (\$warned ? 'warning' : 'success') : 'danger';", $endpoint);
	$save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/commerce.save.php');
	parasut_connection_card_test_has('parasut_connect_report($parasut_connected)', $save);
	parasut_connection_card_test_has('$liveform->add_warning(h($parasut_line));', $save);

	// Connect hands the saved number to the company choice.
	$body = parasut_connection_card_test_body('parasut_connect_with_code');
	parasut_connection_card_test_has("parasut_connect_company(\$company_id, defined('PARASUT_COMPANY_ID') ? (string) PARASUT_COMPANY_ID : '', \$me['companies'])", $body);
}

// The default product/service carries only the lines that have no product
// card of their own; every Pinegrap product gets its own card.
function test_parasut_connection_card_default_product_help()
{
	$card = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/commerce.php');

	pg_assert_same(false, strpos($card, 'used for all invoice line items'));
	parasut_connection_card_test_has("lang('Used for the invoice lines that have no Paraşüt product card of their own: shipping, charges, free-text lines and rounding. Each Pinegrap product gets its own card. Without this ID an invoice with such a line is refused. Create a generic product/service in Paraşüt and enter its ID here.')", $card);
}
