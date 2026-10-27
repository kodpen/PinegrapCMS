<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The token fixes of the Bootstrap 6 switch, run by bs6_switch.php
 * (docs/memory/ui/panel-css-tokens.md). The panel's rules read --pg-*
 * tokens; until the switch the token block resolves them from Bootstrap 5
 * variables, most of which Bootstrap 6 renames or drops.
 *
 * Fixes:
 *   tokens   a token declared from a v5 variable (`--pg-bg-1:
 *            var(--bs-tertiary-bg)`) takes its v6 value; a rule that still
 *            reads a v5 variable reads the panel's token instead
 *   rgb      a v5 RGB triple read with an alpha becomes color-mix() on the
 *            panel token; the panel's own body and navbar triples are
 *            renamed --pg-*-rgb
 *   bridge   Bootstrap 6's own variables take the panel's palette and the
 *            v5 neutrals, so its theme classes, badges, buttons, menus and
 *            dialogs keep the panel's colours and radii
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The comment the bridge block starts with; its presence marks a file done.
const BS6_SWITCH_BRIDGE_NOTE = '/* Bootstrap 6 bridge: its theme, neutral and radius variables';

// Accent colours of the panel palette (--pg-<colour>, -ink, -subtle, -line).
function bs6_switch_token_colours()
{
	return array('primary', 'secondary', 'success', 'danger', 'warning', 'info');
}

// v5 variable name (without --bs-) => the v6 value a token declared from it
// takes.
function bs6_switch_token_values()
{
	$values = array(
		'body-bg' => 'var(--bs-bg-body)',
		'tertiary-bg' => 'var(--bs-bg-1)',
		'secondary-bg' => 'var(--bs-bg-2)',
		'body-color' => 'var(--bs-fg-body)',
		'emphasis-color' => 'light-dark(var(--bs-black), var(--bs-white))',
		'secondary-color' => 'var(--bs-fg-2)',
		'tertiary-color' => 'var(--bs-fg-3)',
		'border-radius-sm' => 'var(--bs-radius-3)',
		'border-radius' => 'var(--bs-radius-4)',
		'border-radius-lg' => 'var(--bs-radius-5)',
		'border-radius-xl' => 'var(--bs-radius-9)',
		'border-radius-xxl' => '2rem',
		'border-radius-pill' => 'var(--bs-radius-pill)',
		'font-monospace' => 'var(--bs-font-mono)',
		'dark' => 'var(--bs-gray-900)',
	);

	foreach (bs6_switch_token_colours() as $colour) {
		$values[$colour] = 'var(--bs-' . $colour . '-base)';
		$values[$colour . '-text-emphasis'] = 'var(--bs-' . $colour . '-fg-emphasis)';
		$values[$colour . '-border-subtle'] = 'var(--bs-' . $colour . '-border)';
	}

	return $values;
}

// v5 variable name (without --bs-) => the panel token a rule reads instead.
function bs6_switch_token_names()
{
	$names = array(
		'body-bg' => 'bg-body',
		'tertiary-bg' => 'bg-1',
		'secondary-bg' => 'bg-2',
		'body-color' => 'fg-body',
		'emphasis-color' => 'fg-emphasis',
		'secondary-color' => 'fg-2',
		'tertiary-color' => 'fg-3',
		'border-radius-sm' => 'radius-sm',
		'border-radius' => 'radius',
		'border-radius-lg' => 'radius-lg',
		'border-radius-xl' => 'radius-xl',
		'border-radius-xxl' => 'radius-2xl',
		'border-radius-pill' => 'radius-pill',
		'font-monospace' => 'font-mono',
	);

	foreach (bs6_switch_token_colours() as $colour) {
		$names[$colour . '-text-emphasis'] = $colour . '-fg-emphasis';
		$names[$colour . '-border-subtle'] = $colour . '-border-subtle';
	}

	return $names;
}

