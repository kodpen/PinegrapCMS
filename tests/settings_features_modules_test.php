<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Features, the Modules board: the rows' order and names,
 * each switch posted under its old name with the save unchanged, Commerce
 * and Accounting as a state with Open, the old card ids opening the board,
 * and no Content approval row for a Manager.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/features_modules.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');

function settings_features_modules_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// Every row on, every module this install may have.
function settings_features_modules_all()
{
	$rows = array();

	foreach (pg_features_module_order() as $key) {
		$rows[$key] = array('on' => true);
	}

	$rows['commerce']['href'] = 'settings_commerce.php';
	$rows['sharing']['settings'] = 'pg-fold-sharing';
	$rows['courses']['extra'] = '<input type="hidden" name="courses_card" value="1"/>';

	return $rows;
}

// The rows of a board, one string each.
function settings_features_modules_rows($html)
{
	preg_match_all('~<div class="pg-set-switch-row[^"]*">.*?</div>(?=<div class="pg-set-switch-row|</div></div>$)~s', $html, $match);

	return $match[0];
}

function test_settings_features_modules_order_and_names()
{
	pg_assert_same(
		array('commerce', 'accounting', 'forms', 'calendars', 'workspace', 'courses', 'tickets', 'ads', 'auto_dialogs', 'sharing', 'site_push', 'maps', 'page_clicks', 'content_approval', 'mobile'),
		pg_features_module_order(),
		'the rows in the description\'s order');

	$names = array();

	foreach (pg_features_module_order() as $key) {
		$face = pg_features_module_face($key);
		$names[] = $face['name'];
		pg_assert_true(stripos($face['name'], 'Enable') !== 0, $key . ': a name never starts with Enable');
		pg_assert_true($face['on'] !== '', $key . ': a line while on');
	}

	pg_assert_same(
		array('Commerce', 'Accounting', 'Forms', 'Calendars', 'Workspace', 'Courses', 'Tickets', 'Ads', 'Auto dialogs', 'Sharing buttons', 'Visitor notifications', 'Maps', 'Clicks and scrolling', 'Content approval', 'Mobile view'),
		$names);

	$mobile = pg_features_module_face('mobile');
	pg_assert_contains('page cache is off', $mobile['on'], 'Mobile view says it turns the page cache off');
}

// Each switch keeps the name and id of the card it came from, and the save
// still reads those names.
function test_settings_features_modules_switches_post_under_their_old_names()
{
	$fields = array(
		'forms' => 'forms', 'calendars' => 'calendars', 'workspace' => 'workspace_enabled', 'courses' => 'courses_on',
		'tickets' => 'event_tickets_on', 'ads' => 'ads', 'auto_dialogs' => 'auto_dialogs', 'sharing' => 'social_networking',
		'site_push' => 'site_push_on', 'maps' => 'place_map_on', 'page_clicks' => 'page_clicks_on',
		'content_approval' => 'content_approval_on', 'mobile' => 'mobile',
	);

	$board = pg_features_modules_board(settings_features_modules_all());

	foreach ($fields as $key => $field) {
		$face = pg_features_module_face($key);
		pg_assert_same($field, $face['field'], $key);
		pg_assert_contains('<input type="checkbox" class="pg-switch" value="1" id="' . $field . '" name="' . $field . '"', $board, $key . ': a pg-switch under its old name and id');
		pg_assert_contains('<label class="pg-set-fold-name" for="' . $field . '">', $board, $key . ': its name is the switch\'s label');
	}

	pg_assert_contains('<input type="hidden" name="courses_card" value="1"/>', $board, 'a card with nothing but its switch posts its marker from the row');

	$save = settings_features_modules_source('includes/settings/features.save.php');

	foreach (array("post_value('forms')", "post_value('calendars')", "post_value('ads')", "post_value('auto_dialogs')", "post_value('social_networking')", "post_value('mobile')", "post_value('workspace_enabled')",
		"pg_course_switch_save((bool) post_value('courses_on'));", "pg_event_tickets_switch_save((bool) post_value('event_tickets_on'));",
		"pg_site_push_switch_save((bool) post_value('site_push_on'));", "pg_place_tiles_switch_save((bool) post_value('place_map_on'));",
		"pg_page_clicks_switch_save((bool) post_value('page_clicks_on'));", "(bool) post_value('content_approval_on')",
		"array_key_exists('courses_card', \$_POST)", "array_key_exists('event_tickets_card', \$_POST)") as $read) {
		pg_assert_contains($read, $save, 'the save reads ' . $read);
	}

	$card = settings_features_modules_source('includes/settings/features.php');
	pg_assert_contains("'extra' => '<input type=\"hidden\" name=\"courses_card\" value=\"1\"/>'", $card);
	pg_assert_contains("'extra' => '<input type=\"hidden\" name=\"event_tickets_card\" value=\"1\"/>'", $card);
}

