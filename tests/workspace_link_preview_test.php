<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the link preview of a Workspace message
 * (includes/workspace/link_preview.php): the first web address of a text,
 * the address check and the cut of a page's fields.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/link_preview.php');

function test_workspace_link_preview_first_url()
{
	pg_assert_same('https://example.com/a', ws_link_preview_first_url('See https://example.com/a and https://example.org/b'), 'the first address');
	pg_assert_same('', ws_link_preview_first_url('no address here'), 'no address');
	pg_assert_same('', ws_link_preview_first_url('ftp://example.com/file'), 'only http and https');
	pg_assert_same('http://example.com/x?a=1', ws_link_preview_first_url('Look: http://example.com/x?a=1.'), 'a full stop is the sentence');
	pg_assert_same('https://example.com/b', ws_link_preview_first_url('(see https://example.com/b), done'), 'a bracket around the address');
	pg_assert_same('https://en.wikipedia.org/wiki/Ankara_(disambiguation)', ws_link_preview_first_url('https://en.wikipedia.org/wiki/Ankara_(disambiguation)'), 'a bracket of the address');
}

function test_workspace_link_preview_first_url_skips_code_and_tags()
{
	pg_assert_same('https://example.org/b', ws_link_preview_first_url('run `curl https://example.com/a` then https://example.org/b'), 'inline code');
	pg_assert_same('https://example.org/b', ws_link_preview_first_url("```\nhttps://example.com/a\n```\nhttps://example.org/b"), 'a code block');
	pg_assert_same('', ws_link_preview_first_url("```\nhttps://example.com/a"), 'an open code block');
	pg_assert_same('https://example.org/b', ws_link_preview_first_url('<#order:5> <@app:2> https://example.org/b'), 'tags are not addresses');
	pg_assert_same('', ws_link_preview_first_url('https://' . str_repeat('a', 2100) . '.com'), 'too long');
}

function test_workspace_link_preview_url_allowed()
{
	pg_assert_true(ws_link_preview_url_allowed('https://example.com/a?b=1'), 'a public name');
	pg_assert_true(ws_link_preview_url_allowed('http://example.com:80/'), 'the usual port');
	pg_assert_true(ws_link_preview_url_allowed('https://93.184.216.34/'), 'a public address');

	foreach (array(
		'ftp://example.com/',
		'https://user:pass@example.com/',
		'https://example.com:8080/',
		'http://localhost/',
		'http://intranet/',
		'http://printer.local/',
		'http://db.internal/',
		'http://127.0.0.1/',
		'http://10.1.2.3/',
		'http://192.168.0.1/',
		'http://169.254.169.254/latest/meta-data/',
		'http://[::1]/',
		'http://0x7f.1/',
		'http://2130706433.0/',
		'https://exa mple.com/',
		'',
	) as $url) {
		pg_assert_false(ws_link_preview_url_allowed($url), 'refused: ' . $url);
	}
}

function test_workspace_link_preview_redirect_target_and_type()
{
	pg_assert_same('https://example.com/b', ws_link_preview_redirect_target('/b', 'https://example.com/a'), 'a path');
	pg_assert_same('https://example.org/', ws_link_preview_redirect_target('https://example.org/', 'https://example.com/a'), 'a whole address');
	pg_assert_same('', ws_link_preview_redirect_target('', 'https://example.com/a'), 'no Location');

	pg_assert_true(ws_link_preview_is_html('text/html; charset=utf-8'), 'html');
	pg_assert_true(ws_link_preview_is_html('application/xhtml+xml'), 'xhtml');
	pg_assert_false(ws_link_preview_is_html('application/pdf'), 'pdf');
	pg_assert_false(ws_link_preview_is_html(''), 'none said');
}

function test_workspace_link_preview_cut()
{
	pg_assert_same('Bold & plain', ws_link_preview_cut("  <b>Bold</b> &amp;\n\t plain ", 100), 'tags, entities, spaces');
	pg_assert_same('abcd…', ws_link_preview_cut('abcdefghij', 5), 'cut with an ellipsis');
	pg_assert_same('çğış…', ws_link_preview_cut('çğışöü', 5), 'characters, not bytes');
	pg_assert_same('a b', ws_link_preview_cut("a\x00\u{202E}b", 10), 'control and direction marks out');
	pg_assert_same('<script>', ws_link_preview_cut('&lt;script&gt;', 20), 'kept plain; escaped where drawn');
}

