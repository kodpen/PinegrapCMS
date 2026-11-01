<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's redaction: the block and blur sizes the
 * server burns in are the preview's (the model, run under Node), the regions
 * a save sends are checked before any pixel work, GD and Imagick hide what
 * lies under a region and leave the rest, and a redacted save keeps no
 * metadata.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The block and blur sizes grow with the strength and the picture.
function test_image_editor_redact_sizes()
{
	$weak = pg_image_edit_redact_sizes(10, 2000, 1.0);
	$strong = pg_image_edit_redact_sizes(90, 2000, 1.0);

	pg_assert_true($strong['block'] > $weak['block'], 'a stronger pixelation has bigger blocks');
	pg_assert_true($strong['sigma'] > $weak['sigma'], 'a stronger blur is wider');
	pg_assert_same(70, pg_image_edit_redact_sizes(100, 2000, 1.0)['block'], 'blocks of 3.5 % of the longer side at full strength');
}
