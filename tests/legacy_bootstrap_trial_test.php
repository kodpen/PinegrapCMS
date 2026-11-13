<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bootstrap 5 trial design of the "Towards Bootstrap 5" card
 * (includes/fn/legacy_bootstrap_trial.php): its names, the scripts' tooltip
 * and data attribute conversion, the asset rows, the parts' places, the
 * compatibility sheet, the card's markup, the preview's request and the
 * wiring of the screen, the preview and the page renderer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_bootstrap_trial.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_classic.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/classic_style_design.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/design_previews.php');
// The starting sites' classic styles (_test_dil_starting_styles()).
require_once(__DIR__ . '/designer_import_legacy_test.php');

function _test_lbst_row($type, $content, $extra = array())
{
	return array_merge(array('id' => 'r' . md5($type . $content), 'name' => $content, 'type' => $type, 'content' => $content, 'enabled' => true), $extra);
}

function _test_lbst_metas($rows)
{
	return array_map(function ($r) {
		return (string)($r['meta'] ?? '');
	}, $rows);
}

function test_lbst_names()
{
	pg_assert_same('home-1 set (Bootstrap 5)', pg_lbs_trial_name('home-1 set'));
	pg_assert_same('home-1 set (Bootstrap 5)', pg_lbs_trial_name('  home-1 set '));
	$long = pg_lbs_trial_name(str_repeat('ş', 120));
	pg_assert_same(100, mb_strlen($long));
	pg_assert_same(' (Bootstrap 5)', mb_substr($long, -14));

	pg_assert_same('theme_modern-bs5.css', pg_lbs_trial_file_name('theme_modern.css'));
	pg_assert_same('scripts-bs5.js', pg_lbs_trial_file_name('scripts.js'));
	pg_assert_same('a.b-bs5.css', pg_lbs_trial_file_name('a.b.css'));
	pg_assert_same('README-bs5', pg_lbs_trial_file_name('README'));
	pg_assert_same('.htaccess-bs5', pg_lbs_trial_file_name('.htaccess'));
}

function test_lbst_script_tooltip()
{
	$r = pg_lbs_trial_script("    \$('[data-toggle=\"tooltip\"]').tooltip();\n");
	pg_assert_same("    document.querySelectorAll('[data-bs-toggle=\"tooltip\"]').forEach(function (el) { bootstrap.Tooltip.getOrCreateInstance(el); });\n", $r['js']);
	pg_assert_same(1, $r['calls']);
	pg_assert_same(1, $r['names']);
	pg_assert_same(array(), $r['left']);

	// jQuery by name, double quotes, a popover with its options.
	$r = pg_lbs_trial_script('jQuery( "[data-toggle=popover]" ) . popover( { trigger: "hover" } )');
	pg_assert_same('document.querySelectorAll("[data-bs-toggle=popover]").forEach(function (el) { bootstrap.Popover.getOrCreateInstance(el, { trigger: "hover" }); })', $r['js']);
	// A second pass changes nothing.
	$again = pg_lbs_trial_script($r['js']);
	pg_assert_same($r['js'], $again['js']);
	pg_assert_same(0, $again['calls'] + $again['names']);

	// A selection that is not a quoted selector, an option holding a call
	// and another plugin are left as written and listed.
	$js = "\$(el).tooltip(); \$('.x').tooltip({ title: f() }); \$('#m').modal('show');";
	$r = pg_lbs_trial_script($js);
	pg_assert_same($js, $r['js']);
	pg_assert_same(0, $r['calls']);
	pg_assert_same(array('tooltip()' => 2, 'modal()' => 1), $r['left']);
}

function test_lbst_script_data_names()
{
	$r = pg_lbs_trial_script("a.attr('data-target'); b = 'data-slide-to'; c = 'data-toggles'; d = 'data-bs-toggle'; e = 'my-data-toggle'; f = '[data-dismiss=modal]';");
	pg_assert_same("a.attr('data-bs-target'); b = 'data-bs-slide-to'; c = 'data-toggles'; d = 'data-bs-toggle'; e = 'my-data-toggle'; f = '[data-bs-dismiss=modal]';", $r['js']);
	pg_assert_same(3, $r['names']);
	pg_assert_same('', pg_lbs_trial_script('')['js']);
}

