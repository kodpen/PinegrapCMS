<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for quick edit beyond a list's first fields
 * (includes/fn/list_table_edit.php): the answer drawn from a row read
 * again, and the Pages list's switches decided by the bulk edit's rules.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Behind their gates these files only declare functions.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_bulk.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

function test_list_table_edit_answer_draws_the_cell_and_the_ones_beside_it()
{
	$row = array('id' => '7', 'class' => '', 'cells' => array(
		'id' => array('html' => '<a>SKU</a>', 'class' => 'align-middle', 'attrs' => array('data-pg-edit' => 'name')),
		'weight' => array('html' => '2 lb', 'class' => 'align-middle text-center'),
		'last_modified' => array('html' => 'now', 'class' => ''),
	));

	pg_assert_same(
		array('html' => '2 lb', 'class' => 'align-middle text-center', 'cells' => array('last_modified' => array('html' => 'now', 'class' => ''))),
		pg_list_table_edit_answer($row, 'weight', array('last_modified', 'missing', 'weight')),
		'the edited cell, then the others the row has');
	pg_assert_same(array(), pg_list_table_edit_answer(null, 'weight', array('last_modified')), 'a row no longer on the list draws nothing');
}

function test_page_quick_edit_columns_follow_the_user_rights()
{
	pg_assert_same(array('searchable', 'comments', 'sitemap'), array_keys(pg_page_quick_edit_columns()), 'without a user, the three switches');

	$choices = array('device' => 'desktop', 'preview_theme_id' => 0, 'folders' => array(1 => 'Root'), 'styles' => array('0' => 'Default', '5' => 'Two columns'), 'style_label' => 'Desktop Page Style', 'types' => array('standard' => 'Standard', 'custom form' => 'Custom Form'));
	$manager = pg_page_quick_edit_columns(array('id' => 1, 'role' => 2), $choices);

	pg_assert_same(array('name', 'folder', 'style', 'page_type', 'form_name', 'form_enabled', 'searchable', 'comments', 'sitemap'), array_keys($manager), 'staff change every column the page screen sets');
	pg_assert_same(array(1 => 'Root'), $manager['folder']['options'], 'the folders offered');
	pg_assert_true(!empty($manager['name']['required']) && ($manager['name']['max'] === 100), 'a name is required, at most 100 characters');

	$basic = pg_page_quick_edit_columns(array('id' => 2, 'role' => 3), $choices);

	pg_assert_true(!isset($basic['style']), 'a basic user sets no style, as on the page screen');
	pg_assert_true(!isset($basic['form_name']), 'nor a form without the custom form right');
	pg_assert_true(isset(pg_page_quick_edit_columns(array('id' => 2, 'role' => 3, 'set_page_type_custom_form' => 'yes'), $choices)['form_name']), 'with the right, the form fields');

	$columns = pg_list_table_columns(pg_page_list_columns(array('custom_form' => true, 'previewing_other_theme' => false, 'style_heading' => 'Page Style')));
	$editable = array();

	foreach ($columns as $key => $column) {
		if ($column['editable'] !== '') {
			$editable[] = $key;
		}
	}

	sort($editable);
	$fields = array_keys($manager);
	sort($fields);

	pg_assert_same($fields, $editable, 'each field has an editable column and nothing else is');
}

