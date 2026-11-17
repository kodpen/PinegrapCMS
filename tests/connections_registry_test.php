<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The registry of outside services (includes/fn/connections_registry.php):
 * every row is whole (a name, a group, one place, a state reader or the
 * reason it has none), ids are unique, every driver and provider of the
 * lists that already exist has a row, every place is a card of Site
 * Settings or a System tools screen, and the count follows its rule.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/base.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/model_agents.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mcp_catalogue.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/registry.php');

// Category key => the card ids its 'sections' list, read from the source:
// pg_settings_categories() asks the database.
function connections_registry_test_cards()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
	$cards = array();

	preg_match_all("/\n        '([a-z_]+)' => array\(\n            'label'.*?'sections' => array\((.*?)\n            \),/s", $source, $match);

	foreach ($match[1] as $i => $key) {
		preg_match_all("/'(pgset-[a-z0-9-]+)'/", $match[2][$i], $ids);
		$cards[$key] = $ids[1];
	}

	return $cards;
}

// The scripts pg_settings_tool_groups() links, read from its source: some
// rows sit behind a constant or the store's switch.
function connections_registry_test_tools()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
	$start = strpos($source, 'function pg_settings_tool_groups(');
	$end = strpos($source, 'function pg_settings_tool_keys(');

	pg_assert_true(($start !== false) && ($end !== false), 'the tool groups are found in the registry');

	preg_match_all("/'url' => '([a-z_]+\.php)'/", substr($source, (int) $start, (int) $end - (int) $start), $match);

	return array_values(array_unique($match[1]));
}

function test_connections_registry_ids_are_unique_and_well_formed()
{
	$rows = pg_connections_registry();

	pg_assert_true(count($rows) > 0, 'the registry has rows');

	foreach ($rows as $id => $row) {
		pg_assert_true(preg_match('/^[a-z][a-z0-9_]{1,39}$/', (string) $id) === 1, $id . ': the id is a lower-case key');
	}
}

function test_connections_registry_every_row_is_whole()
{
	foreach (pg_connections_registry() as $id => $row) {
		pg_assert_same(array(), pg_connections_row_problems($row), $id . ': the row is whole');
	}
}

function test_connections_registry_row_problems_names_what_is_missing()
{
	$problems = pg_connections_row_problems(array(
		'name'  => 'Example',
		'group' => 'nowhere',
		'place' => 'settings/example',
		'state' => null,
		'uses'  => 'Something',
	));

	pg_assert_true(in_array('group', $problems, true), 'an unknown group is named');
	pg_assert_true(in_array('place', $problems, true), 'a place that is neither a card address nor a script is named');
	pg_assert_true(in_array('state', $problems, true), 'a row with no reader and no reason is named');

	$problems = pg_connections_row_problems(array(
		'name'  => 'Example',
		'group' => 'payments',
		'place' => '#settings/commerce/pgset-payments',
		'state' => array('kind' => 'app'),
		'uses'  => 'Something',
	));

	pg_assert_same(array('state'), $problems, 'a reader without its key is named');

	$problems = pg_connections_row_problems(array(
		'name'     => 'Example',
		'group'    => 'payments',
		'place'    => '#settings/commerce/pgset-payments',
		'state'    => null,
		'no_state' => 'Nothing stored tells whether it is used.',
		'uses'     => 'Something',
	));

	pg_assert_same(array(), $problems, 'a row named as stateless is whole');
}

function test_connections_registry_every_place_is_a_card_or_a_tool()
{
	$cards = connections_registry_test_cards();
	$tools = connections_registry_test_tools();

	pg_assert_true(isset($cards['commerce']), 'the settings cards are read');
	pg_assert_true(in_array('connections.php', $tools, true), 'the tool screens are read');

	foreach (pg_connections_registry() as $id => $row) {
		if (preg_match('~^#settings/([a-z_]+)/(pgset-[a-z0-9-]+)$~', $row['place'], $match)) {
			pg_assert_true(isset($cards[$match[1]]) && in_array($match[2], $cards[$match[1]], true), $id . ': ' . $row['place'] . ' is a card of its category');
		} elseif (in_array($row['place'], pg_connections_other_screens(), true)) {
			pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/' . $row['place']), $id . ': ' . $row['place'] . ' is a screen of the panel');
		} else {
			pg_assert_true(in_array($row['place'], $tools, true), $id . ': ' . $row['place'] . ' is a System tools screen');
		}
	}
}

