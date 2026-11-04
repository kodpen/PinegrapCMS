<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for saving a page's custom layout (pg_layout_save()): a layout file
 * that cannot be written leaves the page as it was and reports the error,
 * since a page marked modified includes data/layouts/<id>.php when drawn.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs pg_layout_save() in a fresh PHP with the layout folder at $dir and
// returns the result with the UPDATE queries it ran. Null when PHP cannot be
// started again.
function layout_save_write_test_run($dir, $content)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$base = sys_get_temp_dir() . '/pglayoutsave' . getmypid() . mt_rand();

	if (!@mkdir($base)) {
		return null;
	}

	file_put_contents($base . '/generate_layout_content.php', '<?php function generate_layout_content($id) { return "<div><?=\$field?></div>"; }' . "\n");

	$script = <<<'PHP'
define('PG_FUNCTIONS_DIR', $stub_dir);
define('LAYOUT_DIRECTORY_PATH', $layout_dir);
define('USER_ID', 1);

$GLOBALS['updates'] = array();

function pg_hosted() { return false; }
function lang($s) { return is_array($s) ? $s['string'] : $s; }
function log_activity($s) {}
function db_item($sql) { return array('id' => 12, 'name' => 'contact'); }
function db($sql) { $GLOBALS['updates'][] = $sql; return true; }

require $pg_dir . '/includes/fn/layouts.php';

$result = pg_layout_save(12, $content);

echo json_encode(array('result' => $result, 'updates' => $GLOBALS['updates']));
PHP;

	$file = $base . '/run.php';
	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$stub_dir = ' . var_export($base, true) . ";\n"
		. '$layout_dir = ' . var_export($dir === null ? $base . '/layouts' : $dir, true) . ";\n"
		. '$content = ' . var_export((string) $content, true) . ";\n"
		. $script . "\n");

	if ($dir === null) {
		mkdir($base . '/layouts');
	}

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);

	$written = ($dir === null) ? @file_get_contents($base . '/layouts/12.php') : false;

	foreach ((array) @glob($base . '/layouts/*') as $f) {
		@unlink($f);
	}
	@rmdir($base . '/layouts');
	@unlink($base . '/generate_layout_content.php');
	@unlink($file);
	@rmdir($base);

	$result = json_decode(implode("\n", $lines), true);

	if (($code !== 0) || !is_array($result) || !isset($result['result'])) {
		return array('error' => implode("\n", $lines));
	}

	$result['written'] = $written;

	return $result;
}

// A layout that cannot be written is an error and the page stays unmodified:
// marked modified, the page would include a file that is not there.
function test_layout_save_unwritable_folder_is_an_error()
{
	$run = layout_save_write_test_run(sys_get_temp_dir() . '/pg-no-such-layout-folder-' . mt_rand() . '/x', '<div>changed</div>');

	if ($run === null) {
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_false($run['result']['ok'], 'not saved');
	pg_assert_same(0, $run['result']['modified']);
	pg_assert_true($run['result']['message'] !== '', 'a message');
	pg_assert_same(array(), $run['updates'], 'the page is not marked modified');
}

// A changed layout that is written marks the page modified.
function test_layout_save_written_layout_marks_modified()
{
	$run = layout_save_write_test_run(null, '<div>changed</div>');

	if ($run === null) {
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_true($run['result']['ok']);
	pg_assert_same(1, $run['result']['modified']);
	pg_assert_same('<div>changed</div>', $run['written']);
	pg_assert_same(1, count($run['updates']));
	pg_assert_contains("layout_modified = '1'", $run['updates'][0]);
}
