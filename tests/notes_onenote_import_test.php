<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for OneNote pages brought in as notes through Microsoft Graph
 * (includes/workspace/notes_onenote.php): the lists of notebooks, sections
 * and pages, paging through @odata.nextLink, the note a page's HTML becomes
 * (title, body, dates, its pictures as files), the states of a site or a
 * person not connected, and a throttled answer (Retry-After).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_io.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_import.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_enex.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_html.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_onenote.php');

// An answer as pg_connect_api() gives it.
function notes_onenote_import_test_answer($status, $data = null, $headers = array(), $body = null)
{
	$body = ($body !== null) ? $body : (($data !== null) ? json_encode($data) : '');

	return array(
		'ok'      => ($status >= 200) && ($status < 300),
		'status'  => $status,
		'body'    => $body,
		'data'    => json_decode($body, true),
		'error'   => '',
		'headers' => $headers,
	);
}

// A PNG's first bytes: enough for the signature check.
function notes_onenote_import_test_png()
{
	return "\x89PNG\r\n\x1A\n" . str_repeat("\0", 24);
}

function test_notes_onenote_import_state()
{
	$missing = ws_note_onenote_state(false, 0);

	pg_assert_same('missing', $missing['state'], 'no Microsoft application on the site');
	pg_assert_true($missing['message'] !== '', 'says so');

	$not = ws_note_onenote_state(true, 0);

	pg_assert_same('not_connected', $not['state'], 'the person has connected no account');
	pg_assert_true($not['message'] !== '', 'a line pointing to Connect');
	pg_assert_same('microsoft', $not['connect']['provider'], 'the button connects a Microsoft account');
	pg_assert_same('notes', $not['connect']['purpose'], 'for the notes purpose');
	pg_assert_same('person', $not['connect']['owner'], 'as the person\'s own');

	$ready = ws_note_onenote_state(true, 12);

	pg_assert_same('ready', $ready['state'], 'connected: ready');
	pg_assert_same(null, $ready['connect'], 'no Connect button then');
}

function test_notes_onenote_import_ids_and_addresses()
{
	pg_assert_same('0-8f2a1b!12', ws_note_onenote_id('0-8f2a1b!12'), 'an id as Graph gives it');
	pg_assert_same('', ws_note_onenote_id('../me/messages'), 'a path is no id');
	pg_assert_same('', ws_note_onenote_id(array('x')), 'nor is a list');

	pg_assert_true(ws_note_onenote_graph_url('https://graph.microsoft.com/v1.0/me/onenote/sections?$skip=100'), 'a Graph address');
	pg_assert_false(ws_note_onenote_graph_url('http://graph.microsoft.com/v1.0/me'), 'plain http is not');
	pg_assert_false(ws_note_onenote_graph_url('https://graph.microsoft.com.evil.test/v1.0/me'), 'another host is not');
	pg_assert_false(ws_note_onenote_graph_url('https://user@graph.microsoft.com/v1.0/me'), 'nor one carrying a user');
}

function test_notes_onenote_import_list_paging()
{
	$first = 'https://graph.microsoft.com/v1.0/me/onenote/sections';
	$second = 'https://graph.microsoft.com/v1.0/me/onenote/sections?$skip=2';
	$asked = array();
	$get = function ($url) use ($first, $second, &$asked) {
		$asked[] = $url;

		if ($url === $first) {
			return array('ok' => true, 'error' => '', 'wait' => 0, 'data' => array('value' => array(array('id' => 'a'), array('id' => 'b')), '@odata.nextLink' => $second));
		}

		return array('ok' => true, 'error' => '', 'wait' => 0, 'data' => array('value' => array(array('id' => 'c'))));
	};

	$list = ws_note_onenote_list($get, $first, 100);

	pg_assert_true($list['ok'], 'two pages of results');
	pg_assert_same(array('a', 'b', 'c'), array_column($list['items'], 'id'), 'the next link is followed');
	pg_assert_same(array($first, $second), $asked, 'each address once');
	pg_assert_false($list['more'], 'nothing left');

	$capped = ws_note_onenote_list($get, $first, 2);

	pg_assert_same(2, count($capped['items']), 'cut at the list\'s limit');
	pg_assert_true($capped['more'], 'and says there was more');

	$outside = function ($url) {
		return array('ok' => true, 'error' => '', 'wait' => 0, 'data' => array('value' => array(array('id' => 'a')), '@odata.nextLink' => 'https://evil.test/next'));
	};
	$list = ws_note_onenote_list($outside, $first, 100);

	pg_assert_same(1, count($list['items']), 'a next link off Graph is never followed');

	$failing = function ($url) {
		return array('ok' => false, 'error' => 'Busy', 'wait' => 30, 'data' => null);
	};
	$list = ws_note_onenote_list($failing, $first, 100);

	pg_assert_false($list['ok'], 'a failed call fails the list');
	pg_assert_same('Busy', $list['error'], 'with its words');
}

