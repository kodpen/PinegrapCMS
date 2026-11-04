<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for panel buttons that are hard to undo: each asks first through
 * data-confirm-content, and an action-only form around one carries
 * disable_shortcut, so Ctrl+S neither shows its hint on the button nor
 * submits the form without the ask. A Delete that only explains why it
 * cannot delete is a plain button that stops there, so it does not save the
 * edit form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The <button …> tag that holds $marker in $file, and the <form …> tag that
// opens last before it.
function destructive_actions_ask_test_tags($file, $marker)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$at = strpos($source, $marker);

	if ($at === false) {
		return array('button' => '', 'form' => '');
	}

	$button_from = strrpos(substr($source, 0, $at), '<button');
	$button_to = strpos($source, '>', $at);
	$form_from = strrpos(substr($source, 0, $at), '<form');
	$form_to = ($form_from === false) ? false : strpos($source, '>', $form_from);

	return array(
		'button' => (($button_from === false) || ($button_to === false)) ? '' : substr($source, $button_from, $button_to - $button_from + 1),
		'form' => (($form_from === false) || ($form_to === false)) ? '' : substr($source, $form_from, $form_to - $form_from + 1),
	);
}

// [file, text inside the button tag, whether its form is action-only]
function destructive_actions_ask_test_buttons()
{
	return array(
		array('edit_erp_invoice.php', 'name="submit_cancel"', true),
		array('edit_erp_till.php', 'id="delete_button"', false),
		array('edit_user.php', "'Disconnect {var:1}'", true),
		array('edit_user.php', "'Turn off two-step verification'", true),
		array('edit_user.php', "'Sign out all sessions'", true),
		array('view_waf_log.php', 'name="submit_csp_clear"', true),
		array('marketplace_settings.php', 'id="mp_clear_failed"', true),
		array('marketplace_settings.php', 'id="mp_clear_stale"', true),
	);
}

function test_destructive_actions_ask_first()
{
	foreach (destructive_actions_ask_test_buttons() as $row) {
		$tags = destructive_actions_ask_test_tags($row[0], $row[1]);

		pg_assert_true($tags['button'] !== '', $row[0] . ' ' . $row[1] . ': the button is found');
		pg_assert_true(strpos($tags['button'], 'data-confirm-content="') !== false, $row[0] . ' ' . $row[1] . ': the button asks first');

		// The ask is a lang() text escaped once for the attribute.
		pg_assert_true(preg_match('/data-confirm-content="\' \. h\(/', $tags['button']) === 1, $row[0] . ' ' . $row[1] . ': the ask goes through h()');
	}
}

function test_destructive_actions_forms_keep_ctrl_s_off()
{
	foreach (destructive_actions_ask_test_buttons() as $row) {
		if (!$row[2]) {
			continue;
		}

		$tags = destructive_actions_ask_test_tags($row[0], $row[1]);

		pg_assert_true(preg_match('/class="[^"]*\bdisable_shortcut\b/', $tags['form']) === 1, $row[0] . ' ' . $row[1] . ': its form is disable_shortcut');
	}
}

// Without the Recycle Bin a group with child groups cannot be deleted: its
// Delete is a disabled plain button with the reason beside it (a submit
// there saved the form), not a native alert(). With the bin the subtree goes
// there together, so the normal Delete asks with its own text.
function test_destructive_actions_product_group_delete_does_not_save()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_product_group.php');
	$tags = destructive_actions_ask_test_tags('edit_product_group.php', 'aria-describedby="delete_product_group_hint"');

	pg_assert_true($tags['button'] !== '', 'the explaining Delete is found');
	pg_assert_true(strpos($tags['button'], 'type="button"') !== false, 'it is a plain button');
	pg_assert_true(strpos($tags['button'], ' disabled') !== false, 'it is disabled');
	pg_assert_true(strpos($source, 'alert(') === false, 'no native alert');
	pg_assert_contains("(\$child_product_group_exists == true) && !pg_catalog_bin_ready()", $source, 'refused only without the bin');
	pg_assert_contains("lang('This product group and the product groups in it will be moved to the Recycle Bin.", $source, 'the bin ask names the sub-groups');
}
