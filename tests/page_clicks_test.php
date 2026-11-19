<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the click and scroll counter (includes/fn/page_clicks.php): the
 * day signature, a beacon's refusals, the fold of an address and a label,
 * the rows of one beacon, the bound's "other" row, the upsert, the figures
 * and the call sites (the migration, page_view.php, job.php). The runner has
 * no connection: the table reads as missing and nothing is written, so the
 * call sites are read from the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_clicks.php');

function page_clicks_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function page_clicks_test_beacon(array $beacon)
{
	return json_encode($beacon);
}

function test_page_clicks_signature_takes_yesterday_and_refuses_older()
{
	$key = 'test-key';
	$now = strtotime('2026-11-16 10:00:00');
	$today = pg_page_clicks_sign(7, $key, '2026-11-16');
	$yesterday = pg_page_clicks_sign(7, $key, '2026-11-15');
	$older = pg_page_clicks_sign(7, $key, '2026-11-14');

	pg_assert_same(hash_hmac('sha256', 'page-clicks:7|2026-11-16', $key), $today, 'the page and the day, over the site key');
	pg_assert_same('', pg_page_clicks_sign(7, '', '2026-11-16'), 'no key, no signature');
	pg_assert_same('', pg_page_clicks_sign(0, $key, '2026-11-16'), 'no page, no signature');
	pg_assert_same('', pg_page_clicks_sign(7, $key, 'today'), 'a day in another shape');
	pg_assert_true(pg_vitals_sign(7, $key, '2026-11-16') !== $today, 'the speed beacon\'s signature is another one');

	pg_assert_same(7, pg_page_clicks_verify('7', $today, $key, $now), 'today\'s signature');
	pg_assert_same(7, pg_page_clicks_verify('7', $yesterday, $key, $now), 'yesterday\'s: a page drawn before midnight or kept by the cache');
	pg_assert_same(0, pg_page_clicks_verify('7', $older, $key, $now), 'an older one is refused');
	pg_assert_same(0, pg_page_clicks_verify('8', $today, $key, $now), 'another page\'s signature');
	pg_assert_same(0, pg_page_clicks_verify('7', '', $key, $now), 'no signature');
	pg_assert_same(0, pg_page_clicks_verify(array('7'), $today, $key, $now), 'an array in place of the page');
	pg_assert_same(0, pg_page_clicks_verify('7x', $today, $key, $now), 'a page id that is no number');
}

function test_page_clicks_beacon_refusals_drop_the_whole_beacon()
{
	$element = array('main', 'link', '/shop', 'Shop');
	$good = array('d' => 'mobile', 't' => 4, 'e' => array($element));

	pg_assert_true(pg_page_clicks_parse(page_clicks_test_beacon($good)) !== null, 'a well-formed beacon');
	pg_assert_same(null, pg_page_clicks_parse(array('d' => 'mobile')), 'an array in place of the text');
	pg_assert_same(null, pg_page_clicks_parse('{not json'), 'no JSON');
	pg_assert_same(null, pg_page_clicks_parse(str_repeat('x', 9000)), 'too long');
	pg_assert_same(null, pg_page_clicks_parse(page_clicks_test_beacon(array('d' => 'watch') + $good)), 'an unknown device');
	pg_assert_same(null, pg_page_clicks_parse(page_clicks_test_beacon(array('t' => 11) + $good)), 'a tenth above 10');
	pg_assert_same(null, pg_page_clicks_parse(page_clicks_test_beacon(array('t' => -1) + $good)), 'a tenth below 0');
	pg_assert_same(null, pg_page_clicks_parse(page_clicks_test_beacon(array('t' => '4') + $good)), 'a tenth as text');
	pg_assert_same(null, pg_page_clicks_parse(page_clicks_test_beacon(array('e' => 'x') + $good)), 'elements that are no list');
	pg_assert_same(null, pg_page_clicks_parse(page_clicks_test_beacon(array('e' => array_fill(0, 21, $element)) + $good)), 'more than 20 elements');
	pg_assert_same(null, pg_page_clicks_parse(page_clicks_test_beacon(array('e' => array(array('main', 'link', '/shop'))) + $good)), 'an element of three parts');
	pg_assert_same(null, pg_page_clicks_parse(page_clicks_test_beacon(array('e' => array(array('body', 'link', '/shop', 'Shop'))) + $good)), 'an unknown part of the page');
	pg_assert_same(null, pg_page_clicks_parse(page_clicks_test_beacon(array('e' => array(array('main', 'image', '/shop', 'Shop'))) + $good)), 'neither link nor button');
	pg_assert_same(null, pg_page_clicks_parse(page_clicks_test_beacon(array('e' => array(array('main', 'link', 5, 'Shop'))) + $good)), 'an address that is no text');

	$no_tenth = pg_page_clicks_parse(page_clicks_test_beacon(array('d' => 'desktop', 'e' => array())));
	pg_assert_same(array('device' => 'desktop', 'tenth' => null, 'elements' => array()), $no_tenth, 'a view with nothing clicked and no depth measured still counts');
}