function test_notes_onenote_import_notebooks_and_pages()
{
	$sections = array(
		array('id' => '1-s2', 'displayName' => 'Ideas', 'lastModifiedDateTime' => '2026-10-02T08:00:00Z', 'parentNotebook' => array('id' => '1-nb', 'displayName' => 'Work')),
		array('id' => '1-s1', 'displayName' => 'Meetings', 'parentNotebook' => array('id' => '1-nb', 'displayName' => 'Work')),
		array('id' => '0-s9', 'displayName' => 'Recipes', 'parentNotebook' => array('id' => '0-nb', 'displayName' => 'Home')),
		array('id' => '../x', 'displayName' => 'Broken', 'parentNotebook' => array('id' => '0-nb', 'displayName' => 'Home')),
		array('id' => '0-s8', 'displayName' => 'Loose'),
	);
	$notebooks = ws_note_onenote_notebooks($sections);

	pg_assert_same(array('Home', 'Work'), array_column($notebooks, 'name'), 'notebooks by name; a section without its notebook is left out');
	pg_assert_same(array('Ideas', 'Meetings'), array_column($notebooks[1]['sections'], 'name'), 'the sections of each, by name');
	pg_assert_same(array('0-s9'), array_column($notebooks[0]['sections'], 'id'), 'an id that is not one is left out');

	$pages = ws_note_onenote_pages(array(
		array('id' => '1-p1', 'title' => ' Weekly  sync ', 'createdDateTime' => '2026-09-01T10:00:00Z', 'lastModifiedDateTime' => '2026-10-01T09:30:00Z'),
		array('id' => '1-p2', 'title' => ''),
		array('id' => '1-p1', 'title' => 'Again'),
		array('title' => 'No id'),
	));

	pg_assert_same(2, count($pages), 'pages with an id, each once');
	pg_assert_same('Weekly sync', $pages[0]['title'], 'the title on one line');
	pg_assert_same(gmmktime(9, 30, 0, 10, 1, 2026), $pages[0]['when'], 'when it last changed');
	pg_assert_same('', $pages[1]['title'], 'an untitled page keeps an empty title for the screen to name');
}

function test_notes_onenote_import_page_to_note()
{
	$picture = 'https://graph.microsoft.com/v1.0/users(\'x\')/onenote/resources/0-abc!1-def/$value';
	$html = '<html><head><title>Weekly sync</title><meta name="created" content="2026-09-01T10:00:00.0000000" /></head>'
		. '<body data-absolute-enabled="true"><div style="position:absolute;left:48px;top:115px">'
		. '<p>Agreed on <b>Friday</b>.</p>'
		. '<img src="' . str_replace('$value', '$value', $picture) . '" data-src-type="image/png" width="200" />'
		. '<img src="https://graph.microsoft.com/v1.0/users(\'x\')/onenote/resources/0-gone!1/$value" />'
		. '<img src="https://example.test/pic.png" alt="web" />'
		. '<script>alert(1)</script></div></body></html>';

	pg_assert_same(array($picture, 'https://graph.microsoft.com/v1.0/users(\'x\')/onenote/resources/0-gone!1/$value'), ws_note_onenote_image_urls($html), 'the page\'s own pictures, not web ones');

	$meta = array('id' => '1-p1', 'title' => 'Weekly sync', 'createdDateTime' => '2026-09-01T10:00:00Z', 'lastModifiedDateTime' => '2026-10-01T09:30:00Z');
	$note = ws_note_onenote_note($meta, $html, array($picture => notes_onenote_import_test_png()), 2);

	pg_assert_true($note['ok'], 'a page is read');
	pg_assert_same('Weekly sync', $note['title'], 'its title');
	pg_assert_same(gmmktime(10, 0, 0, 9, 1, 2026), $note['created'], 'created');
	pg_assert_same(gmmktime(9, 30, 0, 10, 1, 2026), $note['updated'], 'last changed');
	pg_assert_contains('Agreed on **Friday**.', $note['body'], 'the words and their marks');
	pg_assert_contains('attachments/2/image-1.png', $note['body'], 'the picture linked in the note\'s own folder');
	pg_assert_false(strpos($note['body'], 'onenote/resources') !== false, 'a picture not downloaded leaves no Graph address');
	pg_assert_false(strpos($note['body'], 'alert') !== false, 'no script');
	pg_assert_same(1, count($note['files']), 'one picture kept');
	pg_assert_same('image/png', $note['files'][0]['mime'], 'as a PNG');

	$file = ws_note_onenote_file($note, '1-p1');

	pg_assert_contains('title: "Weekly sync"', $file, 'the staged file carries the title');
	pg_assert_contains('id: "1-p1"', $file, 'and the page id it is known by');
	pg_assert_contains('updated: 2026-10-01T09:30:00Z', $file, 'and its dates');

	$parsed = ws_note_import_parse($file, 'Weekly sync.md');

	pg_assert_same('Weekly sync', $parsed['title'], 'read back by the file import');
	pg_assert_same('1-p1', $parsed['source'], 'with the page id as its source');

	$empty = ws_note_onenote_note(array('id' => '1-p2', 'title' => ''), '<html><body><div></div></body></html>', array(), 1);

	pg_assert_false($empty['ok'], 'an empty page is no note');
}

