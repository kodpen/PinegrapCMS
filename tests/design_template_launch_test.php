<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Phone Launch Site design template's parts
 * (includes/design_templates/pinegrap-launch/): the phone's data hangs
 * together (every model has a price for each of its storage sizes and a
 * value in every specification row, SKUs are unique), a price is written
 * the way the shop writes it, the shared parts load, every page link names
 * a page of the finished template and the header and footer never send a
 * visitor to a page that needs the shop, the spec table reads row by row
 * for a screen reader, the classes are the template's own, and no launch
 * date or year is written into the sample; the public pages load with
 * their keys and root trees, every in-page anchor a link opens exists once,
 * headings never skip a level, and each highlight, spec group and question
 * is a search section.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

// The parts as the template file reads them: parts.php first, the page
// files after it in the same scope. Read once per run.
function _test_launch_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-launch';
	include $dir . '/parts.php';
	$public = include $dir . '/pages_public.php';
	$out = array(
		'pages'       => $public['pages'],
		'widgets'     => $public['widgets'],
		'models'      => $models,
		'storage'     => $storage_sizes,
		'colours'     => $colours,
		'spec_groups' => $spec_groups,
		'prices'      => $phone_prices,
		'sku'         => $phone_sku,
		'accessories' => $accessories,
		'highlights'  => $highlights,
		'press'       => $press_quotes,
		'promises'    => $buy_promises,
		'money'       => $money,
		'price_from'  => $price_from,
		'spec_table'  => $spec_table,
		'header'      => $header(),
		'footer'      => $footer(),
		'shared'      => array($account_bar_tree(), $header(), $footer(), $launch_band(), $notice(), $staff_bar(), $swatches()),
		'parts'       => array(
			$model_card('mini'), $model_card('one'), $model_card('plus'),
			$highlight_tile('design'), $press_quote($press_quotes[0]),
			$spec_table('camera'), $spec_table('battery', array('models' => array('one', 'plus'))), $spec_table(array(), array('differ' => true)),
			$page(array()), $staff_page(array()), $page_header(lang('Tech Specs'), lang('Text')),
			$section_header(lang('Features'), lang('Features'), lang('See the specs'), '{{page:specs}}'),
			$faq(1, lang('Question'), lang('Answer')), $post_card(), $ai_summary(),
			$comment_thread(array('title' => 'T', 'empty' => 'E', 'heading' => 'H', 'label' => 'L', 'sign_in' => 'S', 'submit' => 'B',
				'watch_box' => 'W', 'follow' => 'F', 'unfollow' => 'U', 'close' => 'C', 'reopen' => 'R', 'reply' => true, 'watch' => true)),
		),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_launch_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_launch_nodes($child, $out);
	}
}