function test_page_clicks_fold_of_an_address()
{
	pg_assert_same('/shop/shoes', pg_page_clicks_fold_url('/shop/shoes?colour=red#top'), 'query and fragment go');
	pg_assert_same('/shop', pg_page_clicks_fold_url('https://www.example.com/shop?x=1', 'www.example.com'), 'this site\'s address keeps its path');
	pg_assert_same('/', pg_page_clicks_fold_url('https://www.example.com', 'www.example.com'), 'this site\'s home');
	pg_assert_same('//partner.example.org/offer', pg_page_clicks_fold_url('HTTPS://Partner.Example.org/offer#a', 'www.example.com'), 'another site keeps its host and path');
	pg_assert_same('', pg_page_clicks_fold_url(''), 'a button without an address');
	pg_assert_same('', pg_page_clicks_fold_url('javascript:void(0)'), 'a script address is no address');
	pg_assert_same('', pg_page_clicks_fold_url('shop/shoes'), 'a relative address the browser did not resolve');
	pg_assert_same(255, strlen(pg_page_clicks_fold_url('/' . str_repeat('a-', 200))), 'cut at 255 characters');
}

function test_page_clicks_code_like_path_part_and_tel_address()
{
	pg_assert_same('/unsubscribe/*', pg_page_clicks_fold_url('/unsubscribe/a8F3k29dLq0ZxY7wPm'), 'a code-like part becomes *');
	pg_assert_same('/reset/*/done', pg_page_clicks_fold_url('/reset/9f86d081884c7d659a2feaa0c55ad015/done'), 'a hash in the middle of the path');
	pg_assert_same('/blog/how-to-choose-running-shoes-2026', pg_page_clicks_fold_url('/blog/how-to-choose-running-shoes-2026'), 'a slug with hyphens stays');
	pg_assert_same('/category/accessories', pg_page_clicks_fold_url('/category/accessories'), 'a long word without digits stays');
	pg_assert_same('/p/1234567890123456789', pg_page_clicks_fold_url('/p/1234567890123456789'), 'digits alone are no code mixed with letters');
	pg_assert_same('/p/abc123', pg_page_clicks_fold_url('/p/abc123'), 'a short mixed part stays');

	pg_assert_same('tel:', pg_page_clicks_fold_url('tel:+905551234567'), 'a phone link keeps its kind only');
	pg_assert_same('mailto:', pg_page_clicks_fold_url('mailto:ayse@example.com?subject=Hi'), 'an e-mail link keeps its kind only');
	pg_assert_same('sms:', pg_page_clicks_fold_url('SMS:+905551234567'), 'an SMS link keeps its kind only');
	pg_assert_same('whatsapp:', pg_page_clicks_fold_url('https://wa.me/905551234567?text=Merhaba'), 'a WhatsApp link keeps its kind only');
	pg_assert_same('whatsapp:', pg_page_clicks_fold_url('whatsapp://send?phone=905551234567'), 'the WhatsApp scheme too');
	pg_assert_same('telegram:', pg_page_clicks_fold_url('https://t.me/someone'), 'a Telegram link keeps its kind only');
}

