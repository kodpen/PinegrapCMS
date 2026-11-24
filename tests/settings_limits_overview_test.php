<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Firewall and HTTPS > Limits: the value rows for the limits
 * Accounts and sign-in keeps (Sign-in attempts, Form submissions), what each
 * does today and where Change leads, and the rows drawn in the Limits card.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/firewall_settings_fold.php');

function settings_limits_overview_test_throttle($ready, $on)
{
	return array('ready' => $ready, 'on' => $on, 'attempts' => 10, 'minutes' => 15, 'lockout' => 60);
}

function settings_limits_overview_test_forms($ready, $ip, $recipient, $domain)
{
	return array('ready' => $ready, 'ip_hour' => $ip, 'recipient_day' => $recipient, 'domain_hour' => $domain);
}

function test_settings_limits_overview_rows()
{
	$rows = pg_settings_limits_overview(settings_limits_overview_test_throttle(true, true), settings_limits_overview_test_forms(true, 10, 3, 0));

	pg_assert_same(2, count($rows), 'two rows');
	pg_assert_same(lang('Sign-in attempts'), $rows[0]['name'], 'the throttle first');
	pg_assert_same(lang('Form submissions'), $rows[1]['name'], 'then the form limits');
	pg_assert_same('#settings/security/pgset-throttle', $rows[0]['href'], 'Change opens Sign-in attempts');
	pg_assert_same('#settings/security/pgset-captcha', $rows[1]['href'], 'Change opens Forms and spam');

	foreach ($rows as $row) {
		pg_assert_true(strpos($row['icon'], 'bi-') === 0, $row['name'] . ': an icon');
		pg_assert_true($row['tone'] !== '', $row['name'] . ': a tone');
	}

	pg_assert_same(lang(array('string' => '{var:1} failed sign-ins in {var:2} minutes lock the address and the account out for {var:3} minutes.', 'vars' => array(10, 15, 60))), $rows[0]['line'], 'the throttle\'s values');
	pg_assert_same(lang(array('string' => '{var:1} an hour from one visitor address', 'vars' => 10)) . ' · ' . lang(array('string' => '{var:1} a day for one e-mail address', 'vars' => 3)), $rows[1]['line'], 'the limits that are on; the domain limit at 0 is left out');
}

function test_settings_limits_overview_off_and_waiting()
{
	$rows = pg_settings_limits_overview(settings_limits_overview_test_throttle(true, false), settings_limits_overview_test_forms(true, 0, 0, 0));

	pg_assert_same(lang('Failed sign-ins are not limited.'), $rows[0]['line'], 'throttle off');
	pg_assert_same('amber', $rows[0]['tone'], 'an amber tile when off');
	pg_assert_same(lang('Form submissions are not limited.'), $rows[1]['line'], 'every form limit at 0');
	pg_assert_same('amber', $rows[1]['tone'], 'an amber tile when nothing is limited');

	$rows = pg_settings_limits_overview(settings_limits_overview_test_throttle(false, true), settings_limits_overview_test_forms(false, 10, 3, 0));

	pg_assert_same(lang('Waits for the database upgrade.'), $rows[0]['line'], 'no throttle columns yet');
	pg_assert_same(lang('Waits for the database upgrade.'), $rows[1]['line'], 'no form limit columns yet');
	pg_assert_same('', $rows[0]['href'], 'no Change before the upgrade: the row it would open is not drawn');
	pg_assert_same('', $rows[1]['href'], 'Forms and spam is drawn, but without the limits');

	$rows = pg_settings_limits_overview(settings_limits_overview_test_throttle(true, true), settings_limits_overview_test_forms(true, 0, 0, 5));

	pg_assert_same(lang(array('string' => '{var:1} an hour for one e-mail domain', 'vars' => 5)), $rows[1]['line'], 'the domain limit alone');
}

function test_settings_limits_overview_drawn_in_limits()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/firewall.php');
	$start = strpos($source, '<div id="pgset-limits" class="pg-set-card">');
	$card = substr($source, (int) $start, (int) strpos($source, '// ── Bots ──') - (int) $start);
	$part = (int) strpos($source, '// ── Limits ──');
	$before = substr($source, $part, (int) $start - $part);

	pg_assert_contains('pg_settings_limits_overview(', $before, 'the rows come from the one function');
	pg_assert_contains('pg-card-inner', $card, 'one inner card');
	pg_assert_contains('<div class="pg-value-row">', $before, 'value rows');
	pg_assert_contains('pg-button pg-button-quiet pg-button-sm', $before, 'Change quiet and small');
	pg_assert_true(strpos($card, '$firewall_limit_values') > strpos($card, 'waf_auto_ban_max_minutes'), 'under the firewall\'s own limits');
}
