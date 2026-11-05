<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the Site Log lines about an event subscription name its address
 * as scheme and host only (pg_ops_alert_mask_url()): the API's change and
 * secret renewal (includes/api/resources/webhooks.php) and the panel's
 * stop/resume and removal (api_settings.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every log_activity() call whose text names an API event subscription, as
// source text up to the end of the call's statement.
function webhook_log_address_test_calls($file)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$calls = array();
	$offset = 0;

	while (($at = strpos($source, 'log_activity(', $offset)) !== false) {
		$end = strpos($source, ');', $at);
		$end = ($end === false) ? strlen($source) : $end;
		$call = substr($source, $at, $end - $at);

		if (strpos($call, 'API event subscription') !== false) {
			$calls[] = $call;
		}

		$offset = $at + 1;
	}

	return $calls;
}

// The Site Log is read by every manager and by API applications holding
// site_log:read, and Slack, Discord and Zapier addresses carry their secret
// in the path, so a log line never holds the stored address as it is.
function test_webhook_log_lines_mask_the_subscription_address()
{
	$expected = array(
		'includes/api/resources/webhooks.php' => 2,
		'api_settings.php'                    => 2,
	);

	foreach ($expected as $file => $count) {
		$calls = webhook_log_address_test_calls($file);

		pg_assert_same($count, count($calls), $file . ': subscription log lines found');

		foreach ($calls as $call) {
			pg_assert_true(strpos($call, 'pg_ops_alert_mask_url(') !== false, $file . ': address masked in ' . substr($call, 0, 120));
			pg_assert_false((bool) preg_match('/(?<!pg_ops_alert_mask_url\()\$(row|hook)\[\'url\'\]/', $call), $file . ': no bare stored address');
		}
	}
}

// What the mask leaves of a secret-carrying address.
function test_webhook_log_address_mask_cuts_the_secret()
{
	$masked = pg_ops_alert_mask_url('https://hooks.zapier.com/hooks/catch/123/SECRETPATH/?token=abc');

	pg_assert_same('https://hooks.zapier.com/…', $masked, 'scheme and host');
}
