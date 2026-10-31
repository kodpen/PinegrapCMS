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

function test_workspace_visit_contact_api_input_passes_it_only_when_sent()
{
	$sent = ws_api_plan_item_input(array('kind' => 'visit', 'title' => 'Showroom visit', 'contact_id' => '42'));

	pg_assert_same(42, $sent['contact_id'] ?? null, 'sent, it goes to ws_event_save()');
	pg_assert_same(0, ws_api_plan_item_input(array('kind' => 'visit', 'contact_id' => -3))['contact_id'] ?? null, 'below 0 clears it');
	pg_assert_true(!array_key_exists('contact_id', ws_api_plan_item_input(array('kind' => 'visit', 'title' => 'Showroom visit'))), 'left out, the item keeps its customer');
}

function test_workspace_visit_contact_api_refusal_on_the_customer_is_422()
{
	pg_assert_same('contact_id', ws_api_plan_item_refused_field('contact_id'), 'a customer the owner cannot open is the request\'s');
	pg_assert_same('start_date', ws_api_plan_item_refused_field('start'), 'dates keep their names');
	pg_assert_same('end_date', ws_api_plan_item_refused_field('end'));
	pg_assert_same(null, ws_api_plan_item_refused_field(''), 'anything else is a right: 403');
	pg_assert_true(strpos(workspace_visit_contact_test_source('ws_api_plan_item_refused'), 'ws_api_plan_item_refused_field(') !== false, 'the refusal asks it');
}

function test_workspace_visit_contact_api_schema_and_would()
{
	pg_assert_same('WorkspacePlanItemContact?', ws_api_plan_item_schema()['contact'] ?? null, 'the plan item lists contact');
	pg_assert_same(array('id' => 'integer?', 'label' => 'string?', 'hidden' => 'boolean'), ws_api_plan_item_contact_schema());

	$would = ws_api_plan_item_would(array('event_id' => 0, 'item' => array('kind' => 'visit', 'scope' => 'people', 'title' => 'Visit', 'starts_at' => 0, 'ends_at' => 0, 'all_day' => 0, 'people' => array(), 'contact_id' => 42)));

	pg_assert_same(42, $would['contact_id'] ?? null, 'a dry run echoes the customer');

	$params = array();

	foreach (ws_api_routes() as $route) {
		if ($route['id'] === 'workspace.plan_items.update') {
			$params = $route['params'];
		}
	}

	$field = array_values(array_filter($params, function ($param) {
		return $param['name'] === 'contact_id';
	}));

	pg_assert_same('int', $field[0]['type'] ?? null, 'the route takes contact_id');
	pg_assert_same(0, $field[0]['min'] ?? null, '0 clears it');
}

function test_workspace_visit_contact_api_names_it_only_with_customers_read()
{
	$rows = array(
		array('id' => '42', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'company' => '', 'email_address' => 'ada@example.com'),
		array('id' => '43', 'first_name' => '', 'last_name' => '', 'company' => 'Analytical Ltd', 'email_address' => ''),
	);

	pg_assert_same(array(42 => null, 43 => null), ws_api_plan_item_contact_names($rows, array(42, 43), array('tasks:read')), 'a key without customers:read names nobody');
	pg_assert_same(array(42 => 'Ada Lovelace', 43 => null), ws_api_plan_item_contact_names($rows, array(42), array('tasks:read', 'customers:read')), 'named with customers:read, where the owner may open the contact');

	$viewer = array('id' => 5, 'role' => 1, 'settings' => true);
	$visit = array(
		'id' => 77, 'kind' => 'visit', 'title' => 'Showroom visit', 'note' => '', 'scope' => 'people', 'department_id' => 0, 'people' => array(5),
		'all_day' => 0, 'starts_at' => mktime(10, 0, 0, 11, 4, 2026), 'ends_at' => mktime(11, 0, 0, 11, 4, 2026), 'yearly' => 0, 'feed_id' => 0,
		'meeting_url' => '', 'created_by' => 5, 'created_at' => mktime(9, 0, 0, 11, 1, 2026), 'contact_id' => 42,
	);
	$contacts = array(42 => 'Ada Lovelace', 43 => null);

	pg_assert_same(array('id' => 42, 'label' => 'Ada Lovelace', 'hidden' => false), ws_api_plan_item_present($viewer, $visit, $contacts)['contact'], 'the named form');
	pg_assert_same(array('id' => null, 'label' => null, 'hidden' => true), ws_api_plan_item_present($viewer, array('contact_id' => 43) + $visit, $contacts)['contact'], 'the hidden form: no id, no label');
	pg_assert_same(null, ws_api_plan_item_present($viewer, array('contact_id' => 44) + $visit, $contacts)['contact'], 'a deleted contact names nobody');
	pg_assert_same(null, ws_api_plan_item_present($viewer, array('kind' => 'meeting') + $visit, $contacts)['contact'], 'only a visit names one');
	pg_assert_same(null, ws_api_plan_item_present($viewer, array('contact_id' => 0) + $visit, $contacts)['contact'], 'a visit about nobody');
}

function test_workspace_visit_contact_api_lists_look_up_in_one_batch()
{
	foreach (array('ws_api_plan_items_list', 'ws_api_my_day_get') as $function) {
		$source = workspace_visit_contact_test_source($function);

		pg_assert_true(strpos($source, 'ws_api_plan_item_contacts($viewer, ') !== false, $function . ' looks the contacts up once');
		pg_assert_true(preg_match('~ws_api_plan_item_present\(\$viewer, \$event, \$\w+\)~', $source) === 1, $function . ' hands them to each item');
	}

	$batch = workspace_visit_contact_test_source('ws_api_plan_item_contacts');

	pg_assert_true(strpos($batch, 'ws_event_contacts_ready()') !== false, 'only once the column is there');
	pg_assert_true(strpos($batch, 'ws_viewer_contacts_open($viewer, ') !== false, 'the owner\'s own rule');
	pg_assert_true(strpos($batch, 'api_current_scopes()') !== false, 'the key\'s scopes');
	pg_assert_same(1, substr_count($batch, 'db_items('), 'one query for the page');
}
