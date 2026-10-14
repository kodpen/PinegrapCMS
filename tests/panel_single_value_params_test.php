<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the single value parameters of the panel screens that ended in an
 * error page when one came as an array (?page_id[]=x): escape(), trim() and
 * urlencode() refuse an array. Each screen hands them to
 * pg_request_single_values() at the top, so the request counts as one without
 * them. The call has to come before the screen first reads the parameter.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Screen => parameters it reads as one value.
function panel_single_value_params_screens()
{
	return array(
		'add_email_campaign'         => array('page_id'),
		'add_field'                  => array('page_id', 'product_group_id', 'product_id'),
		'add_menu_item'              => array('menu_id'),
		'add_submitted_form'         => array('page_id'),
		'add_user'                   => array('contact_id'),
		'browse_catalog_menu'        => array('current_page_id', 'product_group_id', 'previous_url_id'),
		'check_if_file_exists'       => array('file_name'),
		'edit_form_item_view'        => array('page_id'),
		'edit_form_list_view'        => array('page_id'),
		'editor_select_image'        => array('CKEditorFuncNum', 'SingleImage', 'file_input_name'),
		'editor_select_page_or_file' => array('CKEditorFuncNum'),
		'generate_layout'            => array('page_id'),
		'get_theme_designer_module'  => array('file_id', 'module_id'),
		'image_editor_edit'          => array('file_name', 'object_type', 'object_id', 'column_to_update'),
		'import_design'              => array('submit_button'),
		'preview_form'               => array('product_id'),
		'print_packing_slip'         => array('ship_to_id'),
		'view_fields'                => array('page_id', 'product_id', 'product_group_id'),
	);
}

// Each listed parameter is in the screen's pg_request_single_values() call,
// and the screen reads it from $_GET, $_POST or $_REQUEST only after the call.
function test_panel_single_value_params_dropped_before_first_read()
{
	foreach (panel_single_value_params_screens() as $screen => $parameters) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen . '.php');

		if (!preg_match('/pg_request_single_values\(array\(([^)]*)\)\);/', $source, $call, PREG_OFFSET_CAPTURE)) {
			pg_assert_true(false, $screen . ': no pg_request_single_values() call');
			continue;
		}

		$call_offset = $call[0][1];
		preg_match_all("/'([^']+)'/", $call[1][0], $names);

		foreach ($parameters as $parameter) {
			pg_assert_true(in_array($parameter, $names[1], true), $screen . ': ' . $parameter . ' not dropped');

			foreach (array('_GET', '_POST', '_REQUEST') as $array) {
				$first_read = strpos($source, '$' . $array . "['" . $parameter . "']");

				if ($first_read !== false) {
					pg_assert_true($first_read > $call_offset, $screen . ': $' . $array . "['" . $parameter . "'] read before the call");
				}
			}
		}
	}
}
