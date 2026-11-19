<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for visitor notifications on the site: the "Notify me" block's door
 * on the published page (includes/fn/site_push_block.php: its mark, the
 * script and its settings added once before the last </body>, nothing
 * without a key), the worker's settings line (site_push_sw.php), the page
 * script assets/js/pg_site_push.js and the worker assets/js/
 * pg_site_push_sw.js under Node on small fakes (the states, nothing shown
 * where the browser cannot subscribe, no worker before the press, the press,
 * Stop, a refusal, the notification and the fixed notice), the block family
 * style_designer_blocks_push.js and the Features card.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_push_block.php');
require_once(__DIR__ . '/helpers/designer_family_run.php');

// Runs $script (async, ending in a printed JSON) with the page script as P,
// the worker as W and the fakes; its JSON, or null without Node.
function _site_push_block_node($script)
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
	$file = @tempnam(sys_get_temp_dir(), 'pgsitepush');
	if ($file === false) {
		return null;
	}
	file_put_contents($file, 'var P = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_site_push.js') . ');' . "\n"
		. 'var W = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_site_push_sw.js') . ');' . "\n"
		. (string) file_get_contents(__DIR__ . '/fixtures/listen/dom.js') . "\n"
		. (string) file_get_contents(__DIR__ . '/fixtures/site_push/fake.js') . "\n"
		. '(async function () {' . "\n" . $script . "\n" . '})().catch(function (e) { console.log(JSON.stringify({ error: String(e && e.stack || e) })); });');
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

function _site_push_block_ran($r)
{
	if ($r === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return false;
	}
	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	return !isset($r['error']);
}

function _site_push_block_settings()
{
	return pg_site_push_block_settings('/', 'pinegrap', 'BKey');
}

// -- the door on the page (N1, N3) -------------------------------------------

function test_site_push_block_pattern()
{
	foreach (array('<section data-pg-push hidden>', '<div class="x" data-pg-push="">', "<p\tDATA-PG-PUSH>", '<i data-pg-push/>') as $html) {
		pg_assert_true(pg_site_push_block_on_page($html), $html);
	}
	foreach (array('<button data-pg-push-on>', '<p>data-pg-push</p>', '<div data-pg-pushy>', '<div xdata-pg-push>') as $html) {
		pg_assert_false(pg_site_push_block_on_page($html), $html);
	}
}

function test_site_push_block_settings_paths()
{
	pg_assert_same(array(
		'door'   => '/site/pinegrap/site_push.php',
		'worker' => '/site/pinegrap/site_push_sw.php',
		'scope'  => '/site/',
		'key'    => 'K',
	), pg_site_push_block_settings('/site/', 'pinegrap', 'K'));
}

function test_site_push_block_inject_adds_the_script_once()
{
	$src = '/pinegrap/assets/js/pg_site_push.js?v=1';
	$html = '<html><body><pre>&lt;/body&gt; </body></pre><section data-pg-push hidden></section></body></html>';
	$out = pg_site_push_block_inject($html, _site_push_block_settings(), $src);

	pg_assert_same(1, substr_count($out, 'pg_site_push.js'));
	pg_assert_contains('window.pgSitePushConfig = {', $out);
	pg_assert_contains('"key":"BKey"', $out);
	pg_assert_contains('<script src="' . $src . '" defer></script>' . "\n" . '</body></html>', $out);
	pg_assert_same($out, pg_site_push_block_inject($out, _site_push_block_settings(), $src), 'a second call adds nothing');
}

function test_site_push_block_inject_leaves_pages_alone()
{
	$src = '/pinegrap/assets/js/pg_site_push.js?v=1';
	$plain = '<html><body><button data-pg-push-on>x</button></body></html>';
	pg_assert_same($plain, pg_site_push_block_inject($plain, _site_push_block_settings(), $src), 'no block, no script');

	$block = '<html><body><section data-pg-push hidden></section></body></html>';
	pg_assert_same($block, pg_site_push_block_inject($block, pg_site_push_block_settings('/', 'pinegrap', ''), $src), 'no key: the block stays hidden');
}

function test_site_push_block_inject_for_an_order_button()
{
	$src = '/pinegrap/assets/js/pg_site_push.js?v=1';
	$html = '<html><body><div class="pg-push-order mt-3" data-pg-push-order="7" hidden></div></body></html>';
	$out = pg_site_push_block_inject($html, _site_push_block_settings(), $src);
	pg_assert_same(1, substr_count($out, 'pg_site_push.js'), 'an order page gets the script');

	pg_assert_true(pg_site_push_order_on_page('<div data-pg-push-order="12" hidden>'));
	foreach (array('<p>data-pg-push-order="1"</p>', '<button data-pg-push-order-on>', '<div xdata-pg-push-order="1">') as $html) {
		pg_assert_false(pg_site_push_order_on_page($html), $html);
	}
}

