<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * General › Organization: one card holds the organization's name, address,
 * logo, support e-mail, tax number and tax office. Each field keeps its post
 * name and column, the General save writes it with the same rule as before,
 * and the card it left draws and writes it no more. The Affiliate Program
 * keeps its own address under its own label.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function settings_organization_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// One category's entry in pg_settings_categories(), cut out of the source.
function settings_organization_test_category($key)
{
	$source = settings_organization_test_source('includes/settings/registry.php');
	$start = strpos($source, "\n        '" . $key . "' => array(");

	if ($start === false) {
		return '';
	}

	return substr($source, $start, strpos($source, "\n        ),\n", $start) - $start);
}

// One card of a category file: from its id to the next card.
function settings_organization_test_card($file, $id)
{
	$source = settings_organization_test_source($file);
	$start = strpos($source, '<div id="' . $id . '" class="pg-set-card');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, '$pg_settings_cards[]', $start);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

// Where each field was drawn and saved before the move.
function settings_organization_test_moved()
{
	return array(
		'organization_name' => array('includes/settings/contact.php', 'includes/settings/contact.save.php'),
		'organization_address_1' => array('includes/settings/contact.php', 'includes/settings/contact.save.php'),
		'organization_address_2' => array('includes/settings/contact.php', 'includes/settings/contact.save.php'),
		'organization_city' => array('includes/settings/contact.php', 'includes/settings/contact.save.php'),
		'organization_state' => array('includes/settings/contact.php', 'includes/settings/contact.save.php'),
		'organization_zip_code' => array('includes/settings/contact.php', 'includes/settings/contact.save.php'),
		'organization_country' => array('includes/settings/contact.php', 'includes/settings/contact.save.php'),
		'organization_logo' => array('includes/settings/prep.php', 'includes/settings/seo.save.php'),
		'erp_seller_vkn' => array('includes/settings/accounting.php', 'includes/settings/accounting.save.php'),
		'erp_seller_tax_office' => array('includes/settings/accounting.php', 'includes/settings/accounting.save.php'),
	);
}

function test_settings_organization_card_is_first_in_general()
{
	$entry = settings_organization_test_category('general');
	pg_assert_contains("'pgset-organization' => lang('Organization')", $entry, 'the card is listed');
	pg_assert_true(strpos($entry, "'pgset-organization'") < strpos($entry, "'pgset-server'"), 'before Server & Domain');
	pg_assert_contains("'pgset-organization' => pg_settings_keywords(", $entry, 'with its keywords');

	$source = settings_organization_test_source('includes/settings/general.php');
	$at = strpos($source, '<div id="pgset-organization" class="pg-set-card">');
	pg_assert_true(($at !== false) && ($at < strpos($source, '<div id="pgset-server" class="pg-set-card">')), 'drawn first');

	// Every other category key is still there.
	foreach (array('general', 'seo', 'contact', 'commerce', 'accounting') as $key) {
		pg_assert_true(settings_organization_test_category($key) !== '', $key . ' is still a category');
	}
}

function test_settings_organization_card_draws_every_field()
{
	$card = settings_organization_test_card('includes/settings/general.php', 'pgset-organization');

	foreach (array_keys(settings_organization_test_moved()) as $field) {
		pg_assert_contains('name="' . $field . '"', $card . settings_organization_test_source('includes/settings/prep.php'), $field . ' keeps its post name');
	}

	foreach (array('organization_name', 'organization_address_1', 'organization_country', 'erp_seller_vkn', 'erp_seller_tax_office') as $field) {
		pg_assert_contains('name="' . $field . '"', $card, $field . ' drawn on the card');
	}

	pg_assert_contains('$output_organization_logo_picker', $card, 'the logo picker printed on the card');
	pg_assert_contains('name="email_address" id="setting_email_address"', $card, 'the Support E-mail Address on the card');
	pg_assert_contains("lang('Support E-mail Address')", $card, 'under its name');
	pg_assert_false(strpos(settings_organization_test_card('includes/settings/general.php', 'pgset-server'), 'email_address') !== false, 'Server & Domain no longer draws it');
}

