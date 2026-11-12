<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bootstrap 3 to 5.3.8 converter of a Legacy design
 * (includes/fn/legacy_bootstrap.php): the class map, a tree, a sheet, the
 * report, and the starting sites' frames.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_bootstrap.php');

function test_lbs_grid_names()
{
	pg_assert_same('col-6 offset-md-2', pg_lbs_classes('col-xs-6 col-md-offset-2')['class']);
}
