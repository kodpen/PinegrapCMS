<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the 3D Secure checkbox tooltip on the express order and order
 * preview pages: the visitor sees it in the site language, not in English.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_threeds_required_title_is_translated()
{
	foreach (array('get_express_order.php', 'get_order_preview.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_same(false, strpos($source, 'title="3DSecure is required"') !== false, $file . ': no fixed English tooltip');
		pg_assert_same(true, strpos($source, "title=\"' . h(lang('3D Secure is required')) . '\"") !== false, $file . ': the tooltip goes through lang()');
	}
}
