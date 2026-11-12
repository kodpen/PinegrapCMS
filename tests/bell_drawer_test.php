<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the bell's drawer: a Workspace row's short title and the place
 * on its second line (ws_bell_title()), the drawer's markup at the right
 * edge, and the rules that open and close it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notify.php');

// Who did what, and the channel apart from it.
function test_bell_drawer_short_title_names_who_and_what()
{
	$channel = array('thread' => false, 'name' => '#sales');

	pg_assert_same(array('title' => 'Ada mentioned you', 'place' => '#sales'), ws_bell_title('mention', 'Ada', $channel));
	pg_assert_same(array('title' => 'Stock bot asked you', 'place' => '#sales'), ws_bell_title('question_asked', 'Stock bot', $channel));
	pg_assert_same(array('title' => 'Ada asked you to say you read it', 'place' => '#sales'), ws_bell_title('ack_reminder', 'Ada', $channel));
	pg_assert_same(array('title' => 'Guest wrote', 'place' => '#sales'), ws_bell_title('guest_message', 'Guest', $channel));
	pg_assert_same(array('title' => 'Ada added you to a channel', 'place' => '#sales'), ws_bell_title('invited', 'Ada', $channel));
	pg_assert_same(array('title' => '@github answered you', 'place' => '#sales'), ws_bell_title('agent_answer', '@github', $channel));
}

// A discussion is named by the channel it is in, and said to be one.
function test_bell_drawer_short_title_names_a_discussion()
{
	pg_assert_same(
		array('title' => 'Ada mentioned you', 'place' => 'A discussion in #sales'),
		ws_bell_title('mention', 'Ada', array('thread' => true, 'name' => '#sales'))
	);

	// A discussion whose channel is gone has no place.
	pg_assert_same(
		array('title' => 'Ada mentioned you', 'place' => ''),
		ws_bell_title('mention', 'Ada', array('thread' => true, 'name' => ''))
	);
	pg_assert_same(array('title' => 'Ada mentioned you', 'place' => ''), ws_bell_title('mention', 'Ada', null));
}

// A kind without a short form, or nobody named, keeps the whole sentence.
function test_bell_drawer_short_title_falls_back()
{
	$channel = array('thread' => false, 'name' => '#sales');
	$none = array('title' => '', 'place' => '');

	pg_assert_same($none, ws_bell_title('assigned', 'Ada', $channel));
	pg_assert_same($none, ws_bell_title('flow_finished', 'Ada', $channel));
	pg_assert_same($none, ws_bell_title('mention', '', $channel));
	pg_assert_same($none, ws_bell_title('mention', '   ', $channel));
}

// The describe cases hand the short title what it needs, the endpoint
// sends it as the row's title, and the push banner keeps the sentence.
function test_bell_drawer_short_title_reaches_the_row()
{
	$notify = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notify.php');
	$agent = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mcp_agent.php');
	$display = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/notifications.php');
	$endpoint = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/notifications.php');

	pg_assert_contains("\$short = ws_bell_title(\$kind, (string) (\$described['who'] ?? ''), \$described['place'] ?? null);", $notify);
	pg_assert_contains("'short' => \$short['title'],", $notify);
	pg_assert_contains("ws_mcp_agent_inbox_describe(\$viewer, \$row, \$channel_name, \$base) + array('place' => \$place);", $notify);
	pg_assert_contains("'who'   => '@' . (\$server ? \$server['alias'] : 'mcp'),", $agent);
	pg_assert_contains("\$display['short']       = h((string) (\$described['short'] ?? ''));", $display);
	pg_assert_contains("\$display['title']       = h(\$described['title']);", $display);
	pg_assert_contains("'title' => ((\$display['short'] !== '') ? \$display['short'] : \$display['title']),", $endpoint);
	pg_assert_contains("'place' => \$display['place'],", $endpoint);
}

// A part of the panel script: from its first line to the end of the block.
function bell_drawer_js_part($js, $start)
{
	$at = strpos($js, $start);
	pg_assert_true($at !== false, $start . ' is there');

	return (string) substr($js, (int) $at, (int) strpos($js, "\n}", (int) $at) - (int) $at);
}

