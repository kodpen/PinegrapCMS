<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the marketplace account screen (marketplace_settings.php): the
 * question and failure rows fit a phone, status and error text is drawn in
 * the emphasis inks and secondary text in the secondary colour instead of
 * an opacity, both dialogs are named by their titles, the account on show
 * is marked for screen readers and the notice counts are formatted.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function marketplace_settings_names_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/marketplace_settings.php');
}

function marketplace_settings_names_test_style()
{
	$source = marketplace_settings_names_test_source();
	$start = strpos($source, '<style>@layer custom {');

	pg_assert_true($start !== false, 'the screen style block is there');

	return substr($source, $start, (int) strpos($source, '</style>', $start) - $start);
}

// A question row is not a grid row: .mp-row carries a 760px minimum width
// and the questions sit outside the sideways-scrolling block.
function test_marketplace_settings_question_rows_have_no_grid_width()
{
	$source = marketplace_settings_names_test_source();
	$style = marketplace_settings_names_test_style();

	pg_assert_true(strpos($source, 'mp-row d-block') === false, 'no question row reuses the grid row');
	pg_assert_true(strpos($source, '<div class="mp-question">') !== false, 'question rows have their own class');
	pg_assert_true((bool) preg_match('/\.mp-question \{[^}]*border-top[^}]*\}/', $style), 'the question row keeps its separator');
	pg_assert_true(!preg_match('/\.mp-question \{[^}]*(min-width|grid)[^}]*\}/', $style), 'the question row has no grid or minimum width');
}

// The failure row wraps, so the message is not squeezed to a few words.
function test_marketplace_settings_failure_rows_wrap()
{
	$source = marketplace_settings_names_test_source();

	pg_assert_true(strpos($source, 'style="min-width:150px"') === false, 'no fixed 150px name column');
	pg_assert_true(strpos($source, '<div class="mp-fail d-flex flex-wrap') !== false, 'the failure row wraps');
	pg_assert_true((bool) preg_match('/\.mp-fail-name \{[^}]*flex: 1 1 150px;[^}]*min-width: 0;/', marketplace_settings_names_test_style()), 'the name shrinks from 150px');
}

// Statuses are look A's pills in a tone; the error line keeps the emphasis
// ink, and no fill colour is used as text.
function test_marketplace_settings_status_text_uses_pills_and_emphasis_inks()
{
	$source = marketplace_settings_names_test_source();
	$style = marketplace_settings_names_test_style();

	pg_assert_true(strpos($source, 'mp-pill') === false, 'no screen-own pill is left');
	pg_assert_contains('<span class="pg-pill pg-tone-teal">\' . lang(\'Active\')', $source, 'an active account is a teal pill');
	pg_assert_contains('<span class="pg-pill pg-tone-gray">\' . lang(\'Paused\')', $source, 'a paused account is a gray pill');
	pg_assert_contains('<span class="pg-pill pg-tone-rose">\' . h(lang(\'Failed\'))', $source, 'a failed order is a rose pill');
	pg_assert_contains("'<span class=\"pg-pill pg-tone-' . \$label[1] . '\">'", $source, 'claim and question statuses name their tone');
	pg_assert_true((bool) preg_match('/\.mp-err \{[^}]*color: var\(--pg-danger-fg-emphasis\);/', $style), 'error line text');
	pg_assert_true(!preg_match('/color: var\(--pg-(success|danger)\);/', $style), 'no fill colour used as text');
}

// Secondary text is the look's second or meta text, never an opacity.
function test_marketplace_settings_secondary_text_without_opacity()
{
	$source = marketplace_settings_names_test_source();
	$style = marketplace_settings_names_test_style();

	$at = strpos($style, "\n.mp-head {");
	pg_assert_true($at !== false, '.mp-head rule is there');
	$rule = substr($style, $at, (int) strpos($style, '}', $at) - $at);
	pg_assert_contains('color: var(--pg-fg-3)', $rule);
	pg_assert_contains('font-size: var(--pg-text-meta)', $rule);

	pg_assert_true(strpos($style, 'opacity') === false, 'the style block has no opacity');
	pg_assert_true(strpos($source, 'opacity-75') === false, 'no text is greyed with opacity-75');
	pg_assert_true(strpos($source, 'class="small') === false && strpos($source, ' small ') === false && strpos($source, '<small>') === false, 'no Bootstrap small text is left');
}

function test_marketplace_settings_dialogs_are_named_by_their_titles()
{
	$source = marketplace_settings_names_test_source();

	pg_assert_contains('<dialog class="dialog" id="mp_new" aria-labelledby="mp_new_title">', $source);
	pg_assert_contains('<h5 class="dialog-title" id="mp_new_title">', $source);
	pg_assert_contains('<dialog class="drawer drawer-end" id="mp_drawer" aria-labelledby="mp_drawer_title"', $source);
	pg_assert_contains('<h5 class="drawer-title mp-name" id="mp_drawer_title">', $source);
}

function test_marketplace_settings_selected_account_is_current()
{
	pg_assert_contains("(\$is_selected ? ' sel\" aria-current=\"page' : '')", marketplace_settings_names_test_source());
}

function test_marketplace_settings_notice_counts_are_formatted()
{
	$source = marketplace_settings_names_test_source();

	pg_assert_contains("'vars' => array(pg_format_number(\$cleared, 0))", $source);
	pg_assert_contains("'vars' => array(pg_format_number(\$queued, 0))", $source);
}
