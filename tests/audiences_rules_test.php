<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the rule model of contact groups filled by rules (2026.11.0,
 * 11.0.155; includes/fn/audiences.php): the catalogue, the one shape a rule
 * set is cleaned to, the SQL builder and the upgrade step.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one top-level function, up to the next one.
function audiences_rules_test_body($source, $name)
{
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// One valid condition for every key of the catalogue.
function audiences_rules_test_samples()
{
	return array(
		'country' => array('key' => 'country', 'op' => 'eq', 'value' => 'Germany'),
		'city' => array('key' => 'city', 'op' => 'neq', 'value' => 'Ankara'),
		'lead_source' => array('key' => 'lead_source', 'op' => 'contains', 'value' => 'fair'),
		'field' => array('key' => 'field', 'op' => 'eq', 'field' => 'company', 'value' => 'Kodpen'),
		'email_opt_in' => array('key' => 'email_opt_in', 'op' => 'yes'),
		'consent' => array('key' => 'consent', 'op' => 'granted', 'value' => 'sms:marketing'),
		'membership' => array('key' => 'membership', 'op' => 'expiring', 'value' => 30),
		'customer' => array('key' => 'customer', 'op' => 'no'),
		'order_count' => array('key' => 'order_count', 'op' => 'gte', 'value' => 2),
		'order_total' => array('key' => 'order_total', 'op' => 'gte', 'value' => 100000),
		'last_order' => array('key' => 'last_order', 'op' => 'within', 'value' => 90),
		'bought_product' => array('key' => 'bought_product', 'op' => 'yes', 'value' => 12),
		'bought_product_group' => array('key' => 'bought_product_group', 'op' => 'no', 'value' => 3),
		'left_cart' => array('key' => 'left_cart', 'op' => 'within', 'value' => 7),
		'returned' => array('key' => 'returned', 'op' => 'within', 'value' => 60),
		'subscription' => array('key' => 'subscription', 'op' => 'eq', 'value' => 'past_due'),
		'submitted_form' => array('key' => 'submitted_form', 'op' => 'yes', 'value' => 44),
		'form_field' => array('key' => 'form_field', 'op' => 'eq', 'field' => 9, 'value' => 'Yes'),
		'visited' => array('key' => 'visited', 'op' => 'not_within', 'value' => 30),
		'landing_page' => array('key' => 'landing_page', 'op' => 'eq', 'value' => 'spring-sale', 'days' => 30),
		'utm_source' => array('key' => 'utm_source', 'op' => 'eq', 'value' => 'newsletter', 'days' => 14),
		'utm_campaign' => array('key' => 'utm_campaign', 'op' => 'eq', 'value' => 'black-friday', 'days' => 14),
		'group' => array('key' => 'group', 'op' => 'not_in', 'value' => 5),
	);
}

// The query with its placeholders filled as db_q() would (addslashes in
// place of the connection's escape), or null when they do not fit.
function audiences_rules_test_bound(array $query)
{
	return pg_db_bind($query['sql'], $query['params'], 'addslashes');
}

function test_audiences_catalogue_has_a_sample_for_every_key()
{
	$samples = audiences_rules_test_samples();

	foreach (pg_audience_conditions() as $key => $entry) {
		pg_assert_true(isset($samples[$key]), 'sample for ' . $key);
		pg_assert_true(is_string($entry['label']) && ($entry['label'] !== ''), 'label of ' . $key);
		pg_assert_true(is_string($entry['table']) && ($entry['table'] !== ''), 'table of ' . $key);

		foreach ($entry['ops'] as $op) {
			pg_assert_true(array_key_exists($op, pg_audience_operators()), $key . ' operator ' . $op);
		}
	}

	pg_assert_same(count(pg_audience_conditions()), count($samples), 'no sample for a removed key');
}

function test_audiences_every_condition_compiles()
{
	foreach (audiences_rules_test_samples() as $key => $condition) {
		$query = pg_audience_sql(array('match' => 'all', 'conditions' => array($condition)), 1800000000);

		pg_assert_true(strpos($query['sql'], 'SELECT contacts.id FROM contacts WHERE (') === 0, $key . ' selects contact ids');
		pg_assert_true(strpos($query['sql'], '1 = 0') === false, $key . ' selects somebody');
		pg_assert_same(substr_count($query['sql'], '?'), count($query['params']), $key . ' placeholders fit');
		pg_assert_true(audiences_rules_test_bound($query) !== null, $key . ' binds');
	}
}

function test_audiences_every_operator_compiles()
{
	$samples = audiences_rules_test_samples();

	foreach (pg_audience_conditions() as $key => $entry) {
		foreach ($entry['ops'] as $op) {
			$condition = $samples[$key];
			$condition['op'] = $op;
			$query = pg_audience_sql(array('conditions' => array($condition)), 1800000000);

			pg_assert_true(strpos($query['sql'], '1 = 0') === false, $key . ' ' . $op . ' kept');
			pg_assert_same(substr_count($query['sql'], '?'), count($query['params']), $key . ' ' . $op . ' placeholders fit');
		}
	}
}

function test_audiences_no_condition_selects_nobody()
{
	$nobody = array('sql' => 'SELECT contacts.id FROM contacts WHERE 1 = 0', 'params' => array());

	pg_assert_same($nobody, pg_audience_sql(array()));
	pg_assert_same($nobody, pg_audience_sql(null));
	pg_assert_same($nobody, pg_audience_sql('not json'));
	pg_assert_same($nobody, pg_audience_sql(array('match' => 'any', 'conditions' => array())));
	pg_assert_same($nobody, pg_audience_sql(array('conditions' => array(array('key' => 'nope', 'op' => 'eq', 'value' => 1)))), 'only unknown keys');
	pg_assert_same($nobody, pg_audience_sql(array('conditions' => array(array('match' => 'any', 'conditions' => array())))), 'only an empty nested set');
}

function test_audiences_all_and_any_nesting()
{
	$rules = array(
		'match' => 'all',
		'conditions' => array(
			array('key' => 'country', 'op' => 'eq', 'value' => 'Germany'),
			array('match' => 'any', 'conditions' => array(
				array('key' => 'customer', 'op' => 'no'),
				array('key' => 'membership', 'op' => 'member'),
			)),
		),
	);

	$query = pg_audience_sql($rules, 1800000000);

	pg_assert_same(
		"SELECT contacts.id FROM contacts WHERE ((COALESCE(contacts.business_country, '') = ? OR COALESCE(contacts.home_country, '') = ?)"
		. " AND ((contacts.id NOT IN (SELECT orders.contact_id FROM orders WHERE orders.status <> 'incomplete' AND orders.contact_id <> 0))"
		. " OR (COALESCE(contacts.member_id, '') <> '')))",
		$query['sql']
	);
	pg_assert_same(array('Germany', 'Germany'), $query['params']);

	$rules['match'] = 'any';
	$query = pg_audience_sql($rules, 1800000000);
	pg_assert_true(strpos($query['sql'], "= ?) OR ((contacts.id NOT IN") !== false, 'the top level joined by OR');
}

function test_audiences_placeholders_never_carry_raw_text()
{
	$evil = "x' OR 1=1 -- ?";
	$keys = array('country', 'city', 'lead_source', 'field', 'form_field', 'landing_page', 'utm_source', 'utm_campaign');
	$samples = audiences_rules_test_samples();

	foreach ($keys as $key) {
		$condition = $samples[$key];
		$condition['value'] = $evil;
		$query = pg_audience_sql(array('conditions' => array($condition)), 1800000000);

		pg_assert_true(strpos($query['sql'], 'OR 1=1') === false, $key . ': the value is not in the SQL');
		pg_assert_true(in_array($evil, $query['params'], true) || in_array('%' . $evil . '%', $query['params'], true), $key . ': the value is a parameter');
		pg_assert_contains("\\'", (string) audiences_rules_test_bound($query), $key . ': quoted when bound');
	}

	$query = pg_audience_sql(array('conditions' => array(array('key' => 'field', 'op' => 'eq', 'field' => 'company; DROP TABLE contacts', 'value' => 'a'))));
	pg_assert_same('SELECT contacts.id FROM contacts WHERE 1 = 0', $query['sql'], 'a field outside the list is dropped');
}

function test_audiences_contains_keeps_wildcards_plain()
{
	$query = pg_audience_sql(array('conditions' => array(array('key' => 'lead_source', 'op' => 'contains', 'value' => '50%_off'))));

	pg_assert_same(array('%50\\%\\_off%'), $query['params']);
}

function test_audiences_order_count_and_total()
{
	$at_most = pg_audience_sql(array('conditions' => array(array('key' => 'order_count', 'op' => 'lte', 'value' => 1))));
	pg_assert_contains('contacts.id NOT IN (SELECT orders.contact_id FROM orders', $at_most['sql'], 'at most n counts a contact with no order');
	pg_assert_contains('HAVING COUNT(*) > ?', $at_most['sql']);
	pg_assert_same(array(1), $at_most['params']);

	$none = pg_audience_sql(array('conditions' => array(array('key' => 'order_count', 'op' => 'eq', 'value' => 0))));
	pg_assert_contains('NOT IN', $none['sql'], 'exactly none');
	pg_assert_same(array(0), $none['params']);

	$exact = pg_audience_sql(array('conditions' => array(array('key' => 'order_count', 'op' => 'eq', 'value' => 3))));
	pg_assert_contains('contacts.id IN (', $exact['sql']);
	pg_assert_contains('HAVING COUNT(*) = ?', $exact['sql']);

	$spent = pg_audience_sql(array('conditions' => array(array('key' => 'order_total', 'op' => 'gte', 'value' => '25000'))));
	pg_assert_contains('HAVING SUM(orders.total) >= ?', $spent['sql']);
	pg_assert_same(array(25000), $spent['params'], 'minor units as an integer');

	$anyone = pg_audience_sql(array('conditions' => array(array('key' => 'order_count', 'op' => 'gte', 'value' => 0))));
	pg_assert_same('SELECT contacts.id FROM contacts WHERE ((1 = 1))', $anyone['sql'], 'at least none is everybody');
}

function test_audiences_day_windows()
{
	$now = 1800000000;
	$query = pg_audience_sql(array('conditions' => array(array('key' => 'last_order', 'op' => 'within', 'value' => 90))), $now);
	pg_assert_same(array($now - 90 * 86400), $query['params']);
	pg_assert_contains('orders.order_date >= ?', $query['sql']);

	$query = pg_audience_sql(array('conditions' => array(array('key' => 'utm_source', 'op' => 'eq', 'value' => 'mail', 'days' => 7))), $now);
	pg_assert_same(array($now - 7 * 86400, 'mail'), $query['params']);
	pg_assert_contains('visitors.start_timestamp >= ?', $query['sql'], 'a visit is read within its window');
	pg_assert_contains('INNER JOIN orders ON orders.id = visitors.order_id', $query['sql'], 'through the order the visit made');

	$query = pg_audience_sql(array('conditions' => array(array('key' => 'membership', 'op' => 'expiring', 'value' => 10))), $now);
	pg_assert_same(array(date('Y-m-d', $now), date('Y-m-d', strtotime(date('Y-m-d', $now) . ' +10 days'))), $query['params']);
}

// expiration_date is a day in the site's zone (init.php sets PHP's zone to
// it), so the membership conditions compare it with the site's today, not
// UTC's, and count "ends within n days" in calendar days.
function test_audiences_membership_reads_the_site_day()
{
	$zone = date_default_timezone_get();

	// 2024-03-10 22:30 UTC is already the 11th in Auckland.
	date_default_timezone_set('Pacific/Auckland');
	$now = gmmktime(22, 30, 0, 3, 10, 2024);

	foreach (array('active', 'expired') as $op) {
		$query = pg_audience_sql(array('conditions' => array(array('key' => 'membership', 'op' => $op))), $now);
		pg_assert_same(array('2024-03-11'), $query['params'], $op . ' compares with the site day');
	}

	// 2024-10-20 00:30 in Berlin is still the 19th in UTC; thirty days on
	// crosses the 25-hour day of the autumn change.
	date_default_timezone_set('Europe/Berlin');
	$now = mktime(0, 30, 0, 10, 20, 2024);
	$query = pg_audience_sql(array('conditions' => array(array('key' => 'membership', 'op' => 'expiring', 'value' => 30))), $now);
	pg_assert_same(array('2024-10-20', '2024-11-19'), $query['params'], 'ends within thirty site days');

	date_default_timezone_set($zone);
}

function test_audiences_consent_reads_the_latest_answer()
{
	$query = pg_audience_sql(array('conditions' => array(array('key' => 'consent', 'op' => 'not_granted', 'value' => 'email:marketing'))));

	pg_assert_same(array('email', 'marketing'), $query['params']);
	pg_assert_contains('contacts.id NOT IN (SELECT consent_log.contact_id', $query['sql']);
	pg_assert_contains("later.status <> 'pending'", $query['sql'], 'a pending row is no answer');
}

function test_audiences_clean_shape_and_bounds()
{
	$clean = pg_audience_rules_clean(json_encode(array(
		'match' => 'whatever',
		'extra' => 'dropped',
		'conditions' => array(
			array('key' => 'order_count', 'op' => 'gte', 'value' => '3', 'junk' => 1),
			array('key' => 'order_count', 'op' => 'gte', 'value' => -1),
			array('key' => 'order_count', 'op' => 'gte', 'value' => 1.5),
			array('key' => 'last_order', 'op' => 'within', 'value' => 0),
			array('key' => 'last_order', 'op' => 'within', 'value' => 99999),
			array('key' => 'country', 'op' => 'contains', 'value' => 'x'),
			array('key' => 'country', 'op' => 'eq', 'value' => '   '),
			array('key' => 'consent', 'op' => 'granted', 'value' => 'cookie:a'),
			array('key' => 'subscription', 'op' => 'eq', 'value' => 'gone'),
			array('key' => 'landing_page', 'op' => 'eq', 'value' => 'home'),
			array('key' => 'membership', 'op' => 'active', 'value' => 'ignored'),
			'not a condition',
		),
	)));

	pg_assert_same(array(
		'match' => 'all',
		'conditions' => array(
			array('key' => 'order_count', 'op' => 'gte', 'value' => 3),
			array('key' => 'membership', 'op' => 'active'),
		),
	), $clean);

	$long = pg_audience_rules_clean(array('conditions' => array(array('key' => 'city', 'op' => 'eq', 'value' => str_repeat('ş', 150)))));
	pg_assert_same(PG_AUDIENCE_MAX_TEXT, mb_strlen($long['conditions'][0]['value'], 'UTF-8'), 'text cut to its bound');

	$many = array();
	for ($i = 0; $i < 30; $i++) {
		$many[] = array('key' => 'customer', 'op' => 'yes');
	}
	pg_assert_same(PG_AUDIENCE_MAX_CONDITIONS, count(pg_audience_rules_clean(array('conditions' => $many))['conditions']));

	$deep = pg_audience_rules_clean(array('conditions' => array(
		array('match' => 'any', 'conditions' => array(
			array('key' => 'customer', 'op' => 'yes'),
			array('match' => 'all', 'conditions' => array(array('key' => 'customer', 'op' => 'no'))),
		)),
	)));
	pg_assert_same(array(
		'match' => 'all',
		'conditions' => array(
			array('match' => 'any', 'conditions' => array(array('key' => 'customer', 'op' => 'yes'))),
		),
	), $deep, 'one nesting level only');

	pg_assert_same($clean, pg_audience_rules_clean($clean), 'cleaning a clean set changes nothing');
}

function test_audiences_upgrade_step()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	$entry = audiences_rules_test_body($source, 'upgrade_to_2026_11_0');
	$step = audiences_rules_test_body($source, 'upgrade_2026_11_0_contact_group_rules');

	pg_assert_true(preg_match('/upgrade_2026_11_0_contact_group_rules\(\);\s+\/\/ 11\.0\.155\b/', $entry) === 1, 'called with its label');
	pg_assert_same(1, preg_match_all('/\/\/ 11\.0\.155\b/', $entry), 'the label is used once');
	pg_assert_contains("install_add_column('contact_groups', 'kind', \"ENUM('static','dynamic') NOT NULL DEFAULT 'static'\")", $step);
	pg_assert_contains("install_add_column('contact_groups', 'rules', \"TEXT NULL\")", $step);
	pg_assert_contains("install_add_column('contact_groups', 'computed_at', \"INT UNSIGNED NOT NULL DEFAULT 0\")", $step);
	pg_assert_contains("install_add_column('contact_groups', 'compute_cursor', \"INT UNSIGNED NOT NULL DEFAULT 0\")", $step);
	pg_assert_contains("'source' => \"ENUM('hand','form','product','rule') NOT NULL DEFAULT 'hand'\"", $step);
	pg_assert_contains("install_add_index('contacts_contact_groups_xref', 'idx_group_source', \"INDEX idx_group_source (contact_group_id, source)\")", $step);
	pg_assert_contains('install_note(', $step);
	pg_assert_false(strpos($step, 'db(') !== false, 'no raw statement');

	pg_assert_false(pg_audience_ready(), 'no database in the test run');
	pg_assert_same(null, pg_audience_count(array('conditions' => array(array('key' => 'customer', 'op' => 'yes')))), 'nothing counted before the upgrade');
}
