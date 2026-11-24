<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure rules of an agent channel's share links
 * (includes/workspace/share_links.php) and their API answer
 * (includes/workspace/api_share_links.php): the end a link takes, its state,
 * the item it may point at, who made it, the sweep's cut-off and the routes;
 * a table or a checklist of a message's text (share_links_text.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Function definitions only: nothing in these files runs a query when loaded.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/approvals.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/approval_guests.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/share_links.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/share_links_text.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/blocks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/render.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_io.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/guests.php');

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_share_links.php');

// A link open until a later time, of a message that carries file 9.
function share_links_test_link($over = array())
{
	return array_merge(array(
		'id' => 5, 'channel_id' => 3, 'message_id' => 40, 'kind' => 'file', 'item_id' => 9,
		'created_by_kind' => 'user', 'created_by_id' => 2, 'created_at' => 1000,
		'expires_at' => 5000, 'revoked_at' => 0, 'opened_at' => 0, 'opens' => 0,
	), $over);
}

function share_links_test_channel($over = array())
{
	return array_merge(array('id' => 3, 'kind' => 'public', 'archived_at' => 0, 'agent_channel' => 1), $over);
}

function share_links_test_message($over = array())
{
	return array_merge(array('id' => 40, 'channel_id' => 3, 'file_id' => 9, 'file_name' => 'plan.png', 'deleted_at' => 0), $over);
}

function test_share_link_days_default_to_seven_and_take_only_the_choices()
{
	pg_assert_same(array(1, 7, 30), ws_share_link_day_choices());
	pg_assert_same(7, ws_share_link_days_clean(null));
	pg_assert_same(7, ws_share_link_days_clean(''));
	pg_assert_same(1, ws_share_link_days_clean(1));
	pg_assert_same(30, ws_share_link_days_clean('30'));
	pg_assert_same(0, ws_share_link_days_clean(3));
	pg_assert_same(0, ws_share_link_days_clean(0));
	pg_assert_same(0, ws_share_link_days_clean('7 '));
	pg_assert_same(0, ws_share_link_days_clean(array(7)));
}

function test_share_link_expiry_is_never_without_an_end()
{
	pg_assert_same(1000 + 7 * 86400, ws_share_link_expiry(1000, 7));
	pg_assert_same(1000 + 86400, ws_share_link_expiry(1000, 0));
}

function test_share_link_token_has_the_approval_link_shape()
{
	pg_assert_true(ws_share_link_token_shape(str_repeat('aZ0_-', 8) . 'abc'));
	pg_assert_true(!ws_share_link_token_shape(str_repeat('a', 42)));
	pg_assert_true(!ws_share_link_token_shape(str_repeat('a', 42) . '=' ));
	pg_assert_true(!ws_share_link_token_shape(null));
	pg_assert_true(!ws_share_link_token_shape(array()));
}

function test_share_link_state_open_while_everything_stands()
{
	pg_assert_same('open', ws_share_link_state(share_links_test_link(), 2000, share_links_test_channel(), null, share_links_test_message(), false, true));
}

function test_share_link_state_revoked_before_expired_before_closed()
{
	$revoked = share_links_test_link(array('revoked_at' => 1500, 'expires_at' => 1200));

	pg_assert_same('revoked', ws_share_link_state($revoked, 2000, null, null, null, true, false));
	pg_assert_same('expired', ws_share_link_state(share_links_test_link(array('expires_at' => 2000)), 2000, null, null, null, true, false));
}

function test_share_link_state_closed_when_channel_message_or_item_no_longer_stands()
{
	$link = share_links_test_link();
	$channel = share_links_test_channel();
	$message = share_links_test_message();

	pg_assert_same('closed', ws_share_link_state($link, 2000, null, null, $message, false, true), 'channel gone');
	pg_assert_same('closed', ws_share_link_state($link, 2000, share_links_test_channel(array('archived_at' => 1)), null, $message, false, true), 'archived');
	pg_assert_same('closed', ws_share_link_state($link, 2000, $channel, null, null, false, true), 'message gone');
	pg_assert_same('closed', ws_share_link_state($link, 2000, $channel, null, share_links_test_message(array('deleted_at' => 1)), false, true), 'deleted');
	pg_assert_same('closed', ws_share_link_state($link, 2000, $channel, null, $message, true, true), 'before the clear floor');
	pg_assert_same('closed', ws_share_link_state($link, 2000, $channel, null, $message, false, false), 'item gone');
}

