<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The hand fixes of the Bootstrap 6 switch that a script can make, run by
 * `php tools/bs6_migrate.php --switch` after the class conversion
 * (docs/memory/plans/bootstrap-6-switch.md). Each fix finds its place by
 * the text around it, not by line number, and leaves a file it already
 * fixed as it is, so the switch branch can be built again from the latest
 * development at any time.
 *
 * Fixes:
 *   head     the panel head loads Bootstrap 6: the stylesheet, the bridge
 *            module (assets/js/pg_bs6_bridge.js) in place of the classic
 *            bundle, the layer order and the third-party sheets in the
 *            vendor layer
 *   sheet    other panel heads load the Bootstrap 6 stylesheet
 *   layer    panel stylesheets go into @layer custom
 *   style    inline <style> blocks of panel screens go into @layer custom
 *   navbar   .software-navbar.fixed-top keeps z-index 1030
 *   stacked  the settings drawer no longer restores body.modal-open after a
 *            stacked dialog closes: Bootstrap 6 scrolls nothing behind a
 *            <dialog>, and the bridge keeps html.dialog-open while one is open
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

require_once(__DIR__ . '/bs6_rules.php');

// Bootstrap 6's own layer order with `vendor` before `custom`. jQuery UI,
// DataTables and select2 go into `vendor`: an unlayered sheet would beat
// every layer, and the panel's overrides of them sit in `custom`.
const BS6_SWITCH_LAYER_ORDER = 'colors, config, root, reboot, layout, content, forms, components, vendor, custom, helpers, utilities';

// The comment a layered panel stylesheet starts with; its presence marks a
// file already done.
const BS6_SWITCH_LAYER_NOTE = '/* Panel rules sit in Bootstrap 6\'s custom layer: above its components,
   below its helpers and utilities, which win as they did with !important. */';

// Panel screens whose inline <style> blocks are printed under the panel
// head. Other panel files print their blocks into documents of their own
// (PDF through dompdf, which drops an @layer block, exports, receipts) or
// into site pages; they stay as they are.
function bs6_switch_style_files()
{
	return array(
		'edit_comment.php', 'editor_select_image.php', 'image_editor_edit.php', 'includes/api/console.php',
		'includes/erp/drawer.php', 'install/index.php', 'marketplace_settings.php', 'print_order.php',
		'print_packing_slip.php', 'product_builder.php', 'router.php', 'theme_designer.php', 'translations.php',
		'view_folders.php', 'view_log.php',
	);
}

// Panel files with a head of their own that names the Bootstrap 5
// stylesheet.
function bs6_switch_sheet_files()
{
	return array(
		'get_erp_edoc_test.php', 'includes/fn/prototype.php', 'includes/fn/prototype_links.php',
		'parasut_connect.php', 'workspace_approval.php', 'workspace_guest.php',
	);
}

// array(path relative to pinegrap/, fix name, function) in the order run.
function bs6_switch_plan($root)
{
	$plan = array(array('includes/fn/output.php', 'head', 'bs6_switch_head'));

	foreach (bs6_switch_sheet_files() as $path) {
		$plan[] = array($path, 'sheet', 'bs6_switch_sheet');
	}

	foreach (bs6_switch_stylesheets($root) as $path) {
		$plan[] = array($path, 'layer', 'bs6_switch_layer_sheet');
	}

	$plan[] = array('assets/css/backend.src.css', 'navbar', 'bs6_switch_navbar');

	foreach (bs6_switch_style_files() as $path) {
		$plan[] = array($path, 'style', 'bs6_switch_style_blocks');
	}

	$plan[] = array('assets/js/backend.src.js', 'stacked', 'bs6_switch_drop_stacked_repair');

	return $plan;
}

