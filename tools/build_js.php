<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Builds the minified scripts the panel serves. The list of what is built
 * from what is pg_js_bundles() (pinegrap/includes/fn/output.php), the same
 * list the screens read, so a script cannot be served from a bundle this tool
 * does not build. Each .min.js is its sources, in order, through terser with
 * the flags below: compressed, mangled and ASCII only (emoji and other
 * non-ASCII text become escapes, so the file survives a server that names the
 * wrong charset). License comments are kept.
 *
 * Usage:  php tools/build_js.php           write every .min.js
 *         php tools/build_js.php --check   build into a temporary folder and
 *                                          compare; nothing is written (CI)
 * Needs:  Node.js with npx on the PATH. terser is fetched by npx at the
 *         version pinned in TERSER below, so every machine writes the same
 *         bytes; change it only together with a rebuild of every .min.js.
 * Exit:   0 when every .min.js was written, or is current; 1 when a build
 *         failed or, with --check, a .min.js differs from its sources.
 *
 * Line endings do not count: sources are read with LF endings, the output is
 * written with them, and --check compares with CR removed, so a checkout that
 * turned them into CRLF still passes.
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

const TERSER = 'terser@5.51.2';
const TERSER_FLAGS = '--compress --mangle --format ascii_only=true';

$check = in_array('--check', array_slice($argv, 1), true);

$repository = dirname(__DIR__);
$software = $repository . '/pinegrap';

// functions.php loads the modules under includes/fn/ without a connection;
// only pg_js_bundles() is needed from it.
require_once($software . '/functions.php');

$work = sys_get_temp_dir() . '/pg_build_js_' . getmypid();

if (!is_dir($work) && !mkdir($work, 0700, true)) {
	fwrite(STDERR, "Cannot create the work folder $work\n");
	exit(1);
}

$failed = 0;
$stale = 0;

foreach (pg_js_bundles() as $bundle => $sources) {

	// The sources with LF endings, numbered so terser reads them in order.
	$inputs = array();

	foreach ($sources as $index => $source) {
		$code = @file_get_contents($software . '/' . $source);

		if ($code === false) {
			echo "FAIL  $bundle: missing source $source\n";
			$failed++;
			continue 2;
		}

		$input = $work . '/' . sprintf('%02d', $index) . '_' . basename($source);
		file_put_contents($input, str_replace("\r\n", "\n", $code));
		$inputs[] = $input;
	}

	$output = $work . '/' . basename($bundle);
	@unlink($output);

	$command = 'npx --yes ' . TERSER . ' ' . implode(' ', array_map('escapeshellarg', $inputs))
		. ' ' . TERSER_FLAGS . ' --output ' . escapeshellarg($output) . ' 2>&1';

	$lines = array();
	exec($command, $lines, $status);

	if (($status !== 0) || !is_file($output)) {
		echo "FAIL  $bundle: terser exited with $status\n" . implode("\n", $lines) . "\n";
		$failed++;
		continue;
	}

	// One trailing newline, as the editors leave it.
	$built = rtrim(str_replace("\r\n", "\n", file_get_contents($output)), "\n") . "\n";
	$target = $software . '/' . $bundle;

	if ($check) {
		$current = is_file($target) ? str_replace("\r", '', file_get_contents($target)) : null;

		if ($current === $built) {
			echo "ok    $bundle\n";
		} else {
			echo 'STALE ' . $bundle . ': ' . (($current === null) ? 'missing' : 'differs from its sources') . " - run php tools/build_js.php\n";
			$stale++;
		}

		continue;
	}

	file_put_contents($target, $built);
	echo 'wrote ' . $bundle . ' (' . number_format(strlen($built)) . " bytes)\n";
}

array_map('unlink', glob($work . '/*'));
@rmdir($work);

if ($failed || $stale) {
	echo "\n" . ($failed ? "$failed build(s) failed. " : '') . ($stale ? "$stale .min.js file(s) out of date." : '') . "\n";
	exit(1);
}

exit(0);
