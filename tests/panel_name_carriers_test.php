<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that names sit only where they can be carried: a disabled pager
 * arrow (a role-less span) says its name through visually-hidden text, not
 * a prohibited aria-label, on the Paraşüt inbox, the visitor report and the
 * marketplace products screen and the picture picker; the picture picker
 * and Translations pagers carry the panel-language name 'Pagination'; and
 * the e-document provider radios of Site
 * Settings are named by the provider alone, their description, links and
 * status line sitting beside the label as the radio's description.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_name_carriers_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_panel_name_carriers_disabled_pager_arrow_has_no_aria_label()
{
	foreach (array('view_parasut_inbox.php', 'view_visitor_report.php', 'marketplace_products.php', 'editor_select_image.php') as $file) {
		$source = panel_name_carriers_test_source($file);

		pg_assert_true(!preg_match('/<span class="[^"]*(page-link|disabled)[^"]*"[^>]*aria-label=/', $source), $file . ': no aria-label on a disabled arrow span');
		pg_assert_true(strpos($source, '<span class="visually-hidden">\' . h($') !== false, $file . ': the disabled arrow says its name in visually-hidden text');
	}

	$report = panel_name_carriers_test_source('view_visitor_report.php');
	pg_assert_true(strpos($report, '<span aria-hidden="true">\' . $label . \'</span>') !== false, 'view_visitor_report.php: the glyph is hidden');
}

function test_panel_name_carriers_hand_built_pagers_use_panel_language_names()
{
	$picker = panel_name_carriers_test_source('editor_select_image.php');

	pg_assert_true(strpos($picker, 'data pagination') === false, 'editor_select_image.php: no English pager landmark name');
	pg_assert_true(strpos($picker, 'aria-label="Previous"') === false, 'editor_select_image.php: no English Previous name');
	pg_assert_true(strpos($picker, 'aria-label="Next"') === false, 'editor_select_image.php: no English Next name');
	pg_assert_contains('<nav class="navigation" aria-label="\' . h(lang(\'Pagination\')) . \'">', $picker);
	pg_assert_contains("lang('Previous page')", $picker);
	pg_assert_contains("lang('Next page')", $picker);
	pg_assert_contains('<span class="bi \' . $glyph . \'" aria-hidden="true"></span>', $picker);

	$translations = panel_name_carriers_test_source('translations.php');
	pg_assert_contains('<nav aria-label="\' . h(lang(\'Pagination\')) . \'"><ul class="pagination', $translations);
	pg_assert_true(strpos($translations, '<nav aria-label="\' . h(lang(\'Pages\'))') === false, 'translations.php: the text pager does not borrow the Pages card name');
}

function test_panel_name_carriers_edoc_provider_label_holds_the_name_alone()
{
	$source = panel_name_carriers_test_source('includes/settings/prep.php');

	preg_match_all('/<label class="" for="edoc_provider_.*?<\/label>/s', $source, $labels);
	pg_assert_same(2, count($labels[0]), 'the Off label and the provider label');

	foreach ($labels[0] as $label) {
		pg_assert_true(strpos($label, '<a ') === false, 'no link inside a provider label');
		pg_assert_true(strpos($label, '<div') === false, 'no block inside a provider label');
		pg_assert_true(strpos($label, '$erp_edoc_status') === false, 'no status line inside a provider label');
	}

	pg_assert_contains('id="edoc_provider_none" value="" aria-describedby="edoc_provider_none_help"', $source);
	pg_assert_contains('<div class="form-text mt-0" id="edoc_provider_none_help">', $source);
	pg_assert_contains('aria-describedby="edoc_provider_\' . h($erp_edoc_code) . \'_help"', $source);
	pg_assert_contains('<div class="form-text mt-0" id="edoc_provider_\' . h($erp_edoc_code) . \'_help">', $source);
}
