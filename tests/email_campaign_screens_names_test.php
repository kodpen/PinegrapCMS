<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the names screen readers get on the e-mail campaign profile
 * and send screens: the schedule fields, the body preview frame, the radio
 * group legends, translated landmarks and ligatures kept out of button
 * names; the send popup's progress bar prints through the panel language.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function email_campaign_screens_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_email_campaign_preview_frames_have_a_title()
{
	$file = 'includes/forms/email_campaign_profile_form.php';
	$src = email_campaign_screens_source($file);
	pg_assert_contains('<iframe id="body_preview_iframe" title="' . "' . h(lang('Body Preview')) . '" . '"', $src, $file . ' titles the preview frame');
}

function test_email_campaign_radio_headings_are_legends()
{
	$files = array(
		'includes/forms/email_campaign_profile_form.php' => array('Format', 'Purpose'),
	);

	foreach ($files as $file => $headings) {
		$src = email_campaign_screens_source($file);
		foreach ($headings as $heading) {
			pg_assert_false(strpos($src, '<label class="form-label">' . "' . lang('" . $heading . "')") !== false, $file . ' has no unbound ' . $heading . ' label');
			pg_assert_contains('<legend class="form-label fs-md">' . "' . lang('" . $heading . "') . '" . '</legend>', $src, $file . ' heads ' . $heading . ' with a legend');
		}
	}
}

function test_email_campaign_progress_prints_in_the_panel_language()
{
	$send = email_campaign_screens_source('send_email_campaign.php');
	pg_assert_false(strpos($send, "'%\">' . \$percent_complete . '%</div>") !== false, 'the progress bar does not glue % in English order');
	pg_assert_contains("lang(array('string' => '{var:1}%', 'vars' => array(\$percent_complete)))", $send, 'the progress bar prints through {var:1}%');
}

function test_email_campaign_schedule_fields_are_named()
{
	$src = email_campaign_screens_source('includes/forms/email_campaign_profile_form.php');

	foreach (array('schedule_length' => 'Send e-mail', 'schedule_unit' => 'Day(s)/Hour(s)', 'schedule_period' => 'Before/After', 'schedule_base' => 'From Action/From Calendar event start time') as $field => $name) {
		pg_assert_true((bool) preg_match("/'name'=>'" . $field . "'[^\\n]*'aria-label'=>lang\\('" . preg_quote($name, '/') . "'\\)/", $src), $field . ' is named ' . $name);
	}
	pg_assert_false(strpos($src, '<label class="form-label">' . "' . lang('Send e-mail')") !== false, 'no unbound Send e-mail label');
}

function test_email_campaign_landmarks_and_ligatures()
{
	$files = array('edit_email_campaign_profile.php', 'add_email_campaign_profile.php', 'send_email_campaign.php');
	foreach ($files as $file) {
		$src = email_campaign_screens_source($file);
		pg_assert_false(strpos($src, 'aria-label="data edit buttons "') !== false, $file . ' translates the save bar landmark');
		pg_assert_false(strpos($src, 'aria-label="Button Bar"') !== false, $file . ' translates the toolbar landmark');
		pg_assert_false((bool) preg_match('/<span class="material-icons[^"]*">(send|save|delete|close|control_point_duplicate)</', $src), $file . ' keeps ligatures out of names');
	}
}
