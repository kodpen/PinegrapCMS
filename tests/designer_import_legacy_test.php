<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a classic style read by the HTML importer
 * (pg_designer_import_html() in includes/designer_import.php): the region
 * placeholders become region nodes, <meta_tags> and <stylesheet> go, the
 * title and the body attributes land on the root node. Every classic style
 * of the two starting sites is converted and written back.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/designer_import.php');

function _test_dil_import($html, $lookup = null)
{
	$ctx = new stdClass();
	$ctx->user_id  = 0;
	$ctx->files    = array();
	$ctx->folders  = array();
	$ctx->warnings = array();
	$ctx->root_id  = 0;
	$ctx->project  = 'style';
	if ($lookup !== null) $ctx->region_lookup = $lookup;
	$out = pg_designer_import_html($html, 'style.html', $ctx);
	$out['warnings'] = array_merge($out['warnings'], $ctx->warnings);
	return $out;
}

// Every node of a tree, depth first, the root left out.
function _test_dil_nodes($nodes)
{
	$out = array();
	foreach ($nodes as $n) {
		$out[] = $n;
		if (!empty($n['children'])) $out = array_merge($out, _test_dil_nodes($n['children']));
	}
	return $out;
}

// The props of every region node, in document order.
function _test_dil_regions($tree)
{
	$out = array();
	foreach (_test_dil_nodes($tree['children']) as $n) {
		if ($n['type'] === 'region') $out[] = $n['props'];
	}
	return $out;
}

function _test_dil_doc($head, $body, $body_attrs = '')
{
	return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title></title><meta_tags></meta_tags>'
		. $head . '<stylesheet></stylesheet></head><body' . $body_attrs . '>' . $body . '</body></html>';
}

