<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the add comment handler (add_comment.php) treats a value posted
 * as an array as not given: page_id[]= became the form's session key, the
 * other fields reached the string functions and file[]= gave the upload
 * checks a list of names, each stopping the request on PHP 8.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_add_comment_fields_are_single_values()
{
	$code = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_comment.php');

	// The page id is checked before it names the form's session slot.
	$request = "pg_request_single_values(array('page_id'));";
	pg_assert_contains($request, $code, 'page id is one value');
	pg_assert_contains("new liveform(\$form_names['state'], \$_POST['page_id'] ?? 0);", $code, 'a missing page id is no notice');
	pg_assert_true(strpos($code, $request) < strpos($code, "new liveform(\$form_names['state']"), 'before the form is opened');

	$list = "pg_liveform_single_values(\$liveform, array('send_to', 'page_id', 'item_id', 'item_type', 'name', 'name_type', 'message', 'watcher', 'parent_id', 'kind', 'rating', 'publish', 'publish_date_and_time', 'publish_cancel'));";
	pg_assert_contains($list, $code, 'every field the handler reads is one value');
	$at = strpos($code, $list);
	pg_assert_true($at !== false && $at > strpos($code, '$liveform->add_fields_to_session();'), 'after the fields are stored');
	pg_assert_true($at !== false && $at < strpos($code, "\$send_to = \$liveform->get_field_value('send_to');"), 'before the first read');

	// A multi-file post (file[]=) is no attachment.
	pg_assert_contains("if (isset(\$_FILES['file']) && !is_string(\$_FILES['file']['name'] ?? null)) {", $code, 'file is one upload');
}
