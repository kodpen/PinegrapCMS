<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The product form field list, Delete Selected on product and variant-set
 * forms and the product group screen read variables that only another
 * branch sets, and posted fields that a form does not always send. On PHP 8
 * each read is an E_WARNING, printed before header('Location') on the save
 * paths. These checks pin the guards in the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function product_form_group_warnings_source($file)
{
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// view_fields.php: the product branch never sets $delete_data_warning.
function test_view_fields_product_branch_has_no_delete_data_warning()
{
	$source = product_form_group_warnings_source('view_fields.php');
	$start = strpos($source, "\$form_type = 'product';");
	$end = strpos($source, "\$form_type = 'product_group';");
	pg_assert_true($start !== false && $end !== false && $start < $end, 'view_fields.php product branch was found');
	pg_assert_same(false, strpos(substr($source, $start, $end - $start), '$delete_data_warning'), 'view_fields.php product branch does not read $delete_data_warning');
}

// delete_fields.php: $page_type and $page_name exist before the page branch.
function test_delete_fields_sets_page_variables_for_every_form()
{
	$source = product_form_group_warnings_source('delete_fields.php');
	$branch = strpos($source, "if ((isset(\$_POST['page_id']))");
	pg_assert_true($branch !== false, 'delete_fields.php page branch was found');
	$head = substr($source, 0, $branch);
	pg_assert_contains("\$page_type = '';", $head, 'delete_fields.php sets $page_type before the page branch');
	pg_assert_contains("\$page_name = '';", $head, 'delete_fields.php sets $page_name before the page branch');
}

// edit_product_group.php: the switch state and the optional posted fields.
function test_edit_product_group_guards_optional_values()
{
	$source = product_form_group_warnings_source('edit_product_group.php');
	pg_assert_contains("\$output_enable_attributes_checked = '';", $source, 'edit_product_group.php sets the attributes switch state for every group');
	foreach (array('enable_attributes_exists', 'attributes', 'enabled') as $field) {
		$count = preg_match_all('/\$_POST\[\'' . $field . '\'\](?!\s*\?\?)/', $source);
		pg_assert_same(0, $count, 'edit_product_group.php reads $_POST[\'' . $field . '\'] without ??');
	}
}