// Files whose tokens or rules read v5 variables: the panel and editor
// stylesheets, the prototype pages' own token blocks and the panel script
// that writes rules of its own.
function bs6_switch_token_files($root)
{
	$paths = bs6_switch_stylesheets($root);
	$paths[] = 'includes/fn/prototype.php';
	$paths[] = 'includes/fn/prototype_links.php';
	$paths[] = 'assets/js/backend.src.js';

	return $paths;
}

// A token declaration `--pg-x: var(--bs-<v5>)` takes the v6 value; then any
// other `var(--bs-<v5>` reads the panel token. The longest name is tried
// first (border-radius-sm before border-radius).
function bs6_switch_tokens($source)
{
	$values = bs6_switch_token_values();
	$names = bs6_switch_token_names();
	$keys = array_keys($values + $names);
	usort($keys, function ($a, $b) {
		return strlen($b) - strlen($a);
	});
	$alternation = implode('|', array_map(function ($key) {
		return preg_quote($key, '/');
	}, $keys));
	$count = 0;

	// One pass, so a value written for a declaration is not read again as a
	// rule's variable.
	// A --bs-theme-* declaration names v6's theme variables (the shell's
	// v5 alert colours), which share v5's names: it is left as it is.
	$source = preg_replace_callback('/(--bs-theme-[a-z-]+\s*:\s*|--pg-[a-z0-9-]+\s*:\s*)?var\(--bs-(' . $alternation . ')(?![a-z0-9-])(\)\s*[;}])?/', function ($m) use ($names, $values, &$count) {
		$declaration = isset($m[1]) ? $m[1] : '';
		$end = isset($m[3]) ? $m[3] : '';

		if (strpos($declaration, '--bs-theme-') === 0) {
			return $m[0];
		}

		if ($declaration !== '' && $end !== '' && isset($values[$m[2]])) {
			$count++;
			return $declaration . $values[$m[2]] . substr($end, 1);
		}

		if (isset($names[$m[2]])) {
			$count++;
			return $declaration . 'var(--pg-' . $names[$m[2]] . $end;
		}

		// A bare colour (`--bs-primary`) outside a token declaration: the
		// panel's palette holds the same fill under --pg-<colour>.
		if (in_array($m[2], bs6_switch_token_colours(), true)) {
			$count++;
			return $declaration . 'var(--pg-' . $m[2] . $end;
		}

		// No panel token (`--bs-dark`): the v6 value itself.
		if ($end !== '' && isset($values[$m[2]])) {
			$count++;
			return $declaration . $values[$m[2]] . substr($end, 1);
		}

		return $m[0];
	}, $source);

	if ($count > 0) {
		return bs6_switch_result($source, 'applied');
	}

	return bs6_switch_result($source, 'done');
}