function test_share_link_state_of_a_discussion_follows_its_channel()
{
	$thread = share_links_test_channel(array('kind' => 'thread', 'agent_channel' => 0));

	pg_assert_same('open', ws_share_link_state(share_links_test_link(), 2000, $thread, share_links_test_channel(), share_links_test_message(), false, true));
	pg_assert_same('closed', ws_share_link_state(share_links_test_link(), 2000, $thread, share_links_test_channel(array('archived_at' => 9)), share_links_test_message(), false, true));
	pg_assert_same('closed', ws_share_link_state(share_links_test_link(), 2000, $thread, null, share_links_test_message(), false, true));
}

function test_share_link_item_is_the_file_the_message_carries()
{
	$message = share_links_test_message();

	pg_assert_true(ws_share_link_item_in($message, 'file', 9, true));
	pg_assert_true(!ws_share_link_item_in($message, 'file', 9, false), 'files row gone');
	pg_assert_true(!ws_share_link_item_in($message, 'file', 8, true), 'another file');
	pg_assert_true(!ws_share_link_item_in(share_links_test_message(array('file_id' => 0)), 'file', 0, true), 'no file');
	pg_assert_true(!ws_share_link_item_in($message, 'other', 9, true), 'an unknown kind');
	pg_assert_true(!ws_share_link_item_in(null, 'file', 9, true));
	pg_assert_same(array('file', 'note', 'table', 'list'), ws_share_link_kinds());
}

function test_share_link_note_is_only_one_of_the_message_channel()
{
	$message = share_links_test_message();
	$note = array('id' => 12, 'owner_channel_id' => 3, 'title' => 'Brief', 'body' => 'Text');

	pg_assert_true(ws_share_link_item_in($message, 'note', 12, $note), 'the channel\'s note whose card the message is');
	pg_assert_true(!ws_share_link_item_in($message, 'note', 12, array('owner_channel_id' => 0) + $note), 'a person\'s note shown in the channel');
	pg_assert_true(!ws_share_link_item_in($message, 'note', 12, array('owner_channel_id' => 4) + $note), 'a note of another channel');
	pg_assert_true(!ws_share_link_item_in($message, 'note', 13, $note), 'another note');
	pg_assert_true(!ws_share_link_item_in($message, 'note', 12, null), 'the note or its card is gone');
	pg_assert_true(!ws_share_link_item_in($message, 'note', 12, true), 'a file\'s answer is not a note');
	pg_assert_true(!ws_share_link_item_in($message, 'file', 9, $note), 'a note\'s row is not a file\'s');
}

function test_share_link_note_name_and_markdown()
{
	$note = array('id' => 12, 'owner_channel_id' => 3, 'title' => "  Release\nbrief ", 'body' => "First line\r\n\n- [ ] Second");

	pg_assert_same("Release\nbrief", ws_share_link_note_name($note), 'the stored title');
	pg_assert_same('First line', ws_share_link_note_name(array('title' => '') + $note), 'else its first line');
	pg_assert_same("# Release brief\n\nFirst line\n\n- [ ] Second\n", ws_share_link_note_markdown($note));
	pg_assert_same("First line\n\n- [ ] Second\n", ws_share_link_note_markdown(array('title' => '') + $note), 'no heading without a title');
}

function test_share_link_note_page_reads_tags_as_plain_names()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/share_links.php');

	foreach (array('function ws_share_link_note_page(', 'function ws_share_link_note_plain_tags(') as $name) {
		$start = strpos($source, $name);
		$body = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

		pg_assert_contains('ws_guest_refs(', $body, $name . ' names tags as the guest page does');
		pg_assert_true(strpos($body, 'ws_refs_resolve(') === false, $name . ' resolves no tag for a reader');
	}

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_share.php');

	pg_assert_contains("\$_GET['raw']", $page, 'raw=1 gives the Markdown');
	pg_assert_contains('ws_share_link_note_page(', $page);
}

