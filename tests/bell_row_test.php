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
		'oversold'         => 'shop',
		'booking_refused'  => 'shop',
		'whatsapp_message' => 'shop',
		'erp_overdue'      => 'erp',
		'erp_money'        => 'erp',
		'erp_low_stock'    => 'erp',
		'erp_quote_expiry' => 'erp',
		'erp_auto_invoice' => 'erp',
		'erp_profit_loss'  => 'erp',
		'form_submited'    => 'content',
		'new_comment'      => 'content',
		'content_change'    => 'content',
		'content_applied'   => 'content',
		'content_dismissed' => 'content',
		'content_stale'     => 'content',
		'prototype_comment' => 'content',
		'prototype_verdict' => 'content',
		'workspace'        => 'team',
		'software_update'  => 'system',
		'mail_failed'      => 'system',
		'ops'              => 'system',
		'profile_flows'    => 'system',
		'privacy_request'  => 'system',
		'launch_answer'    => 'system',
		'webhook_scope'    => 'system',
		'bundle_held'      => 'content',
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

function test_bell_row_short_time()
{
	// 2026-11-22 10:00 in Istanbul (UTC+3) is 07:00 UTC.
	$now = gmmktime(7, 0, 0, 11, 22, 2026);
	$zone = 'Europe/Istanbul';

	pg_assert_same('1 min', pg_notification_short_time($now - 20, $now, $zone), 'under a minute reads as one');
	pg_assert_same('1 min', pg_notification_short_time($now + 600, $now, $zone), 'a time after now reads as one minute');
	pg_assert_same('5 min', pg_notification_short_time($now - 5 * 60 - 59, $now, $zone), 'whole minutes, not rounded up');
	pg_assert_same('59 min', pg_notification_short_time($now - 3599, $now, $zone), 'the last minute of the hour');
	pg_assert_same('1 h', pg_notification_short_time($now - 3600, $now, $zone), 'one hour');
	pg_assert_same('3 h', pg_notification_short_time($now - 3 * 3600 - 1800, $now, $zone), 'whole hours');
	pg_assert_same('10 h', pg_notification_short_time(gmmktime(21, 0, 0, 11, 21, 2026), $now, $zone), 'local midnight is still today');
	pg_assert_same('Yesterday', pg_notification_short_time(gmmktime(20, 59, 0, 11, 21, 2026), $now, $zone), 'a minute before local midnight');
	pg_assert_same('Yesterday', pg_notification_short_time(gmmktime(21, 0, 0, 11, 20, 2026), $now, $zone), 'the first minute of yesterday');
	pg_assert_same('20 Nov', pg_notification_short_time(gmmktime(20, 59, 0, 11, 20, 2026), $now, $zone), 'the day before yesterday: day and month');
	pg_assert_same('Nov 20', pg_notification_short_time(gmmktime(20, 59, 0, 11, 20, 2026), $now, $zone, 'month_day'), 'month first where the site writes it so');
	pg_assert_same('9 Jan', pg_notification_short_time(gmmktime(9, 0, 0, 1, 9, 2026), $now, $zone), 'this year has no year');
	pg_assert_same('30 Dec 2025', pg_notification_short_time(gmmktime(9, 0, 0, 12, 30, 2025), $now, $zone), 'another year shows it');
	pg_assert_same('Yesterday', pg_notification_short_time(gmmktime(21, 0, 0, 11, 21, 2026), $now, 'No/Such_Zone'), 'an unknown zone falls back to UTC');
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
	pg_assert_contains("'day' => pg_notification_day((int) \$notification['timestamp'], \$now, \$zone)", $list, 'day, in the reader\'s zone');
	pg_assert_contains("'time' => pg_notification_short_time((int) \$notification['timestamp'], \$now, \$zone, \$date_format)", $list, 'the short time in the same zone');
	pg_assert_contains("\$zone = pg_time_uses_user_zone(array()) ? pg_timezone_canonical(USER_TIMEZONE) : '';", $list, 'the zone the full time in the title is written in');
	pg_assert_contains(": pg_notification_button(\$display['action'], \$display['kind'], \$display['join']))", $list, 'button');
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

	// A waiting content change and what became of it: a short title without a
	// colon, the page under it.
	foreach (array('content_change', 'content_applied', 'content_dismissed', 'content_stale') as $content_action) {
		$content = pg_notification_display(array('action' => $content_action, 'title' => 'about-us', 'reference_id' => 0));
		pg_assert_true(($content['short'] !== '') && (strpos($content['short'], ':') === false), $content_action . ' has a short title');
		pg_assert_contains('about-us', $content['brief'], $content_action . ': the page');
	}

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

function test_bell_row_short_time_tint_and_sticky_days()
{
	$endpoint = bell_row_function('includes/panel/notifications.php', 'pg_panel_get_notifications');
	$row = bell_row_function('assets/js/backend.src.js', 'pg_bell_row');
	$run = bell_row_function('assets/js/backend.src.js', 'pg_bell_run');
	$time = bell_row_function('assets/js/backend.src.js', 'pg_bell_time');
	$css = bell_row_source('assets/css/backend.src.css');

	// The endpoint sends the short time, the full one and the moment.
	pg_assert_contains("'time' => pg_notification_short_time(", $endpoint, 'the short time');
	pg_assert_contains("'time_title' => get_absolute_time(", $endpoint, 'the full date and time');
	pg_assert_contains("'datetime' => gmdate(", $endpoint, 'the moment');
	pg_assert_false(strpos($endpoint, 'get_relative_time') !== false, 'no relative sentence any more');

	// The script draws one escaped <time> with the full date as its title.
	pg_assert_contains("' title=\"' + h(full) + '\"'", $time, 'the full date and time is the title');
	pg_assert_contains("h(String(row.time || ''))", $time, 'the short time is escaped');
	pg_assert_contains("pg_bell_time(row, 'pg-list-row-time pg-bell-time')", $row, 'the row head');
	pg_assert_contains("pg_bell_time(row, '')", $run, 'a fold\'s run');
	pg_assert_contains('pg-bell-dot-head', $row, 'the phone\'s dot after the time');

	// The unread tint, the phone's order and 32 px menu button, sticky days.
	pg_assert_contains("#notifications .pg-bell-row.new > .pg-bell-card.pg-list-row {\n  background: color-mix(in srgb, var(--pg-accent) 6.5%, transparent);", $css, 'an unread row is tinted');
	pg_assert_contains("#notifications .pg-bell-card.pg-list-row .pg-bell-menu-toggle {\n    width: 32px;\n    height: 32px;", $css, 'the phone\'s 32 px menu button');
	pg_assert_contains("#notifications .pg-bell-card.pg-list-row .pg-bell-quick {\n    order: 3;", $css, 'the menu after the dot');
	pg_assert_contains("#notifications .pg-bell-day {\n  position: sticky;\n  top: 0;", $css, 'day headings stay at the top');
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

// A Workspace row's title: the name in the title's weight, the rest of the
// sentence quieter, in the language's own word order; every part escaped.
function test_bell_row_title_name_split()
{
	pg_assert_same('Ada <span class="pg-list-row-title-verb">mentioned you</span>', pg_notification_title_name('Ada mentioned you', 'Ada'));
	pg_assert_same('<span class="pg-list-row-title-verb">Asked by</span> Stock bot', pg_notification_title_name('Asked by Stock bot', 'Stock bot'));
	pg_assert_same('<span class="pg-list-row-title-verb">Here</span> Ada <span class="pg-list-row-title-verb">wrote</span>', pg_notification_title_name('Here Ada wrote', 'Ada'));
	pg_assert_same('A&amp;B <span class="pg-list-row-title-verb">&lt;b&gt;wrote&lt;/b&gt;</span>', pg_notification_title_name('A&B <b>wrote</b>', 'A&B'));
	pg_assert_same('@github <span class="pg-list-row-title-verb">answered you</span>', pg_notification_title_name('@github answered you', '@github'));

	// No name, or a name the sentence does not hold: the sentence escaped.
	pg_assert_same('Ada &amp; Bo wrote', pg_notification_title_name('Ada & Bo wrote', ''));
	pg_assert_same('Someone wrote', pg_notification_title_name('Someone wrote', 'Ada'));
	pg_assert_same('Ada', pg_notification_title_name('Ada', 'Ada'));
	pg_assert_same('', pg_notification_title_name('', 'Ada'));
}

// The Workspace row's short title reaches the bell split; site rows keep
// theirs.
function test_bell_row_title_name_split_reaches_the_row()
{
	$display = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/notifications.php');
	$notify = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notify.php');

	pg_assert_contains("\$display['short']       = pg_notification_title_name((string) (\$described['short'] ?? ''), (string) (\$described['who'] ?? ''));", $display);
	pg_assert_contains("'who'   => (string) (\$described['who'] ?? ''),", $notify);
}

// A state of the bell's list: the tile in its tone, the title, the
// sentence and the one button, every part escaped; the failed state's
// sentence is filled by the script.
function test_bell_state_note()
{
	$note = pg_bell_state_note('all-read', 'teal', 'bi-check2-all', 'All read', 'No unread notifications.', 'Show all');

	pg_assert_contains('<div class="ws-empty-screen pg-bell-note pg-bell-all-read" data-pg-bell-note="all-read" role="status" hidden>', $note);
	pg_assert_contains('<span class="pg-icon-tile ws-empty-screen-tile pg-tone-teal" aria-hidden="true"><span class="bi bi-check2-all"></span></span>', $note);
	pg_assert_contains('<div class="pg-title-section ws-empty-screen-title">All read</div>', $note);
	pg_assert_contains('<p class="pg-text-2 ws-empty-screen-text pg-bell-note-text">No unread notifications.</p>', $note);
	pg_assert_contains('<button type="button" class="pg-button pg-button-quiet pg-button-sm pg-bell-note-act no-popover" data-pg-bell-note-act="all-read">Show all</button>', $note);

	$failed = pg_bell_state_note('failed', 'rose', 'bi-exclamation-triangle', 'Could <not>', '', 'Try again');
	pg_assert_contains('role="alert"', $failed);
	pg_assert_contains('Could &lt;not&gt;', $failed);
	pg_assert_contains('<p class="pg-text-2 ws-empty-screen-text pg-bell-note-text"></p>', $failed, 'the script fills the reason');
	pg_assert_contains('<span class="bi bi-arrow-clockwise" aria-hidden="true"></span>Try again', $failed);

	$empty = pg_bell_state_note('empty', 'gray', 'bi-bell', 'No notifications', 'Text', '');
	pg_assert_false(strpos($empty, '<button') !== false, 'no button without a label');
	pg_assert_contains('role="status"', $empty);

	$none = pg_bell_state_note('no-match', 'gray', 'bi-funnel', '', 'No match', 'Clear filters');
	pg_assert_false(strpos($none, 'ws-empty-screen-title') !== false, 'no title without one');
	pg_assert_contains('<span class="bi bi-x-lg" aria-hidden="true"></span>Clear filters', $none);

	pg_assert_same('', pg_bell_state_note('bad key', 'gray', 'bi-bell', 'X', 'Y', ''), 'an unknown key draws nothing');
}
