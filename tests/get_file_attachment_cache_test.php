<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for two attachment answers of get_file.php: a submitted-form or
 * comment attachment is never sent for a shared cache, whatever its folder,
 * so a CDN never serves it past the attachment check; and a list view whose
 * linked item view page is gone grants nothing through that page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function get_file_attachment_cache_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_file.php');
}

function test_get_file_attachment_is_private_to_caches()
{
	$source = get_file_attachment_cache_test_source();
	$rule = strpos($source, "if (\$file['attachment'] == 1) {\n    \$cache_control_type = 'private';\n}");
	$header = strpos($source, "header('Cache-Control: ' . ((\$erp_document || \$chat_attachment)");

	pg_assert_true($rule !== false, 'an attachment is private to shared caches');
	pg_assert_true(($rule !== false) && ($header !== false) && ($rule < $header), 'decided before the header is sent');
}

function test_get_file_missing_item_view_grants_nothing()
{
	$source = get_file_attachment_cache_test_source();

	pg_assert_contains("is_array(\$form_item_view)\n                            && (\$form_item_view['folder_id'] !== null)", $source, 'a gone item view page is no way in');
}
