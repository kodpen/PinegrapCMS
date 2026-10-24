<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a Workspace channel's own icon (includes/workspace/channel_icons.php
 * and ws_channel_icon() in includes/workspace/customer.php): the curated list
 * holds only icons the served font draws, each once; a chosen icon wins over
 * the one the channel's kind gives, an unknown stored value is ignored, and
 * the mark of a private, guest or customer channel goes to the corner. Also
 * the channel's colour and icon in the external API: the fields of the
 * channel object and the body fields of create and update.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

// A channel row as ws_channels holds it, with what the case changes.
function ws_channel_icons_test_row($extra = array())
{
	return array_merge(array(
		'id'              => 7,
		'name'            => 'orders',
		'kind'            => 'public',
		'topic'           => '',
		'contact_id'      => 0,
		'customer_type'   => '',
		'customer_id'     => 0,
		'department_id'   => 0,
		'archived_at'     => 0,
		'last_message_at' => 0,
		'color'           => 0,
		'group_id'        => 0,
		'icon'            => '',
	), $extra);
}

// Every choice is a class the panel's Bootstrap Icons font draws, written
// the way the column keeps it, and listed once.
function test_ws_channel_icon_choices_are_font_classes_listed_once()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/fonts/bootstrap-icons/bootstrap-icons.css');
	$list = ws_channel_icon_list();
	$names = array();

	pg_assert_true(count($list) >= 70, 'about eighty icons to choose from');

	foreach ($list as $row) {
		$name = $row[0];

		pg_assert_same(1, preg_match('/^bi-[a-z0-9-]+$/', $name), $name . ' is a bi-* class name');
		pg_assert_true(strlen($name) <= 40, $name . ' fits ws_channels.icon');
		pg_assert_contains('.' . $name . '::before', $css, $name . ' is drawn by the font');
		pg_assert_true(trim($row[1]) !== '', $name . ' has Turkish words');
		pg_assert_true(trim($row[2]) !== '', $name . ' has English words');
		pg_assert_true(isset($row[3]) && trim((string) $row[3]) !== '', $name . ' has a name for the picker');

		$names[] = $name;
	}

	pg_assert_same(count($names), count(array_unique($names)), 'no icon is listed twice');
	pg_assert_same($names, array_keys(ws_channel_icon_choices()), 'the choices keep the order of the list');
}

// An icon that means something about the channel cannot be chosen: a lock
// on a public channel would say it is private.
function test_ws_channel_icon_choices_leave_out_the_kind_marks()
{
	$choices = ws_channel_icon_choices();

	foreach (array('bi-hash', 'bi-lock', 'bi-door-open', 'bi-person', 'bi-person-badge', 'bi-person-vcard') as $mark) {
		pg_assert_false(isset($choices[$mark]), $mark . ' is not offered');
	}
}

function test_ws_channel_icon_clean_and_check()
{
	pg_assert_same('bi-cart', ws_channel_icon_clean('bi-cart'));
	pg_assert_same('bi-cart', ws_channel_icon_clean(' BI-CART '), 'spaces and capitals');
	pg_assert_same('', ws_channel_icon_clean('bi-not-an-icon'), 'not in the list');
	pg_assert_same('', ws_channel_icon_clean('bi-lock'), 'a kind mark is not a choice');
	pg_assert_same('', ws_channel_icon_clean(array('bi-cart')), 'not a string');
	pg_assert_same('', ws_channel_icon_clean(null));

	$cleared = ws_channel_icon_check('');
	pg_assert_true($cleared['ok'], 'empty clears');
	pg_assert_same('', $cleared['icon']);

	$chosen = ws_channel_icon_check('bi-truck');
	pg_assert_true($chosen['ok']);
	pg_assert_same('bi-truck', $chosen['icon']);

	$refused = ws_channel_icon_check('bi-alarm-fill');
	pg_assert_false($refused['ok'], 'an icon outside the list is refused');
	pg_assert_true($refused['error'] !== '', 'with a reason');
}

