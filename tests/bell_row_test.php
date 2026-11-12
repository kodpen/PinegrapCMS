<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A bell row drawn as the dashboard's Next up row: its module (from the
 * action), its day heading (today, yesterday, earlier in the site's time
 * zone) and its one button (from the action and the Workspace kind), the tag
 * hues equal to the dashboard's, and the script that draws the row. The pure
 * parts are run; the script and the stylesheets are pinned by source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The module file holds functions only; loading it runs nothing.
require_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

function bell_row_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// One function of a file, up to the next top-level one.
function bell_row_function($file, $name)
{
	$source = bell_row_source($file);
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n}\n", $start);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start + 3);
}

// The actions pg_notification_display() draws, read from its source, so a new
// one cannot be added without a module.
function bell_row_known_actions()
{
	preg_match_all("/\\\$action == '([a-z_]+)'/", bell_row_function('includes/notifications.php', 'pg_notification_display'), $found);

	return array_values(array_unique($found[1]));
}

function test_bell_row_module_of_every_known_action()
{
	$expected = array(
		'new_order'        => 'shop',
		'order_return'     => 'shop',
		'out_stock'        => 'shop',
		'booking_refused'  => 'shop',
		'erp_overdue'      => 'erp',
		'erp_money'        => 'erp',
		'erp_low_stock'    => 'erp',
		'erp_quote_expiry' => 'erp',
		'erp_auto_invoice' => 'erp',
		'form_submited'    => 'content',
		'new_comment'      => 'content',
		'workspace'        => 'team',
		'software_update'  => 'system',
		'mail_failed'      => 'system',
		'ops'              => 'system',
	);
	$known = bell_row_known_actions();

	pg_assert_true(count($known) >= 15, 'the actions are read from pg_notification_display()');

	foreach ($known as $action) {
		pg_assert_true(isset($expected[$action]), 'a module is written down for ' . $action);
	}

	foreach ($expected as $action => $module) {
		pg_assert_same($module, pg_notification_module($action), $action);
	}

	pg_assert_same('system', pg_notification_module('custom'), 'a custom row');
	pg_assert_same('system', pg_notification_module(''), 'no action');
	pg_assert_same('erp', pg_notification_module('erp_something_new'), 'every ERP notice is Accounting');
	pg_assert_same('system', pg_notification_module(null), 'null');
}

function test_bell_row_day_in_the_site_time_zone()
{
	// 2026-11-11 10:00 in Istanbul (UTC+3) is 07:00 UTC.
	$now = gmmktime(7, 0, 0, 11, 11, 2026);

	pg_assert_same('today', pg_notification_day(gmmktime(21, 30, 0, 11, 10, 2026), $now, 'Europe/Istanbul'), '00:30 local today is still UTC yesterday');
	pg_assert_same('yesterday', pg_notification_day(gmmktime(20, 59, 0, 11, 10, 2026), $now, 'Europe/Istanbul'), '23:59 local yesterday');
	pg_assert_same('yesterday', pg_notification_day(gmmktime(21, 0, 0, 11, 9, 2026), $now, 'Europe/Istanbul'), 'yesterday from its first minute');
	pg_assert_same('earlier', pg_notification_day(gmmktime(20, 59, 0, 11, 9, 2026), $now, 'Europe/Istanbul'), 'the day before yesterday');
	pg_assert_same('today', pg_notification_day($now + 3600, $now, 'Europe/Istanbul'), 'a time after now counts as today');
	pg_assert_same('yesterday', pg_notification_day(gmmktime(21, 30, 0, 11, 10, 2026), $now, 'UTC'), 'the same time is yesterday in UTC');

	// New York leaves summer time on 2026-11-01: the day before the change
	// is still yesterday on the morning after it.
	$after = gmmktime(14, 0, 0, 11, 1, 2026);

	pg_assert_same('yesterday', pg_notification_day(gmmktime(15, 0, 0, 10, 31, 2026), $after, 'America/New_York'), 'across the clock change');
	pg_assert_same('earlier', pg_notification_day(gmmktime(15, 0, 0, 10, 30, 2026), $after, 'America/New_York'), 'two days back across it');
	pg_assert_same('today', pg_notification_day(gmmktime(5, 0, 0, 11, 1, 2026), $after, 'America/New_York'), '01:00 local of the change day');

	pg_assert_same('today', pg_notification_day($now - 60, $now, 'No/Such_Zone'), 'an unknown zone falls back, still a day');
}