// The bridge, unlayered (bs6_switch_token_bridge()): v6's per-colour
// variables from the palette (the filled tone, its ink for text, the subtle
// surface and its line, the ink printed on a fill), the page background the
// panel sets per theme, the v5 neutrals, and v5's button and field sizes (no
// minimum height, v5 radii, the small and large paddings, a space-wide gap
// between icon and label), so the switch keeps today's look. Declared on
// every [data-bs-theme] element like the token aliases: the palette flips
// there.
function bs6_switch_token_bridge_block()
{
	$lines = array(
		'  --bs-bg-body: rgb(var(--pg-body-bg-rgb));',
		'  --bs-bg-1: light-dark(#f8f9fa, #2b3035);',
		'  --bs-bg-2: light-dark(#e9ecef, #343a40);',
		'  --bs-fg-body: light-dark(#212529, #dee2e6);',
		'  --bs-fg-2: light-dark(rgba(33, 37, 41, 0.75), rgba(222, 226, 230, 0.75));',
		'  --bs-fg-3: light-dark(rgba(33, 37, 41, 0.5), rgba(222, 226, 230, 0.5));',
		'  --bs-border-color: light-dark(#dee2e6, #495057);',
		'  --bs-border-color-translucent: light-dark(rgba(0, 0, 0, 0.175), rgba(255, 255, 255, 0.15));',
		'  --bs-link-color: var(--pg-primary-ink);',
		'  --bs-link-hover-color: var(--pg-primary-ink-hover);',
		'  --bs-focus-ring-color: color-mix(in srgb, var(--pg-primary) 35%, transparent);',
		'  --bs-btn-input-min-height: 0;',
		'  --bs-btn-input-xs-min-height: 0;',
		'  --bs-btn-input-sm-min-height: 0;',
		'  --bs-btn-input-lg-min-height: 0;',
		'  --bs-btn-input-border-radius: var(--bs-radius-4);',
		'  --bs-btn-input-xs-border-radius: var(--bs-radius-3);',
		'  --bs-btn-input-sm-border-radius: var(--bs-radius-3);',
		'  --bs-btn-input-lg-border-radius: var(--bs-radius-5);',
		'  --bs-btn-input-gap: 0.25em;',
		'  --bs-btn-input-xs-gap: 0.25em;',
		'  --bs-btn-input-sm-gap: 0.25em;',
		'  --bs-btn-input-lg-gap: 0.25em;',
		'  --bs-btn-input-sm-padding-x: 0.5rem;',
		'  --bs-btn-input-sm-line-height: 1.5;',
		'  --bs-btn-input-lg-font-size: 1.25rem;',
		'  --bs-btn-input-lg-line-height: 1.5;',
	);

	foreach (bs6_switch_token_colours() as $colour) {
		$lines[] = '  --bs-' . $colour . '-base: var(--pg-' . $colour . ');';
		$lines[] = '  --bs-' . $colour . '-bg: var(--pg-' . $colour . ');';
		$lines[] = '  --bs-' . $colour . '-fg: var(--pg-' . $colour . '-ink);';
		$lines[] = '  --bs-' . $colour . '-fg-emphasis: var(--pg-' . $colour . '-ink);';
		$lines[] = '  --bs-' . $colour . '-bg-subtle: var(--pg-' . $colour . '-subtle);';
		$lines[] = '  --bs-' . $colour . '-bg-muted: var(--pg-' . $colour . '-line);';
		$lines[] = '  --bs-' . $colour . '-border: var(--pg-' . $colour . '-line);';
		$lines[] = '  --bs-' . $colour . '-contrast: var(--pg-on-accent);';
		$lines[] = '  --bs-' . $colour . '-focus-ring: color-mix(in srgb, var(--pg-' . $colour . ') 50%, transparent);';
	}

	return BS6_SWITCH_BRIDGE_NOTE . "\n   read the panel's palette and Bootstrap 5's neutrals, so the panel keeps\n"
		. "   its look until the tokens are tuned. */\n:root,\n[data-bs-theme] {\n" . implode("\n", $lines) . "\n}\n"
		. "/* v6 keeps a scrollbar's gutter on the page even when nothing scrolls. */\n:root {\n  scrollbar-gutter: auto;\n}\n"
		. "/* v6 declares the drawer's variables unlayered too: a drawer reads the\n   panel's --pg-drawer-* (bs6_switch_drawer_variables()), else v5's look,\n   flush with the screen edge and square. */\n"
		. ".drawer,\n.sm\\:drawer,\n.md\\:drawer,\n.lg\\:drawer,\n.xl\\:drawer,\n.\\32 xl\\:drawer {\n"
		. "  --bs-drawer-inset: var(--pg-drawer-inset, 0);\n  --bs-drawer-border-radius: var(--pg-drawer-border-radius, 0);\n"
		. "  --bs-drawer-width: var(--pg-drawer-width, 400px);\n  --bs-drawer-height: var(--pg-drawer-height, 30vh);\n}\n";
}

/**
 * Bootstrap 6 declares --bs-drawer-* on .drawer outside any layer, so a
 * panel rule in @layer custom that sets one (`.pg-perm-panel {
 * --bs-drawer-width: 32rem }`) loses. The panel's rules set --pg-drawer-*
 * instead; the bridge's unlayered .drawer rule reads them.
 */