// Panel and editor stylesheets (not minified copies), sorted.
function bs6_switch_stylesheets($root)
{
	$paths = array();

	foreach (glob(rtrim($root, '/') . '/assets/css/*.css') as $file) {
		$path = 'assets/css/' . basename($file);
		$kind = bs6_scope_kind($path);

		if (($kind === 'panel' || $kind === 'editor') && !preg_match('/\.min\.css$/', $path)) {
			$paths[] = $path;
		}
	}

	sort($paths);

	return $paths;
}

// Each fix returns array('source' => text, 'status' => 'applied'|'done'|'missing').
function bs6_switch_result($source, $status)
{
	return array('source' => $source, 'status' => $status);
}

// The panel head in output_control_panel_header_includes().
function bs6_switch_head($source)
{
	$v5_sheet = '/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css';
	$bundle = '#[ \t]*<script type="text/javascript" src="\' \. OUTPUT_PATH \. OUTPUT_SOFTWARE_DIRECTORY \. \'/assets/lib/bootstrap-5\.3\.8/js/bootstrap\.bundle\.min\.js"></script>\n#';
	$vendor = '#<link rel="stylesheet" type="text/css" href="(\' \. OUTPUT_PATH \. OUTPUT_SOFTWARE_DIRECTORY \. \'/assets/lib/(?:Jquery/ui|DataTables|select2)/[^"]*)"\s*/?>#';
	$first_link = '#\n([ \t]*)(<link rel="stylesheet" type="text/css" href="\' \. OUTPUT_PATH \. OUTPUT_SOFTWARE_DIRECTORY \. \'/assets/lib/Jquery/ui/jquery-ui\.min\.css)#';

	if (strpos($source, 'pg_bs6_bridge.js') !== false) {
		return bs6_switch_result($source, 'done');
	}

	if (substr_count($source, $v5_sheet) !== 2 || !preg_match($bundle, $source) || !preg_match($first_link, $source)) {
		return bs6_switch_result($source, 'missing');
	}

	$source = str_replace($v5_sheet, '/assets/lib/bootstrap-6/css/bootstrap.min.css', $source);

	$bridge = '    <script type="module" src="\' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . \'/assets/js/pg_bs6_bridge.js?v=\' . @filemtime(PG_FUNCTIONS_DIR . \'/assets/js/pg_bs6_bridge.js\') . \'"></script>' . "\n";
	$source = preg_replace($bundle, $bridge, $source, 1);

	$source = preg_replace($first_link, "\n" . '$1<style>@layer ' . BS6_SWITCH_LAYER_ORDER . ';</style>' . "\n" . '$1$2', $source, 1);
	$source = preg_replace($vendor, '<style>@import url("$1") layer(vendor);</style>', $source);

	return bs6_switch_result($source, 'applied');
}

// A head of its own: only the stylesheet changes, these screens load no
// Bootstrap script.
function bs6_switch_sheet($source)
{
	$count = 0;
	$source = str_replace('/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css', '/assets/lib/bootstrap-6/css/bootstrap.min.css', $source, $count);

	if ($count > 0) {
		return bs6_switch_result($source, 'applied');
	}

	return bs6_switch_result($source, strpos($source, '/assets/lib/bootstrap-6/css/bootstrap.min.css') !== false ? 'done' : 'missing');
}

// The whole stylesheet into @layer custom. @charset and @import must come
// before any rule, so they stay above the block.
function bs6_switch_layer_sheet($source)
{
	if (strpos($source, BS6_SWITCH_LAYER_NOTE) !== false) {
		return bs6_switch_result($source, 'done');
	}

	$head = '';

	if (preg_match('/^(?:@charset[^;]*;\s*)?(?:@import[^;]*;\s*)*/', $source, $match) && $match[0] !== '') {
		$head = rtrim($match[0]) . "\n\n";
		$source = substr($source, strlen($match[0]));
	}

	$body = rtrim($source);

	return bs6_switch_result($head . BS6_SWITCH_LAYER_NOTE . "\n@layer custom {\n\n" . $body . "\n\n}\n", 'applied');
}

