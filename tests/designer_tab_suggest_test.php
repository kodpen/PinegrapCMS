<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The page strip's suggested groups (assets/js/style_designer.src.js,
 * _pgTabSuggestGroups()): the groups a design without groups is offered,
 * read from the pages' addresses and kinds. Run in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs _pgTabSuggestGroups() in Node on `$pages` ([key, address, kind]
// rows); the groups come back as [id, kind, word, keys]. Null when Node
// cannot run.
function designer_tab_suggest_run($pages, $opts = null)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$a = strpos($js, '    // ── Suggested groups ──');
	$b = ($a !== false) ? strpos($js, '    function _pgTabSuggestKind(', $a) : false;
	pg_assert_true($b !== false, 'the suggested groups are in style_designer.src.js');

	if ($b === false) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgtabsuggest');

	if ($file === false) {
		return null;
	}

	$rows = array();

	foreach ($pages as $p) {
		$rows[] = array('key' => $p[0], 'address' => $p[1], 'kind' => isset($p[2]) ? $p[2] : '');
	}

	$code = substr($js, $a, $b - $a)
		. "\nvar out = _pgTabSuggestGroups(" . json_encode($rows) . ($opts !== null ? ', ' . json_encode($opts) : '') . ")"
		. ".map(function (g) { return [g.id, g.kind, g.word, g.keys]; });"
		. "\nconsole.log(JSON.stringify(out));";
	file_put_contents($file, $code);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);
	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// A shop's design: the home page, three service pages, the account pages,
// two blog pages, two e-mails and two pages that share nothing.
function designer_tab_suggest_shop()
{
	return array(
		array('p1', '/'),
		array('p2', '/services-web'),
		array('p3', '/about'),
		array('p4', '/services-mobile'),
		array('p5', '/login', 'account'),
		array('p6', '/my-account', 'account'),
		array('p7', '/services/seo'),
		array('p8', '/blog'),
		array('p9', '/news', 'blog'),
		array('p10', '/welcome-mail', 'email'),
		array('p11', '/order-mail', 'email'),
		array('p12', '/contact'),
		array('p13', '/forgot-password', 'account'),
		array('p14', '/cart', 'shop'),
		array('p15', '/checkout', 'shop'),
	);
}

function test_designer_tab_suggest_groups_by_kind_and_address()
{
	$out = designer_tab_suggest_run(designer_tab_suggest_shop());

	if ($out === null) {
		return;
	}

	// In the order of each group's first page.
	pg_assert_same(array(
		array('w:services', '', 'services', array('p2', 'p4', 'p7')),
		array('account', 'account', '', array('p5', 'p6', 'p13')),
		array('blog', 'blog', '', array('p8', 'p9')),
		array('email', 'email', '', array('p10', 'p11')),
		array('shop', 'shop', '', array('p14', 'p15')),
	), $out, 'services by address, then the account pages, the blog (its address word too), the e-mails, the shop');
}

function test_designer_tab_suggest_leaves_the_home_page_and_lone_pages_out()
{
	$out = designer_tab_suggest_run(designer_tab_suggest_shop());

	if ($out === null) {
		return;
	}

	$keys = array();

	foreach ($out as $g) {
		$keys = array_merge($keys, $g[3]);
	}

	foreach (array('p1', 'p3', 'p12') as $k) {
		pg_assert_true(!in_array($k, $keys, true), $k . ' stays without a group ("Other")');
	}
}

function test_designer_tab_suggest_needs_two_pages_per_group()
{
	$out = designer_tab_suggest_run(array(
		array('p1', '/'), array('p2', '/a-one'), array('p3', '/team-x'), array('p4', '/login', 'account'),
		array('p5', '/mail', 'email'), array('p6', '/prices'), array('p7', '/b-two'),
	));

	if ($out === null) {
		return;
	}

	// One-letter words and kinds of a single page make no group.
	pg_assert_same(array(), $out, 'nothing to suggest');
}

function test_designer_tab_suggest_small_design_gets_nothing()
{
	$out = designer_tab_suggest_run(array(
		array('p1', '/'), array('p2', '/services-web'), array('p3', '/services-app'), array('p4', '/about'),
	));

	if ($out === null) {
		return;
	}

	pg_assert_same(array(), $out, 'four pages: the strip holds them, nothing is offered');

	$out = designer_tab_suggest_run(array(
		array('p1', '/'), array('p2', '/services-web'), array('p3', '/services-app'), array('p4', '/about'),
	), array('min' => 3));
	pg_assert_same(array(array('w:services', '', 'services', array('p2', 'p3'))), $out, 'the minimum is an option');
}

function test_designer_tab_suggest_group_of_every_page_is_no_grouping()
{
	$pages = array();

	for ($i = 1; $i <= 8; $i++) {
		$pages[] = array('p' . $i, '/mail-' . $i, 'email');
	}

	$out = designer_tab_suggest_run($pages);

	if ($out !== null) {
		pg_assert_same(array(), $out, 'an e-mail design: one group of every page is not offered');
	}
}

function test_designer_tab_suggest_words_folded_and_digits_skipped()
{
	$out = designer_tab_suggest_run(array(
		array('p1', '/'), array('p2', '/Ürünler-Yeni'), array('p3', '/ürünler_eski'), array('p4', '/2026-plan'),
		array('p5', '/2026-budget'), array('p6', '/x'), array('p7', '/about'),
	));

	if ($out === null) {
		return;
	}

	pg_assert_same(array(array('w:ürünler', '', 'ürünler', array('p2', 'p3'))), $out, 'the word is lowercased, "_" ends it, a number is no word');
}

function test_designer_tab_suggest_at_most_eight_groups_largest_kept()
{
	$pages = array(array('p0', '/'));
	$words = array('aa', 'bb', 'cc', 'dd', 'ee', 'ff', 'gg', 'hh', 'ii', 'jj');

	foreach ($words as $i => $w) {
		$n = ($w === 'aa') ? 2 : 3;

		for ($j = 0; $j < $n; $j++) {
			$pages[] = array($w . $j, '/' . $w . '-' . $j);
		}
	}

	$out = designer_tab_suggest_run($pages);

	if ($out === null) {
		return;
	}

	pg_assert_same(8, count($out), 'eight groups at most: one colour each');
	pg_assert_same('w:bb', $out[0][0], 'the smallest group (aa, two pages) is left out first');
	pg_assert_same('w:ii', $out[7][0], 'among equals the later ones go: jj is left out, strip order kept');
}
