<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the prototype viewer (prototype.php, includes/fn/prototype.php):
 * which pages a design's prototype holds and in which order, where each link
 * of a drawn draft leads (another draft of the design, the site, nowhere,
 * outside), how the links of a fixture page are rewritten and what is left
 * alone, the signed address of the frame the page is drawn in, the bar drawn
 * around it, and the gate that keeps the viewer to roles 0-2 for now.
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

// Folder 50 is the Drafts folder, 60 the Recycle Bin, 1 the site.
function prototype_viewer_test_rows()
{
	return array(
		array('page_id' => 11, 'page_name' => 'start', 'page_title' => 'Start', 'page_folder' => 50, 'publish_at' => 0),
		array('page_id' => 12, 'page_name' => 'pricing', 'page_title' => '', 'page_folder' => 50, 'publish_at' => 1900000000),
		array('page_id' => 13, 'page_name' => 'live-page', 'page_title' => 'Live', 'page_folder' => 1, 'publish_at' => 0),
		array('page_id' => 14, 'page_name' => 'binned', 'page_title' => '', 'page_folder' => 60, 'publish_at' => 0),
		array('page_id' => 15, 'page_name' => 'contact', 'page_title' => 'Contact', 'page_folder' => 50),
	);
}

function prototype_viewer_test_ctx()
{
	return array(
		'env' => array('host' => 'example.com', 'path' => '/', 'software_directory' => 'pinegrap'),
		'maps' => array(
			'pages' => array(
				'start' => array('id' => 11, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 50),
				'pricing' => array('id' => 12, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 50),
				'about' => array('id' => 13, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 1),
				'binned-page' => array('id' => 14, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 60),
				'other-draft' => array('id' => 16, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 50),
				'shop' => array('id' => 17, 'type' => 'catalog', 'layout_type' => 'custom', 'folder' => 50),
				'other-shop' => array('id' => 18, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 50),
				'binned-shop' => array('id' => 19, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 60),
				'live-shop' => array('id' => 21, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 1),
			),
			'files' => array('brochure.pdf' => 5),
			'short_links' => array(),
			'products' => array('red-shirt' => 20),
			'product_groups' => array(),
			'home_page_id' => 13,
		),
		'form_names' => array(),
		'proto' => array(11 => true, 12 => true, 17 => true),
		'draft_folder' => 50,
		'bin_folders' => array(60 => true),
		'current_page' => 11,
		'current_sub' => '',
		'current_record' => '',
		'languages' => array('de' => 'de'),
		'viewer' => '/pinegrap/prototype.php',
	);
}

function test_prototype_page_set_holds_the_design_drafts_in_tab_order()
{
	$layout = array('v' => 1, 'groups' => array(), 'tabs' => array(array('id' => 15), array('id' => 13), array('id' => 11)));
	$set = pg_proto_page_set(prototype_viewer_test_rows(), 50, array(60), $layout);

	$ids = array();
	foreach ($set as $page) {
		$ids[] = $page['id'];
	}

	// Listed tabs first, the drafts the layout does not name after them.
	pg_assert_same(array(15, 11, 12), $ids);
	pg_assert_same('contact', $set[0]['name']);
	pg_assert_same('Contact', $set[0]['title']);
}

function test_prototype_page_set_marks_scheduled_drafts()
{
	$set = pg_proto_page_set(prototype_viewer_test_rows(), 50, array(60), '');

	$scheduled = array();
	foreach ($set as $page) {
		$scheduled[$page['id']] = $page['scheduled'];
	}

	pg_assert_same(array(11 => false, 12 => true, 15 => false), $scheduled);
}

function test_prototype_page_set_is_empty_without_a_drafts_folder()
{
	pg_assert_same(array(), pg_proto_page_set(prototype_viewer_test_rows(), 0, array(), ''));
	pg_assert_same(array(), pg_proto_page_set(array(), 50, array(), ''));
}

function test_prototype_page_set_leaves_out_a_drafts_folder_in_the_bin()
{
	pg_assert_same(array(), pg_proto_page_set(prototype_viewer_test_rows(), 60, array(60), ''));
}

