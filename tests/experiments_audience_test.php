<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for audience conditions (includes/fn/page_audience.php): each
 * token's value from fixed requests, the cache rule a page's conditions
 * give, the per-value copy names, and the editor's list against the
 * server's.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A paragraph outside any widget shown when $token passes $op ($value).
function _aud_node($text, $token, $op = 'not_empty', $value = '')
{
	return array('type' => 'semantic', 'props' => array('tag' => 'p', 'cssClass' => 'x', 'text' => $text,
		'_bindings' => array('show_if' => $token),
		'_bindParams' => array('show_if' => array('op' => $op, 'value' => $value))), 'children' => array());
}

function _aud_root($children)
{
	return array('type' => 'root', 'props' => array(), 'children' => $children);
}

function test_experiments_audience_token_kinds()
{
	pg_assert_same('returning', pg_audience_token_kind('__audience_returning'));
	pg_assert_same('country', pg_audience_token_kind('__AUDIENCE_COUNTRY'), 'read in lower case');
	pg_assert_same('device', pg_audience_token_kind('__audience_device'));
	pg_assert_same('utm_campaign', pg_audience_token_kind('__audience_utm_campaign'));
	pg_assert_same('member', pg_audience_token_kind('__audience_member'));
	pg_assert_same('group', pg_audience_token_kind('__audience_group_12'));
	pg_assert_same('experiment', pg_audience_token_kind('__audience_experiment_3'));
	pg_assert_same(12, pg_audience_token_id('__audience_group_12'));
	pg_assert_same(0, pg_audience_token_id('__audience_country'));
	foreach (array('__audience_group_0', '__audience_group_', '__audience_group_x', '__audience_city', '__visit_signed_in', '__audience_experiment_01') as $token) {
		pg_assert_same('', pg_audience_token_kind($token), $token . ' is no audience token');
	}
}

function test_experiments_audience_device_from_user_agents()
{
	$cases = array(
		'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148' => 'phone',
		'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/120.0 Mobile Safari/537.36' => 'phone',
		'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148' => 'tablet',
		'Mozilla/5.0 (Linux; Android 13; SM-X700) AppleWebKit/537.36 Chrome/120.0 Safari/537.36' => 'tablet',
		'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36' => 'desktop',
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 Version/17.0 Safari/605.1.15' => 'desktop',
		'' => 'desktop',
	);
	foreach ($cases as $ua => $device) {
		pg_assert_same($device, pg_audience_device($ua), $ua);
	}
	pg_assert_same(array('device' => 'phone'), pg_audience_request_values(array('device'), array('HTTP_USER_AGENT' => 'x iPhone y')));
	pg_assert_same(array(), pg_audience_request_values(array(), array()), 'no kinds, no values');
}

function test_experiments_audience_country_value()
{
	pg_assert_same('TR', pg_audience_country_value(' tr '));
	pg_assert_same('T1', pg_audience_country_value('T1'), 'Tor as Cloudflare names it');
	pg_assert_same('', pg_audience_country_value('TUR'));
	pg_assert_same('', pg_audience_country_value(''));
	pg_assert_same('', pg_audience_country_value('X-'));
}

function test_experiments_audience_returning()
{
	$now = 1800000000;
	pg_assert_same('', pg_audience_returning(null, $now), 'no cookie: a first visit');
	pg_assert_same('', pg_audience_returning((string) ($now - 60), $now), 'first seen a minute ago: the same visit');
	pg_assert_same('1', pg_audience_returning((string) ($now - PG_AUDIENCE_VISIT_GAP), $now), 'half an hour ago: a new visit');
	pg_assert_same('1', pg_audience_returning((string) ($now - 86400 * 30), $now));
	pg_assert_same('', pg_audience_returning((string) ($now + 600), $now), 'a time to come reads as a first visit');
	pg_assert_same('', pg_audience_returning('abc', $now));
	pg_assert_same('', pg_audience_returning(array('1'), $now));
}

