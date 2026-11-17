<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the rows Waiting Content Changes reads
 * (pg_content_proposals_list() in includes/fn/content_proposals.php): every
 * waiting and stale change is listed however many closed ones are newer,
 * so the list agrees with the count above the page list; only the closed
 * ones are capped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs pg_content_proposals_list() and pg_content_proposals_waiting_count()
// in a PHP process of its own on a fake database holding $rows (the
// design_proposals rows). The fake reads the list's WHERE (status in or not
// in waiting and stale, decided_at at least a time, joined by AND or OR),
// orders by id, newest first, and keeps its LIMIT. Answers the ids listed
// and the count, null when PHP cannot be started.
function content_proposals_list_run($rows)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgcplist');

	if ($file === false) {
		return null;
	}

	$script = '<?php' . "\n"
		. 'define("PG_FUNCTIONS_DIR", ' . var_export(PG_FUNCTIONS_DIR, true) . ');' . "\n"
		. '$GLOBALS["rows"] = ' . var_export($rows, true) . ';' . "\n"
		. <<<'PHP'
function e($value) { return addslashes((string) $value); }
function lang($text) { return is_array($text) ? $text['string'] : $text; }
function pg_schema_has($table, $column = null) { return true; }

function pg_db_bind($sql, array $params, $escape)
{
	$pieces = explode('?', $sql);
	$bound = $pieces[0];
	foreach (array_values($params) as $i => $value) {
		$bound .= (is_int($value) ? (string) $value : "'" . addslashes((string) $value) . "'") . $pieces[$i + 1];
	}
	return $bound;
}

// The rows a design_proposals query of the list or the count matches.
function _fake_match($sql)
{
	$sql = preg_replace('/\s+/', ' ', $sql);
	$open_in = (strpos($sql, "status IN ('pending', 'stale')") !== false);
	$open_out = (strpos($sql, "status NOT IN ('pending', 'stale')") !== false);
	$pending = (strpos($sql, "status = 'pending'") !== false);
	$since = preg_match('/decided_at >= (\d+)/', $sql, $m) ? (int) $m[1] : null;
	$or = (strpos($sql, "'stale') OR p.decided_at") !== false);
	$out = array();

	foreach ($GLOBALS['rows'] as $row) {
		$open = in_array($row['status'], array('pending', 'stale'), true);
		$recent = ($since !== null) && ((int) $row['decided_at'] >= $since);

		if ($pending) {
			$keep = ($row['status'] === 'pending');
		} elseif ($or) {
			$keep = $open || $recent;
		} else {
			$keep = (!$open_in || $open) && (!$open_out || !$open) && (($since === null) || $recent);
		}

		if ($keep) {
			$out[] = $row;
		}
	}

	usort($out, function ($a, $b) { return (int) $b['id'] - (int) $a['id']; });

	return preg_match('/LIMIT (\d+)/', $sql, $m) ? array_slice($out, 0, (int) $m[1]) : $out;
}

function db_items($sql) { return _fake_match($sql); }
function db_value($sql) { return count(_fake_match($sql)); }

require PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php';

$listed = array();
foreach (pg_content_proposals_order(pg_content_proposals_list(), time()) as $row) {
	$listed[] = array((int) $row['id'], $row['status']);
}
echo json_encode(array('listed' => $listed, 'count' => pg_content_proposals_waiting_count()));
PHP;

	file_put_contents($file, $script);

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// One waiting change (id 1) and one stale one (id 2), then $closed changes
// closed today, each newer; plus one closed long ago, never listed.
function content_proposals_list_rows($closed)
{
	$now = time();
	$rows = array(
		array('id' => 1, 'status' => 'pending', 'created_at' => $now - 7200, 'decided_at' => 0),
		array('id' => 2, 'status' => 'stale', 'created_at' => $now - 7100, 'decided_at' => 0),
		array('id' => 3, 'status' => 'applied', 'created_at' => $now - 90 * 86400, 'decided_at' => $now - 60 * 86400),
	);

	for ($i = 0; $i < $closed; $i++) {
		$statuses = array('applied', 'dismissed', 'withdrawn', 'reverted');
		$rows[] = array('id' => 10 + $i, 'status' => $statuses[$i % 4], 'created_at' => $now - 3600 + $i, 'decided_at' => $now - 1800 + $i);
	}

	return $rows;
}

// A waiting change older than 200 closed ones is listed, first, as the
// count counts it; the stale one too; closed rows stay capped at 200.
function test_content_proposals_list_keeps_waiting_past_the_cap()
{
	$result = content_proposals_list_run(content_proposals_list_rows(250));

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : '');
	pg_assert_same(1, $result['count']);
	pg_assert_same(array(1, 'pending'), $result['listed'][0]);
	pg_assert_same(array(2, 'stale'), $result['listed'][1]);

	$waiting = 0;
	$closed = 0;
	foreach ($result['listed'] as $row) {
		if ($row[1] === 'pending') {
			$waiting++;
		} elseif ($row[1] !== 'stale') {
			$closed++;
		}
	}

	pg_assert_same($result['count'], $waiting, 'the list shows every change the count counts');
	pg_assert_same(200, $closed, 'closed changes stay capped');
	pg_assert_same(array(259, 'dismissed'), $result['listed'][2], 'the newest closed ones are kept');
}

// Under the cap nothing changes: every row listed in the screen's order, the
// one closed long ago left out.
function test_content_proposals_list_under_the_cap()
{
	$result = content_proposals_list_run(content_proposals_list_rows(2));

	if ($result === null) {
		return;
	}

	pg_assert_same(array(array(1, 'pending'), array(2, 'stale'), array(11, 'dismissed'), array(10, 'applied')), $result['listed']);
}
