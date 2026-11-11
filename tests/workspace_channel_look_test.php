<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source check for the Workspace channel screen's shell: the header, the
 * bars under it and the tabs are one card (.ws-channel-card, drawn by
 * drawCenter() in workspace.js), the channel's colour sits on that card as
 * a wash from the side its sign stands on and no longer as a top stripe,
 * the tabs are pills, the day mark is a chip without rules and the writing
 * box is a card.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_channel_look_css()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
}

// The body of a method of the screen object in workspace.js.
function workspace_channel_look_method($name)
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	preg_match('/\n        ' . preg_quote($name, '/') . ': function \(\) \{\n(.*?)\n        \},\n/s', $js, $match);

	return $match[1] ?? '';
}

// The declarations of every top-level block whose selector list is exactly $selector.
function workspace_channel_look_block($css, $selector)
{
	if (!preg_match('/\n' . preg_quote($selector, '/') . ' \{([^}]*)\}/', $css, $match)) {
		return '';
	}

	return $match[1];
}

function test_workspace_channel_look_has_no_top_stripe()
{
	$css = workspace_channel_look_css();

	pg_assert_false(strpos($css, 'inset 0 3px 0 var(--ws-ch)') !== false, 'the colour stripe on top of the header is gone');
	pg_assert_false(strpos($css, '.ws-head[data-ws-colored] {') !== false, 'no rule colours the header box itself');
	pg_assert_contains('.ws-channel-card[data-ws-colored] .ws-head-icon {', $css, 'the icon tile keeps its wash');
}

function test_workspace_channel_look_card_holds_header_bars_and_tabs()
{
	$center = workspace_channel_look_method('drawCenter');

	pg_assert_contains("var card = el('div', 'ws-channel-card');", $center, 'drawCenter() builds the card');
	pg_assert_contains("var head = el('div', 'ws-head ws-head-channel');", $center, 'the header keeps its classes for the tour and the menu');
	pg_assert_contains('card.appendChild(head);', $center, 'the header is in the card');
	pg_assert_contains('card.appendChild(auditBar);', $center, 'the audit bar is a row of the card');
	pg_assert_contains('card.appendChild(joinBar);', $center, 'and the join bar');
	pg_assert_contains('card.appendChild(self.guestBar(channel));', $center, 'and the guest bar');
	pg_assert_contains('card.appendChild(self.shareBar(channel));', $center, 'and the share bar');
	pg_assert_contains('card.appendChild(eraBar);', $center, 'and the era bar');
	pg_assert_contains('card.appendChild(tabs);', $center, 'the tabs close the card');
	pg_assert_false(strpos($center, 'center.appendChild(head)') !== false, 'the header is not a loose child of the pane');
	pg_assert_false(strpos($center, 'center.appendChild(tabs)') !== false, 'nor are the tabs');

	$css = workspace_channel_look_css();
	$card = workspace_channel_look_block($css, '.ws-channel-card');

	pg_assert_contains('border: 1px solid', $card, 'the card has a border');
	pg_assert_contains('border-radius:', $card, 'and round corners');
	pg_assert_contains('flex-shrink: 0;', $card, 'and keeps its height when the screen is short');
	pg_assert_false(strpos($card, 'max-width') !== false, 'panel screens stay full width');
	pg_assert_contains('.ws-channel-card > .alert', $css, 'the bars are drawn as rows of the card');
	pg_assert_contains('body.ws-keyboard .ws-center > :is(.ws-strip, .ws-tabs),', $css, 'the tabs of other screens still wait for the keyboard');
	pg_assert_contains('body.ws-keyboard .ws-channel-card > .ws-tabs {', $css, 'and so do the card\'s tabs');
}

