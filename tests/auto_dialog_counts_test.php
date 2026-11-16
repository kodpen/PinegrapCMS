<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pop-up counts (includes/fn/auto_dialog_counts.php): the day
 * signature and the beacon's shape, the upsert, the 30-day period and its
 * figures, the rows a page prints, the shared files that call it (the
 * migration, page_view.php, job.php, the screens) and the beacon in both
 * scripts. The runner has no connection: the table reads as missing and
 * nothing is written, so the call sites are read from the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/auto_dialog_counts.php');

function auto_dialog_counts_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_auto_dialog_count_signature_is_day_bound()
{
	$key = 'test-key';
	$now = strtotime('2026-11-16 10:00:00');
	$today = pg_auto_dialog_count_sign(7, $key, '2026-11-16');
	$yesterday = pg_auto_dialog_count_sign(7, $key, '2026-11-15');
	$older = pg_auto_dialog_count_sign(7, $key, '2026-11-14');

	pg_assert_same(hash_hmac('sha256', 'auto-dialog:7|2026-11-16', $key), $today, 'the id and the day, over the site key');
	pg_assert_same('', pg_auto_dialog_count_sign(7, '', '2026-11-16'), 'no key, no signature');
	pg_assert_same('', pg_auto_dialog_count_sign(0, $key, '2026-11-16'), 'no id, no signature');
	pg_assert_same('', pg_auto_dialog_count_sign(7, $key, 'today'), 'a day in another shape');

	pg_assert_same(array(7, 'shown'), pg_auto_dialog_count_parse(array('ad' => '7', 'ae' => 'shown', 'as' => $today), $key, $now), 'today\'s signature');
	pg_assert_same(array(7, 'closed'), pg_auto_dialog_count_parse(array('ad' => '7', 'ae' => 'closed', 'as' => $yesterday), $key, $now), 'yesterday\'s: a page drawn before midnight or kept by the cache');
	pg_assert_same(null, pg_auto_dialog_count_parse(array('ad' => '7', 'ae' => 'shown', 'as' => $older), $key, $now), 'an older one is not replayed for ever');
	pg_assert_same(null, pg_auto_dialog_count_parse(array('ad' => '8', 'ae' => 'shown', 'as' => $today), $key, $now), 'another pop-up\'s signature');
}

function test_auto_dialog_count_drops_a_post_in_the_wrong_shape()
{
	$key = 'test-key';
	$now = strtotime('2026-11-16 10:00:00');
	$sign = pg_auto_dialog_count_sign(7, $key, '2026-11-16');

	pg_assert_same(array('shown', 'closed', 'taken'), pg_auto_dialog_count_events(), 'three events, each its column');

	foreach (array(
		'an unknown event' => array('ad' => '7', 'ae' => 'clicked', 'as' => $sign),
		'an event in capitals' => array('ad' => '7', 'ae' => 'Shown', 'as' => $sign),
		'an id with a sign' => array('ad' => '-7', 'ae' => 'shown', 'as' => $sign),
		'an id with letters' => array('ad' => '7a', 'ae' => 'shown', 'as' => $sign),
		'an id of zero' => array('ad' => '0', 'ae' => 'shown', 'as' => $sign),
		'an id past ten digits' => array('ad' => '12345678901', 'ae' => 'shown', 'as' => $sign),
		'a list' => array('ad' => array('7'), 'ae' => 'shown', 'as' => $sign),
		'no signature' => array('ad' => '7', 'ae' => 'shown'),
		'an empty post' => array(),
	) as $label => $post) {
		pg_assert_same(null, pg_auto_dialog_count_parse($post, $key, $now), $label);
	}
}

function test_auto_dialog_count_upsert_raises_one_column()
{
	foreach (pg_auto_dialog_count_events() as $event) {
		$sql = pg_auto_dialog_count_upsert_sql($event);

		pg_assert_contains('INSERT INTO auto_dialog_daily (auto_dialog_id, day, ' . $event . ') VALUES (?, ?, 1)', $sql, $event . ': a new day row starts at one');
		pg_assert_contains('ON DUPLICATE KEY UPDATE ' . $event . ' = ' . $event . ' + 1', $sql, $event . ': an existing one is raised');
	}

	pg_assert_same('', pg_auto_dialog_count_upsert_sql('views'), 'no other column');
	pg_assert_same('', pg_auto_dialog_count_upsert_sql('shown = 99, taken'), 'nothing written into the statement');
}

