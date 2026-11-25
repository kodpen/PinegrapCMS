<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "Preview on a phone" on the server
 * (includes/fn/prototype_phone.php): what marks a share link as the phone
 * link, which link is found again, the link a press makes, the signed
 * changed-since address (prototype.php?watch=1) with its bounds and its one
 * read, and the token kept out of that address and of its answers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_links.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_phone.php');

function prototype_phone_test_key()
{
	return str_repeat('k', 32);
}

// A phone link's row as pg_proto_link_issue() writes it, laid over the
// given fields.
function prototype_phone_test_row($over = array())
{
	return array_merge(array(
		'id' => 7, 'style_id' => 3, 'bundle_id' => 0, 'start_page_id' => 11,
		'token_hash' => str_repeat('a', 64), 'token_secret' => '', 'token_hint' => 'AbCdEf',
		'audience' => 'anyone', 'password_hash' => null, 'password_failures' => 0, 'password_locked_until' => 0,
		'created_by' => 5, 'created_at' => 1900000000, 'expires_at' => 1900000000 + 28800,
		'revoked_at' => 0, 'revoked_by' => 0, 'uses' => 0, 'last_used_at' => 0,
		'allow_comments' => 0, 'approval' => 0,
	), $over);
}

function test_prototype_phone_link_is_marked_by_its_eight_hours()
{
	pg_assert_same(28800, pg_proto_link_phone_seconds());
	pg_assert_true(pg_proto_link_is_phone(prototype_phone_test_row()));
	pg_assert_true(pg_proto_link_is_phone(prototype_phone_test_row(array('audience' => 'team'))), 'a team phone link');

	// Eight hours is none of the durations "Share prototype" offers, so no
	// link made by hand carries the mark.
	$offered = array(3600, 86400, 259200, 604800, 2592000, 7776000);
	pg_assert_false(in_array(pg_proto_link_phone_seconds(), $offered, true));

	foreach ($offered as $seconds) {
		pg_assert_false(pg_proto_link_is_phone(prototype_phone_test_row(array('expires_at' => 1900000000 + $seconds))), $seconds . ' s');
	}

	pg_assert_false(pg_proto_link_is_phone(prototype_phone_test_row(array('expires_at' => 0))), 'runs for good');
	pg_assert_false(pg_proto_link_is_phone(prototype_phone_test_row(array('password_hash' => '$2y$10$x'))), 'a password');
	pg_assert_false(pg_proto_link_is_phone(prototype_phone_test_row(array('allow_comments' => 1))), 'comments');
	pg_assert_false(pg_proto_link_is_phone(prototype_phone_test_row(array('approval' => 1))), 'approval');
	pg_assert_false(pg_proto_link_is_phone(prototype_phone_test_row(array('bundle_id' => 4, 'style_id' => 0))), 'a bundle');
	pg_assert_false(pg_proto_link_is_phone(null));

	// A row read before the bundle and approval columns came is still read.
	$old = prototype_phone_test_row();
	unset($old['bundle_id'], $old['approval'], $old['allow_comments']);
	pg_assert_true(pg_proto_link_is_phone($old), 'older row');
}

function test_prototype_phone_plan_makes_an_eight_hour_link_without_a_password()
{
	$now = 1900000000;
	$link = pg_proto_phone_plan('anyone', 12, array(11, 12), $now);

	pg_assert_same('anyone', $link['audience']);
	pg_assert_same(12, $link['start_page_id']);
	pg_assert_same($now, $link['created_at']);
	pg_assert_same($now + 28800, $link['expires_at']);
	pg_assert_same('', $link['password']);
	pg_assert_same(0, $link['allow_comments']);

	// What it writes carries the mark.
	pg_assert_true(pg_proto_link_is_phone(array_merge(prototype_phone_test_row(), array('expires_at' => $link['expires_at'], 'created_at' => $link['created_at']))));

	pg_assert_same('team', pg_proto_phone_plan('team', 11, array(11), $now)['audience']);
	pg_assert_same(0, pg_proto_phone_plan('anyone', 99, array(11), $now)['start_page_id'], 'not a draft: the first draft');
	pg_assert_same(null, pg_proto_phone_plan('everyone', 11, array(11), $now), 'unknown audience');
	pg_assert_same(null, pg_proto_phone_plan('anyone', 11, array(), $now), 'no drafts');
}

