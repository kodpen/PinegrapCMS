<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Cache Rule Cloudflare Tools writes for WebP at a picture's own
 * address (includes/fn/cloudflare_tools.php): the rule, what writing it
 * takes from the read of the zone's cache rules and the header reader; and
 * the token-free self-test behind the CDN confirmation
 * (includes/fn/image_vary_check.php): its verdicts, what they change and
 * when it is due.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/cloudflare_tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/image_vary_check.php');

function cloudflare_image_vary_ruleset($rules)
{
	return array('status' => 200, 'json' => array('success' => true, 'result' => array(
		'id' => str_repeat('a', 32), 'phase' => 'http_request_cache_settings', 'rules' => $rules)));
}

function test_cloudflare_image_vary_rule_shape()
{
	$rule = pg_cloudflare_image_vary_rule();

	pg_assert_same('set_cache_settings', $rule['action'], 'action');
	pg_assert_same('pinegrap_webp_same_address', $rule['ref'], 'ref');
	pg_assert_true(strpos($rule['expression'], '"jpg" "jpeg" "jpe" "png"') !== false, 'the negotiated extensions');
	pg_assert_same(array('action' => 'normalize', 'media_types' => array('image/webp')), $rule['action_parameters']['vary']['headers']['accept'], 'Accept normalised to WebP');
	pg_assert_same(array('action' => 'passthrough'), $rule['action_parameters']['vary']['default'], 'default required by Cloudflare');
	pg_assert_true(pg_cloudflare_image_vary_rule_current($rule), 'the rule is current');
	pg_assert_true(strpos(json_encode($rule), '"media_types":["image\/webp"]') !== false, 'a JSON list');
}

function test_cloudflare_image_vary_plan()
{
	pg_assert_same('create', pg_cloudflare_image_vary_plan(array('status' => 404, 'json' => array('success' => false)))['do'], 'no entrypoint yet');
	pg_assert_same('failed', pg_cloudflare_image_vary_plan(array('status' => 403, 'json' => array('success' => false)))['do'], 'no permission');
	pg_assert_same('failed', pg_cloudflare_image_vary_plan(array('status' => 0, 'json' => null))['do'], 'no answer');

	$other = array('id' => str_repeat('b', 32), 'action' => 'set_cache_settings', 'expression' => 'true');
	$plan = pg_cloudflare_image_vary_plan(cloudflare_image_vary_ruleset(array($other)));
	pg_assert_same(array('do' => 'add', 'ruleset_id' => str_repeat('a', 32), 'rule_id' => ''), $plan, 'other rules only');

	$ours = pg_cloudflare_image_vary_rule();
	$ours['id'] = str_repeat('c', 32);
	pg_assert_same('none', pg_cloudflare_image_vary_plan(cloudflare_image_vary_ruleset(array($other, $ours)))['do'], 'in place');

	$old = $ours;
	$old['action_parameters']['vary']['headers']['accept']['action'] = 'passthrough';
	pg_assert_same(array('do' => 'update', 'ruleset_id' => str_repeat('a', 32), 'rule_id' => str_repeat('c', 32)), pg_cloudflare_image_vary_plan(cloudflare_image_vary_ruleset(array($old))), 'changed by hand');

	$off = $ours;
	$off['enabled'] = false;
	pg_assert_same('update', pg_cloudflare_image_vary_plan(cloudflare_image_vary_ruleset(array($off)))['do'], 'disabled');

	$bad = cloudflare_image_vary_ruleset(array());
	$bad['json']['result']['id'] = '../../zones';
	pg_assert_same('failed', pg_cloudflare_image_vary_plan($bad)['do'], 'an id that is not hex');
}