function test_workspace_channel_look_colour_is_on_the_card()
{
	$center = workspace_channel_look_method('drawCenter');

	pg_assert_contains("card.style.setProperty('--ws-ch', channel.hex);", $center, 'drawCenter() puts the colour on the card');
	pg_assert_contains("card.setAttribute('data-ws-colored', '1');", $center, 'and marks it coloured');
	pg_assert_false(strpos($center, "head.style.setProperty('--ws-ch'") !== false, 'not on the header alone, which the tabs do not see');

	$refresh = workspace_channel_look_method('refreshHead');

	pg_assert_contains("var card = head.closest('.ws-channel-card');", $refresh, 'refreshHead() finds the card');
	pg_assert_contains("card.style.setProperty('--ws-ch', channel.hex);", $refresh, 'sets the colour there');
	pg_assert_contains("card.style.removeProperty('--ws-ch');", $refresh, 'and takes it away there');
	pg_assert_contains("card.removeAttribute('data-ws-colored');", $refresh, 'with the mark');

	$css = workspace_channel_look_css();
	$colored = workspace_channel_look_block($css, '.ws-channel-card[data-ws-colored]');

	pg_assert_contains('var(--ws-ch)', $colored, 'a coloured card takes the colour on its border and wash');
	pg_assert_contains('--ws-wash-from: to right;', $colored, 'the wash starts on the sign\'s side');
	pg_assert_contains('linear-gradient(var(--ws-wash-from)', $colored, 'and fades across the card');
	pg_assert_contains('[dir="rtl"] .ws-channel-card[data-ws-colored] {', $css, 'and from the other side right to left');
	pg_assert_contains('html[data-bs-theme="dark"] .ws-channel-card[data-ws-colored] {', $css, 'the dark theme has its own strengths');
}

function test_workspace_channel_look_tabs_are_pills()
{
	$css = workspace_channel_look_css();

	$pill = workspace_channel_look_block($css, '.ws-channel-card .ws-tabs button');
	pg_assert_contains('border-radius:', $pill, 'a tab is a pill');

	$open = workspace_channel_look_block($css, '.ws-channel-card .ws-tabs button.active');
	pg_assert_contains('background:', $open, 'the open tab is filled');

	pg_assert_contains('.ws-channel-card[data-ws-colored] .ws-tabs button.active {', $css, 'in the channel\'s colour when it has one');
	pg_assert_contains('.ws-channel-card .ws-tabs button:focus-visible {', $css, 'the keyboard focus shows');
	pg_assert_contains('.ws-tabs button.active .ws-tab-mark {', $css, 'counts keep their marks');
}

function test_workspace_channel_look_day_mark_is_a_chip()
{
	$css = workspace_channel_look_css();

	pg_assert_false(strpos($css, '.ws-day::before') !== false, 'no rule before the day');
	pg_assert_false(strpos($css, '.ws-day::after') !== false, 'nor after it');

	$day = workspace_channel_look_block($css, '.ws-day');
	pg_assert_contains('border-radius: 999px;', $day, 'the day is a chip');
	pg_assert_contains('width: fit-content;', $day, 'as wide as its words');

	$composer = workspace_channel_look_block($css, '.ws-composer');
	pg_assert_contains('background: var(--pg-card-bg);', $composer, 'the writing box is a card');
}

// The Tasks, Decisions and Files panes of the channel as cards. My tasks and
// a record's window keep their rows: they draw through the same taskRows().
function test_workspace_channel_panes_tasks_are_cards_in_a_grid()
{
	$tasks = workspace_channel_look_method('drawTasks');

	pg_assert_contains("var list = el('div', 'ws-task-grid');", $tasks, 'the channel\'s tasks are drawn into a grid');
	pg_assert_contains("el('div', 'pg-toolbar ws-pane-toolbar mb-3')", $tasks, 'under a quiet toolbar');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	pg_assert_contains("var row = el('div', 'ws-list-row' + (task.open ? '' : ' ws-row-done'));", $js, 'each task stays one ws-list-row, so My tasks and the record window keep their rows');
	pg_assert_same(1, substr_count($js, "'ws-task-grid'"), 'only the channel\'s tab draws the grid');

	$css = workspace_channel_look_css();
	$grid = workspace_channel_look_block($css, '.ws-task-grid');
	pg_assert_contains('display: grid;', $grid, 'the tasks are a grid');
	pg_assert_contains('minmax(min(100%, ', $grid, 'one column on a phone');

	$card = workspace_channel_look_block($css, '.ws-task-grid > .ws-list-row');
	pg_assert_contains('border: 1px solid', $card, 'a task is a card');
	pg_assert_contains('border-radius:', $card, 'with round corners');
	pg_assert_false(strpos($card, 'border-bottom') !== false, 'and no rule under it');
	pg_assert_false(strpos($grid . $card, 'max-width') !== false, 'the grid fills the pane');
}

