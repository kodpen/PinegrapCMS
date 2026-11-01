<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the toolbar helpers in includes/fn/output.php: the list filter
 * (pg_filter_select() - the selected option, the values carried along from
 * the address, escaping), the period navigator (pg_period_unit(),
 * pg_period_nav()), the cancel button's handler (pg_output_go_script()) and
 * when the licence check asks the server again (pg_license_check_due()) and
 * what its card shows for the states of the Kodpen API.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_output_filter_select_marks_the_current_option()
{
	$html = pg_filter_select('filter', array('' => 'All', 'overdue' => 'Overdue', 'due_week' => 'Due this week'), 'overdue', array('label' => 'Due date'));

	pg_assert_contains('<option value="overdue" selected>Overdue</option>', $html, 'current option');
	pg_assert_contains('<option value="">All</option>', $html, 'empty value stays unselected');
	pg_assert_contains('name="filter"', $html, 'select name');
	pg_assert_contains('onchange="this.form.submit()"', $html, 'submits on change');
	pg_assert_contains('aria-label="Due date"', $html, 'accessible name');
	pg_assert_true(strpos($html, '<form method="get" class="pg-toolbar-filter disable_shortcut">') === 0, 'a GET form of its own');
	pg_assert_same(1, substr_count($html, ' selected'), 'one option selected');
}

function test_output_filter_select_carries_only_the_kept_values()
{
	$saved = $_GET;
	$_GET = array('search' => 'ACME "Ltd"', 'state' => 'open', 'page' => '3', 'empty' => '', 'list' => array('a'));

	$html = pg_filter_select('state', array('' => 'All', 'open' => 'Open'), 'open', array('keep' => array('search', 'state', 'empty', 'list')));

	$_GET = $saved;

	pg_assert_contains('<input type="hidden" name="search" value="ACME &quot;Ltd&quot;">', $html, 'kept value, escaped');
	pg_assert_false(strpos($html, 'type="hidden" name="state"') !== false, 'the filter itself is not carried');
	pg_assert_false(strpos($html, 'name="page"') !== false, 'values not named are dropped');
	pg_assert_false(strpos($html, 'name="empty"') !== false, 'empty values are dropped');
	pg_assert_false(strpos($html, 'name="list"') !== false, 'arrays are dropped');
}

function test_output_filter_select_escapes_labels_and_draws_the_icon()
{
	$html = pg_filter_select('tab', array('a<b' => 'Fish & <chips> (3)'), 'a<b', array('icon' => 'funnel', 'label' => 'Status', 'action' => 'erp_inbox.php?x=1&y=2'));

	pg_assert_contains('<option value="a&lt;b" selected>Fish &amp; &lt;chips&gt; (3)</option>', $html, 'value and label escaped');
	pg_assert_contains('<i class="bi bi-funnel" aria-hidden="true"></i>', $html, 'icon');
	pg_assert_contains('for="pg_filter_tab"', $html, 'icon label points at the select');
	pg_assert_contains('action="erp_inbox.php?x=1&amp;y=2"', $html, 'action escaped');
}

function output_test_range($start, $stop)
{
	return array(
		'start_month' => date('m', strtotime($start)), 'start_day' => date('d', strtotime($start)), 'start_year' => date('Y', strtotime($start)),
		'stop_month' => date('m', strtotime($stop)), 'stop_day' => date('d', strtotime($stop)), 'stop_year' => date('Y', strtotime($stop)),
	);
}

function test_output_period_unit_reads_the_range()
{
	pg_assert_same('day', pg_period_unit(output_test_range('2026-10-10', '2026-10-10')), 'one day');
	pg_assert_same('week', pg_period_unit(output_test_range('2026-10-04', '2026-10-10'), 0), 'Sunday to Saturday');
	pg_assert_same('month', pg_period_unit(output_test_range('2026-10-05', '2026-10-11'), 0), 'seven days not from a Sunday');
	pg_assert_same('week', pg_period_unit(output_test_range('2026-10-25', '2026-10-31'), 0), 'a week across a daylight saving change');
	pg_assert_same('week', pg_period_unit(output_test_range('2026-10-05', '2026-10-11'), 1), 'Monday to Sunday');
	pg_assert_same('month', pg_period_unit(output_test_range('2026-10-04', '2026-10-10'), 1), 'seven days not from a Monday');
	pg_assert_same('week', pg_period_unit(output_test_range('2026-10-19', '2026-10-25'), 1), 'a Monday week across a daylight saving change');
	pg_assert_same('month', pg_period_unit(output_test_range('2026-10-01', '2026-10-31')), 'calendar month');
	pg_assert_same('year', pg_period_unit(output_test_range('2026-01-01', '2026-12-31')), 'calendar year');
	pg_assert_same('month', pg_period_unit(output_test_range('2025-01-01', '2026-12-31')), 'two years step by month');
	pg_assert_same('month', pg_period_unit(output_test_range('2026-09-09', '2026-10-10')), 'any other range');
}