// The page keys the finished template carries (the plan's pages); a link
// to any other key would stay a placeholder on the site.
function _test_launch_page_keys()
{
	return array(
		'home', 'features', 'camera', 'specs', 'compare', 'about', 'faq',
		'buy', 'accessories', 'product', 'cart', 'checkout', 'order', 'email_order',
		'tradein', 'tradein_view', 'my_tradeins', 'staff_tradeins',
		'support', 'support_view', 'my_support', 'staff_support', 'privacy', 'email_reply',
		'email_tradein_new', 'email_tradein_received', 'email_support_new', 'email_support_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
}

// The pages that need the shop: a link to one of them from a part every
// page shows would lead nowhere on a site without it.
function _test_launch_shop_keys()
{
	return array('accessories', 'product', 'cart', 'checkout', 'order', 'email_order');
}

function test_launch_models_prices_and_skus_hang_together()
{
	$d = _test_launch_load();
	pg_assert_same(array('mini', 'one', 'plus'), array_keys($d['models']), 'three models, smallest first');
	$featured = 0;
	$skus = array();
	foreach ($d['models'] as $key => $m) {
		if ($m['featured']) $featured++;
		pg_assert_true(isset($d['prices'][$key]), "$key: prices");
		pg_assert_same($m['storage'], array_map('strval', array_keys($d['prices'][$key])), "$key: a price for each storage size it is sold in, in order");
		foreach ($m['storage'] as $gb) {
			pg_assert_true(isset($d['storage'][$gb]), "$key: storage $gb is a known size");
			foreach (array('USD', 'EUR', 'GBP', 'TRY') as $cur) {
				$v = $d['prices'][$key][$gb][$cur];
				pg_assert_true(is_int($v) && $v > 0, "$key $gb $cur: a whole positive price");
			}
			$sku = call_user_func($d['sku'], $key, $gb);
			pg_assert_true((bool)preg_match('/^ORBIT-(MINI|ONE|PLUS)-(128|256|512)$/', $sku), "SKU $sku");
			$skus[] = $sku;
		}
		$prices = array_values($d['prices'][$key]);
		for ($i = 1; $i < count($prices); $i++) {
			pg_assert_true($prices[$i]['USD'] > $prices[$i - 1]['USD'], "$key: more storage costs more");
		}
	}
	pg_assert_same(1, $featured, 'one model is marked most chosen');
	foreach ($d['accessories'] as $key => $a) {
		pg_assert_true((bool)preg_match('/^ORBIT-ACC-[A-Z]+$/', $a['sku']), "accessory $key: SKU");
		pg_assert_true(isset($a['price']['USD'], $a['price']['TRY']), "accessory $key: prices");
		$skus[] = $a['sku'];
	}
	pg_assert_same(count($skus), count(array_unique($skus)), 'SKUs are unique');
	pg_assert_same(4, count($d['colours']), 'four colours');
}

function test_launch_spec_rows_have_a_value_for_every_model()
{
	$d = _test_launch_load();
	$models = array_keys($d['models']);
	foreach ($d['spec_groups'] as $gk => $g) {
		pg_assert_true($g['title'] !== '' && strpos($g['icon'], 'bi-') === 0, "$gk: title and icon");
		pg_assert_true(count($g['rows']) >= 3, "$gk: rows");
		foreach ($g['rows'] as $r) {
			pg_assert_same($models, array_keys($r[1]), "$gk / {$r[0]}: a value for each model, in model order");
			foreach ($r[1] as $v) pg_assert_true(is_string($v) && $v !== '', "$gk / {$r[0]}: value written");
		}
	}
	// The storage row says what the prices sell.
	foreach ($d['spec_groups']['performance']['rows'] as $r) {
		if ($r[0] !== lang('Storage')) continue;
		foreach ($d['models'] as $key => $m) {
			foreach ($m['storage'] as $gb) pg_assert_contains($gb, $r[1][$key], "$key: storage row names $gb");
		}
	}
}

// The cards and the shop show one amount: the base currency's figure, the
// USD figure for a currency the table does not list.
function test_launch_price_is_written_as_the_shop_writes_it()
{
	$d = _test_launch_load();
	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
	$symbol = defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$';
	$prices = $d['prices']['one']['128'];
	$amount = isset($prices[$base]) ? $prices[$base] : $prices['USD'];
	pg_assert_same(pg_money_text($amount, $symbol), call_user_func($d['money'], $prices));
	pg_assert_same(sprintf(lang('From %s'), pg_money_text($amount, $symbol)), call_user_func($d['price_from'], 'one'), 'From the lowest size');
}

function test_launch_page_links_name_template_pages()
{
	$d = _test_launch_load();
	$known = array_flip(_test_launch_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(array($d['shared'], $d['parts'], $d['pages'])), $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
}

function test_launch_header_and_footer_stand_without_the_shop()
{
	$d = _test_launch_load();
	$json = json_encode(array($d['header'], $d['footer'], $d['shared'][3], $d['shared'][4]));
	foreach (_test_launch_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "shared parts do not link to $key");
	}
	pg_assert_contains('{{page:buy}}', json_encode($d['header']), 'the header has the Buy button');
	$nodes = array();
	_test_launch_nodes($d['header'], $nodes);
	$menu = null;
	foreach ($nodes as $n) {
		if (!empty($n['props']['smartActive'])) $menu = $n;
	}
	pg_assert_true($menu !== null, 'the menu marks the current page on the server');
	foreach ($nodes as $n) pg_assert_false(isset($n['props']['active']), 'no page-specific active link');
}

function test_launch_spec_table_reads_row_by_row()
{
	$d = _test_launch_load();
	$table = call_user_func($d['spec_table'], 'battery', array('models' => array('one', 'plus')));
	pg_assert_same('pg-launch-specs', $table['props']['cssClass'], 'drawn as the launch-specs block');
	$nodes = array();
	_test_launch_nodes($table, $nodes);
	$rows = 0;
	$groups = 0;
	foreach ($nodes as $n) {
		if (($n['props']['tag'] ?? '') !== 'tr') continue;
		$first = $n['children'][0]['props'];
		pg_assert_true(in_array($first['tag'], array('th', 'td'), true), 'a row starts with a cell');
		$scope = '';
		foreach ($first['_attrs'] ?? array() as $a) {
			if ($a['name'] === 'scope') $scope = $a['value'];
		}
		if ($scope === 'rowgroup') {
			$groups++;
			pg_assert_same(1, count($n['children']), 'a group row is one header cell');
			continue;
		}
		$rows++;
		pg_assert_same(3, count($n['children']), 'a feature cell and one per model named');
		pg_assert_true($first['tag'] === 'td' || $scope === 'row', 'a row header has its scope');
	}
	pg_assert_same(1, $groups, 'one group');
	pg_assert_same(count($d['spec_groups']['battery']['rows']) + 1, $rows, 'a head row and a row per line');
}

// The compare table keeps only the rows whose values differ between the
// models; each row of $spec_groups is either there or a shared line.
function test_launch_spec_table_differences()
{
	$d = _test_launch_load();
	$json = json_encode(call_user_func($d['spec_table'], array(), array('differ' => true)), JSON_UNESCAPED_UNICODE);
	foreach ($d['spec_groups'] as $g) {
		foreach ($g['rows'] as $r) {
			$differs = count(array_unique($r[1])) > 1;
			$label = json_encode($r[0], JSON_UNESCAPED_UNICODE);
			pg_assert_same($differs, strpos($json, '"text":' . $label) !== false, $r[0] . ($differs ? ' differs: in the table' : ' is shared: left out'));
		}
	}
}

// Classes the template adds of its own start with plx-; another template's
// prefix copied in would stay unstyled (its rules are not in this one).
function test_launch_classes_are_its_own()
{
	$d = _test_launch_load();
	$nodes = array();
	foreach (array_merge($d['shared'], $d['parts']) as $t) _test_launch_nodes($t, $nodes);
	foreach ($d['pages'] as $p) _test_launch_nodes($p['tree'], $nodes);
	$own = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pc|pl)-/', $c), "class $c is not another template's");
			if (strpos($c, 'plx-') === 0) $own[$c] = true;
		}
	}
	foreach (array('plx-brand', 'plx-eyebrow', 'plx-price', 'plx-swatch', 'plx-glow', 'plx-lift', 'plx-figure') as $c) {
		pg_assert_true(isset($own[$c]), "$c is used");
	}
	foreach (array_keys($d['colours']) as $key) pg_assert_true(isset($own['plx-swatch-' . $key]), "swatch class of $key");
}

