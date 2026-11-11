<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Workspace's New e-mail form (mail_compose.php,
 * assets/js/workspace_mail.js): a mailbox's signature and where it goes,
 * what the form offers (the mailboxes, the channel a mail is kept in, the
 * limits), the files of a form request, which field a refusal points at,
 * the unsent form kept in the browser (run under Node), and the wiring of
 * the panel actions, the screen's button and the channel menu's item. The
 * send itself is ws_mail_send() (tests/workspace_mail_send_test.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_parse.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function workspace_mail_compose_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function workspace_mail_compose_test_entry($box_id, $name, $channels, $settings = array())
{
	$rows = array();

	foreach ($channels as $id => $channel_name) {
		$rows[] = array('id' => $id, 'name' => $channel_name, 'kind' => 'public');
	}

	return array(
		'box'      => array('id' => $box_id, 'name' => $name, 'address' => strtolower($name) . '@example.com', 'channel_id' => key($channels), 'settings' => json_encode($settings)),
		'route'    => array('ok' => true, 'error' => '', 'smtp' => null),
		'channels' => $rows,
	);
}

function test_workspace_mail_compose_signature_clean()
{
	pg_assert_same("Acme Support\n+90 212 000 00 00", ws_mail_signature_clean("  Acme Support\r\n+90 212 000 00 00\r\n\r\n"), 'line ends folded, ends trimmed');
	pg_assert_same("Acme\tLtd", ws_mail_signature_clean("Acme\x00\tLtd\x07"), 'control characters dropped, a tab kept');
	pg_assert_same('', ws_mail_signature_clean(array('x')), 'not text');
	pg_assert_same(WS_MAIL_SIGNATURE_MAX, mb_strlen(ws_mail_signature_clean(str_repeat('ş', WS_MAIL_SIGNATURE_MAX + 50))), 'cut at the limit, by characters');

	$lines = ws_mail_signature_clean(implode("\n", range(1, WS_MAIL_SIGNATURE_LINES + 4)));
	pg_assert_same(WS_MAIL_SIGNATURE_LINES, count(explode("\n", $lines)), 'at most the line limit');
	pg_assert_same("a\n\nb", ws_mail_signature_clean("a\n\n\n\nb"), 'a run of blank lines is one');
}

function test_workspace_mail_compose_box_signature()
{
	pg_assert_same('Acme Support', ws_mail_box_signature(array('settings' => json_encode(array('signature' => " Acme Support \n")))), 'read from the box settings');
	pg_assert_same('', ws_mail_box_signature(array('settings' => '')), 'none kept');
	pg_assert_same('', ws_mail_box_signature(null), 'no box');
}

function test_workspace_mail_compose_signature_under_the_text()
{
	$item = array('id' => 7, 'to_list' => 'ali@example.com', 'cc_list' => '', 'bcc_list' => '', 'subject' => 'Hello [#T3]',
		'body_text' => 'Your order left today.', 'from_name' => 'Ayşe', 'rfc_message_id' => '<pg.c3.x@example.com>', 'message_id' => 0);
	$box = array('id' => 1, 'name' => 'Support', 'address' => 'support@example.com', 'settings' => json_encode(array('signature' => "Acme Support\n+90 212 000 00 00")));
	$route = array('ok' => true, 'error' => '', 'smtp' => null);

	$with = ws_mail_send_properties($item, $box, $route);
	pg_assert_same("Your order left today.\n\n-- \nAyşe\nAcme Support\n+90 212 000 00 00", $with['body'], 'the signature stands under the writer in place of the box name');

	$box['settings'] = '';
	$without = ws_mail_send_properties($item, $box, $route);
	pg_assert_same("Your order left today.\n\n-- \nAyşe\nSupport", $without['body'], 'without one the box name stays');

	pg_assert_contains('ws_mail_signature_line($box)', workspace_mail_compose_test_source('includes/workspace/mail_reply.php'), 'an answer carries the same signature');
}

function test_workspace_mail_compose_box_form_keeps_the_signature()
{
	$input = ws_mail_box_input(array('name' => 'Support', 'address' => 'support@example.com', 'channel_id' => 3, 'signature' => "Acme\r\nSupport\x00"));
	pg_assert_true($input['ok'], 'a box with only a name, an address and a channel');
	pg_assert_same("Acme\nSupport", $input['values']['signature'], 'the signature cleaned');

	$settings = workspace_mail_compose_test_source('includes/workspace/mail_settings.php');
	pg_assert_contains("'reads_bounces', 'signature') as \$key", $settings, 'saved into the settings');
	pg_assert_contains('name="signature"', $settings, 'the box form has the field');
}

function test_workspace_mail_compose_offer_shape()
{
	$entries = array(
		workspace_mail_compose_test_entry(1, 'Support', array(10 => 'support', 12 => 'orders'), array('signature' => 'Acme')),
		workspace_mail_compose_test_entry(2, 'Sales', array(20 => 'sales')),
	);

	$offer = ws_mail_compose_offer_shape($entries, 0, 'Ayşe');
	pg_assert_same(2, count($offer['boxes']), 'both mailboxes');
	pg_assert_same(array('id' => 1, 'name' => 'Support', 'address' => 'support@example.com', 'signature' => 'Acme',
		'channels' => array(array('id' => 10, 'name' => 'support'), array('id' => 12, 'name' => 'orders'))), $offer['boxes'][0], 'a mailbox as the form draws it');
	pg_assert_same(array(1, 10), array($offer['box_id'], $offer['channel_id']), 'nothing asked: the first mailbox in its first channel');
	pg_assert_same('Ayşe', $offer['writer'], 'the writer named under the text');

	$asked = ws_mail_compose_offer_shape($entries, 12, 'Ayşe');
	pg_assert_same(array(1, 12), array($asked['box_id'], $asked['channel_id']), 'a channel one route writes to');

	$asked = ws_mail_compose_offer_shape($entries, 20, 'Ayşe');
	pg_assert_same(array(2, 20), array($asked['box_id'], $asked['channel_id']), 'the mailbox of the channel asked for');

	$unknown = ws_mail_compose_offer_shape($entries, 99, 'Ayşe');
	pg_assert_same(array(1, 10), array($unknown['box_id'], $unknown['channel_id']), 'a channel no mailbox writes to falls back');

	$none = ws_mail_compose_offer_shape(array(), 0, 'Ayşe');
	pg_assert_same(array(array(), 0, 0), array($none['boxes'], $none['box_id'], $none['channel_id']), 'nothing to send from');

	pg_assert_same(WS_MAIL_SEND_ADDRESSES, $offer['limits']['addresses'], 'the address limit');
	pg_assert_same(WS_MAIL_SEND_FILES, $offer['limits']['files'], 'the file count');
	pg_assert_true(($offer['limits']['bytes'] > 0) && ($offer['limits']['bytes'] <= WS_MAIL_SEND_FILES_BYTES), 'the bytes, never above the mail\'s own limit');
	pg_assert_same(WS_MAIL_REPLY_MAX, $offer['limits']['text'], 'the text limit');
}

function test_workspace_mail_compose_file_parts()
{
	$many = array(
		'name'     => array('a.pdf', 'b.png'),
		'type'     => array('application/pdf', 'image/png'),
		'tmp_name' => array('/tmp/phpA', '/tmp/phpB'),
		'error'    => array(UPLOAD_ERR_OK, UPLOAD_ERR_INI_SIZE),
		'size'     => array(10, 0),
	);

	pg_assert_same(array(
		array('name' => 'a.pdf', 'tmp_name' => '/tmp/phpA', 'error' => UPLOAD_ERR_OK, 'size' => 10),
		array('name' => 'b.png', 'tmp_name' => '/tmp/phpB', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0),
	), ws_mail_compose_file_parts($many), 'files[] as PHP lays them out');

	pg_assert_same(array(array('name' => 'a.pdf', 'tmp_name' => '/tmp/phpA', 'error' => UPLOAD_ERR_OK, 'size' => 10)),
		ws_mail_compose_file_parts(array('name' => 'a.pdf', 'tmp_name' => '/tmp/phpA', 'error' => UPLOAD_ERR_OK, 'size' => 10)), 'one part');
	pg_assert_same(array(), ws_mail_compose_file_parts(null), 'no files');
	pg_assert_same(array(), ws_mail_compose_file_parts(array('name' => array(), 'tmp_name' => array(), 'error' => array(), 'size' => array())), 'an empty list');

	$skipped = ws_mail_compose_file_parts(array('name' => array(''), 'tmp_name' => array(''), 'error' => array(UPLOAD_ERR_NO_FILE), 'size' => array(0)));
	pg_assert_same(array(), $skipped, 'an empty file field is no file');
}

function test_workspace_mail_compose_error_field()
{
	foreach (array('no_to' => 'to', 'too_many' => 'to', 'bad_address' => 'to', 'blocked' => 'to', 'no_subject' => 'subject', 'empty' => 'text',
		'too_long' => 'text', 'too_many_files' => 'files', 'files_too_large' => 'files', 'file_refused' => 'files', 'no_box' => 'box',
		'no_access' => 'channel', 'hourly' => '', 'not_sent' => '') as $error => $field) {
		pg_assert_same($field, ws_mail_compose_error_field($error), $error);
	}
}

function test_workspace_mail_compose_wiring()
{
	$actions = workspace_mail_compose_test_source('includes/workspace/actions.php');
	pg_assert_contains("case 'ws_mail_compose':", $actions, 'the form asks what it offers');
	pg_assert_contains("case 'ws_mail_new':", $actions, 'the form sends');
	pg_assert_contains('ws_mail_send($viewer, ', $actions, 'through the one send path');
	pg_assert_contains('ws_mail_compose_request_files(', $actions, 'with the files of the request');

	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mail_compose.php');", workspace_mail_compose_test_source('includes/workspace/bootstrap.php'), 'loaded with the Workspace');
	pg_assert_contains("\$brief['mail_new'] = ", workspace_mail_compose_test_source('includes/workspace/channels.php'), 'a channel says whether its mailbox sends from it');

	$script = workspace_mail_compose_test_source('assets/js/workspace_mail.js');
	pg_assert_contains("h.api('ws_mail_compose', ", $script, 'the form reads its offer');
	pg_assert_contains("'ws_mail_new'", $script, 'and sends');
	pg_assert_contains("form.append('files[]', ", $script, 'files as form parts');
	pg_assert_contains('data-ws-mail-new', $script, 'the screen\'s button');
	pg_assert_contains('write: write', $script, 'the form is reachable from the channel screen');

	$channels = workspace_mail_compose_test_source('assets/js/workspace.js');
	pg_assert_contains("item(t('mail_new'), 'bi-envelope-plus', function () {", $channels, 'the channel menu\'s item');
	pg_assert_contains('channel.mail_new', $channels, 'only where a mailbox sends from the channel');

	$bundle = workspace_mail_compose_test_source('assets/js/workspace.min.js');
	pg_assert_contains('ws_mail_compose', $bundle, 'the bundle is rebuilt');
}

function test_workspace_mail_compose_toolbar_button()
{
	$filters = ws_mail_list_filters(array());
	pg_assert_contains('data-ws-mail-new', ws_mail_list_filters_html($filters, array(), true), 'a person who may send sees New e-mail');
	pg_assert_false(strpos(ws_mail_list_filters_html($filters, array(), false), 'data-ws-mail-new') !== false, 'nobody else');
}

// The draft helpers of workspace_mail.js run under Node; null without Node.
function workspace_mail_compose_test_node($steps)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = workspace_mail_compose_test_source('assets/js/workspace_mail.js');
	$code_parts = array();

	foreach (array('DRAFT_DAYS', 'DRAFT_FIELDS') as $name) {
		if (!preg_match('/\n    var ' . $name . ' = [^\n]*\n/', $source, $match)) {
			return array();
		}

		$code_parts[] = $match[0];
	}

	foreach (array('draftKey', 'draftRead') as $name) {
		$start = strpos($source, "\n    function " . $name . '(');

		if ($start === false) {
			return array();
		}

		$end = strpos($source, "\n    }\n", (int) $start);
		$code_parts[] = substr($source, (int) $start, (int) $end - (int) $start) . "\n    }\n";
	}

	$script = implode('', $code_parts) . "\nvar steps = " . json_encode($steps) . ";\n"
		. "console.log(JSON.stringify(steps.map(function (s) { return s[0] === 'key' ? draftKey(s[1]) : draftRead(s[1], s[2]); })));\n";
	$file = tempnam(sys_get_temp_dir(), 'pgmail') . '.js';
	file_put_contents($file, $script);
	$lines = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $lines), true) : array('error' => implode("\n", $lines));
}

