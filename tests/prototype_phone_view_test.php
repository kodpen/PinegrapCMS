<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "Preview on a phone" on the phone
 * (includes/fn/prototype_phone_view.php): the page list's rows, what the
 * body holds, the page with its thin line and the token kept out of the
 * asking address, the viewer's branch for a phone link, the frame's answer
 * about the place scrolled to, and the asking itself run in Node against a
 * stand-in browser (tests/js/proto_phone_view_harness.js): its pace, the
 * stop after the answer says so, nothing while the tab is hidden, a redraw
 * after a save, a lost connection and an ended link.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_phone_view.php');

function prototype_phone_view_test_pages()
{
	return array(
		array('id' => 11, 'name' => 'home', 'title' => 'Home'),
		array('id' => 12, 'name' => 'about-us', 'title' => 'About us'),
		array('id' => 13, 'name' => 'my-courses', 'title' => ''),
	);
}

function prototype_phone_view_test_view($over = array())
{
	$base = pg_proto_link_viewer_base('/pinegrap/prototype.php', 'SeCrEtToKeN123');

	return array_merge(array(
		'lang' => 'en',
		'software' => '/pinegrap',
		'design' => 'Still Water Yoga',
		'rows' => pg_proto_phone_view_rows(prototype_phone_view_test_pages(), 12, $base, array(13)),
		'body' => 'frame',
		'frame_url' => '/pinegrap/prototype.php?frame=1&l=7&p=12',
		'error' => '',
		'watch_url' => '/pinegrap/prototype.php?' . pg_proto_phone_watch_query(7, 12, 1900000000, str_repeat('k', 32), 'session-a'),
		'since' => 1899999000,
		'base' => $base,
		'here' => pg_proto_viewer_url($base, 12),
		'link_id' => 7,
	), $over);
}

function test_prototype_phone_view_rows_keep_the_order_mark_the_shown_and_the_locked()
{
	$rows = pg_proto_phone_view_rows(prototype_phone_view_test_pages(), 12, '/p.php?link=T', array(13, '13'));

	pg_assert_same(array(11, 12, 13), array_map(function ($row) { return $row['id']; }, $rows), 'tab order as given');
	pg_assert_same(array('Home', 'About us', 'my-courses'), array_map(function ($row) { return $row['label']; }, $rows), 'a page with no title by its name');
	pg_assert_same(array(false, true, false), array_map(function ($row) { return $row['current']; }, $rows));
	pg_assert_same(array(false, false, true), array_map(function ($row) { return $row['locked']; }, $rows));
	pg_assert_same('/p.php?link=T&page=11', $rows[0]['url']);
}

function test_prototype_phone_view_body_says_what_can_be_drawn()
{
	pg_assert_same('frame', pg_proto_phone_view_body('', '/f', false, true));
	pg_assert_same('locked', pg_proto_phone_view_body('', '/f', false, false), 'a page for signed-in visitors on a link for anyone');
	pg_assert_same('gone', pg_proto_phone_view_body('', '/f', true, true), 'the page asked for is no longer a draft');
	pg_assert_same('error', pg_proto_phone_view_body('no key', '', false, true));
	pg_assert_same('error', pg_proto_phone_view_body('', '', false, true), 'nothing signed');
}

function prototype_phone_view_test_config($html)
{
	pg_assert_true(preg_match('~<script type="application/json" id="pp-config">(.*?)</script>~s', $html, $match) === 1, 'the config is there');

	return json_decode($match[1], true);
}

function test_prototype_phone_view_page_draws_the_line_and_keeps_the_token_out_of_the_asking()
{
	$html = pg_proto_phone_shell_html(prototype_phone_view_test_view());
	$config = prototype_phone_view_test_config($html);

	pg_assert_contains('<span class="pp-name" id="pp-name">About us</span>', $html, 'the page shown is named');
	pg_assert_contains('Updated just now', $html);
	pg_assert_contains('id="pg-proto-frame"', $html);
	pg_assert_contains('sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation"', $html, 'the sandbox of every prototype frame');
	pg_assert_contains('aria-current="page"', $html);
	pg_assert_contains('pp-row pp-off', $html, 'the locked page greyed');
	pg_assert_contains('bi bi-lock', $html);

	// Nothing of the desktop viewer's bar.
	pg_assert_false(strpos($html, 'pg-proto-hotspots') !== false, 'no hotspots switch');
	pg_assert_false(strpos($html, 'bootstrap.min.css') !== false, 'no Bootstrap stylesheet for one line');

	pg_assert_contains('watch=1&w=7&p=12&t=1900000000&sig=', $config['watch']);
	pg_assert_false(strpos($config['watch'], 'SeCrEtToKeN123') !== false, 'the token is not in the asking address');
	pg_assert_false(strpos($config['watch'], 'link=') !== false);
	pg_assert_same(1899999000, $config['since']);
	pg_assert_same('/pinegrap/prototype.php?link=SeCrEtToKeN123&page=12', $config['here']);
	pg_assert_same('pg-proto-phone-7', $config['key']);
	pg_assert_contains('{n}', $config['ago']);
}

