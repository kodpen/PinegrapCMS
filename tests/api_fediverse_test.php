<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for GET /fediverse/followers and /fediverse/deliveries
 * (includes/api/resources/fediverse.php): the rows as the API answers them,
 * their declarations and the scope that opens them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_settings.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/fediverse.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');

// The time helper lives in includes/api/response.php, which loads only behind
// the API's own entry. A stand-in of the same shape.
if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
	}
}

function test_api_fediverse_follower_present()
{
	$row = api_fediverse_follower_present(array('id' => '3', 'actor_id' => 'https://mastodon.example/users/ali', 'name' => '', 'avatar' => '', 'followed_at' => '1792000000'));

	pg_assert_same(3, $row['id']);
	pg_assert_same('https://mastodon.example/users/ali', $row['actor']);
	pg_assert_same(null, $row['name'], 'no name sent');
	pg_assert_same(null, $row['avatar']);
	pg_assert_same('mastodon.example', $row['server']);
	pg_assert_same(gmdate('Y-m-d\\TH:i:s\\Z', 1792000000), $row['followed_at']);
	pg_assert_same(array_keys(api_fediverse_follower_schema()), array_keys($row), 'the schema names every field');
}

function test_api_fediverse_delivery_present()
{
	$base = array('id' => '8', 'activity_id' => 'https://blog.example/@news/p/4#create-1792000000', 'inbox' => 'https://social.example/inbox', 'attempts' => '2', 'next_at' => '1792000600', 'state' => 'queued', 'created_at' => '1792000000');
	$row = api_fediverse_delivery_present($base);

	pg_assert_same(8, $row['id']);
	pg_assert_same('Create', $row['type']);
	pg_assert_same('https://blog.example/@news/p/4', $row['object']);
	pg_assert_same('social.example', $row['server']);
	pg_assert_same(2, $row['attempts']);
	pg_assert_same(gmdate('Y-m-d\\TH:i:s\\Z', 1792000600), $row['next_attempt_at'], 'a waiting delivery says when');
	pg_assert_same(array_keys(api_fediverse_delivery_schema()), array_keys($row), 'the schema names every field');

	$sent = api_fediverse_delivery_present(array_merge($base, array('state' => 'sent', 'next_at' => '0', 'activity_id' => 'https://blog.example/@news#other')));

	pg_assert_same(null, $sent['next_attempt_at'], 'a sent one has none');
	pg_assert_same(null, $sent['type'], 'an activity the list cannot read');
	pg_assert_same(null, $sent['object']);
}

function test_api_fediverse_routes_and_scope()
{
	$found = array();

	foreach (api_schema() as $route) {
		if (strpos($route['id'], 'fediverse.') === 0) {
			$found[$route['id']] = $route;
		}
	}

	pg_assert_same(array('fediverse.followers', 'fediverse.deliveries'), array_keys($found));

	foreach ($found as $route) {
		pg_assert_same('GET', $route['method'], 'read only');
		pg_assert_same('fediverse:read', $route['scope']);
	}

	pg_assert_same('/fediverse/followers', $found['fediverse.followers']['path']);
	pg_assert_same(array('state', 'limit', 'cursor'), array_column($found['fediverse.deliveries']['params'], 'name'));

	$groups = api_scope_groups();

	pg_assert_same('fediverse:read', $groups['fediverse']['read']);
	pg_assert_same('', $groups['fediverse']['write'], 'nothing to write');
	pg_assert_contains("\$scopes[] = 'fediverse:read';", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/scopes.php'), 'in the owner ceiling');
}
