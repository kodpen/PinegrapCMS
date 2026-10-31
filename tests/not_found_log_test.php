<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the 404 log (includes/fn/not_found_log.php): the name an address
 * is logged under, the referrer kept, which requests are logged and as whom,
 * the WHERE of the list, the address that opens the short link screen for a
 * redirect, and the places a 404 hands the request to the log. The runner has
 * no connection: the table reads as missing and nothing is written.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function not_found_log_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// escape() reads db::$con; without a connection it falls back to addslashes().
function not_found_log_test_db_stub()
{
	if (!class_exists('db')) {
		class db
		{
			public static $con;
		}
	}
}

// A request that is logged as a visitor, with one key changed.
function not_found_log_test_request($change = array())
{
	return array_merge(array(
		'method' => 'GET',
		'rendering' => false,
		'name' => 'eski-sayfa',
		'live_page' => false,
		'prefetch' => false,
		'waf_rule' => '',
		'automated' => false,
		'verified_crawler' => false,
	), $change);
}

function test_not_found_log_name_is_the_stored_form_of_a_short_link_name()
{
	pg_assert_same('eski-sayfa', pg_not_found_log_name('eski-sayfa'), 'a plain name');
	pg_assert_same('blog/eski-yazi', pg_not_found_log_name('/blog//eski-yazi/'), 'no / at the ends, one between parts');
	pg_assert_same('Eski_Sayfa', pg_not_found_log_name('Eski Sayfa'), 'a space as _');
	pg_assert_same('ürünler/çanta', pg_not_found_log_name('ürünler/çanta'), 'letters of any language');
	pg_assert_same('wp-login.php', pg_not_found_log_name('wp-login.php'), 'a file name is a name');
	pg_assert_same(str_repeat('a', 191), pg_not_found_log_name(str_repeat('a', 191)), 'as long as short_links.name');
}

function test_not_found_log_name_leaves_out_what_no_short_link_can_be()
{
	pg_assert_same('', pg_not_found_log_name(''), 'the home page');
	pg_assert_same('', pg_not_found_log_name('/'), 'only slashes');
	pg_assert_same('', pg_not_found_log_name(str_repeat('a', 192)), 'longer than the column');
	pg_assert_same('', pg_not_found_log_name('eski*'), 'a star would make a pattern');
	pg_assert_same('', pg_not_found_log_name('100%'), 'a character outside the name rule');
	pg_assert_same('', pg_not_found_log_name("<script>"), 'markup');
	pg_assert_same('', pg_not_found_log_name('a/../b'), 'a part a browser resolves');
	pg_assert_same('', pg_not_found_log_name("\xff\xfe"), 'not UTF-8');
	pg_assert_same('', pg_not_found_log_name(array('x')), 'not a string');
}

function test_not_found_log_name_leaves_out_the_software_directory()
{
	pg_assert_same('', pg_not_found_log_name('pinegrap/missing.php', 'pinegrap'), 'a panel address');
	pg_assert_same('', pg_not_found_log_name('Pinegrap/x', 'pinegrap'), 'whatever its case');
	pg_assert_same('', pg_not_found_log_name('pinegrap', '/pinegrap/'), 'the directory itself');
	pg_assert_same('pinegrap-blog', pg_not_found_log_name('pinegrap-blog', 'pinegrap'), 'a name that only starts like it');
	pg_assert_same('pinegrap/x', pg_not_found_log_name('pinegrap/x', ''), 'no software directory given');
}

function test_not_found_log_referrer_keeps_scheme_host_and_path()
{
	pg_assert_same('https://www.google.com/search', pg_not_found_log_referrer('https://WWW.Google.com/search?q=gizli#x'), 'query and fragment go, host in lower case');
	pg_assert_same('http://example.com/', pg_not_found_log_referrer('http://example.com'), 'no path is the root');
	pg_assert_same('https://example.com:8443/a/b', pg_not_found_log_referrer('https://example.com:8443/a/b'), 'a port stays');
	pg_assert_same('https://example.com/ürün', pg_not_found_log_referrer('https://example.com/ürün'), 'letters stay as they came');
	pg_assert_same(512, mb_strlen(pg_not_found_log_referrer('https://example.com/' . str_repeat('ç', 600)), 'UTF-8'), 'at most 512 characters, not cut inside a letter');
}