function test_page_quick_edit_values_and_cells()
{
	$page = array('page_name' => 'about-us', 'page_folder' => '4', 'page_search' => '1', 'sitemap' => '0', 'form_name' => 'Contact', 'enabled' => '1', 'style_value' => 7, 'page_type' => 'standard');

	pg_assert_same('about-us', pg_page_quick_edit_value('name', $page), 'the name');
	pg_assert_same('4', pg_page_quick_edit_value('folder', $page), 'the folder id');
	pg_assert_same('7', pg_page_quick_edit_value('style', $page), 'the style the cell shows');
	pg_assert_same('1', pg_page_quick_edit_value('form_enabled', $page), 'the form switch');
	pg_assert_same('1', pg_page_quick_edit_value('searchable', $page), 'searchable reads page_search');
	pg_assert_same('0', pg_page_quick_edit_value('comments', array()), 'a missing value opens as No');

	pg_assert_true(!pg_page_quick_cell_editable('style', $page, true), 'a visual editor page keeps its style');
	pg_assert_true(pg_page_quick_cell_editable('style', $page, false), 'another page changes its style');
	pg_assert_true(!pg_page_quick_cell_editable('form_name', $page, false), 'no form fields but on a custom form page');
	pg_assert_true(pg_page_quick_cell_editable('form_enabled', array('page_type' => 'custom form'), false), 'a custom form page has them');

	$columns = array('page_type' => array('options' => array('standard' => 'Standard', 'custom form' => 'Custom Form')));
	pg_assert_same('standard', pg_page_quick_edit_value('page_type', $page), 'the type');
	pg_assert_true(pg_page_quick_cell_editable('page_type', $page, false, $columns), 'a type the user may set changes');
	pg_assert_true(!pg_page_quick_cell_editable('page_type', array('page_type' => 'login'), false, $columns), 'a page of a type the user may not set keeps it, as on the page screen');
}

function test_page_quick_folder_choices_are_the_page_picker()
{
	$folders = array(
		array('folder_id' => 1, 'folder_name' => 'Root', 'folder_parent' => 0, 'folder_archived' => 0),
		array('folder_id' => 2, 'folder_name' => 'Blog', 'folder_parent' => 1, 'folder_archived' => 0),
		array('folder_id' => 3, 'folder_name' => 'Workspace', 'folder_parent' => 1, 'folder_archived' => 0),
		array('folder_id' => 4, 'folder_name' => 'Inside workspace', 'folder_parent' => 3, 'folder_archived' => 0),
		array('folder_id' => 5, 'folder_name' => 'Recycle Bin', 'folder_parent' => 1, 'folder_archived' => 0),
		array('folder_id' => 6, 'folder_name' => 'Old', 'folder_parent' => 2, 'folder_archived' => 1),
		array('folder_id' => 7, 'folder_name' => 'Hidden', 'folder_parent' => 1, 'folder_archived' => 0),
		array('folder_id' => 8, 'folder_name' => 'Below hidden', 'folder_parent' => 7, 'folder_archived' => 0),
	);
	$nb = "\u{00A0}\u{00A0}";

	pg_assert_same(
		array(1 => 'Root', 2 => $nb . 'Blog', 6 => $nb . $nb . 'Old [' . lang('ARCHIVED') . ']', 8 => $nb . 'Below hidden'),
		pg_page_quick_folder_choices($folders, function ($id) { return $id !== 7; }, 3, 5),
		'no Workspace or Recycle Bin or what is below them; a folder the user may not edit is left out, its children move up');
}

function test_page_quick_folder_classes_are_each_folders_own_access()
{
	$folders = array(
		array('folder_id' => 1, 'folder_access_control_type' => 'public'),
		array('folder_id' => 2, 'folder_access_control_type' => 'private'),
		array('folder_id' => 3, 'folder_access_control_type' => ''),
		array('folder_id' => 4, 'folder_access_control_type' => 'Membership'),
		array('folder_id' => 5, 'folder_access_control_type' => 'x" onclick="'),
		array('folder_id' => 6, 'folder_access_control_type' => 'guest'),
	);
	$choices = array(1 => 'Root', 2 => 'Secret', 3 => 'Inherits', 4 => 'Members', 5 => 'Odd');

	pg_assert_same(
		array(1 => 'public', 2 => 'private', 4 => 'membership'),
		pg_page_quick_folder_classes($folders, $choices),
		'each offered folder its own access class, as the page picker prints it; none for an inherited or unknown type, nothing for a folder not offered');
}

