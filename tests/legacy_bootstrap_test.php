<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bootstrap 3 to 5.3.8 converter of a Legacy design
 * (includes/fn/legacy_bootstrap.php): the class map, a class list, a tree,
 * a sheet, the report, and the starting sites' frames.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/classic_style_design.php');
// The starting sites' classic styles (_test_dil_starting_styles()).
require_once(__DIR__ . '/designer_import_legacy_test.php');

// The class names a stylesheet defines.
function _test_lbs_sheet_classes($file)
{
	preg_match_all('/\.(-?[_a-zA-Z][_a-zA-Z0-9-]*)/', preg_replace('#/\*.*?\*/#s', '', (string)file_get_contents($file)), $m);
	return array_flip($m[1]);
}

function test_lbs_grid_names()
{
	pg_assert_same('col-6 offset-md-2', pg_lbs_classes('col-xs-6 col-md-offset-2')['class']);
	pg_assert_same('col-sm-4', pg_lbs_classes('col-sm-4 col-xs-offset-0')['class']);
	// The reset of a push or pull goes; a real push stays for a designer.
	$r = pg_lbs_classes('col-md-5 col-md-pull-0 col-sm-7 col-sm-pull-4');
	pg_assert_same('col-md-5 col-sm-7 col-sm-pull-4', $r['class']);
	pg_assert_same(array('col-sm-pull-4' => 1), $r['by_hand']);
	pg_assert_same(1, $r['converted']['col-md-pull-0']);
}

function test_lbs_helpers_forms_and_components()
{
	pg_assert_same('text-end float-start', pg_lbs_classes('text-right pull-left')['class']);
	pg_assert_same('btn btn-light btn-sm', pg_lbs_classes('btn btn-default btn-xs')['class']);
	pg_assert_same('img-fluid d-block mx-auto', pg_lbs_classes('img-responsive center-block')['class']);
	pg_assert_same('mb-3', pg_lbs_classes('form-group')['class']);
	pg_assert_same('card border-primary', pg_lbs_classes('panel panel-primary')['class']);
	pg_assert_same('badge text-bg-success', pg_lbs_classes('label label-success')['class']);
	pg_assert_same('visually-hidden', pg_lbs_classes('sr-only')['class']);
	pg_assert_same('ratio ratio-16x9', pg_lbs_classes('embed-responsive embed-responsive-16by9')['class']);
	// "in" is renamed only beside the classes Bootstrap's scripts show.
	pg_assert_same('modal fade show', pg_lbs_classes('modal fade in')['class']);
	pg_assert_same('in', pg_lbs_classes('in')['class']);
	// A name the map does not know stays, a theme's name too.
	$r = pg_lbs_classes('feature boxed input-group-btn');
	pg_assert_same('feature boxed input-group-btn', $r['class']);
	pg_assert_same(array('input-group-btn' => 1), $r['by_hand']);
	pg_assert_same('', pg_lbs_classes('')['class']);
}

function test_lbs_visibility_is_one_set_per_element()
{
	pg_assert_same('d-none d-sm-block', pg_lbs_classes('hidden-xs')['class']);
	pg_assert_same('d-inline d-sm-none', pg_lbs_classes('visible-xs-inline')['class']);
	// Two visible-* together: shown at both, hidden from md on. Renamed one by
	// one they gave d-none … d-block and the phone menu button vanished.
	pg_assert_same('module mobile-toggle d-block d-md-none right', pg_lbs_classes('module mobile-toggle visible-sm visible-xs right')['class']);
	pg_assert_same('d-sm-none d-lg-block', pg_lbs_classes('hidden-sm hidden-md')['class']);
	pg_assert_same('d-none', pg_lbs_classes('hidden')['class']);
	pg_assert_same('d-print-none', pg_lbs_classes('hidden-print')['class']);
	pg_assert_same('d-none d-print-block', pg_lbs_classes('visible-print')['class']);
}

