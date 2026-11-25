<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Security Scan section (includes/fn/security_scan_section.php):
 * the severity pill, which findings Quarantine may move, the quarantine
 * file's name, the actions a row offers, the order of the list, the run's
 * state and progress line, the reason and advice of every kind, and the
 * Security Scan row of Firewall and HTTPS.
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

function test_security_section_severity_face()
{
	pg_assert_same(array(lang('Critical'), 'pg-tone-rose'), pg_security_severity_face(PG_SECURITY_SEVERITY_CRITICAL));
	pg_assert_same(array(lang('High'), 'pg-tone-amber'), pg_security_severity_face(PG_SECURITY_SEVERITY_HIGH));
	pg_assert_same(array(lang('Medium'), 'pg-tone-blue'), pg_security_severity_face(PG_SECURITY_SEVERITY_MEDIUM));
	pg_assert_same(array(lang('Low'), 'pg-tone-gray'), pg_security_severity_face(PG_SECURITY_SEVERITY_LOW));
	pg_assert_same(array(lang('Low'), 'pg-tone-gray'), pg_security_severity_face(9), 'an unknown value reads as low');
}

function test_security_section_quarantine_takes_planted_files_only()
{
	pg_assert_true(pg_security_quarantinable('file_php_unknown'));
	pg_assert_true(pg_security_quarantinable('file_php_retired'));
	pg_assert_true(pg_security_quarantinable('file_upload_executable'));
	pg_assert_true(pg_security_quarantinable('file_picture_php'));
	pg_assert_true(pg_security_quarantinable('file_shell_mark'));
	pg_assert_false(pg_security_quarantinable('file_server_config'), 'moving .htaccess away could take the site down');
	pg_assert_false(pg_security_quarantinable('content_redirect'), 'a record cannot be moved');
	pg_assert_false(pg_security_quarantinable('account_new_admin'));
}

function test_security_section_quarantine_name_never_runs()
{
	$name = pg_security_quarantine_name('data/files/shell.php');
	pg_assert_same(sha1('data/files/shell.php') . '.quarantine', $name);
	pg_assert_same($name, pg_security_quarantine_name('./data/files//shell.php'), 'one spelling of the place');
	pg_assert_same('', pg_security_quarantine_name('../config.php'), 'no file place');
	pg_assert_same('', pg_security_quarantine_name(''));
}

function test_security_section_actions_follow_the_state()
{
	$row = array('kind' => 'file_shell_mark', 'state' => PG_SECURITY_STATE_OPEN);
	pg_assert_same(array('quarantine', 'ignore'), pg_security_section_actions($row));

	$row['kind'] = 'file_server_config';
	pg_assert_same(array('ignore'), pg_security_section_actions($row), 'no Quarantine for a server settings file');

	$row['kind'] = 'content_redirect';
	pg_assert_same(array('ignore'), pg_security_section_actions($row), 'Ignore only for a record');

	pg_assert_same(array('restore'), pg_security_section_actions(array('kind' => 'file_php_unknown', 'state' => PG_SECURITY_STATE_QUARANTINED)));
	pg_assert_same(array('reopen'), pg_security_section_actions(array('kind' => 'content_redirect', 'state' => PG_SECURITY_STATE_IGNORED)));
	pg_assert_same(array(), pg_security_section_actions(array('kind' => 'content_redirect', 'state' => PG_SECURITY_STATE_GONE)));
}

function test_security_section_sort_puts_open_and_worst_first()
{
	$rows = array(
		array('id' => 1, 'state' => PG_SECURITY_STATE_IGNORED, 'severity' => 4, 'last_seen' => 50),
		array('id' => 2, 'state' => PG_SECURITY_STATE_OPEN, 'severity' => 2, 'last_seen' => 90),
		array('id' => 3, 'state' => PG_SECURITY_STATE_OPEN, 'severity' => 4, 'last_seen' => 10),
		array('id' => 4, 'state' => PG_SECURITY_STATE_QUARANTINED, 'severity' => 3, 'last_seen' => 70),
		array('id' => 5, 'state' => PG_SECURITY_STATE_OPEN, 'severity' => 2, 'last_seen' => 95),
		array('id' => 6, 'state' => PG_SECURITY_STATE_GONE, 'severity' => 4, 'last_seen' => 99),
	);

	pg_assert_same(array(3, 5, 2, 4, 1), array_column(pg_security_section_sort($rows), 'id'), 'open by severity then newest; quarantined; ignored; gone left out');
}

function test_security_section_open_counts()
{
	$rows = array(
		array('state' => PG_SECURITY_STATE_OPEN, 'severity' => 4),
		array('state' => PG_SECURITY_STATE_OPEN, 'severity' => 4),
		array('state' => PG_SECURITY_STATE_OPEN, 'severity' => 2),
		array('state' => PG_SECURITY_STATE_IGNORED, 'severity' => 3),
	);

	pg_assert_same(array(4 => 2, 3 => 0, 2 => 1, 1 => 0), pg_security_section_open_counts($rows));
}

function test_security_scan_state_defaults_and_phases()
{
	$state = pg_security_scan_state(null);
	pg_assert_same(0, $state['started']);
	pg_assert_same('', $state['phase']);
	pg_assert_same(null, $state['content']);
	pg_assert_same(0, $state['last_finished']);

	$state = pg_security_scan_state(array('started' => 5, 'phase' => 'content', 'files' => 12, 'stray' => 1));
	pg_assert_same('content', $state['phase']);
	pg_assert_same(12, $state['files']);
	pg_assert_false(isset($state['stray']), 'unknown keys dropped');

	$state = pg_security_scan_state(array('started' => 5, 'phase' => 'elsewhere'));
	pg_assert_same('files', $state['phase'], 'a broken phase starts with the files of the same run');
	pg_assert_same(array('files', 'content', 'accounts'), pg_security_scan_phases());
}

