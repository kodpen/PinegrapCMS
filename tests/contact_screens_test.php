<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the contact screens: Organize Contacts reads the ticked
 * group boxes whatever their count, the Description note is not capped,
 * order totals are formatted money, a contact group that holds contacts
 * offers no submitting Delete, and the controls carry translated names.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function contact_screens_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_contact_screens_organize_reads_checked_boxes()
{
	// The Organize Selected dialog of the contacts list.
	$source = contact_screens_source('view_contacts.php');

	// With one group the form's named member is the checkbox itself and has
	// no length, so a loop over .length drops the selection.
	pg_assert_true(strpos($source, 'add_to_contact_groups.length') === false, 'organize: no loop over the add boxes by .length');
	pg_assert_true(strpos($source, 'remove_from_contact_groups.length') === false, 'organize: no loop over the remove boxes by .length');
	pg_assert_true(strpos($source, 'dialog.querySelectorAll(\\\'input[name="\\\' + name + \\\'"]:checked\\\')') !== false, 'organize: ticked boxes read by selector');
	pg_assert_true(strpos($source, 'ticked(\\\'add_to_contact_groups\\\')') !== false, 'organize: ticked add boxes read');
	pg_assert_true(strpos($source, 'ticked(\\\'remove_from_contact_groups\\\')') !== false, 'organize: ticked remove boxes read');
}

function test_contact_screens_description_not_capped()
{
	$source = contact_screens_source('includes/forms/contact_form.php');
	pg_assert_true((bool) preg_match('/<textarea[^>]*name="description"[^>]*>/', $source, $match), 'contact form: description textarea found');
	pg_assert_true(strpos($match[0], 'maxlength') === false, 'contact form: description has no maxlength');
}

function test_contact_screens_order_totals_formatted()
{
	$source = contact_screens_source('view_orders_for_contact.php');
	pg_assert_true(strpos($source, '%01.2lf') === false, 'orders for contact: no bare sprintf total');
	pg_assert_true(strpos($source, "pg_format_money(\$row['total'] / 100, BASE_CURRENCY_SYMBOL)") !== false, 'orders for contact: total through pg_format_money');
}

function test_contact_screens_group_delete_blocked_without_submit()
{
	$source = contact_screens_source('edit_contact_group.php');
	pg_assert_true(strpos($source, 'onclick="alert(') === false, 'contact group: no alert() on Delete');
	pg_assert_same(1, substr_count($source, 'name="submit_delete"'), 'contact group: only the allowed Delete submits');
}

function test_contact_screens_edit_contact_names()
{
	$source = contact_screens_source('edit_contact.php');

	pg_assert_true(strpos($source, '<label for="parasut_contact_id"') !== false, 'edit contact: Parasut label points at its field');
	pg_assert_true((bool) preg_match('/<input[^>]*name="parasut_contact_id" id="parasut_contact_id"/', $source), 'edit contact: Parasut field has its id');
	pg_assert_true((bool) preg_match('/<a [^>]*href="mailto:[^>]*aria-label="\' \. h\(lang\(\'Send Email\'\)\) \. \'"/', $source), 'edit contact: mailto link named Send Email');
	pg_assert_true((bool) preg_match('/<span class="material-icons" aria-hidden="true">email<\/span>/', $source), 'edit contact: mailto ligature hidden');

	pg_assert_true((bool) preg_match_all('/<button[^>]*opacity-0[^>]*>/', $source, $spacers), 'edit contact: spacer button found');
	foreach ($spacers[0] as $tag) {
		pg_assert_true(strpos($tag, 'tabindex="-1"') !== false && strpos($tag, 'aria-hidden="true"') !== false, 'edit contact: spacer out of reach: ' . $tag);
	}

	pg_assert_true((bool) preg_match_all('/<img [^>]*>/', $source, $images), 'edit contact: pictures found');
	foreach ($images[0] as $tag) {
		pg_assert_true(strpos($tag, ' alt="') !== false, 'edit contact: picture has alt: ' . $tag);
	}
}

