<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a release bundle's preview in the prototype viewer
 * (prototype.php?bundle=<id>, includes/fn/prototype.php): which drafts the
 * preview holds and in which order, a link to a page the bundle replaces
 * opening its replacement, the frame address signed for the bundle, the bar's
 * labels, and the gate that keeps the preview to roles 0 and 1.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype.php');

// Folder 50 is the Drafts folder, 60 the Recycle Bin, 1 the site. Drafts 31
// and 33 replace the live pages 13 (about) and 41 (home); 32 is a new page.
function prototype_bundle_test_rows()
{
	return array(
		array('page_id' => 31, 'page_name' => 'about-copy', 'page_title' => 'About us, new', 'page_folder' => 50, 'page_style' => 7, 'publish_at' => 0),
		array('page_id' => 32, 'page_name' => 'campaign', 'page_title' => '', 'page_folder' => 50, 'page_style' => 8, 'publish_at' => 0),
		array('page_id' => 33, 'page_name' => 'home-copy', 'page_title' => 'Home', 'page_folder' => 50, 'page_style' => 7),
		array('page_id' => 34, 'page_name' => 'published-copy', 'page_title' => '', 'page_folder' => 1, 'page_style' => 7),
		array('page_id' => 35, 'page_name' => 'other-draft', 'page_title' => '', 'page_folder' => 50, 'page_style' => 7),
	);
}

function prototype_bundle_test_items()
{
	return array(
		array('draft_page_id' => 33, 'live_page_id' => 41, 'live_name' => 'home', 'status' => 'waiting'),
		array('draft_page_id' => 32, 'live_page_id' => 0, 'live_name' => '', 'status' => 'waiting'),
		array('draft_page_id' => 31, 'live_page_id' => 13, 'live_name' => 'about', 'status' => 'waiting'),
		// Written to the site already: no longer a draft of the bundle.
		array('draft_page_id' => 34, 'live_page_id' => 42, 'live_name' => 'team', 'status' => 'written'),
		// A draft the bundle names twice, or one that was deleted.
		array('draft_page_id' => 31, 'live_page_id' => 13, 'live_name' => 'about', 'status' => 'waiting'),
		array('draft_page_id' => 36, 'live_page_id' => 0, 'live_name' => '', 'status' => 'waiting'),
	);
}

function prototype_bundle_test_ctx()
{
	return array(
		'env' => array('host' => 'example.com', 'path' => '/', 'software_directory' => 'pinegrap'),
		'maps' => array(
			'pages' => array(
				'about' => array('id' => 13, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 1),
				'home' => array('id' => 41, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 1),
				'contact' => array('id' => 43, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 1),
				'campaign' => array('id' => 32, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 50),
				'about-copy' => array('id' => 31, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 50),
				'other-draft' => array('id' => 35, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 50),
			),
			'files' => array(),
			'short_links' => array(),
			'products' => array(),
			'product_groups' => array(),
			'home_page_id' => 41,
		),
		'form_names' => array(),
		'proto' => array(31 => true, 32 => true, 33 => true),
		'replaced' => array(41 => 33, 13 => 31),
		'draft_folder' => 50,
		'bin_folders' => array(60 => true),
		'current_page' => 32,
		'current_sub' => '',
		'current_record' => '',
		'languages' => array('de' => 'de'),
		'viewer' => '/pinegrap/prototype.php?bundle=5',
	);
}

function test_prototype_bundle_page_set_holds_the_waiting_drafts_in_bundle_order()
{
	// The design's tab order is not the bundle's: the bundle's own order.
	$layout = array('v' => 1, 'groups' => array(), 'tabs' => array(array('id' => 31), array('id' => 32)));
	$set = pg_proto_page_set(prototype_bundle_test_rows(), 50, array(60), $layout, prototype_bundle_test_items());

	$ids = array();
	foreach ($set as $page) {
		$ids[] = $page['id'];
	}

	// A written page, a page out of Drafts, a deleted draft and a second
	// listing of the same draft are left out.
	pg_assert_same(array(33, 32, 31), $ids);
	pg_assert_same(41, $set[0]['replaces']);
	pg_assert_same('home', $set[0]['replaces_name']);
	pg_assert_same(0, $set[1]['replaces']);
	pg_assert_same('', $set[1]['replaces_name']);
	pg_assert_same(8, $set[1]['style']);
	pg_assert_same('About us, new', $set[2]['title']);
	pg_assert_false($set[2]['scheduled']);
}

