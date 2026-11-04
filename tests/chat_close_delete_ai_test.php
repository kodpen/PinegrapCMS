<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the panel chat window: close and delete leave the
 * conversation only on success, the closer's own close is not unread to
 * them, an image attachment is a real link, and the AI Chat snippet gets
 * the panel's texts and theme colours.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function chat_close_delete_source($path)
{
	return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path));
}

// The body of one JavaScript or PHP function, up to the next declaration
// at the same or a lower indent.
function chat_close_delete_function($source, $declaration)
{
	$start = strpos($source, $declaration);
	if ($start === false) {
		return '';
	}
	$end = strpos($source, "\n    function ", $start + strlen($declaration));
	$php_end = strpos($source, "\nfunction ", $start + strlen($declaration));
	if ($php_end !== false && ($end === false || $php_end < $end)) {
		$end = $php_end;
	}

	return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_chat_close_and_delete_leave_only_on_success()
{
	$js = chat_close_delete_source('assets/js/chat_backend.src.js');
	$leave = chat_close_delete_function($js, 'function leaveOnSuccess(');

	pg_assert_true($leave !== '', 'leaveOnSuccess() exists');
	pg_assert_true(strpos($leave, "response.status !== 'success'") !== false, 'leaveOnSuccess() reads the response status');
	pg_assert_true(strpos($leave, 'window.alert(response.message') !== false, 'a refusal shows the server message');
	pg_assert_true(strpos($js, "api('chat_close', { conversation_id: state.conversation.id }, leaveOnSuccess, alertRequestFailed)") !== false, 'chat_close checks the answer and handles a failed request');
	pg_assert_true(strpos($js, "api('chat_delete', { conversation_id: state.conversation.id }, leaveOnSuccess, alertRequestFailed)") !== false, 'chat_delete checks the answer and handles a failed request');

	$php = chat_close_delete_source('chat.php');
	pg_assert_true(strpos($php, "'request_failed' => lang('An error occurred.')") !== false, 'the generic failure text is in the panel strings');
}

function test_chat_close_moves_the_closers_read_cursor()
{
	$close = chat_close_delete_function(chat_close_delete_source('chat.php'), "\nfunction pg_chat_close(");

	pg_assert_true($close !== '', 'pg_chat_close() is found');
	pg_assert_true(strpos($close, "\$my_read_column = (\$side == 'initiator') ? 'initiator_last_read_id' : 'target_last_read_id';") !== false, 'the closer\'s read column is chosen by side');
	pg_assert_true(strpos($close, '" . $my_read_column . " = \'" . e($message_id) . "\'') !== false, 'the closer\'s read cursor moves onto the closing message');
}

function test_chat_image_attachment_is_a_link()
{
	$node = chat_close_delete_function(chat_close_delete_source('assets/js/chat_backend.src.js'), 'function attachmentNode(');
	$image = substr($node, 0, (int) strpos($node, "attachment.kind === 'audio'"));

	pg_assert_true($image !== '', 'the image branch of attachmentNode() is found');
	pg_assert_true(strpos($image, "document.createElement('a')") !== false, 'the image preview sits in a link');
	pg_assert_true(strpos($image, "imageLink.rel = 'noopener'") !== false, 'the link opens without an opener');
	pg_assert_true(strpos($image, 'window.open(') === false, 'no mouse-only click handler is left');
	pg_assert_true(strpos($image, 'cursor') === false, 'no pointer cursor is faked on the image');
}

function test_chat_ai_snippet_gets_panel_texts_and_colours()
{
	$js = chat_close_delete_source('assets/js/chat_backend.src.js');
	pg_assert_true(strpos($js, "snippet.setAttribute('translations', JSON.stringify(config.ai.translations))") !== false, 'the snippet gets the translations attribute');

	$php = chat_close_delete_source('chat.php');
	$launcher = chat_close_delete_function($php, "\nfunction pg_chat_render_backend_launcher(");
	foreach (array('chatPlaceholder', 'sendButtonLabel', 'chatEmptyTitle', 'chatEmptyDescription', 'historyTitle', 'newChatButton', 'clearChatButton') as $key) {
		pg_assert_true(strpos($launcher, "'" . $key . "' => lang(") !== false, 'the snippet text ' . $key . ' goes through lang()');
	}
	pg_assert_true(strpos($launcher, '--search-snippet-background: var(--pg-bg-body)') !== false, 'the snippet background follows the panel theme');
	pg_assert_true(strpos($launcher, '--search-snippet-text-color: var(--pg-fg-body)') !== false, 'the snippet text colour follows the panel theme');
}
