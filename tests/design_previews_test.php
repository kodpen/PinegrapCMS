<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the live pictures of designs and templates
 * (includes/fn/design_previews.php): the box a picture is drawn in, the
 * preview address of a template page or tour (look and palette only from
 * the known lists) and the tour page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The first built-in template a preview can show, with its first page.
function _test_dp_template()
{
	static $found = null;
	if ($found !== null) return $found;
	$found = array('', '');
	foreach (pg_design_templates() as $id => $tpl) {
		if (empty($tpl['builtin'])) continue;
		$pages = pg_design_template_preview_pages($tpl);
		if (count($pages) < 2) continue;
		$keys = array_keys($pages);
		$found = array($id, $keys[0]);
		break;
	}
	return $found;
}

function test_live_picture_box_holds_the_address_but_no_frame()
{
	$html = pg_design_live_picture('/x/design_preview.php?template=a&page=home');
	pg_assert_contains('class="pg-live-picture"', $html);
	pg_assert_contains('data-pg-live-picture', $html);
	// The address is escaped once and the frame is made by the script on view.
	pg_assert_contains('data-src="/x/design_preview.php?template=a&amp;page=home"', $html);
	pg_assert_false(strpos($html, '<iframe') !== false, 'no frame before the box is seen');
	pg_assert_contains('pg-live-picture-wait', $html);
	pg_assert_false(strpos($html, 'pg-live-picture-fallback') !== false, 'no fallback unless given');
}

function test_live_picture_label_class_and_fallback()
{
	$html = pg_design_live_picture('?a=1', array(
		'label'    => 'Café <home>',
		'class'    => 'pg-x "y"',
		'fallback' => '<svg class="drawing"></svg>',
	));
	pg_assert_contains('role="img"', $html);
	pg_assert_contains('aria-label="Café &lt;home&gt;"', $html);
	pg_assert_contains('class="pg-live-picture pg-x &quot;y&quot;"', $html);
	pg_assert_contains('<div class="pg-live-picture-fallback" hidden><svg class="drawing"></svg></div>', $html);
}

function test_live_picture_without_address_shows_the_fallback_at_once()
{
	$html = pg_design_live_picture('', array('fallback' => '<b>x</b>'));
	pg_assert_contains('pg-failed', $html);
	pg_assert_false(strpos($html, 'data-src') !== false, 'no address to load');
	pg_assert_contains('<div class="pg-live-picture-fallback"><b>x</b></div>', $html);
}

function test_preview_address_installer_page()
{
	$url = pg_design_preview_address('install', array(
		'template' => 'pinegrap-cafe', 'page' => 'menu', 'look' => 'organic', 'palette' => 'cocoa', 'local' => 'tr',
	));
	pg_assert_same('?install_action=design_preview&template=pinegrap-cafe&look=organic&palette=cocoa&local=tr&page=menu', $url);
}

function test_preview_address_drops_unknown_look_palette_and_bad_values()
{
	$url = pg_design_preview_address('install', array(
		'template' => 'pinegrap-cafe', 'page' => 'menu"><x', 'look' => 'no-such-look', 'palette' => '../x', 'local' => 'tr"',
	));
	pg_assert_same('?install_action=design_preview&template=pinegrap-cafe', $url);
	pg_assert_same('', pg_design_preview_address('install', array('template' => '../etc')), 'a bad template gives no address');
	pg_assert_same('', pg_design_preview_address('nowhere', array('template' => 'pinegrap-cafe')), 'unknown place');
}

function test_preview_address_tour_and_page_placeholder()
{
	pg_assert_same(
		'?install_action=design_tour&template=custom-12&palette=cocoa&page=home',
		pg_design_preview_address('install', array('template' => 'custom-12', 'palette' => 'cocoa', 'tour' => true, 'page' => 'home'))
	);
	// {page} is left for pg_design_template_preview_html() to fill.
	pg_assert_same(
		'?install_action=design_preview&template=pinegrap-cafe&page={page}',
		pg_design_preview_address('install', array('template' => 'pinegrap-cafe', 'page' => '{page}'))
	);
}

function test_preview_address_panel()
{
	$url = pg_design_preview_address('panel', array('template' => 'pinegrap-cafe', 'page' => 'home', 'look' => 'organic', 'tour' => true, 'local' => 'tr'));
	pg_assert_true(substr($url, -strlen('design_preview.php?template=pinegrap-cafe&look=organic&tour=1&page=home')) === 'design_preview.php?template=pinegrap-cafe&look=organic&tour=1&page=home', $url);
}

function test_tour_lists_pages_and_mails_and_frames_the_start_page()
{
	list($id, $first) = _test_dp_template();
	pg_assert_true($id !== '', 'a built-in template with two preview pages');
	$tpl = pg_design_template($id);
	$pages = pg_design_template_preview_pages($tpl);
	$mails = pg_design_template_email_pages($tpl);
	$html = pg_design_tour_html(array('where' => 'install', 'template' => $id, 'look' => 'no-such', 'palette' => 'cocoa', 'local' => 'tr'));
	pg_assert_contains('<!doctype html>', $html);
	pg_assert_contains('<meta name="robots" content="noindex">', $html);
	foreach ($pages as $key => $title) {
		pg_assert_contains('<option value="' . h($key) . '"', $html);
	}
	if ($mails) pg_assert_contains('<optgroup label="' . h(lang('E-mails')) . '">', $html);
	pg_assert_contains('1 / ' . (count($pages) + count($mails)), $html);
	// The frame starts on the first page, its address from the known lists only.
	pg_assert_contains('src="' . h(pg_design_preview_address('install', array('template' => $id, 'palette' => 'cocoa', 'local' => 'tr', 'page' => $first))) . '"', $html);
	pg_assert_contains('sandbox="allow-scripts"', $html);
	pg_assert_contains('id="pg_tour_continue"', $html);
}

