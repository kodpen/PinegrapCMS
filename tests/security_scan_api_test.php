<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for GET /security-scan (includes/api/resources/security_scan.php):
 * the risk answer from the open findings, the counts by severity and state,
 * the last run and a running scan, the state filter and each finding's shape.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/response.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/security_scan.php');

function security_scan_api_test_row($id, $kind, $severity, $state, $last_seen = 1000)
{
	return array(
		'id' => $id, 'kind' => $kind, 'place' => 'files/x' . $id . '.php', 'severity' => $severity, 'state' => $state,
		'detail' => '', 'note' => '', 'acted_at' => 0, 'first_seen' => 900, 'last_seen' => $last_seen,
	);
}

function test_security_scan_api_never_scanned()
{
	$answer = api_security_scan_answer(array(), pg_security_scan_state(null), true, '');

	pg_assert_true($answer['ready']);
	pg_assert_false($answer['scanned'], 'no run has finished');
	pg_assert_false($answer['at_risk']);
	pg_assert_same(null, $answer['worst']);
	pg_assert_same(array('critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0), $answer['open']);
	pg_assert_same(null, $answer['scan']['last_finished_at']);
	pg_assert_false($answer['scan']['running']);
	pg_assert_same(array(), $answer['findings']);
}

function test_security_scan_api_not_ready()
{
	$answer = api_security_scan_answer(array(), pg_security_scan_state(null), false, '');

	pg_assert_false($answer['ready'], 'the database update has not run');
	pg_assert_false($answer['at_risk']);
}

function test_security_scan_api_risk_from_open_high_or_critical()
{
	$state = pg_security_scan_state(array('last_finished' => 2000, 'last_files' => 120, 'last_records' => 45));

	$low = api_security_scan_answer(array(security_scan_api_test_row(1, 'file_server_config', PG_SECURITY_SEVERITY_MEDIUM, PG_SECURITY_STATE_OPEN)), $state, true, '');
	pg_assert_true($low['scanned']);
	pg_assert_false($low['at_risk'], 'a medium finding alone is no risk');
	pg_assert_same('medium', $low['worst']);
	pg_assert_same('1970-01-01T00:33:20Z', $low['scan']['last_finished_at']);
	pg_assert_same(120, $low['scan']['last_files']);
	pg_assert_same(45, $low['scan']['last_records']);

	$ignored = api_security_scan_answer(array(security_scan_api_test_row(2, 'file_shell_mark', PG_SECURITY_SEVERITY_CRITICAL, PG_SECURITY_STATE_IGNORED)), $state, true, '');
	pg_assert_false($ignored['at_risk'], 'an ignored finding is a person\'s call');
	pg_assert_same(null, $ignored['worst']);
	pg_assert_same(1, $ignored['ignored']);

	$high = api_security_scan_answer(array(
		security_scan_api_test_row(3, 'file_server_config', PG_SECURITY_SEVERITY_MEDIUM, PG_SECURITY_STATE_OPEN),
		security_scan_api_test_row(4, 'file_php_unknown', PG_SECURITY_SEVERITY_HIGH, PG_SECURITY_STATE_OPEN),
		security_scan_api_test_row(5, 'file_shell_mark', PG_SECURITY_SEVERITY_CRITICAL, PG_SECURITY_STATE_QUARANTINED),
	), $state, true, '');
	pg_assert_true($high['at_risk']);
	pg_assert_same('high', $high['worst'], 'a quarantined finding does not count');
	pg_assert_same(array('critical' => 0, 'high' => 1, 'medium' => 1, 'low' => 0), $high['open']);
	pg_assert_same(1, $high['quarantined']);
	pg_assert_same(array(4, 3, 5), array_column($high['findings'], 'id'), 'open worst first, then quarantined');
}

function test_security_scan_api_running_scan()
{
	$state = pg_security_scan_state(array('started' => 3000, 'phase' => 'content', 'records' => 7));
	$answer = api_security_scan_answer(array(), $state, true, '');

	pg_assert_true($answer['scan']['running']);
	pg_assert_same('content', $answer['scan']['phase']);
	pg_assert_same('1970-01-01T00:50:00Z', $answer['scan']['started_at']);
	pg_assert_same(pg_security_scan_progress($state), $answer['scan']['progress']);
}

function test_security_scan_api_state_filter_keeps_counts()
{
	$rows = array(
		security_scan_api_test_row(1, 'file_php_unknown', PG_SECURITY_SEVERITY_HIGH, PG_SECURITY_STATE_OPEN),
		security_scan_api_test_row(2, 'file_shell_mark', PG_SECURITY_SEVERITY_CRITICAL, PG_SECURITY_STATE_IGNORED),
	);
	$answer = api_security_scan_answer($rows, pg_security_scan_state(null), true, 'ignored');

	pg_assert_same(array(2), array_column($answer['findings'], 'id'));
	pg_assert_same(1, $answer['open']['high'], 'the counts cover every finding');
	pg_assert_true($answer['at_risk']);
}

function test_security_scan_api_finding_shape()
{
	$row = security_scan_api_test_row(9, 'file_server_config', PG_SECURITY_SEVERITY_MEDIUM, PG_SECURITY_STATE_IGNORED, 1500);
	$row['detail'] = 'new';
	$row['note'] = 'Our own rewrite rules';
	$row['acted_at'] = 1600;
	$answer = api_security_scan_answer(array($row), pg_security_scan_state(null), true, '');
	$finding = $answer['findings'][0];
	$text = pg_security_rule_text('file_server_config', 'new');

	pg_assert_same(9, $finding['id']);
	pg_assert_same('file_server_config', $finding['kind']);
	pg_assert_same('medium', $finding['severity']);
	pg_assert_same('ignored', $finding['state']);
	pg_assert_same('files/x9.php', $finding['place']);
	pg_assert_same($text['reason'], $finding['reason']);
	pg_assert_same($text['advice'], $finding['what_to_do']);
	pg_assert_same(pg_security_section_detail('file_server_config', 'new'), $finding['detail']);
	pg_assert_same('Our own rewrite rules', $finding['note']);
	pg_assert_same('1970-01-01T00:15:00Z', $finding['first_seen']);
	pg_assert_same('1970-01-01T00:25:00Z', $finding['last_seen']);
	pg_assert_same('1970-01-01T00:26:40Z', $finding['acted_at']);
	pg_assert_false($finding['quarantinable'], 'a server settings file is never moved');

	$open = api_security_scan_answer(array(security_scan_api_test_row(10, 'file_php_unknown', PG_SECURITY_SEVERITY_HIGH, PG_SECURITY_STATE_OPEN)), pg_security_scan_state(null), true, '');
	pg_assert_true($open['findings'][0]['quarantinable']);
	pg_assert_same(null, $open['findings'][0]['note'], 'no note reads null');
	pg_assert_same(null, $open['findings'][0]['detail']);
	pg_assert_same(null, $open['findings'][0]['acted_at']);
}

function test_security_scan_api_schema_names_every_field()
{
	$answer = api_security_scan_answer(array(security_scan_api_test_row(1, 'file_php_unknown', PG_SECURITY_SEVERITY_HIGH, PG_SECURITY_STATE_OPEN)), pg_security_scan_state(null), true, '');
	$schema = api_security_scan_schema();

	pg_assert_same(array_keys($schema), array_keys($answer));
	pg_assert_same(array_keys($schema['scan']), array_keys($answer['scan']));
	pg_assert_same(array_keys($schema['findings'][0]), array_keys($answer['findings'][0]));
}