function test_prototype_bundle_page_set_is_empty_without_waiting_drafts()
{
	pg_assert_same(array(), pg_proto_page_set(prototype_bundle_test_rows(), 50, array(60), '', array()));
	pg_assert_same(array(), pg_proto_page_set(prototype_bundle_test_rows(), 0, array(60), '', prototype_bundle_test_items()));
	pg_assert_same(array(), pg_proto_page_set(prototype_bundle_test_rows(), 60, array(60), '', prototype_bundle_test_items()));

	$written = array(array('draft_page_id' => 31, 'live_page_id' => 13, 'live_name' => 'about', 'status' => 'written'));
	pg_assert_same(array(), pg_proto_page_set(prototype_bundle_test_rows(), 50, array(60), '', $written));
}

function test_prototype_design_page_set_is_unchanged_without_a_bundle()
{
	// A design's prototype keeps its keys: no bundle key is added.
	$set = pg_proto_page_set(prototype_bundle_test_rows(), 50, array(60), '');

	pg_assert_same(array('id', 'name', 'title', 'publish_at', 'scheduled'), array_keys($set[0]));
	pg_assert_same(array(), pg_proto_bundle_replaced($set));
}

function test_prototype_bundle_replaced_maps_live_pages_to_their_replacements()
{
	$set = pg_proto_page_set(prototype_bundle_test_rows(), 50, array(60), '', prototype_bundle_test_items());

	pg_assert_same(array(41 => 33, 13 => 31), pg_proto_bundle_replaced($set));
}

function test_prototype_bundle_link_to_a_replaced_page_opens_its_replacement()
{
	$ctx = prototype_bundle_test_ctx();

	$about = pg_proto_link_target('/about', $ctx);
	pg_assert_same('prototype', $about['kind']);
	pg_assert_same(31, $about['page_id']);
	pg_assert_same('/pinegrap/prototype.php?bundle=5&page=31', $about['href']);

	// The home page by its own name, with a record and a section.
	$home = pg_proto_link_target('/home?r=ab12#prices', $ctx);
	pg_assert_same('prototype', $home['kind']);
	pg_assert_same('/pinegrap/prototype.php?bundle=5&page=33&r=ab12&anchor=prices', $home['href']);

	// A new page of the bundle by its own name, and the replacement by its
	// draft name, stay in the preview as they do in a design's.
	pg_assert_same('/pinegrap/prototype.php?bundle=5&page=31', pg_proto_link_target('/about-copy', $ctx)['href']);
	pg_assert_same('prototype', pg_proto_link_target('/campaign', $ctx)['kind']);
}

function test_prototype_bundle_other_links_open_the_live_site()
{
	$ctx = prototype_bundle_test_ctx();

	// A live page the bundle does not replace opens on the site.
	$contact = pg_proto_link_target('/contact', $ctx);
	pg_assert_same('site', $contact['kind']);
	pg_assert_same('/contact', $contact['href']);

	// Another language's version of a replaced page is the live one: the
	// frame draws the source language only.
	$german = pg_proto_link_target('/de/about', $ctx);
	pg_assert_same('site', $german['kind']);

	// A draft the bundle does not hold goes nowhere.
	pg_assert_same('draft', pg_proto_link_target('/other-draft', $ctx)['kind']);
}

function test_prototype_design_link_to_a_live_page_is_unchanged_without_replacements()
{
	$ctx = prototype_bundle_test_ctx();
	unset($ctx['replaced']);

	pg_assert_same('site', pg_proto_link_target('/about', $ctx)['kind']);
}

function test_prototype_bundle_frame_signature_names_the_bundle()
{
	$sig = pg_proto_frame_sign(7, 31, 3, 1000900, 'k', '', '', 5);
	$query = pg_proto_frame_query(7, 31, 3, 1000900, $sig, '', '', pg_proto_frame_bind($sig, 'session-a', 'k'), 5);
	parse_str($query, $get);

	pg_assert_same('5', $get['k']);
	$parsed = pg_proto_frame_parse($get);
	pg_assert_same(5, $parsed['k']);
	pg_assert_same('ok', pg_proto_frame_state($parsed, 'k', 1000000, 'session-a'));

	// Another bundle, or none, does not open with the same signature.
	$other = $get;
	$other['k'] = '6';
	pg_assert_same('invalid', pg_proto_frame_state(pg_proto_frame_parse($other), 'k', 1000000, 'session-a'));

	$none = $get;
	unset($none['k']);
	pg_assert_same('invalid', pg_proto_frame_state(pg_proto_frame_parse($none), 'k', 1000000, 'session-a'));

	// A design's address keeps its signature and gets no bundle.
	pg_assert_same(pg_proto_frame_sign(7, 31, 3, 1000900, 'k'), pg_proto_frame_sign(7, 31, 3, 1000900, 'k', '', '', 0));
	pg_assert_false($sig === pg_proto_frame_sign(7, 31, 3, 1000900, 'k'));
	parse_str(pg_proto_frame_query(7, 31, 3, 1000900, pg_proto_frame_sign(7, 31, 3, 1000900, 'k')), $design);
	pg_assert_false(isset($design['k']));
	pg_assert_same(0, pg_proto_frame_parse($design)['k']);

	// A bundle that is not a plain number is no address.
	$bad = $get;
	$bad['k'] = '05';
	pg_assert_same(null, pg_proto_frame_parse($bad));
}

