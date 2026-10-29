<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the e-mail campaign lists (My Campaigns, My Campaign
 * History, My Campaign Profiles): named row checkboxes, the progress percent
 * in the panel language's order, a readable Enabled mark, icon ligatures kept
 * out of link and button names, translated landmark names and a full-width
 * profiles toolbar.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function email_campaign_list_names_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function email_campaign_list_names_screens()
{
	return array('view_email_campaigns.php', 'view_email_campaign_history.php', 'view_email_campaign_profiles.php');
}

function test_email_campaign_list_row_checkboxes_named()
{
	foreach (array('view_email_campaigns.php', 'view_email_campaign_history.php') as $path) {
		$source = email_campaign_list_names_source($path);
		pg_assert_contains("name=\"email_campaigns[]\" value=\"' . \$email_campaign['id'] . '\" aria-label=\"' . h(lang(array('string'=>'Select {var:1}','vars'=>array(\$email_campaign['subject'])))) . '\" />", $source, $path);
		pg_assert_false(strpos($source, 'class="checkbox"') !== false, $path . ': duplicate class attribute');
	}
	$source = email_campaign_list_names_source('view_email_campaign_profiles.php');
	pg_assert_contains("name=\"fake_checkbox[]\" value=\"1\" aria-label=\"' . h(lang(array('string'=>'Select {var:1}','vars'=>array(\$email_campaign_profile['name'])))) . '\">", $source);
}

function test_email_campaign_list_progress_percent_translated()
{
	foreach (array('view_email_campaigns.php', 'view_email_campaign_history.php') as $path) {
		$source = email_campaign_list_names_source($path);
		pg_assert_false(strpos($source, "\$progress_percentage . '%") !== false, $path . ': percent sign glued in English order');
		pg_assert_contains("lang(array('string'=>'{var:1}%','vars'=>array(\$progress_percentage)))", $source, $path);
	}
}

function test_email_campaign_profiles_enabled_mark_named()
{
	$source = email_campaign_list_names_source('view_email_campaign_profiles.php');
	pg_assert_contains("<span class=\"material-icons\" aria-hidden=\"true\">task_alt</span><span class=\"visually-hidden\">' . lang('Yes') . '</span>", $source);
}

function test_email_campaign_list_ligatures_hidden_and_landmarks_translated()
{
	foreach (email_campaign_list_names_screens() as $path) {
		$source = email_campaign_list_names_source($path);
		pg_assert_false(strpos($source, 'aria-label="Button Bar"') !== false, $path . ': literal landmark name');
		pg_assert_false(strpos($source, 'aria-label="data edit buttons') !== false, $path . ': literal landmark name');
		pg_assert_contains("aria-label=\"' . h(lang('Button Bar')) . '\"", $source, $path);
		// Every live ligature span carries aria-hidden; commented-out markup is ignored.
		$live = preg_replace('/<!--.*?-->/s', '', $source);
		pg_assert_false((bool) preg_match('/<span class="material-icons[^"]*">/', $live), $path . ': ligature span read aloud');
	}
	foreach (array('view_email_campaigns.php', 'view_email_campaign_history.php') as $path) {
		pg_assert_contains("aria-label=\"' . h(lang('data edit buttons')) . '\"", email_campaign_list_names_source($path), $path);
	}
}

function test_email_campaign_profiles_toolbar_full_width()
{
	$source = email_campaign_list_names_source('view_email_campaign_profiles.php');
	pg_assert_false(strpos($source, 'col-12 sm:col-12 md:col-6 xl:col-9') !== false, 'toolbar column stops at half width');
	pg_assert_contains('<div class="col-12 text-center md:text-start">', $source);
}