function test_experiments_audience_utm_from_request_then_visit()
{
	pg_assert_same('mail', pg_audience_utm('utm_source', array('utm_source' => 'mail'), array('utm_source' => 'ads')), 'the request first');
	pg_assert_same('ads', pg_audience_utm('utm_source', array(), array('utm_source' => 'ads')), 'then the visit');
	pg_assert_same('ads', pg_audience_utm('utm_source', array('utm_source' => '  '), array('utm_source' => 'ads')), 'a blank parameter is none');
	pg_assert_same('', pg_audience_utm('utm_campaign', array(), array()));
	pg_assert_same('ab', pg_audience_utm('utm_campaign', array('utm_campaign' => "a\nb"), array()), 'control characters dropped');
	pg_assert_same(100, mb_strlen(pg_audience_utm('utm_campaign', array('utm_campaign' => str_repeat('x', 300)), array())), 'at most 100');
	pg_assert_same('', pg_audience_utm('utm_source', array('utm_source' => array('x')), array()), 'an array is no value');
}

function test_experiments_audience_cache_rule()
{
	pg_assert_same(array('vary' => array(), 'personal' => false), pg_audience_cache_rule(array()));
	pg_assert_same(array('vary' => array('country', 'device'), 'personal' => false), pg_audience_cache_rule(array('device', 'country')), 'country before device');
	pg_assert_same(array('vary' => array(), 'personal' => false), pg_audience_cache_rule(array('member', 'group')),
		'member and group are read for a signed-in visitor only, whom no cache serves');
	foreach (array('returning', 'utm_source', 'utm_campaign', 'experiment') as $kind) {
		pg_assert_true(pg_audience_cache_rule(array($kind))['personal'], $kind . ' makes the answer personal');
	}
	pg_assert_same(array('vary' => array('country'), 'personal' => true), pg_audience_cache_rule(array('country', 'returning')));
}

function test_experiments_audience_cache_decision_per_tree()
{
	$tree = _aud_root(array(
		_aud_node('Hello Turkey', '__audience_country', 'eq', 'TR'),
		array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
			_aud_node('Phones only', '__audience_device', 'eq', 'phone'),
		)),
		_aud_node('Signed in', '__visit_signed_in'),
	));
	$html = _render_tree_node($tree, 0, 0);
	pg_assert_same(array('__audience_country', '__audience_device'), pg_audience_html_tokens($html));
	pg_assert_same(array('vary' => array('country', 'device'), 'personal' => false),
		pg_audience_cache_rule(pg_audience_token_kinds(pg_audience_html_tokens($html))));

	// A span a condition around it cuts still counts: the copy must not
	// depend on which spans one visit reached.
	$outer = _aud_node('', '__audience_country', 'eq', 'TR');
	$outer['props']['tag'] = 'div';
	unset($outer['props']['text']);
	$outer['children'] = array(_aud_node('Welcome back', '__audience_returning'));
	$html = _render_tree_node(_aud_root(array($outer)), 0, 0);
	pg_assert_same(array('vary' => array('country'), 'personal' => true),
		pg_audience_cache_rule(pg_audience_token_kinds(pg_audience_html_tokens($html))));

	pg_assert_same(array(), pg_audience_html_tokens(_render_tree_node(_aud_root(array(_aud_node('x', '__visit_signed_in'))), 0, 0)),
		'a page without one is kept as before');
	pg_assert_same(array(), pg_audience_html_tokens('<!--pg-pf:0123456789ab:@@-->x<!--/pg-pf:0123456789ab-->'), 'an unreadable spec');
}

