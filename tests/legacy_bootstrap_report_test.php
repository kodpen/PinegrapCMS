<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the "Towards Bootstrap 5" card (includes/fn/legacy_bootstrap_report.php):
 * an asset row's file name and version, Bootstrap 3 found by the framework
 * sheet's row, the files left out, the scripts' Bootstrap 3 calls, the
 * trees' and a sheet's report, the card's markup and the screen's wiring.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_bootstrap_report.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/classic_style_design.php');
// The starting sites' classic styles (_test_dil_starting_styles()).
require_once(__DIR__ . '/designer_import_legacy_test.php');

function _test_lbsr_row($type, $content, $extra = array())
{
	return array_merge(array('id' => 'r' . md5($type . $content), 'name' => $content, 'type' => $type, 'content' => $content, 'enabled' => true), $extra);
}

function _test_lbsr_no_banner($row)
{
	return 0;
}

function test_lbsr_asset_name()
{
	pg_assert_same('bootstrap.css', pg_lbs_asset_name(_test_lbsr_row('external-css', '{path}bootstrap.css')));
	pg_assert_same('bootstrap.min.css', pg_lbs_asset_name(_test_lbsr_row('external-css', 'https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css?v=2#x')));
	pg_assert_same('bootstrap.min.css', pg_lbs_asset_name(_test_lbsr_row('external-css', '/BootStrap.Min.CSS')));
	pg_assert_same('livesite.src.css', pg_lbs_asset_name(_test_lbsr_row('external-css', '{path}{software_directory}/livesite.src.css')));
	pg_assert_same('bootstrap.min.js', pg_lbs_asset_name(_test_lbsr_row('external-js', '{path}bootstrap.min.js')));
	// An inline row has no file.
	pg_assert_same('', pg_lbs_asset_name(_test_lbsr_row('css', '.a{}')));
	pg_assert_same('', pg_lbs_asset_name(null));
}

