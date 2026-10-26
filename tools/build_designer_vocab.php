<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Writes the Bootstrap vocabulary of the visual designer's smart completion
 * (pinegrap/assets/js/designer_vocab/bootstrap5.json) from the Bootstrap copy
 * the software ships: bootstrap.min.css and the sources inside
 * bootstrap.bundle.min.js.map (pg_designer_vocab_build() in
 * includes/fn/designer_vocab.php). Run it after replacing Bootstrap; --check
 * only reports whether the file is out of date (the unit test does the same).
 *
 * Usage:  php tools/build_designer_vocab.php [--check] [directory]   (default: pinegrap/)
 * Exit:   0 when the file is current (or was written), 1 otherwise.
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

$check = in_array('--check', $argv, true);
$args  = array_values(array_filter(array_slice($argv, 1), function ($a) { return $a !== '--check'; }));
$root  = rtrim(isset($args[0]) ? $args[0] : dirname(__DIR__) . '/pinegrap', '/');

if (!is_file($root . '/includes/fn/designer_vocab.php')) {
	fwrite(STDERR, "Not a Pinegrap directory: $root\n");
	exit(1);
}

define('PG_FUNCTIONS_DIR', $root);
require_once($root . '/includes/fn/designer_vocab.php');

$bootstrap = $root . '/assets/lib/bootstrap-5.3.8';
$css = @file_get_contents($bootstrap . '/css/bootstrap.min.css');
$map = json_decode((string) @file_get_contents($bootstrap . '/js/bootstrap.bundle.min.js.map'), true);

if ($css === false || !is_array($map)) {
	fwrite(STDERR, "Bootstrap files not found under $bootstrap\n");
	exit(1);
}

$json = pg_designer_vocab_json(pg_designer_vocab_build($css, $map));
$file = $root . '/assets/js/designer_vocab/bootstrap5.json';
$current = is_file($file) ? file_get_contents($file) : null;

if ($current === $json) {
	echo "bootstrap5.json is up to date.\n";
	exit(0);
}

if ($check) {
	fwrite(STDERR, "bootstrap5.json is out of date: run php tools/build_designer_vocab.php\n");
	exit(1);
}

if (!is_dir(dirname($file))) {
	mkdir(dirname($file), 0755, true);
}

file_put_contents($file, $json);
echo 'Wrote ' . $file . ' (' . strlen($json) . " bytes).\n";