function test_prototype_start_page_is_the_asked_draft_or_the_first()
{
	$set = pg_proto_page_set(prototype_viewer_test_rows(), 50, array(60), '');

	pg_assert_same(array('id' => 12, 'fallback' => false), pg_proto_start_page($set, 12));
	pg_assert_same(array('id' => 11, 'fallback' => false), pg_proto_start_page($set, 0));
	pg_assert_same(array('id' => 11, 'fallback' => true), pg_proto_start_page($set, 13));
	pg_assert_same(array('id' => 0, 'fallback' => false), pg_proto_start_page(array(), 12));
}

function test_prototype_link_to_a_draft_of_the_design_stays_in_the_prototype()
{
	$target = pg_proto_link_target('/pricing', prototype_viewer_test_ctx());

	pg_assert_same('prototype', $target['kind']);
	pg_assert_same(12, $target['page_id']);
	pg_assert_same('/pinegrap/prototype.php?page=12', $target['href']);
}

function test_prototype_link_carries_a_record_and_a_pretty_address()
{
	$target = pg_proto_link_target('/shop/red-shirt?r=ab12&utm=x', prototype_viewer_test_ctx());

	pg_assert_same('prototype', $target['kind']);
	pg_assert_same(17, $target['page_id']);
	pg_assert_same('/pinegrap/prototype.php?page=17&sub=red-shirt&r=ab12', $target['href']);

	$target = pg_proto_link_target('https://example.com/pricing?r=ab12', prototype_viewer_test_ctx());
	pg_assert_same('/pinegrap/prototype.php?page=12&r=ab12', $target['href']);
}

function test_prototype_link_to_a_live_page_opens_the_site()
{
	$target = pg_proto_link_target('/about', prototype_viewer_test_ctx());
	pg_assert_same('site', $target['kind']);
	pg_assert_same(13, $target['page_id']);
	pg_assert_same('/about', $target['href']);

	// A relative address is made absolute: the frame is not at the site root.
	$target = pg_proto_link_target('about', prototype_viewer_test_ctx());
	pg_assert_same('/about', $target['href']);

	pg_assert_same('site', pg_proto_link_target('/brochure.pdf', prototype_viewer_test_ctx())['kind']);
	pg_assert_same('site', pg_proto_link_target('/', prototype_viewer_test_ctx())['kind']);
}

function test_prototype_link_to_nothing_is_broken()
{
	foreach (array('/no-such-page', '/binned-page', '', '/shop/no-such-item', 'http:///example.com') as $href) {
		$target = pg_proto_link_target($href, prototype_viewer_test_ctx());
		pg_assert_same('broken', $target['kind'], $href);
		pg_assert_same('', $target['href'], $href);
	}
}

function test_prototype_link_to_another_design_draft_is_marked()
{
	$target = pg_proto_link_target('/other-draft', prototype_viewer_test_ctx());

	pg_assert_same('draft', $target['kind']);
	pg_assert_same(16, $target['page_id']);
	pg_assert_same('', $target['href']);
}

function test_prototype_link_to_an_item_under_another_draft_or_a_binned_page()
{
	// The page before the slash decides, as it does for a plain page link.
	$target = pg_proto_link_target('/other-shop/red-shirt', prototype_viewer_test_ctx());
	pg_assert_same('draft', $target['kind']);
	pg_assert_same(18, $target['page_id']);
	pg_assert_same('', $target['href']);

	$target = pg_proto_link_target('/binned-shop/red-shirt', prototype_viewer_test_ctx());
	pg_assert_same('broken', $target['kind']);
	pg_assert_same('', $target['href']);

	$target = pg_proto_link_target('/live-shop/red-shirt', prototype_viewer_test_ctx());
	pg_assert_same('site', $target['kind']);
	pg_assert_same('/live-shop/red-shirt', $target['href']);
}