function test_workspace_mail_compose_draft_kept_in_the_browser()
{
	$now = 1800000000000;
	$fresh = json_encode(array('to' => 'ali@example.com', 'subject' => 'Hi', 'text' => 'Hello', 'box' => 1, 'channel' => 10, 'at' => $now - 1000, 'evil' => 'x'));
	$old = json_encode(array('text' => 'Hello', 'at' => $now - (15 * 86400000)));
	$result = workspace_mail_compose_test_node(array(
		array('key', array('api_url' => '/pg/api.php', 'me' => 5)),
		array('read', $fresh, $now),
		array('read', $old, $now),
		array('read', '{broken', $now),
		array('read', json_encode(array('text' => '', 'to' => '', 'at' => $now)), $now),
	));

	if ($result === null) {
		return;
	}

	pg_assert_same('ws-mail-new:/pg/api.php|5', $result[0] ?? null, 'one slot per site and person');
	pg_assert_same(array('box' => 1, 'channel' => 10, 'to' => 'ali@example.com', 'cc' => '', 'bcc' => '', 'subject' => 'Hi', 'text' => 'Hello'), $result[1] ?? null, 'a fresh form, only its own fields');
	pg_assert_same(null, array_key_exists(2, $result) ? $result[2] : 'missing', 'older than two weeks is gone');
	pg_assert_same(null, array_key_exists(3, $result) ? $result[3] : 'missing', 'a broken value is nothing');
	pg_assert_same(null, array_key_exists(4, $result) ? $result[4] : 'missing', 'an empty form is nothing');
}
