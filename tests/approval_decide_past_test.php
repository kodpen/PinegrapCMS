<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for ws_approval_decide_check() (includes/workspace/approvals.php):
 * a request in an earlier version of its channel's conversation (eras.php)
 * is kept as it was, so nobody answers it there - through the panel, the
 * external API or its dry run alike, since all of them make this check.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs the check in a PHP process of its own, with approvals.php loaded on a
// fake db layer and fake channel helpers. $floor is the channel's first
// message of the current version: the request's message 50 is in the past
// when the floor is at or above it. Null when PHP cannot be started again.
function approval_decide_past_test_run($floor)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgapvpast');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('PG_FUNCTIONS_DIR', $argv[1]);
$GLOBALS['floor'] = (int) $argv[2];

function lang($text) { return is_array($text) ? $text['string'] : $text; }
function db_item($sql) { return array('decision' => 'pending'); }
function db_items($sql) { return array(array('user_id' => 7, 'decision' => 'pending', 'note' => '', 'decided_at' => 0)); }
function ws_message($id) { return array('id' => (int) $id, 'channel_id' => 3, 'deleted_at' => 0); }
function ws_channel($id) { return array('id' => (int) $id, 'kind' => 'public', 'archived_at' => 0); }
function ws_can_post_channel($viewer, $channel) { return true; }
function ws_message_in_past($message) { return (int) $message['id'] <= $GLOBALS['floor']; }

require PG_FUNCTIONS_DIR . '/includes/workspace/approvals.php';

$approval = array('id' => 1, 'message_id' => 50, 'channel_id' => 3, 'rule' => 'any', 'closed_at' => 0, 'closes_at' => 0);
echo json_encode(ws_approval_decide_check(array('id' => 7), $approval, 'approve'));
PHP;

	file_put_contents($file, "<?php\n" . $script);
	$output = array();
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' ' . escapeshellarg(PG_FUNCTIONS_DIR) . ' ' . (int) $floor . ' 2>&1', $output);
	@unlink($file);

	$result = json_decode(implode("\n", $output), true);

	return is_array($result) ? $result : array('ok' => null, 'error' => implode("\n", $output));
}

function test_approval_decide_past_request_is_refused()
{
	$result = approval_decide_past_test_run(60);

	if ($result === null) {
		return;
	}

	pg_assert_same(false, $result['ok'], (string) ($result['error'] ?? ''));
	pg_assert_same('This message is in an earlier version of the conversation, which is kept as it was.', $result['error']);
	pg_assert_same(false, $result['expired']);
}

function test_approval_decide_current_request_is_answered()
{
	$result = approval_decide_past_test_run(10);

	if ($result === null) {
		return;
	}

	pg_assert_same(true, $result['ok'], (string) ($result['error'] ?? ''));
	pg_assert_same('approved', $result['outcome']);
}
