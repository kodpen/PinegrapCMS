<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The public custom form endpoints read their query values only as text: a
 * value sent as an array (?r[]=x, ?page_id[]=x) reached trim() or escape(),
 * which throw a TypeError on PHP 8 and end the request with a server error.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_custom_form_confirmation_reference_code_is_read_as_text()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_custom_form_confirmation_screen_content.php');

	pg_assert_false(strpos($source, "trim(\$_GET['r'] ?? '')") !== false, 'the reference code is not trimmed unchecked');
	pg_assert_contains("is_string(\$_GET['r'] ?? null) ? trim(\$_GET['r']) : ''", $source, 'an array reference code reads as missing');
}

function test_custom_form_fields_page_id_is_read_as_a_number()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_custom_form_fields.php');

	pg_assert_false(strpos($source, "escape(\$_GET['page_id'])") !== false, 'the page id is not escaped unchecked');
	pg_assert_contains("is_scalar(\$_GET['page_id'] ?? null) ? (int) \$_GET['page_id'] : 0", $source, 'an array page id reads as 0');
}
