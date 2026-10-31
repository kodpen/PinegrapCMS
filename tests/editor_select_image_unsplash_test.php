<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the picture picker's Unsplash Load More: a failed request rolls the
 * page counter back, an empty later page keeps the count already shown, and
 * focus moves to a tile before the button hides.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_editor_select_image_unsplash_load_more_keeps_state()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/editor_select_image.php');

	pg_assert_same(2, substr_count($source, '_unsplashPage--;'), 'both loaders roll the page back on a failed request');
	pg_assert_contains('status.textContent = loadMore ? status.getAttribute(\\\'data-count\\\')', $source);
	pg_assert_contains('if (!hasMore && more.contains(document.activeElement) && first) first.focus();', $source);
	pg_assert_contains("    return first;\n}", $source);
	pg_assert_same(3, substr_count($source, 'esiUnsplashShow('), 'one helper and its two callers');
}