function test_auto_dialog_count_period_and_figures()
{
	$now = strtotime('2026-11-16 10:00:00');

	pg_assert_same('2026-10-18', pg_auto_dialog_count_from(30, $now), 'thirty days, today included');
	pg_assert_same('2026-11-16', pg_auto_dialog_count_from(1, $now), 'one day is today');
	pg_assert_same('2025-10-12', pg_auto_dialog_count_keep_from($now), 'four hundred days kept');

	$figures = pg_auto_dialog_count_rows(array(
		array('auto_dialog_id' => '2', 'shown' => '40', 'closed' => '31', 'taken' => '0'),
		array('auto_dialog_id' => '5', 'shown' => '3', 'closed' => null, 'taken' => '1'),
		array('auto_dialog_id' => '0', 'shown' => '9'),
		'not a row',
	));

	pg_assert_same(array(2 => array('shown' => 40, 'closed' => 31, 'taken' => 0), 5 => array('shown' => 3, 'closed' => 0, 'taken' => 1)), $figures, 'the summed rows as whole numbers by id');
	pg_assert_same(array('shown' => 0, 'closed' => 0, 'taken' => 0), pg_auto_dialog_count_of($figures, 9), 'a pop-up with no row reads zeros');
	pg_assert_same(array(), pg_auto_dialog_count_figures(), 'no table: no figures');

	$source = auto_dialog_counts_test_source('includes/fn/auto_dialog_counts.php');
	pg_assert_contains('SUM(shown) AS shown, SUM(closed) AS closed, SUM(taken) AS taken', $source, 'the rows are summed');
	pg_assert_contains('" GROUP BY auto_dialog_id"', $source, 'in one grouped query');
}

function test_auto_dialog_count_rows_a_page_prints()
{
	$rows = array(array('id' => 2, 'url' => '/a'), array('id' => 3, 'url' => '/b'));
	$signed = pg_auto_dialog_count_signed($rows, 'test-key', '2026-11-16');

	pg_assert_same(pg_auto_dialog_count_sign(2, 'test-key', '2026-11-16'), $signed[0]['sign'], 'each row carries its day signature');
	pg_assert_same(array(2, 3), array_column($signed, 'id'), 'every row stays');
	pg_assert_same($rows, pg_auto_dialog_count_signed($rows, '', '2026-11-16'), 'no key: no signature, no beacon');

	$page = auto_dialog_counts_test_source('get_page_content.php');
	pg_assert_contains("pg_auto_dialog_count_signed(\$auto_dialogs_page, pg_auto_dialog_counts_ready() && defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '', date('Y-m-d'))", $page, 'the page signs its rows only with the table');
	pg_assert_contains("count_url: ' . encode_json(PATH . SOFTWARE_DIRECTORY . '/page_view.php')", $page, 'and names the endpoint');
	pg_assert_false(strpos(substr($page, strpos($page, 'preview_auto_dialog'), strpos($page, 'preview: true') - strpos($page, 'preview_auto_dialog')), 'pg_auto_dialog_count_signed') !== false, 'a preview prints no signature');
}

function test_auto_dialog_count_page_view_branch_is_small_and_guarded()
{
	$view = auto_dialog_counts_test_source('page_view.php');
	$start = strpos($view, "if (isset(\$_POST['ad'])) {");
	$branch = substr($view, $start, strpos($view, "exit();\n}", $start) - $start);

	pg_assert_true($start !== false, 'page_view.php has the branch');
	pg_assert_true($start < strpos($view, "if (isset(\$_POST['vp'])) {"), 'before the other payloads');
	pg_assert_contains('!pg_auto_dialog_count_rate_exceeded() && pg_auto_dialog_count_allowed()', $branch, 'the rate limit, then who may count');
	pg_assert_contains('pg_auto_dialog_count_parse($_POST,', $branch, 'the module reads the post');
	pg_assert_contains('pg_auto_dialog_count($auto_dialog_event[0], $auto_dialog_event[1]);', $branch, 'and writes it');
	pg_assert_true(substr_count($branch, "\n") < 12, 'the branch stays small');

	$module = auto_dialog_counts_test_source('includes/fn/auto_dialog_counts.php');
	$allowed = substr($module, strpos($module, 'function pg_auto_dialog_count_allowed('), 900);
	pg_assert_contains('pg_request_is_automated()', $allowed, 'no crawler or script');
	pg_assert_contains('pg_site_search_user_countable(', $allowed, 'staff by the site search rule, no second copy');
	pg_assert_contains("pg_consent_allows('analytics')", $allowed, 'statistics allowed, or the notice off');
	pg_assert_contains("waf_rate_exceeded(\$ip, 'popup', PG_AUTO_DIALOG_COUNT_RATE_LIMIT, 60)", $module, 'the vitals beacon\'s rate limit, its own bucket');

	$count = substr($module, strpos($module, 'function pg_auto_dialog_count('), 700);
	pg_assert_contains('!pg_auto_dialog_counts_ready()', $count, 'a site without the table skips silently');
	pg_assert_contains('SELECT id FROM auto_dialogs WHERE id', $count, 'an id with no pop-up is not counted');
}