function test_share_link_maker_is_the_application_of_a_key_else_the_person()
{
	pg_assert_same(array('kind' => 'app', 'id' => 12), ws_share_link_maker(2, 12));
	pg_assert_same(array('kind' => 'user', 'id' => 2), ws_share_link_maker(2, 0));

	$link = share_links_test_link();

	pg_assert_true(ws_share_link_made_by($link, array('kind' => 'user', 'id' => 2)));
	pg_assert_true(!ws_share_link_made_by($link, array('kind' => 'app', 'id' => 2)), 'an application with the person\'s id');
	pg_assert_true(!ws_share_link_made_by(share_links_test_link(array('created_by_id' => 0)), array('kind' => 'user', 'id' => 0)));
}

function test_share_links_sweep_keeps_thirty_days_after_the_end()
{
	pg_assert_same(100 * 86400 - 30 * 86400, ws_share_links_sweep_before(100 * 86400));
}

function test_share_link_api_present_never_carries_the_address()
{
	$out = ws_api_share_link_present(share_links_test_link(array('revoked_at' => 0, 'opened_at' => 0)), 'open', 'plan.png');

	pg_assert_same(array_keys(ws_api_share_link_schema()), array_keys($out));
	pg_assert_true(!array_key_exists('url', $out) && !array_key_exists('token_hash', $out));
	pg_assert_same(null, $out['revoked_at']);
	pg_assert_same(array('kind' => 'user', 'id' => 2), $out['created_by']);
	pg_assert_same('plan.png', $out['name']);
}

function test_share_link_routes_redact_the_address_and_dry_run_their_writes()
{
	$routes = array();

	foreach (ws_share_link_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	pg_assert_same(array('workspace.share_links.create', 'workspace.share_links.list', 'workspace.share_links.revoke'), array_keys($routes));
	pg_assert_same(array('url'), $routes['workspace.share_links.create']['idempotency_redact']);
	pg_assert_true(!empty($routes['workspace.share_links.create']['dry_run']));
	pg_assert_true(!empty($routes['workspace.share_links.revoke']['dry_run']));
	pg_assert_same('GET', $routes['workspace.share_links.list']['method']);

	// Every route is an MCP tool: an agent hands a link on without a card.
	foreach ($routes as $route) {
		pg_assert_true(!isset($route['mcp']) || ($route['mcp'] !== false), $route['id']);
	}
}

function test_share_link_open_is_counted_once_a_minute()
{
	pg_assert_true(ws_share_link_open_counts(share_links_test_link(), 2000), 'never opened');
	pg_assert_same(false, ws_share_link_open_counts(share_links_test_link(array('opened_at' => 1950)), 2000), 'within the minute');
	pg_assert_true(ws_share_link_open_counts(share_links_test_link(array('opened_at' => 1940)), 2000), 'a minute later');
}

function test_share_link_file_shows_a_picture_and_downloads_what_a_browser_could_run()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/files.php');

	$picture = ws_share_link_file_headers('plan.png');

	pg_assert_same('image/png', $picture['mime']);
	pg_assert_same(0, strpos($picture['disposition'], 'inline;'), 'a picture inline');

	foreach (array('page.html', 'logo.svg', 'notes.txt', 'run.js') as $name) {
		$headers = ws_share_link_file_headers($name);

		pg_assert_same('application/octet-stream', $headers['mime'], $name);
		pg_assert_same(0, strpos($headers['disposition'], 'attachment;'), $name);
	}

	pg_assert_contains("filename*=UTF-8''%C3%A7izim.pdf", ws_share_link_file_headers('çizim.pdf')['disposition'], 'a name in UTF-8');
}

function test_share_link_address_page_keeps_its_headers_404_and_limit()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_share.php');
	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	$start = strpos($waf, 'function waf_sensitive_scripts()');

	pg_assert_true(strpos(substr($waf, $start, strpos($waf, "\n}\n", $start) - $start), "'workspace_share.php'") !== false, 'counted on the sensitive allowance');
	pg_assert_contains('Pinegrap - Enterprise Website Platform', $page);
	pg_assert_contains("define('PG_NO_SESSION', true)", $page, 'no session, no cookie');
	pg_assert_contains("header('X-Robots-Tag: noindex, nofollow')", $page);
	pg_assert_contains("header('Referrer-Policy: no-referrer')", $page);
	pg_assert_contains("header('Cache-Control: private, no-store')", $page);
	pg_assert_contains('http_response_code(404)', $page, 'one 404 for every fault');
	pg_assert_contains('ws_share_link_count_open(', $page, 'an open is counted');
	pg_assert_contains('pg_send_nosniff()', $page);
}