function test_notes_onenote_import_throttled_and_refused()
{
	$url = 'https://graph.microsoft.com/v1.0/me/onenote/notebooks';
	$calls = 0;
	$slept = array();
	$sleep = function ($seconds) use (&$slept) {
		$slept[] = $seconds;
	};
	$busy_once = function ($id, $method, $address, $options, $scopes) use (&$calls) {
		$calls++;

		return ($calls === 1)
			? notes_onenote_import_test_answer(429, array('error' => array('code' => 'TooManyRequests')), array('retry-after' => '2'))
			: notes_onenote_import_test_answer(200, array('value' => array()));
	};
	$answer = ws_note_onenote_get(5, $url, 1048576, $busy_once, $sleep);

	pg_assert_true($answer['ok'], 'a short Retry-After is waited out and the call repeated');
	pg_assert_same(2, $calls, 'once');
	pg_assert_same(array(2), $slept, 'for the seconds Graph asked');

	$calls = 0;
	$slept = array();
	$busy = function ($id, $method, $address, $options, $scopes) use (&$calls) {
		$calls++;

		return notes_onenote_import_test_answer(503, null, array('retry-after' => '120'));
	};
	$answer = ws_note_onenote_get(5, $url, 1048576, $busy, $sleep);

	pg_assert_false($answer['ok'], 'a long Retry-After is not waited out');
	pg_assert_same(120, $answer['wait'], 'the wait is passed on');
	pg_assert_same(1, $calls, 'no second call');
	pg_assert_same(array(), $slept, 'and no sleep');
	pg_assert_contains('120', $answer['error'], 'the person is told how long');

	pg_assert_same(30, ws_note_onenote_retry_after(array('status' => 429, 'headers' => array())), 'no header: half a minute');
	pg_assert_same(90, ws_note_onenote_retry_after(array('status' => 429, 'headers' => array('retry-after' => gmdate('D, d M Y H:i:s', 1000090) . ' GMT')), 1000000), 'a date in the header');

	$refused = function ($id, $method, $address, $options, $scopes) {
		return notes_onenote_import_test_answer(401, array('error' => array('code' => 'InvalidAuthenticationToken', 'message' => 'Expired')));
	};
	$answer = ws_note_onenote_get(5, $url, 1048576, $refused, $sleep);

	pg_assert_false($answer['ok'], 'a token refused after its renewal');
	pg_assert_true($answer['reauth'], 'asks for the account to be connected again');

	$no_token = function ($id, $method, $address, $options, $scopes) {
		return array('ok' => false, 'status' => 0, 'body' => '', 'data' => null, 'error' => 'The account has to be connected again.');
	};
	$answer = ws_note_onenote_get(5, $url, 1048576, $no_token, $sleep);

	pg_assert_false($answer['ok'], 'no token at all');
	pg_assert_same('The account has to be connected again.', $answer['error'], 'the connection layer\'s words');

	$denied = function ($id, $method, $address, $options, $scopes) {
		return notes_onenote_import_test_answer(403, array('error' => array('code' => '40004', 'message' => 'The user does not have access.')));
	};
	pg_assert_contains('The user does not have access.', ws_note_onenote_get(5, $url, 1048576, $denied, $sleep)['error'], 'Graph\'s own message');

	pg_assert_false(ws_note_onenote_get(5, 'https://evil.test/me', 1048576, $denied, $sleep)['ok'], 'an address off Graph is never called');
}