function test_prototype_link_keeps_the_section_it_names()
{
	$ctx = prototype_viewer_test_ctx();

	// Another page of the prototype opens at that section.
	$target = pg_proto_link_target('/pricing#plans', $ctx);
	pg_assert_same('prototype', $target['kind']);
	pg_assert_same('/pinegrap/prototype.php?page=12&anchor=plans', $target['href']);

	// A section of the page on view scrolls inside the frame.
	$target = pg_proto_link_target('/start#features', $ctx);
	pg_assert_same('anchor', $target['kind']);
	pg_assert_same(11, $target['page_id']);
	pg_assert_same('#features', $target['href']);
	pg_assert_same('#features', pg_proto_link_target('https://example.com/start?utm=x#features', $ctx)['href']);

	// The same page showing a record is another view.
	$target = pg_proto_link_target('/start?r=ab12#features', $ctx);
	pg_assert_same('prototype', $target['kind']);
	pg_assert_same('/pinegrap/prototype.php?page=11&r=ab12&anchor=features', $target['href']);

	// An empty fragment is the page itself.
	pg_assert_same('/pinegrap/prototype.php?page=12', pg_proto_link_target('/pricing#', $ctx)['href']);

	// On a page showing an item, that item's sections stay in the frame and
	// the page's list is another view.
	$ctx['current_page'] = 17;
	$ctx['current_sub'] = 'red-shirt';
	pg_assert_same('#reviews', pg_proto_link_target('/shop/red-shirt#reviews', $ctx)['href']);
	pg_assert_same('/pinegrap/prototype.php?page=17&anchor=reviews', pg_proto_link_target('/shop#reviews', $ctx)['href']);
}

function test_prototype_link_to_another_language_opens_the_site()
{
	$ctx = prototype_viewer_test_ctx();

	// The language switcher's other entries: that language's version, on the
	// site, since the frame draws the page in the source language only.
	$target = pg_proto_link_target('/de/pricing', $ctx);
	pg_assert_same('site', $target['kind']);
	pg_assert_same(12, $target['page_id']);
	pg_assert_same('/de/pricing', $target['href']);

	pg_assert_same('site', pg_proto_link_target('/de/about', $ctx)['kind']);
	pg_assert_same('site', pg_proto_link_target('/de/', $ctx)['kind']);
	pg_assert_same('site', pg_proto_link_target('/de', $ctx)['kind']);
	pg_assert_same('broken', pg_proto_link_target('/de/no-such-page', $ctx)['kind']);
	pg_assert_same('broken', pg_proto_link_target('/de/binned-page', $ctx)['kind']);
	pg_assert_same('draft', pg_proto_link_target('/de/other-draft', $ctx)['kind']);

	// Not a language of the site (the router compares the prefix as written).
	pg_assert_same('broken', pg_proto_link_target('/fr/pricing', $ctx)['kind']);
	pg_assert_same('broken', pg_proto_link_target('/DE/pricing', $ctx)['kind']);
}

function test_prototype_split_language_cuts_a_known_prefix_only()
{
	$languages = array('de' => 'de', 'pt-br' => 'pt-BR');

	pg_assert_same(array('language' => 'de', 'item' => 'pricing'), pg_proto_split_language('de/pricing', $languages));
	pg_assert_same(array('language' => 'pt-br', 'item' => 'shop/red-shirt'), pg_proto_split_language('pt-br/shop/red-shirt', $languages));
	pg_assert_same(array('language' => 'de', 'item' => ''), pg_proto_split_language('de', $languages));
	pg_assert_same(array('language' => 'de', 'item' => ''), pg_proto_split_language('de/', $languages));
	pg_assert_same(array('language' => '', 'item' => 'deutsch/x'), pg_proto_split_language('deutsch/x', $languages));
	pg_assert_same(array('language' => '', 'item' => 'DE/x'), pg_proto_split_language('DE/x', $languages));
	pg_assert_same(array('language' => '', 'item' => 'de/x'), pg_proto_split_language('de/x', array()));
}

function test_prototype_clean_fragment_keeps_a_safe_section_name()
{
	pg_assert_same('plans', pg_proto_clean_fragment('plans'));
	pg_assert_same('caf%C3%A9', pg_proto_clean_fragment('caf%C3%A9'));
	pg_assert_same('caf%C3%A9', pg_proto_clean_fragment("caf\xC3\xA9"));
	pg_assert_same('a%20b%22%3C', pg_proto_clean_fragment('a b"<'));
	pg_assert_same('', pg_proto_clean_fragment("a\nb"));
	pg_assert_same('', pg_proto_clean_fragment(array('x')));
	pg_assert_same('', pg_proto_clean_fragment(str_repeat('a', 201)));
	// The limit counts the section name, not its escapes.
	pg_assert_same(str_repeat('%C3%A9', 100), pg_proto_clean_fragment(str_repeat("\xC3\xA9", 100)));
	pg_assert_same('', pg_proto_clean_fragment(str_repeat("\xC3\xA9", 101)));
	pg_assert_same('', pg_proto_clean_fragment(str_repeat('%C3%A9', 101)));
}

