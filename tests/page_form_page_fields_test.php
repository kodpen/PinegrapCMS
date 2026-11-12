<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The page settings of Create Page and Edit Page are page picker fields
 * (pg_page_form_page_field()): each carries only its stored page, a setting
 * that takes some page types searches only those (pg_picker_page_type_kinds()),
 * and the designer's page type pane keeps its page selects. A stored page in
 * an archived folder or in the Recycle Bin stays, marked.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function page_form_page_fields_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_page_form_page_fields_options_html()
{
	$options = array(
		array('id' => 3, 'label' => 'about', 'selected' => false),
		array('id' => 7, 'label' => 'contact <us>', 'selected' => true),
	);

	pg_assert_same(
		'<option value="3">about</option><option value="7" selected="selected">contact &lt;us&gt;</option>',
		pg_page_select_options_html($options),
		'the pane\'s select: every page an option, the stored one selected, names escaped');
	pg_assert_same('', pg_page_select_options_html(array()));
}

function test_page_form_page_fields_kind_for_types()
{
	pg_assert_same('page', pg_picker_page_kind_for(''), 'any page');
	pg_assert_same('page', pg_picker_page_kind_for(array()), 'an empty list is any page');
	pg_assert_same('page_catalog_detail', pg_picker_page_kind_for('catalog detail'));
	pg_assert_same('page_preview_step', pg_picker_page_kind_for(array('express order', 'order preview')), 'the order of the types does not matter');
	pg_assert_same('', pg_picker_page_kind_for('folder view'), 'no kind lists it');

	$types = array_keys(get_page_type_names());

	foreach (pg_picker_page_type_kinds() as $name => $listed) {
		pg_assert_same($name, pg_picker_page_kind_for($listed), $name . ' found by its types');

		foreach ($listed as $type) {
			pg_assert_true(in_array($type, $types, true), $name . ': ' . $type . ' is a page type');
		}
	}
}

function test_page_form_page_fields_every_setting_has_a_kind()
{
	$source = page_form_page_fields_test_source('includes/forms/page_form.php');

	pg_assert_false(strpos($source, 'select_page(') !== strrpos($source, 'select_page('), 'select_page() only in the pane\'s select');
	pg_assert_false(strpos($source, 'pg_page_picker_template') !== false, 'no template fill');
	pg_assert_contains('$output_page_type_properties .= pg_picker_script();', $source, 'the picker\'s script goes with the fields');

	preg_match_all("/pg_page_form_page_field\('([a-z_]+)', \((\\\$[a-z_]+)\['([a-z_]+)'\] \?\? ''\), ('[a-z ]*'|array\([^)]*\)),/", $source, $calls, PREG_SET_ORDER);
	pg_assert_same(35, count($calls), 'the page settings of every type');

	foreach ($calls as $call) {
		$types = ($call[4][0] === "'") ? trim($call[4], "'") : array_map(function ($type) { return trim($type, " '"); }, explode(',', substr($call[4], 6, -1)));
		pg_assert_true(pg_picker_page_kind_for($types) !== '', $call[1] . ' has a kind');
	}

	foreach (array('add_page.php', 'edit_page.php') as $file) {
		pg_assert_contains("        true);\n", page_form_page_fields_test_source($file), $file . ' draws picker fields');
	}

	pg_assert_contains('$fields = pg_page_form_type_fields($page_type, $row[\'layout_type\'], $properties, $user, $page_id);', page_form_page_fields_test_source('includes/fn/page_type_pane.php'), 'the designer\'s pane keeps its selects');
}

function test_page_form_page_fields_draws_a_picker_field()
{
	pg_page_form_picker_fields(true);
	$html = pg_page_form_page_field('catalog_catalog_detail_page_id', '', 'catalog detail', 'Select Page');
	pg_page_form_picker_fields(false);

	pg_assert_contains('data-pg-picker-kind="page_catalog_detail"', $html, 'a catalog detail page only');
	pg_assert_contains('<input type="hidden" name="catalog_catalog_detail_page_id" value="" data-pg-picker-value/>', $html);
	pg_assert_contains('id="catalog_catalog_detail_page_id" class="pg-pfield-input"', $html, 'the label names the text input');
	pg_assert_contains('data-pg-picker-title="' . h(lang('Choose a page')) . '"', $html);
	pg_assert_false(pg_page_form_picker_fields(), 'switched off again');
}

function test_page_form_page_fields_stored_page_marks()
{
	$kinds = pg_picker_kinds();
	$page = $kinds['page'];

	$missing = pg_picker_field_view($page, 42, null);
	pg_assert_same(array('missing', '#42', 'warning'), array($missing['state'], $missing['text'], $missing['tone']));
	pg_assert_same(lang('This page no longer exists; choose another.'), $missing['note']);

	$archived = pg_picker_field_view($page, 7, array('state' => 'offline', 'text' => 'Old offers', 'address' => '/old-offers'));
	pg_assert_same(array('Old offers', 'warning', 'bi-file-earmark-text'), array($archived['text'], $archived['tone'], $archived['icon']), 'kept under its name, marked');
	pg_assert_same(lang('This page is in an archived folder; visitors cannot open it. Choose another, or take its folder out of the archive.'), $archived['note']);

	$binned = pg_picker_field_view($page, 8, array('state' => 'binned', 'text' => 'Terms'));
	pg_assert_same(array('Terms', 'danger'), array($binned['text'], $binned['tone']));
	pg_assert_same(lang('This page is in the Recycle Bin; visitors cannot open it. Restore it or choose another.'), $binned['note']);

	pg_assert_same(lang('A page in a folder you cannot open'), pg_picker_field_view($page, 9, array('state' => 'hidden'))['text']);
}

function test_page_form_page_fields_other_screens()
{
	foreach (array(
		'edit_page.php' => array('comments_submitter_email_page_id', 'comments_watcher_email_page_id'),
		'add_user.php' => array('home_page'),
		'edit_user.php' => array('home_page'),
		'import_users.php' => array('home_page'),
		'includes/settings/commerce.php' => array('ecommerce_reward_program_email_page_id', 'ecommerce_retrieve_order_next_page_id'),
		'includes/settings/privacy.php' => array('ecommerce_kvkk_page_id'),
		'includes/settings/security.php' => array('membership_expiration_warning_email_page_id'),
	) as $file => $names) {
		$source = page_form_page_fields_test_source($file);
		pg_assert_false(strpos($source, 'select_page(') !== false, $file . ' prints no page select');
		pg_assert_contains('pg_picker_script()', $source, $file . ' prints the picker\'s script');

		foreach ($names as $name) {
			pg_assert_contains("pg_picker_field('page', '" . $name . "', ", $source, $file . ': ' . $name);
		}
	}

	// A cleared start page posts '', written as no page.
	pg_assert_contains("'\" . (int) (\$fields['home_page'] ?? 0) . \"',", page_form_page_fields_test_source('includes/fn/user_write.php'));
	pg_assert_contains("'\" . (int) (\$_POST['home_page'] ?? 0) . \"',", page_form_page_fields_test_source('import_users.php'));
}