function test_prototype_phone_view_page_asks_nothing_when_nothing_is_drawn()
{
	foreach (array('locked', 'gone', 'error') as $body) {
		$html = pg_proto_phone_shell_html(prototype_phone_view_test_view(array('body' => $body)));
		$config = prototype_phone_view_test_config($html);

		pg_assert_same('', $config['watch'], $body . ': no asking');
		pg_assert_false(strpos($html, '<iframe') !== false, $body . ': no frame');
		pg_assert_contains('<span class="pp-words" id="pp-words" role="status"></span>', $html, $body . ': no time');
	}

	$locked = pg_proto_phone_shell_html(prototype_phone_view_test_view(array('body' => 'locked')));
	pg_assert_contains('This page is for signed-in visitors', $locked);
	pg_assert_contains('Ask for a team link to see it on your phone.', $locked);

	// A page gone from the design opens the list.
	$gone = pg_proto_phone_shell_html(prototype_phone_view_test_view(array('body' => 'gone')));
	pg_assert_contains('This page is no longer in the design.', $gone);
	pg_assert_contains('<nav class="pp-sheet" id="pp-sheet" aria-label="Pages">', $gone, 'the list is open');
	pg_assert_contains('aria-expanded="true"', $gone);

	$closed = pg_proto_phone_shell_html(prototype_phone_view_test_view());
	pg_assert_contains('<nav class="pp-sheet" id="pp-sheet" aria-label="Pages" hidden>', $closed, 'closed otherwise');
}

// The viewer draws a phone link's page after every gate of a share link
// (team sign-in, password, the use counted on the shared address alone) and
// before the comment view, which a phone link never has.
function test_prototype_phone_view_branch_sits_after_the_gates()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/prototype.php');
	$branch = strpos($source, 'if (pg_proto_link_is_phone($pg_proto_link))');

	pg_assert_true($branch !== false, 'the branch is there');
	pg_assert_true(strpos($source, 'pg_proto_link_counts_use(') < $branch, 'after the use is counted');
	pg_assert_true(strpos($source, 'pg_proto_link_session_mark(') < $branch, 'after the session is marked');
	pg_assert_true(strpos($source, "if ((string) \$pg_proto_link['password_hash'] !== '')") < $branch, 'after the password gate');
	pg_assert_true(strpos($source, '$pg_proto_comment_view = ') > $branch, 'before the comment view');

	$part = substr($source, $branch, strpos($source, 'exit;', $branch) - $branch);
	pg_assert_contains('pg_proto_phone_watch_query($pg_proto_link[\'id\'], $pg_proto_start[\'id\'], $pg_proto_now, $pg_proto_key, session_id())', $part, 'bound to this browser\'s session');
	pg_assert_contains("if (\$pg_proto_body === 'frame')", $part, 'asked only for a drawn page');
}

// The frame tells only its viewer where it is scrolled to and takes the
// place back from it.
function test_prototype_phone_view_frame_answers_where_it_is_scrolled()
{
	$html = pg_proto_frame_inject('<html><head></head><body></body></html>', array());

	pg_assert_contains('if (e.source !== window.parent || !e.data) return;', $html);
	pg_assert_contains('kind === "where"', $html);
	pg_assert_contains('kind === "scroll"', $html);
	pg_assert_contains('root.classList.toggle("pg-proto-hotspots", !!e.data.on)', $html, 'the hotspots switch as before');
}

