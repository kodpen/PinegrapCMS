<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the permission picker of Application Access: every scope group,
 * the core's and the modules', has its area, icon and line; the bulk rule
 * (api_app_bulk_choice()) on rows with read alone, write alone and both, and
 * the money count; the script's twins against the PHP; the picker's markup.
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

if (!function_exists('lang')) {
	function lang($text) { return is_array($text) ? (string) $text['string'] : (string) $text; }
}

if (!function_exists('h')) {
	function h($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/app_access.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/app_permissions_view.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/api.php');

// Every scope group a site can show: the core's and every module's.
function api_app_permissions_test_groups()
{
	return api_scope_groups() + erp_scope_groups() + ws_scope_groups() + translate_scope_groups();
}

// Three rows: read alone, write alone, both.
function api_app_permissions_test_shapes()
{
	return array(
		'seo'       => array('read' => 'seo:read', 'write' => ''),
		'inventory' => array('read' => '', 'write' => 'inventory:write'),
		'orders'    => array('read' => 'orders:read', 'write' => 'orders:write')
	);
}

function test_every_scope_group_has_its_area_icon_and_line()
{
	$areas = api_app_permission_areas();
	$known = api_app_permission_area_keys();

	pg_assert_same(array('store', 'content', 'design', 'workspace', 'accounting', 'site'), array_keys($areas), 'the areas and their order');

	foreach ($areas as $key => $area) {
		pg_assert_true($area[0] !== '' && strpos($area[1], 'bi-') === 0 && $area[2] !== '', $key . ': title, icon and tone');
	}

	foreach (api_app_permissions_test_groups() as $key => $group) {
		pg_assert_true(isset($known[$key]), $key . ': named in api_app_permission_area_keys()');
		pg_assert_true(isset($areas[api_app_permission_area($key, $group)]), $key . ': a known area');

		$text = api_settings_group_text($key, $group);
		pg_assert_true(is_string($text[0]) && $text[0] !== '', $key . ': a name');
		pg_assert_true(is_string($text[1]) && $text[1] !== '', $key . ': a line');
		pg_assert_same(0, strpos($text[2], 'bi-'), $key . ': an icon');
	}

	foreach (array_keys($known) as $key) {
		pg_assert_true(isset($areas[$known[$key]]), $key . ': its area exists');
	}

	pg_assert_same('accounting', api_app_permission_area('erp_cash', array()));
	pg_assert_same('site', api_app_permission_area('unknown_module', array()), 'an unknown group sits under Site');
	pg_assert_same('store', api_app_permission_area('unknown_module', array('area' => 'store')), 'a module may name its area');
}

function test_api_app_bulk_choice_on_read_alone_write_alone_and_both()
{
	$groups = api_app_permissions_test_shapes();

	pg_assert_same(array('seo' => 'read', 'inventory' => 'write', 'orders' => 'write'), api_app_bulk_choice($groups, 'all'), 'All: the highest level of every row');
	pg_assert_same(array('seo' => '', 'inventory' => '', 'orders' => ''), api_app_bulk_choice($groups, 'none'), 'None');
	pg_assert_same(array('seo' => 'read', 'inventory' => '', 'orders' => 'read'), api_app_bulk_choice($groups, 'read'), 'All read: nothing on a write-only row');
	pg_assert_same(array('seo' => '', 'inventory' => 'write', 'orders' => 'write'), api_app_bulk_choice($groups, 'write'), 'All write: nothing on a read-only row');
	pg_assert_same(array('seo' => '', 'inventory' => '', 'orders' => ''), api_app_bulk_choice($groups, 'other'), 'an unknown button chooses nothing');
}

function test_api_app_bulk_money_count()
{
	$money = array('seo' => '', 'inventory' => 'write', 'orders' => 'read');

	pg_assert_same(2, api_app_bulk_money_count(array('seo' => 'read', 'inventory' => 'write', 'orders' => 'write'), $money));
	pg_assert_same(1, api_app_bulk_money_count(array('seo' => 'read', 'inventory' => '', 'orders' => 'read'), $money), 'a read that moves money counts');
	pg_assert_same(0, api_app_bulk_money_count(array('seo' => 'read', 'inventory' => '', 'orders' => ''), $money));

	pg_assert_same('write', api_app_permission_money(array('read' => 'orders:read', 'write' => 'orders:write'), array('orders:write')));
	pg_assert_same('read', api_app_permission_money(array('read' => 'gift_cards:read', 'write' => 'gift_cards:write'), array('gift_cards:read')));
	pg_assert_same('', api_app_permission_money(array('read' => 'seo:read', 'write' => ''), array('orders:write')));
}

// The script's bulkChoice() and moneyCount() answer as the PHP does.
function test_the_script_twin_matches_the_php()
{
	$out = array();
	$status = 1;

	if (!function_exists('exec')) {
		return;
	}

	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return;
	}

	$groups = api_app_permissions_test_shapes() + array('both_off' => array('read' => '', 'write' => ''));
	$money = array('seo' => '', 'inventory' => 'write', 'orders' => 'read', 'both_off' => '');
	$shape = array();

	foreach ($groups as $key => $group) {
		$shape[$key] = array('read' => $group['read'] !== '', 'write' => $group['write'] !== '');
	}

	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/api_app_access.js');
	$code = $script . "\nvar shape = " . json_encode($shape) . ";\nvar money = " . json_encode($money) . ";\nvar out = {};\n"
		. "['all', 'none', 'read', 'write', 'other'].forEach(function (w) { var c = globalThis.pgAppAccess.bulkChoice(shape, w); out[w] = {choices: c, money: globalThis.pgAppAccess.moneyCount(c, money)}; });\n"
		. "console.log(JSON.stringify(out));";

	$file = @tempnam(sys_get_temp_dir(), 'pgappaccess');

	if ($file === false) {
		return;
	}

	file_put_contents($file, $code);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, 'the script runs in Node: ' . implode("\n", array_slice($out, 0, 8)));

	$script_answers = json_decode(implode("\n", $out), true);

	foreach (array('all', 'none', 'read', 'write', 'other') as $which) {
		$choices = api_app_bulk_choice($groups, $which);
		pg_assert_same($choices, $script_answers[$which]['choices'], $which . ': the same choices');
		pg_assert_same(api_app_bulk_money_count($choices, $money), $script_answers[$which]['money'], $which . ': the same money count');
	}
}

