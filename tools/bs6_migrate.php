<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Rewrites Bootstrap 5 classes in panel files to their Bootstrap 6 form with
 * the rules of tools/bs6_rules.php (docs/memory/ui/bootstrap-6-codemod.md).
 * Without --write it only reports: nothing is changed.
 *
 * The size rules (spacing, font size, radius) map v5 values to the v6 key of
 * the same size, so they run once per line: --all on switch day, --diff for
 * the lines a branch older than the switch added. A second run over the same
 * line would grow its sizes again.
 *
 * Usage:  php tools/bs6_migrate.php [--write] [--verbose] [--force] path ...
 *         php tools/bs6_migrate.php [--write] [--verbose] --all
 *         php tools/bs6_migrate.php [--write] [--verbose] --diff=<base>
 *
 * path ...      files, relative to the repository or to pinegrap/
 * --all         every panel file
 * --diff=<ref>  only the lines added since the merge base of <ref> and the
 *               working tree (`git diff -U0`), in panel files
 * --write       write the converted files
 * --verbose     list each class that needs a hand, with its line
 * --force       also convert named files that are not panel files (shared,
 *               editor or site files; see the memory file first)
 *
 * Exit:   0 on success; 1 on bad usage, a git error or a file not written.
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

$repository = dirname(__DIR__);
$product = $repository . '/pinegrap';

$write = false;
$verbose = false;
$force = false;
$all = false;
$diff_base = null;
$paths = array();

foreach (array_slice($argv, 1) as $arg) {
	if ($arg === '--write') {
		$write = true;
	} elseif ($arg === '--verbose') {
		$verbose = true;
	} elseif ($arg === '--force') {
		$force = true;
	} elseif ($arg === '--all') {
		$all = true;
	} elseif (strncmp($arg, '--diff=', 7) === 0) {
		$diff_base = substr($arg, 7);
	} elseif (strncmp($arg, '--', 2) === 0) {
		fwrite(STDERR, "Unknown option: $arg\n");
		exit(1);
	} else {
		$paths[] = $arg;
	}
}

if ((int) $all + (int) ($diff_base !== null) + (int) (count($paths) > 0) !== 1) {
	fwrite(STDERR, "Give file paths, --all or --diff=<ref> (one of them).\n");
	exit(1);
}

// path relative to pinegrap/ => array of line numbers to convert (null: all)
$targets = array();

if ($all) {
	foreach (bs6_scope_files($product) as $path => $kind) {
		if ($kind === 'panel') {
			$targets[$path] = null;
		}
	}
} elseif ($diff_base !== null) {
	$base = bs6_git($repository, array('merge-base', $diff_base, 'HEAD'));

	if ($base['status'] !== 0) {
		fwrite(STDERR, "git merge-base failed: {$base['err']}\n");
		exit(1);
	}

	$diff = bs6_git($repository, array('diff', '-U0', '--no-color', trim($base['out']), '--', 'pinegrap'));

	if ($diff['status'] !== 0) {
		fwrite(STDERR, "git diff failed: {$diff['err']}\n");
		exit(1);
	}

	foreach (bs6_parse_added_lines($diff['out']) as $path => $lines) {
		$path = preg_replace('#^pinegrap/#', '', $path);

		if (bs6_scope_kind($path) === 'panel') {
			$targets[$path] = $lines;
		}
	}
} else {
	foreach ($paths as $path) {
		$path = str_replace('\\', '/', $path);
		$path = preg_replace('#^(\./)?(pinegrap/)?#', '', $path);
		$kind = bs6_scope_kind($path);

		if (!is_file($product . '/' . $path)) {
			fwrite(STDERR, "No such file: pinegrap/$path\n");
			exit(1);
		}

		if ($kind !== 'panel' && !$force) {
			fwrite(STDERR, "Skipped pinegrap/$path: a $kind file (--force converts it anyway).\n");
			continue;
		}

		$targets[$path] = null;
	}
}

$total_changed = 0;
$total_reviews = 0;
$files_changed = 0;
$failed = false;
$reasons = array();

foreach ($targets as $path => $lines) {
	$file = $product . '/' . $path;
	$source = (string) file_get_contents($file);
	$result = bs6_convert_source($source, $lines);

	foreach ($result['reviews'] as $review) {
		$reasons[$review[2]] = isset($reasons[$review[2]]) ? $reasons[$review[2]] + 1 : 1;

		if ($verbose) {
			echo "  pinegrap/$path:{$review[0]}  {$review[1]}  ({$review[2]})\n";
		}
	}

	$total_reviews += count($result['reviews']);

	if ($result['changed'] === 0) {
		continue;
	}

	if ($write) {
		$error = bs6_syntax_error($path, $result['source']);

		if ($error !== null) {
			fwrite(STDERR, "Not written, the result does not parse: pinegrap/$path\n  $error\n");
			$failed = true;
			continue;
		}
	}

	$files_changed++;
	$total_changed += $result['changed'];
	echo ($write ? 'converted' : 'would convert') . "  pinegrap/$path  ({$result['changed']} classes)\n";

	if ($write && file_put_contents($file, $result['source']) === false) {
		fwrite(STDERR, "Could not write pinegrap/$path\n");
		$failed = true;
	}
}

echo "\n" . ($write ? 'Converted' : 'Would convert') . " $total_changed classes in $files_changed of " . count($targets) . " files.\n";

if ($total_reviews > 0) {
	echo "$total_reviews classes need a hand" . ($verbose ? '' : ' (--verbose lists them)') . ":\n";
	arsort($reasons);
	foreach ($reasons as $reason => $count) {
		echo sprintf("  %5d  %s\n", $count, $reason);
	}
}

exit($failed ? 1 : 0);