function test_lbs_theme_names_taken_by_5_3_8_keep_the_theme_s_rule()
{
	pg_assert_same('legacy-bg-secondary pt120', pg_lbs_classes('bg-secondary pt120')['class']);
	pg_assert_same('footer-1 legacy-bg-dark', pg_lbs_classes('footer-1 bg-dark')['class']);
	$css = pg_lbs_css(".bg-secondary{background:#f8f8f8}\n.bg-dark h1,.bg-dark p{color:#fff}");
	pg_assert_same(".legacy-bg-secondary{background:#f8f8f8}\n.legacy-bg-dark h1,.legacy-bg-dark p{color:#fff}", $css['css']);
	// The map never writes one of these names, or a second pass would rename
	// its own output.
	foreach (pg_lbs_class_map() as $from => $to) {
		foreach (preg_split('/\s+/', $to, -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false(in_array($c, pg_lbs_theme_names(), true), $from . ' writes ' . $c);
		}
	}
}

function test_lbs_icons_with_a_twin()
{
	pg_assert_same('bi bi-check-lg', pg_lbs_classes('glyphicon glyphicon-ok')['class']);
	pg_assert_same('bi bi-facebook', pg_lbs_classes('ti-facebook')['class']);
	pg_assert_same('bi bi-palette icon-lg mb40', pg_lbs_classes('ti-palette icon-lg mb40')['class']);
	// No twin: the glyph and its base class stay, counted.
	$r = pg_lbs_classes('glyphicon glyphicon-bishop');
	pg_assert_same('glyphicon glyphicon-bishop', $r['class']);
	pg_assert_same(array('glyphicon-bishop' => 1), $r['kept']);
	// Every twin is a Bootstrap Icons name.
	$bi = _test_lbs_sheet_classes(PG_FUNCTIONS_DIR . '/assets/fonts/bootstrap-icons/bootstrap-icons.css');
	foreach (pg_lbs_icon_map() as $from => $to) {
		pg_assert_true(isset($bi['bi-' . $to]), $from . ': bi-' . $to . ' exists');
	}
}

// Every class the map writes is one 5.3.8 defines, or the theme's own name.
function test_lbs_map_writes_5_3_8_names()
{
	$bs5 = _test_lbs_sheet_classes(PG_FUNCTIONS_DIR . '/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css');
	foreach (pg_lbs_class_map() as $from => $to) {
		foreach (preg_split('/\s+/', $to, -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_true(isset($bs5[$c]) || strpos($c, 'legacy-') === 0, $from . ' writes ' . $c);
		}
	}
	foreach (array('hidden-xs', 'visible-sm visible-xs', 'visible-md-inline-block', 'hidden-sm hidden-md', 'visible-print-inline') as $set) {
		foreach (pg_lbs_visibility(explode(' ', $set)) as $c) {
			pg_assert_true(isset($bs5[$c]), $set . ' writes ' . $c);
		}
	}
}

function test_lbs_tree_classes_html_attrs_and_list_items()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'ul', 'cssClass' => 'list-inline social-list'), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'li'), 'children' => array()),
			array('type' => 'semantic', 'props' => array('tag' => 'li', 'cssClass' => 'text-right'), 'children' => array()),
		)),
		array('type' => 'content', 'props' => array('contentType' => 'custom_html',
			'html' => '<div class="col-xs-12" data-toggle="tooltip" data-placement="top" data-time-delay="3">x</div>'), 'children' => array()),
		array('type' => 'semantic', 'props' => array('tag' => 'a', 'cssClass' => 'btn',
			'_attrs' => array(array('name' => 'data-toggle', 'value' => 'modal'), array('name' => 'data-filter', 'value' => 'x'))), 'children' => array()),
		array('type' => 'shared_ref', 'props' => array('sharedId' => 7, 'sharedName' => 'footer', 'cssClass' => 'pull-left'), 'children' => array()),
	));
	$r = pg_lbs_tree($tree);
	$t = $r['tree'];
	pg_assert_same('list-inline-item', $t['children'][0]['children'][0]['props']['cssClass']);
	pg_assert_same('list-inline-item text-end', $t['children'][0]['children'][1]['props']['cssClass']);
	pg_assert_same('<div class="col-12" data-bs-toggle="tooltip" data-bs-placement="top" data-time-delay="3">x</div>', $t['children'][1]['props']['html']);
	pg_assert_same('data-bs-toggle', $t['children'][2]['props']['_attrs'][0]['name']);
	pg_assert_same('data-filter', $t['children'][2]['props']['_attrs'][1]['name']);
	// A placed part is its own tree, converted on its own.
	pg_assert_same($tree['children'][3], $t['children'][3]);
	pg_assert_same(2, $r['converted']['list-inline']);
	// A second pass changes nothing.
	pg_assert_same($t, pg_lbs_tree($t)['tree']);
	pg_assert_same(array(), pg_lbs_tree($t)['converted']);
}

