<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the retention of the audit trail and the consent log
 * (includes/fn/privacy_retention.php): the years the Security screen takes,
 * the cutoff moment, and which consent lines the daily pass may remove.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');

// 0 or a whole number within the bounds; anything else is refused.
function test_audit_retention_years_from_input()
{
	pg_assert_same(0, pg_audit_retention_years_from_input('0'), 'keep every line');
	pg_assert_same(2, pg_audit_retention_years_from_input(' 2 '), 'trimmed');
	pg_assert_same(10, pg_audit_retention_years_from_input('10'), 'upper bound');

	foreach (array('', '11', '-1', '1.5', 'two', null, array('2')) as $bad) {
		pg_assert_same(null, pg_audit_retention_years_from_input($bad), var_export($bad, true));
	}
}

// The same moment the given years ago; 0 years removes nothing.
function test_privacy_retention_cutoff()
{
	$now = mktime(14, 30, 0, 10, 27, 2026);

	pg_assert_same(mktime(14, 30, 0, 10, 27, 2024), pg_privacy_retention_cutoff(2, $now), 'two years');
	pg_assert_same(mktime(14, 30, 0, 10, 27, 2023), pg_privacy_retention_cutoff(3, $now), 'three years');
	pg_assert_same(0, pg_privacy_retention_cutoff(0, $now), 'kept');
	pg_assert_same(0, pg_privacy_retention_cutoff(-1, $now), 'negative');
}

// Old lines go only without a person or behind a newer line of the same person, channel and purpose.
function test_consent_log_trim_query_keeps_the_answer_in_force()
{
	$query = preg_replace('/\s+/', ' ', pg_consent_log_trim_query(1700000000, 500));

	pg_assert_contains("(c.created_at < '1700000000')", $query, 'older than the cutoff');
	pg_assert_contains("((c.person_token = '') AND (c.user_id = '0') AND (c.contact_id = '0'))", $query, 'no person');

	foreach (array('person_token', 'user_id', 'contact_id', 'channel', 'purpose') as $column) {
		pg_assert_contains('(n.' . $column . ' = c.' . $column . ')', $query, 'same ' . $column);
	}

	pg_assert_contains('((n.created_at > c.created_at) OR ((n.created_at = c.created_at) AND (n.id > c.id)))', $query, 'a newer line');
	pg_assert_contains('LIMIT 500', $query, 'batch');
}

// "Newer" is the order pg_consent_log_current() reads the answer in force
// by: time first, then id. A line written later with an earlier time (an
// IYS refusal stamped with IYS's own time) does not supersede a newer answer.
function test_consent_log_trim_query_newer_by_time_then_id()
{
	$query = preg_replace('/\s+/', ' ', pg_consent_log_trim_query(1700000000, 500));

	pg_assert_true(strpos($query, 'AND (n.id > c.id) )') === false, 'a higher id alone is not newer');

	$older = array('id' => 11, 'created_at' => 100, 'channel' => 'email', 'purpose' => 'marketing', 'status' => 'refused');
	$newer = array('id' => 10, 'created_at' => 200, 'channel' => 'email', 'purpose' => 'marketing', 'status' => 'granted');
	$current = pg_consent_log_current(array($older, $newer));

	pg_assert_same(10, $current['email:marketing']['id'], 'the answer in force is the newer time, not the higher id');
}

// The bounds the screen, the save and the job share.
function test_privacy_retention_limits()
{
	$limits = pg_privacy_retention_limits();

	pg_assert_same(2, $limits['audit_default'], 'default two years');
	pg_assert_same(3, $limits['consent_years'], 'consent three years');
	pg_assert_true($limits['audit_min'] <= $limits['audit_default'] && $limits['audit_default'] <= $limits['audit_max'], 'default within bounds');
}

// The erasure and rule records stay at least three years, whatever the
// Security screen keeps; every other line goes at the cutoff.
function test_audit_retention_delete_query_keeps_destruction_records()
{
	$query = preg_replace('/\s+/', ' ', pg_audit_retention_delete_query(1700000000, 1600000000, 1000));

	pg_assert_contains("(created_at < '1700000000')", $query, 'older than the cutoff');
	pg_assert_contains("(created_at < '1600000000')", $query, 'records past three years');
	pg_assert_contains("(entity_type = 'privacy_rule')", $query, 'rule runs kept');
	pg_assert_contains("((entity_type = 'person') AND (action = 'erase'))", $query, 'erasures kept');
	pg_assert_contains('LIMIT 1000', $query, 'batch');
	pg_assert_same(3, pg_privacy_retention_limits()['record_years'], 'three years');

	$now = mktime(12, 0, 0, 11, 20, 2026);
	pg_assert_same(mktime(12, 0, 0, 11, 20, 2023), pg_privacy_rule_record_cutoff($now), 'the keep is three years back');
}

