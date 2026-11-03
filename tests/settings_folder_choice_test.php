<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Site Settings folder pickers (product images, the folder an
 * order grants access to, API uploads): the Workspace's folders and the
 * Recycle Bin are no choice (pg_settings_folder_choice_verdict() in
 * includes/fn/auth.php), and the pickers and saves use it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_settings_folder_choice_verdict()
{
	// 1 top; 2 Workspace under it, 3 a channel, 4 a note below the channel;
	// 5 the Recycle Bin, 6 inside it; 7 an ordinary folder.
	$rows = array(
		1 => array('folder_parent' => 0),
		2 => array('folder_parent' => 1),
		3 => array('folder_parent' => 2),
		4 => array('folder_parent' => 3),
		5 => array('folder_parent' => 1),
		6 => array('folder_parent' => 5),
		7 => array('folder_parent' => 1),
	);

	pg_assert_true(pg_settings_folder_choice_verdict(0, $rows, 2, 5), 'nothing chosen');
	pg_assert_true(pg_settings_folder_choice_verdict(7, $rows, 2, 5), 'an ordinary folder');
	pg_assert_true(pg_settings_folder_choice_verdict(1, $rows, 2, 5), 'the top folder');
	pg_assert_false(pg_settings_folder_choice_verdict(2, $rows, 2, 5), 'the Workspace folder');
	pg_assert_false(pg_settings_folder_choice_verdict(3, $rows, 2, 5), 'a channel folder');
	pg_assert_false(pg_settings_folder_choice_verdict(4, $rows, 2, 5), 'a folder deeper in the Workspace');
	pg_assert_false(pg_settings_folder_choice_verdict(6, $rows, 2, 5), 'a folder in the Recycle Bin');
	pg_assert_true(pg_settings_folder_choice_verdict(3, $rows, 0, 0), 'no Workspace and no bin: every folder');
}

function test_settings_folder_pickers_and_saves_wired()
{
	$commerce = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/commerce.php');
	pg_assert_contains('pg_settings_select_folder($product_upload_folder_id)', $commerce);
	pg_assert_contains("pg_settings_select_folder(\$ecommerce_private_folder_id, 'private')", $commerce);

	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/api.php');
	pg_assert_contains('pg_settings_select_folder($api_upload_folder_id)', $api);

	$commerce_save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/commerce.save.php');
	pg_assert_contains("array('product_upload_folder_id', 'ecommerce_private_folder_id')", $commerce_save);
	pg_assert_contains('pg_settings_folder_choice_ok(', $commerce_save);

	$api_save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/api.save.php');
	pg_assert_contains("pg_settings_folder_choice_ok((int) post_value('api_upload_folder_id'))", $api_save);
}
