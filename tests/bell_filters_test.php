<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the bell's filters: the selector printed for everybody (All,
 * Unread, Asked of me), the module chips, the search in the head and its
 * marks, the rows that ask something of the reader (pg_notification_asks()),
 * and the kept choice, the one kept before the selector read once.
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

// The selector, the chips and the search are printed for everybody, not
// only for a member of the team: one segmented control (All, Unread, Asked
// of me), the module chips as toggles with no All chip, the search in the
// head.
function test_bell_filters_markup()
{
	$output = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php');
	$at = strpos($output, "\$output_bell_filters = '<div class=\"pg-bell-filters\">");
	pg_assert_true($at !== false, 'the filters are printed');
	$part = (string) substr($output, (int) $at, (int) strpos($output, '$output_bell_drawer = ', (int) $at) - (int) $at);

	pg_assert_false(strpos($output, "\$output_workspace_rights['member']) {\n        \$output_bell_filters") !== false, 'not only for the team');
	pg_assert_contains('<div class="pg-seg pg-bell-seg" role="group"', $part, 'one segmented control');
	pg_assert_contains("array('all' => lang('All'), 'unread' => lang('Unread'), 'ask' => lang('Asked of me'))", $part);
	pg_assert_contains('class="pg-seg-item pg-bell-view no-popover" data-pg-bell-view="', $part);
	pg_assert_contains("array('shop' => array(lang('Shop'), 'teal'), 'erp' => array(lang('Accounting'), 'amber'), 'content' => array(lang('Content'), 'rose'), 'team' => array(lang('Team'), 'blue'), 'system' => array(lang('System'), 'gray'))", $part, 'a chip per module, no All chip');
	pg_assert_contains('data-pg-bell-filter="\' . $filter . \'" aria-pressed="false" hidden>', $part, 'a chip waits hidden till the list has a row of it');
	pg_assert_contains('<div class="pg-bell-chips" role="group"', $part, 'the chips on one line');
	pg_assert_false(strpos($output, 'pg_bell_unread_only') !== false, 'no Unread only switch');
	pg_assert_false(strpos($part, 'pg-bell-search') !== false, 'the search is no line of its own');
	pg_assert_false(strpos($part, "lang('Site')") !== false, 'no Site chip');
	pg_assert_contains("pg_bell_state_note('no-match', 'gray', 'bi-funnel', '', lang('No notification matches these filters.'), lang('Clear filters'))", $output, 'the no-match state offers Clear filters');
}

