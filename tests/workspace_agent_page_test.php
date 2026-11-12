<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for live pages an agent makes (includes/workspace/page.php,
 * api_page.php): the file name, what HTML is refused, who a version names,
 * the retention, the versions as read and the routes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_canvas.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_page.php');

function test_workspace_agent_page_file_name()
{
	pg_assert_same('Sales chart.html', ws_page_file_name('Sales chart'));
	pg_assert_same('Sales chart.html', ws_page_file_name('Sales chart.htm'), 'its own extension replaced');
	pg_assert_same('a b.html', ws_page_file_name('a/b'), 'no path');
	pg_assert_same('chart.html', ws_page_file_name('chart.js'), 'always .html');
	pg_assert_same(lang('Page') . '.html', ws_page_file_name(''));
	pg_assert_same(lang('Page') . '.html', ws_page_file_name(array('x')));
	pg_assert_same(125, mb_strlen(ws_page_file_name(str_repeat('ş', 300))), '120 letters and .html');
}

function test_workspace_agent_page_html_refused()
{
	pg_assert_same('', ws_page_html_error('<!DOCTYPE html><p>Hi</p>'));
	pg_assert_same('empty', ws_page_html_error(''));
	pg_assert_same('empty', ws_page_html_error("  \n"));
	pg_assert_same('empty', ws_page_html_error(null));
	pg_assert_same('empty', ws_page_html_error(array('<p>')));
	pg_assert_same('not_utf8', ws_page_html_error("<p>\xff</p>"));
	pg_assert_same('', ws_page_html_error(str_repeat('a', PG_GUARDED_FRAME_MAX_BYTES)), '500 KB is allowed');
	pg_assert_same('too_large', ws_page_html_error(str_repeat('a', PG_GUARDED_FRAME_MAX_BYTES + 1)));

	foreach (array('empty', 'too_large', 'not_utf8') as $error) {
		pg_assert_true(ws_page_html_error_text($error) !== '', $error);
	}
}

function test_workspace_agent_page_version_names_its_agent()
{
	$doc = ws_page_version_doc(array('id' => 7, 'kind' => 'app', 'handle' => 'chartbot'));

	pg_assert_same(array('page' => 1, 'by' => array('id' => 7, 'kind' => 'app', 'handle' => 'chartbot'), 'libraries' => array()), json_decode($doc, true));
	pg_assert_same(array('chartjs'), ws_page_version_libraries(ws_page_version_doc(array('handle' => 'x'), array('chartjs'))));
	pg_assert_same(array(), ws_page_version_libraries('{"libraries":["chartjs","jquery"]}'), 'a name the site does not have reads as none');
	pg_assert_same(array(), ws_page_version_libraries(''));
	pg_assert_same(array('id' => 7, 'kind' => 'app', 'handle' => 'chartbot'), ws_page_version_by($doc));
	pg_assert_same(null, ws_page_version_by(''));
	pg_assert_same(null, ws_page_version_by('{"by":{"handle":""}}'));
	pg_assert_same(null, ws_page_version_by('{"by":"x"}'));
}

function test_workspace_agent_page_keeps_the_first_and_the_newest()
{
	$rows = array();

	for ($no = 1; $no <= 25; $no++) {
		$rows[] = array('version_id' => 100 + $no, 'version_no' => $no);
	}

	// 1 and 7..25 stay: twenty versions.
	pg_assert_same(array(102, 103, 104, 105, 106), ws_page_versions_pruned($rows));
	pg_assert_same(20, ws_page_versions_keep());
	pg_assert_same(array(), ws_page_versions_pruned(array_slice($rows, 0, 20)), 'twenty: nothing goes');
	pg_assert_same(array(102), ws_page_versions_pruned(array_slice($rows, 0, 21)));
}

