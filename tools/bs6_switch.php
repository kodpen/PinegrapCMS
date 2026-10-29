<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The hand fixes of the Bootstrap 6 switch that a script can make, run by
 * `php tools/bs6_migrate.php --switch` after the class conversion
 * (docs/memory/ui/bootstrap-6-codemod.md). Each fix finds its place by
 * the text around it, not by line number, and leaves a file it already
 * fixed as it is, so the switch branch can be built again from the latest
 * development at any time.
 *
 * Fixes:
 *   head     the panel head loads Bootstrap 6: the stylesheet, the bridge
 *            module (assets/js/pg_bs6_bridge.js) in place of the classic
 *            bundle, the layer order and the third-party sheets (icon fonts
 *            included) in the vendor layer
 *   sheet    other panel heads load the Bootstrap 6 stylesheet
 *   layer    panel stylesheets go into @layer custom
 *   style    inline <style> blocks of panel screens go into @layer custom
 *   navbar   .software-navbar.fixed-top keeps z-index 1030
 *   stacked  the settings drawer no longer restores body.modal-open after a
 *            stacked dialog closes: Bootstrap 6 scrolls nothing behind a
 *            <dialog>, and the bridge keeps html.dialog-open while one is open
 *   selectors  selectors in panel stylesheets on classes v6 renames: the v6
 *            class (a compound for a pair, `.btn-solid.theme-primary`) or the
 *            new check markup; structural ones (modal, dropdown) are left
 *   pins     unit tests that pin panel markup to its v5 classes take the v6
 *            classes the conversion writes (bs6_switch_test_pins())
 *   checks   checkbox, radio and switch markup: the wrapper becomes
 *            .form-field, the input .check or .radio by its type, a switch's
 *            input goes into a .switch box, the label loses its class
 *   dialogs  modal markup to <dialog class="dialog"> (bs6_switch_shell.php)
 *   drawers  offcanvas markup to <dialog class="drawer">
 *   menus    dropdown menus to .menu, alignment to the toggle's placement
 *   accordions  accordion items to <details>/<summary>
 *   stems    classes built in code from a v5 stem, where the values are
 *            known (bs6_switch_stems.php)
 *   names    the renamed components' class names, data attributes and
 *            open-state selectors in code
 *   variables  component variables in panel stylesheets (--bs-dropdown-*
 *            to --bs-menu-*, offcanvas to drawer, modal to dialog)
 *   tokens   tokens and rules that read v5 variables (bs6_switch_tokens.php)
 *   bridge   v6's theme, neutral and radius variables from the panel's
 *            palette (bs6_switch_tokens.php)
 *   shell    the v5 look the panel keeps: flush drawers, the menu caret, the
 *            menu wrapper's positioning (bs6_switch_shell_rules())
 *   own      v5 link colour helpers and print utilities to the panel's own
 *            pg-link-* and pg-print-* classes
 *   breakpoints  own media queries at v5's lg/xl/xxl widths (992, 1200,
 *            1400) move to v6's (1024, 1280, 1536)
 *   fields   the v6 class on liveform fields that name none
 *            (bs6_switch_fields.php)
 *   texts    hand fixes found by their exact text (bs6_switch_texts())
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

require_once(__DIR__ . '/bs6_rules.php');
require_once(__DIR__ . '/bs6_switch_shell.php');
require_once(__DIR__ . '/bs6_switch_tokens.php');
require_once(__DIR__ . '/bs6_switch_stems.php');
require_once(__DIR__ . '/bs6_switch_fields.php');

// Bootstrap 6's own layer order with `vendor` before `custom`. jQuery UI,
// DataTables, select2 and the icon fonts go into `vendor`: an unlayered sheet
// would beat every layer, and the panel's overrides of them sit in `custom`.
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

	foreach (bs6_switch_stylesheets($root) as $path) {
		$plan[] = array($path, 'selectors', 'bs6_switch_selectors');
		$plan[] = array($path, 'variables', 'bs6_switch_component_variables');
	}

	foreach (bs6_switch_token_files($root) as $path) {
		$plan[] = array($path, 'tokens', 'bs6_switch_tokens');
	}

	foreach (bs6_switch_rgb_files($root) as $path) {
		$plan[] = array($path, 'rgb', 'bs6_switch_rgb_variables');
	}

	$plan[] = array('assets/css/backend.src.css', 'bridge', 'bs6_switch_token_bridge');
	$plan[] = array('assets/css/backend.src.css', 'shell', 'bs6_switch_shell_rules');

	foreach (bs6_switch_own_class_files($root) as $path) {
		$plan[] = array($path, 'own', 'bs6_switch_own_classes');
	}

	foreach (bs6_switch_breakpoint_files($root) as $path) {
		$plan[] = array($path, 'breakpoints', 'bs6_switch_breakpoints');
	}

	foreach (bs6_switch_style_files() as $path) {
		$plan[] = array($path, 'style', 'bs6_switch_style_blocks');
	}

	$plan[] = array('assets/js/backend.src.js', 'stacked', 'bs6_switch_drop_stacked_repair');

	foreach (bs6_switch_check_files($root) as $path) {
		$plan[] = array($path, 'checks', 'bs6_switch_checks');
	}

	foreach (bs6_switch_script_check_files($root) as $path) {
		$plan[] = array($path, 'checks', 'bs6_switch_script_checks');
	}

	foreach (bs6_switch_field_files($root) as $path) {
		$plan[] = array($path, 'fields', 'bs6_switch_fields');
	}

	foreach (bs6_switch_shell_files($root) as $path) {
		$plan[] = array($path, 'dialogs', 'bs6_switch_dialogs');
		$plan[] = array($path, 'drawers', 'bs6_switch_drawers');
		$plan[] = array($path, 'menus', 'bs6_switch_menus');
		$plan[] = array($path, 'names', 'bs6_switch_component_names');
		$plan[] = array($path, 'accordions', 'bs6_switch_accordions');
	}

	foreach (bs6_switch_stem_files($root) as $path) {
		$plan[] = array($path, 'stems', 'bs6_switch_stems');
	}

	$plan[] = array('assets/js/style_designer.src.js', 'dialogs', 'bs6_switch_editor_dialogs');

	foreach (bs6_switch_drawer_files($root) as $path) {
		$plan[] = array($path, 'drawer-vars', 'bs6_switch_drawer_variables');
	}

	foreach (bs6_switch_texts() as $path => $pairs) {
		$plan[] = array($path, 'texts', function ($source) use ($pairs) {
			return bs6_switch_pins($source, $pairs);
		});
	}

	foreach (bs6_switch_test_pins() as $path => $pairs) {
		$plan[] = array('../' . $path, 'pins', function ($source) use ($pairs) {
			return bs6_switch_pins($source, $pairs);
		});
	}

	return $plan;
}