function test_prototype_phone_pick_finds_the_persons_live_phone_link_again()
{
	$now = 1900000100;
	$rows = array(
		prototype_phone_test_row(array('id' => 9, 'created_by' => 6)),
		prototype_phone_test_row(array('id' => 8, 'audience' => 'team')),
		prototype_phone_test_row(array('id' => 7)),
		prototype_phone_test_row(array('id' => 6)),
	);

	pg_assert_same(7, (int) pg_proto_phone_pick($rows, 5, 'anyone', $now)['id'], 'newest of the person, same audience');
	pg_assert_same(8, (int) pg_proto_phone_pick($rows, 5, 'team', $now)['id']);
	pg_assert_same(null, pg_proto_phone_pick($rows, 4, 'anyone', $now), 'nobody else\'s');

	$gone = array(
		prototype_phone_test_row(array('revoked_at' => $now - 5)),
		prototype_phone_test_row(array('created_at' => $now - 28800 - 10, 'expires_at' => $now - 10)),
		prototype_phone_test_row(array('expires_at' => 1900000000 + 86400)),
	);
	pg_assert_same(null, pg_proto_phone_pick($gone, 5, 'anyone', $now), 'revoked, expired or made by hand');
}

function test_prototype_phone_watch_address_is_signed_and_carries_no_token()
{
	$key = prototype_phone_test_key();
	$query = pg_proto_phone_watch_query(7, 11, 1900000000, $key, 'session-1');

	pg_assert_true(strpos($query, 'watch=1&w=7&p=11&t=1900000000&sig=') === 0, $query);
	pg_assert_true(strpos($query, '&b=') !== false, 'bound to the session');
	pg_assert_false(strpos($query, 'link='), 'no token parameter');
	pg_assert_false(strpos($query, 'session-1'), 'no session id');
	pg_assert_false(strpos($query, str_repeat('a', 64)), 'no token hash');

	parse_str($query, $get);
	$parsed = pg_proto_phone_watch_parse($get);

	pg_assert_same('ok', pg_proto_phone_watch_state($parsed, $key, 1900000010, 'session-1'));
	pg_assert_same('unbound', pg_proto_phone_watch_state($parsed, $key, 1900000010, 'session-2'), 'another browser');
	pg_assert_same('unbound', pg_proto_phone_watch_state($parsed, $key, 1900000010, ''), 'no session cookie');
	pg_assert_same('invalid', pg_proto_phone_watch_state($parsed, str_repeat('x', 32), 1900000010, 'session-1'), 'another key');

	foreach (array('w' => '8', 'p' => '12', 't' => '1900000001') as $name => $value) {
		$changed = $get;
		$changed[$name] = $value;
		pg_assert_same('invalid', pg_proto_phone_watch_state(pg_proto_phone_watch_parse($changed), $key, 1900000010, 'session-1'), $name);
	}

	// A frame's or a person's signature opens no watch.
	$frame = $get;
	$frame['sig'] = pg_proto_link_frame_sign(3, 11, 7, 1900000000, $key);
	pg_assert_same('invalid', pg_proto_phone_watch_state(pg_proto_phone_watch_parse($frame), $key, 1900000010, null));

	pg_assert_same('', pg_proto_phone_watch_query(7, 11, 1900000000, '', 'session-1'), 'no key');
}

function test_prototype_phone_watch_parse_takes_plain_numbers_only()
{
	$key = prototype_phone_test_key();
	parse_str(pg_proto_phone_watch_query(7, 11, 1900000000, $key, 's'), $get);

	pg_assert_same(0, pg_proto_phone_watch_parse($get)['since'], 'no since');
	$get['since'] = '1900000005';
	pg_assert_same(1900000005, pg_proto_phone_watch_parse($get)['since']);
	$get['since'] = 'x';
	pg_assert_same(0, pg_proto_phone_watch_parse($get)['since'], 'a since that is no number is none');

	foreach (array('w' => '07', 'p' => '-1', 't' => '1e9') as $name => $value) {
		$bad = $get;
		$bad[$name] = $value;
		pg_assert_same(null, pg_proto_phone_watch_parse($bad), $name);
	}

	$bad = $get;
	$bad['w'] = array('7');
	pg_assert_same(null, pg_proto_phone_watch_parse($bad), 'an array');

	$bad = $get;
	$bad['sig'] = 'abc';
	pg_assert_same(null, pg_proto_phone_watch_parse($bad), 'short signature');
	pg_assert_same('invalid', pg_proto_phone_watch_state(null, $key, 1900000010, 's'));
}

