<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a mailbox's block list (includes/workspace/mail_blocks.php): the
 * 2026.11.0 step 11.0.202 run twice against an in-memory schema, the value a
 * block stores, the domains an address falls under, the match, who may block
 * and where, the Site Log lines, the pane's offer and its form, the list on
 * the Mailboxes card, and the wiring into the ingest, the sweeps, the pane,
 * the card's save and the panel action. No database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_parse.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function workspace_mail_blocks_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_workspace_mail_blocks_step_is_called_once_under_a_label_of_its_own()
{
	$source = workspace_mail_blocks_test_source('includes/migrations/2026.11.0.php');

	pg_assert_same(1, substr_count($source, "\tupgrade_2026_11_0_ws_mail_blocks();          // 11.0.202\n"), 'called once under 11.0.202');

	preg_match('/function upgrade_to_2026_11_0\(\) \{(.*?)\n\}\n/s', $source, $entry);
	preg_match_all('#// (11\.0\.\d+)\n#', isset($entry[1]) ? $entry[1] : '', $labels);

	pg_assert_same(count($labels[1]), count(array_unique($labels[1])), 'every label of the call list is used once');
	pg_assert_same(1, substr_count(workspace_mail_blocks_test_source('install/index.php'), "\t\t'ws_mail_blocks',\n"), 'the table is on get_tables() once');
	pg_assert_true(strpos(substr($source, (int) strpos($source, 'function upgrade_2026_11_0_ws_mail_blocks')), 'db(') === false, 'only the runner helpers');
}

// Runs the step twice in a child process: the fake db layer answers the
// runner's probes from an array, applies CREATE TABLE and ADD to it and logs
// every write. Null when PHP cannot be started again.
function workspace_mail_blocks_test_run_twice($schema)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgmailblocks');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('INSTALL_OR_UPDATE', true);

$GLOBALS['fake_writes'] = array();

function e($value) { return addslashes((string) $value); }

function lang($text)
{
	if (is_array($text)) {
		return str_replace('{var:1}', (string) $text['vars'], $text['string']);
	}
	return $text;
}

function db_value($sql)
{
	if (preg_match("/TABLE_NAME = '([^']+)'/", $sql, $m)) {
		return isset($GLOBALS['fake_schema'][$m[1]]) ? 1 : 0;
	}
	throw new Exception('unexpected db_value: ' . $sql);
}

function db_item($sql)
{
	if (preg_match("/^SHOW COLUMNS FROM `([^`]+)` WHERE Field = '([^']+)'$/", $sql, $m)) {
		if (isset($GLOBALS['fake_schema'][$m[1]][$m[2]])) {
			return array('Field' => $m[2], 'Type' => $GLOBALS['fake_schema'][$m[1]][$m[2]]);
		}
		return array();
	}
	throw new Exception('unexpected db_item: ' . $sql);
}

function db($sql)
{
	$GLOBALS['fake_writes'][] = $sql;
	if (preg_match('/^CREATE TABLE (\w+) \(/', $sql, $m)) {
		$GLOBALS['fake_schema'][$m[1]] = array();
		preg_match_all('/^\s+([a-z_]+)\s+([A-Z]+(?:\([^)]*\))?)/m', $sql, $columns, PREG_SET_ORDER);
		foreach ($columns as $column) {
			$GLOBALS['fake_schema'][$m[1]][$column[1]] = strtolower($column[2]);
		}
		$GLOBALS['fake_sql'][$m[1]] = $sql;
		return true;
	}
	if (preg_match('/^ALTER TABLE `([^`]+)` ADD `([^`]+)` ([A-Z]+(?:\([^)]*\))?)/', $sql, $m)) {
		$GLOBALS['fake_schema'][$m[1]][$m[2]] = strtolower($m[3]);
		return true;
	}
	throw new Exception('unexpected db: ' . $sql);
}

require $pg_dir . '/includes/migrations/runner.php';
require $pg_dir . '/includes/migrations/2026.11.0.php';

$runs = array();
foreach (array(1, 2) as $run) {
	$GLOBALS['fake_writes'] = array();
	upgrade_2026_11_0_ws_mail_blocks();
	$runs[] = $GLOBALS['fake_writes'];
}

