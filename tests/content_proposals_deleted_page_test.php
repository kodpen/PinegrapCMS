<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an editor's waiting change whose page is gone or in the Recycle
 * Bin (includes/fn/content_proposals.php): Apply on a deleted page makes the
 * change stale and tells the editor, Apply on a binned page is refused with
 * the Recycle Bin's words and the change keeps waiting, the count above the
 * page list skips a change whose page row is gone, and Waiting Content
 * Changes names a missing page "Deleted page".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');

// Why a page could not be read: gone, in the bin, or neither.
function test_content_proposals_missing_kind()
{
	pg_assert_same('gone', pg_content_proposals_missing_kind(false, false), 'no page row');
	pg_assert_same('gone', pg_content_proposals_missing_kind(false, true), 'no row wins over the bin');
	pg_assert_same('binned', pg_content_proposals_missing_kind(true, true), 'in the Recycle Bin');
	pg_assert_same('', pg_content_proposals_missing_kind(true, false), 'there but unreadable');
}

// Runs pg_content_proposals_apply() in a PHP process of its own on a fake
// database: one waiting change on page 7, whose page row exists or not and
// is binned or not. Answers the apply's result, the statements written and
// the count, null when PHP cannot be started.
function content_proposals_deleted_page_run($page_exists, $binned)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgcpgone');

	if ($file === false) {
		return null;
	}

	$script = '<?php' . "\n"
		. 'define("PG_FUNCTIONS_DIR", ' . var_export(PG_FUNCTIONS_DIR, true) . ');' . "\n"
		. '$GLOBALS["page_exists"] = ' . var_export((bool) $page_exists, true) . ';' . "\n"
		. '$GLOBALS["binned"] = ' . var_export((bool) $binned, true) . ';' . "\n"
		. <<<'PHP'
$GLOBALS['written'] = array();
$GLOBALS['status'] = 'pending';
function e($value) { return addslashes((string) $value); }
function lang($text) { return is_array($text) ? $text['string'] : $text; }
function pg_schema_has($table, $column = null) { return true; }
function pg_recycle_page_binned($page_id) { return $GLOBALS['page_exists'] && $GLOBALS['binned']; }
function pg_page_tree_json($page_id) { return '{}'; }
function pg_designer_load_pages($style_id) { return array(); }

function pg_db_bind($sql, array $params, $escape)
{
	$pieces = explode('?', $sql);
	$bound = $pieces[0];
	foreach (array_values($params) as $i => $value) {
		$bound .= (is_int($value) ? (string) $value : "'" . addslashes((string) $value) . "'") . $pieces[$i + 1];
	}
	return $bound;
}

function db_item($sql)
{
	if (strpos($sql, 'FROM design_proposals') !== false) {
		return array('id' => 3, 'agent' => 'editor', 'page_id' => 7, 'status' => $GLOBALS['status'], 'ops' => '[]', 'steps' => '',
			'requested_by' => 9, 'message_id' => 0, 'channel_id' => 0);
	}
	if (strpos($sql, 'FROM page') !== false) {
		return $GLOBALS['page_exists'] ? array('page_id' => 7, 'page_style' => 4, 'page_folder' => 1) : null;
	}
	return null;
}

function db_value($sql)
{
	if (strpos($sql, 'information_schema') !== false) {
		return 1;
	}
	if (strpos($sql, 'COUNT(*)') !== false) {
		$joined = (strpos($sql, 'JOIN page') !== false);
		return (($GLOBALS['status'] === 'pending') && (!$joined || $GLOBALS['page_exists'])) ? 1 : 0;
	}
	if (strpos($sql, 'FROM page') !== false) {
		return $GLOBALS['page_exists'] ? ((strpos($sql, 'page_folder') !== false) ? 1 : 'Home') : null;
	}
	return null;
}

function db_values($sql) { return array(); }
function db_items($sql) { return array(); }

function db_q($sql, $params = array())
{
	$GLOBALS['written'][] = preg_replace('/\s+/', ' ', $sql);
	if (preg_match("/SET status = 'stale'/", $sql)) {
		$GLOBALS['status'] = 'stale';
		$GLOBALS['stale_error'] = (string) $params[0];
	}
	return true;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');

$before = pg_content_proposals_waiting_count();
$result = pg_content_proposals_apply(array('id' => 1, 'role' => 0), 3);

echo json_encode(array(
	'result' => $result,
	'before' => $before,
	'after' => pg_content_proposals_waiting_count(),
	'status' => $GLOBALS['status'],
	'error' => isset($GLOBALS['stale_error']) ? $GLOBALS['stale_error'] : '',
	'bell' => count(preg_grep('/INSERT INTO notifications/', $GLOBALS['written'])),
));
PHP;

	file_put_contents($file, $script);
	$out = array();
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out);
	@unlink($file);

	$decoded = json_decode(implode("\n", $out), true);
	pg_assert_true(is_array($decoded), 'the fake run answered: ' . implode("\n", $out));

	return is_array($decoded) ? $decoded : null;
}

// A deleted page: the change becomes stale, the editor is told, the count is 0.
function test_content_proposals_apply_on_deleted_page()
{
	$run = content_proposals_deleted_page_run(false, false);

	if ($run === null) {
		return;
	}

	pg_assert_false($run['result']['ok']);
	pg_assert_same('stale', $run['status'], 'stale');
	pg_assert_same('page gone', $run['error']);
	pg_assert_same(1, $run['bell'], "the editor's line");
	pg_assert_same(0, $run['before'], 'a gone page is not counted even before Apply');
	pg_assert_same(0, $run['after']);
}

// A page in the Recycle Bin: refused with the bin's words, still waiting and
// still counted, nobody told.
function test_content_proposals_apply_on_binned_page()
{
	$run = content_proposals_deleted_page_run(true, true);

	if ($run === null) {
		return;
	}

	pg_assert_false($run['result']['ok']);
	pg_assert_contains('Recycle Bin', $run['result']['error']);
	pg_assert_same('pending', $run['status'], 'still waiting');
	pg_assert_same(0, $run['bell'], 'nobody told');
	pg_assert_same(1, $run['after'], 'still counted');
}

// Waiting Content Changes names a page whose row is gone.
function test_content_proposals_list_names_deleted_page()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/content_changes.php');

	pg_assert_contains("lang('Deleted page')", $screen);
	pg_assert_contains("lang('The page of this change was deleted; it can only be dismissed.')", $screen);
}
