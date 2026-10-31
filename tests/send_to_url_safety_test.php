<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_send_to_url(): a send_to that a browser would follow off the
 * site ('//host', '/\host', a control character inside the path, an absolute
 * URL) gives the screen's fallback; a path on this site comes back unchanged.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function send_to_url_safety_with($send_to)
{
	$had = array_key_exists('send_to', $_REQUEST);
	$old = $had ? $_REQUEST['send_to'] : null;

	$_REQUEST['send_to'] = $send_to;

	try {
		return pg_send_to_url('fallback');
	} finally {
		if ($had) {
			$_REQUEST['send_to'] = $old;
		} else {
			unset($_REQUEST['send_to']);
		}
	}
}

function test_send_to_url_refuses_addresses_that_leave_the_site()
{
	$cases = array(
		'/\\evil.example/x' => 'a backslash after the slash is followed as //host',
		"/\tevil" => 'a tab is stripped by the browser',
		"/\x00evil" => 'a NUL byte',
		"/pinegrap/\x7Fx" => 'a DEL character',
		"/x\r\nLocation: y" => 'a header split',
		'//evil' => 'a protocol-relative address',
		'https://evil' => 'an absolute URL',
		'javascript:alert(1)' => 'a script address',
	);

	foreach ($cases as $send_to => $why) {
		pg_assert_same('fallback', send_to_url_safety_with($send_to), $why);
	}
}

function test_send_to_url_keeps_paths_on_this_site()
{
	pg_assert_same('/pinegrap/view_files.php?filter=x', send_to_url_safety_with('/pinegrap/view_files.php?filter=x'), 'a panel list with its filter');
	pg_assert_same('/', send_to_url_safety_with('/'), 'the site root');
	pg_assert_same('/pinegrap/view_files.php', send_to_url_safety_with('  /pinegrap/view_files.php  '), 'surrounding spaces are trimmed');
	pg_assert_same('fallback', send_to_url_safety_with(''), 'empty gives the fallback');
}