function test_tour_starts_on_the_asked_page_and_links_the_continue_button()
{
	list($id) = _test_dp_template();
	$pages = array_keys(pg_design_template_preview_pages(pg_design_template($id)));
	$html = pg_design_tour_html(array('where' => 'panel', 'template' => $id, 'page' => $pages[1], 'continue_href' => 'add_system_style.php?start=template&x=1', 'continue_label' => 'Use'));
	pg_assert_contains('2 / ', $html);
	pg_assert_contains('<option value="' . h($pages[1]) . '" selected', $html);
	pg_assert_contains('href="add_system_style.php?start=template&amp;x=1"', $html);
	pg_assert_contains('>Use</a>', $html);
}

function test_tour_of_an_unknown_template_is_empty()
{
	pg_assert_same('', pg_design_tour_html(array('where' => 'install', 'template' => 'no-such-template')));
}

function test_preview_request_reads_a_design_page()
{
	$r = pg_design_preview_request(array('page_id' => '42', 'template' => 'pinegrap-cafe'));
	pg_assert_same('page', $r['kind']);
	pg_assert_same(42, $r['page_id']);
	pg_assert_same('', $r['template'], 'a page id wins over a template');
	foreach (array('0', '-3', '4x', '12345678901', array('1')) as $bad) {
		pg_assert_same('', pg_design_preview_request(array('page_id' => $bad))['kind'], 'bad page id ' . json_encode($bad));
	}
}

function test_preview_request_reads_a_template_page_and_tour()
{
	$r = pg_design_preview_request(array('template' => 'pinegrap-cafe', 'page' => 'menu', 'look' => 'organic', 'palette' => 'cocoa'));
	pg_assert_same('template', $r['kind']);
	pg_assert_same('pinegrap-cafe', $r['template']);
	pg_assert_same('menu', $r['page']);
	pg_assert_same('organic', $r['look']);
	pg_assert_same('cocoa', $r['palette']);

	$r = pg_design_preview_request(array('template' => 'pinegrap-cafe', 'tour' => '1', 'page' => '../x', 'look' => 'nope', 'palette' => 'nope'));
	pg_assert_same('tour', $r['kind']);
	pg_assert_same('', $r['page'], 'a page key outside the pattern is dropped');
	pg_assert_same('', $r['look'], 'an unknown look is dropped');
	pg_assert_same('', $r['palette'], 'an unknown palette is dropped');

	pg_assert_same('', pg_design_preview_request(array('template' => 'Bad Id'))['kind']);
	pg_assert_same('', pg_design_preview_request(array())['kind']);
	pg_assert_same('', pg_design_preview_request('x')['kind']);
}

function test_preview_use_href_carries_the_theme()
{
	pg_assert_same('add_system_style.php?start=template&template=pinegrap-cafe&look=organic&palette=cocoa', pg_design_preview_use_href('pinegrap-cafe', 'organic', 'cocoa'));
	pg_assert_same('add_system_style.php?start=template&template=pinegrap-cafe', pg_design_preview_use_href('pinegrap-cafe'));
}

function test_preview_strips_every_script()
{
	$html = '<html><head><script src="a.js"></script><SCRIPT type="module">x()</SCRIPT ></head>'
		. '<body><p>Hi</p><script>if (a < b) { alert("</p>"); }</script><script src="b.js"/></body></html>';
	pg_assert_same('<html><head></head><body><p>Hi</p></body></html>', pg_design_preview_strip_scripts($html));
	pg_assert_same('<p>no script</p>', pg_design_preview_strip_scripts('<p>no script</p>'));
}

function test_preview_etag_follows_page_design_and_version()
{
	$a = pg_design_preview_etag(5, 100, 200, '2026.11.0');
	pg_assert_true((bool)preg_match('/^"dp-[0-9a-f]{20}"$/', $a), 'quoted strong tag');
	pg_assert_same($a, pg_design_preview_etag('5', '100', '200', '2026.11.0'));
	pg_assert_true($a !== pg_design_preview_etag(5, 101, 200, '2026.11.0'), 'page change');
	pg_assert_true($a !== pg_design_preview_etag(5, 100, 201, '2026.11.0'), 'design change');
	pg_assert_true($a !== pg_design_preview_etag(5, 100, 200, '2026.11.1'), 'new release');
	pg_assert_true($a !== pg_design_preview_etag(6, 100, 200, '2026.11.0'), 'other page');
}

function test_preview_etag_matching()
{
	$tag = '"dp-abc"';
	pg_assert_true(pg_design_preview_etag_matches('"dp-abc"', $tag));
	pg_assert_true(pg_design_preview_etag_matches('"x", W/"dp-abc"', $tag));
	pg_assert_true(pg_design_preview_etag_matches('*', $tag));
	pg_assert_false(pg_design_preview_etag_matches('', $tag));
	pg_assert_false(pg_design_preview_etag_matches('"dp-abd"', $tag));
}

function test_preview_placeholder_is_a_plain_escaped_page()
{
	$html = pg_design_preview_placeholder_html('No <page>');
	pg_assert_contains('<meta name="robots" content="noindex">', $html);
	pg_assert_contains('<p>No &lt;page&gt;</p>', $html);
	pg_assert_false(stripos($html, '<script') !== false, 'no script');
}