function test_bell_row_button_names_the_job()
{
	pg_assert_same('reply', pg_notification_button('new_comment'), 'a comment is answered');
	pg_assert_same('show', pg_notification_button('new_order'), 'an order is shown');
	pg_assert_same('show', pg_notification_button('erp_overdue'), 'an ERP notice is shown');
	pg_assert_same('', pg_notification_button('custom'), 'a custom row has no page');
	pg_assert_same('', pg_notification_button(''), 'nor has a row without action');

	pg_assert_same('join', pg_notification_button('workspace', 'meeting_soon', 'https://meet.example.com/x'), 'a meeting with its call');
	pg_assert_same('show', pg_notification_button('workspace', 'meeting_soon', ''), 'a meeting without its call');

	foreach (array('approval_requested', 'approval_reminder') as $kind) {
		pg_assert_same('approve', pg_notification_button('workspace', $kind), $kind);
	}

	foreach (array('mention', 'thread', 'guest_message', 'question_asked', 'mail_assigned', 'mail_customer') as $kind) {
		pg_assert_same('reply', pg_notification_button('workspace', $kind), $kind);
	}

	foreach (array('assigned', 'completed', 'flow_finished', 'approval_decided', 'note_shared', 'task_overdue', '') as $kind) {
		pg_assert_same('show', pg_notification_button('workspace', $kind), 'kind ' . $kind);
	}
}

function test_bell_row_fold_runs_stand_under_their_head_day()
{
	$items = pg_notification_fold(array(
		array('id' => 9, 'fold' => 'flow:12:ended', 'day' => 'today'),
		array('id' => 8, 'fold' => '', 'day' => 'yesterday'),
		array('id' => 7, 'fold' => 'flow:12:ended', 'day' => 'earlier'),
		array('id' => 6, 'fold' => '', 'day' => 'earlier'),
	));

	pg_assert_same(array(9, 7, 8, 6), array_column($items, 'id'), 'the run follows its head');
	pg_assert_same(array('today', 'today', 'yesterday', 'earlier'), array_column($items, 'day'), 'and takes its head\'s day');
}

function test_bell_row_endpoint_gives_module_day_and_button()
{
	$list = bell_row_function('includes/panel/notifications.php', 'pg_panel_get_notifications');

	pg_assert_contains("'module' => pg_notification_module(", $list, 'module');
	pg_assert_contains("'day' => pg_notification_day((int) \$notification['timestamp'], \$now)", $list, 'day, in the site zone the panel set');
	pg_assert_contains("'button' => pg_notification_button(\$display['action'], \$display['kind'], \$display['join'])", $list, 'button');
}

// The bell's tag hues are the dashboard's Next up tags, light and dark.
function test_bell_row_tag_hues_equal_the_dashboard()
{
	$dash = bell_row_source('assets/css/pg_dash.src.css');
	$bell = bell_row_source('assets/css/backend.src.css');
	$light = substr($bell, strpos($bell, '--pg-bell-tag-shop-bg'));
	$dark_at = strpos($bell, '/* The bell\'s module tags: the dashboard\'s dark ink');
	$dark = ($dark_at === false) ? '' : substr($bell, $dark_at, 400);
	$norm = function ($value) {
		return strtolower(str_replace(array(' ', '0.'), array('', '.'), trim($value)));
	};

	pg_assert_true($dark !== '', 'the dark block names the bell tags');

	foreach (array('shop', 'erp', 'content', 'team') as $module) {
		pg_assert_true(preg_match('/\.pg-dash-queue-tag\[data-module="' . $module . '"\]\s*\{\s*--pg-dash-tag-bg:\s*([^;]+);\s*--pg-dash-tag-fg:\s*([^;]+);/', $dash, $want) === 1, 'the dashboard has ' . $module);
		pg_assert_true(preg_match('/html\[data-bs-theme="dark"\] \.pg-dash-queue-tag\[data-module="' . $module . '"\]\s*\{\s*--pg-dash-tag-fg:\s*([^;]+);/', $dash, $want_dark) === 1, 'and its dark ink');
		pg_assert_true(preg_match('/--pg-bell-tag-' . $module . '-bg:\s*([^;]+);/', $light, $bg) === 1, 'the bell has ' . $module);
		pg_assert_true(preg_match('/--pg-bell-tag-' . $module . '-fg:\s*([^;]+);/', $light, $fg) === 1, 'and its ink');
		pg_assert_true(preg_match('/--pg-bell-tag-' . $module . '-fg:\s*([^;]+);/', $dark, $fg_dark) === 1, 'and its dark ink');

		pg_assert_same($norm($want[1]), $norm($bg[1]), $module . ' fill');
		pg_assert_same($norm($want[2]), $norm($fg[1]), $module . ' ink');
		pg_assert_same($norm($want_dark[1]), $norm($fg_dark[1]), $module . ' dark ink');
		pg_assert_contains('#notifications .pg-bell-tag[data-module="' . $module . '"]', $bell, 'the tag reads the ' . $module . ' tokens');
	}

	pg_assert_true(preg_match('/\.pg-dash-queue-tag\s*\{\s*--pg-dash-tag-bg:\s*([^;]+);/', $dash, $neutral) === 1, 'the dashboard\'s neutral tag');
	pg_assert_true(preg_match('/--pg-bell-tag-system-bg:\s*([^;]+);/', $light, $system) === 1, 'the bell\'s System tag');
	pg_assert_same($norm($neutral[1]), $norm($system[1]), 'System takes the neutral fill');
}

