<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the bell's filters: the chips printed for everybody (All, the
 * modules, "Asked of me"), the "Unread only" switch and the search box, the
 * rows that ask something of the reader (pg_notification_asks()), and the
 * kept choice read under the chips' names.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

// The part of the script from one marker to the next top-level function.
function bell_filters_js_part($js, $marker)
{
	$at = strpos($js, $marker);
	pg_assert_true($at !== false, $marker . ' is there');
	$end = strpos($js, "\n}\n", (int) $at);

	return (string) substr($js, (int) $at, (($end === false) ? strlen($js) : (int) $end) - (int) $at);
}

// A mention, a question or an approval from the Workspace asks something of
// the reader; nothing else does.
function test_bell_filters_asked_of_me_rows()
{
	foreach (array('mention', 'question_asked', 'approval_requested') as $kind) {
		pg_assert_true(pg_notification_asks('workspace', $kind), $kind);
	}

	foreach (array('thread', 'approval_reminder', 'assigned', 'flow_finished', 'meeting_soon', '') as $kind) {
		pg_assert_false(pg_notification_asks('workspace', $kind), 'kind ' . $kind);
	}

	pg_assert_false(pg_notification_asks('new_comment', 'mention'), 'a site row never asks');
	pg_assert_false(pg_notification_asks('', ''), 'an empty row');

	$endpoint = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/notifications.php');
	pg_assert_contains("'ask' => (pg_notification_asks(\$display['action'], \$display['kind']) ? 1 : 0)", $endpoint);
}

// The chips, the switch and the search box are printed for everybody, not
// only for a member of the team.
function test_bell_filters_markup()
{
	$output = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php');
	$at = strpos($output, "\$output_bell_filters = '<div class=\"pg-bell-filters\"");
	pg_assert_true($at !== false, 'the filters are printed');
	$part = (string) substr($output, (int) $at, (int) strpos($output, '$output_bell_drawer = ', (int) $at) - (int) $at);

	pg_assert_false(strpos($output, "\$output_workspace_rights['member']) {\n        \$output_bell_filters") !== false, 'not only for the team');
	pg_assert_contains("array('all' => lang('All'), 'shop' => lang('Shop'), 'erp' => lang('Accounting'), 'content' => lang('Content'), 'team' => lang('Team'), 'system' => lang('System'), 'ask' => lang('Asked of me'))", $part);
	pg_assert_contains("((\$filter === 'all') ? '' : ' hidden')", $part, 'a chip waits hidden till the list has a row of it');
	pg_assert_contains('<input type="search" class="form-control form-control-sm pg-bell-search" id="pg_bell_search"', $part);
	pg_assert_contains('<input type="checkbox" role="switch" id="pg_bell_unread_only" class="pg-bell-unread-switch">', $part);
	pg_assert_contains("h(lang('Unread only'))", $part);
	pg_assert_false(strpos($part, "lang('Site')") !== false, 'no Site chip');
	pg_assert_contains("h(lang('No notification matches these filters.'))", $output, 'one line for an empty result');
}

// The kept choice reads under the chips' names; pgBellOpen('workspace')
// opens Team; a row carries its module and ask for the chips.
function test_bell_filters_kept_choice()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$name = bell_filters_js_part($js, 'function pg_bell_filter_name(');
	$open = bell_filters_js_part($js, 'window.pgBellOpen = function');
	$row = bell_filters_js_part($js, 'function pg_bell_row(');
	$match = bell_filters_js_part($js, 'function pg_bell_row_matches(');
	$apply = bell_filters_js_part($js, 'function pg_bell_filter_apply(');

	pg_assert_contains("var pgBellFilters = ['all', 'shop', 'erp', 'content', 'team', 'system', 'ask'];", $js);
	pg_assert_contains("if (name === 'workspace') {\n        return 'team';", $name);
	pg_assert_contains("if (name === 'site') {\n        return 'all';", $name);
	pg_assert_contains("return pg_bell_filter_name(kept, 'all');", bell_filters_js_part($js, 'function pg_bell_filter_kept('));
	pg_assert_contains("pgBellFilter = pg_bell_filter_name(filter, '') || pg_bell_filter_kept();", $open);

	pg_assert_contains("var ask = (parseInt(row.ask, 10) === 1);", $row);
	pg_assert_contains("(ask ? ' data-pg-bell-ask=\"1\"' : '')", $row);
	pg_assert_contains("(unread ? ' data-pg-bell-was-new=\"1\"' : '')", $row);

	// Chip, switch and search apply together; the search reads the title,
	// the place and the excerpt.
	pg_assert_contains("(filter === 'ask') ? (row.getAttribute('data-pg-bell-ask') !== '1')", $match);
	pg_assert_contains("if (unread_only && !row.classList.contains('new')", $match);
	pg_assert_contains("row.querySelectorAll('.pg-bell-title, .pg-bell-place, .pg-bell-excerpt')", $match);
	pg_assert_contains('pg_bell_row_matches(row, filter, pgBellUnreadOnly, search)', $apply);
	pg_assert_contains("chip.hidden = (key !== 'all') && (key !== filter) && !present[key];", $apply);
	pg_assert_contains("\$(box).find('.pg-bell-no-match').toggleClass('d-none', (live === 0) || (shown > 0));", $apply);

	// The switch stays for the next visit; the search is cleared on close.
	pg_assert_contains("localStorage.setItem('pg-bell-unread', pgBellUnreadOnly ? '1' : '0');", $js);
	pg_assert_contains("pgBellSearch = '';\n        \$('#notifications .pg-bell-search').val('');", $js);
}

// A chip waiting for its rows stays hidden although the chips are flex
// items.
function test_bell_filters_hidden_chip_rule()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	pg_assert_contains("#notifications .pg-bell-filter[hidden] {\n  display: none;\n}", $css);
}
