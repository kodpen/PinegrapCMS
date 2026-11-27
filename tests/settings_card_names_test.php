<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the names screen readers get in the Site Settings cards: radio
 * groups are headed by a legend, the Panel CSS and cron command code fields
 * and the default barcode type select are named, the e-document check time
 * follows the site's date and time format, and required markers are not
 * read as "star".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function settings_card_names_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/' . $file);
}

function settings_card_names_quoted($text)
{
	return preg_quote(str_replace("'", "\\'", $text), '/');
}

function test_settings_card_radio_groups_have_a_legend()
{
	$groups = array(
		'appearance.php' => array('Editor version'),
		'features.php' => array('Site Search Type', 'Setup'),
		'firewall.php' => array('Mode', 'Restriction', 'Content security policy'),
		'general.php' => array('Date Format', 'Time Format'),
		'languages.php' => array('What decides the language', 'Pages that are redirected'),
	);

	foreach ($groups as $file => $headings) {
		$src = settings_card_names_source($file);

		foreach ($headings as $heading) {
			$text = settings_card_names_quoted($heading);
			pg_assert_true((bool) preg_match('/<legend class="form-label pg-field-label">\'\s*\.\s*lang\(\'' . $text . '\'\)/', $src), $file . ': "' . $heading . '" heads its radios as a legend');
			pg_assert_false((bool) preg_match('/<label class="form-label">\'\s*\.\s*lang\(\'' . $text . '\'\)/', $src), $file . ': "' . $heading . '" is no longer a label that names nothing');
		}
	}

	$commerce = settings_card_names_source('commerce.php');
	// The gateways' choices are pg-segs; pg_commerce_settings_seg() draws
	// their legend (tests/settings_commerce_store_payments_test.php).
	pg_assert_same(3, substr_count($commerce, "pg_commerce_settings_seg(lang('Mode')"), 'commerce.php: all three Mode groups have a legend');
	pg_assert_same(2, substr_count($commerce, "pg_commerce_settings_seg(lang('Transaction type')"), 'commerce.php: both Transaction type groups have a legend');
	pg_assert_same(1, substr_count($commerce, "pg_commerce_settings_seg(lang('Recipient mode')"), 'commerce.php: Recipient mode has a legend');
	pg_assert_same(1, substr_count($commerce, "pg_commerce_settings_seg(lang('Enforcement')"), 'commerce.php: Enforcement has a legend');
	// The reference price rules are radio rows; pg_commerce_settings_choice()
	// draws their legend (tests/settings_commerce_giftcards_test.php).
	pg_assert_same(1, substr_count($commerce, "pg_commerce_settings_choice('ecommerce_reference_price_rule', lang('Price before the discount')"), 'commerce.php: Price before the discount has a legend');

	$general = settings_card_names_source('general.php');
	pg_assert_contains('<div class="pg-text-meta">\' . lang(\'Current Site Time\') . \': \'', $general, 'Current Site Time names no control, so it is a meta line, not a label');

	foreach (array('pgset-organization', 'pgset-server', 'pgset-software', 'pgset-datetime') as $id) {
		pg_assert_contains('<div id="' . $id . '" class="pg-set-card">', $general, 'general.php: ' . $id . ' keeps its card id inside its row');
	}

	$contact = settings_card_names_source('contact.php');

	foreach (array('pgset-chat', 'pgset-chat-site') as $id) {
		pg_assert_contains('<div id="' . $id . '" class="pg-set-card">', $contact, 'contact.php: ' . $id . ' keeps its card id inside its row');
	}

	pg_assert_contains('<label for="chat_operator_user_id" class="form-label">\' . lang(\'Who answers visitors\')', $contact, 'the operator\'s label points at its select');
	pg_assert_contains('<label for="chat_upload_folder_id" class="form-label">\' . lang(\'Folder for chat attachments\')', $contact, 'the folder\'s label points at its select');
}

function test_settings_card_social_setup_legend_heads_only_its_radios()
{
	$src = settings_card_names_source('features.php');
	$start = strpos($src, 'lang(\'Setup\') . \'</legend>');
	pg_assert_true($start !== false, 'the Setup heading is there');

	$block = substr($src, $start, (int) strpos($src, '</fieldset>', $start) - $start);
	pg_assert_contains('id="social_networking_type_advanced"', $block, 'both Setup radios sit in its fieldset');
	pg_assert_false(strpos($block, 'id="social_networking_services_row"') !== false, 'the services block that follows is outside the fieldset');
}

function test_settings_card_code_fields_are_named()
{
	$appearance = settings_card_names_source('appearance.php');
	pg_assert_contains('<label class="form-label" for="custom_css">', $appearance, 'the Panel CSS label points at its textarea');

	// The job commands are lines with a named Copy button; each job's switch
	// is named by its row's name (tests/settings_maintenance_cron_test.php).
	$general = settings_card_names_source('maintenance.php');
	pg_assert_contains('pg_copy_button($maintenance_cron_general, lang(\'Copy the command\'), \'cron_job_general_command\')', $general, 'the general job\'s command has a named Copy');
}

function test_settings_card_barcode_type_is_named()
{
	$src = settings_card_names_source('commerce.php');
	pg_assert_contains('<label class="form-label" for="barcode_default_type">', $src, 'the Default Barcode Type label points at its select');
}

function test_settings_card_edoc_check_time_follows_site_format()
{
	$src = settings_card_names_source('prep.php');
	pg_assert_false(strpos($src, "date('d.m.Y H:i', (int) \$erp_edoc_row['checked_at'])") !== false, 'the check time is not a fixed day.month format');
	pg_assert_contains("get_absolute_time(array('timestamp' => (int) \$erp_edoc_row['checked_at'], 'format' => 'plain_text'))", $src, 'the check time follows the site date and time format as plain text');
}

function test_settings_card_required_markers_are_not_read()
{
	// The e-document boxes are drawn on the provider's page of Connections.
	$edoc = (string) file_get_contents(PG_FUNCTIONS_DIR . '/connections.php');
	pg_assert_false(strpos($edoc, '<span class="fg-danger">*</span>') !== false, 'the e-document star is not a bare span');
	pg_assert_contains('<span class="pg-required-marker fg-danger ms-1" aria-hidden="true">*</span>', $edoc, 'the e-document star is hidden from screen readers');
	pg_assert_contains('aria-required="true"', $edoc, 'a required e-document field says so to screen readers');
	pg_assert_false(strpos($edoc, "' required") !== false, 'the e-document fields carry no required attribute that would refuse the whole save');

	$privacy = settings_card_names_source('privacy.php');
	pg_assert_false(strpos($privacy, '>*\' . lang(\'Remember a tracking code for\')') !== false, 'Remember a tracking code for has no leading star');
	pg_assert_contains('lang(\'Remember a tracking code for\') . \'<span class="pg-required-marker fg-danger ms-1" aria-hidden="true">*</span></label>', $privacy, 'Remember a tracking code for has the hidden trailing marker');
}

function test_settings_card_tsa_placeholder_is_neutral()
{
	$src = settings_card_names_source('features.php');
	pg_assert_false(strpos($src, 'sunucu.example') !== false, 'the TSA placeholder has no Turkish word');
	pg_assert_contains('placeholder="https://tsa.example/tsr"', $src, 'the TSA placeholder uses a neutral host');
}