echo json_encode(array('runs' => $runs, 'schema' => $GLOBALS['fake_schema'], 'sql' => isset($GLOBALS['fake_sql']) ? $GLOBALS['fake_sql'] : array()));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$GLOBALS[\'fake_schema\'] = ' . var_export($schema, true) . ";\n"
		. $script . "\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $out), true);

	if (($code !== 0) || !is_array($result) || !isset($result['runs'])) {
		return array('error' => implode("\n", $out));
	}

	return $result;
}

function test_workspace_mail_blocks_step_run_twice_changes_nothing_the_second_time()
{
	$result = workspace_mail_blocks_test_run_twice(array('ws_mail_items' => array('id' => 'int', 'box_id' => 'int')));

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the step ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	pg_assert_same(2, count($result['runs'][0]), 'the first run creates the table and adds the column');
	pg_assert_same(array(), $result['runs'][1], 'the second run writes nothing');
	pg_assert_same('int', $result['schema']['ws_mail_items']['blocked_at'], 'blocked_at');
	pg_assert_same(array('id', 'box_id', 'kind', 'value', 'created_by', 'created_at'), array_keys($result['schema']['ws_mail_blocks']), 'the columns asked of the owner');
	pg_assert_same("enum('address','domain')", $result['schema']['ws_mail_blocks']['kind'], 'the kinds');
	pg_assert_same('varchar(255)', $result['schema']['ws_mail_blocks']['value'], 'the value');
	pg_assert_contains('UNIQUE KEY uq_block (box_id, kind, value(191))', $result['sql']['ws_mail_blocks'], 'one block per box, kind and value');
	pg_assert_contains('INT UNSIGNED NOT NULL DEFAULT 0', $result['runs'][0][1], 'blocked_at as asked');

	$none = workspace_mail_blocks_test_run_twice(array());
	pg_assert_true(!isset($none['error']), 'without the mail tables it runs');
	pg_assert_same(array(array(), array()), isset($none['runs']) ? $none['runs'] : null, 'and writes nothing');
}

function test_workspace_mail_blocks_value()
{
	pg_assert_same('ali@example.com', ws_mail_block_value('  Ali@Example.COM ', 'address'), 'an address in lower case');
	pg_assert_same('example.com', ws_mail_block_value('Ali@Example.COM', 'domain'), 'its domain');
	pg_assert_same('mail.example.co.uk', ws_mail_block_value('a@mail.example.co.uk', 'domain'), 'a subdomain is kept whole');
	pg_assert_same('', ws_mail_block_value('nobody', 'address'), 'no @');
	pg_assert_same('', ws_mail_block_value('@example.com', 'address'), 'nothing before @');
	pg_assert_same('', ws_mail_block_value('a@localhost', 'domain'), 'a domain without a dot');
	pg_assert_same('', ws_mail_block_value('a b@example.com', 'address'), 'a space');
	pg_assert_same('', ws_mail_block_value(str_repeat('a', 250) . '@example.com', 'address'), 'longer than the column');
}

function test_workspace_mail_blocks_domains()
{
	pg_assert_same(array('a.mail.example.com', 'mail.example.com', 'example.com'), ws_mail_block_domains('x@A.Mail.Example.com'), 'the domain and its parents');
	pg_assert_same(array('example.com'), ws_mail_block_domains('x@example.com'), 'one');
	pg_assert_same(array(), ws_mail_block_domains('x'), 'none');
}

function test_workspace_mail_blocks_match()
{
	$blocks = array(
		array('id' => 1, 'box_id' => 3, 'kind' => 'address', 'value' => 'spam@example.com'),
		array('id' => 2, 'box_id' => 0, 'kind' => 'domain', 'value' => 'junk.test'),
		array('id' => 3, 'box_id' => 5, 'kind' => 'domain', 'value' => 'other.test'),
	);

	pg_assert_same(1, ws_mail_block_match('Spam@Example.com', 3, $blocks)['id'], 'an address in its box');
	pg_assert_same(null, ws_mail_block_match('spam@example.com', 4, $blocks), 'not in another box');
	pg_assert_same(null, ws_mail_block_match('ham@example.com', 3, $blocks), 'another address of the domain');
	pg_assert_same(2, ws_mail_block_match('a@junk.test', 9, $blocks)['id'], 'a domain for every box');
	pg_assert_same(2, ws_mail_block_match('a@mx.junk.test', 9, $blocks)['id'], 'and its subdomains');
	pg_assert_same(null, ws_mail_block_match('a@notjunk.test', 9, $blocks), 'not a name that only ends alike');
	pg_assert_same(null, ws_mail_block_match('a@other.test', 9, $blocks), 'a domain of another box');
	pg_assert_same(3, ws_mail_block_match('a@other.test', 5, $blocks)['id'], 'in its own box');
	pg_assert_same(null, ws_mail_block_match('', 3, $blocks), 'no sender');
	pg_assert_same(null, ws_mail_block_match('a@junk.test', 3, array()), 'no blocks');
}

