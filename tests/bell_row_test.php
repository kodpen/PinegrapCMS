<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A bell row in look A: its module (from the action), its day heading
 * (today, yesterday, earlier in the site's time zone), its one button (from
 * the action and the Workspace kind), its short title, the tile's tone, and
 * the script that draws the row on the recipes. The pure
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

// A site row's short title says what happened and with what; the device
// banner keeps the sentence, and the bell's text does not repeat the title.
function test_bell_row_short_titles_say_what_happened()
{
	$order = pg_notification_display(array('action' => 'new_order', 'title' => 'SP-1044', 'order_total' => '2.015,50 ₺', 'order_id' => '9'));
	pg_assert_same('New order #SP-1044', $order['short']);
	pg_assert_same('<b>2.015,50 ₺</b>', $order['brief'], 'the total is the text');
	pg_assert_contains('Congratulations', $order['title'], 'the banner keeps its sentence');

	$escaped = pg_notification_display(array('action' => 'new_order', 'title' => '<i>x</i>', 'order_total' => '<b>1</b>', 'order_id' => '9'));
	pg_assert_contains('&lt;i&gt;x&lt;/i&gt;', $escaped['short'], 'the number is escaped');
	pg_assert_same('<b>&lt;b&gt;1&lt;/b&gt;</b>', $escaped['brief'], 'and the total');

	$return = pg_notification_display(array('action' => 'order_return', 'title' => '1044', 'order_total' => 'refund_overdue', 'order_id' => '9'));
	pg_assert_same('Refund overdue #1044', $return['short']);
	pg_assert_same($return['title'], $return['brief'], 'the sentence is the text');

	pg_assert_same('Out of stock', pg_notification_display(array('action' => 'out_stock', 'title' => 'Mug', 'product_id' => '3'))['short']);

	$form = pg_notification_display(array('action' => 'form_submited', 'title' => 'AB12', 'form_id' => '4'));
	pg_assert_same('Form submitted', $form['short']);
	pg_assert_same('Reference Code <b>AB12</b>', $form['brief']);

	pg_assert_same('Order #1172 not invoiced', pg_notification_display(array('action' => 'erp_auto_invoice', 'title' => 'x', 'order_id' => '17', 'order_total' => '1172'))['short']);

	// No short title is a label with a colon or a greeting.
	foreach (array('new_order', 'order_return', 'out_stock', 'form_submited', 'erp_auto_invoice') as $action) {
		$display = pg_notification_display(array('action' => $action, 'title' => '1', 'order_total' => '1', 'order_id' => '1', 'form_id' => '1', 'product_id' => '1'));
		pg_assert_false(strpos($display['short'], ':') !== false, $action . ' has no colon');
	}

	$list = bell_row_function('includes/panel/notifications.php', 'pg_panel_get_notifications');
	pg_assert_contains("'details' => ((\$display['brief'] !== '') ? \$display['brief'] : \$display['details'])", $list, 'the bell takes the brief text');
}

// The tile names the module by its tone, as the look's list does; the old
// tag and its tokens are gone.
function test_bell_row_tile_tone_names_the_module()
{
	$tile = bell_row_function('assets/js/backend.src.js', 'pg_bell_tile');
	$css = bell_row_source('assets/css/backend.src.css');

	pg_assert_contains("{ shop: 'teal', erp: 'amber', content: 'rose', team: 'blue', system: 'gray' }", $tile, 'the look\'s module tones');
	pg_assert_contains('class="pg-icon-tile pg-tone-', $tile, 'the recipe\'s tile');
	pg_assert_contains("tone = 'amber';", $tile, 'a warning is amber');
	pg_assert_contains("tone = 'rose';", $tile, 'an error rose');
	pg_assert_false(strpos($css, '--pg-bell-tag-') !== false, 'the tag tokens are gone');
	pg_assert_false(strpos($css, '.pg-bell-tag') !== false, 'and the tag');
}