function test_lbs_css_renames_selectors_only()
{
	$css = "/* .text-right in a comment */\n"
		. "@import url(\"x.css\");\n"
		. ".text-right,.pull-right>a{float:right;content:\".text-right\"}\n"
		. "@media (max-width:767px){.col-xs-12 .img-responsive{width:100%}}\n"
		. "@font-face{font-family:x;src:url(a.text-right.woff)}\n"
		. "@keyframes k{from{opacity:0}to{opacity:1}}\n"
		. "a[href\$=\".text-right\"]{color:red}\n"
		. ".hidden-xs,.center-block,.navbar-header{display:block}";
	$r = pg_lbs_css($css);
	$want = "/* .text-right in a comment */\n"
		. "@import url(\"x.css\");\n"
		. ".text-end,.float-end>a{float:right;content:\".text-right\"}\n"
		. "@media (max-width:767px){.col-12 .img-fluid{width:100%}}\n"
		. "@font-face{font-family:x;src:url(a.text-right.woff)}\n"
		. "@keyframes k{from{opacity:0}to{opacity:1}}\n"
		. "a[href\$=\".text-right\"]{color:red}\n"
		. ".hidden-xs,.d-block.mx-auto,.navbar-header{display:block}";
	pg_assert_same($want, $r['css']);
	pg_assert_same(array('hidden-xs' => 1, 'navbar-header' => 1), $r['kept']);
	// A second pass changes nothing; a sheet with no class of the map is
	// returned byte for byte.
	pg_assert_same($want, pg_lbs_css($want)['css']);
	$plain = ".feature{margin:0}\r\n@media print{.x{y:z}}";
	pg_assert_same($plain, pg_lbs_css($plain)['css']);
	pg_assert_same('', pg_lbs_css('')['css']);
}

function test_lbs_report()
{
	$r = pg_lbs_report(array(
		array('converted' => array('text-right' => 2), 'kept' => array(), 'by_hand' => array('col-sm-push-8' => 1)),
		array('converted' => array('text-right' => 1, 'form-group' => 4), 'kept' => array('navbar-header' => 1)),
	));
	pg_assert_same(array('form-group' => 4, 'text-right' => 3), $r['converted']);
	pg_assert_same(array('converted' => 7, 'kept' => 1, 'by_hand' => 1), $r['totals']);
}

// Every classic style of both starting sites: its frame converted keeps its
// shape and placed parts, leaves no Bootstrap 3 name the map knows, and a
// second pass changes nothing.
function test_lbs_starting_site_frames()
{
	$map = pg_lbs_class_map();
	$styles = 0;
	$converted = 0;
	foreach (array('english_default', 'turkish_default') as $site) {
		foreach (_test_dil_starting_styles(PG_FUNCTIONS_DIR . '/data/backups/' . $site . '/sql.sql') as $id => $code) {
			$label = $site . ' style ' . $id;
			$f = pg_classic_style_frame($code, function ($name) {
				return array('regionType' => 'common');
			});
			$frame = $f['frame'];
			$r = pg_lbs_tree($frame);
			$styles++;
			$converted += array_sum($r['converted']);
			$before = json_encode($frame);
			$after = json_encode($r['tree']);
			pg_assert_same(substr_count($before, '"type":'), substr_count($after, '"type":'), $label . ': the same nodes');
			pg_assert_same(substr_count($before, '"shared_ref"'), substr_count($after, '"shared_ref"'), $label . ': the same parts');
			array_walk_recursive($r['tree'], function ($v, $k) use ($map, $label) {
				if ($k !== 'cssClass') return;
				foreach (preg_split('/\s+/', (string)$v, -1, PREG_SPLIT_NO_EMPTY) as $c) {
					pg_assert_false(isset($map[$c]) || pg_lbs_is_visibility($c), $label . ': ' . $c . ' left');
				}
			});
			pg_assert_same($r['tree'], pg_lbs_tree($r['tree'])['tree'], $label . ': a second pass');
		}
	}
	pg_assert_true($styles > 80, 'both sites read');
	pg_assert_true($converted > 0, 'the frames have Bootstrap 3 classes');
}