// Chosen beats derived; an unknown stored value is as if nothing was chosen.
function test_ws_channel_icon_chosen_wins_over_the_kind()
{
	pg_assert_same('bi-hash', ws_channel_icon(ws_channel_icons_test_row()));
	pg_assert_same('bi-cart', ws_channel_icon(ws_channel_icons_test_row(array('icon' => 'bi-cart'))));
	pg_assert_same('bi-cart', ws_channel_icon(ws_channel_icons_test_row(array('icon' => 'bi-cart', 'kind' => 'private'))));
	pg_assert_same('bi-lock', ws_channel_icon(ws_channel_icons_test_row(array('kind' => 'private'))));
	pg_assert_same('bi-door-open', ws_channel_icon(ws_channel_icons_test_row(array('kind' => 'guest'))));
	pg_assert_same('bi-building', ws_channel_icon(ws_channel_icons_test_row(array('customer_type' => 'erp_account', 'customer_id' => 3))));
	pg_assert_same('bi-person-vcard', ws_channel_icon(ws_channel_icons_test_row(array('contact_id' => 5))));

	// A value the list no longer holds (or never held) is ignored.
	pg_assert_same('bi-hash', ws_channel_icon(ws_channel_icons_test_row(array('icon' => 'bi-not-an-icon'))));
	pg_assert_same('bi-lock', ws_channel_icon(ws_channel_icons_test_row(array('icon' => 'bi-not-an-icon', 'kind' => 'private'))));
	pg_assert_same('bi-hash', ws_channel_icon(ws_channel_icons_test_row(array('icon' => 'bi-lock'))), 'a lock written into a public channel');

	// A database before the column: no icon key at all.
	$row = ws_channel_icons_test_row();
	unset($row['icon']);
	pg_assert_same('bi-hash', ws_channel_icon($row));
	pg_assert_same('', ws_channel_icon_badge($row));
}

// The kind's mark moves to the corner only when a chosen icon took its place.
function test_ws_channel_icon_badge_keeps_the_kind()
{
	pg_assert_same('', ws_channel_icon_badge(ws_channel_icons_test_row(array('icon' => 'bi-cart'))), 'a public channel has no mark');
	pg_assert_same('bi-lock', ws_channel_icon_badge(ws_channel_icons_test_row(array('icon' => 'bi-cart', 'kind' => 'private'))));
	pg_assert_same('bi-door-open', ws_channel_icon_badge(ws_channel_icons_test_row(array('icon' => 'bi-cart', 'kind' => 'guest'))));
	pg_assert_same('bi-person', ws_channel_icon_badge(ws_channel_icons_test_row(array('icon' => 'bi-cart', 'customer_type' => 'user_account', 'customer_id' => 2))));
	pg_assert_same('bi-person', ws_channel_icon_badge(ws_channel_icons_test_row(array('icon' => 'bi-cart', 'contact_id' => 9))));
	pg_assert_same('', ws_channel_icon_badge(ws_channel_icons_test_row(array('kind' => 'private'))), 'the lock is the icon itself');
	pg_assert_same('', ws_channel_icon_badge(ws_channel_icons_test_row(array('kind' => 'private', 'icon' => 'bi-not-an-icon'))));
}

// The sidebar's brief carries the outcome; reading it again gives the same
// answer, also for a derived icon that happens to be one of the choices.
function test_ws_channel_icon_brief_reads_back_the_same()
{
	$derived = ws_channel_brief(ws_channel_icons_test_row(array('customer_type' => 'erp_account', 'customer_id' => 3)));
	pg_assert_same('bi-building', $derived['icon']);
	pg_assert_false($derived['icon_chosen']);
	pg_assert_same('', $derived['icon_badge']);
	pg_assert_same('bi-building', ws_channel_icon($derived));
	pg_assert_same('', ws_channel_icon_badge($derived), 'the derived building is not taken for a chosen one');

	$chosen = ws_channel_brief(ws_channel_icons_test_row(array('icon' => 'bi-building', 'kind' => 'private')));
	pg_assert_same('bi-building', $chosen['icon']);
	pg_assert_true($chosen['icon_chosen']);
	pg_assert_same('bi-lock', $chosen['icon_badge']);
	pg_assert_same('bi-building', ws_channel_icon($chosen));
	pg_assert_same('bi-lock', ws_channel_icon_badge($chosen));
}

