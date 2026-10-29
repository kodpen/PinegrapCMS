<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Notion pages brought in as notes through the notion MCP alias
 * (includes/workspace/notes_notion.php): which of the server's tools search
 * and fetch, the argument names read from their schemas, the picker list a
 * search result gives, the note a fetched page becomes (title without its
 * page id, Markdown, dates, tags), the Markdown file staged for it, and the
 * states of a server that is missing, not connected or lacks a tool.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_notion.php');

// A tool as pg_mcp_client_tool_normalise() keeps it.
function notes_notion_import_test_tool($name, $properties, $required = array())
{
	return array(
		'name'   => $name,
		'title'  => '',
		'schema' => json_encode(array('type' => 'object', 'properties' => $properties, 'required' => $required)),
		'hints'  => array('readOnlyHint' => true),
	);
}

// A tools/call result whose one text item is $data as JSON.
function notes_notion_import_test_result($data)
{
	return array('content' => array(array('type' => 'text', 'text' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))));
}

function notes_notion_import_test_tools()
{
	return array(
		'notion-search'      => notes_notion_import_test_tool('notion-search', array('query' => array('type' => 'string'), 'query_type' => array('type' => 'string')), array('query')),
		'notion-fetch'       => notes_notion_import_test_tool('notion-fetch', array('id' => array('type' => 'string')), array('id')),
		'notion-create-pages' => notes_notion_import_test_tool('notion-create-pages', array('pages' => array('type' => 'array'))),
	);
}

function test_notes_notion_import_tool_pick()
{
	$tools = notes_notion_import_test_tools();

	pg_assert_same('notion-search', ws_note_notion_tool_pick($tools, 'search')['name'], 'the search tool by its name');
	pg_assert_same('notion-fetch', ws_note_notion_tool_pick($tools, 'fetch')['name'], 'the fetch tool by its name');

	$plain = array('search' => notes_notion_import_test_tool('search', array('query' => array('type' => 'string'))), 'fetch' => notes_notion_import_test_tool('fetch', array('id' => array('type' => 'string'))));

	pg_assert_same('search', ws_note_notion_tool_pick($plain, 'search')['name'], 'a server that names it plainly');
	pg_assert_same('fetch', ws_note_notion_tool_pick($plain, 'fetch')['name'], 'and fetch too');

	unset($tools['notion-fetch']);

	pg_assert_same(null, ws_note_notion_tool_pick($tools, 'fetch'), 'no fetch tool: none');
	pg_assert_same(null, ws_note_notion_tool_pick(array(), 'search'), 'no tools at all');
}

function test_notes_notion_import_argument_name()
{
	$tools = notes_notion_import_test_tools();

	pg_assert_same('query', ws_note_notion_argument_name($tools['notion-search'], array('query', 'q')), 'the search words go in query');
	pg_assert_same('id', ws_note_notion_argument_name($tools['notion-fetch'], array('id', 'url')), 'the page goes in id');

	$by_url = notes_notion_import_test_tool('fetch', array('url' => array('type' => 'string')), array('url'));

	pg_assert_same('url', ws_note_notion_argument_name($by_url, array('id', 'url')), 'a fetch that takes an address');

	$other = notes_notion_import_test_tool('search', array('limit' => array('type' => 'integer'), 'text' => array('type' => 'string')), array('text'));

	pg_assert_same('text', ws_note_notion_argument_name($other, array('query', 'q')), 'else the first required string');
	pg_assert_same('', ws_note_notion_argument_name(notes_notion_import_test_tool('search', array()), array('query')), 'a schema with nothing to fill');
}

function test_notes_notion_import_page_id_and_title()
{
	pg_assert_same('2a1b3c4d5e6f40718293a4b5c6d7e8f9', ws_note_notion_page_id('2a1b3c4d-5e6f-4071-8293-a4b5c6d7e8f9'), 'an id with dashes');
	pg_assert_same('2a1b3c4d5e6f40718293a4b5c6d7e8f9', ws_note_notion_page_id('https://www.notion.so/Meeting-notes-2A1B3C4D5E6F40718293A4B5C6D7E8F9?pvs=4'), 'an address');
	pg_assert_same('', ws_note_notion_page_id('not a page'), 'no id');

	pg_assert_same('Meeting notes', ws_note_notion_title('Meeting notes 2a1b3c4d5e6f40718293a4b5c6d7e8f9'), 'the 32-hex id after a space');
	pg_assert_same('Meeting notes', ws_note_notion_title(' Meeting-notes-2a1b3c4d5e6f40718293a4b5c6d7e8f9 '), 'or after a dash, as an address writes it');
	pg_assert_same('Plan 2026', ws_note_notion_title('Plan 2026'), 'a title without one');
}

