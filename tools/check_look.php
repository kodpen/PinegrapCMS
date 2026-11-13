<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Counts, in the panel stylesheets, the rules outside look A's recipes: a
 * font size or weight off the type scale, a colour that is not a token
 * (tools/look_rules.php). The counts are kept in tools/check_look_baseline.json
 * and may only fall: a count above its baseline fails.
 *
 *   php tools/check_look.php            compare with the baseline
 *   php tools/check_look.php --update   write the lower counts back
 *
 * --update never raises a count: a rise is fixed in the stylesheet, by
 * building the rule from the recipes and tokens.
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

require_once(__DIR__ . '/look_rules.php');

$root = dirname(__DIR__) . '/pinegrap/';
$baseline_file = __DIR__ . '/check_look_baseline.json';
$update = in_array('--update', $argv, true);
$baseline = is_file($baseline_file) ? json_decode((string) file_get_contents($baseline_file), true) : null;
$first = !is_array($baseline);
$baseline = $first ? array() : $baseline;
$current = array();
$rose = array();
$fell = array();

foreach (pg_look_stylesheets() as $path) {
	if (!is_file($root . $path)) {
		continue;
	}
	$current[$path] = pg_look_counts((string) file_get_contents($root . $path));

	foreach ($current[$path] as $kind => $count) {
		$was = isset($baseline[$path][$kind]) ? (int) $baseline[$path][$kind] : 0;

		if (!$first && $count > $was) {
			$rose[] = sprintf('%s: %s %d, baseline %d', $path, $kind, $count, $was);
		} elseif ($count < $was) {
			$fell[] = sprintf('%s: %s %d, baseline %d', $path, $kind, $count, $was);
		}
	}
}

$kinds = array('size' => 'font sizes off the scale', 'weight' => 'font weights off the scale', 'colour' => 'colours outside the tokens');
$totals = array('size' => 0, 'weight' => 0, 'colour' => 0);
foreach ($current as $counts) {
	foreach ($counts as $kind => $count) {
		$totals[$kind] += $count;
	}
}
foreach ($kinds as $kind => $label) {
	printf("%6d  %s\n", $totals[$kind], $label);
}

if ($rose) {
	echo "\nA count rose above its baseline. Build the rule from the recipes and the --pg-* tokens (docs/memory/ui/look-check.md):\n  " . implode("\n  ", $rose) . "\n";
	exit(1);
}

if ($update || $first) {
	file_put_contents($baseline_file, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
	echo "\nBaseline written: tools/check_look_baseline.json\n";
} elseif ($fell) {
	echo "\nA count fell; lower the baseline with php tools/check_look.php --update:\n  " . implode("\n  ", $fell) . "\n";
}
exit(0);
