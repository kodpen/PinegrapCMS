<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the page picker fields outside the page form: the short link
 * window's page slots, the field's extra hidden input attributes, the catalog
 * page kind, pgPicker.clear() and the screens that print the fields.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

function picker_page_fields_screens_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_short_link_page_slots_follow_the_classic_form()
{
	$none = array('page' => 0, 'catalog_page' => 0, 'catalog_detail_page' => 0);

	pg_assert_same(array_merge($none, array('page' => 12)), pg_short_link_page_slots('page', 12, 'standard'), 'a page link fills Page');
	pg_assert_same(array_merge($none, array('catalog_page' => 7)), pg_short_link_page_slots('product_group', '7', 'catalog'), 'a group on a catalog fills Catalog Page');
	pg_assert_same(array_merge($none, array('catalog_detail_page' => 7)), pg_short_link_page_slots('product_group', 7, 'catalog detail'), 'a group on a detail page fills Catalog Detail Page');
	pg_assert_same(array_merge($none, array('catalog_detail_page' => 7)), pg_short_link_page_slots('product_group', 7, ''), 'a group whose page is gone: Catalog Detail Page, as the classic form');
	pg_assert_same(array_merge($none, array('catalog_detail_page' => 9)), pg_short_link_page_slots('product', 9, 'catalog detail'), 'a product link fills Catalog Detail Page');
	pg_assert_same($none, pg_short_link_page_slots('url', 9, 'standard'), 'an address link has no page');
	pg_assert_same($none, pg_short_link_page_slots('page', 0, ''), 'no page');
	pg_assert_same($none, pg_short_link_page_slots('page', array(3), ''), 'a list is no page');
	pg_assert_same($none, pg_short_link_page_slots('page', -4, ''), 'a negative id is no page');
}

function test_picker_field_value_attributes()
{
	$kinds = pg_picker_kinds();
	$view = pg_picker_field_view($kinds['page'], '', null);
	$html = pg_picker_field_markup('page', 'upsell_page_id', '', $view, array('id' => 'pg-if-upsell-page', 'value_attributes' => array('data-on' => 'upsell-page', 'bad attr' => 'x', 'data-q' => '"<')));

	pg_assert_contains('<input type="hidden" name="upsell_page_id" value="" data-on="upsell-page" data-q="&quot;&lt;" data-pg-picker-value/>', $html, 'the hidden input carries the attributes, escaped');
	pg_assert_false(strpos($html, 'bad attr') !== false, 'an attribute name that is not a plain name is dropped');
	pg_assert_contains('id="pg-if-upsell-page"', $html, 'the text input carries the id');
}

function test_picker_catalog_page_kind()
{
	$kinds = pg_picker_kinds();

	pg_assert_same(array('catalog'), $kinds['page_catalog']['types'], 'the catalog page kind lists catalogs');
	pg_assert_same('pages', $kinds['page_catalog']['source'], 'it is a page kind');
	pg_assert_same('page_catalog', pg_picker_page_kind_for('catalog'), 'found by its type');
}

function test_picker_clear_is_public()
{
	$js = picker_page_fields_screens_test_source('assets/js/pg_picker.js');

	pg_assert_contains('root.pgPicker = { open: open, clear: clear, pure: pure };', $js, 'pgPicker.clear() is exported');
	pg_assert_contains("if (field && field.value.value !== '') {\n            field.set(null);", $js, 'it empties only a field that holds a value, firing change');
}