function test_not_found_log_referrer_refuses_what_is_no_web_address()
{
	pg_assert_same('', pg_not_found_log_referrer(''), 'none');
	pg_assert_same('', pg_not_found_log_referrer('android-app://com.google.android.gm/'), 'an app');
	pg_assert_same('', pg_not_found_log_referrer('javascript:alert(1)'), 'a script');
	pg_assert_same('', pg_not_found_log_referrer('/relative/path'), 'no host');
	pg_assert_same('', pg_not_found_log_referrer("https://example.com/a\x01b"), 'a control character');
	pg_assert_same('', pg_not_found_log_referrer("https://example.com/\xff"), 'not UTF-8');
	pg_assert_same('', pg_not_found_log_referrer(array('https://example.com/')), 'not a string');
}

function test_not_found_log_prefetch()
{
	pg_assert_true(pg_not_found_log_is_prefetch(array('HTTP_SEC_PURPOSE' => 'prefetch;prerender')), 'speculation rules');
	pg_assert_true(pg_not_found_log_is_prefetch(array('HTTP_PURPOSE' => 'Prefetch')), 'the older header, any case');
	pg_assert_true(pg_not_found_log_is_prefetch(array('HTTP_X_MOZ' => 'prefetch')), 'Firefox');
	pg_assert_true(!pg_not_found_log_is_prefetch(array('HTTP_SEC_FETCH_MODE' => 'navigate')), 'a click');
	pg_assert_true(!pg_not_found_log_is_prefetch(array('HTTP_PURPOSE' => array('prefetch'))), 'a list is no header');
}

function test_not_found_log_decides_who_is_logged()
{
	pg_assert_same('visitor', pg_not_found_log_decide(not_found_log_test_request()), 'a person');
	pg_assert_same('visitor', pg_not_found_log_decide(not_found_log_test_request(array('method' => 'head'))), 'HEAD, as for the patterns');
	pg_assert_same('', pg_not_found_log_decide(not_found_log_test_request(array('method' => 'POST'))), 'a form posted to a missing address');
	pg_assert_same('', pg_not_found_log_decide(not_found_log_test_request(array('rendering' => true))), 'a page drawn inside another request');
	pg_assert_same('', pg_not_found_log_decide(not_found_log_test_request(array('name' => ''))), 'no name a short link can have');
	pg_assert_same('', pg_not_found_log_decide(not_found_log_test_request(array('live_page' => true))), 'a record named in the query string of a page that is there');
	pg_assert_same('visitor', pg_not_found_log_decide(not_found_log_test_request(array('live_page' => true, 'name' => 'magaza/eski-urun'))), 'an item under a page that is there');
	pg_assert_same('', pg_not_found_log_decide(not_found_log_test_request(array('prefetch' => true))), 'a prefetch');
	pg_assert_same('', pg_not_found_log_decide(not_found_log_test_request(array('waf_rule' => 'sqli-1'))), 'a request the firewall logged');
	pg_assert_same('', pg_not_found_log_decide(not_found_log_test_request(array('automated' => true))), 'a bot');
	pg_assert_same('crawler', pg_not_found_log_decide(not_found_log_test_request(array('automated' => true, 'verified_crawler' => true))), 'a crawler the firewall verified');
	pg_assert_same('visitor', pg_not_found_log_decide(not_found_log_test_request(array('verified_crawler' => true))), 'verified means nothing for a person');
	pg_assert_same('visitor', pg_not_found_log_decide(array('name' => 'x')), 'missing keys are the plain request');
}

// /blog/live-post/ answers 404 where /blog/live-post answers: the stored form
// drops the /, and a short link under it would take the live post over.
function test_not_found_log_leaves_out_a_slash_variant_of_a_live_address()
{
	pg_assert_same('', pg_not_found_log_decide(not_found_log_test_request(array('name' => 'blog/canli-yazi', 'reshaped' => true, 'live_page' => true))), 'a page took part of the address');
	pg_assert_same('', pg_not_found_log_decide(not_found_log_test_request(array('name' => 'sepet', 'reshaped' => true, 'page_answers' => true))), 'a page has the name');
	pg_assert_same('visitor', pg_not_found_log_decide(not_found_log_test_request(array('name' => 'eski-sayfa', 'reshaped' => true))), 'an old address with a / at the end that nothing answers');
	pg_assert_same('visitor', pg_not_found_log_decide(not_found_log_test_request(array('name' => 'magaza/eski-urun', 'live_page' => true))), 'an item under a live page, asked as it is stored');
	pg_assert_same('visitor', pg_not_found_log_decide(not_found_log_test_request(array('page_answers' => true))), 'page_answers counts only for a reshaped name');
}