function test_page_clicks_fold_of_a_label()
{
	pg_assert_same('Add to cart', pg_page_clicks_fold_label("  Add \n\t to   cart "), 'white space folded');
	pg_assert_same(60, mb_strlen(pg_page_clicks_fold_label(str_repeat('Ü', 80)), 'UTF-8'), 'cut at 60 characters');
	pg_assert_same('', pg_page_clicks_fold_label(array('x')), 'no text');
	pg_assert_same('Call us', pg_page_clicks_fold_label("Call\x07 us"), 'control characters go');
}

function test_page_clicks_label_with_email_or_long_number_kept_empty()
{
	pg_assert_same('', pg_page_clicks_fold_label('ayse.yilmaz@example.com'), 'a label shaped like an e-mail');
	pg_assert_same('', pg_page_clicks_fold_label('Write to info@example.com'), 'a label holding an e-mail');
	pg_assert_same('', pg_page_clicks_fold_label('0555 1234567'), 'seven digits in a row');
	pg_assert_same('', pg_page_clicks_fold_label('Order 20261116001'), 'a long order number');
	pg_assert_same('Since 1998, 24/7', pg_page_clicks_fold_label('Since 1998, 24/7'), 'short numbers stay');

	$beacon = pg_page_clicks_parse(page_clicks_test_beacon(array('d' => 'desktop', 'e' => array(array('footer', 'link', 'mailto:info@example.com', 'info@example.com')))));
	pg_assert_same(array(array('footer', 'link', 'mailto:', '')), $beacon['elements'], 'the element is counted by its address alone');
}

function test_page_clicks_element_counted_once_per_beacon()
{
	$beacon = pg_page_clicks_parse(page_clicks_test_beacon(array('d' => 'tablet', 't' => 10, 'e' => array(
		array('main', 'button', '', 'Buy now'),
		array('main', 'button', '', "Buy   now"),
		array('main', 'link', '/shop?a=1', 'Shop'),
		array('main', 'link', '/shop?a=2', 'Shop'),
		array('header', 'link', '/shop', 'Shop'),
		array('main', 'link', '', ''),
	))));

	pg_assert_same(array(
		array('main', 'button', '', 'Buy now'),
		array('main', 'link', '/shop', 'Shop'),
		array('header', 'link', '/shop', 'Shop'),
	), $beacon['elements'], 'an element folded alike counts once; one with neither address nor label is left out');
}

function test_page_clicks_rows_of_a_beacon_and_the_tenth()
{
	$beacon = array('device' => 'mobile', 'tenth' => 7, 'elements' => array(array('nav', 'link', '/about', 'About')));
	$rows = pg_page_clicks_rows('2026-11-16', 12, $beacon);

	pg_assert_same(3, count($rows), 'the view, the tenth and one element');
	pg_assert_same('view', $rows[0]['kind'], 'the views row first');
	pg_assert_same(pg_page_clicks_key('2026-11-16', 12, 'mobile', 'view', 0, '', '', '', ''), $rows[0]['id'], 'the views row\'s key');
	pg_assert_same('depth', $rows[1]['kind'], 'the tenth\'s row');
	pg_assert_same(7, $rows[1]['depth'], 'the deepest tenth reached');
	pg_assert_same(array('element', 'nav', 'link', '/about', 'About'), array($rows[2]['kind'], $rows[2]['area'], $rows[2]['role'], $rows[2]['url'], $rows[2]['label']), 'the element row keeps its folded parts');
	pg_assert_same(40, strlen($rows[2]['id']), 'a sha1 key');

	$zero = pg_page_clicks_rows('2026-11-16', 12, array('tenth' => 0) + $beacon);
	pg_assert_same(0, $zero[1]['depth'], 'tenth 0 is a measurement too');

	$none = pg_page_clicks_rows('2026-11-16', 12, array('tenth' => null) + $beacon);
	pg_assert_same(array('view', 'element'), array_column($none, 'kind'), 'no tenth measured, no tenth row');

	pg_assert_true(pg_page_clicks_key('2026-11-16', 12, 'mobile', 'element', 0, 'a', 'b|c', '', '') !== pg_page_clicks_key('2026-11-16', 12, 'mobile', 'element', 0, 'a|b', 'c', '', ''), 'parts holding the separator do not meet');
}