// The picker: one section per area in use, each row with its icon, line and
// a control offering only the levels the row has; the posted names stay
// scope[group]; money rows carry the pill.
function test_the_picker_markup()
{
	$groups = api_app_permissions_test_shapes();
	$html = api_app_permission_picker_html($groups, 'scope', 'n_scope', array('orders:write'));

	pg_assert_same(2, substr_count($html, 'class="api-pick-area"'), 'store and site only');
	pg_assert_contains('data-area="store"', $html);
	pg_assert_contains('data-area="site"', $html);
	pg_assert_false(strpos($html, 'data-area="design"') !== false, 'an empty area is left out');

	pg_assert_contains('name="scope[orders]" id="n_scope_orders_write" value="write"', $html);
	pg_assert_contains('name="scope[orders]" id="n_scope_orders_read" value="read"', $html);
	pg_assert_false(strpos($html, 'n_scope_seo_write') !== false, 'a read-only row offers no write');
	pg_assert_false(strpos($html, 'n_scope_inventory_read') !== false, 'a write-only row offers no read');
	pg_assert_same(3, substr_count($html, ' value="" checked>'), 'every row starts at None');

	pg_assert_same(1, substr_count($html, 'data-money="write"'), 'one money row');
	pg_assert_contains('Orders <small class="pg-pill pg-tone-amber api-pick-money">Moves money</small>', $html, 'its pill');

	foreach (array('all', 'none', 'read', 'write') as $which) {
		pg_assert_contains('data-bulk="' . $which . '">', $html, $which . ': in the bar');
		pg_assert_contains('data-bulk="' . $which . '" data-area="store"', $html, $which . ': in the area menu');
	}

	pg_assert_contains('data-pick-search', $html);
	pg_assert_false(strpos($html, 'btn btn-') !== false, 'no old button');
	pg_assert_false(strpos($html, ' name="search') !== false, 'the search box is never posted');
}