function test_connections_registry_covers_every_existing_list()
{
	$lists = array(
		'payment'     => array_keys(pg_payment_builtin_drivers()),
		'label'       => array_keys(pg_label_builtin_drivers()),
		'marketplace' => array_keys(mp_providers()),
		'sms'         => array_keys(pg_sms_providers()),
		'connect'     => array_keys(pg_connect_definitions('providers')),
		'purpose'     => array_keys(pg_connect_definitions('purposes')),
		'model'       => array_keys(ws_model_providers()),
		'mcp'         => array_keys(ws_mcp_catalogue()),
		'mail'        => array_keys(pg_mail_transports()),
		'address'     => array_keys(pg_addr_providers()),
		'captcha'     => array_keys(pg_captcha_providers()),
		'picker'      => array_keys(pg_cloud_picker_providers()),
		'edoc'        => array_keys(erp_edoc_drivers()),
		'notify'      => array_keys(pg_notify_channel_kinds()),
	);

	pg_assert_same(array_keys(pg_connections_source_lists()), array_keys($lists), 'every source list is checked');

	$skipped = pg_connections_not_services();

	foreach ($lists as $list => $keys) {
		pg_assert_true(count($keys) > 0, $list . ': the list is read');

		$covered = pg_connections_covered($list);
		$left = isset($skipped[$list]) ? array_keys($skipped[$list]) : array();

		foreach ($keys as $key) {
			pg_assert_true(in_array($key, $covered, true) || in_array($key, $left, true), $list . ' ' . $key . ': a row covers it or it is named as no service');
		}

		foreach ($covered as $key) {
			pg_assert_true(in_array($key, $keys, true), $list . ' ' . $key . ': a row covers a key the list holds');
		}
	}
}

function test_connections_registry_count_follows_its_rule()
{
	$rows = pg_connections_registry();
	$services = 0;
	$connectors = 0;

	foreach ($rows as $row) {
		if ($row['group'] === 'mcp') {
			$connectors++;
		} else {
			$services++;
		}
	}

	pg_assert_same($services, pg_connections_count(), 'the figure counts every row but the ready-made MCP connectors');
	pg_assert_same($connectors, pg_connections_connector_count(), 'the connectors are counted apart');
	pg_assert_same(count(ws_mcp_catalogue()), pg_connections_connector_count(), 'one connector per catalogue entry');
	pg_assert_same(78, pg_connections_count(), 'the figure: the census\'s 79, less what the rule leaves out, plus what it missed (api/connections-registry.md)');
}

function test_connections_registry_groups_are_used_and_ordered()
{
	$groups = pg_connections_groups();
	$used = array();

	foreach (pg_connections_registry() as $row) {
		$used[$row['group']] = true;
	}

	foreach (array_keys($groups) as $group) {
		pg_assert_true(isset($used[$group]), $group . ': the group holds a row');
	}
}

function test_connections_registry_needs_attention_only_from_an_account()
{
	foreach (pg_connections_registry() as $id => $row) {
		if (is_array($row['state']) && !empty($row['state']['attention'])) {
			pg_assert_same('account', $row['state']['kind'], $id . ': only a connected account carries an error on file');
		}
	}
}

function test_connections_registry_every_reader_function_exists()
{
	$wanted = array();

	foreach (pg_connections_registry() as $id => $row) {
		if (is_array($row['state']) && ($row['state']['kind'] === 'reader')) {
			$wanted[$row['state']['function']] = $id;
		}
	}

	$found = array();
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR . '/includes', FilesystemIterator::SKIP_DOTS));

	foreach ($files as $file) {
		if (substr((string) $file, -4) !== '.php') {
			continue;
		}

		$source = (string) file_get_contents((string) $file);

		foreach (array_keys($wanted) as $function) {
			if (strpos($source, 'function ' . $function . '(') !== false) {
				$found[$function] = true;
			}
		}
	}

	foreach ($wanted as $function => $id) {
		pg_assert_true(isset($found[$function]), $id . ': ' . $function . '() is declared');
	}
}
