<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the upload cap (includes/fn/image.php): how a stored value is
 * clamped, which uploaded pictures are scaled down and with which engine
 * options, the file manager's upload options under the cap, and the
 * migration step and installer value that bring the setting.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

// 0 and anything unreadable or negative is off; a set value is held to the
// range of the other image limits.
function test_image_upload_cap_clamp()
{
	pg_assert_same(0, pg_image_upload_cap_clamp(0), 'zero is off');
	pg_assert_same(0, pg_image_upload_cap_clamp(''), 'empty is off');
	pg_assert_same(0, pg_image_upload_cap_clamp(null), 'null is off');
	pg_assert_same(0, pg_image_upload_cap_clamp('abc'), 'text is off');
	pg_assert_same(0, pg_image_upload_cap_clamp(-5), 'negative is off');
	pg_assert_same(200, pg_image_upload_cap_clamp(10), 'raised to the floor');
	pg_assert_same(2560, pg_image_upload_cap_clamp('2560'), 'a number as text');
	pg_assert_same(20000, pg_image_upload_cap_clamp(65535), 'held to the ceiling');
}

// Only a picture whose long edge is over the cap, of a format the engine
// writes back as itself, is given options; the quality is the resize one.
function test_image_upload_cap_options()
{
	$settings = array('upload_max_dimension' => 2560, 'resize_quality' => 82);
	$options = array('max_dimension' => 2560, 'quality' => 82);

	pg_assert_same($options, pg_image_upload_cap_options(4000, 3000, 'jpg', $settings), 'landscape over the cap');
	pg_assert_same($options, pg_image_upload_cap_options(3000, 4000, 'JPEG', $settings), 'portrait over the cap, extension case');
	pg_assert_same($options, pg_image_upload_cap_options(2561, 100, 'webp', $settings), 'one pixel over');
	pg_assert_same(null, pg_image_upload_cap_options(2560, 1440, 'png', $settings), 'at the cap: stored as it arrived');
	pg_assert_same(null, pg_image_upload_cap_options(800, 600, 'gif', $settings), 'under the cap');
	pg_assert_same(null, pg_image_upload_cap_options(9000, 6000, 'tif', $settings), 'a format the engine cannot write');
	pg_assert_same(null, pg_image_upload_cap_options(9000, 6000, 'pdf', $settings), 'not a picture');
	pg_assert_same(null, pg_image_upload_cap_options(0, 0, 'jpg', $settings), 'unmeasured');
	pg_assert_same(null, pg_image_upload_cap_options(9000, 6000, 'jpg', array('upload_max_dimension' => 0, 'resize_quality' => 82)), 'cap off');
	pg_assert_same(null, pg_image_upload_cap_options(9000, 6000, 'jpg', array()), 'no setting');
}

// The file manager's upload: the cap is merged unless optimize brings its
// own product ceiling; a WebP conversion under the cap is scaled as well.
function test_explorer_upload_options_under_the_cap()
{
	$settings = array('product_max_dimension' => 2000, 'product_min_dimension' => 300, 'resize_quality' => 82);
	$cap = array('max_dimension' => 2560, 'quality' => 82);

	pg_assert_same(
		array('quality' => 82, 'max_dimension' => 2560),
		pg_explorer_upload_image_options($settings, false, false, 'jpg', $cap),
		'cap alone');

	pg_assert_same(
		array('quality' => 82, 'max_dimension' => 2560, 'format' => 'webp'),
		pg_explorer_upload_image_options($settings, false, true, 'jpg', $cap),
		'cap and webp');

	pg_assert_same(
		array('max_dimension' => 2000, 'min_dimension' => 300, 'quality' => 82),
		pg_explorer_upload_image_options($settings, true, false, 'jpg', $cap),
		'optimize keeps the product ceiling');

	pg_assert_same(
		array('quality' => 82, 'format' => 'webp'),
		pg_explorer_upload_image_options($settings, false, true, 'png', null),
		'no cap: webp only, nothing scaled');
}

// The column comes from step 11.0.142, off on a running site; a new starter
// site gets 2560 from the installer.
function test_image_upload_cap_migration_and_install()
{
	$migration = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	$install = (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');

	pg_assert_contains("upgrade_2026_11_0_image_upload_max_dimension(); // 11.0.142", $migration, 'step called with its label');
	pg_assert_contains("install_add_column('config', 'image_upload_max_dimension', \"SMALLINT UNSIGNED NOT NULL DEFAULT 0\");", $migration, 'column off by default');
	pg_assert_contains("db(\"UPDATE config SET image_upload_max_dimension = 2560\");", $install, 'new install value');
}
