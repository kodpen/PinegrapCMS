<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings › E-mail › E-mail sending as folded rows
 * (includes/fn/mail_sending_rows.php, includes/settings/mail_sending.php):
 * the sentence of how a kind of mail leaves, the rows' lines and pills, the
 * ways sheet's cards and what its choice posts, the last test's result.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/mail_sending_rows.php';

function settings_mail_sending_fold_test_account($status = 'active')
{
	return array('label' => 'info@example.com', 'provider' => 'google', 'provider_label' => 'Google', 'status' => $status);
}

// The sentence follows email()'s order: the account, the service, the
// kind's server, PHP mail().
function test_settings_mail_sending_fold_way_now_in_email_order()
{
	$all = array('account' => settings_mail_sending_fold_test_account(), 'service' => 'Brevo', 'host' => 'smtp.example.com', 'mail' => true);

	$way = pg_mail_way_now($all);
	pg_assert_same('google', $way['way'], 'the account first');
	pg_assert_same('Leaves through the Google account info@example.com.', $way['sentence']);

	$all['account'] = null;
	pg_assert_same('Leaves through Brevo.', pg_mail_way_now($all)['sentence'], 'then the service');

	$all['service'] = '';
	pg_assert_same('Leaves through the mail server smtp.example.com.', pg_mail_way_now($all)['sentence'], 'then the server');

	$all['host'] = '';
	pg_assert_same('mail', pg_mail_way_now($all)['way'], 'then PHP mail()');

	$all['mail'] = false;
	$none = pg_mail_way_now($all);
	pg_assert_same(array('none', '', false), array($none['way'], $none['short'], $none['ok']), 'no way at all');

	$again = pg_mail_way_now(array('account' => settings_mail_sending_fold_test_account('reauth')));
	pg_assert_true($again['ask'] && !$again['ok'], 'an account to connect again asks for it');
}

function test_settings_mail_sending_fold_leaves_line_and_pill()
{
	$google = pg_mail_way_now(array('account' => settings_mail_sending_fold_test_account()));
	$brevo = pg_mail_way_now(array('service' => 'Brevo'));

	$same = pg_mail_sending_fold_leaves($google, $google, true);
	pg_assert_same('System e-mail through info@example.com · campaigns the same way', $same['line']);
	pg_assert_same(array('Ready', 'pg-tone-teal'), $same['pill']);
	pg_assert_false($same['attention'], 'ready: no attention');

	pg_assert_same('System e-mail through info@example.com · campaigns through Brevo', pg_mail_sending_fold_leaves($google, $brevo, true)['line']);

	$mail = pg_mail_way_now(array('mail' => true));
	$unset = pg_mail_sending_fold_leaves($mail, $mail, false);
	pg_assert_same(array('Not set up', 'pg-tone-gray'), $unset['pill'], 'PHP mail() alone is not set up, as the tab says');
	pg_assert_true($unset['attention']);

	$ask = pg_mail_sending_fold_leaves(pg_mail_way_now(array('account' => settings_mail_sending_fold_test_account('reauth'))), $mail, true);
	pg_assert_same(array('Sign-in needed', 'pg-tone-amber'), $ask['pill']);
}

function test_settings_mail_sending_fold_domain_dkim_campaign_mailchimp()
{
	$health = array('domain' => 'example.com', 'spf' => array('state' => 'ok'), 'dkim' => array('state' => 'ok'), 'dmarc' => array('state' => 'error'));
	$domain = pg_mail_sending_fold_domain($health);
	pg_assert_same('example.com · DMARC to check', $domain['line']);
	pg_assert_same('pg-tone-amber', $domain['pill'][1]);

	$health['dmarc']['state'] = 'ok';
	pg_assert_same(array('Good', 'pg-tone-teal'), pg_mail_sending_fold_domain($health)['pill']);
	pg_assert_same(array('Could not check', 'pg-tone-gray'), pg_mail_sending_fold_domain(array('domain' => 'example.com'))['pill']);

	pg_assert_same('pg._domainkey.example.com', pg_mail_sending_fold_dkim('pg', 'example.com', true)['line']);
	pg_assert_same('No DKIM key generated yet.', pg_mail_sending_fold_dkim('pg', 'example.com', false)['line']);

	pg_assert_same('Scheduled sending on · at most 200 a run', pg_mail_sending_fold_campaign(true, '200')['line']);
	pg_assert_same(array('Off', 'pg-tone-gray'), pg_mail_sending_fold_campaign(false, 200)['pill']);

	pg_assert_same('Sync on · list abc', pg_mail_sending_fold_mailchimp(true, 'abc')['line']);
	pg_assert_same('Sync off', pg_mail_sending_fold_mailchimp(false, 'abc')['line']);

	pg_assert_same(array('leaves', 'domain', 'dkim', 'campaign', 'mailchimp', 'test'), pg_mail_sending_fold_order(), 'How e-mail leaves first, Send a test last');
}

