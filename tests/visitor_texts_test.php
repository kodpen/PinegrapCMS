<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of includes/fn/visitor_texts.php: how a referring
 * address or a user agent is cut and cleaned before it is hashed, the SQL
 * that reads both kinds of visitor row, the joins a query takes for it, and
 * the moment before which an unused value may go.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Nothing to keep for an empty text or a value that is not text at all.
function test_visitor_text_clean_empty_and_non_text()
{
	pg_assert_same('', pg_visitor_text_clean(''));
	pg_assert_same('', pg_visitor_text_clean(null));
	pg_assert_same('', pg_visitor_text_clean(array('x')), 'an array');
	pg_assert_same('42', pg_visitor_text_clean(42));
}

// Cut to the 255 characters of the columns it replaces, counted in
// characters and not bytes, so the hash is of what the list stores.
function test_visitor_text_clean_cuts_to_255_characters()
{
	pg_assert_same(255, mb_strlen(pg_visitor_text_clean(str_repeat('a', 400)), 'UTF-8'));
	pg_assert_same(str_repeat('ç', 255), pg_visitor_text_clean(str_repeat('ç', 300)), 'two-byte characters');
	pg_assert_same('Mozilla/5.0 😀', pg_visitor_text_clean('Mozilla/5.0 😀'), 'a four-byte character stays');
	pg_assert_same('https://www.google.com/', pg_visitor_text_clean('https://www.google.com/'));
}

// Bytes that are not UTF-8 are replaced, so the text the list keeps is the
// one that was hashed.
function test_visitor_text_clean_makes_utf8()
{
	$clean = pg_visitor_text_clean("Agent \xC3\x28 end");

	pg_assert_true(mb_check_encoding($clean, 'UTF-8'), 'valid UTF-8');
	pg_assert_contains('Agent', $clean);
	pg_assert_contains('end', $clean);
}

// No joins for a query that reads neither column; one per column it reads.
function test_visitor_text_joins_follow_the_columns_named()
{
	pg_assert_same('', pg_visitor_text_joins(''));
	pg_assert_same('', pg_visitor_text_joins('visitors.city, visitors.http_referer'), 'the plain column needs none');

	$agent = pg_visitor_text_joins("COALESCE(vt_agent.text_value, visitors.user_agent) LIKE '%iPad%'");

	pg_assert_same(' LEFT JOIN visitor_texts AS vt_agent ON (vt_agent.text_id = visitors.user_agent_id)', $agent);

	$both = pg_visitor_text_joins('COALESCE(vt_referer.text_value, v.http_referer), COALESCE(vt_agent.text_value, v.user_agent)', 'v');

	pg_assert_contains('LEFT JOIN visitor_texts AS vt_referer ON (vt_referer.text_id = v.http_referer_id)', $both);
	pg_assert_contains('LEFT JOIN visitor_texts AS vt_agent ON (vt_agent.text_id = v.user_agent_id)', $both);
}

// Without the list (here: no database at all) a column reads itself, as
// before the step; a column the list does not hold always does.
function test_visitor_text_sql_falls_back_to_the_column()
{
	pg_assert_same('visitors.http_referer', pg_visitor_text_sql('http_referer'));
	pg_assert_same('visitors.user_agent', pg_visitor_text_sql('user_agent'));
	pg_assert_same('visitors.utm_term', pg_visitor_text_sql('utm_term'));
	pg_assert_same('', pg_visitor_text_joins(pg_visitor_text_sql('user_agent')));
}

// A value may go once it was last used two days before the oldest visit
// left, or two days ago when there is none; a clock ahead of the database
// never moves the moment past now.
function test_visitor_texts_prune_before()
{
	$now = 1790000000;
	$two_days = 2 * PG_VISITOR_TEXT_REFRESH_SECONDS;

	pg_assert_same(1780000000 - $two_days, pg_visitor_texts_prune_before(1780000000, $now));
	pg_assert_same($now - $two_days, pg_visitor_texts_prune_before(0, $now), 'no visit left');
	pg_assert_same($now - $two_days, pg_visitor_texts_prune_before($now + 5000, $now), 'oldest visit in the future');
	pg_assert_same(0, pg_visitor_texts_prune_before(100, 200), 'never below 0');
}

// The step adds the number columns the module reads.
function test_visitor_texts_step_adds_the_columns_read()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.4.10.php');
	$start = strpos($source, 'function upgrade_2026_4_10_visitor_texts()');

	pg_assert_true($start !== false, 'the step is there');

	$body = substr($source, $start);

	pg_assert_contains("install_create_table('visitor_texts'", $body);

	foreach (pg_visitor_text_columns() as $column => $text) {
		pg_assert_contains("'" . $text['id'] . "'", $body, $column);
	}
}