function test_workspace_channel_panes_decisions_are_a_card_grid()
{
	$css = workspace_channel_look_css();
	$pane = workspace_channel_look_block($css, '.ws-pane-decisions');

	pg_assert_contains('display: grid;', $pane, 'the decisions are a grid');
	pg_assert_contains('minmax(min(100%, ', $pane, 'one column on a phone');
	pg_assert_contains('.ws-pane-decisions > :is(.ws-empty, .ws-skeleton) {', $css, 'the empty state and the skeleton span the grid');

	$card = workspace_channel_look_block($css, '.ws-pane-decisions > .ws-msg');
	pg_assert_contains('border-block: 1px solid', $card, 'each decision is a card');
	pg_assert_contains('border-inline-end: 1px solid', $card, 'framed on three sides');
	pg_assert_false(strpos($card, 'border: ') !== false, 'the start edge stays the colour of its kind');
	pg_assert_contains('border-inline-start: 3px solid var(--pg-success);', workspace_channel_look_block($css, '.ws-pane-decisions > .ws-msg-decision,' . "\n" . '.ws-pane-decisions > .ws-msg-note'), 'a decision keeps its green edge on the reading side');
	pg_assert_contains(".ws-pane-decisions > .ws-msg-note {\n  border-inline-start-color: var(--pg-info);\n}", $css, 'a note its blue one');

	preg_match('/\n        loadList: function \(action, emptyText\) \{\n(.*?)\n        \},\n/s', (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js'), $match);
	$list = $match[1] ?? '';
	pg_assert_contains("var node = self.messageNode(message, null);", $list, 'a decision is still drawn as the conversation draws it');
	pg_assert_contains("t('show_in_conversation')", $list, 'with its way back to the conversation');
}

function test_workspace_channel_panes_file_groups_are_cards()
{
	preg_match('/\n        drawFiles: function \(\) \{\n(.*?)\n        \},\n/s', (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js'), $match);
	$files = $match[1] ?? '';

	pg_assert_same(3, substr_count($files, "el('section', 'ws-file-group')"), 'pictures, videos and the file lists are each drawn in a group card');
	pg_assert_false(strpos($files, "pane.appendChild(el('div', 'ws-section-title'") !== false, 'no group title sits loose in the pane');

	$css = workspace_channel_look_css();
	$group = workspace_channel_look_block($css, '.ws-file-group');
	pg_assert_contains('border: 1px solid', $group, 'a group is a card');
	pg_assert_contains('background: var(--pg-card-bg);', $group, 'on the card background');

	$rows = workspace_channel_look_block($css, '.ws-file-group .ws-file-row');
	pg_assert_contains('border-bottom: 0;', $rows, 'its file rows have no rule between them');
	pg_assert_contains('border: 0;', workspace_channel_look_block($css, '.ws-file-group .ws-file-list'), 'and the list no frame of its own');
}

// The Summary, Board and Memory panes as cards, and the channel panes' empty
// states in a dashed card.
function test_workspace_channel_panes_summary_is_a_card_with_edit_in_its_head()
{
	$summary = workspace_channel_look_method('drawSummary');

	pg_assert_contains("var card = el('section', 'card ws-summary-card');", $summary, 'the summary is a card');
	pg_assert_contains('header.appendChild(edit);', $summary, 'Edit stands in its head');
	pg_assert_contains('clear(body);', $summary, 'the editor opens inside the card');
	pg_assert_false(strpos($summary, 'clear(pane);') !== false, 'not in place of the whole pane');
	pg_assert_contains("var side = el('div', 'ws-summary-side');", $summary, 'the customer and the time spent stand beside it');
	pg_assert_contains('side.appendChild(window.PGWsTaskTime.channelCard(', $summary, 'the time spent among them');

	$css = workspace_channel_look_css();
	pg_assert_contains('grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);', workspace_channel_look_block($css, '.ws-summary-layout'), 'side by side on a wide pane');
	pg_assert_contains("@container (max-width: 48rem) {\n  .ws-summary-layout {\n    grid-template-columns: minmax(0, 1fr);", $css, 'one under the other on a narrow one');

	$stat = workspace_channel_look_block($css, '.ws-task-time-stat');
	pg_assert_contains('border: 1px solid', $stat, 'each figure of Time spent is a tile');

	$time = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_task_time.js');
	pg_assert_contains("h.el('div', 'card ws-task-time-card')", $time, 'the card takes its place from the layout');
}

function test_workspace_channel_panes_board_toolbar_is_chips_and_columns_cards()
{
	$board = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_board_channel.js');

	pg_assert_contains("k.el('div', 'ws-chip-group')", $board, 'the grouping is a row of chips');
	pg_assert_contains("k.button('btn btn-sm ws-chip' + (prefs.group === key ? ' active' : '')", $board, 'each grouping a chip');
	pg_assert_contains("var chip = k.button('btn btn-sm ws-chip' + (on ? ' active' : '')", $board, 'the switches are chips');
	pg_assert_contains("chip.setAttribute('aria-pressed', on ? 'true' : 'false');", $board, 'that say whether they are on');
	pg_assert_false(strpos($board, "'btn-group btn-group-sm'") !== false, 'no button group left');

	$css = workspace_channel_look_css();
	$col = workspace_channel_look_block($css, '.ws-pane-board .ws-cb-col');
	pg_assert_contains('border: 1px solid', $col, 'a column of the channel\'s board is a card');
	pg_assert_contains('background: var(--pg-card-bg);', $col, 'on the card background');
	pg_assert_contains('border: 2px solid transparent;', workspace_channel_look_block($css, '.ws-cb-col'), 'the workflow table\'s board keeps its columns');
	pg_assert_contains("    .ws-cb-col.ws-cb-col-current {\n        display: flex;", $css, 'a phone still shows the picked column');
}

function test_workspace_channel_panes_memory_is_cards()
{
	$memory = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_memory.js');

	pg_assert_false(strpos($memory, 'list-group') !== false, 'no list-group rows');
	pg_assert_contains("var intro = el('div', 'ws-memory-intro');", $memory, 'the hint is a card');
	pg_assert_contains('intro.appendChild(add);', $memory, 'with New record in it');
	pg_assert_contains("el('div', 'ws-memory-entry d-flex align-items-start gap-3'", $memory, 'each record is a card');

	$css = workspace_channel_look_css();
	pg_assert_contains('border: 1px solid', workspace_channel_look_block($css, '.ws-memory-intro'), 'the hint card has a border');
	pg_assert_contains('border: 1px solid', workspace_channel_look_block($css, '.ws-memory-entry'), 'and so does a record');
	pg_assert_contains('display: grid;', workspace_channel_look_block($css, '.ws-memory-list'), 'records are a grid');
}

function test_workspace_channel_panes_empty_states_are_dashed_cards()
{
	$css = workspace_channel_look_css();
	$empty = workspace_channel_look_block($css, '.ws-pane:is(.ws-pane-tasks, .ws-pane-decisions, .ws-pane-files, .ws-pane-summary, .ws-pane-board, .ws-pane-memory) .ws-empty');

	pg_assert_contains('border: 1px dashed', $empty, 'an empty channel pane shows a dashed card');
	pg_assert_same("\n  text-align: center;\n  color: var(--pg-fg-2);\n  padding: 2.5rem 1rem;\n", workspace_channel_look_block($css, '.ws-empty'), 'other screens keep their empty state');
	pg_assert_contains('.ws-task-grid > .ws-empty {', $css, 'the task grid\'s empty state still spans it');
}