function test_prototype_bundle_viewer_address()
{
	pg_assert_same('/pinegrap/prototype.php?bundle=5', pg_proto_bundle_viewer('/pinegrap/prototype.php', 5));
	pg_assert_same('/pinegrap/prototype.php?bundle=5&page=31', pg_proto_viewer_url(pg_proto_bundle_viewer('/pinegrap/prototype.php', 5), 31));
}

function test_prototype_bundle_shell_names_the_replaced_page_and_its_empty_state()
{
	$view = array(
		'design' => 'Spring campaign',
		'pages' => pg_proto_page_set(prototype_bundle_test_rows(), 50, array(60), '', prototype_bundle_test_items()),
		'current' => 33,
		'fallback' => false,
		'frame_url' => '/pinegrap/prototype.php?frame=1&s=7&p=33&k=5',
		'viewer' => '/pinegrap/prototype.php?bundle=5',
		'close_url' => '/pinegrap/page_bundles.php?id=5',
		'software' => '/pinegrap',
		'lang' => 'en',
		'error' => '',
		'empty_title' => 'No page waits.',
		'empty_text' => 'Add one.',
	);
	$html = pg_proto_shell_html($view);

	pg_assert_contains('Spring campaign', $html);
	pg_assert_contains('<option value="/pinegrap/prototype.php?bundle=5&amp;page=33" selected>Home (replaces home)</option>', $html);
	pg_assert_contains('>campaign</option>', $html);

	$view['pages'] = array();
	$view['frame_url'] = '';
	$html = pg_proto_shell_html($view);

	pg_assert_contains('No page waits.', $html);
	pg_assert_contains('Add one.', $html);
	pg_assert_false(strpos($html, 'This design has no draft pages.') !== false);
}

function test_prototype_bundle_page_chooser_takes_the_width_its_names_need()
{
	$view = array(
		'design' => 'Spring campaign',
		'pages' => pg_proto_page_set(prototype_bundle_test_rows(), 50, array(60), '', prototype_bundle_test_items()),
		'current' => 33,
		'viewer' => '/pinegrap/prototype.php?bundle=5',
		'wide_pages' => true,
	);
	$html = pg_proto_shell_html($view);

	// "(replaces …)" tells a replacement from a new page, so a bundle's
	// chooser is not held to the design's 16rem.
	pg_assert_contains('<div class="pg-proto-nav pg-proto-nav-wide">', $html);
	pg_assert_contains('.pg-proto-nav-wide select.form-control { max-width: none; }', $html);

	// A design's prototype keeps its chooser as it was.
	unset($view['wide_pages']);
	$html = pg_proto_shell_html($view);
	pg_assert_contains('<div class="pg-proto-nav">', $html);
	pg_assert_contains('.pg-proto-nav select.form-control { width: auto; max-width: 16rem; min-width: 8rem; }', $html);
}

function test_prototype_php_keeps_the_bundle_preview_to_bundle_makers()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/prototype.php');

	// The viewer: the manager gate first, then the bundle's own gate (roles
	// 0 and 1, as the Bundles screen) before anything is drawn.
	$bundle = strpos($source, "isset(\$_GET['bundle'])");
	$gate = strpos($source, "validate_area_access(\$user, 'designer');");
	pg_assert_true(($bundle !== false) && ($gate !== false));
	pg_assert_true(strpos($source, "validate_area_access(\$user, 'manager');") < $bundle);
	pg_assert_true($gate < strpos($source, 'echo pg_proto_shell_html(', $bundle));

	// The frame reads the person's role again and asks the bundle rule.
	pg_assert_contains('pg_page_bundle_user_may(', $source);
	pg_assert_contains('pg_proto_bundle_pages(', $source);
	pg_assert_contains("'replaced' => pg_proto_bundle_replaced(", $source);
}
