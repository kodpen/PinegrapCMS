<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Renames the panel's own button label class `btn-text` to `pg-btn-label` in
 * panel files (docs/memory/ui/bootstrap-6.md, "v6 names collide with own
 * classes"). Bootstrap 6 ships `.btn-text` as a button variant, so a label
 * span carrying it would be drawn as a button inside the button.
 *
 * Every `btn-text` in a panel file is the label span or a selector for it,
 * so the rename is a plain whole-word replace. Site, shared, editor and
 * vendor files are never touched (bs6_scope_kind()). Without --write it only
 * lists what it would change; a written file must still pass `php -l` or
 * `node --check`, or it is left as it was.
 *
 * Usage:  php tools/bs6_rename_collisions.php [--write] [--skip=path,path] [path ...]
 *
 * path ...      files, relative to pinegrap/; none means every panel file
 * --skip=…      comma-separated paths, relative to pinegrap/, left as they are
 * --write       write the renamed files
 *
 * Exit:   0 on success; 1 when a file was not written.
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

require_once(__DIR__ . '/bs6_rules.php');

$product = dirname(__DIR__) . '/pinegrap';

$write = false;
$skip = array();
$paths = array();

foreach (array_slice($argv, 1) as $arg) {
	if ($arg === '--write') {
		$write = true;
	} elseif (strpos($arg, '--skip=') === 0) {
		$skip = array_filter(array_map('trim', explode(',', substr($arg, 7))));
	} else {
		$paths[] = ltrim(preg_replace('#^pinegrap/#', '', str_replace('\\', '/', $arg)), './');
	}
}

if (!$paths) {
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($product, FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $file) {
		$paths[] = str_replace('\\', '/', substr($file->getPathname(), strlen($product) + 1));
	}
	sort($paths);
}

$pattern = '/(?<![\w-])btn-text(?![\w-])/';
$changed = 0;
$failed = 0;

foreach ($paths as $path) {
	if (in_array($path, $skip, true) || bs6_scope_kind($path) !== 'panel') {
		continue;
	}

	$full = $product . '/' . $path;
	if (!is_file($full)) {
		continue;
	}

	$source = file_get_contents($full);
	$count = 0;
	$result = preg_replace($pattern, 'pg-btn-label', $source, -1, $count);
	if (!$count) {
		continue;
	}

	$changed++;
	echo $path . ': ' . $count . "\n";

	if (!$write) {
		continue;
	}

	file_put_contents($full, $result);

	$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
	$check = null;
	if ($extension === 'php') {
		$check = 'php -l ' . escapeshellarg($full) . ' 2>&1';
	} elseif ($extension === 'js') {
		$check = 'node --check ' . escapeshellarg($full) . ' 2>&1';
	}

	if ($check !== null) {
		exec($check, $output, $status);
		if ($status !== 0) {
			file_put_contents($full, $source);
			echo '  not written: ' . implode(' ', $output) . "\n";
			$failed++;
		}
	}
}

echo $changed . ' file(s)' . ($write ? ' renamed' : ' to rename') . "\n";
exit($failed ? 1 : 0);
