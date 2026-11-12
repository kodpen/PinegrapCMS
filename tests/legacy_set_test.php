<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the sets of the move to the visual designer
 * (includes/fn/legacy_to_designer.php, legacy_to_designer_plan.php): the
 * stylesheets and scripts a classic style loads, its set key, the report's
 * styles grouped into sets, a set's asset rows and its dry run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_plan.php');

function _lgs_head($css, $js, $extra = '')
{
	$out = '<html><head><title>x</title>';
	foreach ($css as $href) $out .= '<link rel="stylesheet" href="' . $href . '">';
	$out .= '<stylesheet></stylesheet>' . $extra . '</head><body><pregion></pregion>';
	foreach ($js as $src) $out .= '<script src="' . $src . '"></script>';
	return $out . '</body></html>';
}

function _lgs_entry($id, $name, $key, $over = array())
{
	return array_merge(array(
		'id' => $id, 'name' => $name, 'state' => 'ready', 'set_key' => $key, 'mobile' => 0,
		'own' => array(array('page_id' => $id * 10, 'page_name' => $name . '-a')), 'folder' => array(),
		'refs' => array('css' => array('/a.css'), 'js' => array()), 'theme' => '', 'theme_id' => 0, 'collection' => 'a',
		'social_position' => 'bottom_left', 'layout_type' => '',
	), $over);
}

function test_lgs_style_refs_in_order_filled_once()
{
	$code = '<html><head>'
		. '<link rel="stylesheet" href="{path}bootstrap.css">'
		. '<link href="{PATH}theme.css?v=2&amp;x=1" rel="Stylesheet">'
		. '<link rel="icon" href="{path}favicon.ico">'
		. '<!-- <link rel="stylesheet" href="{path}old.css"> -->'
		. '<!--[if lt IE 9]><script src="{path}html5shiv.js"></script><![endif]-->'
		. "<link rel='stylesheet' href='{software_directory}/x.css'>"
		. '<style>body { color: red }</style>'
		. '<script>var a = "<script src=\"no.js\"></script>";</script>'
		. '<link rel="stylesheet" href="{path}bootstrap.css">'
		. '</head><body><script src="{path}{software_directory}/assets/js/jquery.js"></script>'
		. '<script type="module" src="https://cdn.example.com/m.js"></script></body></html>';
	$refs = pg_ltd_style_refs($code, '/site/', 'pinegrap');
	pg_assert_same(array('/site/bootstrap.css', '/site/theme.css?v=2&x=1', 'pinegrap/x.css'), $refs['css'],
		'stylesheets in order, placeholders filled without case, entities decoded, comments and other links left out, each once');
	pg_assert_same(array('/site/pinegrap/assets/js/jquery.js', 'https://cdn.example.com/m.js'), $refs['js'],
		'scripts with an address in order; inline ones and those inside a comment left out');
	pg_assert_same(array('css' => array(), 'js' => array()), pg_ltd_style_refs('', '/', 'pinegrap'));
}

function test_lgs_set_key_counts_addresses_order_collection_theme_not_inline()
{
	$row = array('collection' => 'a', 'theme_id' => 4, 'layout_type' => '', 'social_networking_position' => 'top_left');
	$a = pg_ltd_set_key(pg_ltd_style_refs(_lgs_head(array('{path}a.css', '{path}b.css'), array('{path}j.js'), '<style>p{}</style>'), '/', 'pinegrap'), $row);
	$b = pg_ltd_set_key(pg_ltd_style_refs(_lgs_head(array('/a.css', '/b.css'), array('/j.js'), '<script>var x;</script>'), '/', 'pinegrap'), $row);
	pg_assert_same(1, preg_match('/^[0-9a-f]{16}$/', $a), 'sixteen hex characters');
	pg_assert_same($a, $b, '{path} filled the way a design row fills it; inline blocks do not count');

	$swap = pg_ltd_set_key(pg_ltd_style_refs(_lgs_head(array('/b.css', '/a.css'), array('/j.js')), '/', 'pinegrap'), $row);
	pg_assert_false($a === $swap, 'order counts');
	$refs = pg_ltd_style_refs(_lgs_head(array('/a.css', '/b.css'), array('/j.js')), '/', 'pinegrap');
	pg_assert_same($a, pg_ltd_set_key($refs, $row));
	foreach (array('collection' => 'b', 'theme_id' => 5) as $field => $value) {
		$other = $row;
		$other[$field] = $value;
		pg_assert_false($a === pg_ltd_set_key($refs, $other), $field . ' counts');
	}
	$other = array_merge($row, array('layout_type' => 'system', 'social_networking_position' => 'disabled'));
	pg_assert_same($a, pg_ltd_set_key($refs, $other), 'settings the design holds once do not split a set: the dry run shows them');
	$moved = array('css' => array('/a.css'), 'js' => array('/b.css', '/j.js'));
	pg_assert_false($a === pg_ltd_set_key($moved, $row), 'a stylesheet and a script never swap places');
}

