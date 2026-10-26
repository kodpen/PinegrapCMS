<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Attributes and Styles panels at content level (manager,
 * user): which part is read-only and why (_sdPanelLockReason()), and the
 * reason text (_sdContentReason()). An element's classes, attributes and
 * inline style may change only inside an area marked as editable, since the
 * save takes text and image addresses alone elsewhere; the design's User
 * Styles never. The functions are cut out of assets/js/style_designer.js and
 * run under Node with the editor's state stubbed; skipped without Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one function declaration of the designer, braces matched;
// '' when it is not found.
function designer_panels_readonly_test_function($source, $name)
{
	$from = strpos($source, 'function ' . $name . '(');

	if ($from === false) {
		return '';
	}

	$open = strpos($source, '{', $from);
	$depth = 0;

	for ($i = $open; $i < strlen($source); $i++) {
		if ($source[$i] === '{') {
			$depth++;
		} elseif ($source[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				return substr($source, $from, $i - $from + 1);
			}
		}
	}

	return '';
}

function test_designer_panels_readonly_reason()
{
	if (!function_exists('exec')) {
		return;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$functions = '';

	foreach (array('_sdCanEditNode', '_sdIsTextNode', '_sdPanelLockReason', '_sdContentReason') as $name) {
		$found = designer_panels_readonly_test_function($source, $name);
		pg_assert_true($found !== '', $name . ' is in the designer');
		$functions .= $found . "\n";
	}

	// Nodes say where they sit: area (inside a marked area), closed (in a
	// shared component not opened to the operator).
	$script = 'var _collab = { mayEdit: true }; var level = "content";'
		. 'function _sdT(s) { return s; }'
		. 'function _sdIsContentLevel() { return level === "content"; }'
		. 'function _sdInClosedShared(n) { return !!(n && n.closed); }'
		. 'function _sdInEditableArea(n) { return !!(n && n.area); }'
		. 'function _sdNodeContentImage() { return null; }'
		. $functions
		. 'var plain = { type: "semantic", props: { tag: "div", text: "" } };'
		. 'var marked = { type: "semantic", props: { tag: "div" }, area: true };'
		. 'var heading = { type: "content", props: { contentType: "heading", text: "Hi" } };'
		. 'var shared = { type: "semantic", props: { tag: "div" }, area: true, closed: true };'
		. 'var r = {};'
		. 'r.plain = _sdPanelLockReason([plain]);'
		. 'r.heading = _sdPanelLockReason([heading]);'
		. 'r.marked = _sdPanelLockReason([marked]);'
		. 'r.shared = _sdPanelLockReason([shared]);'
		. 'r.mixed = _sdPanelLockReason([marked, plain]);'
		. 'r.allMarked = _sdPanelLockReason([marked, marked]);'
		. 'r.single = _sdPanelLockReason(marked);'
		. 'r.none = _sdPanelLockReason([]);'
		. 'r.design = _sdPanelLockReason([marked], "design");'
		. '_collab.mayEdit = false; r.viewMode = _sdPanelLockReason([plain]); _collab.mayEdit = true;'
		. 'level = "full"; r.full = _sdPanelLockReason([plain]); r.fullDesign = _sdPanelLockReason([plain], "design");'
		. 'r.text = [_sdContentReason("area"), _sdContentReason("shared"), _sdContentReason("design")];'
		. 'console.log(JSON.stringify(r));';

	$file = @tempnam(sys_get_temp_dir(), 'pgsdpanel');

	if ($file === false) {
		return;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));

	if ($code !== 0) {
		return;
	}

	$r = json_decode(implode("\n", $out), true);

	pg_assert_same('area', $r['plain'], 'an element outside a marked area is read-only');
	pg_assert_same('area', $r['heading'], 'a heading\'s text is the operator\'s, its class and attributes are not');
	pg_assert_same('', $r['marked'], 'inside a marked area the panels edit');
	pg_assert_same('shared', $r['shared'], 'a closed shared component wins over a marked area');
	pg_assert_same('area', $r['mixed'], 'a selection edits only when every element may');
	pg_assert_same('', $r['allMarked']);
	pg_assert_same('', $r['single'], 'one node without a list');
	pg_assert_same('', $r['none'], 'nothing selected, nothing to lock');
	pg_assert_same('design', $r['design'], 'the design\'s User Styles are never written at content level');
	pg_assert_same('', $r['viewMode'], 'view mode keeps its own nudge');
	pg_assert_same('', $r['full'], 'a designer edits everything');
	pg_assert_same('', $r['fullDesign']);
	pg_assert_same(array(
		'You can edit text, and anything inside an area marked as editable.',
		'System widgets and shared components are managed by designers.',
		'The design\'s styles are managed by designers.',
	), $r['text']);
}