// Outside the layer: Bootstrap 6 sets .fixed-top to 1020 in its helpers
// layer, which beats @layer custom, and 1020 falls below the sidebar and the
// open menu's backdrop.
function bs6_switch_navbar($source)
{
	$rule = '.software-navbar.fixed-top {';

	if (strpos($source, $rule) !== false) {
		return bs6_switch_result($source, 'done');
	}

	if (strpos($source, BS6_SWITCH_LAYER_NOTE) === false) {
		return bs6_switch_result($source, 'missing');
	}

	return bs6_switch_result(rtrim($source) . "\n\n/* Unlayered: Bootstrap 6 gives .fixed-top 1020 in its helpers layer, which\n   beats @layer custom, and 1020 falls below the sidebar and the menu backdrop. */\n"
		. $rule . "\n  z-index: 1030;\n}\n", 'applied');
}

// Inline <style> blocks into @layer custom. A block that holds an @import,
// already starts with @layer or carries the site's own CUSTOM_CSS is left.
function bs6_switch_style_blocks($source)
{
	$applied = 0;
	$done = 0;

	$source = preg_replace_callback('#(<style\b[^>]*>)(.*?)(</style>|<\\\\/style>)#is', function ($m) use (&$applied, &$done) {
		$css = $m[2];

		if (preg_match('/^\s*@layer\b/', $css)) {
			$done++;
			return $m[0];
		}

		if (trim($css) === '' || stripos($css, '@import') !== false || strpos($css, 'CUSTOM_CSS') !== false) {
			return $m[0];
		}

		$applied++;
		return $m[1] . '@layer custom {' . $css . '}' . $m[3];
	}, $source);

	if ($applied > 0) {
		return bs6_switch_result($source, 'applied');
	}

	return bs6_switch_result($source, $done > 0 ? 'done' : 'missing');
}

// The stacked-dialog repair in the settings drawer's click handler.
function bs6_switch_drop_stacked_repair($source)
{
	$pattern = '#\n[ \t]*if \(!target\._pgStacked\) \{\n.*?document\.body\.classList\.add\(\'modal-open\'\);\n\s*\}\n\s*\}\);\n\s*\}\n#s';

	if (!preg_match($pattern, $source)) {
		return bs6_switch_result($source, strpos($source, '_pgStacked') === false ? 'done' : 'missing');
	}

	return bs6_switch_result(preg_replace($pattern, "\n", $source, 1), 'applied');
}

// Runs the plan over $root (the pinegrap/ folder). Returns a list of
// array(path, fix, status, error); with $write the changed files are
// written, unless the result fails `php -l` or `node --check`.
function bs6_switch_run($root, $write)
{
	$root = rtrim($root, '/');
	$sources = array();
	$report = array();

	foreach (bs6_switch_plan($root) as $step) {
		list($path, $fix, $function) = $step;
		$file = $root . '/' . $path;

		if (!isset($sources[$path])) {
			if (!is_file($file)) {
				$report[] = array($path, $fix, 'missing', 'no such file');
				continue;
			}
			$sources[$path] = array('old' => (string) file_get_contents($file), 'new' => null);
			$sources[$path]['new'] = $sources[$path]['old'];
		}

		$result = $function($sources[$path]['new']);
		$sources[$path]['new'] = $result['source'];
		$report[] = array($path, $fix, $result['status'], null);
	}

	foreach ($sources as $path => $source) {
		if ($source['new'] === $source['old']) {
			continue;
		}

		$error = bs6_syntax_error($path, $source['new']);

		if ($error !== null) {
			foreach ($report as $i => $line) {
				if ($line[0] === $path && $line[2] === 'applied') {
					$report[$i][2] = 'failed';
					$report[$i][3] = $error;
				}
			}
			continue;
		}

		if ($write && file_put_contents($root . '/' . $path, $source['new']) === false) {
			foreach ($report as $i => $line) {
				if ($line[0] === $path && $line[2] === 'applied') {
					$report[$i][2] = 'failed';
					$report[$i][3] = 'could not write';
				}
			}
		}
	}

	return $report;
}
