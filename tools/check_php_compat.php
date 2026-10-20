<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Compares a PHPCompatibility report with tools/phpcs-baseline.json.
 *
 * The product runs on every PHP version from 7.1 to 8.5. PHPCompatibility
 * (ruleset tools/phpcs.xml) reads the source for functions, constants, syntax
 * and ini settings that one of those versions does not have yet, no longer
 * has or has deprecated. It cannot see a function_exists() or defined()
 * guard, so the tree carries findings that are guarded, dead code that is
 * never reached, or planned work; tools/phpcs-baseline.json counts them per
 * file and sniff. More findings of a sniff in a file than the baseline counts
 * is new code and fails; fewer is a fix and passes.
 *
 * Usage:  bash tools/static_analysis.sh phpcompat runs phpcs and this script.
 *         By hand:
 *         phpcs --standard=tools/phpcs.xml --report=json --report-file=<report>
 *         php tools/check_php_compat.php <report>
 *         php tools/check_php_compat.php <report> --generate-baseline
 *         (phpcs exits 2 whenever it finds anything; this script decides.)
 * Exit:   0 when the report holds nothing beyond the baseline, or when the
 *         baseline was written; 1 when it does, or when the report is
 *         missing, unreadable or lists no product file (phpcs did not run).
 *
 * New code that uses a newer API behind a guard marks the line instead of
 * growing the baseline:
 *     // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.imageavifFound -- guarded by function_exists()
 * Regenerate the baseline after a fix (it only shrinks then), never to let a
 * new finding through.
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

$repository = str_replace('\\', '/', dirname(__DIR__));
$baseline_file = __DIR__ . '/phpcs-baseline.json';

$report_file = '';
$generate = false;

foreach (array_slice($argv, 1) as $argument) {

	if ($argument === '--generate-baseline') {
		$generate = true;
	} elseif ($report_file === '') {
		$report_file = $argument;
	} else {
		fwrite(STDERR, "Unexpected argument: $argument\n");
		exit(1);
	}

}

if ($report_file === '') {
	fwrite(STDERR, "Usage: php tools/check_php_compat.php <phpcs JSON report> [--generate-baseline]\n");
	exit(1);
}

// A path of the report relative to the repository, with forward slashes, so
// that the baseline reads the same on every machine.
function check_php_compat_relative($path, $repository)
{
	$path = str_replace('\\', '/', (string) $path);

	if (strpos($path, $repository . '/') === 0) {
		return substr($path, strlen($repository) + 1);
	}

	return $path;
}

$report = is_file($report_file) ? json_decode((string) file_get_contents($report_file), true) : null;

if (!is_array($report) || !isset($report['totals'], $report['files']) || !is_array($report['files'])) {
	fwrite(STDERR, "The report $report_file is missing or is not a phpcs JSON report: did phpcs run? Its own output above says why not.\n");
	exit(1);
}

// Findings per file and sniff: how many, and each one for the message.
$found = array();
$details = array();
$product_files = 0;

foreach ($report['files'] as $file => $result) {

	$path = check_php_compat_relative($file, $repository);

	if (strpos($path, 'pinegrap/') === 0) {
		$product_files++;
	}

	if (empty($result['messages']) || !is_array($result['messages'])) {
		continue;
	}

	foreach ($result['messages'] as $message) {

		$source = isset($message['source']) ? (string) $message['source'] : '';

		if (!isset($found[$path][$source])) {
			$found[$path][$source] = 0;
		}

		$found[$path][$source]++;

		$details[$path][$source][] = sprintf(
			'%s:%d  %s  %s',
			$path,
			isset($message['line']) ? (int) $message['line'] : 0,
			isset($message['type']) ? (string) $message['type'] : '',
			isset($message['message']) ? (string) $message['message'] : ''
		);
	}

}

// A ruleset whose file list matched nothing makes an empty report, which
// would pass every time.
if ($product_files === 0) {
	fwrite(STDERR, "The report lists no file under pinegrap/: the file list in tools/phpcs.xml matched nothing.\n");
	exit(1);
}

// "Internal.*" is phpcs itself failing on a file (it could not tokenize it):
// that file was not checked at all, which no baseline can accept.
$internal = array();

foreach ($found as $path => $sources) {
	foreach ($sources as $source => $count) {
		if (($source === '') || (strpos($source, 'Internal.') === 0)) {
			$internal = array_merge($internal, $details[$path][$source]);
		}
	}
}

if ($internal) {
	fwrite(STDERR, "phpcs could not check these files:\n  " . implode("\n  ", $internal) . "\n");
	exit(1);
}

ksort($found);

foreach ($found as $path => $sources) {
	ksort($sources);
	$found[$path] = $sources;
}

if ($generate) {

	$json = json_encode($found, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

	// Written next to the old one and moved over it, so an interrupted run
	// cannot leave half a baseline.
	$temporary = $baseline_file . '.tmp';

	if (($json === false) || (file_put_contents($temporary, $json . "\n") === false) || !rename($temporary, $baseline_file)) {
		fwrite(STDERR, "Could not write $baseline_file\n");
		exit(1);
	}

	$total = 0;

	foreach ($found as $sources) {
		$total += array_sum($sources);
	}

	echo "Baseline written: $total finding(s) in " . count($found) . " file(s).\n";
	exit(0);
}

$baseline = array();

if (is_file($baseline_file)) {

	$baseline = json_decode((string) file_get_contents($baseline_file), true);

	if (!is_array($baseline)) {
		fwrite(STDERR, "$baseline_file is not valid JSON.\n");
		exit(1);
	}

}

$new = 0;
$fixed = array();

foreach ($found as $path => $sources) {

	foreach ($sources as $source => $count) {

		$allowed = isset($baseline[$path][$source]) ? (int) $baseline[$path][$source] : 0;

		if ($count <= $allowed) {

			if ($count < $allowed) {
				$fixed[] = "$path  $source  ($count found, $allowed in the baseline)";
			}

			continue;
		}

		$new += $count - $allowed;

		// Which of the lines is the new one cannot be told from a count, so
		// every finding of that sniff in the file is listed.
		echo "NEW  $path  $source  ($count found, $allowed in the baseline)\n";
		echo '       ' . implode("\n       ", $details[$path][$source]) . "\n";
	}

}

foreach ($baseline as $path => $sources) {

	if (!is_array($sources)) {
		continue;
	}

	foreach ($sources as $source => $allowed) {
		if (!isset($found[$path][$source])) {
			$fixed[] = "$path  $source  (0 found, " . (int) $allowed . " in the baseline)";
		}
	}

}

if ($fixed) {
	echo "Fewer findings than the baseline counts (fixed; php tools/check_php_compat.php <report> --generate-baseline shrinks it):\n  " . implode("\n  ", $fixed) . "\n";
}

if ($new > 0) {
	echo "$new finding(s) beyond tools/phpcs-baseline.json. The product runs on PHP 7.1 to 8.5: use what every one of them has, or guard it and mark the line with // phpcs:ignore <sniff> -- <the guard>.\n";
	exit(1);
}

echo 'PHP compatibility: nothing new; the report\'s ' . (int) $report['totals']['errors'] . ' error(s) and ' . (int) $report['totals']['warnings'] . " warning(s) are all in the baseline.\n";
exit(0);
