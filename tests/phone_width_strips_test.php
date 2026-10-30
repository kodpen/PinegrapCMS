<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that three panel strips wrap at phone width instead of pushing the
 * page sideways: the e-invoice inbox fetch form, the header buttons of the
 * marketplace "Matched products" card and the image picker toolbar. Also
 * pins the Unsplash result tile as a keyboard-reachable button built
 * without innerHTML.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function phone_width_strips_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_phone_width_inbox_fetch_form_wraps()
{
	$source = phone_width_strips_test_source('erp_inbox.php');

	pg_assert_true(preg_match('/<form action="erp_inbox\.php" method="post" class="[^"]*\bflex-wrap\b[^"]*\brow-gap-2\b/', $source) === 1, 'fetch form wraps');

	// The date range stays together on one row; the button may drop below it.
	pg_assert_true(preg_match('/<span class="d-inline-flex align-items-center gap-1">\s*<input type="date" data-pg-date name="from"[^>]*>\s*<span class="fg-secondary">&ndash;<\/span>\s*<input type="date" data-pg-date name="to"[^>]*>\s*<\/span>/', $source) === 1, 'date range is one group');
}

function test_phone_width_marketplace_card_header_buttons_wrap()
{
	$source = phone_width_strips_test_source('marketplace_settings.php');

	preg_match_all('/<div class="ms-auto d-flex[^"]*">/', $source, $clusters);

	pg_assert_true(count($clusters[0]) >= 3, 'card header clusters found');

	foreach ($clusters[0] as $cluster) {
		pg_assert_true(strpos($cluster, ' flex-wrap ') !== false && strpos($cluster, 'justify-content-end') !== false, $cluster . ' wraps');
	}
}

function test_phone_width_image_picker_toolbar_wraps()
{
	$source = phone_width_strips_test_source('editor_select_image.php');

	pg_assert_true(preg_match('/@media \(max-width: 767\.98px\) \{(.*?)\n\}/s', $source, $block) === 1, 'phone block exists');

	pg_assert_true(preg_match('/#esi_toolbar\s*\{[^}]*flex-wrap: wrap/', (isset($block[1]) ? $block[1] : "")) === 1, 'toolbar wraps');
	pg_assert_true(preg_match('/#files_nav_controls\s*\{[^}]*flex-basis: 100%/', (isset($block[1]) ? $block[1] : "")) === 1, 'filters take their own row');
	pg_assert_true(preg_match('/\.esi-field,\s*\.esi-search\s*\{[^}]*flex: 1 1 0[^}]*min-width: 0/', (isset($block[1]) ? $block[1] : "")) === 1, 'fields share the row');
}

function test_phone_width_unsplash_tile_is_a_button()
{
	$source = phone_width_strips_test_source('editor_select_image.php');

	pg_assert_true(preg_match('/function esiRenderUnsplash\(photos\) \{(.*?)\n\}/s', $source, $render) === 1, 'renderer found');

	pg_assert_true(strpos($render[1], 'innerHTML') === false, 'no innerHTML for remote text');
	pg_assert_true(strpos($render[1], 'cursor-pointer') === false, 'no clickable div');
	pg_assert_true(preg_match('/createElement\(\\\\\'button\\\\\'\)/', $render[1]) === 1, 'tile is a button');
	pg_assert_true(strpos($render[1], '.type = \\\'button\\\'') !== false, 'button does not submit');
	pg_assert_true(strpos($render[1], 'a.textContent = text;') !== false && strpos($render[1], 'photo.user.name);') !== false, 'photographer name as text');
}