function test_lbsr_versions()
{
	pg_assert_same(3, pg_lbs_address_version('https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css'));
	pg_assert_same(3, pg_lbs_address_version('//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css'));
	pg_assert_same(5, pg_lbs_address_version('https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css'));
	pg_assert_same(4, pg_lbs_address_version('/files/bootstrap-4.6.2-dist/css/bootstrap.min.css'));
	pg_assert_same(0, pg_lbs_address_version('{path}bootstrap.css'));

	pg_assert_same(3, pg_lbs_sheet_version("/*!\n * Bootstrap v3.3.5 (http://getbootstrap.com)\n */"));
	pg_assert_same(5, pg_lbs_sheet_version("@charset \"UTF-8\";/*!\n * Bootstrap  v5.3.8 (https://getbootstrap.com/)"));
	pg_assert_same(0, pg_lbs_sheet_version('/* a theme */ .btn{}'));
	// The starting sites' own file.
	pg_assert_same(3, pg_lbs_sheet_version((string)file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/english_default/files/bootstrap.css', false, null, 0, 1024)));
}

function test_lbsr_design_assets_bootstrap3()
{
	$css = array(
		_test_lbsr_row('external-css', '{path}bootstrap.css'),
		_test_lbsr_row('external-css', '{path}themify-icons.css'),
		_test_lbsr_row('external-css', '{path}theme-foundrygreen.css', array('_file' => true, 'name' => 'theme-foundrygreen.css')),
		_test_lbsr_row('css', '.hero{color:red}'),
		_test_lbsr_row('external-css', '{path}off.css', array('enabled' => false)),
		_test_lbsr_row('external-css', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css', array('meta' => 'bootstrap-icons')),
	);
	$js = array(
		_test_lbsr_row('external-js', '{path}jquery.min.js'),
		_test_lbsr_row('external-js', '{path}bootstrap.min.js'),
		_test_lbsr_row('external-js', '{path}scripts.js'),
	);
	$a = pg_lbs_design_assets(json_encode($css), $js, '_test_lbsr_no_banner');
	pg_assert_true($a['bootstrap3']);
	pg_assert_same('bootstrap.css', $a['framework']);
	pg_assert_same(0, $a['version']);
	pg_assert_same(array('bootstrap.css', 'bootstrap.min.js'), array_map('pg_lbs_asset_name', $a['left_out']));
	// The disabled row and the editor's icon row are not listed.
	pg_assert_same(array('themify-icons.css', 'theme-foundrygreen.css', ''), array_map('pg_lbs_asset_name', $a['sheets']));
	pg_assert_same(array('jquery.min.js', 'scripts.js'), array_map('pg_lbs_asset_name', $a['scripts']));

	// A CDN address with a 3.x version, the banner not asked.
	$asked = 0;
	$a = pg_lbs_design_assets(array(_test_lbsr_row('external-css', 'https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css')), array(), function ($row) use (&$asked) {
		++$asked;
		return 4;
	});
	pg_assert_true($a['bootstrap3']);
	pg_assert_same(3, $a['version']);
	pg_assert_same(0, $asked);
}

function test_lbsr_design_assets_not_bootstrap3()
{
	// Materialize shares row, container, btn and badge with Bootstrap 3:
	// only the sheet's row tells them apart.
	$a = pg_lbs_design_assets(array(_test_lbsr_row('external-css', '{path}materialize.min.css')), array(_test_lbsr_row('external-js', '{path}materialize.min.js')), '_test_lbsr_no_banner');
	pg_assert_false($a['bootstrap3']);
	pg_assert_same('', $a['framework']);
	pg_assert_same(array(), $a['left_out']);
	pg_assert_same(array(), $a['sheets']);

	// A bootstrap.min.css whose banner is Bootstrap 4.
	$a = pg_lbs_design_assets(array(_test_lbsr_row('external-css', '{path}bootstrap.min.css')), array(), function ($row) {
		return 4;
	});
	pg_assert_false($a['bootstrap3']);
	pg_assert_same('bootstrap.min.css', $a['framework']);
	pg_assert_same(4, $a['version']);

	// Bootstrap 5 from a CDN, a disabled Bootstrap 3 sheet, an editor's
	// framework row, an inline style holding the name, no rows at all.
	pg_assert_false(pg_lbs_design_assets(array(_test_lbsr_row('external-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css')), array(), '_test_lbsr_no_banner')['bootstrap3']);
	pg_assert_false(pg_lbs_design_assets(array(_test_lbsr_row('external-css', '{path}bootstrap.css', array('enabled' => false))), array(), '_test_lbsr_no_banner')['bootstrap3']);
	pg_assert_false(pg_lbs_design_assets(array(_test_lbsr_row('external-css', '{path}bootstrap.min.css', array('meta' => 'bootstrap-css'))), array(), '_test_lbsr_no_banner')['bootstrap3']);
	pg_assert_false(pg_lbs_design_assets(array(_test_lbsr_row('css', '@import "bootstrap.css";')), array(), '_test_lbsr_no_banner')['bootstrap3']);
	pg_assert_false(pg_lbs_design_assets('', '', '_test_lbsr_no_banner')['bootstrap3']);
	pg_assert_false(pg_lbs_design_assets('not json', null, '_test_lbsr_no_banner')['bootstrap3']);
}

function test_lbsr_starting_sites_heads()
{
	$sites = 0;
	foreach (array('english_default', 'turkish_default') as $site) {
		$bootstrap3 = 0;
		$other = 0;
		foreach (_test_dil_starting_styles(PG_FUNCTIONS_DIR . '/data/backups/' . $site . '/sql.sql') as $id => $code) {
			$f = pg_classic_style_frame($code, function ($name) {
				return array('regionType' => 'common');
			});
			$a = pg_lbs_design_assets($f['css'], $f['js'], function ($row) use ($site) {
				$name = pg_lbs_site_file($row, '/');
				return pg_lbs_sheet_version((string)@file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/' . $site . '/files/' . $name, false, null, 0, 1024));
			});
			if (!$a['bootstrap3']) {
				++$other;
				continue;
			}
			++$bootstrap3;
			pg_assert_same(array('bootstrap.css', 'bootstrap.min.js'), array_map('pg_lbs_asset_name', $a['left_out']), $site . ' style ' . $id);
			pg_assert_same(3, $a['version'], $site . ' style ' . $id . ': the banner');
		}
		pg_assert_true($bootstrap3 >= 40, $site . ': the home styles load Bootstrap 3');
		// The e-mail styles draw their own tables with no Bootstrap sheet.
		pg_assert_true($other > 0, $site . ': styles with no Bootstrap 3 sheet');
		++$sites;
	}
	pg_assert_same(2, $sites);
}

function test_lbsr_site_file()
{
	pg_assert_same('scripts.js', pg_lbs_site_file(_test_lbsr_row('external-js', '{path}scripts.js'), '/'));
	pg_assert_same('theme.css', pg_lbs_site_file(_test_lbsr_row('external-css', '/site/theme.css', array('_file' => true, 'name' => 'theme.css')), '/site/'));
	pg_assert_same('theme.css', pg_lbs_site_file(_test_lbsr_row('external-css', '/site/theme.css?v=3'), '/site/'));
	// A CDN, a software file, a name with a path, an inline row.
	pg_assert_same('', pg_lbs_site_file(_test_lbsr_row('external-css', '//cdnjs.cloudflare.com/ajax/libs/flexslider/2.3.0/flexslider.css'), '/'));
	pg_assert_same('', pg_lbs_site_file(_test_lbsr_row('external-css', '{path}{software_directory}/livesite.src.css'), '/'));
	pg_assert_same('', pg_lbs_site_file(_test_lbsr_row('external-css', '/x', array('_file' => true, 'name' => '../etc/passwd')), '/site/'));
	pg_assert_same('', pg_lbs_site_file(_test_lbsr_row('js', 'alert(1)'), '/'));

	// A row is shown by its panel name, else by its address.
	pg_assert_same('flexslider.css', pg_lbs_row_label(_test_lbsr_row('external-css', '//cdn/flexslider.css', array('name' => 'flexslider.css'))));
	pg_assert_same('//cdn/flexslider.css', pg_lbs_row_label(_test_lbsr_row('external-css', '//cdn/flexslider.css', array('name' => ' '))));
}

function test_lbsr_script_calls()
{
	pg_assert_same(array('tooltip()' => 1, 'data-toggle' => 1), pg_lbs_script_calls("\$('[data-toggle=\"tooltip\"]').tooltip();"));
	pg_assert_same(array('modal()' => 2, 'tab()' => 1, 'data-target' => 1),
		pg_lbs_script_calls("\$('#a').modal('show');\njQuery(this).tab('show');\n\$(x).modal ('hide');\nvar t = el.getAttribute('data-target');"));
	// Comments, 5.3.8's own calls and names, and plain function names are not counted.
	pg_assert_same(array(), pg_lbs_script_calls("// \$('[data-toggle=\"tooltip\"]').tooltip();\n/* \$(a).modal(); */\nbootstrap.Tooltip.getOrCreateInstance(el);\n\$('[data-bs-toggle]');\nmodal(1); \$('.x').flexslider({});\nvar n = 'my-data-toggle';"));
	pg_assert_same(array(), pg_lbs_script_calls(''));
	// The starting sites' theme script: the one tooltip line the LB1 trial met.
	$calls = pg_lbs_script_calls((string)file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/english_default/files/scripts.js'));
	pg_assert_same(1, $calls['tooltip()'] ?? 0);
	pg_assert_same(1, $calls['data-toggle'] ?? 0);
}

function test_lbsr_trees_report()
{
	$page = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'container', 'props' => array('cssClass' => 'col-xs-6 text-right col-sm-push-8'), 'children' => array()),
		array('type' => 'shared_ref', 'props' => array('sharedId' => 4, 'cssClass' => 'pull-left')),
	));
	$part = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'container', 'props' => array('cssClass' => 'col-sm-push-8 glyphicon glyphicon-not-a-name'), 'children' => array()),
	));
	$plain = array('type' => 'root', 'props' => array('cssClass' => 'hero'), 'children' => array());
	$r = pg_lbs_trees_report(array(
		array('label' => 'home', 'tree' => $page),
		array('label' => 'Main top (shared part)', 'tree' => $part),
		array('label' => 'about', 'tree' => $plain),
		array('label' => 'broken', 'tree' => null),
	));
	pg_assert_same(3, $r['trees']);
	pg_assert_same(1, $r['changed'], 'only the page has a name to convert');
	pg_assert_same(array('col-xs-6' => 1, 'text-right' => 1), $r['converted'], 'a placed part is left to its own tree');
	pg_assert_same(array('col-sm-push-8' => 2), $r['by_hand']);
	pg_assert_same(array('glyphicon-not-a-name' => 1), $r['kept']);
	pg_assert_same(array('converted' => 2, 'kept' => 1, 'by_hand' => 2), $r['totals']);
	pg_assert_same(array('col-sm-push-8' => array('home', 'Main top (shared part)')), $r['where']['by_hand']);
	pg_assert_same(array('glyphicon-not-a-name' => array('Main top (shared part)')), $r['where']['kept']);

	$none = pg_lbs_trees_report(array());
	pg_assert_same(0, $none['trees']);
	pg_assert_same(array('converted' => 0, 'kept' => 0, 'by_hand' => 0), $none['totals']);
}