// Each rule is off by default; the cart's period is at least 31 days and
// the pass interval at most six months.
function test_privacy_rules_defaults_and_bounds()
{
	$rules = pg_privacy_rules_normalise(array());

	foreach (array('contacts', 'forms', 'carts', 'orders') as $key) {
		pg_assert_same(false, $rules[$key]['on'], $key . ' off');
		pg_assert_same(0, $rules[$key]['approved_at'], $key . ' not approved');
	}

	pg_assert_same(3, $rules['contacts']['period'], 'passive contact: 3 years');
	pg_assert_same(2, $rules['forms']['period'], 'form submissions: 2 years');
	pg_assert_same(90, $rules['carts']['period'], 'carts: 90 days');
	pg_assert_same(null, $rules['orders']['period'], 'orders: never until typed');

	$catalogue = pg_privacy_rules_catalogue();
	pg_assert_same(31, $catalogue['carts']['min'], 'the 31-day floor');
	pg_assert_same(183, pg_privacy_rules_interval_limits()['max'], 'the 6-month bound');

	$stored = pg_privacy_rules_normalise(array('carts' => array('on' => 1, 'period' => 5), 'interval' => 400));
	pg_assert_same(31, $stored['carts']['period'], 'a stored cart period under the floor is raised');
	pg_assert_same(183, $stored['interval'], 'a stored interval over six months is lowered');
}

// What the panel posts: bounds, empty meaning never for orders, and a
// switched or changed rule waits for a new yes.
function test_privacy_rules_from_input()
{
	$current = pg_privacy_rules_normalise(array('contacts' => array('on' => 1, 'period' => 3, 'approved_at' => 1700000000, 'approved_by' => 4)));

	$out = pg_privacy_rules_from_input(array('carts' => array('on' => '1', 'period' => '30')), $current);
	pg_assert_same(array('carts' => 'period'), $out['errors'], '30 days refused');

	$out = pg_privacy_rules_from_input(array('carts' => array('on' => '1', 'period' => '31'), 'interval' => '183'), $current);
	pg_assert_same(array(), $out['errors'], '31 days and 183 days taken');
	pg_assert_same(31, $out['rules']['carts']['period'], 'cart period');
	pg_assert_same(183, $out['rules']['interval'], 'interval');

	$out = pg_privacy_rules_from_input(array('interval' => '184'), $current);
	pg_assert_same(array('interval' => 'interval'), $out['errors'], 'over six months refused');

	$out = pg_privacy_rules_from_input(array('orders' => array('on' => '1', 'period' => '')), $current);
	pg_assert_same(array('orders' => 'period'), $out['errors'], 'an order rule switched on needs its years');

	$out = pg_privacy_rules_from_input(array('orders' => array('period' => '')), $current);
	pg_assert_same(null, $out['rules']['orders']['period'], 'empty: never');

	$out = pg_privacy_rules_from_input(array('contacts' => array('on' => '1', 'period' => '3')), $current);
	pg_assert_same(1700000000, $out['rules']['contacts']['approved_at'], 'unchanged rule keeps its yes');

	$out = pg_privacy_rules_from_input(array('contacts' => array('on' => '1', 'period' => '2')), $current);
	pg_assert_same(0, $out['rules']['contacts']['approved_at'], 'a changed period waits for a new dry run');

	$out = pg_privacy_rules_from_input(array('contacts' => array('period' => '3')), $current);
	pg_assert_same(false, $out['rules']['contacts']['on'], 'unticked: off');
	pg_assert_same(0, $out['rules']['contacts']['approved_at'], 'switched off loses its yes');

	$out = pg_privacy_rules_from_input(array('forms' => array('on' => '1', 'period' => '2', 'pages' => array('12' => '5', '13' => '0', '14' => '', 'x' => '1'))), $current);
	pg_assert_same(array(12 => 5, 13 => 0), $out['rules']['forms']['pages'], 'per form: years, 0 keeps, empty follows the rule');

	$out = pg_privacy_rules_from_input(array('forms' => array('on' => '1', 'period' => '2', 'pages' => array('12' => '99'))), $current);
	pg_assert_same(array('forms' => 'pages'), $out['errors'], 'a form period out of bounds');
}

