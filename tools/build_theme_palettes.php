<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Writes the built-in colour palettes of the visual editor
 * (pinegrap/assets/css/themes/palettes/<key>.css) from the list in
 * pg_design_palettes(), with the same generator that writes a custom palette
 * from the editor (pg_design_palette_css()). Run it after changing a colour
 * or the generator; --check only reports files that are out of date.
 *
 * Usage:  php tools/build_theme_palettes.php [--check] [directory]   (default: pinegrap/)
 * Exit:   0 when every file is current (or was written), 1 otherwise.
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

if (!is_file($root . '/includes/fn/design_themes.php')) {
	fwrite(STDERR, "Not a Pinegrap directory: $root\n");
	exit(1);
}

define('PG_FUNCTIONS_DIR', $root);

// The palette names go through lang(); the files carry the English names.
if (!function_exists('lang')) {
	function lang($s) { return is_array($s) ? $s['string'] : $s; }
}

require $root . '/includes/fn/design_themes.php';

$dir = $root . '/assets/css/themes/palettes';
if (!is_dir($dir) && !$check && !mkdir($dir, 0755, true)) {
	fwrite(STDERR, "Cannot create $dir\n");
	exit(1);
}

$stale = 0;
foreach (pg_design_palettes() as $key => $p) {
	$css  = pg_design_palette_css($p['primary'], $p['secondary'], $p['name']);
	$file = $dir . '/' . $key . '.css';
	$have = is_file($file) ? file_get_contents($file) : null;
	if ($have === $css) continue;
	$stale++;
	if ($check) {
		echo "out of date: palettes/$key.css\n";
		continue;
	}
	file_put_contents($file, $css);
	echo "written: palettes/$key.css\n";
}

if ($check && $stale) {
	echo "$stale palette file(s) out of date. Run: php tools/build_theme_palettes.php\n";
	exit(1);
}
echo $check ? "All palette files are current.\n" : "Done.\n";
exit(0);
