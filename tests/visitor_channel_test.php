<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the channel of a visit and the search engine of a referring
 * host: pg_visitor_channel() and pg_visitor_channel_sql() in
 * includes/fn/visitor_retention.php, pg_visitor_search_engine() in
 * includes/fn/seo.php, and the summary days a breakdown hands to the rows
 * in includes/fn/visitor_figures.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function visitor_channel_of($host, $engine = '', $medium = '', $code = '', $flag = '')
{
	return pg_visitor_channel(array(
		'referring_host_name' => $host,
		'referring_search_engine' => $engine,
		'utm_medium' => $medium,
		'tracking_code' => $code,
	), $flag);
}

// Every kind of referrer lands in its channel.
function test_visitor_channel_kinds()
{
	pg_assert_same('direct', visitor_channel_of(''));
	pg_assert_same('referral', visitor_channel_of('www.example.org'));
	pg_assert_same('organic', visitor_channel_of('www.google.com', 'Google'));
	pg_assert_same('social', visitor_channel_of('m.facebook.com'));
	pg_assert_same('social', visitor_channel_of('www.linkedin.com'));
	pg_assert_same('social', visitor_channel_of('t.co'));
	pg_assert_same('social', visitor_channel_of('', '', 'Social'), 'medium alone, any case');
	pg_assert_same('ai', visitor_channel_of('chatgpt.com'));
	pg_assert_same('ai', visitor_channel_of('www.perplexity.ai'));
	pg_assert_same('email', visitor_channel_of('', '', 'newsletter'));
	pg_assert_same('email', visitor_channel_of('mail.example.com', '', 'email'));
	pg_assert_same('paid', visitor_channel_of('', '', 'cpc'));
	pg_assert_same('paid', visitor_channel_of('', '', ' PPC '));
	pg_assert_same('paid', visitor_channel_of('', '', '', 'spring-adwords', 'adwords'));
}

// A site whose name only ends like a social network's is a referral.
function test_visitor_channel_social_by_last_two_labels()
{
	pg_assert_same('referral', visitor_channel_of('notfacebook.com.tr'));
	pg_assert_same('referral', visitor_channel_of('facebook.example.com'));
	pg_assert_same('social', visitor_channel_of('l.instagram.com'));
}

// The first that matches wins: paid before email before ai before organic
// before social before referral.
function test_visitor_channel_precedence()
{
	pg_assert_same('paid', visitor_channel_of('www.google.com', 'Google', 'cpc'), 'a paid search click is paid');
	pg_assert_same('paid', visitor_channel_of('m.facebook.com', '', 'paid_social'));
	pg_assert_same('paid', visitor_channel_of('', '', 'email', 'x-adwords', 'adwords'), 'the flag before the medium');
	pg_assert_same('email', visitor_channel_of('www.google.com', 'Google', 'email'));
	pg_assert_same('ai', visitor_channel_of('chatgpt.com', '', 'social'));
	pg_assert_same('organic', visitor_channel_of('www.youtube.com', 'Google'));
	pg_assert_same('social', visitor_channel_of('www.youtube.com', '', 'referral'));
	pg_assert_same('referral', visitor_channel_of('www.example.org', '', '', 'x-adwords', ''), 'no flag, nothing paid by code');
}

// The SQL tries the channels in the rule's order and never builds a pattern
// that matches every visit.
function test_visitor_channel_sql_order()
{
	$sql = pg_visitor_channel_sql('v.');
	$at = -1;

	foreach (pg_visitor_channels() as $channel) {
		$position = ($channel === 'direct') ? strpos($sql, "ELSE 'direct' END") : strpos($sql, "THEN '" . $channel . "'");
		pg_assert_true($position !== false, $channel . ' is there');
		pg_assert_true($position > $at, $channel . ' comes in its place');
		$at = $position;
	}

	pg_assert_contains("SUBSTRING_INDEX(LOWER(v.referring_host_name), '.', -2) IN (", $sql);
	pg_assert_contains("'chatgpt.com'", $sql);
	pg_assert_false(strpos($sql, "LIKE '%%'") !== false, 'no pattern that matches every visit');
	pg_assert_contains("'channel'", var_export(array_keys(visitor_channel_summary_names()), true));
}

function visitor_channel_summary_names()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/visitor_retention.php');
	preg_match_all("/^        '([a-z_]+)' => /m", substr($source, strpos($source, 'function pg_visitor_summary_dimensions()')), $m);

	return array_flip($m[1]);
}

