<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a mail's HTML in the E-mails screen's reading pane
 * (includes/workspace/mail_pane.php, workspace_mail_frame.php,
 * assets/js/workspace_mail.js): the frame or the text a mail gets, Plain
 * text and Show pictures, the columns the pane reads, the frame's route and
 * the remembered senders. What a browser enforces on the frame is proven in
 * tests/js/guarded_frame_proofs.js; reading a mail needs the database and
 * is not run here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/guarded_frame.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_html.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_parse.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function workspace_mail_frame_test_source($file)
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file));
}

function workspace_mail_frame_test_item($changes = array())
{
	return array_merge(array('id' => 41, 'direction' => 'in', 'from_name' => 'Ayşe <A>', 'from_email' => ' Ayse@Example.com ', 'to_list' => 'support@example.com',
		'cc_list' => '', 'body_text' => "Hello <b>\nthere", 'refused_files' => '', 'user_id' => 0, 'sent_at' => 0, 'created_at' => 100, 'send_state' => '',
		'send_error' => '', 'files' => array(), 'bounces' => array(), 'has_html' => 1, 'outside_pictures' => 0), $changes);
}

// A mail with kept HTML: the frame, Plain text and the text beside it.
function test_workspace_mail_pane_body_frame()
{
	$html = ws_mail_pane_body_html(workspace_mail_frame_test_item(), 'Ayşe <A>', '/pg/');

	pg_assert_contains('<div class="ws-mail-body" data-ws-mail-body>', $html);
	pg_assert_contains('<iframe class="ws-mail-frame" src="/pg/workspace_mail_frame.php?item=41"', $html, 'the frame\'s own address');
	pg_assert_contains(' sandbox="' . pg_guarded_frame_panel_sandbox('mail') . '" loading="lazy"', $html, 'the mail mode\'s sandbox');
	pg_assert_contains('title="' . h(lang(array('string' => 'E-mail from {var:1}', 'vars' => 'Ayşe <A>'))) . '"', $html, 'the frame is named, the name escaped');
	pg_assert_contains('data-ws-mail-plain aria-pressed="false"', $html, 'Plain text, off');
	pg_assert_contains('<div class="ws-mail-text" hidden>Hello &lt;b&gt;<br>' . "\n" . 'there</div>', $html, 'the text waits behind the frame, escaped');
	pg_assert_same(false, strpos($html, 'data-ws-mail-show-pictures'), 'no outside picture, no Show pictures');
	pg_assert_same(false, strpos($html, 'pictures=1'), 'no second address');
	pg_assert_same(false, strpos($html, 'data-ws-mail-sender'), 'no sender to remember');

	$sandbox = pg_guarded_frame_panel_sandbox('mail');
	pg_assert_same('allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox', $sandbox);

	foreach (array('allow-forms', 'allow-top-navigation', 'allow-modals', 'allow-downloads') as $word) {
		pg_assert_same(false, strpos($sandbox, $word), 'the shell gets no ' . $word);
	}

	pg_assert_same(false, strpos(pg_guarded_frame_inner_sandbox('mail'), 'allow-scripts'), 'the mail itself runs no script');
}

// Pictures from outside wait: the line, its button and the second address.
function test_workspace_mail_pane_body_outside_pictures()
{
	$html = ws_mail_pane_body_html(workspace_mail_frame_test_item(array('outside_pictures' => '1')), 'Ayşe', '/pg/');

	pg_assert_contains('<span class="ws-mail-pictures" data-ws-mail-pictures>', $html);
	pg_assert_contains(h(lang('Pictures from outside are not shown.')), $html);
	pg_assert_contains('data-ws-mail-show-pictures>' . h(lang('Show pictures')) . '</button>', $html);
	pg_assert_contains('src="/pg/workspace_mail_frame.php?item=41"', $html, 'the frame starts without them');
	pg_assert_contains('data-ws-mail-pictures-src="/pg/workspace_mail_frame.php?item=41&amp;pictures=1"', $html, 'the address that shows them');
	pg_assert_contains('data-ws-mail-sender="ayse@example.com"', $html, 'remembered by the sender, lower case, trimmed');
	pg_assert_true(strpos($html, 'data-ws-mail-pictures>') < strpos($html, 'data-ws-mail-plain'), 'the line comes before Plain text');
}