// The kept choice reads under the new names; the switch and an "Asked of
// me" chip kept before are read once; pgBellOpen('workspace') opens Team; a
// row carries its module and ask.
function test_bell_filters_kept_choice()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$name = bell_filters_js_part($js, 'function pg_bell_filter_name(');
	$open = bell_filters_js_part($js, 'window.pgBellOpen = function');
	$row = bell_filters_js_part($js, 'function pg_bell_row(');
	$match = bell_filters_js_part($js, 'function pg_bell_row_matches(');
	$apply = bell_filters_js_part($js, 'function pg_bell_filter_apply(');
	$upgrade = bell_filters_js_part($js, 'function pg_bell_kept_upgrade(');

	pg_assert_contains("var pgBellFilters = ['all', 'shop', 'erp', 'content', 'team', 'system'];", $js);
	pg_assert_contains("var pgBellViews = ['all', 'unread', 'ask'];", $js);
	pg_assert_contains("if (name === 'workspace') {\n        return 'team';", $name);
	pg_assert_contains("if (name === 'site') {\n        return 'all';", $name);
	pg_assert_contains("return pg_bell_filter_name(pg_bell_store_get('pg-bell-filter') || 'all', 'all');", bell_filters_js_part($js, 'function pg_bell_filter_kept('));
	pg_assert_contains("pgBellFilter = pg_bell_filter_name(filter, '') || pg_bell_filter_kept();", $open);

	// Read once: a kept view means it ran; the kept ask chip becomes Asked
	// of me, the switch Unread, and the switch's key goes.
	pg_assert_contains("if (pg_bell_store_get('pg-bell-view') !== null) {\n        return;", $upgrade);
	pg_assert_contains("if (pg_bell_store_get('pg-bell-filter') === 'ask') {\n        view = 'ask';\n        pg_bell_store_set('pg-bell-filter', 'all');", $upgrade);
	pg_assert_contains("} else if (pg_bell_store_get('pg-bell-unread') === '1') {\n        view = 'unread';", $upgrade);
	pg_assert_contains("pg_bell_store_set('pg-bell-unread', null);", $upgrade);
	pg_assert_true(strpos($js, 'pg_bell_kept_upgrade();') < strpos($js, 'var pgBellFilter = pg_bell_filter_kept();'), 'read before the kept choice');

	pg_assert_contains("var ask = (parseInt(row.ask, 10) === 1);", $row);
	pg_assert_contains("(ask ? ' data-pg-bell-ask=\"1\"' : '')", $row);
	pg_assert_contains("(unread ? ' data-pg-bell-was-new=\"1\"' : '')", $row);

	// Chip, selector and search apply together; the search reads the title,
	// the place and the excerpt.
	pg_assert_contains("if ((view === 'ask') && (row.getAttribute('data-pg-bell-ask') !== '1')) {", $match);
	pg_assert_contains("if ((view === 'unread') && !row.classList.contains('new')", $match);
	pg_assert_contains("row.querySelectorAll('.pg-bell-title, .pg-bell-place, .pg-bell-excerpt')", $match);
	pg_assert_contains('pg_bell_row_matches(row, filter, view, search)', $apply);
	pg_assert_contains("chip.hidden = (key !== filter) && !present[key];", $apply);
	pg_assert_contains("chips.hidden = (modules < 2) && (filter === 'all');", $apply, 'the chips only with two modules or more');
	pg_assert_contains("var note = pg_bell_state(pgBellState, live, shown, view, counts.unread || 0);", $apply);
	pg_assert_contains("item.hidden = (item.getAttribute('data-pg-bell-note') !== note);", $apply, 'one state at most');
	pg_assert_contains("bar.hidden = (note === 'failed') || (note === 'empty');", $apply, 'no filters over nothing');
	pg_assert_contains("pill.hidden = !counts.unread;", $apply, 'the N new pill while a row is unread');
	pg_assert_contains("button.hidden = !counts.unread || (search !== '') || (button.parentNode !== first_heading);", $apply, 'Mark all as read at the first shown heading');

	// A chip pressed again shows every module; both choices stay for the
	// next visit; the search is cleared on close.
	pg_assert_contains("pgBellFilter = (name === pgBellFilter) ? 'all' : name;", $js);
	pg_assert_contains("pg_bell_store_set('pg-bell-filter', pgBellFilter);", $js);
	pg_assert_contains("pg_bell_store_set('pg-bell-view', pgBellView);", $js);
	pg_assert_contains("pgBellSearch = '';\n        box.find('.pg-bell-search').val('');", bell_filters_js_part($js, 'function pg_bell_search_show('));
}

// The search's marks are taken out before each new search, and a match is
// found letter by letter as the search reads the Turkish i.
function test_bell_filters_search_marks()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$mark = bell_filters_js_part($js, 'function pg_bell_search_mark(');

	pg_assert_contains("box.querySelectorAll('mark.pg-bell-mark')", $mark, 'old marks go first');
	pg_assert_contains("li.software_notification:not(.pg-bell-off) :is(.pg-bell-title, .pg-bell-excerpt)", $mark, 'only shown titles and texts');
	pg_assert_contains(".replace(/i̇/g, 'i').replace(/ı/g, 'i')", $mark, 'the Turkish i as one letter');
	pg_assert_contains('mark.textContent = source.slice(start, end);', $mark, 'inserted as text, never as markup');
	pg_assert_contains('pg_bell_search_mark(box, search);', bell_filters_js_part($js, 'function pg_bell_filter_apply('));
}

// A chip waiting for its rows stays hidden although the chips are flex
// items.
function test_bell_filters_hidden_chip_rule()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	pg_assert_contains("#notifications .pg-bell-chips[hidden],\n#notifications .pg-bell-filter[hidden] {\n  display: none;\n}", $css);
}