// Google is matched by host with any country suffix; the new engines are
// known; an AI assistant under a search engine's domain is not search.
function test_visitor_search_engine_hosts()
{
	$cases = array(
		'www.google.com' => array('Google', 'q'),
		'google.com.tr' => array('Google', 'q'),
		'www.google.co.uk' => array('Google', 'q'),
		'WWW.GOOGLE.DE.' => array('Google', 'q'),
		'www.bing.com' => array('Bing', 'q'),
		'yandex.com.tr' => array('Yandex', 'text'),
		'yandex.ru' => array('Yandex', 'text'),
		'duckduckgo.com' => array('DuckDuckGo', 'q'),
		'www.ecosia.org' => array('Ecosia', 'q'),
		'search.brave.com' => array('Brave Search', 'q'),
		'www.qwant.com' => array('Qwant', 'q'),
		'www.startpage.com' => array('Startpage', 'query'),
		'www.baidu.com' => array('Baidu', 'wd'),
		'm.search.naver.com' => array('Naver', 'query'),
		'search.seznam.cz' => array('Seznam', 'q'),
		'uk.search.yahoo.com' => array('Yahoo!', 'p'),
		'search.aol.com' => array('AOL', 'query'),
	);

	foreach ($cases as $host => $expected) {
		pg_assert_same(array('name' => $expected[0], 'key' => $expected[1]), pg_visitor_search_engine($host), $host);
	}

	foreach (array('', 'flask.com', 'mygoogle.com', 'notyandex.com', 'www.example.org', 'gemini.google.com', 'brave.com') as $host) {
		pg_assert_same(null, pg_visitor_search_engine($host), $host === '' ? 'empty host' : $host);
	}
}

// Every name the table stores is a value the column holds once the schema
// step has run, and the step appends exactly the new ones.
function test_visitor_search_engine_names_are_enum_values()
{
	$dump = file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/english_default/sql.sql');
	preg_match('/`referring_search_engine` (enum\([^)]*\))/', $dump, $m);
	pg_assert_true(isset($m[1]), 'the dump has the column');

	foreach (pg_visitor_search_engines() as $engine) {
		pg_assert_true(pg_visitor_enum_lists($m[1], $engine['name']), $engine['name']);
	}

	$step = file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	pg_assert_contains("array('" . implode("', '", pg_visitor_search_engines_added()) . "')", $step);
}

// An old column refuses the appended names until the step has run.
function test_visitor_enum_lists()
{
	$old = "enum('','Google','Yahoo!')";

	pg_assert_true(pg_visitor_enum_lists($old, 'Google'));
	pg_assert_false(pg_visitor_enum_lists($old, 'Yandex'));
	pg_assert_true(pg_visitor_enum_lists("enum('','Google','Yandex')", 'Yandex'));
	pg_assert_true(pg_visitor_enum_lists('varchar(64)', 'Yandex'));
	pg_assert_false(pg_visitor_enum_lists('', 'Yandex'), 'no column');
}

// A summarized day without the dimension's rows is read from the rows; its
// neighbours stay with the summary and join their own source again.
function test_visitor_parts_move_days()
{
	$from = strtotime('2026-10-01 00:00:00');
	$to = strtotime('2026-10-06 00:00:00') - 1;
	$run = array('first' => '2026-09-01', 'last' => '2026-10-04');
	$parts = pg_visitor_day_split($from, $to, $run, '2026-11-03');

	pg_assert_same(array('summary', 'raw'), array_column($parts, 'source'));

	$moved = pg_visitor_parts_move_days($parts, array('2026-10-02', '2026-10-04'));

	pg_assert_same(array('summary', 'raw', 'summary', 'raw'), array_column($moved, 'source'));
	pg_assert_same(array('2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04'), array_column($moved, 'first'));
	pg_assert_same('2026-10-05', $moved[3]['last'], 'the moved last day joins the raw days after it');
	pg_assert_same(strtotime('2026-10-02 00:00:00'), $moved[1]['from']);
	pg_assert_same($to, $moved[3]['to']);
	pg_assert_same(array(1, 1, 1, 2), array_column($moved, 'days'));
	pg_assert_same($parts, pg_visitor_parts_move_days($parts, array()), 'nothing to move');
}
