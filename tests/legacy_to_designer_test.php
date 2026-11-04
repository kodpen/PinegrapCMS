<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the readiness report of the move to the visual designer
 * (includes/fn/legacy_to_designer.php): region tags of a style's code,
 * common regions split by their rows, pages by style, the state.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer.php');

function test_ltd_style_tags_regions_in_order()
{
	$code = '<html><head><title><pregion></pregion></title><meta_tags></meta_tags></head><body>'
		. '<cregion>Header &amp; Nav</cregion><menu>Main</menu><div style="clear: both"></div>'
		. '<pregion></pregion><system></system><dregion "x=1">Clock</dregion><tcloud>Tags</tcloud>'
		. '<!-- <cregion>Old</cregion> --><script>var s = "<ad>Top</ad>";</script>'
		. '<!--pg-comments-block--><cart "x">Cart</cart><menu_sequence>Seq</menu_sequence></body></html>';
	$t = pg_ltd_style_tags($code);
	$types = array();
	foreach ($t['regions'] as $r) $types[] = $r['type'];
	pg_assert_same(array('common', 'menu', 'page', 'system', 'dynamic', 'tag_cloud', 'comments_block', 'menu_sequence'), $types);
	pg_assert_same('Header & Nav', $t['regions'][0]['name']);
	pg_assert_same('Clock', $t['regions'][4]['name']);
	pg_assert_same(false, $t['php']);
}

function test_ltd_style_tags_php()
{
	pg_assert_same(true, pg_ltd_style_tags('<body><?php echo 1; ?></body>')['php']);
	pg_assert_same(true, pg_ltd_style_tags('<body><?= $x ?></body>')['php']);
	pg_assert_same(true, pg_ltd_style_tags('<script><? echo 1; ?></script>')['php'], 'PHP inside a script still runs');
	pg_assert_same(false, pg_ltd_style_tags('<?xml version="1.0"?><body></body>')['php']);
	pg_assert_same(array('regions' => array(), 'php' => false), pg_ltd_style_tags(''));
}

function test_ltd_region_counts_splits_common_regions()
{
	$regions = array(
		array('type' => 'common', 'name' => 'Header'),
		array('type' => 'common', 'name' => 'Footer'),
		array('type' => 'common', 'name' => 'Promo'),
		array('type' => 'common', 'name' => 'Gone'),
		array('type' => 'common', 'name' => 'Gone'),
		array('type' => 'page', 'name' => ''),
		array('type' => 'page', 'name' => ''),
		array('type' => 'dynamic', 'name' => 'Clock'),
	);
	$cregions = array(
		'Header' => array('id' => 3, 'designer' => false),
		'Footer' => array('id' => 4, 'designer' => false),
		'Promo'  => array('id' => 5, 'designer' => true),
	);
	$c = pg_ltd_region_counts($regions, $cregions, array(4));
	pg_assert_same(array('common' => 1, 'common_converted' => 1, 'designer' => 1, 'missing' => 2, 'page' => 2, 'dynamic' => 1), $c['counts']);
	pg_assert_same(array('Gone'), $c['missing']);
}

function test_ltd_pages_by_style_own_and_folder()
{
	$folder_styles = array(1 => 7, 2 => 0, 3 => 8);
	$pages = array(
		array('page_id' => 10, 'page_folder' => 1, 'page_style' => 0),
		array('page_id' => 11, 'page_folder' => 1, 'page_style' => 8),
		array('page_id' => 12, 'page_folder' => 2, 'page_style' => 0),
		array('page_id' => 13, 'page_folder' => 3, 'page_style' => null),
		array('page_id' => 14, 'page_folder' => 99, 'page_style' => 0),
	);
	$by = pg_ltd_pages_by_style($pages, $folder_styles);
	pg_assert_same(array(7, 8), array_keys($by));
	pg_assert_same(array(10), array_column($by[7]['folder'], 'page_id'));
	pg_assert_same(array(), $by[7]['own']);
	pg_assert_same(array(11), array_column($by[8]['own'], 'page_id'));
	pg_assert_same(array(13), array_column($by[8]['folder'], 'page_id'));
}

function test_ltd_style_state()
{
	$ok = array('refusal' => '', 'php' => false, 'locked' => 0, 'open' => 0, 'missing' => 0, 'dynamic' => 0, 'custom_layouts' => 0, 'pages' => 3);
	pg_assert_same(array('state' => 'ready', 'reasons' => array(), 'notes' => array()), pg_ltd_style_state($ok));

	// What stays classic is a note; the style is still ready.
	$s = pg_ltd_style_state(array_merge($ok, array('dynamic' => 2, 'custom_layouts' => 1)));
	pg_assert_same('ready', $s['state']);
	pg_assert_same(array('dynamic', 'custom_layout'), $s['notes']);

	$s = pg_ltd_style_state(array_merge($ok, array('php' => true, 'locked' => 1, 'missing' => 1, 'open' => 1)));
	pg_assert_same('attention', $s['state']);
	pg_assert_same(array('php', 'missing', 'locked', 'open'), $s['reasons']);

	$s = pg_ltd_style_state(array_merge($ok, array('refusal' => 'x', 'php' => true, 'pages' => 0)));
	pg_assert_same('blocked', $s['state']);
	pg_assert_same(array('refusal'), $s['reasons']);
	pg_assert_same(array('no_pages'), $s['notes']);
}

function test_ltd_stays_classic_list()
{
	$report = array(
		array('name' => 'Old grid', 'state' => 'blocked', 'regions' => array('counts' => array()), 'custom_layouts' => 0),
		array('name' => 'Main', 'state' => 'ready', 'regions' => array('counts' => array('dynamic' => 2)), 'custom_layouts' => 3),
		array('name' => 'Shop', 'state' => 'attention', 'regions' => array('counts' => array('dynamic' => 1)), 'custom_layouts' => 0),
	);
	$out = pg_ltd_stays_classic($report, 'Authorize.Net');
	$codes = array();
	foreach ($out as $i) $codes[] = $i['code'];
	pg_assert_same(array('blocked', 'dynamic', 'custom_layout', 'gateway'), $codes);
	pg_assert_same(array(1, array('Old grid')), array($out[0]['count'], $out[0]['styles']));
	pg_assert_same(array(3, array('Main', 'Shop')), array($out[1]['count'], $out[1]['styles']));
	pg_assert_same(array(3, array('Main')), array($out[2]['count'], $out[2]['styles']));
	pg_assert_same('Authorize.Net', $out[3]['gateway']);
}

function test_ltd_stays_classic_empty()
{
	pg_assert_same(array(), pg_ltd_stays_classic(array(), ''));
	pg_assert_same(array(), pg_ltd_stays_classic(array(array('name' => 'A', 'state' => 'ready', 'regions' => array('counts' => array()), 'custom_layouts' => 0)), ''));
}
