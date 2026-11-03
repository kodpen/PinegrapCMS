<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the requested page slug the system widgets read
 * (_pg_sw_current_page_id() in includes/fn/widgets.php): a page parameter
 * that is not a string, as ?page[]=2 on a panel screen, is no slug.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');

function test_widgets_page_param_array_is_no_slug()
{
	$kept = isset($_GET['page']) ? $_GET['page'] : null;

	$_GET['page'] = array('2');

	try {
		pg_assert_same(0, _pg_sw_current_page_id(), 'an array page parameter raises no warning and names no page');
	} finally {
		if ($kept === null) {
			unset($_GET['page']);
		} else {
			$_GET['page'] = $kept;
		}
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');

	pg_assert_contains("is_string(\$_GET['page'])", $source, 'the slug is read only from a string');
}