function test_settings_features_modules_rows()
{
	$board = pg_features_modules_board(settings_features_modules_all());
	$rows = settings_features_modules_rows($board);

	pg_assert_same(15, count($rows), 'a row a module');
	pg_assert_contains('9 / 15 on', pg_features_modules_board(array_slice(settings_features_modules_all(), 0, 9, true) + array_fill_keys(array_slice(pg_features_module_order(), 9), array('on' => false))), 'the count of the rows that are on');
	pg_assert_false(strpos($board, 'class="switch"') !== false, 'no old switch drawing');

	foreach ($rows as $i => $row) {
		pg_assert_true(strpos($row, '<div class="pg-set-switch-row') === 0, $i . ': a row is a div');
		$switches = substr_count($row, 'class="pg-switch"');
		$open = substr_count($row, 'pg-pill');

		pg_assert_same(1, $switches + $open, $i . ': one switch or one state pill');

		if ($switches === 1) {
			pg_assert_same(1, substr_count($row, '<label '), $i . ': the switch has its label');
			pg_assert_contains('aria-describedby="pg-module-', $row, $i . ': the line describes the switch');
		}
	}

	$commerce = $rows[0];
	pg_assert_contains('<span class="pg-pill pg-tone-teal" data-pg-module-on>On</span>', $commerce, 'Commerce shows its state');
	pg_assert_contains('href="settings_commerce.php"', $commerce, 'and opens its category');

	$accounting = pg_features_module_row(array('key' => 'accounting', 'on' => false, 'href' => ''));
	pg_assert_contains('<span class="pg-pill pg-tone-gray">Off</span>', $accounting, 'Accounting off');
	pg_assert_false(strpos($accounting, '<a ') !== false, 'no Open for a reader who may not open the category');

	$sharing = pg_features_module_row(array('key' => 'sharing', 'on' => false, 'settings' => 'pg-fold-sharing'));
	pg_assert_contains('data-pg-set-goto="pg-fold-sharing"', $sharing, 'Settings leads to the setup');
	pg_assert_contains('>Off: pages show no share buttons; their setup is kept.</span>', $sharing, 'off, the line says what is hidden');
	pg_assert_false(strpos($sharing, 'checked') !== false, 'off, the switch is off');

	$forms = pg_features_module_row(array('key' => 'forms', 'on' => true));
	pg_assert_false(strpos($forms, 'data-pg-set-goto') !== false, 'no Settings on a row whose settings are not below');
	pg_assert_contains('Switching it off deletes nothing.', $forms, 'on, the line says switching off deletes nothing');
}

function test_settings_features_modules_old_card_ids_open_the_board()
{
	$anchors = pg_settings_legacy_anchors();

	foreach (array('pgset-features', 'pgset-courses', 'pgset-event-tickets') as $old) {
		pg_assert_same(array('features', 'pgset-features'), $anchors[$old], $old . ' opens the board');
	}

	$card = settings_features_modules_source('includes/settings/features.php');
	pg_assert_contains('<div id="pgset-features" class="pg-set-card">', $card, 'the board takes the pgset-features id');
	pg_assert_false(strpos($card, 'id="pgset-courses"') !== false || strpos($card, 'id="pgset-event-tickets"') !== false, 'the Courses and Tickets cards are gone');

	foreach (array('pgset-site-push' => 'site-push', 'pgset-place-map' => 'maps', 'pgset-page-clicks' => 'page-clicks', 'pgset-content-approval' => 'content-approval', 'pgset-sharing' => 'sharing') as $id => $fold) {
		pg_assert_contains('id="' . $id . '"', $card, $id . ': the card that keeps the module\'s settings stays');
		pg_assert_contains("'settings' => 'pg-fold-" . $fold . "'", $card, $id . ': the row\'s Settings leads to the folded row holding it');
	}

	pg_assert_false(strpos($card, 'id="social_networking_type_row"') !== false, 'the switch opens no popover');

	$js = settings_features_modules_source('assets/js/backend.src.js');
	pg_assert_contains("var card = byId(section) || ((moved && (moved[0] === pane) && moved[1]) ? byId(moved[1]) : null);", $js, 'an old card id inside the open pane lands where its card went');

	$registry = settings_features_modules_source('includes/settings/registry.php');
	pg_assert_contains("'pgset-features' => lang('Modules'),", $registry, 'the board\'s section');
	pg_assert_contains("'pgset-features' => pg_settings_keywords(lang('module, modules, feature, commerce, accounting, courses, lessons, tickets,", $registry, 'the settings search finds Courses and Tickets');
}

function test_settings_features_modules_manager_gets_no_content_approval_row()
{
	$card = settings_features_modules_source('includes/settings/features.php');
	pg_assert_contains('$features_content_approval_shown = pg_content_proposals_ready() && pg_content_proposals_may_set($user);', $card);
	pg_assert_true((bool) preg_match("/if \\(\\\$features_content_approval_shown\\) \\{\\s+\\\$content_approval = pg_content_proposals_settings\\(\\);\\s+\\\$features_modules\\['content_approval'\\]/", $card), 'the row is added only under that gate');
	pg_assert_false(pg_content_proposals_may_set(array('role' => 2)), 'a Manager may not set it');

	$rows = settings_features_modules_all();
	unset($rows['content_approval']);
	pg_assert_false(strpos(pg_features_modules_board($rows), 'content_approval_on') !== false, 'a board without the row draws no switch for it');
}