function test_auto_dialog_count_table_sweep_and_delete()
{
	$migration = auto_dialog_counts_test_source('includes/migrations/2026.11.0.php');
	pg_assert_contains('upgrade_2026_11_0_auto_dialog_daily();       // 11.0.223', $migration, 'the step is called');
	pg_assert_contains('PRIMARY KEY (auto_dialog_id, day)', $migration, 'one row per pop-up and day');
	pg_assert_contains('KEY idx_day (day)', $migration, 'the day index of the sum and the sweep');
	pg_assert_contains('auto_dialog_id INT UNSIGNED NOT NULL', $migration, 'the id as auto_dialogs.id');
	pg_assert_contains("'auto_dialog_daily',", auto_dialog_counts_test_source('install/index.php'), 'listed in get_tables()');
	pg_assert_contains("includes/fn/auto_dialog_counts.php'", auto_dialog_counts_test_source('functions.php'), 'loaded with the functions');

	$job = auto_dialog_counts_test_source('job.php');
	pg_assert_contains("function_exists('pg_auto_dialog_count_sweep_due') && pg_auto_dialog_count_sweep_due()", $job, 'the general job sweeps once a day');
	pg_assert_contains('DELETE FROM auto_dialog_daily WHERE day < ? LIMIT 5000', auto_dialog_counts_test_source('includes/fn/auto_dialog_counts.php'), 'in chunks');

	pg_assert_contains('pg_auto_dialog_count_delete($auto_dialog_id);', auto_dialog_counts_test_source('includes/fn/auto_dialogs.php'), 'a deleted pop-up\'s rows go with it');
}

function test_auto_dialog_count_screens_show_the_period()
{
	$list = auto_dialog_counts_test_source('view_auto_dialogs.php');
	pg_assert_same(1, substr_count($list, 'pg_auto_dialog_count_figures('), 'the list sums once for every pop-up');
	pg_assert_true(strpos($list, 'pg_auto_dialog_count_figures(') < strpos($list, 'foreach ($auto_dialogs as $auto_dialog)'), 'before the rows, no query per row');

	pg_assert_contains("'shown' => array('heading' => lang('Shown (30 days)'), 'kind' => 'number')", $list, 'the list\'s shown column');
	pg_assert_contains('pg_auto_dialog_count_total($auto_dialog_figures)', $list, 'the figure row sums all three');

	pg_assert_contains('pg_auto_dialog_count_of(pg_auto_dialog_count_figures($id), $id)', auto_dialog_counts_test_source('edit_auto_dialog.php'), 'the edit screen reads its own');
	pg_assert_contains('data-pg-auto-dialog-count="<?=$count_field?>"', auto_dialog_counts_test_source('includes/templates/edit_auto_dialog.php'), 'and shows them in its status column');
}

function test_auto_dialog_count_beacon_in_both_scripts()
{
	$src = auto_dialog_counts_test_source('frontend.src.js');
	$count = substr($src, strpos($src, 'count_auto_dialog: function('), 2000);
	$count = substr($count, 0, strpos($count, "\n    },\n"));

	pg_assert_contains("if (!document.getElementById('pg-cc')) {", $src, 'the notice off: statistics allowed');
	pg_assert_contains("/(^|\\.)a1(\\.|$)/.test(software.read_cookie('pg_consent'))", $src, 'the notice on: only with statistics allowed');
	pg_assert_contains('!software.statistics_allowed()', $count, 'the beacon asks the named check');
	pg_assert_contains("var key = auto_dialog.id + ':' + event;", $count, 'once per pop-up, event and page load');
	pg_assert_contains('!auto_dialog.sign', $count, 'no signature (a preview): no beacon');
	pg_assert_contains("software.count_auto_dialog(auto_dialog, 'shown');", $src, 'shown when it opens');
	pg_assert_contains("software.count_auto_dialog(auto_dialog, 'closed');", $src, 'closed when it is closed');

	$min = auto_dialog_counts_test_source('frontend.min.js');
	foreach (array('statistics_allowed:function(){return!document.getElementById("pg-cc")||/(^|\\.)a1(\\.|$)/.test(software.read_cookie("pg_consent"))}', 'count_auto_dialog:function(', 'software.count_auto_dialog(a,"shown")', 'software.count_auto_dialog(a,"closed")', 'software.auto_dialog_count_url=t.count_url||""', 'i.append("as",e.sign)') as $needle) {
		pg_assert_contains($needle, $min, 'the served script: ' . $needle);
	}
}