function test_settings_organization_fields_left_their_old_places()
{
	foreach (settings_organization_test_moved() as $field => $places) {
		list($drawn, $saved) = $places;

		if ($drawn === 'includes/settings/prep.php') {
			$prep = settings_organization_test_source($drawn);
			$start = strpos($prep, '$output_share_image_pickers = \'');
			$share = substr($prep, $start, strpos($prep, "';", $start) - $start);
			pg_assert_false(strpos($share, 'organization_logo') !== false, 'the SEO share pickers no longer carry the logo');
			pg_assert_contains('$output_organization_logo_picker = \'', $prep, 'the logo has a picker of its own');
		} else {
			pg_assert_false(strpos(settings_organization_test_source($drawn), 'name="' . $field . '"') !== false, $field . ' no longer drawn by ' . $drawn);
		}

		pg_assert_false(strpos(settings_organization_test_source($saved), $field . ' = ') !== false, $field . ' no longer written by ' . $saved);
		pg_assert_false(strpos(settings_organization_test_source($saved), "post_value('" . $field . "')") !== false, $field . ' no longer read by ' . $saved);
	}

	pg_assert_false(strpos(settings_organization_test_card('includes/settings/seo.php', 'pgset-seo'), 'organization_logo') !== false, 'the SEO card draws no logo');
}

function test_settings_organization_general_save_writes_the_same_columns()
{
	$save = settings_organization_test_source('includes/settings/general.save.php');
	$update = strpos($save, 'db("UPDATE config');

	foreach (array('organization_name', 'organization_address_1', 'organization_address_2', 'organization_city', 'organization_state', 'organization_zip_code', 'organization_country') as $column) {
		pg_assert_contains($column . " = '\" . escape(post_value('" . $column . "')) . \"',", $save, $column . ' written as Campaigns wrote it');
	}

	pg_assert_contains("organization_logo = '\" . escape(\$organization_logo_input) . \"',", $save, 'the logo written as SEO wrote it, after the public image check');
	pg_assert_contains("waf_table_has_column('config', 'og_default_image')", $save, 'only where the SEO save wrote it');

	// The seller's tax number keeps its rule: digits in Turkey, the column's width elsewhere.
	pg_assert_contains("COLUMN_NAME = 'erp_seller_vkn'", $save, 'the column width is read');
	pg_assert_contains("substr(preg_replace('/\\D/', '', \$erp_seller_vkn_value), 0, 11)", $save, 'digits only in Turkey');
	pg_assert_contains("mb_substr(\$erp_seller_vkn_value, 0, \$erp_seller_vkn_width)", $save, 'cut to the column elsewhere');
	pg_assert_contains("erp_seller_vkn = '\" . escape(\$erp_seller_vkn_value) . \"',", $save, 'the tax number written');
	pg_assert_contains("erp_seller_tax_office = '\" . escape(trim(post_value('erp_seller_tax_office'))) . \"',", $save, 'the tax office written');

	foreach (array('organization_name = ', '$sql_organization_logo . ', 'erp_seller_vkn = ', 'email_address = ') as $column) {
		pg_assert_true(strpos($save, $column) > $update, $column . 'in the one UPDATE');
	}
}

function test_settings_organization_affiliate_address_named_apart()
{
	$card = settings_organization_test_card('includes/settings/commerce.php', 'pgset-affiliate');
	pg_assert_contains('<label for="affiliate_email_address" class="form-label">\' . lang(\'Affiliate Program E-mail Address\') . \'</label>', $card, 'the affiliate field has its own name');
	pg_assert_contains('name="affiliate_email_address"', $card, 'same post name');
	pg_assert_false(strpos($card, 'Support E-mail Address') !== false, 'no second Support E-mail Address');
	pg_assert_contains("affiliate_email_address = '\" . escape(post_value('affiliate_email_address')) . \"',", settings_organization_test_source('includes/settings/commerce.save.php'), 'same column and save');

	$tr = json_decode(settings_organization_test_source('includes/local/tr/a.json'), true);
	pg_assert_same('Ortaklık Programı E-posta Adresi', $tr['Affiliate Program E-mail Address'] ?? null, 'translated');
}

function test_settings_organization_erp_settings_names_organization()
{
	$screen = settings_organization_test_source('erp_settings.php');
	pg_assert_false(strpos($screen, 'are on the ERP card of the accounting settings') !== false, 'the tax number is no longer said to be on the ERP card');
	pg_assert_false(strpos($screen, "lang('Contact')") !== false, 'no Contact place');
	pg_assert_contains("lang('General') . ' &rsaquo; ' . lang('Organization')", $screen, 'names it for those without Site Settings');
}

function test_settings_organization_legacy_anchors()
{
	$source = settings_organization_test_source('includes/settings/registry.php');
	pg_assert_contains("'pgset-campaigns'  => array('contact',    'pgset-campaigns'),", $source, 'Campaigns stays where it is');
	pg_assert_contains("'pgset-organization' => array('general',  'pgset-organization'),", $source, 'the card is reached by its anchor from any page');
}