// A section name the link accepted reaches the frame: the viewer cleans the
// escaped form it is handed again and keeps it as it is.
function test_prototype_long_section_name_survives_the_viewer()
{
	$fragment = 'как-оформить-заказ-и-оплатить-доставку';
	$target = pg_proto_link_target('/pricing#' . $fragment, prototype_viewer_test_ctx());
	$escaped = pg_proto_clean_fragment($fragment);

	pg_assert_true(strlen($escaped) > 200, 'escaped form is longer than the limit');
	pg_assert_same('/pinegrap/prototype.php?page=12&anchor=' . rawurlencode($escaped), $target['href']);

	parse_str((string) parse_url($target['href'], PHP_URL_QUERY), $get);
	pg_assert_same($escaped, pg_proto_clean_fragment($get['anchor']));
}

function test_prototype_frame_url_carries_the_section()
{
	pg_assert_same('/pinegrap/prototype.php?frame=1&s=7', pg_proto_frame_url('/pinegrap/prototype.php', 'frame=1&s=7', ''));
	pg_assert_same('/pinegrap/prototype.php?frame=1&s=7#plans', pg_proto_frame_url('/pinegrap/prototype.php', 'frame=1&s=7', 'plans'));
}

function test_prototype_public_address_of_the_drawn_page()
{
	// What the widgets read as the request in the frame: the page's own
	// address, never the frame's signed one.
	pg_assert_same('/start', pg_proto_public_uri('/', 'start', '', ''));
	pg_assert_same('/site/my%20page/red%20shirt/x?r=ab%2012', pg_proto_public_uri('/site/', 'my page', 'red shirt/x', 'ab 12'));
}

function test_prototype_frame_answers_the_cookie_notice_with_no()
{
	$value = pg_proto_consent_refused(pg_consent_category_letters());

	pg_assert_same('1.a0.m0', $value);
	pg_assert_same(array('analytics' => false, 'marketing' => false), pg_consent_parse($value));
	pg_assert_same('1', pg_proto_consent_refused(array()));
}

function test_prototype_link_outside_the_site_opens_normally()
{
	pg_assert_same('external', pg_proto_link_target('https://other.example.org/x', prototype_viewer_test_ctx())['kind']);
	pg_assert_same('contact', pg_proto_link_target('mailto:info@example.com', prototype_viewer_test_ctx())['kind']);
	pg_assert_same('contact', pg_proto_link_target('tel:+903120000000', prototype_viewer_test_ctx())['kind']);
	pg_assert_same('mailto:info@example.com', pg_proto_link_target('mailto:info@example.com', prototype_viewer_test_ctx())['href']);
}

function test_prototype_link_anchors_and_scripts_are_left_alone()
{
	pg_assert_same('', pg_proto_link_target('#features', prototype_viewer_test_ctx())['kind']);
	pg_assert_same('', pg_proto_link_target('#', prototype_viewer_test_ctx())['kind']);
	pg_assert_same('', pg_proto_link_target('javascript:void(0)', prototype_viewer_test_ctx())['kind']);
}

function test_prototype_link_bare_query_is_the_page_itself()
{
	$target = pg_proto_link_target('?sort=new', prototype_viewer_test_ctx());

	pg_assert_same('prototype', $target['kind']);
	pg_assert_same('/pinegrap/prototype.php?page=11', $target['href']);
}

// A bare query string is read the way a browser reads it against the view
// on screen: the path (page and item) stays, the record is the one the query
// names, the fragment is the link's.
function test_prototype_link_bare_query_keeps_the_view_it_sits_on()
{
	$ctx = prototype_viewer_test_ctx();

	// A record named in the query, and its section.
	pg_assert_same('/pinegrap/prototype.php?page=11&r=abc', pg_proto_link_target('?r=abc', $ctx)['href']);
	pg_assert_same('/pinegrap/prototype.php?page=11&r=abc&anchor=sec', pg_proto_link_target('?r=abc#sec', $ctx)['href']);

	// A catalog's pages and filters on a product group stay on that group.
	$ctx['current_page'] = 17;
	$ctx['current_sub'] = 'shirts';
	$target = pg_proto_link_target('?page=2', $ctx);
	pg_assert_same('prototype', $target['kind']);
	pg_assert_same(17, $target['page_id']);
	pg_assert_same('/pinegrap/prototype.php?page=17&sub=shirts', $target['href']);
	pg_assert_same('/pinegrap/prototype.php?page=17&sub=shirts', pg_proto_link_target('?page_number=2&sort=new', $ctx)['href']);

	// On a record, another query replaces it (as the browser does); the
	// same record with a section scrolls inside the frame.
	$ctx['current_page'] = 11;
	$ctx['current_sub'] = '';
	$ctx['current_record'] = 'abc';
	pg_assert_same('/pinegrap/prototype.php?page=11', pg_proto_link_target('?page=2', $ctx)['href']);
	$target = pg_proto_link_target('?r=abc#sec', $ctx);
	pg_assert_same('anchor', $target['kind']);
	pg_assert_same('#sec', $target['href']);
}

