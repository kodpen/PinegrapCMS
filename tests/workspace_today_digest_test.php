<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Today's "What happened in the channels" and "Latest decisions"
 * cards (homeDigest() and homeDecisions() in assets/js/workspace.js, their
 * sections of assets/css/ws_today.src.css): drawn in the new dashboard's
 * parts, no old .ws-home-* row left in them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of one method of the channel screen in workspace.js, or ''.
function workspace_today_digest_test_method($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	if (!preg_match('/\n        ' . preg_quote($name, '/') . ': function \([^)]*\) \{\n(.*?)\n        \},\n/s', $source, $match)) {
		return '';
	}

	return $match[1];
}

function test_workspace_today_digest_card()
{
	$digest = workspace_today_digest_test_method('homeDigest');

	pg_assert_contains("self.homeCount(card, items.length, items.some(", $digest, 'the badge counts the channels, hot with a mention');
	pg_assert_contains("el('p', 'pg-dash-card-empty', t('home_digest_empty'))", $digest, 'the empty line is the dashboard\'s');
	pg_assert_contains("button('ws-today-digest-row')", $digest, 'each channel is a row button');
	pg_assert_contains("'ws-today-new' + (item.mentions ? ' ws-today-new-mention' : '')", $digest, 'a mention puts the count in the accent');
	pg_assert_contains('self.open(item.id, 0)', $digest, 'the row opens the channel');
	pg_assert_false(strpos($digest, 'ws-home-') !== false, 'no old row class left');
}

function test_workspace_today_decisions_card()
{
	$decisions = workspace_today_digest_test_method('homeDecisions');

	pg_assert_contains("el('ol', 'pg-dash-timeline ws-today-decisions')", $decisions, 'the decisions are the Activity line');
	pg_assert_contains("el('li', 'pg-dash-event ws-today-decision')", $decisions, 'each decision is an Activity event');
	pg_assert_contains("setAttribute('data-kind', item.claude ? 'claude' : 'decision')", $decisions, 'Claude\'s decisions are told apart');
	pg_assert_contains("button('pg-dash-event-note ws-today-decision-open')", $decisions, 'the channel line is the keyboard way to the message');
	pg_assert_contains("event.target.closest('a, button')", $decisions, 'a link in the words is followed, not the row');
	pg_assert_contains('CFG.urls.timeline', $decisions, 'the card links the Decision Timeline');
	pg_assert_false(strpos($decisions, 'ws-home-') !== false, 'no old row class left');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/ws_today.src.css');
	pg_assert_contains('.ws-today-decision[data-kind="claude"] {', $css, 'Claude\'s dot is in the accent');
	pg_assert_contains('.ws-today-digest-row:focus-visible {', $css, 'a digest row shows its focus');
}