function test_share_link_answer_counts_an_unknown_token_against_the_address()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/share_links.php');
	$start = strpos($source, 'function ws_share_link_answer(');
	$body = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	pg_assert_contains('ws_guest_offence()', $body, 'a wrong token is a failed try');
	pg_assert_contains("!== 'open'", $body, 'only an open link answers');
	pg_assert_contains('pg_file_withheld(', $body, 'a withheld file never leaves');
}

function test_share_link_line_names_who_the_item_and_the_end()
{
	$until = mktime(14, 30, 0, 12, 1, 2026);

	pg_assert_same('<@user:2> shared plan.png by link until 01.12.2026 14:30', ws_share_link_line(true, '<@user:2>', 'plan.png', $until));
	pg_assert_same('@coordinator stopped sharing plan.png', ws_share_link_line(false, '@coordinator', 'plan.png', $until));
	pg_assert_same('@a shared my plan.png by link until 01.12.2026 14:30', ws_share_link_line(true, '@a', "my\n  plan.png ", $until), 'the name stays on one line');
}

function test_share_link_item_mark_carries_the_end_and_never_the_address()
{
	$mark = ws_share_link_item_mark(share_links_test_link(array('expires_at' => mktime(9, 0, 0, 1, 2, 2027))), true);

	pg_assert_same(array('id' => 5, 'until_at' => mktime(9, 0, 0, 1, 2, 2027), 'until' => '02.01.2027 09:00', 'can_revoke' => true), $mark);
	pg_assert_same(false, ws_share_link_item_mark(share_links_test_link(), 0)['can_revoke']);
}

function test_share_link_kind_icon_names_each_kind()
{
	pg_assert_same('bi-file-earmark', ws_share_link_kind_icon('file'));
	pg_assert_same('bi-journal-text', ws_share_link_kind_icon('note'));
	pg_assert_same('bi-table', ws_share_link_kind_icon('table'));
	pg_assert_same('bi-list-check', ws_share_link_kind_icon('list'));
	pg_assert_same('bi-link-45deg', ws_share_link_kind_icon('other'), 'an unknown kind keeps the link icon');
}

function test_share_link_opened_words_count_the_opens()
{
	pg_assert_same('not opened yet', ws_share_link_opened_words(0));
	pg_assert_same('opened once', ws_share_link_opened_words(1));
	pg_assert_same('opened 4 times', ws_share_link_opened_words('4'));
}

function test_share_link_row_lists_who_the_end_and_the_opens_without_the_address()
{
	$until = mktime(9, 0, 0, 1, 2, 2027);
	$row = ws_share_link_row(share_links_test_link(array('expires_at' => $until, 'opens' => 3)), 'plan.png', 'Ayşe', true);

	pg_assert_same('plan.png', $row['name']);
	pg_assert_same('bi-file-earmark', $row['icon']);
	pg_assert_same('Ayşe · until 02.01.2027 09:00 · opened 3 times', $row['line']);
	pg_assert_same('02.01.2027 09:00', $row['until']);
	pg_assert_same(true, $row['can_revoke']);
	pg_assert_false(isset($row['url']) || isset($row['token_hash']), 'never the address');

	$unnamed = ws_share_link_row(share_links_test_link(array('expires_at' => $until, 'opens' => 0)), 'plan.png', '', 0);

	pg_assert_same('until 02.01.2027 09:00 · not opened yet', $unnamed['line'], 'an unknown maker leaves no empty part');
	pg_assert_same(false, $unnamed['can_revoke']);
}