function prototype_viewer_test_rewrite($html)
{
	$ctx = prototype_viewer_test_ctx();

	return pg_proto_rewrite_links($html, function ($href) use ($ctx) {
		return pg_proto_link_target($href, $ctx);
	}, array('broken' => 'Nowhere', 'draft' => 'Another draft'));
}

function test_prototype_rewrite_points_draft_links_at_the_viewer()
{
	$out = prototype_viewer_test_rewrite('<p><a class="nav-link" href="/pricing" target="_self">Pricing</a></p>');

	pg_assert_same('<p><a class="nav-link" href="/pinegrap/prototype.php?page=12" target="_top" data-pg-proto="prototype">Pricing</a></p>', $out);
}

function test_prototype_rewrite_opens_site_and_outside_links_in_a_new_tab()
{
	$out = prototype_viewer_test_rewrite('<a href="/about" rel="nofollow">About</a> <A HREF=\'https://other.example.org/x\'>X</A>');

	pg_assert_contains('<a href="/about" rel="nofollow noopener" target="_blank" data-pg-proto="site">About</a>', $out);
	pg_assert_contains('<A href="https://other.example.org/x" target="_blank" rel="noopener noreferrer" data-pg-proto="external">X</A>', $out);
}

function test_prototype_rewrite_scrolls_a_section_of_the_page_in_view()
{
	$out = prototype_viewer_test_rewrite('<a href="/start#features" target="_blank" class="x">F</a>');

	pg_assert_same('<a href="#features" target="_self" class="x" data-pg-proto="anchor">F</a>', $out);
}

function test_prototype_rewrite_makes_broken_links_inert_and_marked()
{
	$out = prototype_viewer_test_rewrite('<a href="/no-such-page" class="btn">Go</a>');

	pg_assert_same('<a class="btn" data-pg-proto="broken" data-pg-proto-href="/no-such-page" aria-disabled="true" role="link" title="Nowhere">Go</a>', $out);
}

function test_prototype_rewrite_keeps_an_existing_title_on_a_broken_link()
{
	$out = prototype_viewer_test_rewrite('<a href="/other-draft" title="Own">Go</a>');

	pg_assert_same('<a title="Own" data-pg-proto="draft" data-pg-proto-href="/other-draft" aria-disabled="true" role="link">Go</a>', $out);
}

function test_prototype_rewrite_leaves_anchors_scripts_styles_and_comments_alone()
{
	$html = '<a href="#top" data-bs-toggle="collapse">Menu</a>'
		. '<a name="here">Here</a>'
		. '<script>var t = "<a href=\"/pricing\">x</a>";</script>'
		. '<style>a[href="/pricing"]{color:red}</style>'
		. '<!-- <a href="/pricing">old</a> -->'
		. '<textarea><a href="/pricing"></a></textarea>'
		. '<abbr title="x">X</abbr><article>Y</article>';

	pg_assert_same($html, prototype_viewer_test_rewrite($html));
}

function test_prototype_rewrite_reads_quoted_greater_than_and_entities()
{
	$out = prototype_viewer_test_rewrite('<a data-x="a>b" href="/pricing?r=ab&amp;x=1">P</a>');

	pg_assert_same('<a data-x="a>b" href="/pinegrap/prototype.php?page=12&amp;r=ab" target="_top" data-pg-proto="prototype">P</a>', $out);
}