// Files the codemod converts that name a v5 check class, sorted.
function bs6_switch_check_files($root)
{
	$paths = array();

	foreach (bs6_scope_files(rtrim($root, '/')) as $path => $kind) {
		if (bs6_codemod_converts($path) && strpos((string) file_get_contents(rtrim($root, '/') . '/' . $path), 'form-check') !== false) {
			$paths[] = $path;
		}
	}

	sort($paths);

	return $paths;
}

// Scripts the codemod converts that build check markup with an element
// helper (bs6_switch_script_checks()), sorted; the editor's script is left
// out.
function bs6_switch_script_check_files($root)
{
	$paths = array();

	foreach (bs6_scope_files(rtrim($root, '/')) as $path => $kind) {
		if (bs6_codemod_converts($path) && $path !== 'assets/js/style_designer.src.js' && preg_match('/\.js$/', $path)
			&& strpos((string) file_get_contents(rtrim($root, '/') . '/' . $path), "'form-check") !== false) {
			$paths[] = $path;
		}
	}

	sort($paths);

	return $paths;
}

// Scripts and pages the codemod converts that name a modal, offcanvas or
// dropdown, sorted. The visual editor's script is left out: its chrome and
// its canvas code (Bootstrap 5 inside the page) share members, so its
// dialogs and menus are converted by their text (bs6_switch_texts()).
function bs6_switch_shell_files($root)
{
	$paths = array();

	foreach (bs6_scope_files(rtrim($root, '/')) as $path => $kind) {
		if (!bs6_codemod_converts($path) || $path === 'assets/js/style_designer.src.js' || !preg_match('/\.(php|js)$/', $path)) {
			continue;
		}

		if (preg_match('/modal|offcanvas|dropdown|dropup|dropend|dropstart|accordion/', (string) file_get_contents(rtrim($root, '/') . '/' . $path))) {
			$paths[] = $path;
		}
	}

	sort($paths);

	return $paths;
}

// Files the codemod converts and panel stylesheets that name a v5 class the
// panel keeps as its own (bs6_shell_own_class_renames()), sorted; the
// editor's script is left out (its canvas code names the site's Bootstrap 5
// classes).
function bs6_switch_own_class_files($root)
{
	$paths = array();
	$sheets = bs6_switch_stylesheets($root);

	foreach (bs6_scope_files(rtrim($root, '/')) as $path => $kind) {
		if ((!in_array($path, $sheets, true) && !bs6_codemod_converts($path)) || $path === 'assets/js/style_designer.src.js' || !preg_match('/\.(php|js|css)$/', $path)) {
			continue;
		}

		$source = (string) file_get_contents(rtrim($root, '/') . '/' . $path);

		foreach (array_keys(bs6_shell_own_class_renames()) as $pattern) {
			if (preg_match($pattern, $source)) {
				$paths[] = $path;
				break;
			}
		}
	}

	sort($paths);

	return $paths;
}

