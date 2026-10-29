<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the names screen readers get in the Site Settings cards: radio
 * groups are headed by a legend, the Custom CSS and cron command code fields
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
		'appearance.php' => array('Editor Version'),
		'commerce.php' => array('Recipient Mode', 'Enforcement', 'Transaction Type', 'Mode', 'Price before the discount'),
		'features.php' => array('Site Search Type', 'Setup'),
		'firewall.php' => array('Mode', 'Restriction', 'Content Security Policy'),
		'general.php' => array('Date Format', 'Time Format'),
		'languages.php' => array('What decides the language', 'Pages that are redirected'),
	);

	foreach ($groups as $file => $headings) {
		$src = settings_card_names_source($file);

		foreach ($headings as $heading) {
			$text = settings_card_names_quoted($heading);
			pg_assert_true((bool) preg_match('/<legend class="form-label fs-md">\'\s*\.\s*lang\(\'' . $text . '\'\)/', $src), $file . ': "' . $heading . '" heads its radios as a legend');
			pg_assert_false((bool) preg_match('/<label class="form-label">\'\s*\.\s*lang\(\'' . $text . '\'\)/', $src), $file . ': "' . $heading . '" is no longer a label that names nothing');
		}
	}

	$commerce = settings_card_names_source('commerce.php');
	pg_assert_same(3, preg_match_all('/<legend class="form-label fs-md">\'\s*\.\s*lang\(\'Mode\'\)/', $commerce), 'commerce.php: all three Mode groups have a legend');
	pg_assert_same(2, preg_match_all('/<legend class="form-label fs-md">\'\s*\.\s*lang\(\'Transaction Type\'\)/', $commerce), 'commerce.php: both Transaction Type groups have a legend');

	$general = settings_card_names_source('general.php');
	pg_assert_contains('<div class="form-label">\' . lang(\'Current Site Time\') . \'</div>', $general, 'Current Site Time names no control, so it is not a label');
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
	pg_assert_contains('<label class="form-label" for="custom_css">', $appearance, 'the Custom CSS label points at its textarea');

	$general = settings_card_names_source('general.php');
	pg_assert_contains('<textarea id="cron_job_general_dispatch" aria-label="\' . h(lang(\'General job command\')) . \'">', $general, 'the dispatch command box has a translated name');

	$jobs = array('general', 'webhook', 'push', 'exchange_rates', 'email_campaign', 'search_index', 'seo_score', 'seo_analyze', 'requrring_payment', 'membership', 'auto_backup');

	foreach ($jobs as $job) {
		$id = 'cron_job_' . $job;
		pg_assert_contains('<h5 id="' . $id . '_title">', $general, $id . ': its heading has an id');
		pg_assert_contains('<textarea id="' . $id . '" aria-labelledby="' . $id . '_title">', $general, $id . ': its textarea is named by the heading');
	}
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
	$prep = settings_card_names_source('prep.php');
	pg_assert_false(strpos($prep, '<span class="fg-danger">*</span>') !== false, 'the e-document star is not a bare span');
	pg_assert_contains('<span class="pg-required-marker fg-danger ms-1" aria-hidden="true">*</span>', $prep, 'the e-document star is hidden from screen readers');
	pg_assert_contains('aria-required="true"', $prep, 'a required e-document field says so to screen readers');
	pg_assert_false(strpos($prep, "\$erp_edoc_required ? ' required") !== false, 'the e-document fields carry no required attribute that would refuse the whole save');

	$seo = settings_card_names_source('seo.php');
	pg_assert_false(strpos($seo, '>*\' . lang(\'Tracking Code Duration\')') !== false, 'Tracking Code Duration has no leading star');
	pg_assert_contains('lang(\'Tracking Code Duration\') . \'<span class="pg-required-marker fg-danger ms-1" aria-hidden="true">*</span></label>', $seo, 'Tracking Code Duration has the hidden trailing marker');
}

function test_settings_card_tsa_placeholder_is_neutral()
{
	$src = settings_card_names_source('features.php');
	pg_assert_false(strpos($src, 'sunucu.example') !== false, 'the TSA placeholder has no Turkish word');
	pg_assert_contains('placeholder="https://tsa.example/tsr"', $src, 'the TSA placeholder uses a neutral host');
}
