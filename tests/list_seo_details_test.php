<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The SEO score of the Pages and Products lists opens the record's analysis
 * in the list's Details panel, under the SEO line, not in a drawer of its
 * own: the cell names the fragment (data-pg-details-load), the driver
 * selects the row, opens the panel and loads it (Driver.detailsFrom()),
 * checking the answer like the other SEO fetches.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/list_table_js_test.php');

function list_seo_details_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_list_seo_details_no_drawer()
{
	$seo = list_seo_details_source('seo.php');

	pg_assert_false(strpos($seo, 'function pg_seo_render_detail_offcanvas(') !== false, 'the SEO drawer is gone');
	pg_assert_false(strpos($seo, 'pg_seo_offcanvas') !== false, 'nothing draws it');
	pg_assert_contains('"pg-details-loaded"', $seo, 'a loaded analysis refreshes the row score');

	foreach (array('view_pages.php', 'view_products.php') as $file) {
		$source = list_seo_details_source($file);
		pg_assert_false(strpos($source, 'pg_seo_render_detail_offcanvas') !== false, $file . ' places no drawer');
		pg_assert_contains('pg_seo_render_run_script()', $source, $file . ' keeps the run buttons working');
	}

	$cell = list_seo_details_source('includes/fn/list_table.php');
	$start = strpos($cell, 'function pg_list_table_seo_cell(');
	$cell = substr($cell, $start, strpos($cell, "\n}", $start) - $start);

	pg_assert_contains('data-pg-details-load="', $cell, 'the score names the fragment for the Details panel');
	pg_assert_contains("&amp;fragment=checklist'", $cell, 'the checklist alone: the panel already shows the name and the buttons');
	pg_assert_false(strpos($cell, 'pg-seo-open') !== false, 'no drawer trigger');
}

function test_list_seo_details_load()
{
	$result = list_table_js_test_node(<<<'JS'
var out = {};
global.document = { createElement: function (tag) {
    return { tag: tag, attrs: {}, children: [], innerHTML: '', events: [],
        setAttribute: function (k, v) { this.attrs[k] = v; },
        removeAttribute: function (k) { delete this.attrs[k]; },
        replaceChildren: function (c) { this.children = [c]; this.innerHTML = ''; },
        dispatchEvent: function (e) { this.events.push(e.type); } };
} };
function run(name, answer) {
    var calls = [];
    var fake = {
        details: {}, texts: { loadFailed: 'failed', sessionExpired: 'expired' }, loaded: null,
        pick: function (row) { calls.push('pick ' + row.getAttribute('data-pg-id')); },
        detailsPressed: function () { return false; },
        setDetails: function (open) { calls.push('open ' + open); },
        showDetails: function () { calls.push('show'); }
    };
    var cell = { getAttribute: function () { return 'seo'; } };
    var trigger = { closest: function () { return cell; }, getAttribute: function () { return 'get_seo_analysis.php?x'; } };
    var row = { getAttribute: function () { return '7'; } };
    window.fetch = function (url) { calls.push('fetch ' + url); return Promise.resolve(answer); };
    T.Driver.prototype.detailsFrom.call(fake, row, trigger);
    return new Promise(function (done) { setTimeout(function () {
        var box = fake.loaded.box;
        out[name] = { calls: calls, id: fake.loaded.id, key: fake.loaded.key, cls: box.className, html: box.innerHTML,
            alert: box.children.length ? box.children[0].textContent : null, busy: box.attrs['aria-busy'] || null, events: box.events };
        done();
    }, 20); });
}
run('ok', { ok: true, redirected: false, status: 200, text: function () { return Promise.resolve('<div class="pg_seo_panel_wrap">A</div>'); } })
.then(function () { return run('expired', { ok: false, redirected: false, status: 401 }); })
.then(function () { return run('redirected', { ok: true, redirected: true, status: 200, text: function () { return Promise.resolve('sign in'); } }); })
.then(function () { return run('error', { ok: false, redirected: false, status: 500 }); })
.then(function () { console.log(JSON.stringify(out)); });
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('pick 7', 'open true', 'fetch get_seo_analysis.php?x'), $result['ok']['calls'], 'the row is selected and the panel opened, then the fragment asked for');
	pg_assert_same(array('7', 'seo', 'pg-list-table-details-more'), array($result['ok']['id'], $result['ok']['key'], $result['ok']['cls']), 'under the SEO line of that row');
	pg_assert_same('<div class="pg_seo_panel_wrap">A</div>', $result['ok']['html'], 'the analysis is put in');
	pg_assert_same(null, $result['ok']['busy']);
	pg_assert_same(array('pg-details-loaded'), $result['ok']['events'], 'the page is told, to refresh the row score');
	pg_assert_same('expired', $result['expired']['alert'], '401 says the session ended');
	pg_assert_same('expired', $result['redirected']['alert'], 'a followed redirect is the sign-in page, not the analysis');
	pg_assert_same('failed', $result['error']['alert'], 'anything else is the generic line');
	pg_assert_same(array(), $result['error']['events']);
}