// The drawer at the right edge, printed outside the header's navbar: the
// head (the name, "Mark all as read", the menu holding "Delete All
// Notifications", close), then the filters, the list and the push line.
function test_bell_drawer_markup()
{
	$output = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php');
	$at = strpos($output, "\$output_bell_drawer = '");
	$drawer = ($at === false) ? '' : substr($output, $at, strpos($output, '</dialog>', $at) - $at);

	pg_assert_contains('<dialog class="drawer drawer-end pg-bell-drawer pg-print-none" id="notifications" aria-labelledby="pg_bell_heading">', $drawer);
	pg_assert_contains('<h2 class="drawer-title pg-bell-heading" id="pg_bell_heading">\' . h(lang(\'Notifications\')) . \'</h2>', $drawer);
	pg_assert_contains('class="btn-close pg-bell-close" data-bs-dismiss="drawer"', $drawer, 'the close button');
	pg_assert_contains("h(lang('Delete All Notifications'))", $drawer, 'Delete All in the head menu');

	$last = -1;
	foreach (array('pg-bell-head', 'pg-bell-mark-all', 'pg-bell-head-menu', 'pg-bell-delete-all', 'pg-bell-close', '$output_bell_filters', 'pg-bell-scroll', 'notifications_empty', 'id="push_row"', 'id="push_hint"') as $part) {
		$pos = strpos($drawer, $part);
		pg_assert_true(($pos !== false) && ($pos > $last), $part . ' in its place');
		$last = (int) $pos;
	}

	// Escape and a click outside close it: Bootstrap's defaults, untouched.
	pg_assert_false(strpos($drawer, 'data-bs-backdrop') !== false, 'a click outside closes it');
	pg_assert_false(strpos($drawer, 'data-bs-keyboard') !== false, 'Escape closes it');

	// The bell opens it; it is printed after the header, where a resizing
	// navbar cannot hide it.
	pg_assert_contains('id="notification_toggle" class="nav-link nav-link-sm no-popover" data-bs-toggle="drawer" data-bs-target="#notifications" aria-controls="notifications" aria-haspopup="dialog"', $output);
	pg_assert_contains("' . \$output_bell_drawer . \$output_software_sidebar", $output, 'printed outside the navbar');
	pg_assert_false(strpos($output, 'id="notifications" class="menu') !== false, 'no Menu box left');
}

// The bell, Ctrl+Q and pgBellOpen() open the drawer; one list load per
// opening, the bell's show class and the 20 s poll on the drawer's state.
function test_bell_drawer_open_and_close()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$open = bell_drawer_js_part($js, 'window.pgBellOpen = function');
	$poll = bell_drawer_js_part($js, 'function check_unread_notifications(');
	$filter = bell_drawer_js_part($js, 'function pg_bell_filter_apply(');

	pg_assert_contains("pgUI.drawer.toggle('#notifications');", $js, 'Ctrl+Q toggles it');
	pg_assert_contains("pgUI.drawer.show('#notifications');", $open, 'pgBellOpen() shows it');
	pg_assert_contains("if (pgUI.drawer.isOpen('#notifications')) {\n        pg_bell_filter_apply();", $open, 'an open drawer only takes the filter');
	pg_assert_false(strpos($js, "\$('#notification_toggle').on('click'") !== false, 'the bell has no click handler of its own');

	// One list load per opening, started on show; once it is in, the close
	// button takes the focus (showModal() finds nothing while it is hidden).
	$at = strpos($js, "pgUI.drawer.on('#notifications', 'shown'");
	pg_assert_true($at !== false, 'the shown handler is there');
	$shown = (string) substr($js, (int) $at, (int) strpos($js, '});', (int) $at) - (int) $at);
	pg_assert_contains("\$('#notifications .pg-bell-close').trigger('focus');", $shown, 'focus on the close button');
	pg_assert_false(strpos($shown, 'get_notifications') !== false, 'no second load once it has opened');
	pg_assert_contains("pgUI.drawer.on('#notifications', 'show', function () {\n        \$('#notifications').find('.pg-bell-mark-all, .pg-bell-head-menu').addClass('d-none');\n        \$('#notification_toggle').addClass('show');\n        get_notifications();", $js);
	pg_assert_contains("pgUI.drawer.on('#notifications', 'hidden', function () {\n        \$('#notification_toggle').removeClass('show');", $js, 'the bell\'s show class goes when it closes');
	pg_assert_contains("pgUI.drawer.isOpen('#notifications')", $poll, 'the poll asks the drawer');
	pg_assert_false(strpos($js, "pgUI.menu.isOpen('#notification_toggle')") !== false, 'no Menu state is read');

	// Delete All is offered under All with two rows or more.
	pg_assert_contains("var no_delete = (filter !== 'all') || (rows.length < 2);", $filter);
	pg_assert_contains("\$(document).on('click', '#notifications .pg-bell-delete-all', function () {", $js);

	// A row's menu keeps Escape before the drawer hears it.
	pg_assert_contains("window.addEventListener('keydown', pg_bell_menu_key, true);", $js);
}

// Only the list scrolls; the drawer is 30rem, the whole width on a phone.
function test_bell_drawer_only_the_list_scrolls()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	$rule = function ($selector) use ($css) {
		$at = strpos($css, "\n" . $selector . ' {');
		pg_assert_true($at !== false, $selector . ' rule is there');

		return (string) substr($css, (int) $at, (int) strpos($css, '}', (int) $at) - (int) $at);
	};

	pg_assert_contains('--pg-drawer-width: min(30rem, 100vw);', $rule('#notifications.pg-bell-drawer'));
	pg_assert_contains('overflow: hidden;', $rule('#notifications .pg-bell-body'));
	pg_assert_contains('overflow-y: auto;', $rule('#notifications .pg-bell-scroll'));
	pg_assert_contains('min-height: 0;', $rule('#notifications .pg-bell-scroll'));
	pg_assert_false(strpos($css, '.menu-scroll-area') !== false, 'the old Menu list is gone');
}