// The classic styles (style_type 'custom') of a starting site's SQL dump:
// style_id => style_code.
function _test_dil_starting_styles($sql_file)
{
	$sql = (string)file_get_contents($sql_file);
	if (!preg_match('/INSERT INTO `style` VALUES (.*?);\n/s', $sql, $m)) return array();
	$s = $m[1];
	$n = strlen($s);
	$styles = array();
	$i = 0;
	while ($i < $n) {
		if ($s[$i] !== '(') { $i++; continue; }
		$i++;
		$row = array();
		$cur = '';
		while ($i < $n) {
			$c = $s[$i];
			if ($c === "'") {
				$v = '';
				for ($i++; $s[$i] !== "'"; $i++) {
					if ($s[$i] === '\\') {
						$i++;
						$map = array('n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0");
						$v .= isset($map[$s[$i]]) ? $map[$s[$i]] : $s[$i];
					} else {
						$v .= $s[$i];
					}
				}
				$i++;
				$row[] = $v;
				continue;
			}
			$i++;
			if ($c === ',' || $c === ')') {
				if ($cur !== '') $row[] = $cur;
				$cur = '';
				if ($c === ')') break;
				continue;
			}
			$cur .= $c;
		}
		// style_id, style_name, style_code, style_user, style_timestamp, style_type, ...
		if (isset($row[5]) && $row[5] === 'custom') $styles[(int)$row[0]] = $row[2];
	}
	return $styles;
}

// The region placeholders of a style's code, in order, as the emitter
// writes them. One inside a comment is not on the page.
function _test_dil_tags($code)
{
	$code = preg_replace('~<!--(?!pg-comments-block-->).*?-->~s', '', $code);
	preg_match_all('~<!--pg-comments-block-->|<(pregion|system|cregion|dregion|menu_sequence|menu|login|ad|cart|tcloud|mobile_switch|pdf)\b[^>]*>[^<]*</\1>~i', $code, $m);
	return $m[0];
}

function test_designer_import_legacy_every_placeholder()
{
	$body = '<pregion></pregion><system></system><system>catalog</system><cregion>footer</cregion>'
		. '<dregion "id=4">clock</dregion><dregion>plain</dregion><menu>main</menu><div style="clear: both"></div>'
		. '<menu_sequence>docs</menu_sequence><login>members</login><ad>top</ad><cart></cart>'
		. '<tcloud>tags</tcloud><mobile_switch></mobile_switch><pdf></pdf><!--pg-comments-block-->';
	$r = _test_dil_import(_test_dil_doc('', '<div class="wrap">' . $body . '</div>'));
	$want = array(
		array('regionType' => 'page', 'regionName' => ''),
		array('regionType' => 'system', 'regionName' => ''),
		array('regionType' => 'system', 'regionName' => 'catalog'),
		array('regionType' => 'common', 'regionName' => 'footer'),
		array('regionType' => 'dynamic', 'regionName' => 'clock', 'regionAttr' => 'id=4'),
		array('regionType' => 'dynamic', 'regionName' => 'plain'),
		array('regionType' => 'menu', 'regionName' => 'main'),
		array('regionType' => 'menu_sequence', 'regionName' => 'docs'),
		array('regionType' => 'login', 'regionName' => 'members'),
		array('regionType' => 'ad', 'regionName' => 'top'),
		array('regionType' => 'cart', 'regionName' => ''),
		array('regionType' => 'tag_cloud', 'regionName' => 'tags'),
		array('regionType' => 'mobile_switch', 'regionName' => ''),
		array('regionType' => 'pdf', 'regionName' => ''),
		array('regionType' => 'comments_block', 'regionName' => ''),
	);
	pg_assert_same($want, _test_dil_regions($r['tree']));

	// The menu took its clearing div; nothing else is left beside the regions.
	$wrap = $r['tree']['children'][0];
	pg_assert_same(count($want), count($wrap['children']), 'the wrapper holds the regions only');

	// Written back, the same tags in the same order.
	pg_assert_same(_test_dil_tags($body), _test_dil_tags(generate_style_code_from_tree($r['tree'], 'x')));
}

function test_designer_import_legacy_head_tags_dropped()
{
	$r = _test_dil_import(_test_dil_doc('', '<p>Hi</p>'));
	$json = json_encode($r['tree']);
	pg_assert_false(strpos($json, 'meta_tags') !== false, 'no meta_tags node');
	pg_assert_false(strpos($json, 'stylesheet') !== false, 'no stylesheet node');
	pg_assert_same(1, count($r['tree']['children']));
	pg_assert_same('paragraph', $r['tree']['children'][0]['props']['contentType']);
}

function test_designer_import_legacy_title_and_body_on_root()
{
	$doc = '<!DOCTYPE html><html><head><title>Fallback &amp; more</title><meta_tags></meta_tags></head>'
		. '<body class="home dark" id="top" style="margin:0" data-x="1"><pregion></pregion></body></html>';
	$r = _test_dil_import($doc);
	$p = $r['tree']['props'];
	pg_assert_same('Fallback & more', $p['title']);
	pg_assert_same('home dark', $p['cssClass']);
	pg_assert_same('top', $p['id']);
	pg_assert_same('margin:0', $p['inlineStyle']);
	pg_assert_same(array(array('name' => 'data-x', 'value' => '1')), $p['_attrs']);

	$code = generate_style_code_from_tree($r['tree'], 'x');
	pg_assert_true(strpos($code, '<title>Fallback &amp; more</title>') !== false, 'the emitter prints the title');

	// An empty title stays empty; a document that is no classic style keeps
	// its title on the page only.
	$r = _test_dil_import(_test_dil_doc('', '<pregion></pregion>'));
	pg_assert_false(isset($r['tree']['props']['title']), 'no title prop for an empty title');
	$r = _test_dil_import('<html><head><title>About</title></head><body><p>x</p></body></html>');
	pg_assert_false(isset($r['tree']['props']['title']), 'a plain page keeps no root title');
	pg_assert_same('About', $r['page_title']);
	pg_assert_true(strpos(generate_style_code_from_tree($r['tree'], 'x'), '<title></title>') !== false);
}

function test_designer_import_legacy_names_and_lookup()
{
	// The emitter writes names escaped; the lookup and the node take them as typed.
	$asked = array();
	$r = _test_dil_import(_test_dil_doc('', '<cregion>Tom &amp; Jerry</cregion><cregion>gone</cregion><system>a &amp; b</system>'),
		function ($name) use (&$asked) {
			$asked[] = $name;
			return ($name === 'Tom & Jerry') ? array('regionType' => 'designer') : null;
		});
	pg_assert_same(array('Tom & Jerry', 'gone'), $asked);
	pg_assert_same(array(
		array('regionType' => 'designer', 'regionName' => 'Tom & Jerry'),
		array('regionType' => 'common', 'regionName' => 'gone'),
		array('regionType' => 'system', 'regionName' => 'a & b'),
	), _test_dil_regions($r['tree']));
	pg_assert_true(strpos(generate_style_code_from_tree($r['tree'], 'x'), '<cregion>Tom &amp; Jerry</cregion>') !== false, 'written back escaped');
}

function test_designer_import_legacy_shared_ref_from_lookup()
{
	$r = _test_dil_import(_test_dil_doc('', '<cregion>header</cregion>'), function ($name) {
		return array('sharedId' => 7, 'sharedName' => 'Header');
	});
	pg_assert_same('shared_ref', $r['tree']['children'][0]['type']);
	pg_assert_same(7, $r['tree']['children'][0]['props']['sharedId']);
}

function test_designer_import_legacy_menu_clear_from_code_view()
{
	$r = _test_dil_import(_test_dil_doc('', '<div><menu>main</menu><div style="clear:both"></div></div>'));
	pg_assert_same(1, count($r['tree']['children'][0]['children']), 'the code view\'s clearing div goes with the menu');
}

function test_designer_import_legacy_placeholder_in_text_kept()
{
	$doc = _test_dil_doc('<script>var t = "<cregion>x</cregion>";</script>',
		'<!-- <pregion></pregion> --><p>a</p><iframe srcdoc="x"><system></system></iframe>');
	$r = _test_dil_import($doc);
	pg_assert_same(array(), _test_dil_regions($r['tree']), 'no region node out of a script, a comment or an iframe');
	pg_assert_same(1, count($r['js']), 'the script is kept');
	$html = '';
	foreach (_test_dil_nodes($r['tree']['children']) as $n) {
		if (isset($n['props']['contentType']) && $n['props']['contentType'] === 'custom_html') $html .= $n['props']['html'];
	}
	pg_assert_true(strpos($html, '<system></system>') !== false, 'the iframe keeps the tag as written');
	pg_assert_false(strpos(json_encode($r), 'pg-region') !== false, 'no lifted marker left anywhere');
}

function test_designer_import_legacy_site_paths_kept()
{
	$doc = _test_dil_doc('<link href="{path}theme.css" rel="stylesheet"><script src="{path}{software_directory}/x.js"></script>',
		'<img src="{path}logo.png" alt="">');
	$r = _test_dil_import($doc);
	pg_assert_same(array(), $r['warnings'], 'no "not in the archive" warning for a site path');
	pg_assert_same('{path}theme.css', $r['css'][0]['content']);
	pg_assert_same('{path}{software_directory}/x.js', $r['js'][0]['content']);
	pg_assert_same('{path}logo.png', $r['tree']['children'][0]['props']['src']);
}

function test_designer_import_legacy_starting_site_styles()
{
	$total = 0;
	foreach (array('english_default', 'turkish_default') as $site) {
		$styles = _test_dil_starting_styles(PG_FUNCTIONS_DIR . '/data/backups/' . $site . '/sql.sql');
		pg_assert_true(count($styles) > 10, $site . ': classic styles found');
		foreach ($styles as $id => $code) {
			$label = $site . ' style ' . $id;
			$r = _test_dil_import($code);
			$tags = _test_dil_tags($code);
			pg_assert_same(count($tags), count(_test_dil_regions($r['tree'])), $label . ': one region node per placeholder');
			$json = json_encode($r);
			pg_assert_false(strpos($json, 'pg-region') !== false, $label . ': no lifted marker left');
			pg_assert_false(strpos($json, 'meta_tags') !== false, $label . ': meta_tags dropped');
			pg_assert_same(array(), $r['warnings'], $label . ': no warning');

			// Written back by the emitter: the same placeholders in the same
			// order (names normalised as the emitter writes them).
			$back = generate_style_code_from_tree($r['tree'], 'x');
			pg_assert_same(array_map('_test_dil_norm_tag', $tags), array_map('_test_dil_norm_tag', _test_dil_tags($back)), $label . ': round trip');
			$total++;
		}
	}
	pg_assert_true($total > 40, 'every classic style was converted');
}

// A placeholder with its whitespace and case normalised, the menu's clearing div left out.
function _test_dil_norm_tag($tag)
{
	return strtolower(preg_replace('/\s+/', ' ', $tag));
}