function test_not_found_log_ai_fetcher_is_no_search_engine()
{
	if (!function_exists('waf_ai_bots')) {
		return;
	}

	pg_assert_true(pg_not_found_log_ai_fetcher('Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ChatGPT-User/1.0; +https://openai.com/bot)'), 'ChatGPT-User fetches for a person');
	pg_assert_true(pg_not_found_log_ai_fetcher('Mozilla/5.0 (compatible; Claude-User/1.0; +Claude-User@anthropic.com)'), 'Claude-User too');
	pg_assert_true(!pg_not_found_log_ai_fetcher('Mozilla/5.0 (compatible; OAI-SearchBot/1.0; +https://openai.com/searchbot)'), 'an AI search crawler is a crawler');
	pg_assert_true(!pg_not_found_log_ai_fetcher('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'), 'Googlebot');
	pg_assert_true(!pg_not_found_log_ai_fetcher('Mozilla/5.0 (X11; Linux x86_64) Chrome/130.0'), 'a person');
}

function test_not_found_log_where()
{
	not_found_log_test_db_stub();

	pg_assert_same("last_seen >= '1000'", pg_not_found_log_where(array('since' => 1000)), 'the retention period always');
	pg_assert_same("last_seen >= '1000' AND hit_count > crawler_hits", pg_not_found_log_where(array('since' => 1000, 'who' => 'visitors')), 'visitors');
	pg_assert_same("last_seen >= '1000' AND crawler_hits > 0", pg_not_found_log_where(array('since' => 1000, 'who' => 'crawlers')), 'crawlers');
	pg_assert_same("last_seen >= '0'", pg_not_found_log_where(array('who' => "x' OR 1=1")), 'an unknown filter is no filter');
	pg_assert_same("last_seen >= '0' AND path LIKE '%100\\\\%\\\\_x%'", pg_not_found_log_where(array('search' => ' 100%_x ')), 'a search for % and _ as text');
	pg_assert_same("last_seen >= '0' AND path LIKE '%a\\'b%'", pg_not_found_log_where(array('search' => "a'b")), 'a quote is escaped');
	pg_assert_same("last_seen >= '0'", pg_not_found_log_where(array('search' => array('x'))), 'a list is no search');
	pg_assert_same(191 + strlen("last_seen >= '0' AND path LIKE '%%'"), strlen(pg_not_found_log_where(array('search' => str_repeat('a', 300)))), 'a search no longer than the column');
	pg_assert_same(1000 - (90 * 86400), pg_not_found_log_since(1000), 'ninety days');
}

function test_not_found_log_redirect_url_fills_the_short_link_screen()
{
	pg_assert_same('add_short_link.php?name=eski-sayfa&redirect_code=301', pg_not_found_log_redirect_url('eski-sayfa'), 'name and answer');
	pg_assert_same('add_short_link.php?name=blog%2F%C3%BCr%C3%BCn_a%26b&redirect_code=301', pg_not_found_log_redirect_url('blog/ürün_a&b'), 'encoded, & inside the name stays the name');
	pg_assert_same('add_short_link.php?name=x&redirect_code=301&send_to=%2Fpinegrap%2Fview_not_found_log.php%3Fq%3D%2Fwp-', pg_not_found_log_redirect_url('x', '/pinegrap/view_not_found_log.php?q=/wp-'), 'the filtered log rides along as send_to');
	pg_assert_same('add_short_link.php?name=x&redirect_code=301', pg_not_found_log_redirect_url('x', ''), 'no send_to when none is given');
}

function test_not_found_log_short_link_notice_goes_where_the_save_lands()
{
	pg_assert_same('view_not_found_log', pg_short_link_return_form('/pinegrap/view_not_found_log.php?who=crawlers&q=%2Fwp-'), 'back to the log: its notices');
	pg_assert_same('view_not_found_log', pg_short_link_return_form('/pinegrap/view_not_found_log.php'), 'the bare log too');
	pg_assert_same('view_short_links', pg_short_link_return_form('/pinegrap/view_short_links.php'), 'the short link list');
	pg_assert_same('view_short_links', pg_short_link_return_form(''), 'no send_to');
	pg_assert_same('view_short_links', pg_short_link_return_form('/pinegrap/x.php?send_to=view_not_found_log.php'), 'only the path counts');
}

