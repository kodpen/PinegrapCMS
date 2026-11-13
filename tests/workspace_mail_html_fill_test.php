<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the fill of a mail's HTML from its kept .eml
 * (ws_mail_html_fill(), includes/workspace/mail.php), run by the recurring
 * job. In a PHP process of its own (the real mail modules, a fake db layer
 * and settings text, .eml files in a temporary data folder) it walks the
 * mail there was when it first ran, a few at a time, keeps its place, fills
 * only incoming mail whose .eml is still there and holds HTML, names a
 * picture's kept file, and stops for good once past the last one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_mail_html_fill_test_eml($html, $picture = false)
{
	$head = "From: a@example.com\r\nTo: box@example.com\r\nSubject: News\r\nMIME-Version: 1.0\r\n";

	if ($html === '') {
		return $head . "Content-Type: text/plain; charset=utf-8\r\n\r\nOnly text\r\n";
	}

	if (!$picture) {
		return $head . "Content-Type: text/html; charset=utf-8\r\n\r\n" . $html . "\r\n";
	}

	return $head . "Content-Type: multipart/related; boundary=\"b1\"\r\n\r\n"
		. "--b1\r\nContent-Type: text/html; charset=utf-8\r\n\r\n" . $html . "\r\n"
		. "--b1\r\nContent-Type: image/png; name=\"logo.png\"\r\nContent-Disposition: inline; filename=\"logo.png\"\r\n"
		. "Content-ID: <Logo@x>\r\nContent-Transfer-Encoding: base64\r\n\r\niVBORw0KGgo=\r\n--b1--\r\n";
}

// Runs the fill four times, two mails a run, in a child process; one mail
// comes in after the first run. Null when PHP cannot be started again.
function workspace_mail_html_fill_test_run()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$dir = sys_get_temp_dir() . '/pgmailfill' . getmypid() . mt_rand(1000, 9999);
	$folder = $dir . '/data/mail/1/202611';

	if (!@mkdir($folder, 0755, true)) {
		return null;
	}

	file_put_contents($folder . '/a.eml', workspace_mail_html_fill_test_eml('<html><body><p>Hello <img src="cid:logo@x"></p><script>x()</script></body></html>', true));
	file_put_contents($folder . '/b.eml', workspace_mail_html_fill_test_eml(''));
	file_put_contents($folder . '/c.eml', workspace_mail_html_fill_test_eml('<p onclick="x()">Plain <b>news</b></p>'));
	file_put_contents($folder . '/h.eml', workspace_mail_html_fill_test_eml('<p>Later</p>'));
	file_put_contents($dir . '/data/outside.eml', workspace_mail_html_fill_test_eml('<p>Outside</p>'));

	$file = $dir . '/run.php';
	$script = <<<'PHP'
define('PG_FUNCTIONS_DIR', $data_dir);

$GLOBALS['items'] = array(
	1 => array('direction' => 'in', 'raw_path' => 'mail/1/202611/a.eml', 'body_html' => null),
	2 => array('direction' => 'in', 'raw_path' => '', 'body_html' => null),
	3 => array('direction' => 'out', 'raw_path' => 'mail/1/202611/c.eml', 'body_html' => null),
	4 => array('direction' => 'in', 'raw_path' => 'mail/1/202611/b.eml', 'body_html' => null),
	5 => array('direction' => 'in', 'raw_path' => 'mail/1/202611/gone.eml', 'body_html' => null),
	6 => array('direction' => 'in', 'raw_path' => 'mail/../outside.eml', 'body_html' => null),
	7 => array('direction' => 'in', 'raw_path' => 'mail/1/202611/c.eml', 'body_html' => null),
);
$GLOBALS['messages'] = array(1 => array(array('file_id' => 77, 'file_name' => 'logo.png')));
$GLOBALS['config_text'] = array();
$GLOBALS['writes'] = array();

function e($value) { return addslashes((string) $value); }
function pg_basename($path) { $parts = explode('/', (string) $path); return (string) end($parts); }
function pg_schema_has($table, $column = null) { return ($table === 'ws_mail_items') && ($column === 'body_html'); }
function pg_config_text($name) { return isset($GLOBALS['config_text'][$name]) ? $GLOBALS['config_text'][$name] : ''; }
function pg_config_text_save($name, $value) { $GLOBALS['config_text'][$name] = $value; return true; }

function db_value($sql)
{
	if (trim($sql) === 'SELECT MAX(id) FROM ws_mail_items') {
		return max(array_keys($GLOBALS['items']));
	}
	throw new Exception('unexpected db_value: ' . $sql);
}