function test_prototype_html_hrefs_lists_link_addresses_outside_scripts()
{
	$hrefs = pg_proto_html_hrefs('<a href="/a">A</a><script>"<a href=\'/b\'>"</script><a class="x" href=\'/c?x=1&amp;y=2\'>C</a><a>none</a>');

	pg_assert_same(array('/a', '/c?x=1&y=2'), $hrefs);
}

function test_prototype_frame_signature_round_trip()
{
	$sig = pg_proto_frame_sign(7, 12, 3, 1000900, 'k', 'red-shirt', 'ab12');
	$parsed = pg_proto_frame_parse(array('s' => '7', 'p' => '12', 'u' => '3', 'e' => '1000900', 'sig' => $sig, 'sub' => 'red-shirt', 'r' => 'ab12'));

	pg_assert_same(64, strlen($sig));
	pg_assert_same('ok', pg_proto_frame_state($parsed, 'k', 1000000));
	pg_assert_same('red-shirt', $parsed['sub']);
	pg_assert_same('ab12', $parsed['r']);
}

function test_prototype_frame_signature_refuses_changes_age_and_no_key()
{
	$sig = pg_proto_frame_sign(7, 12, 3, 1000900, 'k');
	$base = array('s' => '7', 'p' => '12', 'u' => '3', 'e' => '1000900', 'sig' => $sig);

	// An address opens only the page and the item it was made for.
	$other_page = $base;
	$other_page['p'] = '13';
	pg_assert_same('invalid', pg_proto_frame_state(pg_proto_frame_parse($other_page), 'k', 1000000));

	$other_sub = $base;
	$other_sub['sub'] = 'red-shirt';
	pg_assert_same('invalid', pg_proto_frame_state(pg_proto_frame_parse($other_sub), 'k', 1000000));

	$other_record = $base;
	$other_record['r'] = 'ab12';
	pg_assert_same('invalid', pg_proto_frame_state(pg_proto_frame_parse($other_record), 'k', 1000000));

	$other_design = $base;
	$other_design['s'] = '8';
	pg_assert_same('invalid', pg_proto_frame_state(pg_proto_frame_parse($other_design), 'k', 1000000));

	$other_user = $base;
	$other_user['u'] = '4';
	pg_assert_same('invalid', pg_proto_frame_state(pg_proto_frame_parse($other_user), 'k', 1000000));

	pg_assert_same('expired', pg_proto_frame_state(pg_proto_frame_parse($base), 'k', 1000901));
	pg_assert_same('invalid', pg_proto_frame_state(pg_proto_frame_parse($base), 'other', 1000000));
	pg_assert_same('invalid', pg_proto_frame_state(pg_proto_frame_parse($base), '', 1000000));
	pg_assert_same('', pg_proto_frame_sign(7, 12, 3, 1000900, ''));
	pg_assert_same('', pg_proto_frame_sign(7, 0, 3, 1000900, 'k'));

	// Made further ahead than a frame address ever is.
	$far = pg_proto_frame_sign(7, 12, 3, 1000000 + pg_proto_frame_ttl() + 3600, 'k');
	$far_query = array('s' => '7', 'p' => '12', 'u' => '3', 'e' => (string) (1000000 + pg_proto_frame_ttl() + 3600), 'sig' => $far);
	pg_assert_same('invalid', pg_proto_frame_state(pg_proto_frame_parse($far_query), 'k', 1000000));
}

function test_prototype_frame_parse_takes_plain_numbers_only()
{
	$sig = pg_proto_frame_sign(7, 12, 3, 1000900, 'k');

	pg_assert_same(null, pg_proto_frame_parse(array('s' => '07', 'p' => '12', 'u' => '3', 'e' => '1000900', 'sig' => $sig)));
	pg_assert_same(null, pg_proto_frame_parse(array('s' => array('7'), 'p' => '12', 'u' => '3', 'e' => '1000900', 'sig' => $sig)));
	pg_assert_same(null, pg_proto_frame_parse(array('s' => '7', 'p' => '12', 'u' => '3', 'e' => '1000900', 'sig' => 'zz')));
	pg_assert_same(null, pg_proto_frame_parse(null));
	// A record code or a sub-address that is not plain text is dropped, not refused.
	$parsed = pg_proto_frame_parse(array('s' => '7', 'p' => '12', 'u' => '3', 'e' => '1000900', 'sig' => $sig, 'sub' => array('x'), 'r' => "a\nb"));
	pg_assert_same('', $parsed['sub']);
	pg_assert_same('', $parsed['r']);
}