// Panel stylesheets and the pages the codemod converts that hold a media
// query (their inline <style> blocks), sorted. Scripts are left out: the
// editor writes Bootstrap 5's breakpoints into the canvas on purpose.
function bs6_switch_breakpoint_files($root)
{
	$paths = bs6_switch_stylesheets($root);

	foreach (bs6_scope_files(rtrim($root, '/')) as $path => $kind) {
		if (bs6_codemod_converts($path) && preg_match('/\.php$/', $path) && strpos((string) file_get_contents(rtrim($root, '/') . '/' . $path), '@media') !== false) {
			$paths[] = $path;
		}
	}

	sort($paths);

	return array_values(array_unique($paths));
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
	$vendor = '#<link rel="stylesheet" type="text/css" href="(\' \. OUTPUT_PATH \. OUTPUT_SOFTWARE_DIRECTORY \. \'/assets/(?:lib/(?:Jquery/ui|DataTables|select2)|fonts/(?:bootstrap-icons|material-icons))/[^"]*)"\s*/?>#';
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

		bs6_current_path($path);
		$result = $function($sources[$path]['new']);
		bs6_current_path(false);
		$sources[$path]['new'] = $result['source'];
		$report[] = array($path, $fix, $result['status'], !empty($result['left']) ? $result['left'] . ' left for a hand' : null);
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

// End offset (after '>') of the tag that starts at $start, or null. A '>'
// right after '-', '=' or '?' belongs to PHP inside the tag (an arrow or
// the end of a PHP block), not to the tag; so does one inside brackets
// (`($ttl > 0)`), which attribute values only hold balanced.
function bs6_switch_tag_end($source, $start)
{
	$length = strlen($source);
	$depth = 0;

	for ($i = $start + 1; $i < $length; $i++) {
		$char = $source[$i];

		if ($char === '(') {
			$depth++;
		} elseif ($char === ')') {
			$depth = max(0, $depth - 1);
		} elseif ($char === '<' && $depth === 0 && substr($source, $i, 2) !== '<?') {
			return null;
		} elseif ($char === '>' && $depth === 0 && strpos('-=?', $source[$i - 1]) === false) {
			return $i + 1;
		}
	}

	return null;
}

/**
 * Checkbox, radio and switch markup in class="…" attributes, by tag:
 *   form-check            .form-field
 *   form-check-inline     d-inline-grid and the v5 1rem right margin
 *   form-switch           dropped; the next <input> with form-check-input
 *                         (before another check wrapper, within 800 bytes)
 *                         goes into <div class="switch"> without a class
 *   form-check-input      .check or .radio by the tag's literal type
 *   form-check-label      dropped (v6 styles `.form-field > label`)
 * Site-output code (`/* @pg-site-output *\/`) and the class keys of PHP
 * arrays or JS objects are left for a hand: the tag is not in sight there.
 */
function bs6_switch_checks($source)
{
	$names = array('form-check', 'form-check-inline', 'form-switch', 'form-check-input', 'form-check-label');
	$site_output = bs6_site_output_ranges($source);
	$inline = bs6_map_token('me-3')['tokens'];
	$edits = array();
	$switch_at = null;

	foreach (bs6_find_class_lists($source) as $list) {
		if (!in_array($list['kind'], array('attribute', 'php-key'), true) || ($site_output && bs6_in_ranges($list['offset'], $site_output))) {
			continue;
		}

		$parts = preg_split('/(\s+)/', $list['value'], -1, PREG_SPLIT_DELIM_CAPTURE);

		if (!array_intersect($names, $parts)) {
			continue;
		}

		$wrap = false;

		if ($list['kind'] === 'php-key') {
			// A liveform field: the input is the output_field() call, its type
			// the call's 'type' key, and a switch box goes around the call.
			$field = bs6_switch_field_call($source, $list['offset']);

			if ($field === null) {
				continue;
			}

			$tag = 'input';
			$tag_start = $field['before'];
			$tag_end = $field['after'];
			$tag_text = $field['text'];
		} else {
			$tag_start = strrpos(substr($source, 0, $list['offset']), '<');
			$tag = ($tag_start !== false && preg_match('/^<([a-zA-Z][a-zA-Z0-9]*)/', substr($source, $tag_start, 20), $m)) ? strtolower($m[1]) : '';
			$tag_end = $tag !== '' ? bs6_switch_tag_end($source, $tag_start) : null;
			$tag_text = $tag_end !== null ? substr($source, $tag_start, $tag_end - $tag_start) : '';
		}

		foreach ($parts as $i => $part) {
			if (($i % 2) === 1) {
				continue;
			}

			if ($part === 'form-check') {
				$parts[$i] = 'form-field';
				$switch_at = null;
			} elseif ($part === 'form-check-inline') {
				$parts[$i] = 'd-inline-grid ' . implode(' ', $inline);
			} elseif ($part === 'form-switch') {
				$parts[$i] = '';
				$switch_at = $list['offset'];
			} elseif ($part === 'form-check-label') {
				$parts[$i] = '';
			} elseif ($part === 'form-check-input' && $tag === 'input' && $tag_end !== null) {
				if ($switch_at !== null && $list['offset'] - $switch_at < 800 && ($list['kind'] === 'attribute' || $field['wrappable'])) {
					$parts[$i] = '';
					$wrap = true;
					$switch_at = null;
				} elseif (preg_match('/\btype=\\\\?["\'](checkbox|radio)\\\\?["\']|[\'"]type[\'"]\s*=>\s*[\'"](checkbox|radio)[\'"]/', $tag_text, $type)) {
					$type[1] = $type[1] !== '' ? $type[1] : $type[2];
					$parts[$i] = $type[1] === 'checkbox' ? 'check' : 'radio';
				}
			}
		}

		// The `form-check` wrapper of a switch may come after `form-switch`
		// in the same list; the switch stays pending then.
		if (in_array('form-switch', explode(' ', preg_replace('/\s+/', ' ', $list['value'])), true)) {
			$switch_at = $list['offset'];
		}

		$value = trim(bs6_join_class_parts($list['value'], $parts));
		$edits[] = array($list['offset'], $list['length'], $value);

		if ($wrap) {
			$edits[] = array($tag_start, 0, '<div class="switch">');
			$edits[] = array($tag_end, 0, '</div>');
		}
	}

	if (!$edits) {
		return bs6_switch_result($source, 'done');
	}

	// From the end, so earlier offsets stay valid; an insertion at a tag's
	// start sorts after the class list inside that tag.
	usort($edits, function ($a, $b) {
		return $b[0] - $a[0];
	});

	foreach ($edits as $edit) {
		$source = substr($source, 0, $edit[0]) . $edit[2] . substr($source, $edit[0] + $edit[1]);
	}

	return bs6_switch_result($source, 'applied');
}

// The $x->output_field(...) call around a PHP class key at $offset, as
// array('text' => the call, 'before', 'after', 'wrappable'): the call sits
// between two string pieces (`…">' . $liveform->output_field(…) . '<…`),
// 'before' is the closing quote of the piece before it and 'after' the
// offset past the opening quote of the piece after it, where a box can open
// and close. Null when the key is not inside such a call.
function bs6_switch_field_call($source, $offset)
{
	$head = substr($source, max(0, $offset - 600), min($offset, 600));
	$at = strrpos($head, '->output_field(');

	if ($at === false) {
		return null;
	}

	$start = max(0, $offset - 600) + $at;
	$open = $start + strlen('->output_field');
	$length = strlen($source);
	$depth = 0;
	$quote = null;

	for ($i = $open; $i < $length; $i++) {
		$char = $source[$i];

		if ($quote !== null) {
			if ($char === '\\') {
				$i++;
			} elseif ($char === $quote) {
				$quote = null;
			}
			continue;
		}

		if ($char === '\'' || $char === '"') {
			$quote = $char;
		} elseif ($char === '(') {
			$depth++;
		} elseif ($char === ')' && --$depth === 0) {
			break;
		}
	}

	if ($i >= $length || $i < $offset) {
		return null;
	}

	$end = $i + 1;
	$before = null;
	$after = null;
	$prefix = substr($source, 0, $start);

	if (preg_match('/\'\s*\.\s*\$[A-Za-z_][A-Za-z0-9_]*$/', $prefix, $m)) {
		$before = $start - strlen($m[0]);
	}

	if (preg_match('/^\s*\.\s*\'/', substr($source, $end, 40), $m)) {
		$after = $end + strlen($m[0]);
	}

	return array(
		'text' => substr($source, $start, $end - $start),
		'before' => $before !== null ? $before : $start,
		'after' => $after !== null ? $after : $end,
		'wrappable' => $before !== null && $after !== null,
	);
}

// Selector text for one v5 class in a panel stylesheet, or null to leave it.
// Sizes (spacing, font size, radius) are never rewritten here: their v6
// names are v5 names of other sizes, so a second run would change them again.
function bs6_switch_selector_for($class)
{
	$checks = array(
		// Plain: panel markup gets .form-field only from a converted check, and
		// a :has() here would sit inside the :has() of a rule that names the
		// wrapper, which drops the whole rule.
		'form-check' => '.form-field',
		'form-check-input' => ':is(.check, .radio)',
		'form-check-label' => ':is(.form-field > label)',
		'form-check-inline' => '.form-field.d-inline-grid',
	);

	if (isset($checks[$class])) {
		return $checks[$class];
	}

	$components = array(
		'modal' => '.dialog',
		'offcanvas' => '.drawer',
		'dropdown' => '.pg-menu-wrap',
		'dropup' => '.pg-menu-wrap',
		'dropend' => '.pg-menu-wrap',
		'dropstart' => '.pg-menu-wrap',
		'dropdown-menu' => '.menu',
		'dropdown-menu-end' => '.pg-menu-end',
		'dropdown-menu-start' => '.pg-menu-start',
		'dropdown-item' => '.menu-item',
		'dropdown-item-text' => '.menu-text',
		'dropdown-item-sm' => '.pg-menu-item-sm',
		'dropdown-divider' => '.menu-divider',
		'dropdown-header' => '.menu-header',
		'dropdown-toggle' => '.pg-menu-caret',
		'form-select' => ':is(select.form-control)',
		'form-select-sm' => ':is(select.form-control-sm)',
		'form-select-lg' => ':is(select.form-control-lg)',
	);

	if (isset($components[$class])) {
		return $components[$class];
	}

	if (preg_match('/^modal-(header|body|footer|title)$/', $class, $m)) {
		return '.dialog-' . $m[1];
	}

	if (preg_match('/^offcanvas-(start|end|top|bottom|header|body|footer|title)$/', $class, $m)) {
		return '.drawer-' . $m[1];
	}

	if (preg_match('/^(([mp][trblxyse]?|g[xy]?|gap|row-gap|column-gap)-|fs-|rounded)/', $class)) {
		return null;
	}

	$mapped = bs6_map_token($class);

	if ($mapped['review'] !== null || $mapped['tokens'] === array($class) || !$mapped['tokens']) {
		return null;
	}

	$out = '';
	foreach ($mapped['tokens'] as $token) {
		$out .= '.' . str_replace(':', '\:', $token);
	}

	return $out;
}

function bs6_switch_selectors($source)
{
	$count = 0;

	$source = preg_replace_callback('/\.([A-Za-z][A-Za-z0-9_-]*)(?![A-Za-z0-9_\\\\-])(?=[^{};]*\{)/', function ($m) use (&$count) {
		$selector = bs6_switch_selector_for($m[1]);

		if ($selector === null) {
			return $m[0];
		}

		$count++;
		return $selector;
	}, $source);

	return bs6_switch_result($source, $count > 0 ? 'applied' : 'done');
}

// Unit tests that check panel output or panel source for a v5 class, with
// the text each must change from and to. The tests run against development
// until the switch, so they change with the generated commit.
function bs6_switch_test_pins()
{
	return array(
		'tests/ai_fields_test.php' => array(
			array('bg-primary-subtle text-primary-emphasis border border-primary-subtle', 'bg-subtle-primary fg-emphasis-primary border border-subtle-primary'),
			array('column-gap-2', 'column-gap-3'),
			array('lh-1 text-reset pg-ai-mark-remove', 'lh-1 fg-reset pg-ai-mark-remove'),
		),
		'tests/ai_write_test.php' => array(
			array('column-gap-2', 'column-gap-3'),
		),
		'tests/pg_ui_test.php' => array(
			array("'root' => 'DIALOG.dialog dialog-xl dialog-scrollable',", "'root' => 'DIALOG.dialog dialog-xl pg-dialog-centered dialog-scrollable',"),
		),
		'tests/list_edit_lists_test.php' => array(
			array("'status_enabled text-success'", "'status_enabled fg-success'"),
			array("'status_disabled text-danger'", "'status_disabled fg-danger'"),
		),
		'tests/catalog_bin_test.php' => array(
			array('btn my-1 btn-secondary', 'btn my-1 btn-solid theme-secondary'),
		),
		'tests/erp_edoc_service_test.php' => array(
			array('<div class=\"col-12 my-2\">', '<div class=\"col-12 my-3\">'),
		),
		'tests/erp_auto_invoice_test.php' => array(
			array("pg_assert_contains('text-warning-emphasis', \$html);", "pg_assert_contains('fg-emphasis-warning', \$html);"),
		),
		'tests/parasut_order_gates_test.php' => array(
			array("'accepted' => 'bg-success',", "'accepted' => 'theme-success',"),
			array("'sent' => 'bg-primary',", "'sent' => 'theme-primary',"),
			array("'created' => 'bg-warning text-dark',", "'created' => 'theme-warning',"),
			array("'error' => 'bg-danger',", "'error' => 'theme-danger',"),
			array("'rejected' => 'bg-danger',", "'rejected' => 'theme-danger',"),
			array("'cancelled' => 'bg-secondary',", "'cancelled' => 'theme-secondary',"),
			array("'' => 'bg-secondary',", "'' => 'theme-secondary',"),
			array("'<span class=\"badge bg-warning text-dark\">'", "'<span class=\"badge theme-warning\">'"),
			array("pg_assert_contains('bg-success\">' . h(lang('Invoiced through the ERP'))", "pg_assert_contains('theme-success\">' . h(lang('Invoiced through the ERP'))"),
			array("pg_assert_contains('bg-success\">' . lang('Sent')", "pg_assert_contains('theme-success\">' . lang('Sent')"),
			array("pg_assert_contains('bg-warning', \$open)", "pg_assert_contains('theme-warning', \$open)"),
		),
		'tests/parasut_order_invoice_test.php' => array(
			array("pg_assert_contains('bg-warning', \$unsure)", "pg_assert_contains('theme-warning', \$unsure)"),
		),
		'tests/payment_drivers_test.php' => array(
			array("pg_assert_contains('alert-warning', \$unread", "pg_assert_contains('alert theme-warning', \$unread"),
		),
		'tests/shipping_test.php' => array(
			array("pg_assert_same('text-bg-success', pg_shipment_status_class(", "pg_assert_same('theme-success', pg_shipment_status_class("),
			array("pg_assert_same('text-bg-primary', pg_shipment_status_class(", "pg_assert_same('theme-primary', pg_shipment_status_class("),
			array("pg_assert_same('text-bg-warning', pg_shipment_status_class(", "pg_assert_same('theme-warning', pg_shipment_status_class("),
			array("pg_assert_same('text-bg-danger', pg_shipment_status_class(", "pg_assert_same('theme-danger', pg_shipment_status_class("),
			array("pg_assert_same('text-bg-secondary', pg_shipment_status_class(", "pg_assert_same('theme-secondary', pg_shipment_status_class("),
		),
		'tests/signin_test.php' => array(
			array("'bg-warning'", "'theme-warning'"),
			array("'class=\"btn btn-primary\" href=\"/giris?a=1&amp;b=2\"'", "'class=\"btn btn-solid theme-primary\" href=\"/giris?a=1&amp;b=2\"'"),
			array("'class=\"btn btn-outline-secondary\" href=\"/pinegrap/signin.php?provider=google\"'", "'class=\"btn btn-outline theme-secondary\" href=\"/pinegrap/signin.php?provider=google\"'"),
		),
		'tests/code_editor_test.php' => array(
			array("if (!dlg || !dlg.classList.contains('modal-fullscreen')) return false;", "if (!_modalEl || !_modalEl.classList.contains('dialog-fullscreen')) return false;"),
		),
		'tests/output_test.php' => array(
			array("'dropdown-item link-body-emphasis active'", "'menu-item pg-link-body-emphasis active'"),
		),
		'tests/workspace_sales_quote_test.php' => array(
			array("'\\\\.dropdown-divider'", "'\\\\.menu-divider'"),
		),
		'tests/order_quick_edit_test.php' => array(
			array("pg_assert_contains('bg-warning', pg_order_list_status_html(", "pg_assert_contains('theme-warning', pg_order_list_status_html("),
			array("pg_assert_contains('bg-primary', pg_order_list_status_html(", "pg_assert_contains('theme-primary', pg_order_list_status_html("),
			array("pg_assert_contains('bg-success', pg_order_list_status_html(", "pg_assert_contains('theme-success', pg_order_list_status_html("),
			array("pg_assert_contains('bg-danger', pg_order_list_status_html(", "pg_assert_contains('theme-danger', pg_order_list_status_html("),
			array("pg_assert_contains('bg-warning', \$unsure);", "pg_assert_contains('theme-warning', \$unsure);"),
		),
		'tests/waf_offence_test.php' => array(
			array("\"'would-ban'   => array('bg-warning text-dark', lang('Would ban')),\"", "\"'would-ban'   => array('bg-warning fg-black', lang('Would ban')),\""),
		),
		'tests/widget_fill_test.php' => array(
			array('<select class="form-select form-select-sm sd-sw-bindfilter"', '<select class="form-control form-control-sm sd-sw-bindfilter"'),
		),
	);
}

// Replaces every occurrence of each pair's first text with its second.
// Missing when a first text is gone and its second is not there: the test
// changed on development and its pair needs updating. A pair whose second
// text holds its first (code inserted before a line) is done once the second
// text is there, so a second run does not insert it again.
function bs6_switch_pins($source, array $pairs)
{
	$applied = false;

	foreach ($pairs as $pair) {
		// A pair with an empty "to" deletes its text: once gone it is done
		// (strpos() refuses an empty needle on PHP 7).
		$deletes = ($pair[1] === '');

		if (!$deletes && strpos($pair[1], $pair[0]) !== false && strpos($source, $pair[1]) !== false) {
			continue;
		}

		if (strpos($source, $pair[0]) !== false) {
			$source = str_replace($pair[0], $pair[1], $source);
			$applied = true;
		} elseif (!$deletes && strpos($source, $pair[1]) === false) {
			return bs6_switch_result($source, 'missing');
		}
	}

	return bs6_switch_result($source, $applied ? 'applied' : 'done');
}

// Rules giving v5's alert-<colour> classes the variables of v6's
// theme-<colour> (light and dark take secondary and inverse). The class is
// matched as an attribute word so that check_bs6.php, which lists selectors
// on classes v6 renames, does not count these on purpose kept v5 names.
function bs6_switch_v5_alert_themes()
{
	$rules = '';
	$themes = array('primary' => 'primary', 'secondary' => 'secondary', 'success' => 'success', 'danger' => 'danger',
		'warning' => 'warning', 'info' => 'info', 'light' => 'secondary', 'dark' => 'inverse');

	foreach ($themes as $v5 => $v6) {
		$rules .= ".alert[class~=\"alert-" . $v5 . "\"] {\n";
		foreach (array('base', 'fg', 'fg-emphasis', 'bg', 'bg-subtle', 'bg-muted', 'border', 'focus-ring', 'contrast') as $part) {
			$rules .= "  --bs-theme-" . $part . ": var(--bs-" . $v6 . "-" . $part . ");\n";
		}
		$rules .= "}\n";
	}

	return $rules;
}

// The comment the shell rules start with; its presence marks them done.
const BS6_SWITCH_SHELL_NOTE = '/* Bootstrap 6 shell: what the panel keeps of Bootstrap 5\'s look. */';

// Rules at the top of backend.src.css's custom layer that keep the v5 look
// of the converted components: v5's badge metrics,
// the caret v6 no
// longer draws on a menu toggle, the wrapper a menu used to position
// against, the scrim of a dialog printed open (bs6_shell_open_sheet()), and
// v5's link-body-emphasis and print display utilities as the panel's
// pg-link-body-emphasis and pg-print-*, and the other v5 utilities the
// codemod maps to pg-* classes.
function bs6_switch_shell_rules($source)
{
	if (strpos($source, BS6_SWITCH_SHELL_NOTE) !== false) {
		return bs6_switch_result($source, 'done');
	}

	$open = BS6_SWITCH_LAYER_NOTE . "\n@layer custom {\n\n";

	if (strpos($source, $open) === false) {
		return bs6_switch_result($source, 'missing');
	}

	$rules = BS6_SWITCH_SHELL_NOTE . "\n"
		. ".pg-menu-wrap {\n  position: relative;\n}\n"
		. "/* v5's navbar has no minimum height; a button with no variant is clear. */\n"
		. ".navbar {\n  --bs-navbar-min-height: 0;\n  --bs-navbar-nav-link-padding-x: 0.5rem;\n}\n"
		. ":where(.btn:not(.btn-solid, .btn-outline, .btn-subtle, .btn-text, .btn-link)) {\n  --bs-btn-bg: transparent;\n  --bs-btn-hover-bg: transparent;\n  --bs-btn-active-bg: transparent;\n  --bs-btn-disabled-bg: transparent;\n}\n"
		. "/* v5's card: block body and header, v5 paddings and cap tint, children\n"
		. "   keep their own margins (v6 zeroes them and spaces them with a gap). */\n"
		. ".card {\n  --bs-card-spacer-y: 1rem;\n  --bs-card-spacer-x: 1rem;\n  --bs-card-cap-padding-y: 0.5rem;\n  --bs-card-cap-padding-x: 1rem;\n"
		. "  --bs-card-cap-bg: color-mix(in srgb, var(--pg-fg-body) 3%, transparent);\n  --bs-card-border-color: var(--pg-border-color-translucent);\n}\n"
		. ".card-body,\n.card-header {\n  display: block;\n  flex-direction: row;\n}\n"
		. ":where(.card-body) > :where(p, ul, ol, dl, pre, blockquote, figure) {\n  margin-top: 0;\n  margin-bottom: 1rem;\n}\n"
		. ":where(.card-body) > :where(h1, h2, h3, h4, h5, h6, .h1, .h2, .h3, .h4, .h5, .h6, .card-title) {\n  margin-top: 0;\n  margin-bottom: 0.5rem;\n}\n"
		. ":where(.card-body) > :where(hr) {\n  margin: 1rem 0;\n}\n"
		. ":where(.card-body) > :where(.card-text:last-child) {\n  margin-bottom: 0;\n}\n"
		. "/* v6 zeroes the block margins of a card body's children; a row keeps its\n   negative gutter, a table and a form hint their v5 margins. */\n"
		. ".card-body > .row {\n  margin-top: calc(-1 * var(--bs-gutter-y));\n}\n"
		. ".card-body > .table {\n  margin-bottom: 1rem;\n}\n"
		. ".card-body > .form-text {\n  margin-top: 0.25rem;\n}\n"
		. "/* v5's accordion colours, radius and chevron size. */\n"
		. ".accordion {\n  --bs-accordion-btn-color: var(--pg-fg-body);\n  --bs-accordion-border-radius: var(--pg-radius);\n  --bs-accordion-btn-icon-width: 1.25rem;\n}\n"
		. "/* v5's breadcrumb draws its divider before every item but the first;\n   v6 wants a .breadcrumb-divider element between them. */\n"
		. ".breadcrumb-item + .breadcrumb-item {\n  padding-left: var(--bs-breadcrumb-item-padding-x, 0.5rem);\n}\n"
		. ".breadcrumb-item + .breadcrumb-item::before {\n  padding-right: var(--bs-breadcrumb-item-padding-x, 0.5rem);\n  color: var(--pg-fg-2);\n  content: var(--bs-breadcrumb-divider, \"/\");\n}\n"
		. ".breadcrumb-item.active {\n  color: var(--pg-fg-2);\n}\n"
		. "/* v5's nav: no gap between items, v5 link padding. */\n"
		. ".nav,\n.navbar-nav {\n  --bs-nav-gap: 0;\n  --bs-nav-link-gap: 0;\n}\n"
		. ".navbar-nav {\n  --bs-nav-link-border-width: 0;\n}\n"
		. ".navbar-nav .nav-link {\n  padding-top: 0.5rem;\n  padding-bottom: 0.5rem;\n}\n"
		. ".nav {\n  --bs-nav-link-padding-x: 1rem;\n  --bs-nav-link-padding-y: 0.5rem;\n}\n"
		. "/* v5's nav links: start-aligned (v6 centres them; filled and justified\n   navs centre as in v5) and borderless (tabs draw their own); v5's pills:\n   no bar around them, the links with v5's radius. */\n"
		. ".nav {\n  --bs-nav-link-justify: flex-start;\n  --bs-nav-link-border-width: 0;\n}\n"
		. ".nav-fill,\n.nav-justified {\n  --bs-nav-link-justify: center;\n}\n"
		. ".nav-pills {\n  --bs-nav-pills-bg: transparent;\n  --bs-nav-pills-padding: 0;\n  --bs-nav-pills-border-radius: 0;\n  --bs-nav-pills-link-border-radius: var(--pg-radius);\n  display: flex;\n  flex-wrap: wrap;\n}\n"
		. "/* v5's check, radio and switch sizes (1em, the switch twice as wide). */\n"
		. ".check {\n  --bs-check-size: 1em;\n  --bs-check-border-radius: 0.25em;\n}\n"
		. ".radio {\n  --bs-radio-size: 1em;\n}\n"
		. ".switch {\n  --bs-switch-height: 1em;\n  --bs-switch-width: 2em;\n}\n"
		. "/* v5's input.btn-check before its label: hidden, the label is the control\n   (v6 wraps the input in a label.btn-check). */\n"
		. "input.btn-check {\n  position: absolute;\n  clip: rect(0, 0, 0, 0);\n  pointer-events: none;\n}\n"
		. "/* v6 styles only a label.btn-check that wraps its input; the v5 sibling\n   label takes the checked, focus and disabled looks from its input, and the\n   first label of a group keeps its start corners. */\n"
		. "input.btn-check:checked + .btn {\n  color: var(--bs-btn-active-color);\n  background-color: var(--bs-btn-active-bg, var(--bs-bg-3));\n  background-image: none;\n  border-color: var(--bs-btn-active-border-color);\n  box-shadow: var(--bs-btn-active-shadow);\n}\n"
		. "input.btn-check:focus-visible + .btn {\n  outline: 2px solid var(--pg-primary-ink);\n  outline-offset: 2px;\n  box-shadow: 0 0 0 var(--pg-focus-ring-width) var(--pg-focus-ring-color);\n}\n"
		. "input.btn-check:disabled + .btn {\n  color: var(--bs-btn-disabled-color);\n  pointer-events: none;\n  background-color: var(--bs-btn-disabled-bg, var(--bs-bg-1));\n  background-image: none;\n  border-color: var(--bs-btn-disabled-border-color);\n  opacity: var(--bs-btn-disabled-opacity, 0.65);\n  box-shadow: none;\n}\n"
		. ".btn-group > input.btn-check:checked + .btn {\n  z-index: 2;\n}\n"
		. ".btn-group > input.btn-check:focus-visible + .btn {\n  z-index: 3;\n}\n"
		. ".btn-group > input.btn-check:first-child + .btn {\n  margin-inline-start: 0;\n  border-start-start-radius: var(--bs-btn-border-radius);\n  border-end-start-radius: var(--bs-btn-border-radius);\n}\n"
		. "/* v5's headings: its sizes (min() is v5's responsive size, fixed from\n   1200 px), weight and line height. */\n"
		. ":where(h1, h2, h3, h4, h5, h6, .h1, .h2, .h3, .h4, .h5, .h6) {\n  font-weight: 500;\n  line-height: 1.2;\n}\n"
		. ":where(h1, .h1):not(.menu-header, .popover-header) {\n  font-size: min(calc(1.375rem + 1.5vw), 2.5rem);\n}\n"
		. ":where(h2, .h2):not(.menu-header, .popover-header) {\n  font-size: min(calc(1.325rem + 0.9vw), 2rem);\n}\n"
		. ":where(h3, .h3):not(.menu-header, .popover-header) {\n  font-size: min(calc(1.3rem + 0.6vw), 1.75rem);\n}\n"
		. ":where(h4, .h4):not(.menu-header, .popover-header) {\n  font-size: min(calc(1.275rem + 0.3vw), 1.5rem);\n}\n"
		. ":where(h5, .h5):not(.menu-header, .popover-header) {\n  font-size: 1.25rem;\n}\n"
		. ":where(h6, .h6):not(.menu-header, .popover-header) {\n  font-size: 1rem;\n}\n"
		. "/* v5's dropdown menu: 1rem text, full-width items with no gap or radius,\n   v5 paddings (a heading in a menu keeps the header's own size). */\n"
		. ".menu {\n  --bs-menu-font-size: 1rem;\n  --bs-menu-padding-x: 0;\n  --bs-menu-padding-y: 0.5rem;\n  --bs-menu-gap: 0;\n  --bs-menu-item-gap: 0;\n"
		. "  --bs-menu-item-padding-x: 1rem;\n  --bs-menu-item-border-radius: 0;\n  --bs-menu-header-padding-x: 1rem;\n  --bs-menu-header-padding-y: 0.5rem;\n"
		. "  --bs-menu-divider-margin-x: 0;\n  --bs-menu-divider-margin-y: 0.5rem;\n}\n"
		. "/* v5's close button: its thin cross, 1em with a 0.25em padding. */\n"
		. ".btn-close {\n  --bs-btn-close-size: 1em;\n  --bs-btn-close-icon: url(\"data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23000'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414'/%3e%3c/svg%3e\");\n"
		. "  padding: 0.25em;\n  border-radius: var(--pg-radius);\n  mask-origin: content-box;\n  mask-size: 1em auto;\n}\n"
		. "/* v5's dialog and drawer titles: line height 1.5. */\n"
		. ".dialog-title,\n.drawer-title {\n  line-height: 1.5;\n}\n"
		. "/* v5's dialog sizes on narrow screens: a large or extra large dialog\n   falls back to the default width under its breakpoint, and on a phone\n   every dialog that is not full screen spans it with a 0.5rem margin. */\n"
		. "@media (max-width: 1279.98px) {\n  .dialog-xl {\n    --bs-dialog-width: 800px;\n  }\n}\n"
		. "@media (max-width: 1023.98px) {\n  .dialog-lg,\n  .dialog-xl {\n    --bs-dialog-width: 500px;\n  }\n}\n"
		. "@media (max-width: 575.98px) {\n  .dialog:not([class*=\"dialog-fullscreen\"]) {\n    --bs-dialog-width: 100%;\n    --bs-dialog-margin: 0.5rem;\n  }\n}\n"
		. "/* v5's alert: a block with its radius and bottom margin (v6 lays it out\n   as a flex row with a gap); pg-alert-dismissible is v5's alert-dismissible. */\n"
		. ".alert {\n  --bs-alert-border-radius: var(--pg-radius);\n  position: relative;\n  display: block;\n  margin-bottom: 1rem;\n}\n"
		. ".pg-alert-dismissible {\n  padding-right: 3rem;\n}\n"
		. "/* Alerts that renderers shared with the site print with v5's colour\n   classes (liveform's notices and errors) take v6's theme. */\n"
		. bs6_switch_v5_alert_themes()
		. ".pg-alert-dismissible > .btn-close {\n  position: absolute;\n  top: 0;\n  right: 0;\n  z-index: 2;\n  padding: 1.25rem 1rem;\n}\n"
		. "/* An icon font class on an input group's add-on keeps the add-on's size,\n   as the more specific v5 rule did. */\n"
		. ".input-group > .input-group-text.material-icons {\n  font-size: var(--bs-input-group-addon-font-size);\n  line-height: var(--bs-input-group-addon-line-height);\n}\n"
		. ".input-group-sm > .input-group-text.material-icons {\n  font-size: var(--bs-btn-input-sm-font-size);\n}\n"
		. ".input-group-lg > .input-group-text.material-icons {\n  font-size: var(--bs-btn-input-lg-font-size);\n}\n"
		. "/* v6's input group joins, restated: the panel's radius rules lower in this\n   layer (.form-control, .input-group-text, .input-group-sm > *) beat\n   Bootstrap's layered joins whatever their weight and rounded every corner\n   of a group. The doubled class outweighs them. v5's add-on background. */\n"
		. ".input-group.input-group > :not(:last-child, .menu-toggle-split, .menu, .input-group-ignore, .form-floating, :has(+ :is(.menu, .input-group-ignore):last-child)),\n"
		. ".input-group.input-group > .menu-toggle-split:nth-last-child(n+3),\n.input-group.input-group > .form-floating:not(:last-child) > .form-control {\n  border-start-end-radius: 0;\n  border-end-end-radius: 0;\n}\n"
		. ".input-group.input-group > :not(:first-child, .menu, .input-group-ignore),\n.input-group.input-group > .form-floating:not(:first-child) > .form-control {\n  border-start-start-radius: 0;\n  border-end-start-radius: 0;\n}\n"
		. ".input-group {\n  --bs-input-group-addon-bg: var(--pg-bg-1);\n}\n"
		. "/* A select in a small or large input group keeps the room for its arrow:\n   v6's input group padding (components layer) beats the select's own\n   (forms layer). */\n"
		. ".input-group-sm > select.form-control:not([multiple], [size]:not([size=\"1\"])) {\n  padding-inline-end: calc(var(--bs-btn-input-sm-padding-x) * 3);\n}\n"
		. ".input-group-lg > select.form-control:not([multiple], [size]:not([size=\"1\"])) {\n  padding-inline-end: calc(var(--bs-btn-input-lg-padding-x) * 3);\n}\n"
		. "/* v5's field: a label in the text's own colour and weight (.small\n   shrinks it), no inset shadow. */\n"
		. ".form-label,\n.col-form-label {\n  --bs-label-font-weight: 400;\n  --bs-label-color: currentcolor;\n}\n"
		. ":where(.form-label, .col-form-label):is(.small, small) {\n  font-size: 0.875em;\n}\n"
		. ".form-control {\n  --bs-control-box-shadow: none;\n}\n"
		. "/* v5's badge: no minimum height or border, its padding, size, weight and\n   radius. */\n"
		. ".badge {\n  --bs-badge-padding-x: 0.65em;\n  --bs-badge-padding-y: 0.35em;\n  --bs-badge-font-size: 0.75em;\n  --bs-badge-font-weight: 700;\n  --bs-badge-border-radius: var(--pg-radius);\n  min-height: 0;\n}\n"
		. ".badge:not(.badge-outline) {\n  --bs-badge-border-width: 0;\n}\n"
		. "/* v5's light outline button, which the visual editor's dark chrome uses\n   (v6 has no light theme). */\n"
		. ".btn-outline-light {\n  --bs-btn-bg: transparent;\n  --bs-btn-color: #f8f9fa;\n  --bs-btn-border-color: #f8f9fa;\n  --bs-btn-hover-bg: #f8f9fa;\n  --bs-btn-hover-color: #000;\n  --bs-btn-hover-border-color: #f8f9fa;\n"
		. "  --bs-btn-active-bg: #f8f9fa;\n  --bs-btn-active-color: #000;\n  --bs-btn-active-border-color: #f8f9fa;\n  --bs-btn-disabled-color: #f8f9fa;\n  --bs-btn-disabled-border-color: #f8f9fa;\n}\n"
		. "/* v5's badge has no background of its own: one with no theme is clear. */\n"
		. ".badge:not(.badge-subtle, .badge-outline) {\n  --bs-badge-bg: transparent;\n}\n"
		. ".pg-menu-caret::after {\n  display: inline-block;\n  margin-left: 0.255em;\n  vertical-align: 0.255em;\n  content: \"\";\n"
		. "  border-top: 0.3em solid;\n  border-right: 0.3em solid transparent;\n  border-bottom: 0;\n  border-left: 0.3em solid transparent;\n}\n"
		. ".pg-menu-caret:empty::after {\n  margin-left: 0;\n}\n"
		. "/* v5's dialog: its radius, an unblurred backdrop, under the top of the\n   screen; .pg-dialog-centered (v5's modal-dialog-centered) is centred. */\n"
		. ".dialog {\n  --bs-dialog-border-radius: var(--pg-radius-lg);\n  --bs-dialog-backdrop-blur: 0px;\n  margin-top: var(--bs-dialog-margin);\n}\n"
		. ".dialog.pg-dialog-centered,\n.dialog.dialog-fullscreen {\n  margin-top: auto;\n}\n"
		. "/* A closed .dialog keeps display: flex (Bootstrap 6 hides it with visibility),\n   so the UA's absolute position would let a tall one stretch the page. */\n"
		. ".dialog:not(:modal) {\n  position: fixed;\n}\n"
		. ".pg-open-sheet {\n  position: fixed;\n  inset: 0;\n  z-index: 1055;\n  overflow-y: auto;\n  padding: 1.75rem 0;\n}\n"
		. ".pg-open-sheet > .dialog {\n  position: relative;\n  max-height: none;\n}\n"
		. ".pg-link-body-emphasis {\n  color: color-mix(in srgb, var(--pg-fg-emphasis) calc(var(--bs-link-opacity, 1) * 100%), transparent) !important;\n"
		. "  text-decoration-color: color-mix(in srgb, var(--pg-fg-emphasis) calc(var(--bs-link-underline-opacity, 1) * 100%), transparent) !important;\n}\n"
		. ".pg-link-body-emphasis:hover,\n.pg-link-body-emphasis:focus {\n  color: color-mix(in srgb, var(--pg-fg-emphasis) calc(var(--bs-link-opacity, 0.75) * 100%), transparent) !important;\n"
		. "  text-decoration-color: color-mix(in srgb, var(--pg-fg-emphasis) calc(var(--bs-link-underline-opacity, 0.75) * 100%), transparent) !important;\n}\n"
		. "/* v5 utilities with no v6 class (bs6_map_plain_token()): emphasis text,\n   the light table tint, lead and display text, block negative margins. */\n"
		. ".pg-fg-emphasis {\n  color: var(--pg-fg-emphasis) !important;\n}\n"
		. ".pg-table-light {\n  --bs-table-color: #000;\n  --bs-table-bg: #f8f9fa;\n  --bs-table-border-color: #c6c7c8;\n  color: #000;\n  border-color: #c6c7c8;\n}\n"
		. ".pg-lead {\n  font-size: 1.25rem;\n  font-weight: 300;\n}\n";

	foreach (array(1 => array('1.625rem', '4.5vw', '5rem'), 2 => array('1.575rem', '3.9vw', '4.5rem'), 3 => array('1.525rem', '3.3vw', '4rem'),
		4 => array('1.475rem', '2.7vw', '3.5rem'), 5 => array('1.425rem', '2.1vw', '3rem'), 6 => array('1.375rem', '1.5vw', '2.5rem')) as $key => $size) {
		$rules .= ".pg-display-" . $key . " {\n  font-size: min(calc(" . $size[0] . " + " . $size[1] . "), " . $size[2] . ");\n  font-weight: 300;\n  line-height: 1.2;\n}\n";
	}

	$sides = array('m' => array('margin'), 'mt' => array('margin-top'), 'mb' => array('margin-bottom'), 'ms' => array('margin-left'), 'me' => array('margin-right'),
		'mx' => array('margin-left', 'margin-right'), 'my' => array('margin-top', 'margin-bottom'));

	foreach ($sides as $side => $properties) {
		foreach (array(1 => '0.25rem', 2 => '0.5rem', 3 => '1rem', 4 => '1.5rem', 5 => '3rem') as $key => $size) {
			$rules .= ".pg-" . $side . "-n" . $key . " {\n";
			foreach ($properties as $property) {
				$rules .= "  " . $property . ": -" . $size . " !important;\n";
			}
			$rules .= "}\n";
		}
	}

	$rules .= "@media print {\n";

	foreach (array('none', 'inline', 'inline-block', 'block', 'grid', 'inline-grid', 'table', 'table-row', 'table-cell', 'flex', 'inline-flex') as $display) {
		$rules .= "  .pg-print-" . $display . " {\n    display: " . $display . " !important;\n  }\n";
	}

	$rules .= "}\n\n";

	return bs6_switch_result(str_replace($open, $open . $rules, $source), 'applied');
}

/**
 * Hand fixes found by their exact text, per file (relative to pinegrap/):
 * array(from, to) pairs applied like the test pins (bs6_switch_pins()), after
 * every other fix. Each names a place the scripted fixes cannot reach: a
 * selector on a dropped box (`.modal-dialog`, `.modal-content`), a box built
 * apart from its dialog, a backdrop rule. They are kept in
 * tools/bs6_switch_texts.txt:
 *
 *   @@ path/under/pinegrap
 *   <<<
 *   text to find (every occurrence is replaced)
 *   ===
 *   its replacement
 *   >>>
 *
 * Lines before the first `@@` and between blocks are comments.
 */
function bs6_switch_texts($file = null)
{
	$file = $file !== null ? $file : __DIR__ . '/bs6_switch_texts.txt';
	$texts = array();
	$path = null;
	$from = null;
	$to = null;
	$mode = null;

	foreach (explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($file))) as $line) {
		if ($mode === null && strncmp($line, '@@ ', 3) === 0) {
			$path = trim(substr($line, 3));
		} elseif ($mode === null && $line === '<<<' && $path !== null) {
			$mode = 'from';
			$from = array();
		} elseif ($mode === 'from' && $line === '===') {
			$mode = 'to';
			$to = array();
		} elseif ($mode === 'to' && $line === '>>>') {
			$texts[$path][] = array(implode("\n", $from), implode("\n", $to));
			$mode = null;
		} elseif ($mode === 'from') {
			$from[] = $line;
		} elseif ($mode === 'to') {
			$to[] = $line;
		}
	}

	return $texts;
}