function test_cloudflare_header_value()
{
	$block = "HTTP/1.1 100 Continue\r\n\r\nHTTP/2 200\r\ncontent-type: image/webp\r\nCF-Ray: 8c1f-IST\r\nVary: Accept\r\n";

	pg_assert_same('image/webp', pg_cloudflare_header_value($block, 'Content-Type'), 'case');
	pg_assert_same('8c1f-IST', pg_cloudflare_header_value($block, 'cf-ray'), 'cf-ray');
	pg_assert_same('', pg_cloudflare_header_value($block, 'age'), 'missing');
	pg_assert_same('image/png', pg_cloudflare_header_value("HTTP/1.1 301\r\nContent-Type: text/html\r\n\r\nHTTP/1.1 200\r\nContent-Type: image/png\r\n", 'content-type'), 'the last answer');
}

function test_image_vary_verdict()
{
	$webp = array('status' => 200, 'type' => 'image/webp', 'proxied' => true);
	$jpeg = array('status' => 200, 'type' => 'image/jpeg; charset=binary', 'proxied' => true);
	$webp_hit = $webp + array('hit' => true);
	$jpeg_hit = $jpeg + array('hit' => true);

	pg_assert_same('ok', pg_image_vary_verdict(array($webp, $jpeg, $webp_hit, $jpeg_hit)), 'both copies came back from the edge');
	pg_assert_same('shared', pg_image_vary_verdict(array($webp, $webp_hit, $webp_hit, $webp_hit)), 'WebP given to everyone');
	pg_assert_same('shared', pg_image_vary_verdict(array($webp, $jpeg, $jpeg_hit, $jpeg_hit)), 'the original given to everyone');
	pg_assert_same('shared', pg_image_vary_verdict(array($webp, $jpeg, $webp_hit, $webp_hit)), 'the WebP copy given to the last reader');
	pg_assert_same('no_webp', pg_image_vary_verdict(array($jpeg, $jpeg, $jpeg, $jpeg)), 'no WebP made');

	// The right type every time is no proof by itself: four answers that all
	// reached the server (a site whose own requests skip the edge's cache) say
	// nothing about what the edge gives a visitor.
	pg_assert_same('not_cached', pg_image_vary_verdict(array($webp, $jpeg, $webp, $jpeg)), 'no answer from the cache');
	pg_assert_same('not_cached', pg_image_vary_verdict(array($webp, $jpeg, $webp_hit, $jpeg)), 'only the WebP copy kept');
	pg_assert_same('not_cached', pg_image_vary_verdict(array($webp, $jpeg, $webp, $jpeg_hit)), 'only the original kept');

	pg_assert_same('not_proxied', pg_image_vary_verdict(array(
		array('status' => 200, 'type' => 'image/webp'), array('status' => 200, 'type' => 'image/jpeg'),
		array('status' => 200, 'type' => 'image/webp'), array('status' => 200, 'type' => 'image/jpeg'))), 'not through a CDN');
	pg_assert_same('unreachable', pg_image_vary_verdict(array($webp, array('status' => 403, 'type' => 'text/html', 'proxied' => true), $webp_hit, $jpeg_hit)), 'a refusal');
	pg_assert_same('unreachable', pg_image_vary_verdict(array($webp, array('status' => 200, 'type' => 'text/html', 'proxied' => true), $webp_hit, $jpeg_hit)), 'a page, not a picture');
	pg_assert_same('unreachable', pg_image_vary_verdict(array($webp, $jpeg, $webp_hit)), 'too few');
}

