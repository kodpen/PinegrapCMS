<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Reports what is still Bootstrap 5 in the panel: classes tools/bs6_migrate.php
 * would rewrite, classes that need a hand (structure, no v6 counterpart),
 * Bootstrap JS calls and events of renamed components, data attributes,
 * jQuery plugin calls, v5 CSS variables and v5 component selectors in CSS
 * (the check list of the bootstrap-v5-v6-migration skill, step 6).
 *
 * Panel files are counted; files shared with the site and the visual editor's
 * files are listed apart, since they are converted per function. Site files
 * stay on Bootstrap 5 and are not read, nor is code marked
 * `/* @pg-site-output *\/` (bs6_site_output_ranges()).
 *
 * After the switch (the panel head loads Bootstrap 6) a class the v6 dist
 * defines is read as a v6 class, so converted sizes (`p-5`) are not counted.
 *
 * Usage:  php tools/check_bs6.php [--verbose] [--strict] [--top=N]
 *
 * --verbose  every finding with its file and line
 * --strict   exit 1 when a panel file still holds a finding (from the switch,
 *            step B8 of docs/memory/plans/bootstrap-6-panel-steps.md, on)
 * --top=N    files listed per check (default 5)
 *
 * Exit:   0, or 1 with --strict when panel findings remain.
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

$verbose = in_array('--verbose', $argv, true);
$strict = in_array('--strict', $argv, true);
$top = 5;

foreach ($argv as $arg) {
	if (preg_match('/^--top=(\d+)$/', $arg, $m)) {
		$top = (int) $m[1];
	}
}

$product = dirname(__DIR__) . '/pinegrap';

// After the switch the panel head loads Bootstrap 6 and panel markup holds v6
// classes: a class the v6 dist defines (`p-5`, `md:col-6`) is then not a v5
// class to rewrite. Before it every class is read as Bootstrap 5.
$converted = bs6_panel_head_is_v6($product);

if ($converted) {
	bs6_converted_tree_classes(bs6_dist_classes((string) file_get_contents($product . '/assets/lib/bootstrap-6/css/bootstrap.css')));
}

// Checks read from the source text. Each counts its matches per line.
$text_checks = array(
	'Bootstrap JS call of a renamed or bridged component' => '/\bbootstrap\.(Modal|Offcanvas|Dropdown|Tooltip|Popover|Collapse|Tab|Toast|Alert|Button|Carousel|ScrollSpy)\b/',
	'jQuery Bootstrap plugin call (no jQuery bridge in v6)' => '/\.(modal|dropdown|offcanvas|tooltip|popover|collapse|toast)\(\s*(\'|"|\{|\)|\()/',
	'event of a renamed component (*.bs.dialog, *.bs.drawer, *.bs.menu)' => '/\b(show|shown|hide|hidden|hidePrevented)\.bs\.(modal|offcanvas|dropdown)\b/',
	'data attribute of a renamed component' => '/data-bs-(toggle|dismiss)\s*=\s*\\\\?["\'](modal|offcanvas|dropdown)\b/',
	'carousel ride attribute (data-bs-autoplay in v6)' => '/data-bs-ride\b/',
	'popperConfig (floatingConfig in v6)' => '/\bpopperConfig\b/',
	'box or state of a v5 modal named in code (the <dialog> is the box)' => '/(?<![\w-])modal-(dialog|content|backdrop|open|static|fullscreen)(?![\w-])/',
	'v5 component variable (--bs-dropdown-*, --bs-offcanvas-*, --bs-modal-*)' => '/--bs-(dropdown|offcanvas|modal)-[a-z-]+/',
	'v5 RGB variable (removed in v6: use color-mix())' => '/var\(\s*--bs-[a-z0-9-]+-rgb\s*\)/',
	'v5 component selector in a style block' => '/\.(modal-dialog|modal-content|modal-backdrop|dropdown-menu|dropdown-item|dropdown-toggle|offcanvas|form-check-input|form-check|form-switch|form-select|accordion-button|btn-close-white|collapsing)\b(?=[^\n{;]*\{)/',
);

