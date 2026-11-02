<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Cache Rule Cloudflare Tools writes for WebP at a picture's own
 * address (includes/fn/cloudflare_tools.php): the rule, what writing it
 * takes from the read of the zone's cache rules, the header reader and the
 * verdict of the edge check.
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

function test_cloudflare_image_vary_verdict()
{
	$webp = array('status' => 200, 'type' => 'image/webp', 'cloudflare' => true);
	$jpeg = array('status' => 200, 'type' => 'image/jpeg; charset=binary', 'cloudflare' => true);

	pg_assert_same('ok', pg_cloudflare_image_vary_verdict(array($webp, $jpeg, $webp)), 'a copy per format');
	pg_assert_same('shared', pg_cloudflare_image_vary_verdict(array($webp, $webp, $webp)), 'WebP given to everyone');
	pg_assert_same('shared', pg_cloudflare_image_vary_verdict(array($webp, $jpeg, $jpeg)), 'the original given to everyone');
	pg_assert_same('no_webp', pg_cloudflare_image_vary_verdict(array($jpeg, $jpeg, $jpeg)), 'no WebP made');
	pg_assert_same('not_proxied', pg_cloudflare_image_vary_verdict(array(
		array('status' => 200, 'type' => 'image/webp'), array('status' => 200, 'type' => 'image/jpeg'), array('status' => 200, 'type' => 'image/webp'))), 'not through Cloudflare');
	pg_assert_same('unreachable', pg_cloudflare_image_vary_verdict(array($webp, array('status' => 403, 'type' => 'text/html', 'cloudflare' => true), $webp)), 'a refusal');
	pg_assert_same('unreachable', pg_cloudflare_image_vary_verdict(array($webp, array('status' => 200, 'type' => 'text/html', 'cloudflare' => true), $webp)), 'a page, not a picture');
	pg_assert_same('unreachable', pg_cloudflare_image_vary_verdict(array($webp)), 'too few');
}
