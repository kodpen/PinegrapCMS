<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the bulk edit texts: the Modify Files, Modify Pages and Modify
 * Products dialogs print their instruction as visible text, and the variant
 * sets bulk bar names what it acts on.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function bulk_popup_texts_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The design files list's Modify Selected is a dialog of the page, no popup
// window: it shows its instruction, its fields belong to the list's form and
// edit_files.php only takes the post.
function test_bulk_popup_texts_modify_files_is_a_dialog()
{
	$dialog = bulk_popup_texts_source('includes/fn/files.php');
	$list = bulk_popup_texts_source('view_design_files.php');
	$post = bulk_popup_texts_source('edit_files.php');

	pg_assert_contains("<p class=\"small fg-secondary\">' . lang('You may update the selected Files via the form below. You may leave an option unselected if you do not want to modify a property.') . '</p>", $dialog, 'the dialog shows its instruction');
	pg_assert_contains('name="move_to_folder"\' . $owner . \'>', $dialog, 'the fields belong to the page form');
	pg_assert_contains('name="edit_design"\' . $owner . \'>', $dialog, 'the design field belongs to the page form');
	pg_assert_contains('pgUI.dialog.hide(dialog).then(function () {', $dialog, 'the form is sent once the dialog has closed');
	pg_assert_false(strpos($dialog, 'opener.') !== false, 'nothing written into an opener');
	pg_assert_contains("pg_design_files_modify_dialog(\$user, 'view_design_files_form')", $list, 'the list prints the dialog');
	pg_assert_contains('data-pg-modify-open', $list, 'Modify Selected opens the dialog');
	pg_assert_false(strpos($list, 'window.open(') !== false, 'no popup window');
	pg_assert_false(strpos($post, 'window.close()') !== false, 'edit_files.php only takes the post');
	pg_assert_false(strpos($post, 'output_header_secure(') !== false, 'edit_files.php draws no window');
}

// The Pages list's Modify Selected is a dialog of the list, no popup
// window: it shows its instruction and posts with the list's form.
function test_bulk_popup_texts_modify_pages_is_a_dialog()
{
	$dialog = bulk_popup_texts_source('includes/fn/page_bulk.php');
	$list = bulk_popup_texts_source('view_pages.php');

	pg_assert_contains("<p class=\"small fg-secondary\">' . lang('You may update various properties below for the selected pages. You may leave a pick list unselected if you do not want to modify a property.') . '</p>", $dialog, 'the dialog shows its instruction');
	pg_assert_contains("pg_page_bulk_edit_dialog(\$user, 'view_pages_form')", $list, 'the list prints the dialog');
	pg_assert_false(strpos($list, 'window.open(') !== false, 'no popup window');
	pg_assert_false(strpos(bulk_popup_texts_source('edit_pages.php'), 'opener.document') !== false, 'edit_pages.php only takes the post');
}

// The products list's and the product group screen's Modify Selected is a
// dialog of the page, no popup window: its fields belong to the page's form
// and edit_products.php only takes the post.
function test_bulk_popup_texts_modify_products_is_a_dialog()
{
	$dialog = bulk_popup_texts_source('includes/templates/edit_products.php');
	$list = bulk_popup_texts_source('view_products.php');
	$group = bulk_popup_texts_source('edit_product_group.php');

	pg_assert_contains("<p class=\"small fg-secondary\"><?=lang(array('string'=>'You may update the selected Products via the form below.' ))?></p>", $dialog, 'the dialog shows its instruction');
	pg_assert_contains('<dialog class="dialog', $dialog, 'an in-page dialog');
	pg_assert_contains('name="edit_enabled" class="form-control"<?=$owner?>', $dialog, 'the fields belong to the page form');
	pg_assert_contains('pgUI.dialog.hide(dialog).then(function () {', $dialog, 'the form is sent once the dialog has closed');
	pg_assert_false(strpos($dialog, 'opener.') !== false, 'nothing written into an opener');
	pg_assert_false(strpos($dialog, 'bd-theme') !== false, 'no theme menu');
	pg_assert_false(strpos($dialog, 'col-xs-') !== false, 'no Bootstrap 3 columns');
	pg_assert_false(strpos($dialog, 'popover') !== false, 'no popover boxes');
	pg_assert_contains("pg_product_modify_dialog('view_products_form')", $list, 'the list prints the dialog');
	pg_assert_contains("pg_product_modify_dialog('product_group_modify_form', ", $group, 'the group screen prints the dialog');

	foreach (array('view_products.php' => $list, 'edit_product_group.php' => $group) as $file => $source) {
		pg_assert_false(strpos($source, 'window.open(') !== false, $file . ': no popup window');
	}

	pg_assert_false(strpos(bulk_popup_texts_source('edit_products.php'), "require('includes/templates/edit_products.php')") !== false, 'edit_products.php only takes the post');
}

function test_bulk_popup_texts_variant_sets_bar_acts_on_the_selection()
{
	$source = bulk_popup_texts_source('product_builder.php');

	pg_assert_contains('data-pg-vs-status="enabled"><i class="bi bi-toggle-on me-3"></i>\' . lang(\'Enable Selected\') . \'</button>', $source, 'the enable button names the selection');
	pg_assert_contains('data-pg-vs-status="disabled"><i class="bi bi-toggle-off me-3"></i>\' . lang(\'Disable Selected\') . \'</button>', $source, 'the disable button names the selection');
}

// A list's bulk action that posts the page form (Delete Selected on the
// products list) asks through the panel's own dialog, not window.confirm(),
// and takes the action back when the answer is no.
function test_bulk_popup_texts_bulk_action_asks_with_pg_confirm()
{
	$js = bulk_popup_texts_source('assets/js/backend.src.js');
	$start = strpos($js, "case 'Delete Selected':");
	pg_assert_true($start !== false, 'the bulk handler is found');
	$block = substr($js, $start, 2500);

	pg_assert_contains("if (result == true && autoconfirm == false && confirm_content && !confirm_action && typeof window.pgConfirm === 'function') {", $block, 'a form-posting bulk action asks with pgConfirm');
	pg_assert_contains("variant: value == 'Delete Selected' ? 'danger' : 'primary'", $block, 'Delete Selected asks in red');
	pg_assert_contains("document.form.action.value = '';", $block, 'a refusal takes the action back');
	pg_assert_true(strpos($block, 'window.pgConfirm(') < strpos($block, 'if (confirm(confirm_content) == true)'), 'pgConfirm comes before the native fallback');
}