// Only the role that may save Site Settings switches a rule or says yes.
function test_privacy_rules_may_change()
{
	pg_assert_same(true, pg_privacy_rules_may_change(array('role' => 0)), 'administrator');
	pg_assert_same(true, pg_privacy_rules_may_change(array('role' => 2)), 'manager saves Site Settings');
	pg_assert_same(false, pg_privacy_rules_may_change(array('role' => 3)), 'user');
	pg_assert_same(false, pg_privacy_rules_may_change(array()), 'nobody');
}

// P8: every skip, in its order; a passive contact with a booking ahead and
// a staff member are never removed.
function test_privacy_rule_skips()
{
	pg_assert_same(array('staff', 'open_order', 'open_return', 'balance', 'booking', 'active'), pg_privacy_rule_skip_reasons(), 'the skips');
	pg_assert_same('', pg_privacy_rule_skip_reason(array()), 'nothing holds the person');

	foreach (pg_privacy_rule_skip_reasons() as $reason) {
		pg_assert_same($reason, pg_privacy_rule_skip_reason(array($reason => 1)), $reason);
	}

	pg_assert_same('booking', pg_privacy_rule_skip_reason(array('booking' => 1, 'active' => 0)), 'a passive contact with a booking ahead');
	pg_assert_same('staff', pg_privacy_rule_skip_reason(array('booking' => 1, 'staff' => true)), 'staff first');

	pg_assert_same(true, pg_privacy_rule_is_staff(array('user_role' => '2')), 'manager');
	pg_assert_same(true, pg_privacy_rule_is_staff(array('user_role' => '0')), 'administrator');
	pg_assert_same(false, pg_privacy_rule_is_staff(array('user_role' => '3', 'user_manage_forms' => 'no', 'user_publish_calendar_events' => 'yes')), 'member');
	pg_assert_same(true, pg_privacy_rule_is_staff(array('user_role' => '3', 'user_manage_ecommerce' => 'yes')), 'a member who manages orders');
	pg_assert_same(true, pg_privacy_rule_is_staff(array('user_role' => '3', 'manage_workspace' => '1')), 'a Workspace member');
	pg_assert_same(true, pg_privacy_rule_is_staff(array('user_role' => null)), 'unknown role: kept');
}

// Invoices, ERP records and order legal documents are never reached by a
// rule; carts keep their row, through the orders entry's own field set.
function test_privacy_rule_entries()
{
	$tables = pg_privacy_erase_tables();
	$never = pg_privacy_rules_never_entries();

	foreach (array('erp_accounts', 'erp_invoices', 'erp_quotes', 'erp_waybills', 'order_documents') as $entry) {
		pg_assert_true(in_array($entry, $never, true), $entry . ' never');
	}

	foreach (array_keys(pg_privacy_rules_catalogue()) as $key) {
		$entries = pg_privacy_rule_entries($key);
		pg_assert_true(count($entries) > 0, $key . ' reaches something');
		pg_assert_same(array(), array_values(array_intersect($entries, $never)), $key . ' leaves invoices, ERP and legal documents');

		foreach ($entries as $entry) {
			pg_assert_true(isset($tables[$entry]), $key . ': ' . $entry . ' is an erase entry');
		}
	}

	pg_assert_true(in_array('contacts', pg_privacy_rule_entries('contacts'), true), 'a passive contact is erased');
	pg_assert_same(array('ship_tos', 'orders'), pg_privacy_rule_entries('carts'), 'carts');

	foreach (array('carts', 'orders') as $key) {
		foreach (pg_privacy_rule_entries($key) as $entry) {
			pg_assert_same('anonymise', $tables[$entry]['action'], $key . ': ' . $entry . ' keeps its row');
		}
	}

	$fields = pg_privacy_rule_cart_fields();
	pg_assert_same($tables['orders']['fields'], $fields, 'the orders entry field set');

	foreach (array('user_id' => 0, 'contact_id' => 0, 'billing_email_address' => '{token}', 'billing_phone_number' => '', 'billing_address_1' => '', 'ip_address' => 0) as $column => $value) {
		pg_assert_same($value, $fields[$column], 'cart ' . $column);
	}
}

// A row is still personal while one of the field set's columns holds a value.
function test_privacy_rule_pending_where()
{
	$where = pg_privacy_rule_pending_where(array('user_id' => 0, 'billing_city' => '', 'billing_first_name' => '{name}', 'billing_email_address' => '{token}'));

	pg_assert_contains('(`user_id` <> 0)', $where, 'a link');
	pg_assert_contains("((`billing_city` IS NOT NULL) AND (`billing_city` <> ''))", $where, 'a text');
	pg_assert_contains("(`billing_email_address` NOT REGEXP '^[0-9a-f]{64}$')", $where, 'an address not yet a token');
	pg_assert_true(strpos($where, 'billing_first_name') === false, 'the name placeholder is no sign');
	pg_assert_same('', pg_privacy_rule_pending_where(array()), 'no field');
}

