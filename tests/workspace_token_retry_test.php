<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A Workspace request refused for a stale form token (a page left open while
 * the remember-me cookie signed the person in again and renewed the token)
 * gets the session's current token and is sent once more; a refused session
 * is handed to the caller, which keeps the typed text. The rule runs in Node
 * (assets/js/workspace_token_retry.js); the server side is pinned in source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the module as R and answers its JSON; null without Node.
function workspace_token_retry_node($script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwstoken');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var R = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/workspace_token_retry.js') . ');' . "\n"
		// A fake server: the session token, a log of the tokens each request
		// carried, and whether the sign-in still stands.
		. 'function refusal(code) { var e = new Error(code); e.code = code; return e; }' . "\n"
		. 'function server(o) {' . "\n"
		. '  var s = { session: o.session, page: o.page, signedIn: o.signedIn !== false, sent: [], renewals: 0 };' . "\n"
		. '  s.request = function () { s.sent.push(s.page); if (!s.signedIn) { return Promise.reject(refusal("session")); }'
		. ' if (s.page !== s.session) { return Promise.reject(refusal("token")); } return Promise.resolve("ok"); };' . "\n"
		. '  s.renew = R.renewer(function () { s.renewals++; if (!s.signedIn) { return Promise.reject(refusal("session")); }'
		. ' return Promise.resolve(o.answer !== undefined ? o.answer : s.session); }, function (t) { s.page = t; });' . "\n"
		. '  return s; }' . "\n"
		. 'function run(s) { return R.send(s.request, s.renew).then(function (v) { return { ok: v, sent: s.sent, renewals: s.renewals }; },'
		. ' function (e) { return { code: e.code || "", sent: s.sent, renewals: s.renewals }; }); }' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

function test_workspace_token_retry_rule()
{
	$r = workspace_token_retry_node(
		'Promise.all(['
		. ' run(server({ session: "new", page: "new" })),'
		. ' run(server({ session: "new", page: "old" })),'
		. ' run(server({ session: "new", page: "old", signedIn: false })),'
		. ' run(server({ session: "new", page: "old", answer: "still-wrong" })),'
		. ' run(server({ session: "new", page: "old", answer: "" }))'
		. ']).then(function (all) { console.log(JSON.stringify(all)); });'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	list($fresh, $stale, $signedOut, $stillStale, $noToken) = $r;

	pg_assert_same(array('ok' => 'ok', 'sent' => array('new'), 'renewals' => 0), $fresh, 'a good token: one request, no renewal');
	pg_assert_same(array('ok' => 'ok', 'sent' => array('old', 'new'), 'renewals' => 1), $stale, 'a stale token: renewed and sent again with the new one');
	pg_assert_same(array('code' => 'session', 'sent' => array('old'), 'renewals' => 0), $signedOut, 'a refused session: no renewal, handed to the caller');
	pg_assert_same(array('code' => 'token', 'sent' => array('old', 'still-wrong'), 'renewals' => 1), $stillStale, 'sent again only once');
	pg_assert_same(array('code' => 'token', 'sent' => array('old'), 'renewals' => 1), $noToken, 'an answer without a token: the first refusal stands');
}

// The sign-in ended between the refusal and the renewal: the caller is told
// the session ended, not that the token was stale.
function test_workspace_token_retry_session_ends_during_renewal()
{
	$r = workspace_token_retry_node(
		'var s = server({ session: "new", page: "old" });' . "\n"
		. 'var request = s.request; s.request = function () { var p = request(); s.signedIn = false; return p; };' . "\n"
		. 'run(s).then(function (out) { console.log(JSON.stringify(out)); });'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('session', $r['code'] ?? '', 'the renewal\'s own refusal');
	pg_assert_same(array('old'), $r['sent'] ?? null, 'nothing sent again');
}

// Requests refused at the same time share one renewal.
function test_workspace_token_retry_one_renewal_for_many()
{
	$r = workspace_token_retry_node(
		'var s = server({ session: "new", page: "old" });' . "\n"
		. 'Promise.all([R.send(s.request, s.renew), R.send(s.request, s.renew), R.send(s.request, s.renew)])'
		. '.then(function (all) { console.log(JSON.stringify({ all: all, renewals: s.renewals })); });'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('ok', 'ok', 'ok'), $r['all'] ?? null, 'every request sent');
	pg_assert_same(1, $r['renewals'] ?? 0, 'one renewal');
}

function workspace_token_retry_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The server marks a stale token apart from an ended session, and hands a
// signed-in session its current token.
function test_workspace_token_retry_server_side()
{
	pg_assert_contains("'code' => 'token'", workspace_token_retry_source('api.php'), 'api.php: the token refusal says so');

	require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');
	$row = pg_panel_action('session_token');
	pg_assert_true(is_array($row), 'session_token is an action');
	pg_assert_same('session', pg_panel_gate('session_token'), 'any signed-in role, refused without a session');
	pg_assert_false($row['write'], 'it reads');

	$handler = workspace_token_retry_source('includes/panel/dashboard.php');
	pg_assert_contains('function pg_panel_session_token(', $handler, 'handler');
	pg_assert_contains("defined('API_AUTHENTICATED')", $handler, 'a password caller holds no page token');

	$js = workspace_token_retry_source('assets/js/workspace.js');
	pg_assert_contains("apiOnce('session_token')", $js, 'api() renews through session_token');
	pg_assert_contains('retry.send(', $js, 'api() sends again once');
	pg_assert_contains("t('send_signed_out')", $js, 'an ended sign-in keeps the text and says so');
}