// Whether an answer came out of a cache rather than from the server.
function test_image_vary_answer_hit()
{
	pg_assert_true(pg_image_vary_answer_hit("HTTP/2 200\r\ncf-cache-status: HIT\r\nage: 0\r\n"), 'Cloudflare hit');
	pg_assert_true(pg_image_vary_answer_hit("HTTP/2 200\r\ncf-cache-status: REVALIDATED\r\n"), 'Cloudflare revalidated');
	pg_assert_true(pg_image_vary_answer_hit("HTTP/1.1 200 OK\r\nX-Cache: Hit from cloudfront\r\n"), 'CloudFront');
	pg_assert_true(pg_image_vary_answer_hit("HTTP/1.1 200 OK\r\nX-Cache: MISS, HIT\r\n"), 'Fastly, a hit on one layer');
	pg_assert_true(pg_image_vary_answer_hit("HTTP/1.1 200 OK\r\nAge: 12\r\n"), 'an Age from any cache');
	pg_assert_false(pg_image_vary_answer_hit("HTTP/2 200\r\ncf-cache-status: MISS\r\n"), 'Cloudflare miss');
	pg_assert_false(pg_image_vary_answer_hit("HTTP/2 200\r\ncf-cache-status: DYNAMIC\r\n"), 'not cached at all');
	pg_assert_false(pg_image_vary_answer_hit("HTTP/1.1 200 OK\r\nX-Cache: MISS\r\nVia: 1.1 varnish\r\n"), 'a proxy that missed');
	pg_assert_false(pg_image_vary_answer_hit("HTTP/1.1 200 OK\r\nAge: soon\r\n"), 'an Age that is no number');
	pg_assert_false(pg_image_vary_answer_hit("HTTP/1.1 200 OK\r\nAge: 3\r\n\r\nHTTP/1.1 200 OK\r\ncf-cache-status: MISS\r\n"), 'only the last answer counts');
	pg_assert_false(pg_image_vary_answer_hit(''), 'nothing');
}

// The private address asked with WebP, then without: a second WebP means the
// edge kept the private answer.
function test_image_vary_private_verdict()
{
	$webp = array('status' => 200, 'type' => 'image/webp', 'proxied' => true);
	$jpeg = array('status' => 200, 'type' => 'image/jpeg', 'proxied' => true);

	pg_assert_same('honoured', pg_image_vary_private_verdict(array($webp, $jpeg)), 'nothing kept');
	pg_assert_same('kept', pg_image_vary_private_verdict(array($webp, $webp)), 'the private WebP kept for everyone');
	pg_assert_same('no_webp', pg_image_vary_private_verdict(array($jpeg, $jpeg)), 'no WebP');
	pg_assert_same('unreachable', pg_image_vary_private_verdict(array($webp, array('status' => 500, 'type' => 'text/html'))), 'an error');
	pg_assert_same('unreachable', pg_image_vary_private_verdict(array($webp)), 'too few');
}

// Which CDN headers come back on an answer.
function test_image_vary_answer_proxied()
{
	pg_assert_true(pg_image_vary_answer_proxied("HTTP/1.1 200 OK\r\nCF-Ray: 1-IST\r\n"), 'Cloudflare');
	pg_assert_true(pg_image_vary_answer_proxied("HTTP/1.1 200 OK\r\nVia: 1.1 abc.cloudfront.net (CloudFront)\r\nX-Amz-Cf-Id: x\r\n"), 'CloudFront');
	pg_assert_true(pg_image_vary_answer_proxied("HTTP/1.1 200 OK\r\nx-cache: MISS\r\n"), 'X-Cache');
	pg_assert_false(pg_image_vary_answer_proxied("HTTP/1.1 200 OK\r\nContent-Type: image/webp\r\nVia:\r\n"), 'none, an empty Via');
	pg_assert_false(pg_image_vary_answer_proxied(''), 'nothing');
}