function test_lbst_starting_scripts()
{
	foreach (array('english_default', 'turkish_default') as $site) {
		$js = (string)file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/' . $site . '/files/scripts.js');
		$r = pg_lbs_trial_script($js);
		pg_assert_same(1, $r['calls'], $site);
		pg_assert_same(array(), $r['left'], $site);
		pg_assert_true(strpos($r['js'], "document.querySelectorAll('[data-bs-toggle=\"tooltip\"]').forEach(function (el) { bootstrap.Tooltip.getOrCreateInstance(el); });") !== false, $site);
		// The card's report finds nothing left to change.
		pg_assert_same(array(), pg_lbs_script_calls($r['js']), $site);
		pg_assert_same($r['js'], pg_lbs_trial_script($r['js'])['js'], $site . ': a second pass');
		// Only that line differs.
		$before = explode("\n", $js);
		$after = explode("\n", $r['js']);
		pg_assert_same(count($before), count($after), $site);
		pg_assert_same(1, count(array_diff_assoc($before, $after)), $site);
	}
}

function test_lbst_framework_rows()
{
	$fw = pg_lbs_trial_framework_rows();
	$reg = pg_design_framework('bootstrap5');
	pg_assert_same($reg['css'], $fw['css']['content']);
	pg_assert_same($reg['js'], $fw['js']['content']);
	pg_assert_same(pg_design_icons_url(), $fw['icons']['content']);
	pg_assert_same(array('bootstrap-css', 'bootstrap-icons', 'bootstrap-js'), array($fw['css']['meta'], $fw['icons']['meta'], $fw['js']['meta']));
	pg_assert_same('{path}{software_directory}/assets/css/themes/legacy-bootstrap3.css', $fw['compat']['content']);
	pg_assert_same('/pinegrap/assets/css/themes/legacy-bootstrap3.css', pg_design_asset_address($fw['compat']['content'], '/', 'pinegrap'));
	pg_assert_false(isset($fw['compat']['meta']));
}