function test_lgs_sets_group_by_key_and_leave_out_who_moves_alone()
{
	$report = array(
		_lgs_entry(1, 'about', 'k1'),
		_lgs_entry(2, 'blog', 'k2'),
		_lgs_entry(3, 'contact', 'k1', array('folder' => array(array('page_id' => 77, 'page_name' => 'f')))),
		_lgs_entry(4, 'gallery', 'k2'),
		_lgs_entry(5, 'home', 'k1', array('social_position' => 'disabled')),
		_lgs_entry(6, 'mobile', 'k1', array('mobile' => 2, 'social_position' => 'disabled', 'layout_type' => 'system')),
		_lgs_entry(7, 'old', 'k1', array('state' => 'blocked')),
		_lgs_entry(8, 'shop', 'k3'),
	);
	$r = pg_ltd_sets($report);
	pg_assert_same(2, count($r['sets']));
	pg_assert_same('k1', $r['sets'][0]['key'], 'the set with most styles first');
	pg_assert_same(array(1, 3, 5, 6), $r['sets'][0]['styles'], 'a mobile style joins its set: its classic row keeps serving phones');
	pg_assert_same(5, $r['sets'][0]['pages'], 'own and folder pages');
	pg_assert_same(array('social_networking_position' => 'bottom_left', 'layout_type' => ''), $r['sets'][0]['settings'],
		'the value most of its styles carry; a tie goes to the first style');
	pg_assert_same(pg_ltd_set_name('about'), $r['sets'][0]['name'], 'named after its first style');
	pg_assert_same(array('css' => array('/a.css'), 'js' => array()), $r['sets'][0]['refs']);
	pg_assert_same(array(2, 4), $r['sets'][1]['styles']);
	pg_assert_same(array(7 => 'blocked', 8 => 'single'), $r['alone'], 'a refused style and one sharing its files with nobody');

	$tie = pg_ltd_sets(array(_lgs_entry(1, 'b', 'x'), _lgs_entry(2, 'a', 'y'), _lgs_entry(3, 'b2', 'x'), _lgs_entry(4, 'a2', 'y')));
	pg_assert_same('y', $tie['sets'][0]['key'], 'equal sizes: by the first style name');
	pg_assert_same(array('sets' => array(), 'alone' => array()), pg_ltd_sets(array()));
}

function test_lgs_set_setting_most_common_first_on_a_tie()
{
	pg_assert_same('b', pg_ltd_set_setting(array('a', 'b', 'b', 'c')));
	pg_assert_same('a', pg_ltd_set_setting(array('a', 'b', 'b', 'a')), 'a tie: the first');
	pg_assert_same('', pg_ltd_set_setting(array('', '', 'system')));
	pg_assert_same('', pg_ltd_set_setting(array()));
}

function test_lgs_set_assets_shared_once_inline_limited_to_pages()
{
	$ext = array(pg_di_asset_entry('external-css', 'a.css', '{path}a.css', false));
	$js = array(pg_di_asset_entry('external-js', 'j.js', '{path}j.js', false));
	$same = pg_di_asset_entry('js', 'style-inline.js', "init();\n", false);
	$styles = array(
		array('id' => 1, 'name' => 'About Us', 'pages' => array(10, 11), 'css' => array_merge($ext, array(pg_di_asset_entry('css', 'style-inline.css', "p{color:red}\n", false))), 'js' => array_merge($js, array($same))),
		array('id' => 2, 'name' => 'blog', 'pages' => array(20), 'css' => array_merge($ext, array(pg_di_asset_entry('css', 'style-inline.css', "p{color:red}\n", false))), 'js' => array_merge($js, array($same))),
		array('id' => 3, 'name' => 'home', 'pages' => array(30), 'css' => array_merge($ext, array(pg_di_asset_entry('css', 'style-inline.css', "h1{}\n", false))), 'js' => array_merge($js, array($same))),
		array('id' => 4, 'name' => 'unused', 'pages' => array(), 'css' => array_merge($ext, array(pg_di_asset_entry('css', 'style-inline.css', "em{}\n", false))), 'js' => $js),
	);
	$a = pg_ltd_set_assets($styles);
	pg_assert_same(3, count($a['css']));
	pg_assert_same('{path}a.css', $a['css'][0]['content'], 'the shared file once');
	pg_assert_false(isset($a['css'][0]['pages']), 'on every page');
	pg_assert_same(array(10, 11, 20), $a['css'][1]['pages'], 'inline CSS two styles share: their pages');
	pg_assert_same(array('About Us', 'blog'), $a['css'][1]['styles']);
	pg_assert_same('about-us-inline.css', $a['css'][1]['name'], 'named after the first style using it');
	pg_assert_same(array(30), $a['css'][2]['pages']);
	pg_assert_same('home-inline.css', $a['css'][2]['name']);
	pg_assert_same(2, count($a['js']));
	pg_assert_false(isset($a['js'][1]['pages']), 'inline JS every style with pages has: on every page');
	pg_assert_same('style-inline.js', $a['js'][1]['name']);
	pg_assert_false($a['css'][1]['id'] === $a['css'][2]['id'], 'each row its own id');
}