function test_prototype_viewer_admits_roles_zero_to_two_only()
{
	pg_assert_true(pg_proto_viewer_role_allowed(0));
	pg_assert_true(pg_proto_viewer_role_allowed('1'));
	pg_assert_true(pg_proto_viewer_role_allowed('2'));
	pg_assert_false(pg_proto_viewer_role_allowed(3));
	pg_assert_false(pg_proto_viewer_role_allowed('3'));
	pg_assert_false(pg_proto_viewer_role_allowed(''));
	pg_assert_false(pg_proto_viewer_role_allowed(null));
	pg_assert_false(pg_proto_viewer_role_allowed(-1));
}

function test_prototype_php_gates_the_viewer_and_the_frame()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/prototype.php');

	// The viewer: signed in (validate_user() sends anyone else to sign-in)
	// and a manager or above; a role-3 user is refused there.
	pg_assert_contains('$user = validate_user();', $source);
	pg_assert_contains("validate_area_access(\$user, 'manager');", $source);
	// The frame has no session: its signed address names the person, whose
	// role is read again on every frame.
	pg_assert_contains("define('PG_NO_SESSION', true);", $source);
	pg_assert_contains('$_COOKIE = array();', $source);
	pg_assert_contains('pg_proto_frame_state(', $source);
	pg_assert_contains('pg_proto_viewer_role_allowed(', $source);
	// A forged frame address is written to the firewall log and counted.
	pg_assert_contains('pg_proto_frame_offence();', $source);
	// Drawn as a background render with no page cache, never through
	// get_page.php's private gate.
	pg_assert_contains('pg_seo_rendering(true);', $source);
	pg_assert_false(strpos($source, 'pg_page_cache_store_page') !== false);
	pg_assert_same(0, preg_match('~(include|require)(_once)?\s*\(?[^;]*get_page\.php~', $source));
	pg_assert_contains("header('X-Robots-Tag: noindex, nofollow');", $source);
	pg_assert_contains("header('Cache-Control: no-store, private');", $source);

	// The widgets read the page's own address as the request, not the
	// signed frame address, and the cookie notice is answered with no;
	// both before the page is drawn.
	$render = strpos($source, 'get_page_content(');
	pg_assert_true($render !== false);

	foreach (array("\$_SERVER['REQUEST_URI'] = pg_proto_public_uri(", "unset(\$_SERVER['HTTP_X_REWRITE_URL']);", "\$_COOKIE['pg_consent'] = pg_proto_consent_refused(") as $needle) {
		$at = strpos($source, $needle);
		pg_assert_true(($at !== false) && ($at < $render), $needle);
	}

	// The session-less branch is decided before init.php and ends in exit;
	// the viewer draws nothing before its gate.
	pg_assert_true(strpos($source, "define('PG_NO_SESSION', true);") < strpos($source, "include('init.php');"));
	pg_assert_true(strpos($source, "validate_area_access(\$user, 'manager');") < strpos($source, 'echo pg_proto_shell_html('));
	pg_assert_true(strpos($source, 'echo $html;') < strpos($source, '$user = validate_user();'));
}

function test_background_render_prints_no_analytics_tag()
{
	// The SEO pass and the prototype frame are nobody's visit: the Google
	// tag is not printed, so no page view is reported with the frame's
	// address.
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');

	pg_assert_same(1, preg_match("~if \(\(GOOGLE_ANALYTICS == TRUE\) && \(GOOGLE_ANALYTICS_WEB_PROPERTY_ID != ''\) && !pg_seo_rendering\(\)\)~", $source));
}

// A My Account widget set to send signed-out visitors to the sign-in page
// draws its signed-out view in a background render (the prototype frame, the
// SEO pass): a redirect there would end the request mid-render.
function test_background_render_my_account_does_not_redirect()
{
	if (defined('USER_LOGGED_IN') && USER_LOGGED_IN) {
		return;
	}

	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'Notice: ^^not_logged_in^^'), 'children' => array()),
	));
	$cfg = array('redirect_to_login' => true, 'login_page_id' => 5, 'not_logged_in_message' => 'Sign in first');
	$seo = pg_seo_rendering();
	pg_seo_rendering(true);

	try {
		$html = _render_system_widget_my_account(json_encode($tree), 3, $cfg);
	} finally {
		pg_seo_rendering($seo);
	}

	pg_assert_same('<p>Notice: Sign in first</p>', $html);
}

