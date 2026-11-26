<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A lead from a Site Settings pane to a service's page of Connections keeps
 * what the pane held: pgSettingsLead() reads the lead and names the drawer
 * it sat in, pgSettingsKeptSecret() leaves a password or key field out of
 * what the browser keeps, pgSettingsKeptDue() says when the pane painted
 * again puts it back (backend.src.js). Run under Node, skipped where Node
 * cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs one of the three functions on each case (an argument list); null
// when Node cannot run.
function settings_lead_kept_test_run($name, $cases)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$found = preg_match('/^window\.' . $name . ' = function \([^)]*\) \{.*?^\};$/ms', $source, $m);

	pg_assert_same(1, $found, 'backend.src.js defines window.' . $name);

	if (!$found || !function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pglead');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var window = {};' . "\n" . $m[0] . "\n"
		. 'console.log(JSON.stringify(' . json_encode($cases) . '.map(function (c) { return window.' . $name . '.apply(null, c); })));');
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function test_settings_lead_reads_the_card_and_adds_the_drawer()
{
	$got = settings_lead_kept_test_run('pgSettingsLead', array(
		array('/software/connections.php?service=stripe&from=commerce%2Fpgset-payments', ''),
		array('/software/connections.php?service=openai&from=workspace_assistants%2Fpgset-ws-agents#connections-details', 'pgset-ws-agent-0'),
		array('connections.php?service=google&from=email%2Fpgset-ws-mail%2Fpgset-ws-mail-box-4&x=1', 'pgset-ws-mail-setup-google'),
		array('/software/connections.php?service=stripe', 'pgset-x'),
		array('/software/settings_commerce.php?from=commerce%2Fpgset-payments', ''),
		array('/software/connections.php?service=stripe&from=commerce%2Fhttps%3A%2F%2Fevil', ''),
		array('/software/connections.php?service=stripe&from=commerce%2Fpgset-payments', 'Bad Drawer"'),
		array('/software/connections.php?service=stripe&from=%E0%A4%A', ''),
	));

	if ($got === null) {
		return;
	}

	pg_assert_same(array('pane' => 'commerce', 'card' => 'pgset-payments', 'drawer' => '',
		'href' => '/software/connections.php?service=stripe&from=commerce%2Fpgset-payments'), $got[0], 'a lead on the pane: its card, no drawer');
	pg_assert_same(array('pane' => 'workspace_assistants', 'card' => 'pgset-ws-agents', 'drawer' => 'pgset-ws-agent-0',
		'href' => '/software/connections.php?service=openai&from=workspace_assistants%2Fpgset-ws-agents%2Fpgset-ws-agent-0#connections-details'), $got[1], 'a lead in a drawer names the drawer, the anchor kept');
	pg_assert_same('pgset-ws-mail-box-4', $got[2]['drawer'], 'a drawer the lead already names stays');
	pg_assert_same('connections.php?service=google&from=email%2Fpgset-ws-mail%2Fpgset-ws-mail-box-4&x=1', $got[2]['href'], 'and its address is left as it is');
	pg_assert_same(null, $got[3], 'a page of Connections without from= is no lead');
	pg_assert_same(null, $got[4], 'another screen is no lead');
	pg_assert_same(null, $got[5], 'from= that is not a card is no lead');
	pg_assert_same('', $got[6]['drawer'], 'a drawer id outside the keys is not added');
	pg_assert_same(null, $got[7], 'a broken escape is no lead');
}

function test_settings_lead_kept_leaves_secrets_out()
{
	$cases = array(
		array('ecommerce_payment_gateway', 'select-one', ''),
		array('ws_agent_model', 'text', ''),
		array('stripe_secret_key', 'text', ''),
		array('ws_agent_key', 'text', ''),
		array('smtp_password', 'text', ''),
		array('door_secret', 'text', ''),
		array('api_token', 'text', ''),
		array('anything', 'password', ''),
		array('anything', 'hidden', ''),
		array('anything', 'file', ''),
		array('code', 'text', 'one-time-code'),
		array('keywords', 'text', ''),
		array('ws_agent[12][key]', 'text', ''),
		array('', 'text', ''),
	);
	$got = settings_lead_kept_test_run('pgSettingsKeptSecret', $cases);

	if ($got === null) {
		return;
	}

	pg_assert_same(array(false, false, true, true, true, true, true, true, true, true, true, false, true, true), $got, 'keys, passwords, secrets, tokens and hidden fields stay out');
}

function test_settings_lead_kept_puts_back_on_its_pane_only()
{
	$now = 1764000000000;
	$record = array('pane' => 'commerce', 'card' => 'pgset-payments', 'at' => $now - 60000);
	$got = settings_lead_kept_test_run('pgSettingsKeptDue', array(
		array($record, 'commerce', $now),
		array($record, 'email', $now),
		array(array('pane' => 'commerce', 'at' => $now - 3600001), 'commerce', $now),
		array(array('pane' => 'commerce', 'at' => $now + 5000), 'commerce', $now),
		array(null, 'commerce', $now),
		array('commerce', 'commerce', $now),
	));

	if ($got === null) {
		return;
	}

	pg_assert_same(array(true, false, false, false, false, false), $got, 'its own pane within the hour, nothing else');
}
