<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for My Notes' note drawn as a page: the scope class on its card
 * (.ws-nb-page, workspace.js drawNote()) that every rule of the page hangs
 * on, so a channel's note, the task description and the other writing
 * boxes keep their look; the one row of tools folding what does not fit
 * into its More menu (assets/js/workspace_note_page.js, its pure part run
 * in Node, skipped where Node cannot run); and the script in its bundle.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_note_page_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The page's classes: none of them is drawn or styled outside My Notes.
function workspace_note_page_test_classes()
{
	return array('ws-nb-page', 'ws-nb-sheet', 'ws-nb-meta', 'ws-nb-last', 'ws-nb-more', 'ws-nb-folded');
}

// Runs $body against the script in Node and answers what it printed as JSON;
// null when Node cannot run.
function workspace_note_page_test_node($body)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$script = "var window = {};\n" . workspace_note_page_test_source('assets/js/workspace_note_page.js') . "\n"
		. "var P = window.PGWsNotePage.pure;\n"
		. 'console.log(JSON.stringify((function () {' . $body . '}())));';

	$file = @tempnam(sys_get_temp_dir(), 'pgpage');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// The rules of a stylesheet as selector => body, comments left out.
function workspace_note_page_test_rules($css)
{
	$css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
	$rules = array();

	preg_match_all('#([^{}]+)\{([^{}]*)\}#', $css, $found, PREG_SET_ORDER);

	foreach ($found as $one) {
		$selector = trim((string) preg_replace('#\s+#', ' ', $one[1]));
		$rules[] = array($selector, $one[2]);
	}

	return $rules;
}

// The body of the rule whose selector is exactly $selector; '' when none.
function workspace_note_page_test_rule($css, $selector)
{
	foreach (workspace_note_page_test_rules($css) as $rule) {
		if ($rule[0] === $selector) {
			return $rule[1];
		}
	}

	return '';
}

