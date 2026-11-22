<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The "How long we keep data" overview (pg_privacy_retention_overview()):
 * every period set outside the row, in the screen's order, each with its
 * value today and the card that changes it; the fixed periods with none.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function privacy_retention_overview_test_facts()
{
	return array(
		'audit_years'   => 2,
		'consent_years' => 3,
		'waf_days'      => 14,
		'waf_rows'      => 20000,
		'api_days'      => 30,
		'booking_days'  => 400,
		'rules'         => pg_privacy_rules_normalise(array()),
	);
}

function privacy_retention_overview_test_rows($facts)
{
	$rows = array();

	foreach (pg_privacy_retention_overview($facts) as $row) {
		$rows[$row['key']] = $row;
	}

	return $rows;
}

function test_privacy_retention_overview_rows_in_order()
{
	$rows = privacy_retention_overview_test_rows(privacy_retention_overview_test_facts());

	pg_assert_same(array('audit', 'consent', 'waf', 'api', 'bookings', 'chats', 'contacts', 'forms', 'carts', 'orders', 'requests'), array_keys($rows), 'every period, in the screen\'s order');

	foreach ($rows as $key => $row) {
		pg_assert_true(($row['name'] !== '') && ($row['line'] !== ''), $key . ': a name and a value');
		pg_assert_true(strpos($row['icon'], 'bi-') === 0, $key . ': an icon');
		pg_assert_false(stripos($row['name'], 'Log Retention') !== false, $key . ': never "Log Retention"');
	}
}

function test_privacy_retention_overview_values_today()
{
	$rows = privacy_retention_overview_test_rows(privacy_retention_overview_test_facts());

	pg_assert_same(lang(array('string' => 'Kept {var:1} year{suffix:1}', 'vars' => 2, 'suffix' => array('s'))), $rows['audit']['line'], 'the audit trail\'s years');
	pg_assert_same(lang(array('string' => 'Kept {var:1} year{suffix:1}; the answer in force always', 'vars' => 3, 'suffix' => array('s'))), $rows['consent']['line'], 'the consent history');
	pg_assert_same(lang(array('string' => 'Kept {var:1} day{suffix:1}, at most {var:2} rows', 'vars' => array(14, pg_format_number(20000, 0)), 'suffix' => array('s'))), $rows['waf']['line'], 'the firewall log, with its size limit');
	pg_assert_same(lang(array('string' => 'Kept {var:1} day{suffix:1}', 'vars' => 30, 'suffix' => array('s'))), $rows['api']['line'], 'the API log');
	pg_assert_same(lang(array('string' => 'Kept {var:1} day{suffix:1} after their last change', 'vars' => 400, 'suffix' => array('s'))), $rows['bookings']['line'], 'cancelled bookings');
	pg_assert_same(lang('Kept until deleted'), $rows['chats']['line'], 'site chats');

	foreach (array('contacts', 'forms', 'carts', 'orders', 'requests') as $key) {
		pg_assert_same(lang('Off: kept until deleted'), $rows[$key]['line'], $key . ': every rule is off by default');
	}

	$facts = privacy_retention_overview_test_facts();
	$facts['audit_years'] = 0;
	$facts['api_days'] = 1;
	$facts['rules']['contacts'] = array('on' => true, 'period' => 3, 'approved_at' => 1700000000, 'approved_by' => 1);
	$facts['rules']['carts'] = array('on' => true, 'period' => 90, 'approved_at' => 1700000000, 'approved_by' => 1);
	$facts['rules']['forms']['on'] = true;
	$facts['rules']['orders'] = array('on' => true, 'period' => null, 'approved_at' => 1700000000, 'approved_by' => 1);
	$rows = privacy_retention_overview_test_rows($facts);

	pg_assert_same(lang('Kept until deleted'), $rows['audit']['line'], '0 keeps every line');
	pg_assert_same(lang(array('string' => 'Kept {var:1} day{suffix:1}', 'vars' => 1, 'suffix' => array(''))), $rows['api']['line'], 'one day');
	pg_assert_same(lang(array('string' => 'Kept {var:1} year{suffix:1}', 'vars' => 3, 'suffix' => array('s'))), $rows['contacts']['line'], 'a rule on and approved, in years');
	pg_assert_same(lang(array('string' => 'Kept {var:1} day{suffix:1}', 'vars' => 90, 'suffix' => array('s'))), $rows['carts']['line'], 'a rule in days');
	pg_assert_same(lang('On, waiting for a dry run and your yes: kept until deleted'), $rows['forms']['line'], 'a rule on without its yes runs nothing');
	pg_assert_same(lang('Off: kept until deleted'), $rows['orders']['line'], 'orders with no period are never touched');
}

function test_privacy_retention_overview_change_opens_its_card()
{
	$rows = privacy_retention_overview_test_rows(privacy_retention_overview_test_facts());

	pg_assert_same(array('security', 'pgset-audit'), array($rows['audit']['category'], $rows['audit']['card']), 'the Audit Trail card');
	pg_assert_same(array('firewall', 'pgset-waf-logs'), array($rows['waf']['category'], $rows['waf']['card']), 'the firewall\'s Logs row');
	pg_assert_same(array('api', 'pgset-api-storage'), array($rows['api']['category'], $rows['api']['card']), 'Uploads & Logs');

	foreach (array('contacts', 'forms', 'carts', 'orders', 'requests') as $key) {
		pg_assert_same('privacy_centre.php', $rows[$key]['url'], $key . ': the Privacy centre\'s rules');
	}

	foreach (array('consent', 'bookings', 'chats') as $key) {
		pg_assert_same(array('', '', ''), array($rows[$key]['category'], $rows[$key]['card'], $rows[$key]['url']), $key . ': fixed, no Change');
	}
}

function test_privacy_retention_overview_leaves_out_what_the_site_lacks()
{
	$facts = privacy_retention_overview_test_facts();
	$facts['audit_years'] = null;
	$facts['booking_days'] = null;

	$rows = privacy_retention_overview_test_rows($facts);

	pg_assert_false(isset($rows['audit']), 'no audit row before its column exists');
	pg_assert_false(isset($rows['bookings']), 'no bookings row without bookings');
	pg_assert_true(isset($rows['consent']), 'the rest stays');
}