function test_bell_row_script_draws_the_row()
{
	$row = bell_row_function('assets/js/backend.src.js', 'pg_bell_row');
	$get = bell_row_function('assets/js/backend.src.js', 'get_notifications');
	$words = bell_row_function('assets/js/backend.src.js', 'pg_bell_words');
	$dot = bell_row_function('assets/js/backend.src.js', 'pg_bell_read_button');

	pg_assert_contains('class="pg-bell-tag" data-module="', $row, 'the tag');
	pg_assert_contains('pg-bell-title', $row, 'the title');
	pg_assert_contains('pg-bell-sub', $row, 'one second line');
	pg_assert_contains("((excerpt !== '') ? '<p class=\"pg-bell-excerpt\">'", $row, 'an empty excerpt leaves no gap under the row');

	// The details (an order's number and total) wrap in the narrow bell
	// instead of ending in an ellipsis, as the title does.
	$css = bell_row_source('assets/css/backend.src.css');
	$at = strpos($css, "\n#notifications .pg-bell-sub {");
	$sub = ($at === false) ? '' : substr($css, $at, strpos($css, '}', $at) - $at);
	pg_assert_contains('overflow-wrap: anywhere;', $sub, 'the second line wraps');
	pg_assert_same(false, strpos($sub, 'ellipsis'), 'and never ends in an ellipsis');
	$at = strpos($css, "\n#notifications .pg-bell-excerpt {");
	$excerpt = ($at === false) ? '' : substr($css, $at, strpos($css, '}', $at) - $at);
	pg_assert_contains('-webkit-line-clamp: 2;', $excerpt, 'the excerpt shows two lines at most');
	pg_assert_contains('overflow-wrap: anywhere;', $excerpt, 'and wraps a long word');
	pg_assert_contains('pg-bell-act', $row, 'the one button');
	pg_assert_contains('pg_bell_read_button(id, unread)', $row, 'the dot');
	pg_assert_contains("lang('Mark as read')", $dot, 'the dot is named');
	pg_assert_contains('<button type="button" class="pg-bell-dot', $dot, 'and is a button');
	pg_assert_contains("aria-label=\"' + h(lang('More actions'))", $row, 'the menu button is named');
	pg_assert_contains("lang('Mark as unread')", $row, 'the menu: Mark as unread');
	pg_assert_contains("lang('Delete')", $row, 'the menu: Delete');
	pg_assert_false(strpos($row, 'Go to the relevant page') !== false, 'the full-width link line is gone');
	pg_assert_false(strpos($row, 'menu-item') !== false && strpos($row, 'pg-bell-menu-item') === false, 'no full-width menu items');

	foreach (array('Shop', 'Accounting', 'Content', 'Team', 'System', 'Reply', 'Show', 'Join', 'Approve', 'Today', 'Yesterday', 'Earlier') as $word) {
		pg_assert_contains("lang('" . $word . "')", $words, $word);
	}

	pg_assert_contains('class="pg-bell-day"', $get, 'day headings');
	pg_assert_contains('fold_head', $get, 'a fold\'s runs open no heading of their own');
	pg_assert_contains('pg_bell_row(response.data[i])', $get, 'each row');
}

function test_bell_row_menu_keeps_its_keys()
{
	$js = bell_row_source('assets/js/backend.src.js');

	$key = bell_row_function('assets/js/backend.src.js', 'pg_bell_menu_key');

	// Bootstrap's menu keys run on the document's capture phase: a listener
	// on the bell itself never sees Escape, which would close the whole bell.
	pg_assert_contains("window.addEventListener('keydown', pg_bell_menu_key, true);", $js, 'taken on the window\'s capture phase, before Bootstrap\'s');
	pg_assert_false(strpos($js, "\$('#notifications').on('keydown', '.pg-bell-menu-wrap'") !== false, 'not on the bell, where Bootstrap gets the keys first');
	pg_assert_contains("closest('#notifications .pg-bell-menu-wrap')", $key, 'only inside a row menu');
	pg_assert_contains('event.stopPropagation();', $key, 'Escape and the arrows stop there');
	pg_assert_contains('pg_bell_menu_close($wrap, true);', $key, 'Escape closes the row menu and returns to its button');
	pg_assert_contains("edit_notification(parseInt(\$(this).attr('data-pg-bell-delete'), 10) || 0, 'remove')", $js, 'Delete');
	pg_assert_contains("edit_notification(parseInt(\$(this).attr('data-pg-bell-unread'), 10) || 0, 'mark_unread')", $js, 'Mark as unread');
	pg_assert_contains("\$(document).on('click', '#notifications button.pg-bell-read'", $js, 'the dot reads the row');
}

function test_bell_row_words_reach_the_script()
{
	$output = bell_row_source('includes/fn/output.php');

	foreach (array('Shop', 'Accounting', 'Content', 'Team', 'System', 'Reply', 'Show', 'Join', 'Approve', 'Today', 'Yesterday', 'Earlier', 'Delete', 'More actions', 'Mark as read', 'Mark as unread') as $word) {
		pg_assert_contains("'" . $word . "' => lang('" . $word . "')", $output, $word . ' is in the run-time dictionary');
	}
}
