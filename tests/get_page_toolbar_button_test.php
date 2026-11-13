<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The page toolbar of a signed-in user (get_page.php): a user who gets the
 * toolbar without edit access to the page gets no edit button, and the
 * markup that prints the button still finds the variable set, so no PHP
 * warning (with the server's path in it) reaches the page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_get_page_grid_button_set_without_edit_access()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page.php');
	$branch = strpos($source, 'if (($edit_access == true) && $pg_is_visual_page)');
	$used = strpos($source, "' . \$output_grid_button . '");
	pg_assert_true(($branch !== false) && ($used !== false), 'the edit button branch and the toolbar markup are found');
	if (($branch === false) || ($used === false)) {
		return;
	}

	// The last assignment before the branch, with nothing between it and the
	// branch but comments: every path to the markup has the variable.
	$before = substr($source, 0, $branch);
	$init = strrpos($before, "\$output_grid_button = '';");
	pg_assert_true($init !== false, 'the button starts empty');
	if ($init === false) {
		return;
	}

	$between = trim(preg_replace('~//[^\n]*~', '', substr($before, $init + strlen("\$output_grid_button = '';"))));
	pg_assert_same('', $between, 'it is set right before the branch');
	pg_assert_true($used > $branch, 'the markup prints it after the branch');
}
