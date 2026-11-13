<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for where a File Manager tile draws its SEO score ring
 * (view_folders.php): in the tile's top right corner, which the glyph leaves
 * free, for pages as for groups and products. At the lower left it covered
 * the start of a name that filled the tile or wrapped to a second line.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_explorer_seo_ring_sits_in_the_top_right_corner()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');

	pg_assert_contains('#explorer_grid .item-card .seo-ring { position: absolute; right: .3rem; top: .3rem; z-index: 2; }', $source,
		'every tile kind takes the corner the glyph leaves free');
	pg_assert_same(0, preg_match('/\.seo-ring\s*\{[^}]*\bbottom:\s*\.3rem/', $source), 'no ring left at the lower edge, over the name');
	pg_assert_same(0, preg_match('/data-kind="(group|product)"\] \.item-card \.seo-ring/', $source), 'no kind of its own any more');

	// The selection circle keeps the other top corner.
	pg_assert_contains('.sel-dot { position: absolute; top: .4rem; left: .4rem;', $source);
}
