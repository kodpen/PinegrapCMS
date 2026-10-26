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
 *   names    the renamed components' class names, data attributes and
 *            open-state selectors in code
 *   variables  component variables in panel stylesheets (--bs-dropdown-*
 *            to --bs-menu-*, offcanvas to drawer, modal to dialog)
 *   shell    the v5 look the panel keeps: flush drawers, the menu caret, the
 *            menu wrapper's positioning (bs6_switch_shell_rules())
 *   links    v5 link colour helpers to the panel's own pg-link-* classes
 *   breakpoints  own media queries at v5's lg/xl/xxl widths (992, 1200,
 *            1400) move to v6's (1024, 1280, 1536)
 *   texts    hand fixes found by their exact text (bs6_switch_texts())
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

require_once(__DIR__ . '/bs6_rules.php');
require_once(__DIR__ . '/bs6_switch_shell.php');

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

	foreach (bs6_switch_stylesheets($root) as $path) {
		$plan[] = array($path, 'selectors', 'bs6_switch_selectors');
		$plan[] = array($path, 'variables', 'bs6_switch_component_variables');
	}

	$plan[] = array('assets/css/backend.src.css', 'shell', 'bs6_switch_shell_rules');

	foreach (bs6_switch_link_files($root) as $path) {
		$plan[] = array($path, 'links', 'bs6_switch_links');
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

	foreach (bs6_switch_shell_files($root) as $path) {
		$plan[] = array($path, 'dialogs', 'bs6_switch_dialogs');
		$plan[] = array($path, 'drawers', 'bs6_switch_drawers');
		$plan[] = array($path, 'menus', 'bs6_switch_menus');
		$plan[] = array($path, 'names', 'bs6_switch_component_names');
	}

	$plan[] = array('assets/js/style_designer.js', 'dialogs', 'bs6_switch_editor_dialogs');

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

// Scripts and pages the codemod converts that name a modal, offcanvas or
// dropdown, sorted. The visual editor's script is left out: its chrome and
// its canvas code (Bootstrap 5 inside the page) share members, so its
// dialogs and menus are converted by their text (bs6_switch_texts()).
function bs6_switch_shell_files($root)
{
	$paths = array();

	foreach (bs6_scope_files(rtrim($root, '/')) as $path => $kind) {
		if (!bs6_codemod_converts($path) || $path === 'assets/js/style_designer.js' || !preg_match('/\.(php|js)$/', $path)) {
			continue;
		}

		if (preg_match('/modal|offcanvas|dropdown|dropup|dropend|dropstart/', (string) file_get_contents(rtrim($root, '/') . '/' . $path))) {
			$paths[] = $path;
		}
	}

	sort($paths);

	return $paths;
}

// Files the codemod converts and panel stylesheets that name a v5 link
// colour helper, sorted; the editor's script is left out (its canvas code
// names the site's Bootstrap 5 classes).
function bs6_switch_link_files($root)
{
	$paths = array();

	foreach (bs6_scope_files(rtrim($root, '/')) as $path => $kind) {
		$sheet = in_array($path, bs6_switch_stylesheets($root), true);

		if ((!$sheet && !bs6_codemod_converts($path)) || $path === 'assets/js/style_designer.js' || !preg_match('/\.(php|js|css)$/', $path)) {
			continue;
		}

		if (preg_match('/(?<![\w-])link-(primary|secondary|success|danger|warning|info|body-emphasis)(?![\w-])/', (string) file_get_contents(rtrim($root, '/') . '/' . $path))) {
			$paths[] = $path;
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
		'form-check' => '.form-field:has(> .check, > .radio, > .switch)',
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
		),
		'tests/ai_write_test.php' => array(
			array('column-gap-2', 'column-gap-3'),
		),
		'tests/catalog_bin_test.php' => array(
			array('btn my-1 btn-secondary', 'btn my-1 btn-solid theme-secondary'),
		),
		'tests/erp_edoc_service_test.php' => array(
			array('<div class=\"col-12 my-2\">', '<div class=\"col-12 my-3\">'),
		),
		'tests/parasut_order_gates_test.php' => array(
			array("pg_assert_contains('bg-success\">' . lang('Sent')", "pg_assert_contains('theme-success\">' . lang('Sent')"),
			array("pg_assert_contains('bg-warning', \$open)", "pg_assert_contains('theme-warning', \$open)"),
		),
		'tests/parasut_order_invoice_test.php' => array(
			array("pg_assert_contains('bg-warning', \$unsure)", "pg_assert_contains('theme-warning', \$unsure)"),
		),
		'tests/payment_drivers_test.php' => array(
			array("pg_assert_contains('alert-warning', \$unread", "pg_assert_contains('alert theme-warning', \$unread"),
		),
		'tests/signin_test.php' => array(
			array("'bg-warning'", "'theme-warning'"),
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
		'tests/widget_fill_test.php' => array(
			array('<select class="form-select form-select-sm sd-sw-bindfilter"', '<select class="form-control form-control-sm sd-sw-bindfilter"'),
		),
	);
}

// Replaces every occurrence of each pair's first text with its second.
// Missing when a first text is gone and its second is not there: the test
// changed on development and its pair needs updating.
function bs6_switch_pins($source, array $pairs)
{
	$applied = false;

	foreach ($pairs as $pair) {
		if (strpos($source, $pair[0]) !== false) {
			$source = str_replace($pair[0], $pair[1], $source);
			$applied = true;
		} elseif (strpos($source, $pair[1]) === false) {
			return bs6_switch_result($source, 'missing');
		}
	}

	return bs6_switch_result($source, $applied ? 'applied' : 'done');
}

// The comment the shell rules start with; its presence marks them done.
const BS6_SWITCH_SHELL_NOTE = '/* Bootstrap 6 shell: what the panel keeps of Bootstrap 5\'s look. */';

// Rules at the top of backend.src.css's custom layer that keep the v5 look
// of the converted components: drawers flush with the screen edge and
// square (v6 floats them with an inset and a radius), the caret v6 no
// longer draws on a menu toggle, the wrapper a menu used to position
// against, the scrim of a dialog printed open (bs6_shell_open_sheet()), and
// v5's link-body-emphasis as the panel's pg-link-body-emphasis.
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
		. ".drawer {\n  --bs-drawer-inset: 0;\n  --bs-drawer-border-radius: 0;\n}\n"
		. ".pg-menu-wrap {\n  position: relative;\n}\n"
		. ".pg-menu-caret::after {\n  display: inline-block;\n  margin-left: 0.255em;\n  vertical-align: 0.255em;\n  content: \"\";\n"
		. "  border-top: 0.3em solid;\n  border-right: 0.3em solid transparent;\n  border-bottom: 0;\n  border-left: 0.3em solid transparent;\n}\n"
		. ".pg-menu-caret:empty::after {\n  margin-left: 0;\n}\n"
		. ".pg-open-sheet {\n  position: fixed;\n  inset: 0;\n  z-index: 1055;\n  overflow-y: auto;\n  padding: 1.75rem 0;\n}\n"
		. ".pg-open-sheet > .dialog {\n  position: relative;\n  max-height: none;\n}\n"
		. ".pg-link-body-emphasis {\n  color: color-mix(in srgb, var(--pg-fg-emphasis) calc(var(--bs-link-opacity, 1) * 100%), transparent) !important;\n"
		. "  text-decoration-color: color-mix(in srgb, var(--pg-fg-emphasis) calc(var(--bs-link-underline-opacity, 1) * 100%), transparent) !important;\n}\n"
		. ".pg-link-body-emphasis:hover,\n.pg-link-body-emphasis:focus {\n  color: color-mix(in srgb, var(--pg-fg-emphasis) calc(var(--bs-link-opacity, 0.75) * 100%), transparent) !important;\n"
		. "  text-decoration-color: color-mix(in srgb, var(--pg-fg-emphasis) calc(var(--bs-link-underline-opacity, 0.75) * 100%), transparent) !important;\n}\n\n";

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