function test_lbsr_sheet_result()
{
	$s = pg_lbs_sheet_result('theme.css', ".bg-secondary { background: #eee; }\n.label, .text-right { x: 1 }\n.hero { y: 2 }", true);
	pg_assert_true($s['read']);
	pg_assert_true($s['theme']);
	pg_assert_same(3, $s['renamed']);
	pg_assert_same(array('bg-secondary' => 1, 'label' => 1, 'text-right' => 1), $s['names']);

	$s = pg_lbs_sheet_result('plain.css', '.hero { y: 2 }', false);
	pg_assert_same(0, $s['renamed']);
	pg_assert_true($s['read']);

	$s = pg_lbs_sheet_result('//cdn/x.css', null, false);
	pg_assert_false($s['read']);
	pg_assert_same(0, $s['renamed']);

	// The starting sites' activated theme: its bg-* and label rules get renamed.
	$s = pg_lbs_sheet_result('theme-foundrygreen.css', (string)file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/english_default/files/theme-foundrygreen.css'), true);
	pg_assert_true($s['renamed'] > 100, 'the theme sheet has Bootstrap 3 and taken-over names');
	pg_assert_true(isset($s['names']['bg-secondary']));
}

function test_lbsr_card_html()
{
	pg_assert_same('', pg_lbs_card_html(array(), 0, null));

	$bs3 = array('bootstrap3' => true, 'framework' => 'bootstrap.css', 'version' => 3, 'left_out' => array(), 'sheets' => array(), 'scripts' => array());
	$mat = array('bootstrap3' => false, 'framework' => '', 'version' => 0, 'left_out' => array(), 'sheets' => array(), 'scripts' => array());
	$bs4 = array('bootstrap3' => false, 'framework' => 'bootstrap.min.css', 'version' => 4, 'left_out' => array(), 'sheets' => array(), 'scripts' => array());
	$designs = array(
		array('id' => 7, 'name' => 'home-1 set <b>', 'pages' => 148, 'assets' => $bs3),
		array('id' => 9, 'name' => 'material-about set', 'pages' => 2, 'assets' => $mat),
		array('id' => 11, 'name' => 'four', 'pages' => 1, 'assets' => $bs4),
	);
	$html = pg_lbs_card_html($designs, 0, null);
	pg_assert_contains('id="ltd-bootstrap"', $html);
	pg_assert_contains('home-1 set &lt;b&gt;', $html);
	pg_assert_false(strpos($html, 'home-1 set <b>') !== false, 'the name is escaped');
	pg_assert_contains('href="legacy_to_designer.php?bootstrap=7#ltd-bootstrap-7"', $html);
	pg_assert_false(strpos($html, 'bootstrap=9') !== false, 'no report for a design that is not Bootstrap 3');
	pg_assert_false(strpos($html, 'bootstrap=11') !== false, 'no report for another Bootstrap');
	pg_assert_contains('does not use Bootstrap 3', $html);
	pg_assert_contains('Bootstrap 4, not Bootstrap 3', $html);

	$report = array(
		'id' => 7, 'name' => 'home-1 set', 'pages' => 2, 'parts' => 1,
		'trees' => pg_lbs_trees_report(array(array('label' => 'home <i>', 'tree' => array('type' => 'root', 'props' => array('cssClass' => 'col-sm-push-8 text-right'), 'children' => array())))),
		'sheets' => array(pg_lbs_sheet_result('theme.css', '.bg-dark{}', true), pg_lbs_sheet_result('//cdn/x.css', null, false), pg_lbs_sheet_result('plain.css', '.a{}', false)),
		'scripts' => array(array('label' => 'scripts.js', 'calls' => array('tooltip()' => 1))),
		'left_out' => array(array('kind' => 'css', 'label' => 'bootstrap.css'), array('kind' => 'js', 'label' => 'bootstrap.min.js')),
	);
	$html = pg_lbs_card_html($designs, 7, $report);
	pg_assert_contains('Hide the report', $html);
	pg_assert_contains('<code>col-sm-push-8</code> 1 <span class="fg-secondary">home &lt;i&gt;</span>', $html);
	pg_assert_contains('a copy of its own', $html);
	pg_assert_contains('Not read', $html);
	pg_assert_contains('loaded as it is', $html);
	pg_assert_contains('tooltip() 1', $html);
	pg_assert_contains('bootstrap.min.js</span>: Bootstrap 3&#039;s script', $html);
	pg_assert_contains('Nothing has been written', $html);
	// The report of an asked design that is gone shows only the lines.
	pg_assert_false(strpos(pg_lbs_card_html($designs, 7, null), 'Hide the report') !== false);
}

function test_lbsr_names_html_limit()
{
	$names = array();
	for ($i = 1; $i <= 45; $i++) $names['n' . $i] = 46 - $i;
	$html = pg_lbs_names_html($names, array('n1' => array('a', 'b', 'c', 'd', 'e', 'f', 'g')));
	pg_assert_same(41, substr_count($html, '<li'));
	pg_assert_contains('5 more names', $html);
	pg_assert_contains('a, b, c, d, e and 2 more', $html);
}

function test_lbsr_screen_wiring()
{
	$screen = (string)file_get_contents(PG_FUNCTIONS_DIR . '/legacy_to_designer.php');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_bootstrap_report.php');", $screen);
	pg_assert_contains("\$bootstrap_open = (int)(\$_GET['bootstrap'] ?? 0);", $screen);
	pg_assert_contains('$bootstrap_designs = pg_lbs_designs();', $screen);
	pg_assert_contains('pg_lbs_card_html($bootstrap_designs, $bootstrap_open, $bootstrap_open > 0 ? pg_lbs_design_report($bootstrap_open, $bootstrap_designs) : null)', $screen);
	pg_assert_contains('$output_back . $output_bootstrap . $output_classic', $screen);
	// The report reads only: no write in the module.
	$module = (string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legacy_bootstrap_report.php');
	pg_assert_false((bool)preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE)\s/', $module), 'no SQL write');
	pg_assert_false(strpos($module, 'file_put_contents') !== false || strpos($module, 'pg_file_write') !== false, 'no file write');
}
