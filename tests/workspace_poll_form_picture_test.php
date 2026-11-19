<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the New poll form's "Question picture" field: the fields the
 * form sends (ws_poll_picture_from_form()), the panel actions that take
 * them, and the form's own lines in assets/js/workspace.js.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/poll_picture.php');

// The panel actions' lines from one case to the next.
function workspace_poll_form_picture_test_case($from, $to)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$start = strpos($source, "case '" . $from . "':");
	$end = strpos($source, "case '" . $to . "':", (int) $start);

	return (($start === false) || ($end === false)) ? '' : substr($source, $start, $end - $start);
}

// A new picture, a removal, nothing sent, and what does not read.
function test_ws_poll_picture_from_form()
{
	$new = ws_poll_picture_from_form(array('picture_name' => 'C:\\shots\\screen.png', 'picture_base64' => base64_encode('PNG')));
	pg_assert_same(array('error' => '', 'field' => '', 'sent' => true, 'picture' => array('name' => 'screen.png', 'bytes' => 'PNG')), $new, 'the name without its folder');

	pg_assert_same(array('error' => '', 'field' => '', 'sent' => true, 'picture' => null), ws_poll_picture_from_form(array('remove_picture' => 1)), 'a removal');
	pg_assert_same(array('error' => '', 'field' => '', 'sent' => false, 'picture' => null), ws_poll_picture_from_form(array('question' => 'Q')), 'nothing sent keeps it');

	pg_assert_same('picture', ws_poll_picture_from_form(array('picture_name' => 'a.png', 'picture_base64' => base64_encode('PNG'), 'remove_picture' => 1))['field'], 'both at once');
	pg_assert_same('picture', ws_poll_picture_from_form(array('picture_name' => 'a.png', 'picture_base64' => '%%%'))['field'], 'not base64');
	pg_assert_same('picture', ws_poll_picture_from_form(array('picture_base64' => base64_encode('PNG')))['field'], 'no name');
}

// Create sends it with the question; edit checks the change before the
// edit and writes it after; ask again carries a new one or none.
function test_ws_poll_form_picture_actions()
{
	$create = workspace_poll_form_picture_test_case('ws_poll_create', 'ws_poll_edit');
	pg_assert_true(strpos($create, 'ws_poll_picture_from_form($request)') !== false, 'create reads the form\'s picture');
	pg_assert_true(strpos($create, "'picture'   => \$form_picture['picture']") !== false, 'create sends it with the question');

	$edit = workspace_poll_form_picture_test_case('ws_poll_edit', 'ws_poll_withdraw');
	$check_at = strpos($edit, 'ws_poll_picture_change_check(');
	$edit_at = strpos($edit, 'ws_poll_edit($viewer');
	$change_at = strpos($edit, 'ws_poll_picture_change($viewer');
	pg_assert_true($check_at !== false && $edit_at !== false && $change_at !== false && $check_at < $edit_at && $edit_at < $change_at, 'edit: checked first, written after the edit');

	$again = workspace_poll_form_picture_test_case('ws_poll_withdraw', 'ws_poll_vote');
	pg_assert_true(strpos($again, "\$sent['picture'] = \$form_picture['picture'];") !== false, 'ask again takes a new picture or none');
}

// The form's field: a translated label, an upload and a removal, sent only
// when changed.
function test_ws_poll_form_picture_field()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$strings = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

	foreach (array('poll_question_picture', 'poll_question_picture_help', 'poll_question_picture_upload', 'poll_question_picture_locked') as $key) {
		pg_assert_true(strpos($strings, "'" . $key . "'") !== false, $key . ' is given');
		pg_assert_true(strpos($source, "t('" . $key . "')") !== false, $key . ' is used');
	}

	pg_assert_true(strpos($source, 'settings.picture_base64 = questionPicture.data;') !== false, 'a new picture goes as base64');
	pg_assert_true(strpos($source, 'settings.remove_picture = 1;') !== false, 'a removal is sent');
}