// The phone is "new", never dated: a year or a launch day in the sample
// would read as a past launch a year later.
function test_launch_no_fixed_dates()
{
	$d = _test_launch_load();
	$json = json_encode(array($d['models'], $d['spec_groups'], $d['highlights'], $d['press'], $d['promises'], $d['accessories'], $d['shared'], $d['parts'], $d['pages']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace of the drawn stars is not a date.
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the parts has its Turkish, and the Turkish names the
// template's things one way (Orbit stays Orbit, a trade-in is "takas").
function test_launch_turkish_words()
{
	$map = pg_test_language_map('tr');
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-launch';
	$src = file_get_contents($dir . '/parts.php') . file_get_contents($dir . '/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (strpos($key, 'Orbit') !== false && isset($map[$key])) pg_assert_contains('Orbit', $map[$key], "'$key' keeps the name Orbit");
		if (stripos($key, 'trade-in') !== false && isset($map[$key])) pg_assert_true(mb_stripos($map[$key], 'takas', 0, 'UTF-8') !== false, "'$key' says takas");
	}
}

function test_launch_public_pages_load()
{
	$d = _test_launch_load();
	$keys = array();
	foreach ($d['pages'] as $p) {
		pg_assert_true(isset($p['key'], $p['name'], $p['title'], $p['tree'], $p['meta_description']), 'page has key, name, title, tree, description');
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': root tree');
		pg_assert_same('public', $p['folder'], $p['key'] . ': public folder');
		pg_assert_true(!isset($p['requires']), $p['key'] . ': stands without the shop');
		$keys[] = $p['key'];
	}
	pg_assert_same(array('home', 'features', 'camera', 'specs', 'compare', 'about', 'faq'), $keys);
	pg_assert_same(array(), $d['widgets'], 'the public pages bring no widget of their own');
	$home = json_encode($d['pages'][0]['tree']);
	foreach (array('home_posts', 'launch_band', 'header', 'footer') as $key) pg_assert_contains('"' . $key . '"', $home, "home places $key");
}

// An anchor a link opens (#feature-design, the spec groups, buy-<model>
// aside, which the buy page carries) exists exactly once on its page.
function test_launch_anchors_exist_and_are_unique()
{
	$d = _test_launch_load();
	$ids = array();
	foreach ($d['pages'] as $p) {
		$nodes = array();
		_test_launch_nodes($p['tree'], $nodes);
		$seen = array();
		foreach ($nodes as $n) {
			if (!isset($n['props']['id'])) continue;
			$id = $n['props']['id'];
			pg_assert_false(isset($seen[$id]), $p['key'] . ": id $id is unique");
			$seen[$id] = true;
		}
		$ids[$p['key']] = $seen;
	}
	$json = json_encode(array($d['shared'], $d['parts'], $d['pages']));
	preg_match_all('/\{\{page:([a-z_]+)\}\}#([a-z0-9-]+)/', $json, $m, PREG_SET_ORDER);
	pg_assert_true(count($m) > 5, 'anchored links found');
	foreach ($m as $hit) {
		if (!isset($ids[$hit[1]])) continue; // a page of a later part
		pg_assert_true(isset($ids[$hit[1]][$hit[2]]), "{{page:{$hit[1]}}}#{$hit[2]} exists");
	}
	foreach ($d['pages'] as $p) {
		if ($p['key'] !== 'specs') continue;
		preg_match_all('/"href":"#([a-z0-9-]+)"/', json_encode($p['tree']), $jm);
		foreach ($jm[1] as $id) pg_assert_true(isset($ids['specs'][$id]), "specs jump link #$id exists");
		pg_assert_same(count($d['spec_groups']), count($jm[1]), 'a jump link per spec group');
	}
}

// Headings go down one level at a time on every page (h1, then h2, ...).
function test_launch_headings_never_skip_a_level()
{
	$d = _test_launch_load();
	foreach ($d['pages'] as $p) {
		$nodes = array();
		_test_launch_nodes($p['tree'], $nodes);
		$last = 0;
		$h1 = 0;
		foreach ($nodes as $n) {
			$tag = isset($n['props']['tag']) ? $n['props']['tag'] : '';
			if (!preg_match('/^h([1-6])$/', $tag, $hm)) continue;
			$level = (int)$hm[1];
			if ($level === 1) $h1++;
			pg_assert_true($level <= $last + 1, $p['key'] . ": h$level after h$last");
			$last = $level;
		}
		pg_assert_same(1, $h1, $p['key'] . ': one h1');
	}
}

function test_launch_search_sections()
{
	$d = _test_launch_load();
	$sections = array();
	foreach ($d['pages'] as $p) {
		$nodes = array();
		_test_launch_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (!empty($n['props']['searchSection'])) $sections[$p['key']][] = $n['props']['id'];
		}
	}
	foreach (array_keys($d['highlights']) as $key) pg_assert_true(in_array('feature-' . $key, $sections['features'], true), "feature-$key is a search section");
	foreach (array_keys($d['spec_groups']) as $key) pg_assert_true(in_array('spec-' . $key, $sections['specs'], true), "spec-$key is a search section");
	pg_assert_true(count($sections['faq']) >= 10, 'every question is a search section');
	foreach (array('camera', 'compare', 'about') as $key) pg_assert_true(!empty($sections[$key]), "$key has search sections");
}
