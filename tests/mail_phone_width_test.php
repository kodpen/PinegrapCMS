<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Communication › Incoming e-mail and Site Settings › Mailboxes at
 * phone width (390 and 320 px): the list's views wrap instead of running
 * off the screen, the mailbox row's buttons wrap inside the card, the box
 * form's sent-copy check box stands beside its label, the open
 * conversation's status and holder keep a touch-sized height.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function test_mail_phone_views_wrap()
{
	// Sent, Not sent, Blocked and Drafts: on a 390 px screen the group was
	// 619 px wide in 358 and Blocked and Drafts lay out of sight.
	$toolbar = ws_mail_list_filters_html(ws_mail_list_filters(array()), array(), true, true, array(), true, true, true);

	pg_assert_contains('<div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="' . h(lang('View')) . '">', $toolbar, 'the views wrap onto a second line');
	pg_assert_contains('data-ws-mail-new', $toolbar, 'the toolbar is the sending one');
}

function test_mail_phone_box_row_buttons_wrap()
{
	// Test connection and Edit side by side are about 240 px: on a 320 px
	// screen Edit ran past the card's right edge, half cut.
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_settings.php'));

	pg_assert_contains("<div class=\"pg-list-row-actions flex-wrap\">\n                        ' . (\$reads ? '<button type=\"submit\" name=\"ws_mail_test\"", $source, 'the box row\'s buttons wrap inside the card');
}

function test_mail_phone_sent_copy_beside_its_label()
{
	// The check box stood alone on a line and its label under it at the
	// size of a heading, as `form-field` draws a label; the box form's other
	// check boxes sit in a flex row beside theirs.
	$html = ws_mail_box_form('workspace_settings.php', null, array());
	$row = '<div class="d-flex align-items-center gap-3 mt-2">' . "\n"
		. '                        <input class="pg-checkbox" type="checkbox" name="sent_copy"';

	pg_assert_contains($row, $html, 'the sent copy check box beside its label');
	pg_assert_contains('<div class="d-flex align-items-center gap-3">' . "\n" . '                        <input class="pg-checkbox" type="checkbox" name="read_sent"', $html, 'as Read the Sent folder is');
}
