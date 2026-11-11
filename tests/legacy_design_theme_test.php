<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the theme file of a design moved from a classic style
 * (includes/fn/legacy_design_theme.php): which theme a design loads, the
 * asset row that already links it, the classic head that loads CSS after
 * its theme, and the dry run's warning about it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_design_theme.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_plan.php');

function _test_ldt_facts($over = array())
{
	return array_merge(array(
		'own'         => null,
		'classic'     => true,
		'device'      => 'desktop',
		'desktop'     => array('id' => 4, 'name' => 'theme.css'),
		'mobile'      => array('id' => 9, 'name' => 'mobile.css'),
		'rows'        => array(),
		'output_path' => '/',
		'page_id'     => null,
	), $over);
}

function _test_ldt_row($content, $extra = array())
{
	return array_merge(array('id' => 'r1', 'name' => 'x', 'type' => 'external-css', 'content' => $content, 'enabled' => true), $extra);
}

function test_legacy_design_theme_own_file_wins()
{
	$t = pg_legacy_design_theme(_test_ldt_facts(array('own' => array('id' => 7, 'name' => 'own.css'))));
	pg_assert_same(array('id' => 7, 'name' => 'own.css', 'source' => 'own'), $t);

	// A design of the visual designer from the start keeps its own file too.
	$t = pg_legacy_design_theme(_test_ldt_facts(array('classic' => false, 'own' => array('id' => 7, 'name' => 'own.css'))));
	pg_assert_same('own', $t['source']);
}

function test_legacy_design_theme_activated_theme_for_a_moved_design()
{
	pg_assert_same(array('id' => 4, 'name' => 'theme.css', 'source' => 'classic'), pg_legacy_design_theme(_test_ldt_facts()));

	// The mobile style: the mobile theme, the desktop one when none is activated.
	pg_assert_same('mobile.css', pg_legacy_design_theme(_test_ldt_facts(array('device' => 'mobile')))['name']);
	pg_assert_same('theme.css', pg_legacy_design_theme(_test_ldt_facts(array('device' => 'mobile', 'mobile' => null)))['name']);

	// No activated theme: none, as the classic head printed none.
	pg_assert_same(null, pg_legacy_design_theme(_test_ldt_facts(array('desktop' => null, 'mobile' => null))));
	pg_assert_same(null, pg_legacy_design_theme(_test_ldt_facts(array('desktop' => array('id' => 0, 'name' => '')))));
}

function test_legacy_design_theme_none_for_a_design_not_moved()
{
	pg_assert_same(null, pg_legacy_design_theme(_test_ldt_facts(array('classic' => false))));
}

function test_legacy_design_theme_none_when_a_row_links_it()
{
	foreach (array('{path}theme.css', '{PATH}Theme.css?v=3', '/theme.css') as $ref) {
		$t = pg_legacy_design_theme(_test_ldt_facts(array('rows' => array(_test_ldt_row($ref)))));
		pg_assert_same(null, $t, $ref . ': the row already loads the theme');
	}
	$t = pg_legacy_design_theme(_test_ldt_facts(array('output_path' => '/site/', 'rows' => array(_test_ldt_row('/site/theme.css')))));
	pg_assert_same(null, $t, 'the row under the site path');

	// Rows that print nothing, or another file, leave it in.
	$keep = array(
		'another file'      => _test_ldt_row('{path}other.css'),
		'switched off'      => _test_ldt_row('{path}theme.css', array('enabled' => false)),
		'inline css'        => _test_ldt_row('{path}theme.css', array('type' => 'css')),
		'a framework row'   => _test_ldt_row('{path}theme.css', array('meta' => 'bootstrap-css')),
		'a CDN of the name' => _test_ldt_row('https://cdn.example.com/theme.css'),
		'below a folder'    => _test_ldt_row('{path}css/theme.css'),
	);
	foreach ($keep as $label => $row) {
		$t = pg_legacy_design_theme(_test_ldt_facts(array('rows' => array($row))));
		pg_assert_same('theme.css', $t['name'], $label);
	}

	// A row kept to some pages links it only there.
	$rows = array(_test_ldt_row('{path}theme.css', array('pages' => array('12'))));
	pg_assert_same(null, pg_legacy_design_theme(_test_ldt_facts(array('rows' => $rows, 'page_id' => 12))));
	pg_assert_same('theme.css', pg_legacy_design_theme(_test_ldt_facts(array('rows' => $rows, 'page_id' => 13)))['name']);

	// The stored JSON is read as well as the decoded list.
	$json = json_encode(array(_test_ldt_row('{path}theme.css')));
	pg_assert_same(null, pg_legacy_design_theme(_test_ldt_facts(array('rows' => $json))));
	pg_assert_same('theme.css', pg_legacy_design_theme(_test_ldt_facts(array('rows' => 'not json')))['name']);
}