function test_not_found_log_takes_its_turn_at_every_404_that_tries_the_patterns()
{
	$core = not_found_log_test_source('includes/fn/core.php');

	pg_assert_same(2, substr_count($core, "pg_short_link_not_found();\n        pg_not_found_log_mark();"), 'output_error() and error() log after the patterns had their chance');
	pg_assert_contains("pg_short_link_not_found();\n        pg_not_found_log_mark();", not_found_log_test_source('includes/fn/widgets.php'), 'and pg_sw_item_missing()');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/not_found_log.php');", not_found_log_test_source('functions.php'), 'loaded with the other modules');

	$job = not_found_log_test_source('job.php');

	pg_assert_contains('pg_not_found_log_sweep_due() && pg_not_found_log_sweep_due()', str_replace("function_exists('pg_not_found_log_sweep_due')", 'pg_not_found_log_sweep_due()', $job), 'the general job sweeps it daily');
	pg_assert_contains("upgrade_2026_11_0_not_found_log();          // 11.0.30", not_found_log_test_source('includes/migrations/2026.11.0.php'), 'the step is called');
	pg_assert_contains("'not_found_log',", not_found_log_test_source('install/index.php'), 'a reinstall drops the table with the others');
}

function test_not_found_log_waits_for_the_upgrade()
{
	pg_assert_true(!pg_not_found_log_ready(), 'no table without a connection');
	pg_assert_same(false, pg_not_found_log_sweep_due(), 'nothing to sweep');
	pg_assert_same(0, pg_not_found_log_sweep(), 'and nothing removed');
}

function test_not_found_log_screen_and_the_short_link_screen_it_opens()
{
	$screen = not_found_log_test_source('view_not_found_log.php');

	pg_assert_contains("validate_area_access(\$user, 'manager');", $screen, 'managers and up, as the short link screens');
	pg_assert_contains('validate_token_field();', $screen, 'delete and clear check the token');
	pg_assert_contains('pg_not_found_log_since()', $screen, 'the list shows the retention period only');
	pg_assert_contains('pg_not_found_log_redirect_url($path, $log_url)', $screen, 'each row opens the short link screen, coming back to the filtered log');
	pg_assert_contains("'&send_to=' . urlencode(\$log_url)", $screen, 'Edit comes back to the filtered log');
	pg_assert_contains('<form id="pg-404-delete-form" method="post" action="\' . h($log_url) . \'"', $screen, 'removing an address posts the filter along');
	pg_assert_contains('go($log_url);', $screen, 'and lands on the filtered log');
	pg_assert_contains('<form id="pg-404-clear-form" method="post" action="view_not_found_log.php"', $screen, 'Clear Log keeps the bare address');
	pg_assert_true(preg_match('#<tr[^>]*>(?:(?!</tr>).)*<form#s', $screen) !== 1, 'no form inside a table row');

	$add = not_found_log_test_source('add_short_link.php');

	pg_assert_contains("\$liveform->assign_field_value('name', pg_short_link_name_from_address(\$_GET['name']));", $add, 'the name is read as a pasted address is');
	pg_assert_contains("in_array((int) \$_GET['redirect_code'], pg_short_link_answer_codes(), true)", $add, 'only an answer the screen offers');
	pg_assert_contains("(\$liveform->get_field_value('name') == '')", $add, 'a form already filled in keeps its values');

	foreach (array('add_short_link.php', 'edit_short_link.php') as $file) {
		$source = not_found_log_test_source($file);

		pg_assert_contains("'cancel'=>array('enable'=>'true','url'=>pg_send_to_url('view_short_links.php'))", $source, $file . ': the arrow goes where Save goes');
		pg_assert_contains('<input type="hidden" name="send_to" value="\' . h(pg_send_to_url(\'\')) . \'">', $source, $file . ': send_to rides the POST');
		pg_assert_contains("new liveform(pg_short_link_return_form(pg_send_to_url('')))", $source, $file . ': the notice waits on the screen it lands on');
		pg_assert_true(strpos($source, "SOFTWARE_DIRECTORY . '/view_short_links.php');") === false, $file . ': no save lands on the bare list');
		pg_assert_contains("header('Location: ' . URL_SCHEME . HOSTNAME . pg_send_to_url(PATH . SOFTWARE_DIRECTORY . '/view_short_links.php'));", $source, $file . ': Save goes to send_to first');
		pg_assert_true(strpos($source, "'?id=' . \$liveform->get_field_value('id'));") === false, $file . ': a refused save keeps send_to');
	}
}