function prototype_phone_view_test_has_node()
{
	if (!function_exists('exec')) {
		return false;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	return $code === 0;
}

// Runs the page's script in the stand-in browser with `$config`, what
// sessionStorage holds first and `$scenario` (an async body that may call
// next(), settle(), state(), fire()); the JSON it prints, or null without
// Node.
function prototype_phone_view_test_run($config, $kept, $scenario)
{
	if (!prototype_phone_view_test_has_node()) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgphone');

	if ($file === false) {
		return null;
	}

	$js = file_get_contents(dirname(__DIR__) . '/tests/js/proto_phone_view_harness.js')
		. "\nsetConfig(" . json_encode($config) . ");\n"
		. (($kept !== null) ? "storage[" . json_encode($config['key']) . "] = JSON.stringify(Object.assign({ at: clock }, " . json_encode($kept) . "));\n" : '')
		. pg_proto_phone_shell_script() . "\n"
		. "(async function () {\n" . $scenario . "\n})().catch(function (e) { console.error(e); process.exit(1); });\n";

	file_put_contents($file, $js);
	$out = array();
	$code = 1;
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

function prototype_phone_view_test_config_js()
{
	$config = prototype_phone_view_test_config(pg_proto_phone_shell_html(prototype_phone_view_test_view()));

	return $config;
}

// Every 10 seconds, then as the answer says (30 after ten quiet minutes),
// and not at all after its stop: "Paused" with "Update".
function test_prototype_phone_view_asks_at_the_answers_pace_and_stops()
{
	$got = prototype_phone_view_test_run(prototype_phone_view_test_config_js(), null, '
		var out = [state()];
		answers.push({ status: 200, body: { ok: true, changed: false, at: 1899999000, next: 10, stop: false } });
		answers.push({ status: 200, body: { ok: true, changed: false, at: 1899999000, next: 30, stop: false } });
		answers.push({ status: 200, body: { ok: true, changed: false, at: 1899999000, next: 0, stop: true } });
		await next(); out.push(state());
		await next(); out.push(state());
		await next(); out.push(state());
		await next(); out.push(state());
		console.log(JSON.stringify({ out: out, first: asked[0], address: window.lastAddress }));
	');

	if ($got === null) {
		return;
	}

	pg_assert_same(10, $got['out'][0]['wait'], 'the first ask after 10 s');
	pg_assert_same(0, $got['out'][0]['asked']);
	pg_assert_same(10, $got['out'][1]['wait']);
	pg_assert_same(30, $got['out'][2]['wait'], 'slower when the answer says so');
	pg_assert_same('Paused', $got['out'][3]['words']);
	pg_assert_true($got['out'][3]['warn']);
	pg_assert_true($got['out'][3]['update'], '"Update" shows');
	pg_assert_same(0, $got['out'][3]['wait'], 'no more asking');
	pg_assert_same(3, $got['out'][4]['asked'], 'nothing asked after the stop');
	pg_assert_contains('&since=1899999000', $got['first']);
	pg_assert_same('/pinegrap/prototype.php?link=SeCrEtToKeN123&page=12', $got['address'], 'a reload opens the page, counting no use');
}

// While the tab is hidden nothing is asked; coming back asks at once.
function test_prototype_phone_view_asks_only_while_the_tab_is_seen()
{
	$got = prototype_phone_view_test_run(prototype_phone_view_test_config_js(), null, '
		document.visibilityState = "hidden"; fire("document", "visibilitychange");
		var hidden = state();
		await next();
		var still = state();
		document.visibilityState = "visible"; fire("document", "visibilitychange");
		await settle();
		console.log(JSON.stringify({ hidden: hidden, still: still, back: state() }));
	');

	if ($got === null) {
		return;
	}

	pg_assert_same(0, $got['hidden']['wait'], 'the timer is cleared');
	pg_assert_same(0, $got['still']['asked']);
	pg_assert_same(1, $got['back']['asked'], 'asked at once on coming back');
	pg_assert_same(10, $got['back']['wait']);
}

// A save draws the page again in place: the place scrolled to is asked of
// the frame and kept for the new drawing, which shows the blue edge.
function test_prototype_phone_view_redraws_after_a_save_keeping_the_place()
{
	$config = prototype_phone_view_test_config_js();
	$got = prototype_phone_view_test_run($config, null, '
		answers.push({ status: 200, body: { ok: true, changed: true, at: 1900000100, next: 10, stop: false } });
		await next();
		var frame = document.getElementById("pg-proto-frame");
		fire("window", "message", { source: frame.contentWindow, data: { pgPrototype: "where", y: 640 } });
		await settle();
		console.log(JSON.stringify({ s: state(), posted: posted, kept: JSON.parse(storage["pg-proto-phone-7"]) }));
	');

	if ($got === null) {
		return;
	}

	pg_assert_same(array(array('pgPrototype' => 'where')), $got['posted']);
	pg_assert_same(array($config['here']), $got['s']['replaced'], 'the page drawn again in place');
	pg_assert_same(640, $got['kept']['y']);
	pg_assert_true($got['kept']['flash']);

	$after = prototype_phone_view_test_run($config, array('y' => 640, 'flash' => true), '
		var frame = document.getElementById("pg-proto-frame");
		fire("window", "message", { source: frame.contentWindow, data: { pgPrototype: "ready" } });
		var s = state();
		await next();
		var plain = state();
		await next();
		console.log(JSON.stringify({ s: s, plain: plain, asked: state(), posted: posted, left: storage["pg-proto-phone-7"] || null }));
	');

	pg_assert_same(array(array('pgPrototype' => 'scroll', 'y' => 640)), $after['posted'], 'the place handed back');
	pg_assert_true($after['s']['fresh'], 'the blue edge');
	pg_assert_false($after['plain']['fresh'], 'for two seconds');
	pg_assert_same('Updated just now', $after['asked']['words']);
	pg_assert_same(null, $after['left'], 'read once');
}

// No answer: "No connection. Trying again…", asked again after the wait;
// the next answer takes the words away.
function test_prototype_phone_view_says_no_connection_and_tries_again()
{
	$got = prototype_phone_view_test_run(prototype_phone_view_test_config_js(), null, '
		answers.push("offline");
		await next();
		var lost = state();
		await next();
		console.log(JSON.stringify({ lost: lost, back: state() }));
	');

	if ($got === null) {
		return;
	}

	pg_assert_same('No connection. Trying again…', $got['lost']['words']);
	pg_assert_true($got['lost']['warn']);
	pg_assert_same(10, $got['lost']['wait']);
	pg_assert_same('Updated just now', $got['back']['words']);
	pg_assert_false($got['back']['warn']);
}

// A 404 (link ended, page gone, session changed) draws the page once more so
// the viewer says what is left; a 404 straight after that redraw stops.
function test_prototype_phone_view_redraws_once_on_a_404_then_stops()
{
	$config = prototype_phone_view_test_config_js();
	$got = prototype_phone_view_test_run($config, null, '
		answers.push({ status: 404, body: { ok: false } });
		await next();
		await next();
		console.log(JSON.stringify({ s: state(), kept: JSON.parse(storage["pg-proto-phone-7"]) }));
	');

	if ($got === null) {
		return;
	}

	pg_assert_same(array($config['here']), $got['s']['replaced']);
	pg_assert_true($got['kept']['gone']);

	$again = prototype_phone_view_test_run($config, array('gone' => true), '
		answers.push({ status: 404, body: { ok: false } });
		await next();
		console.log(JSON.stringify({ s: state() }));
	');

	pg_assert_same(array(), $again['s']['replaced'], 'no loop of redraws');
	pg_assert_same('Paused', $again['s']['words']);
	pg_assert_same(0, $again['s']['wait']);
}

// "Update" after the pause draws the page again, which starts a new watch.
function test_prototype_phone_view_update_starts_again()
{
	$config = prototype_phone_view_test_config_js();
	$got = prototype_phone_view_test_run($config, null, '
		answers.push({ status: 200, body: { ok: true, changed: false, at: 1, next: 0, stop: true } });
		await next();
		document.getElementById("pp-update").fire("click");
		await next();
		console.log(JSON.stringify({ s: state() }));
	');

	if ($got === null) {
		return;
	}

	pg_assert_same(array($config['here']), $got['s']['replaced']);
}

// The grey words say how long ago the page was drawn.
function test_prototype_phone_view_says_how_long_ago()
{
	$got = prototype_phone_view_test_run(prototype_phone_view_test_config_js(), null, '
		clock += 12 * 60000;
		await next();
		console.log(JSON.stringify({ s: state() }));
	');

	if ($got === null) {
		return;
	}

	pg_assert_same('Updated 12 min ago', $got['s']['words']);
}