function test_contact_screens_radio_groups_named()
{
	$group_form = contact_screens_source('includes/forms/contact_group_form.php');
	pg_assert_true(strpos($group_form, 'role="radiogroup" aria-labelledby="email_subscription_type_label"') !== false, 'contact group: subscription type radio group named');
	pg_assert_true(strpos($group_form, 'id="email_subscription_type_label"') !== false, 'contact group: subscription type label id');

	$import = contact_screens_source('import_contacts.php');
	pg_assert_true(strpos($import, 'for=""') === false, 'import: no empty label for');
	pg_assert_true(strpos($import, 'role="radiogroup" aria-labelledby="import_mode_label"') !== false, 'import: mode radio group named');
	pg_assert_true(strpos($import, 'id="import_mode_label"') !== false, 'import: mode label id');
}

function test_contact_screens_subscription_mark_readable()
{
	$source = contact_screens_source('view_contact_groups.php');
	pg_assert_contains("'subscription' => pg_list_table_check_cell(!empty(\$contact_group['email_subscription']))", $source, 'contact groups: subscription mark reads Yes');
}

function test_contact_screens_landmarks_translated()
{
	$paths = array(
		'edit_contact.php',
		'view_contact_groups.php',
		'view_orders_for_contact.php',
		'add_contact.php',
		'add_contact_group.php',
		'edit_contact_group.php',
		'import_contacts.php',
		'organize_contacts.php',
	);
	foreach ($paths as $path) {
		$source = contact_screens_source($path);
		pg_assert_true(strpos($source, 'aria-label="Button Bar"') === false, $path . ': Button Bar translated');
		pg_assert_true(strpos($source, 'aria-label="data edit buttons') === false, $path . ': data edit buttons translated');
	}
}

// The Material Icons ligature beside a label is hidden from screen readers,
// so Save is not read as "save Kaydet"; a plain span carries no href.
function test_contact_screens_icon_ligatures_hidden()
{
	foreach (array('edit_contact.php', 'edit_contact_group.php', 'includes/forms/contact_form.php') as $file) {
		$source = contact_screens_source($file);

		pg_assert_true((bool) preg_match_all('/<span class="material-icons[^"]*"[^>]*>/', $source, $spans), $file . ': ligatures found');
		foreach ($spans[0] as $tag) {
			pg_assert_true(strpos($tag, 'aria-hidden="true"') !== false, $file . ': ligature hidden: ' . $tag);
		}

		pg_assert_false((bool) preg_match('/<span [^>]*href=/', $source), $file . ': no href on a span');
	}
}

// The group list names the By hand / By rule column Membership and puts
// Members over the member count, as the cells come.
function test_contact_screens_group_list_headings_match_cells()
{
	$source = contact_screens_source('view_contact_groups.php');

	// The driver puts every row's cells in the columns' order by key, so the
	// heading over the count is Members and the badge's is Membership.
	pg_assert_true((bool) preg_match("/'kind' => array\\('heading' => lang\\('Membership'\\)[^\\n]*\\n\\s*'members' => array\\('heading' => lang\\('Members'\\)/", $source), 'headings: Membership, then Members');
	pg_assert_contains("'kind' => pg_list_cell(\$output_kind)", $source, 'Membership column holds the kind badge');
	pg_assert_contains("'members' => pg_list_cell(pg_format_number(\$members, 0)", $source, 'Members column holds the count');
	pg_assert_false(strpos($source, "lang('Contacts')") !== false, 'no Contacts heading over the count');
}

// A save opened without a send to (the hidden field posts it empty) ends on
// the contacts list and announces itself there.
function test_contact_screens_edit_contact_saved_notice_without_send_to()
{
	$source = contact_screens_source('edit_contact.php');

	pg_assert_contains("\$send_to = (isset(\$_REQUEST['send_to']) && is_string(\$_REQUEST['send_to'])) ? \$_REQUEST['send_to'] : '';", $source, 'send to read as a string');
	pg_assert_contains("if ((\$send_to === '') || (mb_strpos(\$send_to, 'view_contacts.php') !== false)) {", $source, 'empty send to counts as the list');
	pg_assert_false(strpos($source, "(isset(\$_REQUEST['send_to']) == FALSE) ||") !== false, 'the empty field no longer hides the notice');
}