function test_page_clicks_bound_other_row()
{
	pg_assert_same(200, PG_PAGE_CLICKS_ELEMENTS, 'the bound');
	pg_assert_same('element', pg_page_clicks_route(0, false), 'a new element under the bound');
	pg_assert_same('element', pg_page_clicks_route(199, false), 'the 200th');
	pg_assert_same('other', pg_page_clicks_route(200, false), 'the 201st goes to "other"');
	pg_assert_same('element', pg_page_clicks_route(250, true), 'an element already counted keeps counting');

	$other = pg_page_clicks_other_row('2026-11-16', 12, 'desktop');
	pg_assert_same('other', $other['kind'], 'the "other" row');
	pg_assert_same(array('', '', '', ''), array($other['area'], $other['role'], $other['url'], $other['label']), 'it keeps no text');
	pg_assert_same(pg_page_clicks_key('2026-11-16', 12, 'desktop', 'other', 0, '', '', '', ''), $other['id'], 'one per page, day and device');
}

function test_page_clicks_upsert_sql()
{
	$sql = pg_page_clicks_upsert_sql();

	pg_assert_contains('INSERT INTO page_click_daily', $sql, 'the table');
	pg_assert_contains('ON DUPLICATE KEY UPDATE hits = hits + 1', $sql, 'a repeat raises the counter');
	pg_assert_same(10, substr_count($sql, '?'), 'every value bound');
}

function test_page_clicks_reach_and_shares()
{
	// 10 measured views: 2 reached tenth 3, 5 tenth 6, 3 the bottom.
	$reach = pg_page_clicks_reach(array(3 => 2, 6 => 5, 10 => 3));

	pg_assert_same(1.0, $reach[0], 'every view reaches the top');
	pg_assert_same(1.0, $reach[3], 'every view reaches tenth 3');
	pg_assert_same(0.8, $reach[4], 'past tenth 3, the eight deeper views');
	pg_assert_same(0.8, $reach[6], 'tenth 6');
	pg_assert_same(0.3, $reach[7], 'past tenth 6');
	pg_assert_same(0.3, $reach[10], 'the bottom');
	pg_assert_same(6, pg_page_clicks_half_tenth($reach), 'the deepest tenth half the views reach');
	pg_assert_same(array_fill(0, 11, null), pg_page_clicks_reach(array()), 'nothing measured');
	pg_assert_same(null, pg_page_clicks_half_tenth(pg_page_clicks_reach(array())), 'no half without measurements');

	pg_assert_same(0.25, pg_page_clicks_share(5, 20), 'clicks over views');
	pg_assert_same(null, pg_page_clicks_share(5, 0), 'no views, no share');
	pg_assert_same(1.0, pg_page_clicks_share(30, 20), 'never past 100 percent');
}

