<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Builds the minified stylesheets the panel serves. The list of what is built
 * from what is pg_css_bundles() (pinegrap/includes/fn/output.php), the same
 * list the panel reads its stylesheet address from. Each .min.css is its
 * source through pg_css_minify() (tools/css_minify.php): no comments and only
 * the whitespace that counts, nothing reordered or rewritten.
 *
 * Usage:  php tools/build_css.php           write every .min.css
 *         php tools/build_css.php --check   compare; nothing is written (CI)
 * Needs:  PHP only.
 * Exit:   0 when every .min.css was written, or is current; 1 when a source
 *         is missing or, with --check, a .min.css differs from its source.
 *
 * Line endings do not count: the source is read with LF endings and --check
 * compares with CR removed.
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

$check = in_array('--check', array_slice($argv, 1), true);

$repository = dirname(__DIR__);
$software = $repository . '/pinegrap';

// functions.php loads the modules under includes/fn/ without a connection;
// only pg_css_bundles() is needed from it.
require_once($software . '/functions.php');
require_once(__DIR__ . '/css_minify.php');

$failed = 0;
$stale = 0;

foreach (pg_css_bundles() as $bundle => $source) {
	$css = @file_get_contents($software . '/' . $source);

	if ($css === false) {
		echo "FAIL  $bundle: missing source $source\n";
		$failed++;
		continue;
	}

	// One trailing newline, as the editors leave it.
	$built = pg_css_minify($css) . "\n";
	$target = $software . '/' . $bundle;

	if ($check) {
		$current = is_file($target) ? str_replace("\r", '', file_get_contents($target)) : null;

		if ($current === $built) {
			echo "ok    $bundle\n";
		} else {
			echo 'STALE ' . $bundle . ': ' . (($current === null) ? 'missing' : 'differs from its source') . " - run php tools/build_css.php\n";
			$stale++;
		}

		continue;
	}

	file_put_contents($target, $built);
	echo 'wrote ' . $bundle . ' (' . number_format(strlen($built)) . " bytes)\n";
}

if ($failed || $stale) {
	echo "\n" . ($failed ? "$failed source(s) missing. " : '') . ($stale ? "$stale .min.css file(s) out of date." : '') . "\n";
	exit(1);
}

exit(0);
