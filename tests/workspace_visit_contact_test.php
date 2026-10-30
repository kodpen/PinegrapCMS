<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the customer a Workspace customer visit is about
 * (ws_events.contact_id, includes/workspace/schedule.php): the upgrade step
 * adds the column and its index with the idempotent helpers; a visit is
 * saved with a contact the person may open and refused one they may not;
 * the visit joins the customer's history and card; the column is a contact
 * holder, so a merge re-points it and the erasure finds it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

// The body of a function with its comment lines left out.
function workspace_visit_contact_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return preg_replace('~^\s*(//|\*|/\*\*).*$~m', '', implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1)));
}

function test_workspace_visit_contact_step_adds_the_column_and_index()
{
	$file = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_true(preg_match('~^\s*upgrade_2026_11_0_workspace_visit_contact\(\);\s*// 11\.0\.118$~m', $file) === 1, 'the step is called, labelled 11.0.118');
	pg_assert_true(preg_match('~function upgrade_2026_11_0_workspace_visit_contact\(\) \{(.*?)\n\}~s', $file, $match) === 1, 'the step exists');
	pg_assert_true(strpos($match[1], "install_add_column('ws_events', 'contact_id', \"INT UNSIGNED NOT NULL DEFAULT 0\");") !== false, 'the column through install_add_column()');
	pg_assert_true(strpos($match[1], "install_add_index('ws_events', 'idx_contact', \"INDEX idx_contact (contact_id)\");") !== false, 'the index through install_add_index()');
	pg_assert_true(stripos($match[1], 'ALTER') === false, 'no ALTER of its own');
}

function test_workspace_visit_contact_pick_takes_a_contact_the_person_may_open()
{
	pg_assert_same(array('ok' => true, 'contact_id' => 42), ws_event_contact_pick('visit', array('contact_id' => '42'), null, array(42)), 'a new visit with a contact the person may open');
	pg_assert_same(array('ok' => false, 'contact_id' => 0), ws_event_contact_pick('visit', array('contact_id' => 43), null, array()), 'a contact the person may not open is refused');
	pg_assert_same(array('ok' => false, 'contact_id' => 0), ws_event_contact_pick('visit', array('contact_id' => 43), array('contact_id' => 42), array(42)), 'an open contact is no pass for another one');
	pg_assert_same(array('ok' => true, 'contact_id' => 0), ws_event_contact_pick('visit', array('contact_id' => 0), array('contact_id' => 42), array()), 'the customer taken away');
	pg_assert_same(array('ok' => true, 'contact_id' => 0), ws_event_contact_pick('visit', array('contact_id' => -5), null, array()), 'nonsense is no customer');
}

function test_workspace_visit_contact_pick_keeps_what_it_has()
{
	pg_assert_same(array('ok' => true, 'contact_id' => 42), ws_event_contact_pick('visit', array('title' => 'Visit'), array('contact_id' => '42'), array()), 'not sent: the visit keeps its customer (the API, an older form)');
	pg_assert_same(array('ok' => true, 'contact_id' => 42), ws_event_contact_pick('visit', array('contact_id' => 42), array('contact_id' => '42'), array()), 'sent back unchanged by somebody who cannot open it: kept');
	pg_assert_same(array('ok' => true, 'contact_id' => 0), ws_event_contact_pick('visit', array(), null, array()), 'a new visit about nobody');
}

function test_workspace_visit_contact_pick_names_nobody_on_other_kinds()
{
	foreach (array('meeting', 'leave', 'holiday', 'other') as $kind) {
		pg_assert_same(array('ok' => true, 'contact_id' => 0), ws_event_contact_pick($kind, array('contact_id' => 42), array('contact_id' => 42), array(42)), $kind . ' names no customer');
	}
}

