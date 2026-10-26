<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Builds the code editor bundle the panel serves:
 * pinegrap/assets/lib/code_editor/cm6.bundle.js. The bundle is CodeMirror 6
 * and its language packages plus js-beautify's CSS and JavaScript
 * formatter, every one of them pinned in
 * tools/code_editor/package.json and package-lock.json, bundled with esbuild
 * from tools/code_editor/entry.js into one IIFE that leaves window.PgCM on
 * the page. The facade pinegrap/assets/js/pg_code_editor.js loads that file
 * when a screen first opens the editor, so the designer page does not carry
 * it on every load.
 *
 * Usage:  php tools/build_code_editor.php           write the bundle
 *         php tools/build_code_editor.php --check   build into a temporary
 *                                                   folder and compare;
 *                                                   nothing is written (CI)
 * Needs:  Node.js with npm on the PATH. The packages are installed with
 *         `npm ci` from the lock file into a temporary folder, so every
 *         machine bundles the same bytes. Upgrading a package: change the
 *         version in package.json, run `npm install` in tools/code_editor to
 *         refresh the lock file, then rebuild and commit the bundle.
 * Exit:   0 when the bundle was written, or is current; 1 when the build
 *         failed or, with --check, the bundle differs from its sources.
 *
 * Line endings do not count: the output is written with LF endings and
 * --check compares with CR removed, so a checkout that turned them into
 * CRLF still passes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit('Forbidden');
}

const BUNDLE = 'assets/lib/code_editor/cm6.bundle.js';
const ESBUILD_FLAGS = '--bundle --minify --format=iife --global-name=PgCM --target=es2018 --legal-comments=none --log-level=error';

$check = in_array('--check', array_slice($argv, 1), true);

$repository = dirname(__DIR__);
$software = $repository . '/pinegrap';
$source_dir = __DIR__ . '/code_editor';

foreach (array('package.json', 'package-lock.json', 'entry.js') as $required) {
	if (!is_file($source_dir . '/' . $required)) {
		fwrite(STDERR, "Missing $source_dir/$required\n");
		exit(1);
	}
}

$work = sys_get_temp_dir() . '/pg_build_code_editor_' . getmypid();

if (!is_dir($work) && !mkdir($work, 0700, true)) {
	fwrite(STDERR, "Cannot create the work folder $work\n");
	exit(1);
}

// npm ci wants the manifest and the lock file beside each other; the entry
// file goes along so its bare imports resolve from the installed packages.
foreach (array('package.json', 'package-lock.json', 'entry.js') as $file) {
	copy($source_dir . '/' . $file, $work . '/' . $file);
}

function pg_bce_run($command, $cwd)
{
	$lines = array();
	$status = 1;
	$previous = getcwd();
	chdir($cwd);
	exec($command . ' 2>&1', $lines, $status);
	chdir($previous);
	return array($status, $lines);
}

function pg_bce_cleanup($dir)
{
	if (!is_dir($dir)) return;
	$items = scandir($dir);
	foreach ($items as $item) {
		if ($item === '.' || $item === '..') continue;
		$path = $dir . '/' . $item;
		if (is_dir($path) && !is_link($path)) {
			pg_bce_cleanup($path);
		} else {
			@unlink($path);
		}
	}
	@rmdir($dir);
}

list($status, $lines) = pg_bce_run('npm ci --no-audit --no-fund --ignore-scripts --silent', $work);

if ($status !== 0) {
	echo "FAIL  npm ci exited with $status\n" . implode("\n", $lines) . "\n";
	pg_bce_cleanup($work);
	exit(1);
}

$output = $work . '/cm6.bundle.js';
$esbuild = $work . '/node_modules/.bin/esbuild';

list($status, $lines) = pg_bce_run(escapeshellarg($esbuild) . ' entry.js ' . ESBUILD_FLAGS . ' --outfile=' . escapeshellarg($output), $work);

if (($status !== 0) || !is_file($output)) {
	echo "FAIL  esbuild exited with $status\n" . implode("\n", $lines) . "\n";
	pg_bce_cleanup($work);
	exit(1);
}

// The bundle carries the licenses of what it bundles: every package is MIT
// (CodeMirror, Replit's minimap and keymap, js-beautify), and the versions are the ones the lock file
// pinned.
$manifest = json_decode(file_get_contents($source_dir . '/package.json'), true);
$packages = array();
foreach ((isset($manifest['dependencies']) ? $manifest['dependencies'] : array()) as $name => $version) {
	$packages[] = ' *   ' . $name . ' ' . $version;
}

$banner = "/*!\n"
	. " * Pinegrap - Enterprise Website Platform\n"
	. " *\n"
	. " * Code editor bundle: CodeMirror 6 and its language packages, the\n"
	. " * minimap and the js-beautify formatter, built by\n"
	. " * tools/build_code_editor.php from\n"
	. " * tools/code_editor/entry.js. Do not edit; change the sources and rebuild.\n"
	. " * Reachable on the page as window.PgCM.\n"
	. " *\n"
	. " * Bundled packages (MIT License; https://codemirror.net/,\n"
	. " * https://github.com/replit/codemirror-minimap,\n"
	. " * https://github.com/beautifier/js-beautify):\n"
	. implode("\n", $packages) . "\n"
	. " *\n"
	. " * @author      Erdal Güral (Kodpen)\n"
	. " * @link        https://kodpen.com\n"
	. " * @copyright   2017–2026 Kodpen\n"
	. " * @license     https://opensource.org/licenses/mit-license.html MIT License\n"
	. " */\n";

$built = $banner . rtrim(str_replace("\r\n", "\n", file_get_contents($output)), "\n") . "\n";
$target = $software . '/' . BUNDLE;

pg_bce_cleanup($work);

if ($check) {
	$current = is_file($target) ? str_replace("\r", '', file_get_contents($target)) : null;

	if ($current === $built) {
		echo 'ok    ' . BUNDLE . "\n";
		exit(0);
	}

	echo 'STALE ' . BUNDLE . ': ' . (($current === null) ? 'missing' : 'differs from its sources') . " - run php tools/build_code_editor.php\n";
	exit(1);
}

if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true)) {
	fwrite(STDERR, 'Cannot create ' . dirname($target) . "\n");
	exit(1);
}

file_put_contents($target, $built);
echo 'wrote ' . BUNDLE . ' (' . number_format(strlen($built)) . " bytes)\n";
exit(0);