function test_prototype_phone_watch_window_starts_now_and_lasts_an_hour()
{
	$key = prototype_phone_test_key();
	parse_str(pg_proto_phone_watch_query(7, 11, 1900000000, $key, 's'), $get);
	$parsed = pg_proto_phone_watch_parse($get);

	pg_assert_same(3600, pg_proto_phone_watch_window());
	pg_assert_same('ok', pg_proto_phone_watch_state($parsed, $key, 1900000000 + 3599, 's'));
	pg_assert_same('over', pg_proto_phone_watch_state($parsed, $key, 1900000000 + 3600, 's'), 'an hour on');

	// A start written in the future (more than a minute of clock drift) was
	// not made by this site's viewer for now.
	pg_assert_same('ok', pg_proto_phone_watch_state($parsed, $key, 1900000000 - 60, 's'));
	pg_assert_same('invalid', pg_proto_phone_watch_state($parsed, $key, 1900000000 - 61, 's'));
}

function test_prototype_phone_watch_sql_is_one_read_by_the_links_id()
{
	$sql = pg_proto_phone_watch_sql(7, 11);

	pg_assert_same(1, substr_count($sql, 'SELECT'), 'one statement');
	pg_assert_true(strpos($sql, "WHERE l.id = '7'") !== false, 'by the primary key');
	pg_assert_true(strpos($sql, "p.page_id = '11'") !== false, 'the page by its key');
	pg_assert_true(strpos($sql, 'p.page_style = l.style_id') !== false, 'only a page of the link\'s design');
	pg_assert_true(strpos($sql, 'LIMIT 1') !== false);
	pg_assert_false(stripos($sql, 'UPDATE'), 'counts no use');
	pg_assert_false(stripos($sql, 'token_hash'), 'never by the token');
}

function test_prototype_phone_watch_answer_says_changed_since()
{
	$now = 1900000300;
	$parsed = array('w' => 7, 'p' => 11, 't' => 1900000000, 'since' => 1900000100);
	$row = prototype_phone_test_row(array('pg_phone_saved' => 1900000200));

	$answer = pg_proto_phone_watch_answer($row, $parsed, $now);
	pg_assert_same(200, $answer['status']);
	pg_assert_same(array('ok' => true, 'changed' => true, 'at' => 1900000200, 'next' => 10, 'stop' => false), $answer['body']);

	$answer = pg_proto_phone_watch_answer(array_merge($row, array('pg_phone_saved' => 1900000100)), $parsed, $now);
	pg_assert_false($answer['body']['changed'], 'saved when drawn');

	$answer = pg_proto_phone_watch_answer($row, array_merge($parsed, array('since' => 0)), $now);
	pg_assert_false($answer['body']['changed'], 'nothing drawn yet: nothing to redraw');
	pg_assert_same(1900000200, $answer['body']['at']);
}

function test_prototype_phone_watch_answer_slows_down_and_stops()
{
	$parsed = array('w' => 7, 'p' => 11, 't' => 1900000000, 'since' => 1900000000);
	$row = prototype_phone_test_row(array('pg_phone_saved' => 1900000000));

	pg_assert_same(10, pg_proto_phone_watch_answer($row, $parsed, 1900000000 + 599)['body']['next']);
	pg_assert_same(30, pg_proto_phone_watch_answer($row, $parsed, 1900000000 + 600)['body']['next'], 'ten minutes without a change');

	// A save brings the quick pace back.
	$saved = array_merge($row, array('pg_phone_saved' => 1900000900));
	pg_assert_same(10, pg_proto_phone_watch_answer($saved, $parsed, 1900001000)['body']['next']);

	$over = pg_proto_phone_watch_answer($row, $parsed, 1900003600);
	pg_assert_same(200, $over['status']);
	pg_assert_true($over['body']['stop'], 'an hour on');
	pg_assert_same(0, $over['body']['next']);
}

function test_prototype_phone_watch_answer_is_the_same_for_every_link_that_does_not_open()
{
	$now = 1900000300;
	$parsed = array('w' => 7, 'p' => 11, 't' => 1900000000, 'since' => 0);
	$row = prototype_phone_test_row(array('pg_phone_saved' => 1900000200));
	$gone = array('status' => 404, 'body' => array('ok' => false));

	pg_assert_same($gone, pg_proto_phone_watch_answer(null, $parsed, $now), 'unknown');
	pg_assert_same($gone, pg_proto_phone_watch_answer(array_merge($row, array('revoked_at' => $now - 1)), $parsed, $now), 'ended');
	pg_assert_same($gone, pg_proto_phone_watch_answer(array_merge($row, array('created_at' => $now - 28801, 'expires_at' => $now - 1)), $parsed, $now), 'expired');
	pg_assert_same($gone, pg_proto_phone_watch_answer(array_merge($row, array('pg_phone_saved' => null)), $parsed, $now), 'page of another design or gone');
	pg_assert_same($gone, pg_proto_phone_watch_answer(array_merge($row, array('expires_at' => 1900000000 + 86400)), $parsed, $now), 'a link made by hand');
	pg_assert_same($gone, pg_proto_phone_watch_answer($row, $parsed, $now, 'invalid'), 'a forged address');
	pg_assert_same($gone, pg_proto_phone_watch_answer($row, $parsed, $now, 'unbound'), 'another browser');
}