// No kept HTML, or no panel address: the text as before.
function test_workspace_mail_pane_body_text_only()
{
	$text = '<div class="ws-mail-text">Hello &lt;b&gt;<br>' . "\n" . 'there</div>';

	pg_assert_same($text, ws_mail_pane_body_html(workspace_mail_frame_test_item(array('has_html' => 0)), 'Ayşe', '/pg/'), 'no HTML kept');
	pg_assert_same($text, ws_mail_pane_body_html(workspace_mail_frame_test_item(array('has_html' => '0')), 'Ayşe', '/pg/'), 'as the database answers it');
	pg_assert_same($text, ws_mail_pane_body_html(workspace_mail_frame_test_item(), 'Ayşe', ''), 'no address to frame');

	$item = workspace_mail_frame_test_item();
	unset($item['has_html'], $item['outside_pictures']);
	pg_assert_same($text, ws_mail_pane_body_html($item, 'Ayşe', '/pg/'), 'a row read without the columns');

	$pane = ws_mail_pane_item_html(workspace_mail_frame_test_item(array('has_html' => 0)), '/pg/');
	pg_assert_contains($text, $pane, 'the mail of the pane');
	pg_assert_same(false, strpos($pane, '<iframe'), 'no frame');
}

// The pane never reads a mail's kept HTML, only whether there is one.
function test_workspace_mail_pane_columns()
{
	$with = ws_mail_pane_columns(true);
	$without = ws_mail_pane_columns(false);

	foreach (array($with, $without) as $columns) {
		pg_assert_same(false, strpos($columns, '*'), 'named columns');
		pg_assert_contains('body_text, auth, refused_files', $columns);
		pg_assert_same(0, preg_match('/(^|,)\s*body_html\s*(,|$)/', $columns), 'the HTML itself is not read');
	}

	pg_assert_contains("(body_html IS NOT NULL AND body_html <> '') AS has_html", $with);
	pg_assert_contains("LOCATE(' data-pg-src=\"http', body_html) > 0 OR LOCATE(' data-pg-background=\"http', body_html) > 0) AS outside_pictures", $with, 'the cleaner\'s outside addresses');
	pg_assert_contains('0 AS has_html, 0 AS outside_pictures', $without, 'before the column exists');
	pg_assert_same(false, strpos($without, 'body_html'), 'the column is not named before it exists');

	$pane = workspace_mail_frame_test_source('includes/workspace/mail_pane.php');
	pg_assert_contains('"SELECT " . ws_mail_pane_columns(ws_mail_html_ready()) . " FROM ws_mail_items WHERE conversation_id = ', $pane, 'the pane reads them');
	pg_assert_same(false, strpos($pane, 'SELECT * FROM ws_mail_items'), 'and nothing reads every column');
	pg_assert_contains('$html .= ws_mail_pane_item_html($item, $base);', $pane, 'the pane passes its address');
}

// The frame's route: framed, a member, a mail of a readable conversation.
function test_workspace_mail_frame_route()
{
	$route = workspace_mail_frame_test_source('workspace_mail_frame.php');
	$pane = workspace_mail_frame_test_source('includes/workspace/mail_pane.php');
	$reader = substr($pane, (int) strpos($pane, 'function ws_mail_frame('), 2200);

	pg_assert_contains("include('init.php');\n\n\$user = validate_user();", $route, 'signed in');
	pg_assert_contains('if (!ws_ready() || !ws_enabled() || !pg_guarded_frame_request_framed($_SERVER)) {', $route, 'only as a frame');
	pg_assert_contains('$frame = (($item_id > 0) && $viewer[\'member\']) ? ws_mail_frame($viewer, $item_id) : null;', $route, 'a member');
	pg_assert_contains("http_response_code(404);", $route);
	pg_assert_contains("pg_guarded_frame_send(pg_mail_html_document(\$frame['html']), 'mail', lang('E-mail'), array(", $route, 'the mail mode');
	pg_assert_contains("'pictures' => ((\$_GET['pictures'] ?? '') === '1'),", $route, 'pictures only when asked');
	pg_assert_true(strpos($route, 'session_write_close();') < strpos($route, 'pg_guarded_frame_send('), 'the session is let go first');
	pg_assert_same('script', pg_guarded_frame_mode('mail'), 'an address never asks for the mail mode itself');

	pg_assert_contains('if (((int) $item_id <= 0) || !ws_mail_html_ready()) {', $reader);
	pg_assert_contains("AND message_id > 0\");", $reader, 'a conversation with its card');
	pg_assert_contains('if (!$channel || !ws_can_read_channel($viewer, $channel)) {', $reader, 'the channel decides');
	pg_assert_contains("WHERE m.sender_kind = 'mail' AND m.sender_id = '\" . (int) \$item['id'] . \"' AND m.file_id > 0 AND m.deleted_at = 0", $reader, 'only the mail\'s own files');
}

