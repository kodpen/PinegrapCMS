<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Syntax-checks every PHP file in the product tree with `php -l`.
 * Vendored third-party libraries are skipped: they ship as-is and a parse
 * error there is the vendor's, not ours.
 *
 * Usage:  php tools/lint.php [directory]      (default: pinegrap/)
 * Exit:   0 when every file parses, 1 otherwise.
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

// Vendored libraries and generated output, relative to the scanned root.
$skip_directories = array(
	'includes/phpmailer',
	'includes/stripe',
	'includes/iyzipay-php',
	'includes/phpexcel',
	'includes/boxpacker',
	'includes/dompdf',
	'assets/lib',
	'data/backups',
	'data/temp',
	'data/cache',
);

$root = isset($argv[1]) ? rtrim($argv[1], '/\\') : dirname(__DIR__) . '/pinegrap';

if (!is_dir($root)) {
	fwrite(STDERR, "Directory not found: $root\n");
	exit(1);
}

$root_real = realpath($root);

$skip_real = array();

foreach ($skip_directories as $relative) {

	$path = realpath($root_real . '/' . $relative);

	if ($path !== false) {
		$skip_real[] = $path . DIRECTORY_SEPARATOR;
	}

}

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($root_real, FilesystemIterator::SKIP_DOTS),
	RecursiveIteratorIterator::LEAVES_ONLY
);

$checked = 0;
$skipped = 0;
$failures = array();

foreach ($iterator as $file) {

	if (strtolower($file->getExtension()) !== 'php') {
		continue;
	}

	$path = $file->getPathname();

	$in_skipped_directory = false;

	foreach ($skip_real as $prefix) {

		if (strpos($path, $prefix) === 0) {
			$in_skipped_directory = true;
			break;
		}

	}

	if ($in_skipped_directory) {
		$skipped++;
		continue;
	}

	$checked++;

	$output = array();
	$status = 0;

	exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $status);

	if ($status !== 0) {
		$failures[$path] = trim(implode("\n", $output));
	}

}

$relative_root = $root_real . DIRECTORY_SEPARATOR;

foreach ($failures as $path => $message) {
	echo 'FAIL  ' . str_replace($relative_root, '', $path) . "\n";
	echo '      ' . str_replace("\n", "\n      ", $message) . "\n";
}

echo "\n";
echo 'Checked ' . $checked . ' file(s), skipped ' . $skipped . ' vendored file(s).' . "\n";

if (count($failures) > 0) {
	echo 'FAILED: ' . count($failures) . ' file(s) did not parse.' . "\n";
	exit(1);
}

echo "OK: every file parses.\n";
exit(0);
