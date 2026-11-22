<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Starter site Twitter feeds: neither dump embeds a Twitter timeline any
 * more (the footer column shows social icons, the two page regions are
 * empty, the theme no longer loads or starts twitterFetcher), and the
 * 2026.11.0 data step replaces a region only while its content is still one
 * of the stock values, so a second run changes nothing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function starter_twitter_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// A value as mysqldump writes it inside a quoted string.
function starter_twitter_sql_escape($value)
{
	return strtr($value, array("\\" => "\\\\", "'" => "\\'", '"' => '\\"', "\r" => '\\r', "\n" => '\\n', "\0" => '\\0'));
}

// The step's values: footer_tr, footer_en, stock_footer, stock_emptied.
function starter_twitter_step_values()
{
	$source = starter_twitter_source('includes/migrations/2026.11.0.php');

	$start = strpos($source, 'function upgrade_2026_11_0_starter_twitter()');
	pg_assert_true($start !== false, 'the step is defined');

	$from = strpos($source, '$footer_tr =', (int) $start);
	$to = strpos($source, '$changed = 0;', (int) $from);
	pg_assert_true($from !== false && $to !== false, 'the step\'s values are found');

	$footer_tr = $footer_en = '';
	$stock_footer = $stock_emptied = array();

	eval(substr($source, (int) $from, (int) $to - (int) $from));

	return array($footer_tr, $footer_en, $stock_footer, $stock_emptied);
}

function test_starter_twitter_dumps_load_no_feed()
{
	foreach (array('english_default', 'turkish_default') as $dump) {
		$sql = starter_twitter_source('data/backups/' . $dump . '/sql.sql');

		foreach (array('twitter-timeline', 'platform.twitter.com', 'tweets-feed', 'data-widget-id', 'twitterfetcher.min.js\"></script>') as $needle) {
			pg_assert_true(strpos($sql, $needle) === false, $dump . ': no ' . $needle);
		}

		pg_assert_true(strpos($sql, "(358,'home-firm-twitter-feed','','yes',") !== false, $dump . ': home-firm-twitter-feed is empty');
		pg_assert_true(strpos($sql, "(339,'home-event-twitter-strip','','yes',") !== false, $dump . ': home-event-twitter-strip is empty');
		pg_assert_true(strpos($sql, '<cregion>home-firm-twitter-feed</cregion>') !== false, $dump . ': the home-firm layout keeps its region tag');
		pg_assert_true(strpos($sql, '<cregion>home-event-twitter-strip</cregion>') !== false, $dump . ': the home-event layout keeps its region tag');
		pg_assert_true(strpos($sql, '<cregion>twitter-feed</cregion>') !== false, $dump . ': the footer keeps its fourth column');

		$scripts = starter_twitter_source('data/backups/' . $dump . '/files/scripts.js');
		pg_assert_true(stripos($scripts, 'twitter') === false && strpos($scripts, 'tweets') === false, $dump . ': scripts.js starts no Twitter feed');
	}
}

function test_starter_twitter_footer_is_social_icons()
{
	list($footer_tr, $footer_en) = starter_twitter_step_values();

	foreach (array('turkish_default' => array($footer_tr, 'Bizi Takip Edin'), 'english_default' => array($footer_en, 'Follow Us')) as $dump => $expected) {
		list($footer, $title) = $expected;

		pg_assert_contains('<h6 class="title">' . $title . '</h6>', $footer, $dump . ': the heading');
		pg_assert_contains('<ul class="list-inline social-list">', $footer, $dump . ': the theme\'s social list');
		pg_assert_contains('<li><a href="#"><i class="ti-facebook"></i></a></li>', $footer, $dump . ': ti- icons linking to #');
		pg_assert_true(strpos($footer, 'http') === false && strpos($footer, '<script') === false, $dump . ': no outside request');

		$sql = starter_twitter_source('data/backups/' . $dump . '/sql.sql');
		pg_assert_true(strpos($sql, "(205,'twitter-feed','" . starter_twitter_sql_escape($footer) . "','yes',") !== false, $dump . ': the dump ships the step\'s footer byte for byte');
	}
}

function test_starter_twitter_step_changes_only_stock()
{
	$source = starter_twitter_source('includes/migrations/2026.11.0.php');

	pg_assert_true(preg_match('/^\tupgrade_2026_11_0_starter_twitter\(\); +\/\/ 11\.0\.\d+$/m', $source) === 1, 'the step is called from upgrade_to_2026_11_0()');

	list($footer_tr, $footer_en, $stock_footer, $stock_emptied) = starter_twitter_step_values();

	pg_assert_same(2, count($stock_footer), 'one stock footer per dump');
	pg_assert_same(4, count($stock_emptied), 'two emptied regions per dump');
	pg_assert_same(array($footer_tr, $footer_en), array_values($stock_footer), 'each footer stock gets its own language');

	$stock = array_merge(array_keys($stock_footer), $stock_emptied);
	pg_assert_same(6, count(array_unique($stock)), 'the six stock values differ');

	foreach ($stock as $value) {
		pg_assert_true(strpos($value, 'twitter-timeline') !== false || strpos($value, 'tweets-feed') !== false, 'a stock value is Twitter markup');
		pg_assert_true(!in_array($value, array($footer_tr, $footer_en, ''), true), 'a new value is no stock value, so a second run changes nothing');
	}

	$start = (int) strpos($source, 'function upgrade_2026_11_0_starter_twitter()');
	$step = substr($source, $start, (int) strpos($source, "\n}\n", $start) - $start);

	pg_assert_same(2, substr_count($step, 'BINARY cregion_content'), 'both updates compare byte for byte');
	pg_assert_true(strpos($step, 'WHERE BINARY cregion_content = ') !== false, 'the footer changes only where it is a stock value');
	pg_assert_true(strpos($step, "SET cregion_content = ''\n\t\tWHERE BINARY cregion_content IN (") !== false, 'the two regions are emptied only where they are a stock value');
	pg_assert_true(strpos($step, 'DELETE') === false, 'no row is deleted');
}