// The remembered senders and the reported height, under Node.
function test_workspace_mail_frame_script()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$many = array();

	for ($i = 0; $i < 600; $i++) {
		$many[] = 'p' . $i . '@x.test';
	}

	$script = "'use strict';\nvar window = {};\n" . workspace_mail_frame_test_source('assets/js/workspace_mail.js')
		. "\nvar M = window.PGWsMail;\nvar many = " . json_encode($many) . ";\nconsole.log(JSON.stringify(["
		. "M.picturesKey('/pg/api.php', 7), M.picturesKey('/shop/api.php', '8'),"
		. "M.picturesSenders(JSON.stringify([' A@X.test ', 'b@x.test', 'a@x.test', 5, null, '', 'c@x.test'])),"
		. "M.picturesSenders('{broken'), M.picturesSenders(JSON.stringify({a: 1})), M.picturesSenders(null),"
		. "M.picturesSenders(JSON.stringify(many)).length,"
		. "M.picturesAdd(['a@x.test', 'b@x.test'], ' B@X.test'), M.picturesAdd(['a@x.test'], ''),"
		. "M.picturesAdd(many, 'new@x.test').length, M.picturesAdd(many, 'new@x.test')[0], M.picturesAdd(many, 'new@x.test')[499],"
		. "M.frameHeight(240.4), M.frameHeight(0), M.frameHeight(-1), M.frameHeight('300'), M.frameHeight(1 / 0), M.frameHeight(10000000)"
		. "]));\n";
	$file = tempnam(sys_get_temp_dir(), 'pgwsmailframe');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	$answer = json_decode(implode("\n", $out), true);

	pg_assert_true(is_array($answer), 'Node printed JSON: ' . implode("\n", $out));
	pg_assert_same('ws-mail-pictures:/pg/api.php|7', $answer[0], 'one list per site and person');
	pg_assert_same('ws-mail-pictures:/shop/api.php|8', $answer[1]);
	pg_assert_same(array('a@x.test', 'b@x.test', 'c@x.test'), $answer[2], 'lower case, once each, words only');
	pg_assert_same(array(), $answer[3], 'broken');
	pg_assert_same(array(), $answer[4], 'not a list');
	pg_assert_same(array(), $answer[5], 'nothing kept');
	pg_assert_same(500, $answer[6], 'at most 500 read');
	pg_assert_same(array('b@x.test', 'a@x.test'), $answer[7], 'the sender moves first');
	pg_assert_same(array('a@x.test'), $answer[8], 'no sender, no change');
	pg_assert_same(500, $answer[9], 'at most 500 kept');
	pg_assert_same('new@x.test', $answer[10]);
	pg_assert_same('p498@x.test', $answer[11], 'the oldest fall off');
	pg_assert_same(array(240, 0, -1, -1, -1, 100000), array_slice($answer, 12), 'whole pixels within bounds');

	$mail = workspace_mail_frame_test_source('assets/js/workspace_mail.js');
	pg_assert_contains("data.pg !== 'guarded-frame' || event.origin !== window.location.origin", $mail, 'only this site is heard');
	pg_assert_contains('if (frame.contentWindow && frame.contentWindow === event.source) {', $mail, 'only a frame of the pane');
	pg_assert_contains("pane.innerHTML = data.html;\n                dress();", $mail, 'remembered senders on every drawn pane');
	pg_assert_contains("current = start;\n                dress();", $mail, 'and on the one the server drew');
	pg_assert_contains("try {\n                stored = window.localStorage.getItem(slot);", $mail, 'storage may fail');
	pg_assert_contains("try {\n                window.localStorage.setItem(slot, JSON.stringify(picturesAdd(remembered(), sender)));", $mail);
	pg_assert_contains("var slot = picturesKey(h.cfg && h.cfg.api_url, h.cfg && h.cfg.me);", $mail);
}