// The picker's list: every icon with a label and the words of both languages.
function test_ws_channel_icon_choices_js_carry_words()
{
	$js = ws_channel_icon_choices_js();

	pg_assert_same(count(ws_channel_icon_list()), count($js));

	foreach ($js as $item) {
		pg_assert_true(isset(ws_channel_icon_choices()[$item['icon']]), $item['icon'] . ' is a choice');
		pg_assert_true($item['label'] !== '', $item['icon'] . ' has a label');
		pg_assert_true(strpos($item['label'], ',') === false, $item['icon'] . ': the label is one phrase');
	}

	$cart = null;

	foreach ($js as $item) {
		if ($item['icon'] === 'bi-cart') {
			$cart = $item;
		}
	}

	pg_assert_contains('sepet', mb_strtolower($cart['words']), 'Turkish words');
	pg_assert_contains('cart', mb_strtolower($cart['words']), 'English words');
	pg_assert_contains('kargo', mb_strtolower($cart['words'] . ' ' . $js[array_search('bi-truck', array_column($js, 'icon'), true)]['words']), 'every icon is found by its words');
}

// The channel object of the API: colour as a palette code or null, the icon
// drawn and whether it was chosen; every field declared.
function test_ws_channel_icon_api_object()
{
	$schema = ws_api_channel_schema();
	pg_assert_same('string?', $schema['color'] ?? null);
	pg_assert_same('string', $schema['icon'] ?? null);
	pg_assert_same('boolean', $schema['icon_chosen'] ?? null);

	$plain = ws_api_channel_present(ws_channel_brief(ws_channel_icons_test_row(array('kind' => 'private'))));
	pg_assert_same(null, $plain['color']);
	pg_assert_same('bi-lock', $plain['icon']);
	pg_assert_false($plain['icon_chosen']);

	$own = ws_api_channel_present(ws_channel_brief(ws_channel_icons_test_row(array('color' => 7, 'icon' => 'bi-truck'))));
	pg_assert_same(ws_palette_hex(7), $own['color']);
	pg_assert_same('bi-truck', $own['icon']);
	pg_assert_true($own['icon_chosen']);

	pg_assert_same(array(), array_diff(array_keys($own), array_keys($schema)), 'every field is declared');
}

// Create and update take color (a palette code) and icon (a choice); both
// clear with null or an empty value.
function test_ws_channel_icon_api_routes_take_color_and_icon()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	foreach (array('workspace.channels.create', 'workspace.channels.update') as $id) {
		$params = array();

		foreach ($routes[$id]['params'] as $param) {
			$params[$param['name']] = $param;
		}

		pg_assert_true(isset($params['color']), $id . ' takes color');
		pg_assert_same('enum', $params['color']['type'] ?? null, $id . ' color');
		pg_assert_same(ws_palette_hexes(), $params['color']['values'] ?? null, $id . ' color values');
		pg_assert_true(!empty($params['color']['nullable']), $id . ' color clears');

		pg_assert_true(isset($params['icon']), $id . ' takes icon');
		pg_assert_same('enum', $params['icon']['type'] ?? null, $id . ' icon');
		pg_assert_same(array_keys(ws_channel_icon_choices()), $params['icon']['values'] ?? null, $id . ' icon values');
		pg_assert_true(!empty($params['icon']['nullable']), $id . ' icon clears');
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' can be rehearsed');
	}
}

// The picker's names go through lang(), one literal English key per icon that
// tr.json translates; the list is no longer read in one language or the other.
function test_ws_channel_icon_names_are_translated()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/channel_icons.php');
	$translations = json_decode((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/local/tr.json'), true);

	pg_assert_same(count(ws_channel_icon_list()), preg_match_all("/array\\('bi-[a-z0-9-]+', '[^']*', '[^']*', lang\\('([^']+)'\\)\\),/", $source, $found));

	foreach ($found[1] as $english) {
		pg_assert_true(isset($translations[$english]), $english . ' is in tr.json');
	}

	pg_assert_true(strpos($source, "lang(array('info' => true))") === false, 'no language test in the picker list');
}