function test_workspace_agent_page_routes()
{
	$routes = array();

	foreach (ws_page_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$create = $routes['workspace.channels.pages.create'];
	pg_assert_same(array('POST', '/workspace/channels/{id}/pages', 'workspace:write', true, 201), array($create['method'], $create['path'], $create['scope'], $create['dry_run'], $create['status']), 'create');

	$get = $routes['workspace.messages.page.get'];
	pg_assert_same(array('GET', '/workspace/messages/{id}/page', 'workspace:read'), array($get['method'], $get['path'], $get['scope']), 'read');

	$edit = $routes['workspace.messages.page.edit'];
	pg_assert_same(array('PATCH', '/workspace/messages/{id}/page', 'workspace:write', true, array('POST')), array($edit['method'], $edit['path'], $edit['scope'], $edit['dry_run'], $edit['also_accepts']), 'edit');

	foreach (array($create, $edit) as $route) {
		foreach ($route['params'] as $param) {
			if ($param['name'] === 'html') {
				pg_assert_same(PG_GUARDED_FRAME_MAX_BYTES, $param['max_length'], 'the route\'s limit is the frame\'s');
			}
		}
	}

	$read = ws_page_versions_read(array(
		array('version_no' => 1, 'source' => 'agent', 'user_id' => 4, 'size' => 30, 'created_at' => 100, 'doc_json' => ws_page_version_doc(array('id' => 7, 'kind' => 'app', 'handle' => 'chartbot'))),
		array('version_no' => 2, 'source' => 'agent', 'user_id' => 0, 'size' => 31, 'created_at' => 200, 'doc_json' => ''),
	));

	pg_assert_same(array('version' => 1, 'source' => 'agent', 'user_id' => 4, 'agent_id' => 7, 'agent' => 'chartbot', 'size' => 30, 'created_at' => 100), $read[0]);
	pg_assert_same(array(null, null, null), array($read[1]['user_id'], $read[1]['agent_id'], $read[1]['agent']));

	$declared = ws_api_page_returns();
	$present = array('message_id' => 1, 'file_id' => 2, 'name' => '', 'html' => '', 'size' => 0, 'version' => 2, 'versions' => $read);

	$present = array_slice($present, 0, 6, true) + array('libraries' => array()) + array_slice($present, 6, null, true);
	pg_assert_same(array_keys($declared), array_keys($present), 'the page\'s fields declared');
	pg_assert_same(array_keys($declared['versions'][0]), array_keys($read[0]), 'a version\'s fields declared');
	pg_assert_true(in_array('workspace.channels.pages.create', array_column(ws_api_routes(), 'id'), true), 'merged into the workspace routes');
}

function test_workspace_agent_page_html_is_never_an_upload()
{
	// The mark of a page rests on this: a person cannot post an .html file.
	foreach (array('page.html', 'page.htm', 'Page.HTML') as $name) {
		pg_assert_true(ws_upload_refused($name), $name);
	}
}

function test_workspace_agent_page_libraries_are_the_sites_own()
{
	foreach (ws_page_libraries() as $name => $library) {
		pg_assert_true((bool) preg_match('/^[a-z0-9]+$/', $name), $name);
		pg_assert_same(0, strpos($library['path'], 'assets/lib/'), $name . ' is a copy the site ships');
		pg_assert_false(strpos($library['path'], '://') !== false, $name . ' is no address');
		pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/' . $library['path']), $name . ' is there');

		$code = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $library['path']);

		pg_assert_false(stripos($code, '</script') !== false, $name . ' cannot end its element');
		pg_assert_false(strpos($code, '<!--') !== false, $name . ' cannot open a comment');
	}

	pg_assert_true(isset(ws_page_libraries()['chartjs']), 'Chart.js first');
}

function test_workspace_agent_page_libraries_check()
{
	pg_assert_same(array('names' => array(), 'error' => ''), ws_page_libraries_check(null));
	pg_assert_same(array('names' => array(), 'error' => ''), ws_page_libraries_check(array()));
	pg_assert_same(array('names' => array('chartjs'), 'error' => ''), ws_page_libraries_check(array('chartjs', ' ChartJS ')), 'once, by its name');
	pg_assert_same('jquery', ws_page_libraries_check(array('chartjs', 'jquery'))['error']);
	pg_assert_same('https://cdn.example/chart.js', ws_page_libraries_check(array('https://cdn.example/chart.js'))['error'], 'an address is no name');
	pg_assert_same('?', ws_page_libraries_check('chartjs')['error'], 'a list, not a word');
	pg_assert_same('?', ws_page_libraries_check(array(array('chartjs')))['error'], 'a list inside is refused');
	pg_assert_same('?', ws_page_libraries_check(array(''))['error'], 'an empty name is refused');
}

function test_workspace_agent_page_libraries_go_in_ahead_of_the_page()
{
	pg_assert_same("<!DOCTYPE html>\n<script>A</script>\n<html><p>x</p></html>", ws_page_inline_scripts('<!DOCTYPE html><html><p>x</p></html>', array('A')), 'after the doctype');
	pg_assert_same("<script>A</script>\n<script>B</script>\n<p>x</p>", ws_page_inline_scripts('<p>x</p>', array('A', 'B')), 'first without one');
	pg_assert_same('<p>x</p>', ws_page_inline_scripts('<p>x</p>', array()), 'none named: the page as it is');
	pg_assert_same('<p>x</p>', ws_page_inline_scripts('<p>x</p>', array('a="</SCRIPT><img src=x>"', 'b<!--', '')), 'a source that would break out is left out');
}

// The site puts its own copy in: nothing in the page the frame shows names
// an address to load a library from.
function test_workspace_agent_page_no_address_reaches_a_page()
{
	$page = '<!DOCTYPE html><html><body><canvas id="c"></canvas><script>new Chart(document.getElementById("c"), {type: "bar"});</script></body></html>';
	$shown = ws_page_with_libraries($page, array('chartjs'));

	pg_assert_contains('Chart.js v4.4.7', $shown, 'the copy is inside the document');
	pg_assert_same(2, substr_count(strtolower($shown), '<script'), 'the library\'s element and the page\'s own');
	pg_assert_false((bool) preg_match('/<(script|link|img|iframe)[^>]*\s(src|href)\s*=/i', $shown), 'no element loads from an address');
	pg_assert_same(0, strpos($shown, '<!DOCTYPE html>'), 'the page keeps its doctype first');

	$policy = pg_guarded_frame_policy('script', '');
	pg_assert_contains("script-src 'unsafe-inline';", $policy, 'and the frame would refuse one');
	pg_assert_false((bool) preg_match('/script-src[^;]*(https?:|\*|\'self\')/', $policy), 'no host may serve a script');
}