function test_page_selects_became_page_fields()
{
	$builder = picker_page_fields_screens_test_source('product_builder.php');

	foreach (array('recurring_profile_disabled_email_page_id', 'send_to_page', 'gift_card_email_page_id', 'email_page', 'add_comment_page_id') as $name) {
		pg_assert_contains("pg_picker_field('page', '" . $name . "', \$v('" . $name . "'))", $builder, 'product builder: ' . $name);
	}

	pg_assert_contains("' . pg_picker_script() . '\n</main>", $builder, 'product builder prints the picker script once');
	pg_assert_false(strpos($builder, 'select_page($v(') !== false, 'product builder: no page select is left');
	pg_assert_contains("pg_picker_field('page_custom_form', 'submit_form_custom_form_page_id', \$v('submit_form_custom_form_page_id'), array('value_attributes' => array('class' => 'collapse-if-selected', 'data-bs-target' => '#submit_form_custom_form_page_row', 'onchange' => 'product_submit_form_update_custom_form_fields()')))", $builder, 'product builder: the custom form field opens its fields and reads them on a pick');

	$offer = picker_page_fields_screens_test_source('includes/templates/edit_offer.php');

	pg_assert_contains("pg_picker_field('page', 'upsell_page_id', \$editor['state']['offer']['upsell']['page_id'], array('id' => 'pg-if-upsell-page', 'value_attributes' => array('data-on' => 'upsell-page')))", $offer, 'Edit Offer: the upsell page');
	pg_assert_false(strpos(picker_page_fields_screens_test_source('edit_offer.php'), 'select_page(') !== false, 'Edit Offer: no list of every page');
	pg_assert_false(strpos(picker_page_fields_screens_test_source('assets/js/offer_editor.js'), "$('#pg-if-upsell-page').value =") !== false, 'the offer editor no longer writes the text input');

	$menu = picker_page_fields_screens_test_source('includes/forms/menu_item_form.php');

	pg_assert_contains("pg_picker_field('page', 'link_page_id', \$liveform->get_field_value('link_page_id')) . pg_picker_script()", $menu, 'menu item: Page');

	$calendar = picker_page_fields_screens_test_source('includes/forms/calendar_event_form.php');

	pg_assert_contains("pg_picker_field('page', 'next_page_id', \$liveform->get_field_value('next_page_id')) . pg_picker_script()", $calendar, 'calendar event: Next Page');
	pg_assert_false(strpos($calendar, 'get_page_options(') !== false, 'calendar event: no list of every page');

	$short_link = picker_page_fields_screens_test_source('includes/forms/short_link_form.php');

	pg_assert_false(strpos($short_link, 'get_page_options(') !== false, 'short link form: no list of every page');
	pg_assert_contains("window.pgPicker.clear(document.getElementById(other));", $short_link, 'short link form: one catalog field empties the other');

	$window = picker_page_fields_screens_test_source('view_folders.php');

	pg_assert_contains("page_id: shortLinkEditing ? 0 : seedPage", $window, 'File Manager: a page opened from its panel is sent');
	pg_assert_contains('lists.page_fields = response.page_fields || {};', $window, 'File Manager: the page fields come from the server');
	pg_assert_contains("if (catalogPick.value !== '') { window.pgPicker.clear(detailPick); }", $window, 'File Manager: one catalog field empties the other');
}

function test_picker_view_and_form_page_kinds()
{
	$kinds = pg_picker_kinds();

	pg_assert_same('view', $kinds['page_view']['access'], 'the body page kind lists by view access');
	pg_assert_same('edit', $kinds['page']['access'], 'the page kind lists by edit access');
	pg_assert_true($kinds['page_form']['forms'], 'the form kind lists form pages');
	pg_assert_same(array('custom form'), $kinds['page_custom_form']['types'], 'the product builder\'s custom form kind');
	pg_assert_same(null, pg_picker_granted_folders(array('id' => 1, 'role' => 2), $kinds['page_view']), 'a manager may use every folder');

	$where = pg_picker_pages_where($kinds['page_form'], array(), null, null, "page.page_type = 'custom form'");

	pg_assert_contains("(page.page_type = 'custom form')", $where, 'a forms kind takes its condition');
	pg_assert_contains('(page.page_id IN (-1))', pg_picker_pages_where($kinds['page_form'], array(), null), 'a forms kind without its condition lists nothing');
	pg_assert_false(strpos(pg_picker_pages_where($kinds['page'], array(), null, null, 'x'), '(x)') !== false, 'any other kind ignores it');
}

function test_campaign_and_custom_form_fields_drive_their_sections()
{
	$js = picker_page_fields_screens_test_source('assets/js/backend.src.js');

	pg_assert_contains('$(document).on("change", "select.collapse-if-selected, input.collapse-if-selected", function () {', $js, 'a picker value opens its section on a pick');
	pg_assert_contains("\$scope.find('select.collapse-if-selected, input.collapse-if-selected')", $js, 'and on load');
	pg_assert_contains("\$('input[name=\"submit_form_custom_form_page_id\"]').val()", $js, 'the product builder reads the picked form');
	pg_assert_false(strpos(picker_page_fields_screens_test_source('edit_email_campaign_profile.php'), "'page_id' => (int) \$email_campaign_profile['page_id']") !== false, 'no keep argument for the body page');
}