function test_workspace_mail_blocks_input_and_decide()
{
	pg_assert_same(array('kind' => 'address', 'scope' => 'box'), ws_mail_block_input(array()), 'defaults');
	pg_assert_same(array('kind' => 'domain', 'scope' => 'all'), ws_mail_block_input(array('kind' => 'domain', 'scope' => 'all')), 'chosen');
	pg_assert_same(array('kind' => 'address', 'scope' => 'box'), ws_mail_block_input(array('kind' => array('domain'), 'scope' => 'x')), 'unknown');

	$item = array('id' => 8, 'direction' => 'in', 'from_email' => 'Spam@Junk.test', 'user_id' => 0, 'blocked_at' => 0);
	$box = array('id' => 3, 'name' => 'Support', 'transport' => 'imap');
	$box_address = array('kind' => 'address', 'scope' => 'box');
	$all_domain = array('kind' => 'domain', 'scope' => 'all');

	$decided = ws_mail_block_decide($item, $box, true, false, $box_address);
	pg_assert_same(array(true, 3, 'address', 'spam@junk.test'), array($decided['ok'], $decided['box_id'], $decided['kind'], $decided['value']), 'this box, the address');

	$decided = ws_mail_block_decide($item, $box, true, true, $all_domain);
	pg_assert_same(array(true, 0, 'domain', 'junk.test'), array($decided['ok'], $decided['box_id'], $decided['kind'], $decided['value']), 'every box, the domain');

	pg_assert_false(ws_mail_block_decide($item, $box, true, false, $all_domain)['ok'], 'every box needs the settings right');
	pg_assert_false(ws_mail_block_decide($item, $box, false, true, $box_address)['ok'], 'only who may answer');
	pg_assert_false(ws_mail_block_decide(array_merge($item, array('direction' => 'out')), $box, true, true, $box_address)['ok'], 'not a sent mail');
	pg_assert_false(ws_mail_block_decide(array_merge($item, array('user_id' => 4)), $box, true, true, $box_address)['ok'], 'not a delivery failure');
	pg_assert_false(ws_mail_block_decide($item, array_merge($box, array('transport' => 'whatsapp')), true, true, $box_address)['ok'], 'no phone numbers');
	pg_assert_false(ws_mail_block_decide($item, null, true, true, $box_address)['ok'], 'no box');
	pg_assert_false(ws_mail_block_decide(array_merge($item, array('from_email' => 'x')), $box, true, true, $box_address)['ok'], 'no usable address');
}

function test_workspace_mail_blocks_log_lines()
{
	pg_assert_same('the sender a@b.test was blocked in the mailbox Support', ws_mail_block_log_line(true, 'address', 'a@b.test', 'Support'), 'blocked in a box');
	pg_assert_same('the sender @b.test was blocked for every mailbox', ws_mail_block_log_line(true, 'domain', 'b.test', ''), 'a domain for every box');
	pg_assert_same('the sender a@b.test was allowed again in the mailbox Support', ws_mail_block_log_line(false, 'address', 'a@b.test', 'Support'), 'allowed again');
	pg_assert_same('the sender @b.test was allowed again for every mailbox', ws_mail_block_log_line(false, 'domain', 'b.test', ''), 'allowed again everywhere');
}

