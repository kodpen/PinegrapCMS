<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Art Gallery design template's artists and works
 * (includes/design_templates/pinegrap-gallery/artists.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once __DIR__ . '/design_template_gallery_test.php';

function test_gallery_artists_part_exists()
{
	pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery/artists.php'), 'artists.php exists');
}