function test_workspace_link_preview_fields()
{
	$html = '<html><head><title>Plain title</title>'
		. '<meta property="og:title" content="Open &amp; Graph">'
		. '<meta name="description" content="A page about things.">'
		. '<meta property="og:site_name" content="Example">'
		. '</head><body><h1>x</h1></body></html>';

	pg_assert_same(array('title' => 'Open & Graph', 'description' => 'A page about things.', 'site_name' => 'Example'), ws_link_preview_fields($html, 'https://www.example.com/a'), 'og first');

	pg_assert_same(array('title' => 'Başlık', 'description' => '', 'site_name' => 'example.com'), ws_link_preview_fields('<title>Başlık</title>', 'https://www.example.com/a'), 'the title tag, the host');

	$latin = mb_convert_encoding('<html><head><meta charset="iso-8859-9"><title>Ağaç</title></head></html>', 'ISO-8859-9', 'UTF-8');
	pg_assert_same('Ağaç', ws_link_preview_fields($latin, 'https://example.com/')['title'], 'a page in another charset');

	pg_assert_same('', ws_link_preview_fields('', 'https://example.com/')['title'], 'nothing to show');
	pg_assert_same(WS_LINK_PREVIEW_TITLE_MAX, mb_strlen(ws_link_preview_fields('<title>' . str_repeat('ş', 400) . '</title>', 'https://e.com/')['title'], 'UTF-8'), 'a long title cut');
}

function test_workspace_link_preview_own_page()
{
	pg_assert_same('about-us', ws_link_preview_own_page('https://www.example.com/about-us?x=1', 'example.com', '/'), 'a page of the site');
	pg_assert_same('', ws_link_preview_own_page('https://example.com/', 'www.example.com', '/'), 'the home page');
	pg_assert_same('hakkımızda', ws_link_preview_own_page('https://example.com/hakk%C4%B1m%C4%B1zda', 'example.com', '/'), 'a name with letters of its own');
	pg_assert_same('shop', ws_link_preview_own_page('https://example.com/site/shop', 'example.com', '/site/'), 'under the site path');
	pg_assert_same('', ws_link_preview_own_page('https://example.com/site', 'example.com', '/site/'), 'the site path itself');
	pg_assert_same(null, ws_link_preview_own_page('https://example.com/other', 'example.com', '/site/'), 'outside the site path');
	pg_assert_same(null, ws_link_preview_own_page('https://example.org/about', 'example.com', '/'), 'another site');
	pg_assert_same(null, ws_link_preview_own_page('https://example.com/files/a.pdf', 'example.com', '/'), 'a file');
	pg_assert_same(null, ws_link_preview_own_page('https://example.com/a/b', 'example.com', '/'), 'deeper than a page');
}

function test_workspace_link_preview_card_html()
{
	$row = array('url' => 'https://example.com/a?b=1&c=2', 'title' => 'A <b>title</b>', 'description' => 'Say "hi" & go', 'site_name' => 'Ex<ample>', 'state' => 'shown');
	$html = ws_link_preview_card_html($row);

	pg_assert_same('<a class="ws-link-card" href="https://example.com/a?b=1&amp;c=2" target="_blank" rel="noopener noreferrer">'
		. '<span class="ws-link-card-site">Ex&lt;ample&gt;</span><span class="ws-link-card-title">A &lt;b&gt;title&lt;/b&gt;</span>'
		. '<span class="ws-link-card-text">Say &quot;hi&quot; &amp; go</span></a>', $html, 'every text escaped, a new tab, no opener');

	pg_assert_same(false, strpos(ws_link_preview_card_html(array('description' => '', 'site_name' => '') + $row), 'ws-link-card-text'), 'no description, no line for it');

	foreach (array('waiting', 'none', 'removed', '') as $state) {
		pg_assert_same('', ws_link_preview_card_html(array('state' => $state) + $row), 'no card in state ' . $state);
	}

	pg_assert_same('', ws_link_preview_card_html(array('title' => '  ') + $row), 'no card without a title');
	pg_assert_same('', ws_link_preview_card_html(array('url' => 'javascript:alert(1)') + $row), 'only a web address is a link');
	pg_assert_same('', ws_link_preview_card_html(null), 'no row');
}

function test_workspace_link_preview_can_remove()
{
	$viewer = array('id' => 7);
	$message = array('sender_kind' => 'user', 'sender_id' => 7, 'deleted_at' => 0);

	pg_assert_true(ws_link_preview_can_remove($viewer, $message, true), 'the writer');
	pg_assert_false(ws_link_preview_can_remove(array('id' => 8), $message, true), 'another person');
	pg_assert_false(ws_link_preview_can_remove($viewer, $message, false), 'no card to remove');
	pg_assert_false(ws_link_preview_can_remove($viewer, array('sender_kind' => 'app') + $message, true), 'an application\'s message');
	pg_assert_false(ws_link_preview_can_remove($viewer, array('deleted_at' => 5) + $message, true), 'a deleted message');
	pg_assert_false(ws_link_preview_can_remove(array('id' => 0), array('sender_id' => 0) + $message, true), 'nobody');
}

function test_workspace_link_preview_cards_off_without_the_switch()
{
	// With no table (the test runner has no database) the switch reads off:
	// no card is drawn, whatever the ids.
	pg_assert_same(array(), ws_link_preview_cards_map(array(1, 2, 3)), 'no cards while off');
	pg_assert_same(array(), ws_link_preview_cards_map(array()), 'no ids');
}