function test_site_push_block_worker_prelude()
{
	$line = pg_site_push_worker_prelude('/pinegrap/site_push.php', 'Kafe </script> & Co', 'Open the site.', '');
	pg_assert_same(0, strpos($line, 'self.pgSitePushWorker = {'));
	pg_assert_false(strpos($line, '</script>') !== false, 'the name cannot close a tag');
	pg_assert_false(strpos($line, '"icon"') !== false, 'no icon without a site icon');
	pg_assert_contains('"door":"/pinegrap/site_push.php"', $line);
	pg_assert_contains('"icon":"/apple-touch-icon.png"', pg_site_push_worker_prelude('/d', 'n', 't', '/apple-touch-icon.png'));
}

// The worker file answers for the site's root and loads no session.
function test_site_push_block_worker_file()
{
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/site_push_sw.php');
	pg_assert_contains("header('Service-Worker-Allowed: ' . PATH);", $php);
	pg_assert_contains("define('PG_NO_SESSION', true);", $php);
	pg_assert_contains("header('Content-Type: application/javascript; charset=utf-8');", $php);

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_site_push_sw.js');
	pg_assert_false(strpos($js, "'fetch'") !== false, 'no fetch handler (N2)');
}

function test_site_push_block_page_content_loads_the_module()
{
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_push_block.php');", $php);
	pg_assert_contains('$content = pg_site_push_block_inject($content);', $php);
}

// -- the page script's pure parts --------------------------------------------