$stats = array();   // check => kind => count
$files = array();   // check => path => count
$lines = array();   // check => list of "path:line  text"

$add = function ($check, $kind, $path, $line, $text) use (&$stats, &$files, &$lines) {
	$stats[$check][$kind] = isset($stats[$check][$kind]) ? $stats[$check][$kind] + 1 : 1;

	if ($kind === 'panel') {
		$files[$check][$path] = isset($files[$check][$path]) ? $files[$check][$path] + 1 : 1;
	}

	$lines[$check][] = "pinegrap/$path:$line  [$kind]  $text";
};

foreach (bs6_scope_files($product) as $path => $kind) {
	if ($kind === 'site') {
		continue;
	}

	$source = (string) file_get_contents($product . '/' . $path);
	$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
	bs6_current_path($path);
	$site_output = bs6_site_output_ranges($source);

	if ($ext === 'css') {
		// A selector on a class v6 renames or drops stops matching after the
		// switch without an error.
		if (preg_match_all('/\\.([A-Za-z][A-Za-z0-9_-]*)(?=[^{};]*\\{)/', $source, $selectors, PREG_OFFSET_CAPTURE)) {
			foreach ($selectors[1] as $selector) {
				$mapped = bs6_map_token($selector[0]);
				if ($mapped['review'] !== null || $mapped['tokens'] !== array($selector[0])) {
					$add('CSS selector on a class v6 renames or drops', $kind, $path, bs6_line_of($source, $selector[1]), '.' . $selector[0]);
				}
			}
		}
	} else {
		$result = bs6_convert_source($source, null, bs6_reads_class_strings($path));

		if ($result['changed'] > 0) {
			$check = 'classes the codemod rewrites';
			$stats[$check][$kind] = (isset($stats[$check][$kind]) ? $stats[$check][$kind] : 0) + $result['changed'];
			if ($kind === 'panel') {
				$files[$check][$path] = $result['changed'];
			}
		}

		foreach ($result['reviews'] as $review) {
			$add('class that needs a hand: ' . $review[2], $kind, $path, $review[0], $review[1]);
		}
	}

	foreach ($text_checks as $check => $pattern) {
		// Stylesheets were read selector by selector above.
		if ($ext === 'css' && $check === 'v5 component selector in a style block') {
			continue;
		}

		if (!preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
			continue;
		}

		foreach ($matches[0] as $match) {
			if ($site_output && bs6_in_ranges($match[1], $site_output)) {
				continue;
			}

			$add($check, $kind, $path, bs6_line_of($source, $match[1]), $match[0]);
		}
	}
}

bs6_current_path(false);

ksort($stats);
$panel_total = 0;

foreach ($stats as $check => $kinds) {
	$panel = isset($kinds['panel']) ? $kinds['panel'] : 0;
	$panel_total += $panel;
	$aside = array();

	foreach (array('shared', 'editor') as $kind) {
		if (!empty($kinds[$kind])) {
			$aside[] = "$kind {$kinds[$kind]}";
		}
	}

	echo sprintf("%6d  %s%s\n", $panel, $check, $aside ? '  (' . implode(', ', $aside) . ')' : '');

	if (!empty($files[$check]) && $top > 0) {
		arsort($files[$check]);
		foreach (array_slice($files[$check], 0, $top, true) as $path => $count) {
			echo sprintf("          %5d  pinegrap/%s\n", $count, $path);
		}
	}

	if ($verbose && !empty($lines[$check])) {
		foreach ($lines[$check] as $row) {
			echo "            $row\n";
		}
	}
}

echo "\n$panel_total findings in panel files (" . ($converted ? 'converted tree: classes the Bootstrap 6 dist defines are not counted' : 'Bootstrap 5 tree') . "); shared and editor files are counted apart (docs/memory/ui/bootstrap-6-codemod.md).\n";

exit(($strict && $panel_total > 0) ? 1 : 0);