function _lgs_plan_style($id, $name, $over = array())
{
	return array_merge(array(
		'id' => $id, 'name' => $name, 'state' => 'ready',
		'facts' => array('refusal' => '', 'common' => array(), 'frame' => array('designer' => array(), 'widgets' => array(), 'page_regions' => 0),
			'frame_exists' => false, 'designer_made' => array(), 'warnings' => array(),
			'pages' => array(array('page_id' => $id * 10, 'page_name' => $name . '-a', 'source' => 'own', 'has_tree' => false, 'holder' => '', 'own_regions' => 0))),
		'body_classes' => '', 'style_head' => false, 'mobile_pages' => 0, 'mobile' => 0,
		'social_position' => 'bottom_left', 'layout_type' => '',
	), $over);
}

function test_lgs_plan_set_one_design_frames_per_style_and_writes()
{
	$folder = _lgs_plan_style(2, 'blog');
	$folder['facts']['pages'][] = array('page_id' => 21, 'page_name' => 'blog-b', 'source' => 'folder', 'has_tree' => false, 'holder' => '', 'own_regions' => 0);
	$folder['facts']['pages'][] = array('page_id' => 22, 'page_name' => 'blog-c', 'source' => 'own', 'has_tree' => false, 'holder' => 'ayse', 'own_regions' => 0);
	$folder['facts']['frame']['widgets'] = array(array('type' => 'menu', 'name' => 'Main', 'kind' => 'menu_region', 'key' => 'menu_name'));
	$folder['body_classes'] = 'blog dark';
	$folder['style_head'] = true;
	$folder['mobile_pages'] = 2;
	$folder['mobile'] = 1;
	$folder['social_position'] = 'disabled';
	$folder['layout_type'] = 'custom';
	$refused = _lgs_plan_style(3, 'old');
	$refused['facts']['refusal'] = 'No.';
	$p = pg_ltd_plan_set(array(
		'key' => 'k1', 'name' => 'about set', 'theme' => '', 'collection' => 'a', 'social_on' => true, 'mobile_on' => true,
		'settings' => array('social_networking_position' => 'bottom_left', 'layout_type' => ''),
		'styles' => array(_lgs_plan_style(1, 'about'), $folder, $refused),
		'assets' => array('css' => array(pg_di_asset_entry('external-css', 'a.css', '{path}a.css', false)), 'js' => array()),
	));
	pg_assert_same('set', $p['kind']);
	pg_assert_same('k1', $p['key']);
	pg_assert_same('about set', $p['name']);
	pg_assert_same('', $p['skip']);
	pg_assert_same(3, count($p['styles']));
	pg_assert_same('make', $p['styles'][0]['frame'], 'a frame component per style');
	pg_assert_same(array('tree', 'tree', 'held'), array_column($p['styles'][1]['pages'], 'action'));
	pg_assert_same(1, $p['styles'][1]['folder_pages'], 'folder pages counted apart');
	pg_assert_same('blog dark', $p['styles'][1]['body_classes']);
	pg_assert_same(true, $p['styles'][1]['style_head']);
	pg_assert_same(2, $p['styles'][1]['mobile_pages']);
	pg_assert_same(2, $p['mobile_pages'], 'pages with a mobile style counted once for the set');
	pg_assert_same(1, $p['styles'][1]['mobile']);
	pg_assert_same(4, $p['styles'][1]['writes'], 'its own writes, the design not counted');
	pg_assert_same(array(), $p['styles'][0]['changes'], 'nothing differs from the design');
	pg_assert_same(array(array('setting' => 'social_networking_position', 'from' => 'disabled', 'to' => 'bottom_left'),
		array('setting' => 'layout_type', 'from' => 'custom', 'to' => '')), $p['styles'][1]['changes'], 'what its pages would show differently');
	$off = pg_ltd_plan_set(array('key' => 'k', 'name' => 'n', 'theme' => '', 'collection' => 'a', 'social_on' => false,
		'settings' => array('social_networking_position' => 'bottom_left', 'layout_type' => ''), 'styles' => array($folder), 'assets' => array('css' => array(), 'js' => array())));
	pg_assert_same(array(array('setting' => 'layout_type', 'from' => 'custom', 'to' => '')), $off['styles'][0]['changes'], 'the sharing position matters only while sharing is on');
	pg_assert_same(0, $off['mobile_pages'], 'mobile styles matter only while the site serves them');
	pg_assert_same(0, $off['styles'][0]['mobile']);
	pg_assert_same('refusal', $p['styles'][2]['skip']);
	// The design once; per style its frame, its free pages and widgets; no style row flipped.
	pg_assert_same(1 + (1 + 1) + (1 + 2 + 1), $p['writes']);

	$none = pg_ltd_plan_set(array('key' => 'k', 'name' => 'n', 'theme' => '', 'collection' => 'a', 'styles' => array($refused), 'assets' => array('css' => array(), 'js' => array())));
	pg_assert_same('empty', $none['skip'], 'no style of the set can move');
	pg_assert_same(0, $none['writes']);
}