function test_notes_notion_import_search_pages()
{
	$result = notes_notion_import_test_result(array(
		'type'    => 'workspace_search',
		'results' => array(
			array('id' => '2a1b3c4d-5e6f-4071-8293-a4b5c6d7e8f9', 'title' => 'Meeting notes 2a1b3c4d5e6f40718293a4b5c6d7e8f9', 'url' => 'https://www.notion.so/2a1b3c4d5e6f40718293a4b5c6d7e8f9', 'type' => 'page', 'timestamp' => '2026-10-01T09:30:00.000Z'),
			array('id' => '3b2c3c4d5e6f40718293a4b5c6d7e8f9', 'title' => 'Tasks', 'url' => 'https://www.notion.so/3b2c3c4d5e6f40718293a4b5c6d7e8f9', 'type' => 'database'),
			array('id' => '4c3d3c4d5e6f40718293a4b5c6d7e8f9', 'title' => '', 'url' => 'https://www.notion.so/4c3d3c4d5e6f40718293a4b5c6d7e8f9', 'type' => 'page'),
			array('id' => '2a1b3c4d5e6f40718293a4b5c6d7e8f9', 'title' => 'Meeting notes again', 'type' => 'page'),
			array('title' => 'No id'),
		),
	));

	$pages = ws_note_notion_search_pages($result);

	pg_assert_same(2, count($pages), 'pages only, each once, with an id');
	pg_assert_same('2a1b3c4d5e6f40718293a4b5c6d7e8f9', $pages[0]['id'], 'the id without dashes');
	pg_assert_same('Meeting notes', $pages[0]['title'], 'the title without its id');
	pg_assert_same(gmmktime(9, 30, 0, 10, 1, 2026), $pages[0]['when'], 'the time the search gives');
	pg_assert_same('', $pages[1]['title'], 'an untitled page keeps an empty title for the screen to name');

	$structured = array('content' => array(), 'structuredContent' => array('results' => array(array('id' => '5d4e3c4d5e6f40718293a4b5c6d7e8f9', 'title' => 'Ideas', 'object' => 'page'))));

	pg_assert_same('Ideas', ws_note_notion_search_pages($structured)[0]['title'], 'results given as structured content');
	pg_assert_same(array(), ws_note_notion_search_pages(array('content' => array(array('type' => 'text', 'text' => 'No results')))), 'words, not results');
}