function prototype_viewer_test_view()
{
	return array(
		'design' => 'Spring <site>',
		'pages' => pg_proto_page_set(prototype_viewer_test_rows(), 50, array(60), ''),
		'current' => 12,
		'fallback' => false,
		'frame_url' => '/pinegrap/prototype.php?frame=1&s=7&p=12',
		'viewer' => '/pinegrap/prototype.php',
		'close_url' => '/pinegrap/edit_system_style.php?id=7&page=12',
		'software' => '/pinegrap',
		'lang' => 'en',
		'error' => '',
	);
}

function test_prototype_shell_draws_bar_and_sandboxed_frame()
{
	$html = pg_proto_shell_html(prototype_viewer_test_view());

	pg_assert_contains('Spring &lt;site&gt;', $html);
	pg_assert_contains('<option value="/pinegrap/prototype.php?page=12" selected>', $html);
	pg_assert_contains('id="pg-proto-hotspots"', $html);
	pg_assert_contains('aria-pressed="false"', $html);
	pg_assert_contains('id="pg-proto-close"', $html);
	pg_assert_contains('src="/pinegrap/prototype.php?frame=1&amp;s=7&amp;p=12"', $html);

	preg_match('~<iframe[^>]*sandbox="([^"]*)"~', $html, $sandbox);
	pg_assert_true(isset($sandbox[1]));
	// No forms, no access to the site's cookies or storage from the page.
	pg_assert_false(strpos($sandbox[1], 'allow-forms') !== false);
	pg_assert_false(strpos($sandbox[1], 'allow-same-origin') !== false);
	pg_assert_contains('allow-scripts', $sandbox[1]);
	pg_assert_contains('allow-top-navigation-by-user-activation', $sandbox[1]);
	pg_assert_contains('allow-popups-to-escape-sandbox', $sandbox[1]);
}

function test_prototype_shell_without_drafts_says_so_and_draws_no_frame()
{
	$view = prototype_viewer_test_view();
	$view['pages'] = array();
	$view['current'] = 0;
	$view['frame_url'] = '';
	$html = pg_proto_shell_html($view);

	pg_assert_false(strpos($html, '<iframe') !== false);
	pg_assert_contains('pg-proto-empty', $html);
}

function test_prototype_frame_inject_adds_head_tags_and_helper()
{
	$out = pg_proto_frame_inject('<!DOCTYPE html><html><head><title>T</title></head><body><p>x</p></body></html>', array('forms' => 'No forms'));

	pg_assert_contains('<head><meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">', $out);
	pg_assert_contains('<style id="pg-proto-style">', $out);
	// The shim runs before any script of the page.
	pg_assert_true(strpos($out, '<script id="pg-proto-shim">') < strpos($out, '<title>'));
	pg_assert_contains('<script id="pg-proto-frame">', $out);
	pg_assert_true(strpos($out, '<script id="pg-proto-frame">') < strpos($out, '</body>'));
	pg_assert_contains('No forms', $out);

	// Hotspots outline every kind of link that goes somewhere, a section of
	// the view on screen included.
	foreach (array('prototype', 'anchor', 'site', 'external', 'contact', 'broken', 'draft') as $kind) {
		pg_assert_contains('.pg-proto-hotspots a[data-pg-proto="' . $kind . '"]', $out, $kind);
	}
}

function test_prototype_frame_inject_primes_the_cookie_jar_and_hides_the_notice()
{
	$out = pg_proto_frame_inject('<html><head></head><body></body></html>', array(), array('pg_consent' => '1.a0.m0'));

	pg_assert_contains('var jar = {"pg_consent":"1.a0.m0"};', $out);
	pg_assert_contains('#pg-cc{display:none!important}', $out);

	pg_assert_contains('var jar = {};', pg_proto_frame_inject('<p>x</p>', array()));
}

function test_prototype_frame_inject_copes_without_head_or_body()
{
	$out = pg_proto_frame_inject('<p>bare</p>', array());

	pg_assert_true(strpos($out, '<meta name="robots"') === 0);
	pg_assert_contains('<p>bare</p>', $out);
	pg_assert_contains('<script id="pg-proto-frame">', $out);
}