function test_legacy_style_css_after_theme()
{
	$head = function ($inner) {
		return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta_tags></meta_tags>' . $inner . '</head><body><pregion></pregion><style>.b{}</style></body></html>';
	};
	$after = array(
		'a link'          => '<link href="{path}a.css" rel="stylesheet"><stylesheet></stylesheet><link rel="stylesheet" href="{path}b.css">',
		'a quoted rel'    => '<stylesheet></stylesheet><link href="{path}b.css" rel=\'stylesheet\' type="text/css">',
		'a bare rel'      => '<stylesheet></stylesheet><link rel=stylesheet href="{path}b.css">',
		'a style block'   => '<stylesheet></stylesheet><style>.a{color:red}</style>',
		'the tag in caps' => '<STYLESHEET></STYLESHEET><STYLE>.a{}</STYLE>',
	);
	foreach ($after as $label => $inner) {
		pg_assert_true(pg_legacy_style_css_after_theme($head($inner)), $label);
	}
	$not = array(
		'all before'        => '<link href="{path}a.css" rel="stylesheet"><style>.a{}</style><stylesheet></stylesheet>',
		'no tag'            => '<link href="{path}a.css" rel="stylesheet">',
		'commented out'     => '<stylesheet></stylesheet><!-- <link rel="stylesheet" href="{path}b.css"> -->',
		'a script'          => '<stylesheet></stylesheet><script>var s = "<style>";</script>',
		'an icon link'      => '<stylesheet></stylesheet><link rel="icon" href="{path}favicon.ico">',
		'a preconnect'      => '<stylesheet></stylesheet><link rel="preconnect" href="https://fonts.gstatic.com">',
	);
	foreach ($not as $label => $inner) {
		pg_assert_false(pg_legacy_style_css_after_theme($head($inner)), $label);
	}
	// CSS in the body stays after the theme in the design too.
	pg_assert_false(pg_legacy_style_css_after_theme('<head><stylesheet></stylesheet></head><body><link rel="stylesheet" href="x.css"></body>'));
	pg_assert_false(pg_legacy_style_css_after_theme(''));
}

// The starting sites' classic styles print their theme last in the head,
// but for two (home-product, home-music) that link their font after it.
function test_legacy_style_css_after_theme_starting_sites()
{
	foreach (array('english_default', 'turkish_default') as $site) {
		$sql = (string)file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/' . $site . '/sql.sql');
		preg_match_all('~<head>.*?</head>~s', stripcslashes($sql), $m);
		pg_assert_true(count($m[0]) > 10, $site . ': classic heads found');
		$after = array();
		foreach ($m[0] as $head) {
			if (!pg_legacy_style_css_after_theme($head)) continue;
			pg_assert_true(preg_match('~<stylesheet></stylesheet>.*\{path\}font-(roboto|dosis)\.css~s', $head) === 1, $site . ': a font after the theme');
			$after[] = $head;
		}
		pg_assert_same(2, count($after), $site . ': two heads load CSS after the theme');
	}
}

function test_legacy_design_theme_dry_run_warning()
{
	$facts = array('refusal' => '', 'common' => array(), 'pages' => array(), 'warnings' => array(), 'designer_made' => array(),
	               'frame_exists' => false, 'frame' => array('designer' => array(), 'widgets' => array(), 'page_regions' => 0));
	pg_assert_same(array(), pg_ltd_plan_style($facts)['warnings']);

	$facts['theme_after'] = 'theme.css';
	$w = pg_ltd_plan_style($facts)['warnings'];
	pg_assert_same(1, count($w));
	pg_assert_true(strpos($w[0], 'theme.css') !== false, 'the warning names the theme');

	// A refused style is skipped before any warning of the move.
	$facts['refusal'] = 'x';
	pg_assert_same(array(), pg_ltd_plan_style($facts)['warnings']);
}