function test_page_quick_option_pairs_read_a_picker()
{
	pg_assert_same(
		array('0' => 'Default (inherit)', '5' => 'A & B', '9' => 'Two'),
		pg_page_quick_option_pairs('<option value="0">Default (inherit)</option><option value="5" selected>A &amp; B</option><option value="9">Two</option>'),
		'value => label, entities read');
	pg_assert_same(array(), pg_page_quick_option_pairs(''), 'no options');
	pg_assert_same(
		array('standard' => 'Standard', 'change password' => 'Change Password'),
		pg_page_quick_option_pairs('<option value="standard">Standard</option><optgroup label="Misc">
			<option value="change password" selected="selected">Change Password</option></optgroup>'),
		'the type picker: text values, groups read through');
}

function test_page_quick_decide_follows_the_bulk_rules()
{
	$page = array('page_id' => 3, 'page_type' => 'standard', 'page_folder' => 1, 'page_search' => 0, 'sitemap' => 0, 'noindex' => 0, 'nofollow' => 0, 'is_visual' => 0);

	$decided = pg_page_quick_decide($page, 'searchable', '1', 2);
	pg_assert_same(array('page_search' => 1), $decided['decision']['columns'], 'searchable writes page_search');
	pg_assert_same('add', $decided['decision']['tag_cloud'], 'its keywords join the tag cloud');
	pg_assert_true($decided['decision']['stale'], 'the SEO score goes stale');

	$decided = pg_page_quick_decide($page, 'comments', '1', 2);
	pg_assert_same(array('comments' => 1), $decided['decision']['columns'], 'comments write comments');
	pg_assert_true(!$decided['decision']['stale'], 'comments leave the SEO score alone');

	pg_assert_same(array('sitemap' => 1), pg_page_quick_decide($page, 'sitemap', '1', 2)['decision']['columns'], 'a standard page joins the site map');
	pg_assert_true(!pg_page_quick_decide(array('page_type' => 'logout') + $page, 'sitemap', '1', 2)['ok'], 'a type the site map does not list is refused');
	pg_assert_true(!pg_page_quick_decide(array('noindex' => 1) + $page, 'sitemap', '1', 2)['ok'], 'a page closed to search engines is refused');
	pg_assert_true(pg_page_quick_decide(array('noindex' => 1, 'sitemap' => 1) + $page, 'sitemap', '0', 2)['ok'], 'taking a page off the site map is always allowed');
	pg_assert_true(!pg_page_quick_decide($page, 'page_name', 'x', 2)['ok'], 'another field is refused');

	$decided = pg_page_quick_decide($page, 'folder', '9', 2);
	pg_assert_same(array('page_folder' => 9), $decided['decision']['columns'], 'a folder moves the page');
	pg_assert_true($decided['decision']['stale'], 'a move marks the SEO score stale');

	pg_assert_same(array('desktop' => 5), pg_page_quick_decide($page, 'style', '5', 2)['decision']['styles'], 'the desktop style on the desktop list');
	pg_assert_same(array('mobile' => 5), pg_page_quick_decide($page, 'style', '5', 2, 'mobile')['decision']['styles'], 'the mobile style on the mobile list');
	pg_assert_same(array('desktop' => 0), pg_page_quick_decide($page, 'style', '0', 2)['decision']['styles'], '0 goes back to the folder style');
	pg_assert_true(!pg_page_quick_decide(array('is_visual' => 1) + $page, 'style', '5', 2)['ok'], 'a visual editor page is refused, with the reason');
}

function test_page_quick_type_properties_start_like_a_new_page()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/forms/page_form.php');

	pg_assert_same(array(), pg_page_quick_type_properties('standard', 4), 'a type without a table of its own');

	$form = pg_page_quick_type_properties('custom form', 4);
	pg_assert_same(4, $form['page_id'], 'the row is the page\'s');
	pg_assert_same('1', $form['enabled'], 'the form starts switched on, as the page screen draws it');
	pg_assert_same('plain_text', $form['submitter_email_format'], 'a new page\'s e-mail format');
	pg_assert_same('', $form['form_name'], 'no name');

	$cart = pg_page_quick_type_properties('shopping cart', 4);
	pg_assert_same('full_description', $cart['product_description_type'], 'a new cart page\'s defaults');
	pg_assert_same(4, pg_page_quick_type_properties('photo gallery', 4)['number_of_columns'], 'a count starts at its default');
}