function test_experiments_audience_conditions_decide_spans()
{
	$tree = _aud_root(array(
		_aud_node('TR', '__audience_country', 'eq', 'tr'),
		_aud_node('Not TR', '__audience_country', 'neq', 'TR'),
		_aud_node('Phone', '__audience_device', 'eq', 'phone'),
		_aud_node('Group 7', '__audience_group_7'),
		_aud_node('Variant B', '__audience_experiment_4', 'eq', 'b'),
	));
	$html = _render_tree_node($tree, 0, 0);
	pg_assert_contains('<!--pg-pf:', $html, 'audience conditions are page conditions');

	$seen = pg_page_facts_resolve($html, array(
		'__audience_country' => 'TR', '__audience_device' => 'desktop',
		'__audience_group_7' => '1', '__audience_experiment_4' => 'b',
	));
	pg_assert_contains('>TR<', $seen);
	pg_assert_false(strpos($seen, 'Not TR') !== false, 'the other country goes');
	pg_assert_false(strpos($seen, 'Phone') !== false, 'a desktop sees no phone block');
	pg_assert_contains('Group 7', $seen);
	pg_assert_contains('Variant B', $seen);

	$seen = pg_page_facts_resolve($html, array(
		'__audience_country' => 'DE', '__audience_device' => 'phone',
		'__audience_group_7' => '', '__audience_experiment_4' => 'a',
	));
	pg_assert_contains('Not TR', $seen);
	pg_assert_contains('Phone', $seen);
	pg_assert_false(strpos($seen, 'Group 7') !== false);
	pg_assert_false(strpos($seen, 'Variant B') !== false);

	$cond = pg_sw_cond_specs($tree['children'][3]['props']);
	pg_assert_true(pg_page_fact_spec($cond), 'a group token is a page fact');
}

function test_experiments_audience_background_reads_nothing()
{
	pg_assert_same('', pg_audience_value('__audience_device', true), 'an e-mail has no device');
	pg_assert_same('', pg_audience_value('__visit_signed_in'), 'another token is not read here');
}

function test_experiments_audience_cache_key_per_value()
{
	$key = sha1('page');
	$tr = pg_audience_cache_key($key, array('country'), array('country' => 'TR'));
	pg_assert_true((bool) preg_match('/^[0-9a-f]{40}$/', $tr), 'a key');
	pg_assert_same($tr, pg_audience_cache_key($key, array('country'), array('country' => 'TR', 'device' => 'phone')),
		'only the kinds the page is kept by');
	pg_assert_true($tr !== pg_audience_cache_key($key, array('country'), array('country' => 'DE')), 'one copy per country');
	pg_assert_true($tr !== $key, 'never the address key');
	pg_assert_true(pg_audience_cache_key($key, array('country', 'device'), array('country' => 'TR', 'device' => 'phone'))
		!== pg_audience_cache_key($key, array('country', 'device'), array('country' => 'TR', 'device' => 'tablet')));
	pg_assert_same(array('country', 'device'), pg_audience_vary_clean(array('device', 'x', 'country')));
	pg_assert_same(array(), pg_audience_vary_clean('country'));
}

function test_experiments_audience_stored_copy_keeps_kinds()
{
	$entry = pg_page_cache_unpack(pg_page_cache_pack(array('created' => 5, 'headers' => array(), 'page_id' => 3, 'aud' => array('device', 'country', 'x')), 'body'));
	pg_assert_same(array('country', 'device'), $entry['meta']['aud']);
	$entry = pg_page_cache_unpack(pg_page_cache_pack(array('created' => 5, 'headers' => array(), 'page_id' => 3), 'body'));
	pg_assert_same(array(), $entry['meta']['aud'], 'a copy without them');
}

function test_experiments_audience_returning_cookie_keeps_page_cacheable()
{
	pg_assert_same(array('other'), pg_page_cache_set_cookie_names(array(
		'Set-Cookie: pg_ret=1800000000; path=/', 'Set-Cookie: other=1', 'Set-Cookie: pg_exp=1:a')));
}

// The editor offers the tokens the server reads (SD_AUDIENCE_FACTS).
function test_experiments_audience_editor_list_matches()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$from = strpos($js, "\n    var SD_AUDIENCE_FACTS = [");
	pg_assert_true($from !== false, 'SD_AUDIENCE_FACTS is found');
	$block = substr($js, $from, strpos($js, '];', $from) - $from);
	preg_match_all("/\\['(__audience_[a-z_]+)'/", $block, $m);
	pg_assert_same(pg_audience_tokens(), $m[1]);
	pg_assert_contains("/^__audience_(group|experiment)_[1-9][0-9]{0,9}$/", $js, 'the twin of pg_audience_token_kind()');
}