// Which rows each rule looks at.
function test_privacy_rule_candidate_where()
{
	$now = mktime(12, 0, 0, 11, 20, 2026);
	$rules = pg_privacy_rules_normalise(array('orders' => array('period' => 10)));

	$where = pg_privacy_rule_candidate_where('carts', $rules['carts'], $now, array('pending' => '(`user_id` <> 0)'));
	pg_assert_contains("(status = 'incomplete')", $where, 'carts only');
	pg_assert_contains("< '" . ($now - 90 * 86400) . "'", $where, '90 days');
	pg_assert_contains('(`user_id` <> 0)', $where, 'still personal');

	$where = pg_privacy_rule_candidate_where('orders', $rules['orders'], $now, array('pending' => '(`user_id` <> 0)'));
	pg_assert_contains("(status IN ('complete', 'exported', 'cancelled'))", $where, 'placed orders');
	pg_assert_contains("(order_date < '" . mktime(12, 0, 0, 11, 20, 2016) . "')", $where, '10 years');

	pg_assert_same('', pg_privacy_rule_candidate_where('orders', pg_privacy_rules_normalise(array())['orders'], $now, array('pending' => '(1)')), 'orders: never');
	pg_assert_same('', pg_privacy_rule_candidate_where('carts', $rules['carts'], $now, array('pending' => '')), 'no personal column');

	$where = pg_privacy_rule_candidate_where('contacts', $rules['contacts'], $now, array());
	$cutoff = mktime(12, 0, 0, 11, 20, 2023);
	pg_assert_contains("(COALESCE(contacts.timestamp, 0) < '" . $cutoff . "')", $where, 'unchanged for 3 years');
	pg_assert_contains("(o.order_date >= '" . $cutoff . "')", $where, 'no order');
	pg_assert_contains("(f.submitted_timestamp >= '" . $cutoff . "')", $where, 'no form');
	pg_assert_contains("(u.user_online_timestamp >= '" . $cutoff . "')", $where, 'no sign-in');

	$forms = pg_privacy_rules_normalise(array('forms' => array('period' => 2, 'pages' => array(12 => 5, 13 => 0))))['forms'];
	$where = preg_replace('/\s+/', ' ', pg_privacy_rule_candidate_where('forms', $forms, $now, array()));
	pg_assert_contains("((submitted_timestamp < '" . mktime(12, 0, 0, 11, 20, 2024) . "') AND (page_id NOT IN ('12', '13')))", $where, 'the rule');
	pg_assert_contains("((page_id = '12') AND (submitted_timestamp < '" . mktime(12, 0, 0, 11, 20, 2021) . "'))", $where, 'a form that says otherwise');
	pg_assert_true(strpos($where, "page_id = '13'") === false, 'a form kept');
}

// A rule runs only after its yes, at night, not before its interval.
function test_privacy_rule_due()
{
	$now = mktime(2, 0, 0, 11, 20, 2026);
	$rule = array('on' => true, 'period' => 3, 'approved_at' => $now - 100, 'approved_by' => 1);
	$idle = array('last_run_at' => 0, 'started_at' => 0);

	pg_assert_same(true, pg_privacy_rule_due($rule, $idle, 1, $now), 'approved, never run');
	pg_assert_same(false, pg_privacy_rule_due(array_merge($rule, array('approved_at' => 0)), $idle, 1, $now), 'the dry run waits for a yes');
	pg_assert_same(false, pg_privacy_rule_due(array_merge($rule, array('on' => false)), $idle, 1, $now), 'off');
	pg_assert_same(false, pg_privacy_rule_due(array_merge($rule, array('period' => null)), $idle, 1, $now), 'never');
	pg_assert_same(false, pg_privacy_rule_due($rule, array('last_run_at' => $now - 3 * 86400, 'started_at' => 0), 7, $now), 'within the interval');
	pg_assert_same(true, pg_privacy_rule_due($rule, array('last_run_at' => $now - 8 * 86400, 'started_at' => 0), 7, $now), 'past the interval');
	pg_assert_same(true, pg_privacy_rule_due($rule, array('last_run_at' => $now - 3600, 'started_at' => $now - 60), 7, $now), 'a pass under way goes on');

	pg_assert_same(true, pg_privacy_rules_night(mktime(0, 30, 0, 11, 20, 2026)), 'after midnight');
	pg_assert_same(true, pg_privacy_rules_night(mktime(5, 59, 0, 11, 20, 2026)), 'before six');
	pg_assert_same(false, pg_privacy_rules_night(mktime(6, 0, 0, 11, 20, 2026)), 'six');
	pg_assert_same(false, pg_privacy_rules_night(mktime(14, 0, 0, 11, 20, 2026)), 'afternoon');
}

