<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Builds the Artifact Canvas's second icon set from a Tabler Icons release:
 * pinegrap/assets/canvas/tabler-icons/icons.json ({ version, icons: { name:
 * path } }) and the release's LICENSE beside it. Only the outline icons
 * drawn by stroke alone are kept, each one's paths joined into one path
 * string in the icons' 24 x 24 box, so the canvas places it as one svg path
 * layer that takes a colour and a stroke width.
 *
 * Usage:  php tools/build_tabler_icons.php <package folder>
 *         where the folder is the unpacked npm package @tabler/icons
 *         (`npm pack @tabler/icons@<version>`, then `tar xzf`); it holds
 *         package.json, LICENSE and tabler-nodes-outline.json.
 * Exit:   0 when both files were written; 1 when the folder is not a
 *         release of the package.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (PHP_SAPI !== 'cli') {
	exit(1);
}

$from = isset($argv[1]) ? rtrim($argv[1], '/\\') : '';
$package = @json_decode((string) @file_get_contents($from . '/package.json'), true);
$nodes = @json_decode((string) @file_get_contents($from . '/tabler-nodes-outline.json'), true);
$license = (string) @file_get_contents($from . '/LICENSE');

if (!is_array($package) || (($package['name'] ?? '') !== '@tabler/icons') || !is_array($nodes) || (strpos($license, 'MIT License') === false)) {
	fwrite(STDERR, "Not an unpacked @tabler/icons package: $from\n");
	exit(1);
}

$icons = array();
$skipped = 0;

foreach ($nodes as $name => $list) {
	$paths = array();

	foreach ((array) $list as $node) {
		// A path with a fill, its own stroke or an opacity is not drawn by
		// the one stroke the canvas paints: such an icon is left out.
		if (!is_array($node) || (($node[0] ?? '') !== 'path') || !isset($node[1]['d']) || (count($node[1]) !== 1)) {
			$paths = array();
			break;
		}

		$paths[] = trim((string) $node[1]['d']);
	}

	if (!$paths || !preg_match('/^[a-z0-9-]{1,60}$/', (string) $name)) {
		$skipped++;
		continue;
	}

	$icons[(string) $name] = implode(' ', $paths);
}

ksort($icons, SORT_STRING);

$to = dirname(__DIR__) . '/pinegrap/assets/canvas/tabler-icons';

if (!is_dir($to)) {
	mkdir($to, 0755, true);
}

file_put_contents($to . '/icons.json', json_encode(array('version' => (string) $package['version'], 'icons' => $icons), JSON_UNESCAPED_SLASHES) . "\n");
file_put_contents($to . '/LICENSE', str_replace("\r", '', $license));

echo 'Tabler Icons ' . $package['version'] . ': ' . count($icons) . ' icons written, ' . $skipped . " left out.\n";