// How many tools stand on the row: all of them when the last one ends
// inside it, else the ones that end before the More button's room.
function test_workspace_note_page_fit_counts_what_stands()
{
	$result = workspace_note_page_test_node('
		return {
			none: P.fit([], 100, 30),
			all: P.fit([30, 60, 90], 100, 30),
			edge: P.fit([30, 60, 100], 100, 30),
			over: P.fit([30, 60, 90, 120], 100, 30),
			room: P.fit([30, 60, 70, 71, 120], 100, 30),
			nothing: P.fit([50, 120], 40, 30),
			bad: P.fit([30, 60], 0, 30)
		};
	');

	if ($result === null) {
		return;
	}

	pg_assert_same(0, $result['none'], 'no tools, none stand');
	pg_assert_same(3, $result['all'], 'every tool fits: no More');
	pg_assert_same(3, $result['edge'], 'the last tool ending on the edge still fits');
	pg_assert_same(2, $result['over'], 'what ends past the room left for More folds');
	pg_assert_same(3, $result['room'], 'a tool ending on the More room stands, the next folds');
	pg_assert_same(0, $result['nothing'], 'a row too narrow for one tool folds them all');
	pg_assert_same(2, $result['bad'], 'a row not drawn (width 0) folds nothing');
}

// A tool's icon for its line in the menu: the caller's data-ws-icon, else
// the bi- class of the button's icon.
function test_workspace_note_page_icon_of_a_tool()
{
	$result = workspace_note_page_test_node('
		return {
			bold: P.iconOf("bi bi-type-bold"),
			extra: P.iconOf("bi bi-paperclip me-1"),
			plain: P.iconOf("bi"),
			none: P.iconOf("")
		};
	');

	if ($result === null) {
		return;
	}

	pg_assert_same('bi-type-bold', $result['bold']);
	pg_assert_same('bi-paperclip', $result['extra']);
	pg_assert_same('', $result['plain']);
	pg_assert_same('', $result['none']);
}

// Only My Notes' card carries the scope class and only its row folds; a
// channel's note, the highlights and lock panes, the task description and
// the editor itself draw none of the page's classes.
function test_workspace_note_page_scope_is_my_notes_only()
{
	$main = workspace_note_page_test_source('assets/js/workspace.js');

	pg_assert_contains("el('div', 'card pg-card ws-nb-card ws-nb-page')", $main, 'My Notes draws its note card as a page');
	pg_assert_same(1, preg_match_all("#'[^'\\n]*ws-nb-page(?![\\w-])[^'\\n]*'#", $main), 'one card in workspace.js carries the scope class');
	pg_assert_same(1, substr_count($main, 'PGWsNotePage.fold('), 'one row of tools folds');
	pg_assert_contains("var card = h.el('div', 'card ws-nb-card ws-hl-mine');", workspace_note_page_test_source('assets/js/workspace_highlights.js'));
	pg_assert_contains("kit.el('div', 'card ws-nb-card ws-nb-lock-pane')", workspace_note_page_test_source('assets/js/workspace_note_lock.js'));

	$channel = workspace_note_page_test_source('assets/js/workspace_channel_notes.js');

	pg_assert_contains("kit.el('div', 'ws-nb-editor ws-cn-editor')", $channel, "a channel's note keeps its writing box");
	pg_assert_contains("className: 'ws-nb-tools',", $channel, "a channel's note keeps its row of tools");

	foreach (glob(PG_FUNCTIONS_DIR . '/assets/js/workspace*.js') as $file) {
		$name = basename($file);

		if (in_array($name, array('workspace.js', 'workspace_note_page.js'), true) || preg_match('#\.min\.js$#', $name)) {
			continue;
		}

		$script = (string) file_get_contents($file);

		pg_assert_false(strpos($script, 'PGWsNotePage') !== false, $name . ' does not fold its row');

		foreach (workspace_note_page_test_classes() as $class) {
			pg_assert_false(strpos($script, $class) !== false, $name . ' draws no ' . $class);
		}
	}
}

// Every rule naming a class of the page starts at the scope class; the
// shared rules of the row, the writing box and a channel's note stay.
function test_workspace_note_page_rules_are_scoped()
{
	$css = workspace_note_page_test_source('assets/css/backend.src.css');
	$scoped = 0;

	foreach (workspace_note_page_test_rules($css) as $rule) {
		foreach (explode(',', $rule[0]) as $selector) {
			$selector = trim($selector);

			foreach (workspace_note_page_test_classes() as $class) {
				if (preg_match('#\.' . preg_quote($class, '#') . '(?![\w-])#', $selector)) {
					pg_assert_same(0, strpos($selector, '.ws-nb-page '), $selector . ' starts at .ws-nb-page');
					$scoped++;
					break;
				}
			}
		}
	}

	pg_assert_true($scoped > 0, 'the page has rules');

	$tools = workspace_note_page_test_rule($css, '.ws-nb-tools');

	foreach (array('position: sticky;', 'top: 0;', 'flex-wrap: wrap;', 'background: var(--pg-bg-body);') as $part) {
		pg_assert_contains($part, $tools, 'the shared row keeps ' . $part);
	}

	pg_assert_contains('padding: 1rem 1.1rem;', workspace_note_page_test_rule($css, '.ws-rich.ws-rich-field.ws-nb-text'));
	pg_assert_contains('flex-wrap: wrap;', workspace_note_page_test_rule($css, '.ws-nb-head'), 'the shared head keeps its rule');
	pg_assert_contains('padding-left: .75rem;', workspace_note_page_test_rule($css, '.ws-nb-tool-group + .ws-nb-tool-group'));

	$page = workspace_note_page_test_rule($css, '.ws-nb-page .ws-nb-tools');

	foreach (array('top: var(--pg-navbar-h);', 'flex-wrap: nowrap;') as $part) {
		pg_assert_contains($part, $page, 'the page row: ' . $part);
	}

	pg_assert_contains('max-width: 46rem;', workspace_note_page_test_rule($css, '.ws-nb-page .ws-nb-sheet'));

	$text = workspace_note_page_test_rule($css, '.ws-nb-page .ws-rich.ws-rich-field.ws-nb-text');

	foreach (array('border: 0;', 'box-shadow: none;', 'background: transparent;') as $part) {
		pg_assert_contains($part, $text, 'the text on the card: ' . $part);
	}

	pg_assert_contains('outline: 0;', workspace_note_page_test_rule($css, '.ws-nb-page .ws-rich.ws-rich-field.ws-nb-text:focus, .ws-nb-page .ws-rich.ws-rich-field.ws-nb-text:focus-visible'), 'no focus box around the text');
}

// The script is in the Workspace's bundle, before workspace.js, and its
// More button is named.
function test_workspace_note_page_in_the_bundle()
{
	$bundles = pg_js_bundles();
	$sources = $bundles['assets/js/workspace.min.js'];
	$at = array_search('assets/js/workspace_note_page.js', $sources, true);

	pg_assert_true($at !== false, 'the page script is bundled');
	pg_assert_true($at < array_search('assets/js/workspace.js', $sources, true), 'before workspace.js');
	pg_assert_contains("'notes_more_tools'", workspace_note_page_test_source('includes/workspace/notes.php'));
}
