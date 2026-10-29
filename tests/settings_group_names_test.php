<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for grouped controls that a screen reader must hear with their
 * heading: the e-document provider radios, the document currencies, the
 * overdue reminder channels, the country restriction exemptions, each target
 * language block and every SMS text in the Site Settings cards, and the
 * permission rows' Select buttons and Select All switches on the user screens,
 * Organize Contacts and the calendar event form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/user_permissions.php');

function settings_group_names_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_settings_group_names_headings_name_their_groups()
{
	$groups = array(
		'includes/settings/commerce.php' => array(
			'erp_edoc_provider_title' => 'e-Document Provider',
			'erp_fx_currencies_title' => 'Currencies allowed on documents',
			'erp_overdue_notify_title' => 'Overdue receivable reminders',
		),
		'includes/settings/firewall.php' => array(
			'waf_country_exempt_title' => 'Always let in',
		),
		'includes/settings/languages.php' => array(
			'translation_targets_title' => 'Target languages',
		),
	);

	foreach ($groups as $file => $headings) {
		$src = settings_group_names_source($file);

		foreach ($headings as $id => $heading) {
			pg_assert_contains('<div class="col-12" role="group" aria-labelledby="' . $id . '">', $src, $file . ': the "' . $heading . '" cell is a group');
			pg_assert_contains('<div class="form-label" id="' . $id . '">\' . lang(\'' . $heading . '\')', $src, $file . ': "' . $heading . '" is the heading that names it');
		}
	}

	pg_assert_false(strpos(settings_group_names_source('includes/settings/firewall.php'), '<label class="form-label">\' . lang(\'Always let in\')') !== false, 'Always let in is no longer a label that names nothing');
	pg_assert_false(strpos(settings_group_names_source('includes/settings/languages.php'), '<label class="form-label">\' . lang(\'Target languages\')') !== false, 'Target languages is no longer a label that names nothing');
}

function test_settings_group_names_language_block_is_named_by_its_language()
{
	$src = settings_group_names_source('includes/settings/languages.php');

	pg_assert_contains('<div class="pg-tr-language border rounded-4 p-5" data-code="\' . h($translation_code) . \'" role="group" aria-labelledby="tr_name_\' . h($translation_code) . \'">', $src, 'each language block is a group');
	pg_assert_contains('<strong class="me-auto" id="tr_name_\' . h($translation_code) . \'">', $src, 'named by the language name');
}

function test_settings_group_names_sms_text_is_named_by_its_event()
{
	$src = settings_group_names_source('includes/fn/sms_messages.php');

	pg_assert_contains('<label class="" for="\' . h($name) . \'on" id="\' . h($name) . \'label">', $src, 'the event switch label has an id');
	pg_assert_contains('id="\' . h($name) . \'text" aria-labelledby="\' . h($name) . \'label"', $src, 'the message box is named by its event');
}

function test_settings_group_names_permission_select_names_its_row()
{
	$html = pg_user_permission_row(array(
		'id' => 'content',
		'gate_field' => '',
		'gate_value' => '',
		'gate_on' => true,
		'counters' => array(),
		'panel' => '<p>list</p>',
		'title' => 'Content',
		'description' => 'Pages',
		'color' => 'red',
		'icon' => 'bi-file',
	));

	pg_assert_contains('id="pgperm_open_content" aria-labelledby="pgperm_open_content pgperm_row_title_content"', $html, 'the Select button reads its own word and the row title');
	pg_assert_contains('<b id="pgperm_row_title_content">Content</b>', $html, 'the row title has an id');
}

function test_settings_group_names_select_all_names_its_list()
{
	$html = pg_user_permission_list('menus', 'Menus', '', 'multiselect-checkbox-checker-3');

	pg_assert_contains('<div class="pg-perm-block-title" id="multiselect-checkbox-checker-3_title">Menus</div>', $html, 'the block title has an id');
	pg_assert_contains('id="multiselect-checkbox-checker-3" class="multiselect-checkbox-checker" type="checkbox" aria-labelledby="multiselect-checkbox-checker-3_label multiselect-checkbox-checker-3_title"', $html, 'Select All reads its list');
	pg_assert_contains('<label for="multiselect-checkbox-checker-3" class="" id="multiselect-checkbox-checker-3_label">', $html, 'the Select All label has an id');

	$bare = pg_user_permission_list('menus', '', '', 'checker-x');
	pg_assert_false(strpos($bare, 'aria-labelledby') !== false, 'a list with no heading keeps its plain label');
}

function test_settings_group_names_organize_contacts_select_all_names_its_list()
{
	$source = settings_group_names_source('organize_contacts.php');

	foreach (array('0' => 'Add to Contact Groups', '1' => 'Remove from Contact Groups') as $n => $heading) {
		$id = 'multiselect-checkbox-checker-' . $n;

		pg_assert_contains('id="' . $id . '_title">\' . lang(array(\'string\'=>\'' . $heading . '\') ) . \'</div>', $source, $heading . ' heading has an id');
		pg_assert_contains('<input id="' . $id . '" class="multiselect-checkbox-checker" type="checkbox" aria-labelledby="' . $id . '_label ' . $id . '_title">', $source, $heading . ' Select All reads its list');
		pg_assert_contains('<label for="' . $id . '" class="" id="' . $id . '_label">', $source, $heading . ' Select All label has an id');
	}
}