function test_output_period_nav_steps_by_the_unit()
{
	$day = function ($date) { return output_test_range($date, $date); };
	$periods = array(
		'day'   => array($day('2026-10-09'), $day('2026-10-10'), $day('2026-10-11')),
		'week'  => array(output_test_range('2026-09-27', '2026-10-03'), output_test_range('2026-10-04', '2026-10-10'), output_test_range('2026-10-11', '2026-10-17')),
		'month' => array(output_test_range('2026-09-01', '2026-09-30'), output_test_range('2026-10-01', '2026-10-31'), output_test_range('2026-11-01', '2026-11-30')),
		'year'  => array(output_test_range('2025-01-01', '2025-12-31'), output_test_range('2026-01-01', '2026-12-31'), output_test_range('2027-01-01', '2027-12-31')),
	);

	$html = pg_period_nav('view_orders.php', $day('2026-10-10'), $periods, 'Oct 10 &amp; on', ' d-none');

	pg_assert_contains('href="view_orders.php?start_month=10&amp;start_day=09&amp;start_year=2026&amp;stop_month=10&amp;stop_day=09&amp;stop_year=2026"', $html, 'previous day');
	pg_assert_contains('href="view_orders.php?start_month=10&amp;start_day=11&amp;start_year=2026&amp;stop_month=10&amp;stop_day=11&amp;stop_year=2026"', $html, 'next day');
	pg_assert_contains('href="view_orders.php?start_month=01&amp;start_day=01&amp;start_year=2026&amp;stop_month=12&amp;stop_day=31&amp;stop_year=2026"', $html, 'this year in the menu');
	pg_assert_same(1, substr_count($html, 'menu-item pg-link-body-emphasis active'), 'one unit marked');
	pg_assert_contains('Oct 10 &amp; on', $html, 'range text as given');
	pg_assert_contains('class="d-inline-block d-none"', $html, 'extra class');
}

// A cancel address read from the request stays a string inside the button's
// handler: the attribute's entities are decoded before the script runs.
function test_output_go_script_keeps_the_address_a_string()
{
	pg_assert_same('pgSettingsGo(&quot;view_submitted_forms.php&quot;);', pg_output_go_script('view_submitted_forms.php'), 'plain address');

	$attack = "/x');alert(1);//\"<b>";
	$decoded = html_entity_decode(pg_output_go_script($attack), ENT_QUOTES, 'UTF-8');

	pg_assert_same('pgSettingsGo("/x\u0027);alert(1);//\u0022\u003Cb\u003E");', $decoded, 'quotes and tags stay escaped for the script');
	pg_assert_same($attack, json_decode(substr($decoded, strlen('pgSettingsGo('), -2)), 'the script gets the address back unchanged');
}

// A session that has not asked yet asks, and a forced check always asks.
function test_output_license_check_due_first_and_forced()
{
	pg_assert_true(pg_license_check_due(false, '', 0, 1000000), 'never asked in this session');
	pg_assert_true(pg_license_check_due(true, 'success', 1000000, 1000000), 'forced right after a good answer');
	pg_assert_true(pg_license_check_due(true, '', 1000000, 1000000), 'forced right after a failure');
}

// A failed attempt (no usable answer, status '') waits 15 minutes before the
// next one. It used to be retried on every call, two per panel page.
function test_output_license_check_due_waits_after_a_failure()
{
	pg_assert_false(pg_license_check_due(false, '', 1000000, 1000000), 'the same moment');
	pg_assert_false(pg_license_check_due(false, '', 1000000, 1000000 + 899), 'just before the wait ends');
	pg_assert_true(pg_license_check_due(false, '', 1000000, 1000000 + 900), 'the wait is over');
	pg_assert_false(pg_license_check_due(false, null, 1000000, 1000000 + 60), 'a missing status counts as a failure');
}

// An answer from the server, valid licence or not, is trusted for 12 hours.
function test_output_license_check_due_trusts_an_answer_for_twelve_hours()
{
	pg_assert_false(pg_license_check_due(false, 'success', 1000000, 1000000 + 43200), 'twelve hours exactly');
	pg_assert_true(pg_license_check_due(false, 'success', 1000000, 1000000 + 43201), 'past twelve hours');
	pg_assert_false(pg_license_check_due(false, 'error', 1000000, 1000000 + 3600), 'a refused key is not asked again within the hour');
}

// Check License opens the same screen with its own query kept; a GET form
// sent only check_license_again, so view_order.php?id=… lost its id.
function test_output_license_check_again_url_keeps_the_query()
{
	pg_assert_same('?id=42&check_license_again=1', pg_license_check_again_url(array('id' => '42')), 'a record screen keeps its id');
	pg_assert_same('?check_license_again=1', pg_license_check_again_url(array()), 'a screen without a query');
	pg_assert_same('?id=7&check_license_again=1', pg_license_check_again_url(array('check_license_again' => '1', 'id' => '7')), 'pressed again: one check_license_again');
	pg_assert_same('?q=a%26b&f%5B0%5D=x&check_license_again=1', pg_license_check_again_url(array('q' => 'a&b', 'f' => array('x'))), 'values and lists encoded');
	pg_assert_same('?check_license_again=1', pg_license_check_again_url(null), 'no query array');
}

