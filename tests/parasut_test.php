<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure helpers of the Paraşüt client (includes/fn/parasut.php):
 * the authorization page address, the owner stamp of the sealed token and
 * the reading of the /v4/me answer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The authorization page names the client, the out-of-band redirect and the
// code response type; without a client id there is nothing to open.
function test_parasut_authorize_url_names_client_and_oob_redirect()
{
	$url = parasut_authorize_url('Lk-abc_123');

	pg_assert_same('https://api.parasut.com/oauth/authorize?', substr($url, 0, 40));
	pg_assert_contains('client_id=Lk-abc_123', $url);
	pg_assert_contains('redirect_uri=urn%3Aietf%3Awg%3Aoauth%3A2.0%3Aoob', $url);
	pg_assert_contains('response_type=code', $url);
	pg_assert_same('', parasut_authorize_url(''));
	pg_assert_same('', parasut_authorize_url('   '));
}

// The owner stamp follows the three values it is given; a change in any one
// of them is another owner, so a token kept for one account is never
// offered to another.
function test_parasut_token_owner_changes_with_company_client_and_user()
{
	$owner = _parasut_token_owner('123', 'client-a', 'a@example.com');

	pg_assert_same(32, strlen($owner));
	pg_assert_same($owner, _parasut_token_owner('123', 'client-a', 'a@example.com'));
	pg_assert_true($owner !== _parasut_token_owner('124', 'client-a', 'a@example.com'));
	pg_assert_true($owner !== _parasut_token_owner('123', 'client-b', 'a@example.com'));
	pg_assert_true($owner !== _parasut_token_owner('123', 'client-a', ''));
}

// /v4/me with the companies included: the user's e-mail and name, every
// company the relationship lists, named from the included records.
function test_parasut_me_parse_reads_user_and_companies()
{
	$body = array(
		'data' => array(
			'id' => '55',
			'type' => 'users',
			'attributes' => array('name' => 'Ayşe Yılmaz', 'email' => 'ayse@example.com', 'is_confirmed' => true),
			'relationships' => array(
				'companies' => array('data' => array(
					array('id' => '1001', 'type' => 'companies'),
					array('id' => '1002', 'type' => 'companies'),
				)),
				'profile' => array('data' => array('id' => '7', 'type' => 'profiles')),
			),
		),
		'included' => array(
			array('id' => '1001', 'type' => 'companies', 'attributes' => array('name' => 'Demo Şirket A.Ş.')),
			array('id' => '7', 'type' => 'profiles', 'attributes' => array()),
		),
	);

	$me = _parasut_me_parse($body);

	pg_assert_same('ayse@example.com', $me['email']);
	pg_assert_same('Ayşe Yılmaz', $me['name']);
	pg_assert_same(array('1001' => 'Demo Şirket A.Ş.', '1002' => ''), $me['companies']);
}

// An answer with no relationships or included records still reads, with no
// companies and empty strings - never a notice.
function test_parasut_me_parse_tolerates_a_bare_answer()
{
	$me = _parasut_me_parse(array('data' => array('id' => '1', 'type' => 'users')));

	pg_assert_same('', $me['email']);
	pg_assert_same('', $me['name']);
	pg_assert_same(array(), $me['companies']);

	$me = _parasut_me_parse(null);

	pg_assert_same(array(), $me['companies']);
}

// Ten requests fit in any ten seconds; the eleventh waits until the oldest
// of the last ten has left the window.
function test_parasut_throttle_wait_keeps_ten_in_ten_seconds()
{
	pg_assert_same(0.0, parasut_throttle_wait(array(), 100.0, 10, 10.0));
	pg_assert_same(0.0, parasut_throttle_wait(array_fill(0, 9, 99.0), 100.0, 10, 10.0));

	$stamps = array(91.0, 92.0, 93.0, 94.0, 95.0, 96.0, 97.0, 98.0, 99.0, 99.5);
	pg_assert_same(1.0, parasut_throttle_wait($stamps, 100.0, 10, 10.0));

	// Requests older than the window do not count.
	pg_assert_same(0.0, parasut_throttle_wait(array(80.0, 85.0, 89.0), 100.0, 3, 10.0));
	pg_assert_same(3.5, parasut_throttle_wait(array(93.5, 95.0, 99.0), 100.0, 3, 10.0));
}