function test_page_clicks_where_sql()
{
	pg_assert_same("day BETWEEN '2026-10-01' AND '2026-10-31'", pg_page_clicks_where_sql('2026-10-01', '2026-10-31'), 'a period');
	pg_assert_same("day BETWEEN '2026-10-01' AND '2026-10-31' AND device = 'mobile'", pg_page_clicks_where_sql('2026-10-01', '2026-10-31', 'mobile'), 'one device');
	pg_assert_same('', pg_page_clicks_where_sql("2026-10-01' OR 1=1 --", '2026-10-31'), 'a day that is no date');
}

function test_page_clicks_keep_from_is_400_days()
{
	pg_assert_same(400, PG_PAGE_CLICKS_DAYS, '400 days');
	pg_assert_same('2025-10-12', pg_page_clicks_keep_from(strtotime('2026-11-16 12:00:00')), 'the first day kept');
}

function test_page_clicks_not_ready_without_the_table()
{
	pg_assert_false(pg_page_clicks_ready(), 'no table without a connection');
	pg_assert_false(pg_page_clicks_record(5, array('device' => 'desktop', 'tenth' => 2, 'elements' => array())), 'nothing written before the step');
	pg_assert_same(array(), pg_page_clicks_pages('2026-10-01', '2026-10-31'), 'no figures before the step');
	pg_assert_same(0, pg_page_clicks_sweep(), 'nothing swept before the step');
}

function test_page_clicks_call_sites()
{
	$migration = page_clicks_test_source('includes/migrations/2026.11.0.php');
	$page_view = page_clicks_test_source('page_view.php');
	$job = page_clicks_test_source('job.php');

	pg_assert_contains('upgrade_2026_11_0_page_click_daily();', $migration, 'the step is called');
	pg_assert_contains("install_create_table('page_click_daily'", $migration, 'the table is made idempotently');
	pg_assert_contains("'page_click_daily',", page_clicks_test_source('install/index.php'), 'listed in get_tables()');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_clicks.php');", page_clicks_test_source('functions.php'), 'the module is loaded');
	pg_assert_contains('pg_page_clicks_sweep()', $job, 'the general job sweeps');

	$clicks = strpos($page_view, "isset(\$_POST['c'])");
	pg_assert_true($clicks !== false, 'page_view.php takes the clicks part');
	pg_assert_true($clicks < strpos($page_view, "isset(\$_POST['vp'])"), 'beside and before the speed part of the same beacon');
	pg_assert_true($clicks < strpos($page_view, '$page_view_payload ='), 'before the page-view payload');
}

function test_consent_statistics_not_refused_is_the_anonymous_counts_check()
{
	pg_assert_true(pg_consent_statistics_not_refused_by(false, array('analytics' => false)), 'the notice off: counted');
	pg_assert_true(pg_consent_statistics_not_refused_by(true, pg_consent_parse('1.a1.m0')), 'statistics allowed (a1)');
	pg_assert_false(pg_consent_statistics_not_refused_by(true, pg_consent_parse('1.a0.m1')), 'statistics refused (a0), closing the notice writes it too');
	pg_assert_true(pg_consent_statistics_not_refused_by(true, pg_consent_parse('')), 'not answered yet');
	pg_assert_true(pg_consent_statistics_not_refused_by(true, pg_consent_parse('9.zz')), 'a cookie that cannot be read counts as not answered');
	pg_assert_true(pg_consent_statistics_not_refused_by(true, pg_consent_parse('1.m0')), 'statistics not named in the answer');

	$module = page_clicks_test_source('includes/fn/page_clicks.php');
	$allowed = substr($module, strpos($module, 'function pg_page_clicks_allowed('), 600);
	pg_assert_contains('pg_consent_statistics_not_refused()', $allowed, 'the counter asks the one check');
	pg_assert_contains('USER_LOGGED_IN', $allowed, 'a signed-in view sends nothing');
	pg_assert_contains('pg_request_is_automated()', $allowed, 'no crawler or script');
	pg_assert_true(strpos(page_clicks_test_source('includes/fn/consent.php'), "return !empty(\$choices[\$category]);") !== false, 'pg_consent_allows() still asks for a yes');
}