function test_channel_share_links_are_in_the_channel_detail_and_the_head()
{
	$channels = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/channels.php');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_contains("\$brief['share_links'] = function_exists('ws_channel_share_links')", $channels);
	pg_assert_contains("var sharing = self.sharingButton(channel);", $js, 'the head draws the share mark');
	pg_assert_contains("item(t('sl_sharing'), 'bi-share', function () { self.showSharing(); }, 'ws-head-narrow');", $js, 'a menu entry on a phone');
	pg_assert_contains("sharingTitle.setAttribute('data-info-key', 'sharing');", $js, 'Channel info has its Sharing part');
}

// A message's text with a titled table, a table inside a block of code, two
// checklists and a second table.
function share_links_test_text()
{
	return implode("\n", array(
		'Plan for the week',
		'::: Release',
		'| Step | Who |',
		'|---|---|',
		'| Build | <@user:2> |',
		'| Ship | =1+1 |',
		'```',
		'| Code | Only |',
		'|---|---|',
		'- [ ] not a list',
		'```',
		'- [ ] Write',
		'- [x] Test',
		'',
		'Then:',
		'* [ ] Merge',
		'| A |',
		'|---|',
	));
}

function test_share_link_text_blocks_are_read_as_the_channel_draws_them()
{
	$blocks = ws_share_link_text_blocks(share_links_test_text());

	pg_assert_same(4, count($blocks), 'the table in a block of code is code');
	pg_assert_same(array('table', 1, 'Release'), array($blocks[0]['kind'], $blocks[0]['number'], $blocks[0]['title']));
	pg_assert_same(array(array('Step', 'Who'), array('Build', '<@user:2>'), array('Ship', '=1+1')), $blocks[0]['rows']);
	pg_assert_same(2, $blocks[0]['columns']);
	pg_assert_same(array('list', 1, ''), array($blocks[1]['kind'], $blocks[1]['number'], $blocks[1]['title']));
	pg_assert_same(array(array('index' => 0, 'checked' => false, 'text' => 'Write'), array('index' => 1, 'checked' => true, 'text' => 'Test')), $blocks[1]['items']);
	pg_assert_same(array('list', 2), array($blocks[2]['kind'], $blocks[2]['number']), 'a line between makes a second checklist');
	pg_assert_same(2, $blocks[2]['items'][0]['index'], 'items count through the whole message, as its ticks do');
	pg_assert_same(array('table', 2), array($blocks[3]['kind'], $blocks[3]['number']));
	pg_assert_same(array(), ws_share_link_text_blocks('Just words | and a bar'));
	pg_assert_same(2, ws_share_link_text_block(share_links_test_text(), 'list', 2)['number']);
	pg_assert_same(null, ws_share_link_text_block(share_links_test_text(), 'list', 3));
}

function test_share_link_text_item_is_its_number_in_the_message()
{
	$block = ws_share_link_text_block(share_links_test_text(), 'table', 2);
	$message = share_links_test_message(array('file_id' => 0, 'sender_kind' => 'app', 'body' => share_links_test_text()));

	pg_assert_true(ws_share_link_item_in($message, 'table', 2, $block));
	pg_assert_true(!ws_share_link_item_in($message, 'table', 1, $block), 'another table');
	pg_assert_true(!ws_share_link_item_in($message, 'list', 2, $block), 'a table is not a list');
	pg_assert_true(!ws_share_link_item_in($message, 'table', 2, null), 'written away');
	pg_assert_same(2, ws_share_link_text_row($message, 'table', 2)['number']);
	pg_assert_same(null, ws_share_link_text_row(array('sender_kind' => 'guest') + $message, 'table', 2), 'a guest\'s text has no tables');
	pg_assert_same(null, ws_share_link_text_row(array('sender_kind' => 'mail') + $message, 'list', 1), 'nor a mail');
}

function test_share_link_text_name_is_its_title_else_its_first_row()
{
	$blocks = ws_share_link_text_blocks(share_links_test_text());

	pg_assert_same('Release', ws_share_link_text_name_source($blocks[0]));
	pg_assert_same('Write', ws_share_link_text_name_source($blocks[1]));
	pg_assert_same('A', ws_share_link_text_name_source($blocks[3]));
	pg_assert_same('Step · Who', ws_share_link_text_name_source(array('title' => '') + $blocks[0]));
	pg_assert_same(80, mb_strlen(ws_share_link_text_name_cut(str_repeat('ş', 120))));
	pg_assert_same('Short', ws_share_link_text_name_cut(' Short '));
}

