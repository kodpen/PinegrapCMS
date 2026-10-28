<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the address a site log row records (log_activity() in
 * includes/fn/forms.php and its copy in get_file.php): both take
 * pg_auth_request_address(), the address the session rows carry, which is
 * the visitor the firewall resolves behind a trusted proxy and the socket
 * peer otherwise (the proxy rule itself is the firewall's, tested with it).
 * The command line, where the scheduled jobs run, has no
 * address; the Activity card prints no "IP" label for such a row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');

// pg_auth_request_address() as a request would see it, in a PHP process of
// its own: waf_client_ip() remembers its first answer for the request, so one
// process answers for one set of server variables. $server holds the
// request's $_SERVER keys; $with_firewall loads waf.php the way router.php
// and init.php do. Returns the address, or the process output when that is
// not the expected JSON.
function log_activity_ip_test_address(array $server, $with_firewall)
{
	$scenario = tempnam(sys_get_temp_dir(), 'pg_log_ip_scenario_');
	$runner = tempnam(sys_get_temp_dir(), 'pg_log_ip_run_');

	file_put_contents($scenario, json_encode(array('server' => $server, 'firewall' => (bool) $with_firewall)));

	file_put_contents($runner, '<?php $scenario = json_decode(file_get_contents($argv[1]), true);'
		. ' foreach ($scenario["server"] as $name => $value) { $_SERVER[$name] = $value; }'
		. ' require $argv[2] . "/functions.php";'
		. ' if ($scenario["firewall"]) { require PG_FUNCTIONS_DIR . "/waf.php"; }'
		. ' echo json_encode(array("address" => pg_auth_request_address()));');

	$command = escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 -d memory_limit=-1 '
		. escapeshellarg($runner) . ' ' . escapeshellarg($scenario) . ' ' . escapeshellarg(PG_FUNCTIONS_DIR);

	$result = (string) shell_exec($command . ' 2>&1');

	unlink($scenario);
	unlink($runner);

	$decoded = json_decode($result, true);

	return (is_array($decoded) && isset($decoded['address'])) ? $decoded['address'] : $result;
}

// The body of a function in a source file that is not loaded here, from its
// "function" line to its closing brace.
function log_activity_ip_test_function_source($source, $name)
{
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n}\n", $start);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end + 2 - $start);
}

// Outside a request, as the scheduled jobs run, there is no address: the row
// keeps an empty log_ip, with or without the firewall loaded.
function test_log_activity_ip_command_line_has_no_address()
{
	pg_assert_same('', log_activity_ip_test_address(array(), false));
}

// Without the firewall the address is the socket peer; a forwarded header is
// the client's own claim and never replaces it.
function test_log_activity_ip_peer_without_the_firewall()
{
	$server = array(
		'REMOTE_ADDR' => '::1',
		'HTTP_X_FORWARDED_FOR' => '85.99.245.119',
		'HTTP_CF_CONNECTING_IP' => '85.99.245.119',
	);

	pg_assert_same('::1', log_activity_ip_test_address($server, false));
}



// Both log_activity() bodies take the address from pg_auth_request_address()
// and read REMOTE_ADDR nowhere themselves; the e-mail-a-friend daily limit
// counts the rows by the same address, or it would never match them.
function test_log_activity_ip_both_copies_and_the_limit_share_the_helper()
{
	$forms = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/forms.php');
	$body = log_activity_ip_test_function_source($forms, 'log_activity');
	pg_assert_contains('$ip = pg_auth_request_address();', $body, 'includes/fn/forms.php');
	pg_assert_same(false, strpos($body, 'REMOTE_ADDR'), 'includes/fn/forms.php reads no REMOTE_ADDR');

	$copy = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_file.php');
	$body = log_activity_ip_test_function_source($copy, 'log_activity');
	pg_assert_contains('escape(pg_auth_request_address())', $body, 'get_file.php');
	pg_assert_same(false, strpos($body, 'REMOTE_ADDR'), 'get_file.php reads no REMOTE_ADDR');

	$limit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/email_a_friend.php');
	pg_assert_contains('(log_ip = ?)', $limit);
	pg_assert_contains('db_q($query, array(pg_auth_request_address()))', $limit, 'the limit counts by the logged address');
	pg_assert_same(false, strpos($limit, 'REMOTE_ADDR'), 'email_a_friend.php reads no REMOTE_ADDR');
}

// The Activity card prints the address label only with an address: a job's
// row never reads "... · IP".
function test_log_activity_ip_empty_address_prints_no_label()
{
	$description = 'general job published 2 scheduled comment(s)';

	pg_assert_same(h($description), pg_dashboard_log_meta($description, ''));
	pg_assert_same(false, strpos(pg_dashboard_log_meta('user logged in', ''), '&middot;'));
	pg_assert_contains('&middot; ' . h(lang(array('string' => 'IP {var:1}', 'vars' => array('85.99.245.119')))), pg_dashboard_log_meta('user logged in', '85.99.245.119'));
}