function test_workspace_visit_contact_save_checks_the_reader()
{
	$check = workspace_visit_contact_test_source('ws_event_save_check');

	pg_assert_true(strpos($check, 'ws_event_contacts_ready()') !== false, 'only once the column is there');
	pg_assert_true(strpos($check, 'ws_viewer_contacts_open($viewer, array($asked))') !== false, 'asked of the person saving');
	pg_assert_true(strpos($check, 'SELECT id FROM contacts WHERE id = \'" . $asked . "\'') !== false, 'a contact that is not there is never open');
	pg_assert_true(strpos($check, "lang('That customer could not be found.'), 'contact_id'") !== false, 'refused on the field, as a contact that is not there');

	$save = workspace_visit_contact_test_source('ws_event_save');

	pg_assert_same(3, substr_count($save, '$contact_set'), 'made once, written on an update and after an insert');
	pg_assert_true(strpos($save, "ws_event_contacts_ready() ? \", contact_id = '\"") !== false, 'written only once the column is there');
}

function test_workspace_visit_contact_history_has_the_visit()
{
	$rights = array('orders' => true, 'invoices' => false, 'sales_quotes' => false, 'forms' => false, 'workspace' => true);
	$data = array(
		'orders' => array(array('id' => 501, 'order_number' => '1042', 'status' => 'complete', 'total' => '100', 'order_date' => 1000)),
		'visits' => array(array('id' => 77, 'title' => 'Showroom visit', 'starts_at' => mktime(10, 0, 0, 11, 4, 2026))),
	);
	$money = function ($minor, $currency) {
		return (string) $minor;
	};

	$history = ws_customer_history_build($rights, $data, $money, '/pg/', 20);

	pg_assert_same(array('visit', 'order'), array_column($history['entries'], 'kind'), 'the visit beside the other sources, newest first');
	pg_assert_same('Showroom visit', $history['entries'][0]['title']);
	pg_assert_same(lang('Customer visit'), $history['entries'][0]['detail']);
	pg_assert_same('/pg/workspace_calendar.php?month=2026-11&event=77', $history['entries'][0]['url'], 'opens the visit on the Work Calendar');

	$rights['workspace'] = false;

	pg_assert_same(array('order'), array_column(ws_customer_history_build($rights, $data, $money, '/pg/', 20)['entries'], 'kind'), 'no visit for whoever is no member of the Workspace');

	$read = workspace_visit_contact_test_source('ws_customer_history_visit_rows');

	pg_assert_true(strpos($read, "kind = 'visit'") !== false, 'only visits');
	pg_assert_true(strpos($read, 'ws_event_contacts_ready()') !== false, 'nothing before the column is there');
	pg_assert_true(strpos(workspace_visit_contact_test_source('ws_customer_history'), "\$data['visits'] = ws_customer_history_visit_rows(") !== false, 'read with the Workspace sources');
}

function test_workspace_visit_contact_card_section()
{
	$at = mktime(10, 0, 0, 11, 4, 2026);

	pg_assert_same(array(array('id' => 77, 'title' => 'Showroom visit', 'date_text' => '04.11.2026', 'url' => '/pg/workspace_calendar.php?month=2026-11&event=77')),
		ws_customer_card_visits(array(array('id' => '77', 'title' => 'Showroom visit', 'starts_at' => (string) $at)), '/pg/'));
	pg_assert_same(array(), ws_customer_card_visits(array(), '/pg/'), 'no visit, no section');
	pg_assert_true(strpos(workspace_visit_contact_test_source('ws_customer_card'), "\$card['sections']['visits']") !== false, 'the card carries the section');
}

function test_workspace_visit_contact_is_a_contact_holder()
{
	$holders = pg_contact_holders();

	pg_assert_same(array('column' => 'contact_id', 'merge' => 'move', 'erase' => 'find'), $holders['ws_events'] ?? null, 'a merge re-points it, the erasure finds it');

	$erase = pg_privacy_erase_tables();

	pg_assert_same(array('contact_id' => 'contact_ids'), $erase['ws_events']['find'] ?? null, 'found by the person\'s contacts');
	pg_assert_same(array('contact_id' => 0), $erase['ws_events']['fields'] ?? null, 'the visit stays on the plan and names nobody');
}