function test_lbst_assets_rows()
{
	$css = array(
		_test_lbst_row('external-css', '{path}bootstrap.css'),
		_test_lbst_row('external-css', '{path}themify-icons.css'),
		_test_lbst_row('external-css', '/theme_modern.css', array('_file' => true, 'name' => 'theme_modern.css', 'pages' => array(4))),
		_test_lbst_row('css', '.col-xs-6 .btn-default{color:red}'),
		_test_lbst_row('external-css', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.3/font/bootstrap-icons.css', array('meta' => 'bootstrap-icons')),
	);
	$js = array(
		_test_lbst_row('external-js', '{path}jquery.min.js'),
		_test_lbst_row('external-js', '{path}bootstrap.min.js'),
		_test_lbst_row('external-js', '{path}scripts.js'),
		_test_lbst_row('js', "\$('[data-toggle=\"tooltip\"]').tooltip();"),
		_test_lbst_row('json', '{"data-toggle":1}'),
	);
	$copies = array('theme_modern.css' => 'theme_modern-bs5.css', 'scripts.js' => 'scripts-bs5.js');
	$a = pg_lbs_trial_assets(json_encode($css), $js, $copies, '/');
	$fw = pg_lbs_trial_framework_rows();

	// The framework sheet's place: 5.3.8, the icons, the compatibility
	// sheet; the editor's old icons row is not kept twice.
	pg_assert_same(array('bootstrap-css', 'bootstrap-icons', '', '', '', ''), _test_lbst_metas($a['css']));
	pg_assert_same(array($fw['css'], $fw['icons'], $fw['compat']), array_slice($a['css'], 0, 3));
	pg_assert_same($css[1], $a['css'][3]);
	pg_assert_same('theme_modern-bs5.css', $a['css'][4]['name']);
	pg_assert_same('/theme_modern-bs5.css', $a['css'][4]['content']);
	pg_assert_true($a['css'][4]['_file']);
	pg_assert_same(array(4), $a['css'][4]['pages'], 'a row kept to some pages stays so');
	pg_assert_same(pg_lbs_css('.col-xs-6 .btn-default{color:red}')['css'], $a['css'][5]['content']);
	pg_assert_true($a['css'][5]['content'] !== $css[3]['content']);

	// Scripts: jQuery first as before, the bundle where Bootstrap 3's script
	// was, that one kept switched off, the copy, the inline row converted,
	// a data row as it was.
	pg_assert_same($js[0], $a['js'][0]);
	pg_assert_same($fw['js'], $a['js'][1]);
	pg_assert_same('{path}bootstrap.min.js', $a['js'][2]['content']);
	pg_assert_same(false, $a['js'][2]['enabled']);
	pg_assert_same('scripts-bs5.js', $a['js'][3]['name']);
	pg_assert_same('/scripts-bs5.js', $a['js'][3]['content']);
	pg_assert_true(strpos($a['js'][4]['content'], 'bootstrap.Tooltip.getOrCreateInstance(el)') !== false);
	pg_assert_same($js[4], $a['js'][5]);
	pg_assert_same(6, count($a['js']));

	// The Legacy design's own lists are not changed.
	pg_assert_same('{path}bootstrap.css', $css[0]['content']);
}

function test_lbst_assets_framework_places()
{
	// Every classic framework sheet row goes, a disabled one and one with a
	// query string too; the framework takes the first one's place.
	$a = pg_lbs_trial_assets(array(
		_test_lbst_row('external-css', '{path}a.css'),
		_test_lbst_row('external-css', '{path}bootstrap.min.css?v=3'),
		_test_lbst_row('external-css', '{path}bootstrap.css', array('enabled' => false)),
	), array(_test_lbst_row('external-js', '{path}x.js')), array(), '/');
	pg_assert_same(array('{path}a.css'), array_slice(array_column($a['css'], 'content'), 0, 1));
	pg_assert_same(array('', 'bootstrap-css', 'bootstrap-icons', ''), _test_lbst_metas($a['css']));
	pg_assert_same(4, count($a['css']));
	// No Bootstrap 3 script: the bundle comes first.
	pg_assert_same(array('bootstrap-js', ''), _test_lbst_metas($a['js']));

	// Nothing at all: the framework rows alone.
	$a = pg_lbs_trial_assets('', 'not json', array(), '/');
	pg_assert_same(array('bootstrap-css', 'bootstrap-icons', ''), _test_lbst_metas($a['css']));
	pg_assert_same(array('bootstrap-js'), _test_lbst_metas($a['js']));
}

function test_lbst_starting_sites_assets()
{
	$checked = 0;
	foreach (array('english_default', 'turkish_default') as $site) {
		foreach (_test_dil_starting_styles(PG_FUNCTIONS_DIR . '/data/backups/' . $site . '/sql.sql') as $id => $code) {
			$f = pg_classic_style_frame($code, function ($name) {
				return array('regionType' => 'common');
			});
			$found = pg_lbs_design_assets($f['css'], $f['js'], function ($row) {
				return 0;
			});
			if (!$found['bootstrap3']) continue;
			$a = pg_lbs_trial_assets($f['css'], $f['js'], array(), '/');
			foreach ($a['css'] as $row) {
				pg_assert_false(in_array(pg_lbs_asset_name($row), array('bootstrap.css', 'bootstrap.min.css'), true) && empty($row['meta']), $site . ' style ' . $id);
			}
			pg_assert_same('bootstrap-css', $a['css'][0]['meta'], $site . ' style ' . $id . ': the framework comes before the theme');
			$at = array_search('bootstrap-js', _test_lbst_metas($a['js']), true);
			pg_assert_true($at !== false, $site . ' style ' . $id);
			pg_assert_same('bootstrap.min.js', pg_lbs_asset_name($a['js'][$at + 1]), $site . ' style ' . $id);
			pg_assert_same(false, $a['js'][$at + 1]['enabled'], $site . ' style ' . $id);
			// The trial's own lists read as no Bootstrap 3 design.
			pg_assert_false(pg_lbs_design_assets($a['css'], $a['js'], function ($row) {
				return 0;
			})['bootstrap3'], $site . ' style ' . $id);
			++$checked;
		}
	}
	pg_assert_true($checked >= 80, 'the starting sites\' home styles');
}

function test_lbst_refs_and_category()
{
	$tree = array('type' => 'root', 'children' => array(
		array('type' => 'shared_ref', 'props' => array('sharedId' => 12)),
		array('type' => 'row', 'children' => array(array('type' => 'shared_ref', 'props' => array('sharedId' => '7')))),
		array('type' => 'shared_ref', 'props' => array('sharedId' => 99)),
		array('type' => 'content', 'props' => array('text' => 'sharedId 12')),
	));
	$out = pg_lbs_trial_refs($tree, array(12 => 40, 7 => 41));
	pg_assert_same(40, $out['children'][0]['props']['sharedId']);
	pg_assert_same('41', $out['children'][1]['children'][0]['props']['sharedId']);
	pg_assert_same(99, $out['children'][2]['props']['sharedId'], 'a part with no copy keeps its place');
	pg_assert_same($tree['children'][3], $out['children'][3]);
	pg_assert_same(array(40, 41, 99), pg_ltd_shared_ids($out));
	pg_assert_same(array(), pg_lbs_trial_refs(array(), array(1 => 2)));

	pg_assert_same('legacy_bootstrap:5:12', pg_lbs_trial_part_category(5, 12));
	// Back to classic never reads a copy as a classic style's frame.
	pg_assert_same(0, pg_ltc_category_style(pg_lbs_trial_part_category(5, 12)));
}

function test_lbst_compat_sheet()
{
	$css = (string)file_get_contents(PG_FUNCTIONS_DIR . '/' . PG_LBS_COMPAT_SHEET);
	$icons = (string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/fonts/bootstrap-icons/bootstrap-icons.css');
	preg_match_all('/\.bi-([a-z0-9-]+)::before\s*\{\s*content:\s*"(\\\\[0-9a-f]+)"/', $icons, $m);
	$points = array_combine($m[1], $m[2]);
	$glyphs = 0;
	foreach (pg_lbs_icon_map() as $class => $icon) {
		if (strpos($class, 'glyphicon-') !== 0) continue;
		pg_assert_true(isset($points[$icon]), $icon);
		pg_assert_true(strpos($css, '.' . $class . '::before { content: "' . $points[$icon] . '"; }') !== false, $class);
		++$glyphs;
	}
	pg_assert_true($glyphs >= 60);
	// What the layout pass leaves as it was: a bare checkbox, a theme widget's glyphicon.
	foreach (array('checkbox', 'glyphicon') as $name) {
		pg_assert_true((bool)preg_match('/(^|\})\s*\.' . preg_quote($name, '/') . '[\s{:]/m', $css), $name);
	}
	pg_assert_contains('glyphicon', pg_lbs_layout_html('<span class="select-option"><i class="glyphicon glyphicon-ok"></i></span>')['html']);
	// What it renames on every page of a Bootstrap 5.3.8 design left the sheet.
	$renamed = array(
		'form-group'        => '<div class="form-group"><input class="form-control"></div>',
		'text-right'        => '<td class="text-right">1</td>',
		'help-block'        => '<p class="help-block">x</p>',
		'img-responsive'    => '<img class="img-responsive" src="a.png">',
		'btn-default'       => '<a class="btn btn-default">x</a>',
		'input-group-addon' => '<div class="input-group"><span class="input-group-addon">@</span><input class="form-control"></div>',
		'visible-xs-inline' => '<span class="visible-xs-inline">x</span>',
	);
	foreach ($renamed as $name => $html) {
		pg_assert_false((bool)preg_match('/(^|\})\s*\.' . preg_quote($name, '/') . '[\s{:]/m', $css), $name);
		pg_assert_false(strpos(pg_lbs_layout_html($html)['html'], $name) !== false, $name . ' is renamed');
	}
	pg_assert_false(strpos($css, '.input-group-btn') !== false, 'input-group-btn: the pass rounds its buttons');
	pg_assert_true(strpos($css, 'font-family: bootstrap-icons') !== false);
	pg_assert_same(substr_count($css, '{'), substr_count($css, '}'));
}

function test_lbst_card_html()
{
	$h = pg_lbs_trial_card_html(5, null, array('running' => false));
	pg_assert_same('', $h['below']);
	pg_assert_true(strpos($h['actions'], 'name="bootstrap_trial" value="make"') !== false);
	pg_assert_true(strpos($h['actions'], 'name="design" value="5"') !== false);
	pg_assert_true(strpos($h['actions'], 'method="post"') !== false);
	pg_assert_true(strpos($h['actions'], 'name="token"') !== false);
	pg_assert_false(strpos($h['actions'], ' disabled') !== false);
	$h = pg_lbs_trial_card_html(5, null, array('running' => true));
	pg_assert_true(strpos($h['actions'], ' disabled') !== false);

	$trial = array('trial' => 9, 'name' => 'A <b> (Bootstrap 5)', 'map' => array(), 'files' => array());
	$h = pg_lbs_trial_card_html(5, $trial, array('users' => 0));
	pg_assert_same('', $h['actions']);
	pg_assert_true(strpos($h['below'], 'id="ltd-trial-5"') !== false);
	pg_assert_true(strpos($h['below'], 'href="edit_system_style.php?id=9"') !== false);
	pg_assert_true(strpos($h['below'], 'A &lt;b&gt; (Bootstrap 5)') !== false);
	pg_assert_true(strpos($h['below'], 'href="legacy_to_designer.php?preview=5#ltd-trial-5"') !== false);
	pg_assert_true(strpos($h['below'], 'name="bootstrap_trial" value="delete"') !== false);
	pg_assert_true(strpos($h['below'], '<details') !== false, 'a second button confirms the delete');

	$h = pg_lbs_trial_card_html(5, $trial, array('users' => 2));
	pg_assert_false(strpos($h['below'], 'value="delete"') !== false, 'no delete while pages use it');
	pg_assert_true(strpos($h['below'], h(lang(array('string' => '{var:1} pages or folders use it: it cannot be deleted.', 'vars' => array('2'))))) !== false);

	$pages = array(array('id' => 3, 'name' => 'home'), array('id' => 4, 'name' => 'about'));
	$h = pg_lbs_trial_card_html(5, $trial, array('users' => 0, 'preview' => array('pages' => $pages, 'page' => 4)));
	pg_assert_true(strpos($h['below'], 'legacy_to_designer.php#ltd-bootstrap-5') !== false, 'Hide the preview');
	pg_assert_true(strpos($h['below'], '<option value="4" selected>about</option>') !== false);
	pg_assert_true(strpos($h['below'], 'design_preview.php?page_id=4&amp;bootstrap5=9"') !== false);
	pg_assert_true(strpos($h['below'], 'design_preview.php?page_id=4"') !== false);
	pg_assert_same(2, substr_count($h['below'], 'data-width="390" data-height="844" style="aspect-ratio: 390 / 844"'));
	pg_assert_same(4, substr_count($h['below'], 'data-pg-live-picture'));
	// A page that is not the design's shows the first one.
	$h = pg_lbs_trial_card_html(5, $trial, array('users' => 0, 'preview' => array('pages' => $pages, 'page' => 77)));
	pg_assert_true(strpos($h['below'], 'design_preview.php?page_id=3&amp;bootstrap5=9"') !== false);
	$h = pg_lbs_trial_card_html(5, $trial, array('users' => 0, 'preview' => array('pages' => array(), 'page' => 0)));
	pg_assert_false(strpos($h['below'], 'data-pg-live-picture') !== false);

	// The card puts both beside and under the design's line.
	$designs = array(array('id' => 5, 'name' => 'S', 'pages' => 1, 'assets' => array('bootstrap3' => true, 'framework' => 'bootstrap.css', 'version' => 3)));
	$card = pg_lbs_card_html($designs, 0, null, array(5 => array('actions' => '<i id="x-actions"></i>', 'below' => '<i id="x-below"></i>')));
	pg_assert_true(strpos($card, '<i id="x-actions"></i>') !== false && strpos($card, '<i id="x-below"></i>') !== false);
	pg_assert_true(strpos($card, '<i id="x-actions"></i>') < strpos($card, '<i id="x-below"></i>'));
}

function test_lbst_preview_override_and_request()
{
	pg_assert_same(null, pg_lbs_preview_override());
	pg_lbs_preview_override(array('page_id' => 3, 'style_id' => 9, 'code' => 'x'));
	pg_assert_same(9, pg_lbs_preview_override()['style_id']);
	pg_lbs_preview_override(null);
	pg_assert_same(null, pg_lbs_preview_override());

	$r = pg_design_preview_request(array('page_id' => '12', 'bootstrap5' => '30'));
	pg_assert_same(array('page', 12, 30), array($r['kind'], $r['page_id'], $r['trial']));
	pg_assert_same(0, pg_design_preview_request(array('page_id' => '12', 'bootstrap5' => '3x'))['trial']);
	pg_assert_same(0, pg_design_preview_request(array('page_id' => '12', 'bootstrap5' => '0'))['trial']);
	pg_assert_same(0, pg_design_preview_request(array('page_id' => '12'))['trial']);
	pg_assert_same(0, pg_design_preview_request(array('template' => 'x', 'bootstrap5' => '30'))['trial']);
}

function test_lbst_wiring()
{
	$gpc = (string)file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');
	$read = strpos($gpc, 'pg_lbs_preview_override()');
	$style = strpos($gpc, "\$style_id = (int) \$pg_lbs_preview['style_id'];");
	$query = strpos($gpc, 'files.name AS style_theme_name');
	pg_assert_true($read !== false && $style !== false && $query !== false && $read < $style && $style < $query, 'the trial\'s row is read in place of the page\'s');
	pg_assert_true(strpos($gpc, "(int) \$pg_lbs_preview['page_id'] === (int) \$page_id") !== false, 'for that page only');

	$preview = (string)file_get_contents(PG_FUNCTIONS_DIR . '/design_preview.php');
	pg_assert_true(strpos($preview, "pg_designer_is_full(\$user) ? pg_lbs_trial_of((int) \$page_row['page_style'])") !== false);
	pg_assert_true(strpos($preview, "header('Cache-Control: no-store');\n    pg_lbs_preview_override(") !== false);
	pg_assert_true(strpos($preview, 'pg_design_preview_strip_scripts($render[\'html\'])') !== false, 'a picture runs no script');

	$screen = (string)file_get_contents(PG_FUNCTIONS_DIR . '/legacy_to_designer.php');
	$token = strpos($screen, 'validate_token_field();');
	$make = strpos($screen, 'pg_lbs_trial_make(');
	pg_assert_true($token !== false && $make !== false && $token < $make);
	pg_assert_true(strpos($screen, 'pg_lbs_trial_delete(') !== false);
	pg_assert_true(strpos($screen, "make or delete a trial design when it has ended") !== false);

	// The module writes no page and no row of the Legacy design: style rows
	// only through save_system_style() and the trial's own delete.
	$src = (string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legacy_bootstrap_trial.php');
	pg_assert_false((bool)preg_match('/\bUPDATE\s+(page|style|files)\b/i', $src));
	pg_assert_same(1, preg_match_all('/DELETE FROM style\b/', $src));
	pg_assert_true(strpos($src, "db(\"DELETE FROM style WHERE style_id = '\$trial_id'\");") !== false);
}