function test_site_push_block_states()
{
	$r = _site_push_block_node('
var s = function (o) { return P.stateOf(o); };
console.log(JSON.stringify({
  none: s({ supported: false, permission: "granted", secret: "x", subscribed: true }),
  blocked: s({ supported: true, permission: "denied", secret: "x", subscribed: true }),
  on: s({ supported: true, permission: "granted", secret: "x", subscribed: true }),
  noSecret: s({ supported: true, permission: "granted", secret: "", subscribed: true }),
  noSub: s({ supported: true, permission: "granted", secret: "x", subscribed: false }),
  fresh: s({ supported: true, permission: "default", secret: "", subscribed: false }),
  parts: [P.partsFor("off", true), P.partsFor("none", true), P.partsFor("on", true)],
  lang: [P.langOf("tr"), P.langOf("en-x-mtfrom-tr"), P.langOf("pt-BR"), P.langOf("x"), P.langOf(null)],
  round: P.b64url(P.keyBytes("BAQEBAc_-w")),
  same: [P.sameKey(null, "AQ"), P.sameKey(new Uint8Array([1]).buffer, "AQ"), P.sameKey(new Uint8Array([2]).buffer, "AQ")]
}));');
	if (!_site_push_block_ran($r)) {
		return;
	}
	pg_assert_same('none', $r['none']);
	pg_assert_same('blocked', $r['blocked']);
	pg_assert_same('on', $r['on']);
	pg_assert_same('off', $r['noSecret'], 'a subscription without its secret is off');
	pg_assert_same('off', $r['noSub'], 'a secret without a subscription is off');
	pg_assert_same('off', $r['fresh']);
	pg_assert_same(array('root' => true, 'ask' => true, 'done' => false, 'blocked' => false, 'failed' => true), $r['parts'][0]);
	pg_assert_same(false, $r['parts'][1]['root'], 'nothing shows where the browser cannot subscribe');
	pg_assert_same(array('root' => true, 'ask' => false, 'done' => true, 'blocked' => false, 'failed' => false), $r['parts'][2]);
	pg_assert_same(array('tr', 'en', 'pt-BR', '', ''), $r['lang']);
	pg_assert_same('BAQEBAc_-w', $r['round']);
	pg_assert_same(array(true, true, false), $r['same']);
}

// -- the page script on a page (N3, N4, N7) ----------------------------------

function test_site_push_block_hidden_where_the_browser_cannot_subscribe()
{
	$r = _site_push_block_node('
var block = pushBlock(), doc = fakeDoc(el("html", { lang: "tr" }, [el("body", {}, [block])]));
var win = fakeBrowser("default", "granted", function () { return {}; });
delete win.PushManager;
P.start(win, doc);
await settle();
var noKey = fakeBrowser("default", "granted", function () { return {}; }), block2 = pushBlock();
noKey.pgSitePushConfig.key = "";
P.start(noKey, fakeDoc(el("html", {}, [block2])));
await settle();
console.log(JSON.stringify({ hidden: block.hidden, hidden2: block2.hidden, api: typeof win.pgSitePush.secret, registered: win.rec.registered }));');
	if (!_site_push_block_ran($r)) {
		return;
	}
	pg_assert_true($r['hidden'], 'no push manager: nothing shows');
	pg_assert_true($r['hidden2'], 'no key: nothing shows');
	pg_assert_same('function', $r['api'], 'the secret is offered to other forms anyway');
	pg_assert_same(null, $r['registered']);
}

function test_site_push_block_press_subscribes_and_stop_removes()
{
	$r = _site_push_block_node('
var block = pushBlock(), doc = fakeDoc(el("html", { lang: "tr-x-mtfrom-en" }, [el("body", {}, [block])]));
var win = fakeBrowser("default", "granted", function (f) { return f.action === "subscribe" ? { secret: "s3cret" } : { removed: 1 }; });
P.start(win, doc);
await settle();
var before = shown(block), registeredBefore = win.rec.registered;
block.querySelector("[data-pg-push-on]").click();
await settle();
var on = shown(block), sub = win.rec.posts[0], stored = win.indexedDB.data.secret, focused = doc.activeElement === block.querySelector("[data-pg-push-stop]");
await heard();
var said = spoken(block);
var secret = await win.pgSitePush.secret();
block.querySelector("[data-pg-push-stop]").click();
await settle();
console.log(JSON.stringify({ before: before, registeredBefore: registeredBefore, on: on, sub: sub, stored: stored, secret: secret, focused: focused,
  registered: win.rec.unregistered, userVisibleOnly: win.rec.subscribedWith.userVisibleOnly, keyLength: win.rec.subscribedWith.applicationServerKey.length,
  stop: win.rec.posts[1], off: shown(block), left: win.indexedDB.data.secret === undefined, unsubscribed: win.rec.unsubscribed,
  refocused: doc.activeElement === block.querySelector("[data-pg-push-on]"), said: said }));');
	if (!_site_push_block_ran($r)) {
		return;
	}
	pg_assert_same(array('root' => true, 'ask' => true, 'done' => false, 'blocked' => false, 'failed' => false, 'state' => 'off'), $r['before']);
	pg_assert_same(null, $r['registeredBefore'], 'no worker before the press');
	pg_assert_same(array('root' => true, 'ask' => false, 'done' => true, 'blocked' => false, 'failed' => false, 'state' => 'on'), $r['on']);
	pg_assert_same('/pg/site_push.php', $r['sub']['url']);
	pg_assert_same('subscribe', $r['sub']['fields']['action']);
	pg_assert_same('https://fcm.googleapis.com/fcm/send/abc', $r['sub']['fields']['endpoint']);
	pg_assert_same(87, strlen($r['sub']['fields']['p256dh']), 'a 65-byte key, base64url without padding');
	pg_assert_same(22, strlen($r['sub']['fields']['auth']));
	pg_assert_true(pg_site_push_keys_valid($r['sub']['fields']['p256dh'], $r['sub']['fields']['auth']), 'the door takes the keys');
	pg_assert_same('tr', $r['sub']['fields']['language'], 'the page language without the translation tail');
	pg_assert_same('s3cret', $r['stored'], 'the worker finds the secret');
	pg_assert_same('s3cret', $r['secret']);
	pg_assert_true($r['focused'], 'the focus moves to Stop');
	pg_assert_same(array('done' => 'Notifications are on.'), $r['said'], 'the line is spoken once and kept whole');
	pg_assert_true($r['userVisibleOnly']);
	pg_assert_same(65, $r['keyLength']);

	pg_assert_same(array('action' => 'stop', 'secret' => 's3cret'), $r['stop']['fields']);
	pg_assert_same(1, $r['unsubscribed'], 'removed in the browser');
	pg_assert_same(1, $r['registered'], 'the worker goes with it');
	pg_assert_true($r['left'], 'the secret is forgotten');
	pg_assert_same('off', $r['off']['state']);
	pg_assert_true($r['refocused']);
}

function test_site_push_block_refused_or_blocked()
{
	$r = _site_push_block_node('
var block = pushBlock(), doc = fakeDoc(el("html", {}, [block]));
var win = fakeBrowser("default", "granted", function () { return { error: "endpoint" }; });
P.start(win, doc);
await settle();
block.querySelector("[data-pg-push-on]").click();
await settle();
var refused = shown(block), unsubscribed = win.rec.unsubscribed;
await heard();
var refusedSaid = spoken(block);

var block2 = pushBlock(), win2 = fakeBrowser("default", "denied", function () { return {}; });
P.start(win2, fakeDoc(el("html", {}, [block2])));
await settle();
block2.querySelector("[data-pg-push-on]").click();
await settle();
await heard();

var block3 = pushBlock(), win3 = fakeBrowser("denied", "denied", function () { return {}; });
P.start(win3, fakeDoc(el("html", {}, [block3])));
await settle();
console.log(JSON.stringify({ refused: refused, unsubscribed: unsubscribed, secret: win.indexedDB.data.secret || "", said: shown(block2), registered: win2.rec.registered, already: shown(block3),
  refusedSaid: refusedSaid, noSaid: spoken(block2), loadSaid: spoken(block3) }));');
	if (!_site_push_block_ran($r)) {
		return;
	}
	pg_assert_same(array('root' => true, 'ask' => true, 'done' => false, 'blocked' => false, 'failed' => true, 'state' => 'off'), $r['refused']);
	pg_assert_same(1, $r['unsubscribed'], 'a subscription the site refused is not kept');
	pg_assert_same('', $r['secret']);
	pg_assert_same('blocked', $r['said']['state'], 'a no shows the hint');
	pg_assert_true($r['said']['blocked'] && !$r['said']['ask']);
	pg_assert_same(null, $r['registered'], 'no worker after a no');
	pg_assert_same('blocked', $r['already']['state']);
	pg_assert_same(array('failed' => 'Failed'), $r['refusedSaid'], 'a failed try is spoken');
	pg_assert_same(array('blocked' => 'Blocked'), $r['noSaid'], 'a no is spoken');
	pg_assert_same(array(), $r['loadSaid'], 'nothing is spoken when the page opens');
}

// The order's button: shown where the block would be, the press subscribes
// this device first, then posts the order action; the block on the same
// page follows.
function test_site_push_block_order_button_subscribes_and_watches()
{
	$r = _site_push_block_node('
var order = orderButton(42), block = pushBlock(), doc = fakeDoc(el("html", { lang: "en" }, [el("body", {}, [block, order])]));
var win = fakeBrowser("default", "granted", function (f) { return f.action === "subscribe" ? { secret: "s3cret" } : { watching: 1 }; });
P.start(win, doc);
await settle();
var before = orderShown(order);
order.querySelector("[data-pg-push-order-on]").click();
await settle();
var after = orderShown(order), focused = doc.activeElement === order.querySelector("[data-pg-push-order-done]");
await heard();
var said = order.querySelector("[data-pg-push-order-done]").taken;
console.log(JSON.stringify({ before: before, after: after, focused: focused, said: said, posts: win.rec.posts.map(function (p) { return p.fields; }),
  block: shown(block).state, stored: win.indexedDB.data.secret }));');
	if (!_site_push_block_ran($r)) {
		return;
	}
	pg_assert_same(array('root' => true, 'on' => true, 'done' => false, 'blocked' => false, 'failed' => false, 'state' => 'ready'), $r['before']);
	pg_assert_same(array('root' => true, 'on' => false, 'done' => true, 'blocked' => false, 'failed' => false, 'state' => 'done'), $r['after']);
	pg_assert_true($r['focused'], 'the focus moves to the line that says it');
	pg_assert_same(1, $r['said'], 'the line is spoken');
	pg_assert_same(2, count($r['posts']));
	pg_assert_same('subscribe', $r['posts'][0]['action'], 'a device without a subscription subscribes first');
	pg_assert_same(array('action' => 'order', 'secret' => 's3cret', 'order_id' => '42'), $r['posts'][1]);
	pg_assert_same('on', $r['block'], 'the block on the page shows notifications on');
	pg_assert_same('s3cret', $r['stored']);
}

function test_site_push_block_order_button_subscribed_device_and_failures()
{
	$r = _site_push_block_node('
// A device already subscribed posts the order alone.
var order = orderButton(5), win = fakeBrowser("granted", "granted", function () { return { watching: 1 }; });
win.indexedDB.data.secret = "kept";
await win.navigator.serviceWorker.register("/pg/site_push_sw.php", { scope: "/" });
await (await win.navigator.serviceWorker.getRegistration("/")).pushManager.subscribe({});
P.start(win, fakeDoc(el("html", {}, [order])));
await settle();
order.querySelector("[data-pg-push-order-on]").click();
await settle();
var kept = win.rec.posts.map(function (p) { return p.fields; });

// A secret the site no longer knows is replaced once.
var order2 = orderButton(6), n = 0, win2 = fakeBrowser("granted", "granted", function (f) {
  if (f.action === "subscribe") return { secret: "fresh" };
  return (n++ === 0) ? { error: "subscription" } : { watching: 1 };
});
win2.indexedDB.data.secret = "old";
await win2.navigator.serviceWorker.register("/pg/site_push_sw.php", { scope: "/" });
await (await win2.navigator.serviceWorker.getRegistration("/")).pushManager.subscribe({});
P.start(win2, fakeDoc(el("html", {}, [order2])));
await settle();
order2.querySelector("[data-pg-push-order-on]").click();
await settle();
var replaced = win2.rec.posts.map(function (p) { return p.fields.action + ":" + (p.fields.secret || ""); });

// The door refuses the order: the failed line beside the button.
var order3 = orderButton(7), win3 = fakeBrowser("granted", "granted", function (f) { return f.action === "subscribe" ? { secret: "s" } : { error: "order" }; });
P.start(win3, fakeDoc(el("html", {}, [order3])));
await settle();
order3.querySelector("[data-pg-push-order-on]").click();
await settle();

// A no: the hint, no order posted.
var order4 = orderButton(8), win4 = fakeBrowser("default", "denied", function () { return {}; });
P.start(win4, fakeDoc(el("html", {}, [order4])));
await settle();
order4.querySelector("[data-pg-push-order-on]").click();
await settle();

// Blocked already, and a browser that cannot subscribe.
var order5 = orderButton(9), win5 = fakeBrowser("denied", "denied", function () { return {}; });
P.start(win5, fakeDoc(el("html", {}, [order5])));
await settle();
var order6 = orderButton(10), win6 = fakeBrowser("default", "granted", function () { return {}; });
delete win6.PushManager;
P.start(win6, fakeDoc(el("html", {}, [order6])));
await settle();
console.log(JSON.stringify({ kept: kept, done: orderShown(order).state, replaced: replaced, done2: orderShown(order2).state,
  failed: orderShown(order3), no: orderShown(order4), noPosts: win4.rec.posts.length, blocked: orderShown(order5), none: orderShown(order6) }));');
	if (!_site_push_block_ran($r)) {
		return;
	}
	pg_assert_same(array(array('action' => 'order', 'secret' => 'kept', 'order_id' => '5')), $r['kept'], 'no new subscription for a subscribed device');
	pg_assert_same('done', $r['done']);
	pg_assert_same(array('order:old', 'subscribe:', 'order:fresh'), $r['replaced'], 'an unknown secret is replaced once');
	pg_assert_same('done', $r['done2']);
	pg_assert_same(array('root' => true, 'on' => true, 'done' => false, 'blocked' => false, 'failed' => true, 'state' => 'ready'), $r['failed']);
	pg_assert_same('blocked', $r['no']['state'], 'a no shows the hint');
	pg_assert_same(0, $r['noPosts'], 'nothing posted after a no');
	pg_assert_same(array('root' => true, 'on' => false, 'done' => false, 'blocked' => true, 'failed' => false, 'state' => 'blocked'), $r['blocked']);
	pg_assert_false($r['none']['root'], 'hidden where the browser cannot subscribe');
}

function test_site_push_block_two_blocks_answer_where_pressed()
{
	$r = _site_push_block_node('
var a = pushBlock(), b = pushBlock(), doc = fakeDoc(el("html", {}, [el("body", {}, [a, b])]));
var win = fakeBrowser("default", "granted", function (f) { return f.action === "subscribe" ? { secret: "s" } : { removed: 1 }; });
P.start(win, doc);
await settle();
b.querySelector("[data-pg-push-on]").click();
await settle();
await heard();
var focused = doc.activeElement === b.querySelector("[data-pg-push-stop]");
console.log(JSON.stringify({ a: shown(a).state, b: shown(b).state, focused: focused, saidA: spoken(a), saidB: spoken(b) }));');
	if (!_site_push_block_ran($r)) {
		return;
	}
	pg_assert_same('on', $r['a'], 'every block on the page shows the state');
	pg_assert_same('on', $r['b']);
	pg_assert_true($r['focused'], 'the focus moves to Stop in the block pressed');
	pg_assert_same(array(), $r['saidA'], 'the other block stays quiet');
	pg_assert_same(array('done' => 'Notifications are on.'), $r['saidB']);
}

// -- the worker (N12) --------------------------------------------------------

function test_site_push_block_worker_shows_the_message()
{
	$r = _site_push_block_node('
var sw = fakeWorkerScope("s3cret", { message: { title: "Sale", body: "Today only", url: "/sale" } });
W.start(sw);
await sw.push();
var none = fakeWorkerScope("", { message: null });
W.start(none);
await none.push();
var empty = fakeWorkerScope("s3cret", { message: null });
W.start(empty);
await empty.push();
console.log(JSON.stringify({ shown: sw.shown, post: sw.posts[0], handlers: Object.keys(sw.on).sort(), none: none.shown, nonePosts: none.posts.length, empty: empty.shown,
  targets: [W.target("/sale", "https://shop.example/"), W.target("https://evil.example/x", "https://shop.example/"), W.target("//evil.example/x", "https://shop.example/"),
    W.target("/other/x", "https://shop.example/site/"), W.target("", "https://shop.example/"), W.target("/site/news?a=1", "https://shop.example/site/")] }));');
	if (!_site_push_block_ran($r)) {
		return;
	}
	pg_assert_same(array('install', 'notificationclick', 'push'), $r['handlers'], 'no fetch handler');
	pg_assert_same(array('action' => 'message', 'secret' => 's3cret'), $r['post']['fields']);
	pg_assert_same('/pg/site_push.php', $r['post']['url']);
	pg_assert_same('Sale', $r['shown'][0]['title']);
	pg_assert_same('Today only', $r['shown'][0]['options']['body']);
	pg_assert_same('/sale', $r['shown'][0]['options']['data']['url']);

	pg_assert_same(0, $r['nonePosts'], 'no secret: the door is not asked');
	foreach (array('none', 'empty') as $k) {
		pg_assert_same('Shop', $r[$k][0]['title'], $k . ': the fixed notice names the site');
		pg_assert_same('Open the site to see what is new.', $r[$k][0]['options']['body']);
		pg_assert_same('pg-site-push', $r[$k][0]['options']['tag']);
	}

	pg_assert_same(array(
		'https://shop.example/sale',
		'https://shop.example/',
		'https://shop.example/',
		'https://shop.example/site/',
		'https://shop.example/',
		'https://shop.example/site/news?a=1',
	), $r['targets']);
}

// -- the block and the switch ------------------------------------------------

function test_site_push_block_family()
{
	$got = pg_test_designer_family_run('push');
	if ($got === null) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}
	pg_assert_same('newsletter-', $got['prefix'], 'the core Forms & Access group');
	pg_assert_same(null, $got['group']);
	pg_assert_same(array('newsletter-notify'), array_keys($got['blocks']));
	pg_assert_same(null, $got['unknown']);

	$b = $got['blocks']['newsletter-notify'];
	pg_assert_same(array('semantic', 'section'), $b['root']);
	pg_assert_same(array('h2'), $b['headings']);
	pg_assert_same(array(array('name' => 'data-pg-push', 'value' => ''), array('name' => 'hidden', 'value' => '')), $b['attrs'], 'the mark, hidden until the script shows it');
	foreach (array('Notify Me', 'Stop', 'Notifications are on.') as $text) {
		pg_assert_true(in_array($text, $b['texts'], true), 'the block says ' . $text);
	}

	$js = pg_test_designer_family_file('push');
	foreach (array('data-pg-push-ask', 'data-pg-push-on', 'data-pg-push-done', 'data-pg-push-stop', 'data-pg-push-blocked', 'data-pg-push-failed') as $mark) {
		pg_assert_contains("'" . $mark . "'", $js);
	}
	pg_assert_false(strpos($js, 'onclick') !== false, 'no script in the block');

	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("'[data-pg-push][hidden] { display:block !important; }'", $core, 'the canvas draws the block');
}

function test_site_push_block_features_card()
{
	$card = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/features.php');
	pg_assert_contains('id="pgset-site-push"', $card);
	pg_assert_contains('<input type="hidden" name="site_push_card" value="1"/>', $card);
	pg_assert_contains("(pg_site_push_switch_on() ? ' checked=\"checked\"' : '')", $card);

	$save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/features.save.php');
	pg_assert_contains("pg_site_push_switch_save((bool) post_value('site_push_on'));", $save);

	$registry = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
	pg_assert_contains("'pgset-site-push' => lang('Visitor notifications'),", $registry);
}