function test_lgs_plan_set_regions_made_once()
{
	$a = _lgs_plan_style(1, 'about');
	$a['facts']['common'] = array('Top');
	$a['facts']['frame']['designer'] = array('Header', 'Footer');
	$b = _lgs_plan_style(2, 'blog');
	$b['facts']['common'] = array('Top', 'Side');
	$b['facts']['frame']['designer'] = array('Header');
	$p = pg_ltd_plan_set(array('key' => 'k', 'name' => 'n', 'theme' => '', 'collection' => 'a', 'social_on' => false,
		'settings' => array('social_networking_position' => 'bottom_left', 'layout_type' => ''), 'styles' => array($a, $b),
		'assets' => array('css' => array(), 'js' => array())));
	$got = array();
	foreach ($p['styles'][1]['components'] as $c) $got[] = $c['kind'] . ':' . $c['name'] . ':' . ($c['exists'] ? 'there' : 'made');
	pg_assert_same(array('common:Side:made', 'designer:Header:there'), $got, 'a region the first style made is there for the second');
	// The design; about: Top, Header, Footer, its frame, its page; blog: Side, its frame, its page.
	pg_assert_same(1 + 5 + 3, $p['writes']);
}

function test_lgs_plan_html_of_a_set()
{
	$s = _lgs_plan_style(2, 'blog <b>');
	$s['body_classes'] = 'blog';
	$s['mobile_pages'] = 1;
	$s['social_position'] = 'top_right';
	$s['facts']['pages'][] = array('page_id' => 21, 'page_name' => 'blog-b', 'source' => 'folder', 'has_tree' => false, 'holder' => '', 'own_regions' => 0);
	$p = pg_ltd_plan_set(array(
		'key' => 'k1', 'name' => 'about set', 'theme' => 'theme_modern.css', 'collection' => 'a', 'social_on' => true,
		'settings' => array('social_networking_position' => 'bottom_left', 'layout_type' => ''),
		'styles' => array(_lgs_plan_style(1, 'about'), $s),
		'assets' => array('css' => array(pg_di_asset_entry('external-css', 'a.css', '{path}a.css', false),
			array_merge(pg_di_asset_entry('css', 'blog-inline.css', 'p{}', false), array('pages' => array(20), 'styles' => array('blog <b>')))), 'js' => array()),
	));
	$html = pg_ltd_plan_html($p);
	pg_assert_contains('about set', $html);
	pg_assert_contains('theme_modern.css', $html);
	pg_assert_contains('a.css', $html);
	pg_assert_contains('blog-inline.css', $html);
	pg_assert_contains('blog &lt;b&gt;', $html, 'names escaped');
	pg_assert_false(strpos($html, 'blog <b>') !== false);
	pg_assert_contains('blog-b', $html, 'the folder page named');
	pg_assert_same(2, substr_count($html, '<h4'), 'one heading per style');
	pg_assert_contains(lang('Top Right'), $html, 'the sharing position it had');
	pg_assert_contains(lang('Bottom Left'), $html, 'and the one the design holds');
	pg_assert_same(1, preg_match('~<details class="mt-2" open><summary><h4[^>]*>blog &lt;b&gt;</h4>~', $html), 'a style whose pages change opens by itself');
	pg_assert_same(1, preg_match('~<details class="mt-2"><summary><h4[^>]*>about</h4>~', $html), 'one with nothing to look at stays folded');
}

function test_lgs_screen_offers_sets()
{
	$screen = (string)file_get_contents(PG_FUNCTIONS_DIR . '/legacy_to_designer.php');
	pg_assert_same(1, preg_match('/name="sets\[\]"[^>]*form="ltd_dry_run"/', $screen), 'a set is ticked for the dry run');
	pg_assert_contains('pg_ltd_sets($report)', $screen);
	pg_assert_contains("pg_ltd_plan(array_keys(\$chosen), array_keys(\$chosen_sets))", $screen);
	pg_assert_contains("\$p['kind'] === 'style' && \$p['skip'] === ''", $screen, 'Convert carries single styles only');
}