// What a self-test changes: bit 8 and the mode get_file.php reads.
function test_image_vary_outcome()
{
	pg_assert_same(array('cdn' => 'set', 'mode' => 'public'), pg_image_vary_outcome('ok', '', false), 'a copy per format: confirmed');
	pg_assert_same(array('cdn' => '', 'mode' => 'public'), pg_image_vary_outcome('ok', '', true), 'already confirmed');
	pg_assert_same(array('cdn' => '', 'mode' => 'private'), pg_image_vary_outcome('shared', 'honoured', false), 'one copy, private honoured');
	pg_assert_same(array('cdn' => '', 'mode' => 'original'), pg_image_vary_outcome('shared', 'kept', false), 'private kept too: the original');
	pg_assert_same(array('cdn' => 'clear', 'mode' => 'private'), pg_image_vary_outcome('shared', 'honoured', true), 'confirmation taken back');
	pg_assert_same(array('cdn' => 'clear', 'mode' => 'original'), pg_image_vary_outcome('shared', 'kept', true), 'taken back, private kept');
	pg_assert_same(array('cdn' => '', 'mode' => ''), pg_image_vary_outcome('shared', 'unreachable', false), 'the private test failed: unchanged');
	pg_assert_same(array('cdn' => '', 'mode' => 'private'), pg_image_vary_outcome('not_proxied', '', false), 'nothing in between');
	pg_assert_same(array('cdn' => 'clear', 'mode' => ''), pg_image_vary_outcome('not_cached', '', true), 'a confirmation the edge did not prove is taken back');
	pg_assert_same(array('cdn' => '', 'mode' => ''), pg_image_vary_outcome('not_cached', '', false), 'unproven: no confirmation, the last mode stays');
	pg_assert_same(array('cdn' => '', 'mode' => ''), pg_image_vary_outcome('unreachable', '', false), 'unreachable: unchanged');
	pg_assert_same(array('cdn' => '', 'mode' => ''), pg_image_vary_outcome('no_webp', '', true), 'no WebP: unchanged');
	pg_assert_same(array('cdn' => '', 'mode' => ''), pg_image_vary_outcome('no_sample', '', false), 'no picture: unchanged');
}

// When the general job runs the self-test.
function test_image_vary_test_due()
{
	$now = 1800000000;

	pg_assert_false(pg_image_vary_test_due(null, 3, $now), 'switch off');
	pg_assert_true(pg_image_vary_test_due(null, 5, $now), 'never tested');

	// A confirmation is tested too, so one ticked by hand or by an earlier
	// test is taken back once the edge stops keeping a copy per format.
	pg_assert_true(pg_image_vary_test_due(null, 13, $now), 'confirmed, never tested');
	pg_assert_true(pg_image_vary_test_due(array('time' => $now - 60, 'bits' => 5, 'verdict' => 'shared'), 13, $now), 'ticked by hand');
	pg_assert_false(pg_image_vary_test_due(array('time' => $now - 3600, 'bits' => 13, 'verdict' => 'ok'), 13, $now), 'confirmed an hour ago');
	pg_assert_true(pg_image_vary_test_due(array('time' => $now - 86401, 'bits' => 13, 'verdict' => 'ok'), 13, $now), 'confirmed a day ago');
	pg_assert_true(pg_image_vary_test_due(array('time' => $now - 60, 'bits' => 13, 'verdict' => 'ok'), 5, $now), 'bit 8 taken back');
	pg_assert_true(pg_image_vary_test_due(array('time' => $now - 60, 'bits' => 1, 'verdict' => 'shared'), 5, $now), 'switched on');
	pg_assert_false(pg_image_vary_test_due(array('time' => $now - 3600, 'bits' => 5, 'verdict' => 'shared'), 5, $now), 'tested an hour ago');
	pg_assert_true(pg_image_vary_test_due(array('time' => $now - 86401, 'bits' => 5, 'verdict' => 'shared'), 5, $now), 'a day later');
	pg_assert_false(pg_image_vary_test_due(array('time' => $now - 86401, 'bits' => 5, 'verdict' => 'not_proxied'), 5, $now), 'not proxied: a week');
	pg_assert_true(pg_image_vary_test_due(array('time' => $now - 604801, 'bits' => 5, 'verdict' => 'not_proxied'), 5, $now), 'not proxied, a week later');
	pg_assert_true(pg_image_vary_test_due(array('time' => 'x'), 5, $now), 'a broken state');
}
