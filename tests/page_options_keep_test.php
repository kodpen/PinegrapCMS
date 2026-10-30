<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the page picker keeping the page a record already points at
 * (get_page_options() $keep_id, pg_page_option_status()): the menu item and
 * campaign profile edit screens pass their stored page, so a plain Save
 * keeps it even when its folder is archived.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function page_options_keep_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_page_option_status_keeps_the_stored_page()
{
	pg_assert_same('archived', pg_page_option_status(true, false, true, true), 'a kept page in an archived folder stays, marked archived');
	pg_assert_same('archived', pg_page_option_status(true, false, false, true), 'also when the bin is left out');
	pg_assert_same('bin', pg_page_option_status(true, true, false, true), 'a kept page in the bin keeps its bin mark');
	pg_assert_same('bin', pg_page_option_status(false, true, false, true), 'a kept page in a folder sent into the bin keeps its bin mark');
	pg_assert_same('show', pg_page_option_status(false, false, true, true), 'a kept page in a live folder is listed as it is');
}

function test_page_option_status_leaves_other_pages_out()
{
	pg_assert_same('skip', pg_page_option_status(true, false, true, false), 'another page in an archived folder is left out');
	pg_assert_same('skip', pg_page_option_status(true, true, true, false), 'another page in the bin is left out');
	pg_assert_same('skip', pg_page_option_status(false, true, false, false), 'a folder sent into the bin is left out when asked');
	pg_assert_same('show', pg_page_option_status(false, true, true, false), 'and listed when not');
	pg_assert_same('show', pg_page_option_status(false, false, false, false), 'a live page is listed');
}

function test_page_options_kept_page_passes_the_folder_filter()
{
	pg_assert_contains('if ($kept || (($access == \'edit\')', page_options_keep_test_source('includes/fn/content.php'), 'the kept page skips the role-3 folder filter');
}

function test_menu_item_edit_keeps_its_stored_page()
{
	$form = page_options_keep_test_source('includes/forms/menu_item_form.php');
	$edit = page_options_keep_test_source('edit_menu_item.php');
	pg_assert_contains("get_page_options('', '', 'edit', true, \$keep_page_id)", $form, 'the picker keeps the given page');
	pg_assert_contains("\$stored_link_page_id = (int) \$row['link_page_id'];", $edit, 'the keep id is the stored link, not the post');
	pg_assert_contains("</div>', \$stored_link_page_id)", $edit, 'edit passes the stored page');
	pg_assert_contains('pg_menu_item_form_card($liveform, $parent_id_options)', page_options_keep_test_source('add_menu_item.php'), 'add keeps nothing');
}

function test_campaign_profile_edit_keeps_its_stored_pages()
{
	$form = page_options_keep_test_source('includes/forms/email_campaign_profile_form.php');
	$edit = page_options_keep_test_source('edit_email_campaign_profile.php');
	pg_assert_contains("get_page_options('', 'custom form', 'edit', true, \$keep_pages['custom_form_page_id'] ?? 0)", $form, 'the custom form picker keeps the given page');
	pg_assert_contains("get_page_options('', '', 'view', true, \$keep_pages['page_id'] ?? 0)", $form, 'the body page picker keeps the given page');
	pg_assert_contains("'custom_form_page_id' => (\$email_campaign_profile['action'] == 'custom_form_submitted') ? (int) \$email_campaign_profile['action_item_id'] : 0", $edit, 'the stored custom form is kept');
	pg_assert_contains("'page_id' => (int) \$email_campaign_profile['page_id']", $edit, 'the stored body page is kept');
	pg_assert_contains('pg_email_campaign_profile_form_cards($liveform, $user)', page_options_keep_test_source('add_email_campaign_profile.php'), 'add keeps nothing');
}