function test_workspace_link_preview_card_wiring()
{
	$root = PG_FUNCTIONS_DIR;
	$js = file_get_contents($root . '/assets/js/workspace.js');
	$messages = file_get_contents($root . '/includes/workspace/messages.php');
	$actions = file_get_contents($root . '/includes/workspace/actions.php');

	pg_assert_true(strpos($messages, "\$payload['link_preview']") !== false && strpos($messages, "\$payload['can_remove_preview']") !== false, 'the payload carries the card');
	pg_assert_true(strpos($actions, "case 'ws_link_preview_remove':") !== false, 'the remove action');
	pg_assert_true(strpos($js, 'message.link_preview') !== false && strpos($js, "api('ws_link_preview_remove'") !== false, 'drawn and removed in the screen');
	pg_assert_true(strpos($js, 'message.can_remove_preview') !== false, 'the menu entry only for the writer');
}

function test_workspace_link_preview_card_fields()
{
	$row = array('url' => 'https://example.com/a', 'title' => ' A <b>title</b> & more ', 'description' => 'Text "quoted"', 'site_name' => 'Example', 'state' => 'shown');
	$fields = ws_link_preview_card_fields($row);

	// The texts as stored: plain, trimmed, never escaped into HTML.
	pg_assert_same(array('url' => 'https://example.com/a', 'title' => 'A <b>title</b> & more', 'description' => 'Text "quoted"', 'site_name' => 'Example'), $fields, 'a read row');

	foreach (array('waiting', 'none', 'refused', 'removed', '') as $state) {
		pg_assert_same(null, ws_link_preview_card_fields(array_merge($row, array('state' => $state))), 'nothing for ' . ($state === '' ? 'no state' : $state));
	}

	pg_assert_same(null, ws_link_preview_card_fields(array_merge($row, array('title' => '  '))), 'no title, no card');
	pg_assert_same(null, ws_link_preview_card_fields(array_merge($row, array('url' => 'javascript:alert(1)'))), 'only a web address');
	pg_assert_same(null, ws_link_preview_card_fields(null), 'no row');
	pg_assert_same(array('url' => 'https://example.com/a', 'title' => 'T', 'description' => '', 'site_name' => ''), ws_link_preview_card_fields(array('url' => 'https://example.com/a', 'title' => 'T', 'state' => 'shown')), 'empty strings for what the page did not say');

	// The screen's card is drawn from the same fields.
	pg_assert_same('', ws_link_preview_card_html(array_merge($row, array('state' => 'removed'))), 'a removed card is not drawn');
	pg_assert_true(strpos(ws_link_preview_card_html($row), 'A &lt;b&gt;title&lt;/b&gt; &amp; more') !== false, 'the screen escapes the same title');
}

function test_workspace_link_preview_api_off_without_the_switch()
{
	// The test runner has no database: the switch reads off and no message
	// read over the API carries a card.
	pg_assert_same(array(), ws_link_preview_card_rows_map(array(1, 2, 3)), 'no fields while off');
	pg_assert_same(array(), ws_link_preview_card_rows_map(array()), 'no ids');
}

function test_workspace_link_preview_api_wiring()
{
	$root = PG_FUNCTIONS_DIR;
	$resources = file_get_contents($root . '/includes/workspace/api_resources.php');
	$api = file_get_contents($root . '/includes/workspace/api.php');

	pg_assert_true(strpos($resources, "'link_preview'   => ws_api_message_link_preview(") !== false, 'the message carries the card');
	pg_assert_true(strpos($resources, "'link_preview'   => 'WorkspaceMessageLinkPreview?'") !== false, 'declared nullable');
	pg_assert_true(strpos($api, "'WorkspaceMessageLinkPreview' => 'ws_api_message_link_preview_schema'") !== false, 'the object is named');
	pg_assert_true(strpos($resources, 'ws_link_preview_card_rows_map($ids)') !== false, 'a page reads its cards in one query');
}

function test_workspace_link_preview_fields_charset_alias()
{
	// The charset is named only in the Content-Type header, by a name that is
	// an alias of mbstring's (Shift_JIS for SJIS, gbk for CP936, big5 for
	// BIG-5, cp1252 for Windows-1252).
	foreach (array(
		array('SJIS', 'Shift_JIS', '日本語のページ'),
		array('CP936', 'gbk', '中文页面'),
		array('BIG-5', 'big5', '中文頁面'),
		array('Windows-1252', 'cp1252', 'Café “quoted”'),
	) as $case) {
		$body = mb_convert_encoding('<html><head><title>' . $case[2] . '</title></head></html>', $case[0], 'UTF-8');
		pg_assert_same($case[2], ws_link_preview_fields($body, 'https://example.com/', 'text/html; charset=' . $case[1])['title'], 'a page in ' . $case[1]);
	}

	pg_assert_same('Plain', ws_link_preview_fields('<title>Plain</title>', 'https://example.com/', 'text/html; charset=no-such-charset')['title'], 'an unknown charset is read as it is');
}