function test_share_link_table_csv_guards_cells_and_gives_a_formula_its_value()
{
	$block = ws_share_link_text_blocks(share_links_test_text())[0];
	$grid = array(2 => array(1 => array('formula' => true, 'display' => '2')));
	$plain = function ($text) {
		return str_replace('<@user:2>', '@Ayşe', $text);
	};

	pg_assert_same("\"Step\",\"Who\"\r\n\"Build\",\"'@Ayşe\"\r\n\"Ship\",\"2\"\r\n", ws_share_link_table_csv($block, $grid, $plain), 'a formula gives its value');
	pg_assert_same("\"Step\",\"Who\"\r\n\"Build\",\"'@Ayşe\"\r\n\"Ship\",\"'=1+1\"\r\n", ws_share_link_table_csv($block, null, $plain), 'a cell a spreadsheet would run is guarded');

	$short = array('rows' => array(array('A', 'B'), array('say "hi"')), 'columns' => 2);

	pg_assert_same("\"A\",\"B\"\r\n\"say \"\"hi\"\"\",\"\"\r\n", ws_share_link_table_csv($short, null, 'strval'), 'a missing cell is empty');
}

function test_share_link_list_markdown_and_page_take_the_ticks_given_since()
{
	$block = ws_share_link_text_blocks(share_links_test_text())[1];
	$checks = array(0 => array('checked' => true, 'name' => 'Ayşe', 'at' => '1 Dec'), 1 => array('checked' => false), 5 => array('checked' => true));

	pg_assert_same("- [x] Write\n- [ ] Test\n", ws_share_link_list_markdown($block, $checks, 'strval'));
	pg_assert_same("# Done\n\n- [ ] Write\n- [x] Test\n", ws_share_link_list_markdown(array('title' => 'Done') + $block, array(), 'strval'), 'as written without ticks');
	pg_assert_same(array(0 => $checks[0], 1 => $checks[1]), ws_share_link_list_checks($block, $checks));

	$second = ws_share_link_text_blocks(share_links_test_text())[2];

	pg_assert_same(array(0 => array('checked' => true)), ws_share_link_list_checks($second, array(2 => array('checked' => true))), 'numbered from the block\'s first item');
}

function test_share_link_text_shares_reach_the_screen_and_the_address()
{
	$messages = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$address = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_share.php');

	pg_assert_contains("'text_shares' => function_exists('ws_share_links_text_map')", $messages);
	pg_assert_contains("'text_shares' => (\$deleted || \$agent || (\$tag_card !== '')) ? array()", $messages, 'none where the text drawn is another');
	pg_assert_contains("items = items.concat(self.shareTextItems(message));", $js, 'the message menu offers them');
	pg_assert_contains("self.shareTextMarks(body, message);", $js, 'the drawn block gets the mark');
	pg_assert_contains("ws_share_link_table_raw(\$answer)", $address);
	pg_assert_contains("ws_share_link_list_raw(\$answer)", $address);
	pg_assert_contains("echo ws_share_link_text_page(\$answer);", $address);
}

// Taking back a link that had ended or closed already (expired, its item or
// message gone) writes no line: the item had stopped being shared, and its
// name may be gone with it.
function test_share_link_revoke_line_only_for_an_open_link()
{
	$until = mktime(14, 30, 0, 12, 1, 2026);

	pg_assert_same('@coordinator stopped sharing plan.png', ws_share_link_revoke_line('open', '@coordinator', 'plan.png', $until), 'an open link taken back says so');

	foreach (array('expired', 'closed', 'revoked') as $state) {
		pg_assert_same('', ws_share_link_revoke_line($state, '@coordinator', '', $until), 'a link ' . $state . ' writes no line');
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/share_links.php');
	$start = strpos($source, 'function ws_share_link_revoke(');
	$body = substr($source, $start, strpos($source, "\nfunction ", $start + 1) - $start);

	pg_assert_contains('ws_share_link_revoke_line(', $body, 'the take-back follows the rule');
	pg_assert_true(strpos($body, 'ws_share_link_read(') < strpos($body, 'UPDATE ws_share_links'), 'the state is read before the link is taken back');
}