function bs6_switch_drawer_variables($source)
{
	// The bridge itself, unlayered, is where the v6 names are set.
	$bridge = strpos($source, BS6_SWITCH_BRIDGE_NOTE);
	$head = $bridge === false ? $source : substr($source, 0, $bridge);
	$tail = $bridge === false ? '' : substr($source, $bridge);
	$head = preg_replace('/--bs-drawer-(inset|border-radius|width|height)(\s*:)/', '--pg-drawer-$1$2', $head, -1, $count);

	return bs6_switch_result($head . $tail, $count > 0 ? 'applied' : 'done');
}

// Panel stylesheets and files with style blocks that may size a drawer.
function bs6_switch_drawer_files($root)
{
	return array_values(array_unique(array_merge(bs6_switch_stylesheets($root), bs6_switch_style_files())));
}

// backend.src.css: the bridge goes after the custom layer, unlayered.
// Bootstrap 6 declares its root variables outside any layer, and an
// unlayered declaration beats every layered one; this sheet loads after
// Bootstrap's, so its own unlayered block wins on source order.
function bs6_switch_token_bridge($source)
{
	if (strpos($source, BS6_SWITCH_BRIDGE_NOTE) !== false) {
		return bs6_switch_result($source, 'done');
	}

	if (strpos($source, BS6_SWITCH_LAYER_NOTE) === false) {
		return bs6_switch_result($source, 'missing');
	}

	return bs6_switch_result(rtrim($source) . "\n\n" . bs6_switch_token_bridge_block(), 'applied');
}

// Panel files that read a v5 RGB triple (`rgba(var(--bs-primary-rgb), .3)`),
// or the panel's own body and navbar triples.
function bs6_switch_rgb_files($root)
{
	$paths = bs6_switch_token_files($root);
	$paths[] = 'includes/fn/output.php';
	$paths[] = 'includes/fn/design_themes.php';
	$paths[] = 'assets/js/page_designer.js';
	// Its in-panel SEO block reads the admin bar's navbar triple.
	$paths[] = 'toolbar.php';

	return array_values(array_unique($paths));
}

/**
 * Bootstrap 6 has no `--bs-*-rgb` triples. A colour of the palette read
 * with an alpha becomes `color-mix(in srgb, var(--pg-<colour>) N%,
 * transparent)` (`rgb(var(…))` the token itself); the body and navbar
 * triples the panel declares itself are renamed `--pg-body-bg-rgb` and
 * `--pg-navbar-bg-rgb`, declarations and reads alike.
 */
function bs6_switch_rgb_variables($source)
{
	$tokens = array('emphasis-color' => 'fg-emphasis', 'body-color' => 'fg-body');
	foreach (bs6_switch_token_colours() as $colour) {
		$tokens[$colour] = $colour;
	}

	$alternation = implode('|', array_map(function ($key) {
		return preg_quote($key, '/');
	}, array_keys($tokens)));
	$count = 0;

	$source = preg_replace_callback('/rgba?\(\s*var\(--bs-(' . $alternation . ')-rgb\)\s*(?:,\s*([0-9]*\.?[0-9]+)(%?)\s*)?\)/', function ($m) use ($tokens, &$count) {
		$count++;
		$token = 'var(--pg-' . $tokens[$m[1]] . ')';

		if (!isset($m[2]) || $m[2] === '') {
			return $token;
		}

		$percent = $m[3] === '%' ? (float) $m[2] : (float) $m[2] * 100;

		return 'color-mix(in srgb, ' . $token . ' ' . round($percent, 2) . '%, transparent)';
	}, $source);

	$source = preg_replace('/--bs-(body-bg|navbar-bg)-rgb(?![a-z0-9-])/', '--pg-$1-rgb', $source, -1, $renamed);
	$count += $renamed;

	return bs6_switch_result($source, $count > 0 ? 'applied' : 'done');
}