// The button is drawn only for a role that may force the re-check, as a link.
function test_output_license_check_button_only_for_managers()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php');

	pg_assert_contains("if (\$output_check_license_button && isset(\$user['role']) && ((int) \$user['role'] <= 2)) {", $source, 'the card asks the role');
	pg_assert_contains("<a href=\"' . h(pg_license_check_again_url(\$_GET)) . '\"", $source, 'a link keeping the query');
	pg_assert_same(0, substr_count($source, '<input type="hidden" name="check_license_again"'), 'no GET form drops the query any more');
}

// The licence card drawn from a state the session already holds (checked a
// moment ago, so nothing is asked): each refusal of the Kodpen API keeps its
// own text and Check License, and an answer shows the type and site rights.
function output_test_license_card($state)
{
	global $user;

	if (!defined('SUBSCRIPTION_KEY')) {
		define('SUBSCRIPTION_KEY', 'ABCD2345EFGH6789');
	}

	$saved_user = isset($user) ? $user : null;
	$saved_session = isset($_SESSION) ? $_SESSION : null;
	$user = array('role' => 1);
	$_SESSION = array('software' => array('settings' => array('license' => array_merge(array(
		'status' => '', 'error_code' => '', 'countdown' => '', 'expiration_date_formatted' => '',
		'type' => '', 'sites_used' => null, 'sites_total' => null, 'source' => 'v1', 'last_check' => time(),
	), $state))));

	$html = license_check(array('output' => 'bar'));

	$user = $saved_user;
	$_SESSION = $saved_session;

	return $html;
}

function test_output_license_card_refusals_of_the_kodpen_api()
{
	$texts = array(
		'suspended' => 'Your license is suspended. You cannot benefit from some features.',
		'seats_full' => 'Every site right of this license is in use; to use it on a new site, first have one of its sites released.',
		'access_blocked' => 'Service access is closed for this site.',
		'no_match' => 'Your license is not valid. You cannot benefit from some features.',
	);

	foreach ($texts as $code => $text) {
		$html = output_test_license_card(array('status' => 'error', 'error_code' => $code));

		pg_assert_contains(h(lang($text)), $html, $code . ': its own text');
		pg_assert_contains('check_license_again=1', $html, $code . ': Check License');
	}

	pg_assert_contains(lang('Suspended'), output_test_license_card(array('status' => 'error', 'error_code' => 'suspended', 'type' => 'premium', 'sites_used' => 1, 'sites_total' => 1)), 'suspended state');
	pg_assert_contains(lang('Premium'), output_test_license_card(array('status' => 'error', 'error_code' => 'suspended', 'type' => 'premium', 'sites_used' => 1, 'sites_total' => 1)), 'a suspended licence keeps its type');
	pg_assert_contains(lang('Buy License'), output_test_license_card(array('status' => 'error', 'error_code' => 'seats_full')), 'no free right: buy one');
	pg_assert_same(false, strpos(output_test_license_card(array('status' => 'error', 'error_code' => 'access_blocked')), lang('Buy License')), 'closed access: nothing to buy');

	$unusable = output_test_license_card(array());
	pg_assert_contains(h(lang('License verification failed due to technical reason. You cannot benefit from some features.')), $unusable, 'unusable: the technical text');
	pg_assert_contains('check_license_again=1', $unusable, 'unusable: Check License');
	pg_assert_contains(lang('Unavailable'), $unusable, 'unusable state');
}

function test_output_license_card_shows_type_and_site_rights()
{
	$valid = new DateTime('today');
	$valid->modify('+200 days');

	$html = output_test_license_card(array('status' => 'success', 'expiration_date_formatted' => $valid, 'countdown' => 200,
		'type' => 'enterprise', 'sites_used' => 3, 'sites_total' => 5));

	pg_assert_contains(lang('Enterprise'), $html, 'the type');
	pg_assert_contains('3 / 5', $html, 'the site rights');
	pg_assert_contains(lang('Active'), $html, 'still active');

	$old = output_test_license_card(array('status' => 'success', 'expiration_date_formatted' => $valid, 'countdown' => 200, 'source' => null));
	pg_assert_same(false, strpos($old, 'pg-lic-type'), 'an answer of the old server: no type line');
}

// With the switch on, a state without source 'v1' is asked again at once;
// the old server's call stays as it was behind the switch.
function test_output_license_check_v1_branch_behind_the_switch()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php');

	pg_assert_contains("\$license_v1 = defined('PG_VENDOR_API_V1') && (PG_VENDOR_API_V1 === true);", $source, 'read from the switch');
	pg_assert_contains("if (\$license_v1 && ((\$_SESSION['software']['settings']['license']['source'] ?? '') !== 'v1')) {", $source, 'an old state is asked again');
	pg_assert_contains("pg_vendor_api_request('POST', pg_vendor_api_url('/licenses/verify'),", $source, 'the v1 call');
	pg_assert_contains('$decoded = pg_license_map_v1($answer);', $source, 'read by the mapping');
	pg_assert_contains('if (pg_license_key_valid($license_key)) {', $source, 'a key outside the alphabet is not sent');
	pg_assert_contains("'https://www.kodpen.com/api2?", $source, 'the old server while the switch is off');
}