// P9: the run's line names the rule and counts, never a person.
function test_privacy_rule_audit_entry()
{
	$counts = pg_privacy_rule_counts_add(pg_privacy_rule_counts_add(pg_privacy_rule_counts(), array('skip' => 'booking', 'tables' => array())), array('skip' => '', 'tables' => array('contacts' => array('action' => 'delete', 'rows' => 1), 'orders' => array('action' => 'anonymise', 'rows' => 2))));
	$entry = pg_privacy_rule_audit_entry('contacts', array('on' => true, 'period' => 3), $counts);

	pg_assert_same('contacts', $entry['rule'], 'rule');
	pg_assert_same(3, $entry['period'], 'period');
	pg_assert_same('years', $entry['unit'], 'unit');
	pg_assert_same(2, $entry['checked'], 'checked');
	pg_assert_same(1, $entry['changed'], 'changed');
	pg_assert_same(array('booking' => 1), $entry['skipped'], 'skipped');
	pg_assert_same(array('contacts' => 1), $entry['deleted'], 'deleted');
	pg_assert_same(array('orders' => 2), $entry['anonymised'], 'anonymised');

	foreach (array('person', 'user_ids', 'contact_ids', 'email', 'name') as $field) {
		pg_assert_true(!array_key_exists($field, $entry), 'no ' . $field);
	}
}

// An open order: placed and paid recently or with its refund unresolved.
function test_privacy_rule_open_order_where()
{
	$now = mktime(12, 0, 0, 11, 20, 2026);
	$where = pg_privacy_rule_open_order_where($now, true);

	pg_assert_contains("(status IN ('complete', 'exported'))", $where, 'placed');
	pg_assert_contains("(order_date >= '" . ($now - 60 * 86400) . "')", $where, 'recent');
	pg_assert_contains("(refund_status IN ('pending', 'failed', 'manual_required'))", $where, 'money owed back');
	pg_assert_true(strpos(pg_privacy_rule_open_order_where($now, false), 'refund_status') === false, 'no refund column yet');
}

// Site chats: a rule of their own, off by default with 60 days suggested,
// reaching only site chats quiet for the period, through the chats' erase
// entries and the attachments' files. The old Chat Retention Period field
// is gone, so the update deletes nothing.
function test_privacy_rule_site_chats()
{
	$catalogue = pg_privacy_rules_catalogue();
	pg_assert_same(array('unit' => 'days', 'default' => 60, 'min' => 7, 'max' => 3650, 'table' => 'chat_conversations', 'entries' => array('files', 'chat_messages', 'chat_conversations')), $catalogue['chats'], 'the rule');

	$rules = pg_privacy_rules_normalise(array());
	pg_assert_same(false, $rules['chats']['on'], 'off by default');
	pg_assert_same(60, $rules['chats']['period'], '60 days suggested');
	pg_assert_same(array('files', 'chat_messages', 'chat_conversations'), pg_privacy_rule_entries('chats'), 'the chats\' erase entries');
	pg_assert_true(isset(pg_privacy_rules_labels()['rules']['chats']), 'a name in the Privacy centre');

	$now = mktime(12, 0, 0, 11, 20, 2026);
	pg_assert_same("(channel = 'site') AND (GREATEST(last_message_at, created_at) < '" . ($now - 60 * 86400) . "')", pg_privacy_rule_candidate_where('chats', $rules['chats'], $now, array()), 'site chats quiet for 60 days');

	$out = pg_privacy_rules_from_input(array('chats' => array('on' => '1', 'period' => '6')), $rules);
	pg_assert_same(array('chats' => 'period'), $out['errors'], 'under a week refused');
	$out = pg_privacy_rules_from_input(array('chats' => array('on' => '1', 'period' => '60')), $rules);
	pg_assert_same(array(), $out['errors'], '60 days taken');
	pg_assert_same(0, $out['rules']['chats']['approved_at'], 'a dry run and a yes come first');

	$one = pg_test_function_source('pg_privacy_rule_one');
	pg_assert_contains("SELECT attachment_file_id FROM chat_messages WHERE (attachment_file_id > 0) AND (conversation_id = '\" . \$id . \"')", $one, 'the attachments go with the chat');

	foreach (array('init.php', 'includes/settings/contact.php', 'includes/settings/contact.save.php', 'includes/settings/prep.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_false(stripos($source, 'chat_retention') !== false, $file . ': no Chat Retention Period');
	}
}
