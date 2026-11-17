<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the number of event subscriptions one application may hold:
 * the limit lives in one function, POST /webhooks counts against it and its
 * 409 text takes the number from it.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/webhooks.php');

function webhook_subscription_limit_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	// The code, not the comments around it.
	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1)));
}

// An application may hold 50 subscriptions.
function test_webhook_subscription_limit_is_fifty()
{
	pg_assert_same(50, api_webhook_subscription_limit());
}

// The limit is reached at the limit, not before.
function test_webhook_subscription_limit_reached_at_the_limit()
{
	pg_assert_false(api_webhook_subscription_limit_reached(0));
	pg_assert_false(api_webhook_subscription_limit_reached(49));
	pg_assert_true(api_webhook_subscription_limit_reached(50));
	pg_assert_true(api_webhook_subscription_limit_reached(51));
}

// POST /webhooks counts against the function and its 409 names the number.
function test_webhook_subscription_limit_used_by_create()
{
	$source = webhook_subscription_limit_test_source('api_webhooks_create');
	pg_assert_contains('api_webhook_subscription_limit_reached(', $source);
	pg_assert_contains("'too_many_webhooks'", $source);
	pg_assert_true(preg_match("~'vars'\s*=>\s*api_webhook_subscription_limit\(\)~", $source) === 1, 'the 409 text takes the number');
	pg_assert_false(strpos($source, '>= 10') !== false);
	pg_assert_false(strpos($source, 'ten subscriptions') !== false);
}