function test_workspace_mail_blocks_item_html()
{
	pg_assert_same('', ws_mail_block_item_html(array('id' => 4, 'block' => null)), 'not offered');

	$html = ws_mail_block_item_html(array('id' => 4, 'block' => array('address' => 'a@b.test', 'domain' => 'b.test', 'every' => false)));
	pg_assert_contains('data-bs-target="#ws-mail-block-4"', $html, 'the button opens the form');
	pg_assert_contains('data-ws-mail-block="4"', $html, 'the form names the mail');
	pg_assert_contains('data-value="address" title="a@b.test"><span class="text-truncate">a@b.test</span></button>', $html, 'the address');
	pg_assert_contains('Everyone at b.test', $html, 'the domain');
	pg_assert_contains('data-value="box"', $html, 'this box');
	pg_assert_true(strpos($html, 'data-value="all"') === false, 'every box only with the settings right');
	pg_assert_contains('class="pg-seg flex-wrap max-w-100"', $html, 'look A: the segmented control, wrapping on a phone');

	$html = ws_mail_block_item_html(array('id' => 4, 'block' => array('address' => '<x>@b.test', 'domain' => 'b.test', 'every' => true)));
	pg_assert_contains('data-value="all"', $html, 'every box');
	pg_assert_contains('&lt;x&gt;@b.test', $html, 'escaped');
}

function test_workspace_mail_blocks_list_html()
{
	pg_assert_same('', ws_mail_blocks_html(array(), 'f'), 'nothing without blocks');

	$html = ws_mail_blocks_html(array(
		array('id' => 7, 'box_id' => 0, 'kind' => 'domain', 'value' => 'junk.test', 'created_at' => 0, 'box_name' => ''),
		array('id' => 8, 'box_id' => 3, 'kind' => 'address', 'value' => 'a@<b>.test', 'created_at' => 0, 'box_name' => 'Support'),
		array('id' => 9, 'box_id' => 4, 'kind' => 'address', 'value' => 'c@d.test', 'created_at' => 0, 'box_name' => ''),
	), 'pg_settings_page_form');

	pg_assert_contains('@junk.test', $html, 'a domain');
	pg_assert_contains('Every mailbox', $html, 'for every box');
	pg_assert_contains('a@&lt;b&gt;.test', $html, 'escaped');
	pg_assert_contains('Support', $html, 'its box');
	pg_assert_contains('A mailbox that was deleted', $html, 'a deleted box');
	pg_assert_contains('name="ws_mail_allow" value="8" form="pg_settings_page_form"', $html, 'Allow again submits the pane form');
	pg_assert_contains('class="pg-list-row"', $html, 'look A: rows');
}

function test_workspace_mail_blocks_wiring()
{
	$ingest = workspace_mail_blocks_test_source('includes/workspace/mail.php');
	$loop = strpos($ingest, "\$loop = ws_mail_loop_reason(");
	$block = strpos($ingest, 'ws_mail_block_find($box_id, $from_email)');
	$hold = strpos($ingest, '>= WS_MAIL_SENDER_HOURLY');
	$find = strpos($ingest, '$conversation = ws_mail_find_conversation(');

	pg_assert_true(($loop !== false) && ($block > $loop) && ($hold > $block) && ($find > $hold), 'after the loop gate, before the hold and the conversation');
	pg_assert_contains("(\$blocks ? ' AND blocked_at = 0' : '')) >= WS_MAIL_SENDER_HOURLY", $ingest, 'kept blocked mail does not count toward the hold');
	pg_assert_contains("\" . (\$blocks ? ' AND blocked_at = 0' : '') . \"", $ingest, 'the .eml sweep leaves blocked mail to its own sweep');
	pg_assert_same('taken', workspace_mail_blocks_test_outcome(array('status' => 'blocked', 'reason' => '')), 'the fetch takes it as done');

	$job = workspace_mail_blocks_test_source('workspace_recurring_job.php');
	pg_assert_true(strpos($job, 'ws_mail_block_sweep();') < strpos($job, 'ws_mail_eml_sweep();'), 'the block sweep runs before the .eml sweep');

	pg_assert_contains('ws_mail_block_pane($pane, $viewer)', workspace_mail_blocks_test_source('includes/workspace/mail_pane.php'), 'the pane offers it');
	pg_assert_contains("post_value('ws_mail_allow')", workspace_mail_blocks_test_source('includes/settings/mailboxes.save.php'), 'Allow again is saved');
	pg_assert_contains('ws_mail_blocks_html(ws_mail_blocks_list(), $form)', workspace_mail_blocks_test_source('includes/workspace/mail_settings.php'), 'the card lists them');
	pg_assert_true(ws_mail_ingest_message(array('status' => 'blocked', 'reason' => ''))['notice'], 'an .eml of a blocked sender is told');
}

function workspace_mail_blocks_test_outcome($result)
{
	if (!function_exists('ws_mail_fetch_outcome')) {
		require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mail_fetch.php');
	}

	return ws_mail_fetch_outcome($result);
}