function db_items($sql)
{
	if (preg_match("/^SELECT id, raw_path FROM ws_mail_items\s+WHERE id > '(\d+)' AND id <= '(\d+)'\s+AND direction = 'in' AND raw_path <> '' AND body_html IS NULL\s+ORDER BY id\s+LIMIT (\d+)$/", $sql, $m)) {
		$rows = array();
		foreach ($GLOBALS['items'] as $id => $item) {
			if (($id > $m[1]) && ($id <= $m[2]) && ($item['direction'] === 'in') && ($item['raw_path'] !== '') && ($item['body_html'] === null) && (count($rows) < $m[3])) {
				$rows[] = array('id' => $id, 'raw_path' => $item['raw_path']);
			}
		}
		return $rows;
	}
	if (preg_match("/^SELECT file_id, file_name FROM ws_messages\s+WHERE sender_kind = 'mail' AND sender_id = '(\d+)' AND file_id > 0 AND deleted_at = 0\s+ORDER BY id$/", $sql, $m)) {
		return isset($GLOBALS['messages'][$m[1]]) ? $GLOBALS['messages'][$m[1]] : array();
	}
	throw new Exception('unexpected db_items: ' . $sql);
}

function db($sql)
{
	if (preg_match("/^UPDATE ws_mail_items SET body_html = '(.*)' WHERE id = '(\d+)' AND body_html IS NULL$/s", $sql, $m)) {
		$GLOBALS['items'][(int) $m[2]]['body_html'] = stripslashes($m[1]);
		$GLOBALS['writes'][] = (int) $m[2];
		return true;
	}
	throw new Exception('unexpected db: ' . $sql);
}

require $pg_dir . '/includes/fn/mail_parse.php';
require $pg_dir . '/includes/fn/mail_html.php';
require $pg_dir . '/includes/workspace/mail.php';

$runs = array();
foreach (array(1, 2, 3, 4) as $run) {
	$read = ws_mail_html_fill(2);
	$runs[] = array('read' => $read, 'state' => json_decode($GLOBALS['config_text']['ws_mail_html_fill'], true));
	if ($run === 1) {
		$GLOBALS['items'][8] = array('direction' => 'in', 'raw_path' => 'mail/1/202611/h.eml', 'body_html' => null);
	}
}

echo json_encode(array('runs' => $runs, 'items' => $GLOBALS['items'], 'writes' => $GLOBALS['writes']));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$data_dir = ' . var_export($dir, true) . ";\n"
		. $script . "\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $out, $code);

	foreach (array($folder . '/a.eml', $folder . '/b.eml', $folder . '/c.eml', $folder . '/h.eml', $dir . '/data/outside.eml', $file) as $made) {
		@unlink($made);
	}

	foreach (array($folder, $dir . '/data/mail/1', $dir . '/data/mail', $dir . '/data', $dir) as $made) {
		@rmdir($made);
	}

	$result = json_decode(implode("\n", $out), true);

	if (($code !== 0) || !is_array($result) || !isset($result['runs'], $result['items'])) {
		return array('error' => implode("\n", $out));
	}

	return $result;
}

function test_workspace_mail_html_fill_walks_the_earlier_mail_and_stops()
{
	$result = workspace_mail_html_fill_test_run();

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the fill ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	$runs = $result['runs'];

	pg_assert_same(array('ceiling' => 7, 'cursor' => 4, 'done' => false), $runs[0]['state'], 'first run: the mail there was, two read (1 and 4: no .eml, sent mail skipped)');
	pg_assert_same(array('ceiling' => 7, 'cursor' => 6, 'done' => false), $runs[1]['state'], 'second run: 5 and 6');
	pg_assert_same(array('ceiling' => 7, 'cursor' => 7, 'done' => true), $runs[2]['state'], 'third run: the last one, done');
	pg_assert_same(array(2, 2, 1, 0), array_column($runs, 'read'), 'two a run, then nothing once done');
	pg_assert_same(array(1, 7), $result['writes'], 'only the mail whose .eml holds HTML is written');

	$items = $result['items'];

	pg_assert_contains('<p>Hello <img data-pg-cid="logo@x" data-pg-file="77"></p>', (string) $items[1]['body_html'], 'cleaned, its picture naming the file the mail brought');
	pg_assert_true(strpos((string) $items[1]['body_html'], 'script') === false, 'no script');
	pg_assert_same('<p>Plain <b>news</b></p>', trim((string) $items[7]['body_html']), 'a plain HTML mail');
	pg_assert_same(null, $items[3]['body_html'], 'sent mail is not read');
	pg_assert_same(null, $items[4]['body_html'], 'a text-only mail stays without');
	pg_assert_same(null, $items[5]['body_html'], 'an .eml already swept');
	pg_assert_same(null, $items[6]['body_html'], 'a path outside the mail folder is not read');
	pg_assert_same(null, $items[8]['body_html'], 'mail that came in after the first run is the ingest\'s');
}