function test_notes_notion_import_page_to_note()
{
	$text = "Here is the result of \"view\" for the Page with URL https://www.notion.so/2a1b3c4d5e6f40718293a4b5c6d7e8f9 as of 2026-10-20:\n"
		. "<page url=\"https://www.notion.so/2a1b3c4d5e6f40718293a4b5c6d7e8f9\">\n"
		. "<ancestor-path>\n<parent-page url=\"https://www.notion.so/9f\" title=\"Team\"/>\n</ancestor-path>\n"
		. "<properties>\n" . json_encode(array('Name' => 'Meeting notes', 'Tags' => array('weekly', 'team'), 'Created time' => '2026-09-01T08:00:00.000Z', 'Last edited time' => '2026-10-02T10:15:00.000Z')) . "\n</properties>\n"
		. "<content>\n# Agenda\n- Budget\n<empty-block/>\nSee <mention-page url=\"https://www.notion.so/7e\">Roadmap</mention-page> and <page url=\"https://www.notion.so/8f\">Minutes</page>.\n<callout icon=\"💡\">\n\tKeep it short\n</callout>\n</content>\n</page>";

	$page = ws_note_notion_page(notes_notion_import_test_result(array(
		'metadata' => array('type' => 'page'),
		'title'    => 'Meeting notes 2a1b3c4d5e6f40718293a4b5c6d7e8f9',
		'url'      => 'https://www.notion.so/2a1b3c4d5e6f40718293a4b5c6d7e8f9',
		'text'     => $text,
	)), '');

	pg_assert_same(true, $page['ok'], 'a page is read');
	pg_assert_same('2a1b3c4d5e6f40718293a4b5c6d7e8f9', $page['id'], 'its id from its address');
	pg_assert_same('Meeting notes', $page['title'], 'its title without the id');
	pg_assert_same(array('weekly', 'team'), $page['tags'], 'its tags');
	pg_assert_same(gmmktime(8, 0, 0, 9, 1, 2026), $page['created'], 'created');
	pg_assert_same(gmmktime(10, 15, 0, 10, 2, 2026), $page['updated'], 'last edited');
	pg_assert_same("# Agenda\n- Budget\n\nSee [Roadmap](https://www.notion.so/7e) and [Minutes](https://www.notion.so/8f).\nKeep it short", $page['body'], 'the content as Markdown, Notion\'s own tags taken out');

	$file = ws_note_notion_file($page);

	pg_assert_true(strpos($file, "title: Meeting notes\n") !== false, 'the file carries the title');
	pg_assert_true(strpos($file, "created: 2026-09-01T08:00:00Z\n") !== false, 'and created');
	pg_assert_true(strpos($file, "updated: 2026-10-02T10:15:00Z\n") !== false, 'and updated');
	pg_assert_true(strpos($file, "id: 2a1b3c4d5e6f40718293a4b5c6d7e8f9\n") !== false, 'and the page id it is known by');
	pg_assert_true(strpos($file, "tags:\n  - weekly\n  - team\n") !== false, 'and the tags');

	$parsed = ws_note_import_parse(ws_note_import_text($file), 'Meeting notes.md', 0);

	pg_assert_same('Meeting notes', $parsed['title'], 'the import reads the title back');
	pg_assert_same(gmmktime(8, 0, 0, 9, 1, 2026), $parsed['created'], 'and the dates');
	pg_assert_same('2a1b3c4d5e6f40718293a4b5c6d7e8f9', $parsed['source'], 'and the id');
}

function test_notes_notion_import_page_plain_and_refused()
{
	$plain = ws_note_notion_page(array('content' => array(array('type' => 'text', 'text' => "# Ideas\n\nOne"))), '2a1b3c4d5e6f40718293a4b5c6d7e8f9');

	pg_assert_same(true, $plain['ok'], 'plain Markdown is taken as the text');
	pg_assert_same('2a1b3c4d5e6f40718293a4b5c6d7e8f9', $plain['id'], 'the id asked for when the answer names none');
	pg_assert_same('Ideas', $plain['title'], 'the first heading as the title');
	pg_assert_same('One', $plain['body'], 'the heading taken out of the text');
	pg_assert_same(0, $plain['created'], 'no dates');

	$database = ws_note_notion_page(notes_notion_import_test_result(array('metadata' => array('type' => 'database'), 'title' => 'Tasks', 'text' => '<database></database>')), '');

	pg_assert_same(false, $database['ok'], 'a database is no note');

	$empty = ws_note_notion_page(array('content' => array()), '2a1b3c4d5e6f40718293a4b5c6d7e8f9');

	pg_assert_same(false, $empty['ok'], 'an empty answer is no note');
}

function test_notes_notion_import_state()
{
	$tools = notes_notion_import_test_tools();
	$server = array('id' => 4, 'alias' => 'notion', 'auth_kind' => 'oauth', 'status' => 'active');

	pg_assert_same('missing', ws_note_notion_state(null, false, array())['state'], 'no notion server on the site');
	pg_assert_same('missing', ws_note_notion_state(array('status' => 'off') + $server, true, $tools)['state'], 'a server turned off is as good as none');
	pg_assert_same('not_connected', ws_note_notion_state($server, false, $tools)['state'], 'the person has not connected their account');
	pg_assert_true(ws_note_notion_state($server, false, $tools)['message'] !== '', 'and is told where to connect it');

	unset($tools['notion-fetch']);

	$no_tool = ws_note_notion_state($server, true, $tools);

	pg_assert_same('no_tool', $no_tool['state'], 'connected, but the fetch tool is missing or off');
	pg_assert_true(strpos($no_tool['message'], 'fetch') !== false, 'the message names the tool');

	$ready = ws_note_notion_state($server, true, notes_notion_import_test_tools());

	pg_assert_same('ready', $ready['state'], 'connected with both tools');
	pg_assert_same('notion-search', $ready['search']['name'], 'the search tool');
	pg_assert_same('notion-fetch', $ready['fetch']['name'], 'the fetch tool');
}