function test_settings_mail_sending_fold_test_result()
{
	$now = mktime(16, 20, 0, 11, 21, 2026);

	pg_assert_same(null, pg_mail_test_result_decode(''), 'no test yet');
	pg_assert_same(null, pg_mail_test_result_decode('nonsense'), 'not one');
	pg_assert_same('No test sent yet', pg_mail_sending_fold_test(null, $now)['line']);

	$ok = pg_mail_test_result_decode(pg_mail_test_result_encode($now, true, 'ignored'));
	pg_assert_same(array('time' => $now, 'ok' => true, 'reason' => ''), $ok, 'a reason only for a failure');

	$line = pg_mail_sending_fold_test($ok, $now);
	pg_assert_same('Last test 16:20 · accepted', $line['line']);
	pg_assert_same(array('Sent', 'pg-tone-teal'), $line['pill']);

	$failed = pg_mail_sending_fold_test(pg_mail_test_result_decode(pg_mail_test_result_encode($now - 86400, false, 'The server refused it.')), $now);
	pg_assert_same('Last test 20.11.2026 16:20 · not sent', $failed['line'], 'another day names the date');
	pg_assert_same('pg-tone-rose', $failed['pill'][1]);
	pg_assert_same('The server refused it.', $failed['result'], 'the result line says why');
}

// The ways sheet starts on today's way and posts what that way means.
function test_settings_mail_sending_fold_way_post()
{
	pg_assert_same('microsoft', pg_mail_way_chosen(array('provider' => 'microsoft'), 'brevo'), 'the account first');
	pg_assert_same('service', pg_mail_way_chosen(null, 'brevo'));
	pg_assert_same('server', pg_mail_way_chosen(null, ''));

	$accounts = array(7 => 'google', 9 => 'microsoft');
	$post = array('system_mail_connection' => '7', 'mail_transport' => 'postmark', 'system_smtp_hostname' => 'smtp.example.com');

	$google = pg_mail_way_post(array('system_mail_way' => 'google') + $post, 'brevo', $accounts);
	pg_assert_same(null, $google['mark']);
	pg_assert_same(array('7', 'brevo'), array($google['post']['system_mail_connection'], $google['post']['mail_transport']), 'an account way keeps the service switched on');
	pg_assert_same('smtp', pg_mail_way_post(array('system_mail_way' => 'google') + $post, '', $accounts)['post']['mail_transport'], 'and switches none on');

	$wrong = pg_mail_way_post(array('system_mail_way' => 'microsoft') + $post, '', $accounts);
	pg_assert_same('system_mail_way', $wrong['mark'][0], 'an account of the other provider is refused');

	$service = pg_mail_way_post(array('system_mail_way' => 'service') + $post, '', $accounts)['post'];
	pg_assert_same(array('', 'postmark'), array($service['system_mail_connection'], $service['mail_transport']), 'a service sends without an account');

	$server = pg_mail_way_post(array('system_mail_way' => 'server') + $post, 'brevo', $accounts)['post'];
	pg_assert_same(array('', 'smtp', 'smtp.example.com'), array($server['system_mail_connection'], $server['mail_transport'], $server['system_smtp_hostname']), 'the server sends without an account or a service');
}

function test_settings_mail_sending_fold_sheet_fields()
{
	$cards = pg_mail_way_cards('service', 'pg_settings_modal_form');

	pg_assert_same(4, substr_count($cards, 'name="system_mail_way"'), 'four ways');
	pg_assert_same(1, substr_count($cards, ' checked'), 'one chosen');
	pg_assert_contains('value="service" id="system_mail_way_service" form="pg_settings_modal_form"', $cards, 'joined to the settings form');
	pg_assert_contains('class="pg-start pg-start-choice"', $cards, 'the Design examples\' choice cards');

	$accounts = array(
		array('id' => 7, 'provider' => 'google', 'label' => 'news@example.com', 'status' => 'active'),
		array('id' => 9, 'provider' => 'microsoft', 'label' => 'shop@example.com', 'status' => 'reauth'),
	);

	$google = pg_mail_way_account_select('google', $accounts, 7, true, 'f');
	pg_assert_contains('name="system_mail_connection" id="setting_system_mail_connection"', $google, 'the field keeps its name and id');
	pg_assert_false(strpos($google, 'shop@example.com') !== false, 'only the provider\'s accounts');
	pg_assert_false(strpos($google, ' disabled') !== false, 'the chosen way posts its account');

	$microsoft = pg_mail_way_account_select('microsoft', $accounts, 7, false, 'f');
	pg_assert_contains(' disabled', $microsoft, 'another way\'s account is not posted');
	pg_assert_contains('shop@example.com - Connect again', $microsoft);
	pg_assert_same('', pg_mail_way_account_select('google', array(), 0, true, 'f'), 'no account, no select');
}

// The sending way is drawn once: in the sheet, not in the pane's cards.
function test_settings_mail_sending_fold_way_drawn_once()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/mail_sending.php');

	pg_assert_same(1, substr_count($source, 'pg_mail_transport_settings_fields()'), 'the service once');
	pg_assert_same(1, substr_count($source, 'name="system_smtp_hostname"'), 'the server once');
	pg_assert_same(1, substr_count($source, "lang('Save and send a test')"), 'one test button, in Send a test');
	pg_assert_contains("pg_settings_drawer(\n    'pgset-mail-way'", $source, 'the ways sheet');
	pg_assert_contains('data-pg-set-goto="pgset-mail-campaign"', $source, 'Campaigns\' Change opens the Campaign mail row');

	$transport = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail_transport.php');
	pg_assert_false(strpos($transport, '<option value="smtp">') !== false, 'the service select lists services only');

	$save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/mail_sending.save.php');
	pg_assert_true(strpos($save, 'pg_mail_way_post(') < strpos($save, '// ── MailChimp ──'), 'the way is read before anything is checked or written');
}