function test_bell_row_script_draws_the_row()
{
	$row = bell_row_function('assets/js/backend.src.js', 'pg_bell_row');
	$quick = bell_row_function('assets/js/backend.src.js', 'pg_bell_quick');
	$run = bell_row_function('assets/js/backend.src.js', 'pg_bell_run');
	$get = bell_row_function('assets/js/backend.src.js', 'get_notifications');
	$words = bell_row_function('assets/js/backend.src.js', 'pg_bell_words');
	$dot = bell_row_function('assets/js/backend.src.js', 'pg_bell_read_button');
	$fold = bell_row_function('assets/js/backend.src.js', 'pg_bell_fold_button');

	// The recipes' row draws it.
	pg_assert_contains('pg-list-row', $row, 'the recipe row');
	pg_assert_contains('pg_bell_tile(row, module)', $row, 'the tile');
	pg_assert_contains('pg-list-row-title pg-bell-title', $row, 'the title');
	pg_assert_contains('pg-list-row-time pg-bell-time', $row, 'the time at its right');
	pg_assert_contains("((text !== '') ? '<p class=\"pg-list-row-text pg-bell-excerpt\">'", $row, 'an empty text leaves no gap under the row');
	pg_assert_contains('pg-unread-dot pg-bell-dot', $row, 'the unread dot at the right');
	pg_assert_contains('pg_bell_quick(id, unread, title_id)', $row, 'the quick actions');

	// A button only when the row asks; one filled button in the view.
	pg_assert_contains("(job === 'reply') || (job === 'approve') || (job === 'join')", $row, 'an answer, an approval, a call');
	pg_assert_contains("((job === 'show') && unread && ((row.type === 'warning') || (row.type === 'error')))", $row, 'Show only for an unread warning');
	pg_assert_contains('draw.fill = false;', $row, 'one filled button');
	pg_assert_contains('pg-button pg-button-sm pg-button-quiet', $row, 'the others quiet');
	pg_assert_contains("var draw = { fill: true };", $get, 'one per drawing');

	// A fold: the count beside the title, the runs as gathered lines.
	pg_assert_contains('<span class="pg-count">\' + fold_count', $row, 'the count beside the title');
	pg_assert_contains('return pg_bell_run(', $row, 'a run is drawn as a line');
	pg_assert_contains('pg-gather-line', $run, 'the recipe\'s gathered line');
	pg_assert_contains("pg-bell-shut", $run, 'shut while its head is closed');
	pg_assert_contains("lang('Hide')", $fold, 'Hide');
	pg_assert_contains("lang('Show {var:1} more')", $fold, 'Show N more');
	pg_assert_contains('pg-gather-toggle', $fold, 'the recipe\'s toggle');

	$css = bell_row_source('assets/css/backend.src.css');
	$at = strpos($css, "\n#notifications .pg-bell-excerpt {");
	$excerpt = ($at === false) ? '' : substr($css, $at, strpos($css, '}', $at) - $at);
	pg_assert_contains('overflow-wrap: anywhere;', $excerpt, 'the text wraps a long word');
	pg_assert_contains('@media (hover: none)', $css, 'a phone keeps the quick actions in sight');

	pg_assert_contains("lang('Mark as read')", $dot, 'Mark as read is named');
	pg_assert_contains('<button type="button" class="pg-icon-button pg-icon-button-sm pg-bell-read', $dot, 'and is a button');
	pg_assert_contains("aria-label=\"' + h(lang('More actions'))", $quick, 'the menu button is named');
	pg_assert_contains("lang('Mark as unread')", $quick, 'the menu: Mark as unread');
	pg_assert_contains("pg-bell-read-item", $quick, 'the menu: Mark as read, for a phone without hover');
	pg_assert_contains("edit_notification(parseInt(\$(this).attr('data-pg-bell-read-item'), 10) || 0, 'mark_read')", bell_row_source('assets/js/backend.src.js'), 'which reads the row');
	pg_assert_contains("lang('Delete')", $quick, 'the menu: Delete');
	pg_assert_false(strpos($row, 'Go to the relevant page') !== false, 'the full-width link line is gone');

	foreach (array('Shop', 'Accounting', 'Content', 'Team', 'System', 'Reply', 'Show', 'Join', 'Approve', 'Today', 'Yesterday', 'Earlier') as $word) {
		pg_assert_contains("lang('" . $word . "')", $words, $word);
	}

	pg_assert_contains('class="pg-bell-day"', $get, 'day headings');
	pg_assert_contains('fold_head', $get, 'a fold\'s runs open no heading of their own');
	pg_assert_contains('pg_bell_row(response.data[i], draw)', $get, 'each row');
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

	foreach (array('Shop', 'Accounting', 'Content', 'Team', 'System', 'Reply', 'Show', 'Join', 'Approve', 'Today', 'Yesterday', 'Earlier', 'Delete', 'More actions', 'Mark as read', 'Mark as unread', 'Show {var:1} more', 'Hide') as $word) {
		pg_assert_contains("'" . $word . "' => lang('" . $word . "')", $output, $word . ' is in the run-time dictionary');
	}
}