function test_security_scan_progress_line()
{
	pg_assert_same('', pg_security_scan_progress(pg_security_scan_state(null)), 'no run');

	$files = pg_security_scan_progress(pg_security_scan_state(array('started' => 5, 'phase' => 'files', 'files' => 1200)));
	pg_assert_true(strpos($files, '1') !== false, 'the files checked so far');

	pg_assert_same(lang('Checking accounts and settings'), pg_security_scan_progress(pg_security_scan_state(array('started' => 5, 'phase' => 'accounts'))));
}

function test_security_rule_text_covers_every_kind()
{
	$kinds = array_merge(array_keys(pg_security_file_kinds()), array_keys(pg_security_content_kinds()), pg_security_accounts_kinds());

	foreach ($kinds as $kind) {
		$text = pg_security_rule_text($kind, '{"created":1700000000,"mode":"0777","version":"8.1.0","state":"missing","fixed_in":"8.1.34"}');
		pg_assert_true($text['reason'] !== '', $kind . ': a reason');
		pg_assert_true($text['advice'] !== '', $kind . ': what to do');
	}

	pg_assert_same(array('reason' => '', 'advice' => ''), pg_security_rule_text('nothing_known', ''));
}

// A retired release file's "What to do" links to Clean Up; other kinds link
// nowhere.
function test_security_rule_link_retired_to_clean_up()
{
	$link = pg_security_rule_link('file_php_retired');

	pg_assert_true(is_array($link) && (substr($link['url'], -12) === 'clean_up.php'), 'Clean Up');
	pg_assert_true(strpos(pg_security_file_advice('file_php_retired'), 'Clean Up') !== false, 'the advice names it');
	pg_assert_same(null, pg_security_rule_link('file_php_unknown'));
}

function test_settings_firewall_fold_scan_line()
{
	$none = array(4 => 0, 3 => 0, 2 => 0, 1 => 0);

	$fold = pg_firewall_fold_scan(false, 0, false, $none, '');
	pg_assert_same(lang('Ready once the upgrade has run'), $fold['line']);
	pg_assert_same(null, $fold['pill']);
	pg_assert_false($fold['attention']);

	$fold = pg_firewall_fold_scan(true, 0, false, $none, '');
	pg_assert_same(lang('Not scanned yet'), $fold['line']);

	pg_assert_same(lang('Scanning now'), pg_firewall_fold_scan(true, 100, true, $none, '')['line']);

	$fold = pg_firewall_fold_scan(true, 100, false, $none, 'two hours ago');
	pg_assert_same(lang('Nothing found') . ' · ' . lang('last scan') . ' two hours ago', $fold['line']);
	pg_assert_same(array(lang('Nothing open'), 'pg-tone-teal'), $fold['pill']);
	pg_assert_false($fold['attention']);

	$fold = pg_firewall_fold_scan(true, 100, false, array(4 => 1, 3 => 0, 2 => 2, 1 => 0), 'today');
	pg_assert_true(strpos($fold['line'], '3') === 0, 'three open findings');
	pg_assert_same(pg_security_severity_face(PG_SECURITY_SEVERITY_CRITICAL), $fold['pill'], 'the worst open severity');
	pg_assert_true($fold['attention'], 'a critical finding opens the row');

	$fold = pg_firewall_fold_scan(true, 100, false, array(4 => 0, 3 => 0, 2 => 1, 1 => 0), 'today');
	pg_assert_false($fold['attention'], 'a medium finding does not');
}

function test_security_open_count_map()
{
	pg_assert_same(array(4 => 0, 3 => 0, 2 => 0, 1 => 0), pg_security_open_count_map(array()));
	pg_assert_same(
		array(4 => 2, 3 => 0, 2 => 5, 1 => 1),
		pg_security_open_count_map(array(
			array('severity' => '2', 'total' => '5'),
			array('severity' => '4', 'total' => '2'),
			array('severity' => '1', 'total' => '1'),
			array('severity' => '9', 'total' => '7'),
		)),
		'counts by severity, an unknown severity left out'
	);
}

function test_security_card_pills_worst_first()
{
	pg_assert_same(array(), pg_security_card_pills(array(4 => 0, 3 => 0, 2 => 0, 1 => 0)));

	$pills = pg_security_card_pills(array(4 => 0, 3 => 2, 2 => 0, 1 => 3));
	pg_assert_same(2, count($pills), 'only severities with open findings');
	pg_assert_same(array('label' => lang('High'), 'count' => '2', 'tone' => 'pg-tone-amber'), $pills[0]);
	pg_assert_same(array('label' => lang('Low'), 'count' => '3', 'tone' => 'pg-tone-gray'), $pills[1]);
}

function test_security_alert_text_high_and_critical_only()
{
	pg_assert_same('', pg_security_alert_text(array(4 => 0, 3 => 0, 2 => 4, 1 => 9)), 'medium and low findings send no alert');

	$text = pg_security_alert_text(array(4 => 1, 3 => 2, 2 => 4, 1 => 0));
	pg_assert_true($text !== '', 'a critical or high finding sends one');
	pg_assert_true(strpos($text, '3') !== false, 'the open critical and high findings are counted together');
}

function test_ops_alerts_security_finding_code()
{
	$codes = pg_ops_alert_codes();
	pg_assert_true(isset($codes['security_finding']));
	pg_assert_same('settings_firewall.php#pgset-scan', $codes['security_finding']['url']);
	pg_assert_same('pg_ops_alerts_check_security', pg_ops_alert_sources()['security']);
}