function test_prototype_phone_answers_keep_the_token_out()
{
	$token = 'AbCdEf0123456789_-AbCdEf0123456789_-AbCdEfG';
	$row = prototype_phone_test_row(array('pg_phone_saved' => 1900000200, 'token_secret' => 'sealed:iv'));
	$answer = pg_proto_phone_watch_answer($row, array('w' => 7, 'p' => 11, 't' => 1900000000, 'since' => 0), 1900000300);
	$json = json_encode($answer);

	foreach (array($token, 'AbCdEf', str_repeat('a', 64), 'sealed', 'link') as $secret) {
		pg_assert_false(strpos($json, $secret), $secret);
	}

	// The phone link's summary for the window names it by its hint, never
	// by its token or hash.
	$summary = pg_proto_phone_summary($row, 1900000300);
	pg_assert_same(7, $summary['id']);
	pg_assert_same(28500, $summary['seconds_left']);
	pg_assert_true($summary['phone']);
	pg_assert_false(strpos(json_encode($summary), str_repeat('a', 64)), 'no hash');
	pg_assert_false(strpos(json_encode($summary), 'sealed'), 'no sealed token');

	// The phone's address is a share link's: the logs write it out.
	$url = '/pinegrap/prototype.php?link=' . $token;
	pg_assert_same('/pinegrap/prototype.php?link=***', pg_proto_link_redact_uri($url));
}

function test_prototype_phone_endpoints_are_wired_for_designers_only()
{
	$designer = file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');

	pg_assert_true(strpos($designer, "case 'prototype_phone':") !== false);
	pg_assert_true(strpos($designer, "case 'prototype_phone_end':") !== false);

	// Not among the sub-actions a content-level operator may call.
	preg_match('/\$content_ok\s*=\s*array\(([^;]*)\);/s', $designer, $m);
	pg_assert_true(isset($m[1]));
	pg_assert_false(strpos($m[1], 'prototype_phone'), 'not in $content_ok');

	pg_assert_same('error', pg_proto_phone_panel('prototype_phone', array(), array('id' => 1, 'role' => 2, 'username' => 'm'))['status'], 'a manager');
	pg_assert_same('error', pg_proto_phone_panel('prototype_phone', array(), array())['status'], 'nobody');

	$viewer = file_get_contents(PG_FUNCTIONS_DIR . '/prototype.php');
	pg_assert_true(strpos($viewer, 'pg_proto_phone_watch_run(') !== false, 'the viewer answers the watch');
	pg_assert_false(strpos(substr($viewer, strpos($viewer, 'pg_proto_phone_watch_run(') - 400, 800), 'pg_proto_link_record_use'), 'the watch counts no use');
}

function test_prototype_phone_start_name_is_the_pages_title_or_the_first_draft()
{
	$pages = array(
		array('id' => 11, 'name' => 'home', 'title' => 'Home'),
		array('id' => 12, 'name' => 'about-us', 'title' => 'About us'),
		array('id' => 13, 'name' => 'courses', 'title' => ' '),
	);

	pg_assert_same('About us', pg_proto_phone_start_name($pages, 12), 'the start page by its title');
	pg_assert_same('courses', pg_proto_phone_start_name($pages, 13), 'its name when it has no title');
	pg_assert_same('Home', pg_proto_phone_start_name($pages, 0), 'the first draft for a link that starts there');
	pg_assert_same('Home', pg_proto_phone_start_name($pages, 99), 'the first draft for a page no longer a draft');
	pg_assert_same('', pg_proto_phone_start_name(array(), 12), 'nothing for a design with no drafts');
}

function test_prototype_phone_window_gets_its_code_inline_and_never_cached()
{
	$fn = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/prototype_phone.php');

	// The code is inline SVG drawn by the server from the address: no image
	// address that would carry the token into a log or a Referer.
	pg_assert_true(strpos($fn, "'qr' => pg_qr_svg(\$url, 0,") !== false, 'the answer carries the code as SVG');

	$designer = file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');
	$at = strpos($designer, "case 'prototype_phone_end':");
	$case = substr($designer, $at, 700);
	pg_assert_true(strpos($case, "includes/fn/qr.php") !== false, 'the generator is loaded for the answer');
	pg_assert_true(strpos($case, "Cache-Control: no-store") !== false, 'the answer is never kept');
}
