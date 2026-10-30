<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visual designer's canvas node index (assets/js/
 * style_designer.js): _sdCanvasNodeIndex() keeps the page's nodes and each
 * shared component's apart, and _sdCanvasNodeOf() reads a canvas wrapper's
 * id in the tree its `data-sw-sid` stamp names, so an audit row names the
 * page's own node when a shared component reuses its id. The two functions
 * are cut out of the file and run under Node on a stub canvas; on a machine
 * without Node the run is skipped. Also: the Hero Section block's second
 * button, the Add Page menu closing on New Page, and the pricing block's
 * support cell keeping its own key apart from the field name 'Priority'.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_audit_node_index_test_source()
{
	static $js = null;
	if ($js === null) {
		$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	}
	return $js;
}

// The source of one function declaration, braces matched; '' when missing.
function designer_audit_node_index_test_function($name)
{
	$source = designer_audit_node_index_test_source();
	$from = strpos($source, 'function ' . $name . '(');

	if ($from === false) {
		return '';
	}

	$depth = 0;

	for ($i = strpos($source, '{', $from); $i < strlen($source); $i++) {
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

// A page node and a shared component's node share the id sd_4: each canvas
// wrapper resolves to the node of its own tree.
function test_designer_audit_node_index_keeps_page_and_shared_apart()
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

	$code_js = '';

	foreach (array('_sdCanvasNodeIndex', '_sdCanvasNodeOf') as $name) {
		$found = designer_audit_node_index_test_function($name);
		pg_assert_true($found !== '', $name . ' is in the designer');
		$code_js .= $found . "\n";
	}

	// A stub canvas element: attributes, a parent and closest() for one
	// attribute selector.
	$script = 'function El(attrs, parent) { this.attrs = attrs; this.parentElement = parent || null; }'
		. 'El.prototype.getAttribute = function (k) { return this.attrs.hasOwnProperty(k) ? this.attrs[k] : null; };'
		. 'El.prototype.closest = function () { for (var e = this; e; e = e.parentElement) { if (e.attrs.hasOwnProperty("data-sd-id")) return e; } return null; };'
		. 'function N(id, name, kids) { return { _id: id, type: "semantic", props: { customName: name }, children: kids || [] }; }'
		. 'var page = { _id: "sd_0", type: "root", props: {}, children: [N("sd_3", "Hero"), N("sd_4", "Page button"), N("sd_9", "Header ref")] };'
		. 'var _sharedCache = { 12: { tree: { _id: "sd_1", type: "root", props: {}, children: [N("sd_4", "Cart button"), N("sd_5", "Account link")] } }, 13: {} };'
		. 'var index = _sdCanvasNodeIndex(page);'
		. 'var body = new El({});'
		. 'var pageBtn = new El({ "data-sd-id": "sd_4" }, new El({ "data-sd-id": "sd_3" }, body));'
		. 'var ref = new El({ "data-sd-id": "sd_9" }, body);'
		. 'var cartBtn = new El({ "data-sd-id": "sd_4", "data-sw-sid": "12" }, new El({}, ref));'
		. 'var unknownInShared = new El({ "data-sd-id": "sd_3", "data-sw-sid": "12" }, ref);'
		. 'var missingSid = new El({ "data-sd-id": "sd_3", "data-sw-sid": "13" }, body);'
		. 'var name = function (n) { return n ? n.props.customName : null; };'
		. 'console.log(JSON.stringify([name(_sdCanvasNodeOf(new El({}, pageBtn), index)), name(_sdCanvasNodeOf(new El({}, cartBtn), index)),'
		. ' name(_sdCanvasNodeOf(unknownInShared, index)), name(_sdCanvasNodeOf(missingSid, index)), name(_sdCanvasNodeOf(body, index))]));';

	$file = @tempnam(sys_get_temp_dir(), 'pgsdindex');

	if ($file === false) {
		return;
	}

	file_put_contents($file, $code_js . "\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));
	pg_assert_same(
		array('Page button', 'Cart button', 'Header ref', null, null),
		json_decode(implode("\n", $out), true),
		'a wrapper is read in its own tree; a shared id the component lacks falls to the shared_ref wrapper, never to the page node'
	);
}

// The Hero Section block draws a dark band: its second button is a solid
// btn-secondary, since btn-outline-secondary keeps a grey text (3.2:1).
function test_designer_hero_block_second_button_passes_contrast()
{
	$source = designer_audit_node_index_test_source();
	$from = strpos($source, "if (ctype === 'hero') {");
	pg_assert_true($from !== false, 'the Hero Section builder is in the designer');
	$body = substr($source, $from, strpos($source, "if (ctype === 'hero-centered')", $from) - $from);

	pg_assert_true(strpos($body, "data-bs-theme', value: 'dark'") !== false, 'the Hero Section block is a dark band');
	pg_assert_true(strpos($body, "text: _sdT('Secondary'), variant: 'secondary'") !== false, 'its second button is btn-secondary');
	pg_assert_false(strpos($body, "'outline-secondary'") !== false, 'no btn-outline-secondary on the dark band');
}

// Choosing New Page closes the Add Page menu before the new tab asks for
// its name.
function test_designer_add_page_menu_closes_on_new_page()
{
	$source = designer_audit_node_index_test_source();
	$from = strpos($source, "var addNew = document.getElementById('sd-tab-new-page');");
	pg_assert_true($from !== false, 'the New Page item is bound');
	$body = substr($source, $from, 600);

	$hide = strpos($body, 'pgUI.menu.hide(btn)');
	$add = strpos($body, '_pgTabsAddNew()');
	pg_assert_true($hide !== false && $add !== false && $hide < $add, 'the menu is hidden before the page is added');
}

// 'Priority' is a field name (DNS records, tasks): "Öncelik". The pricing
// block's support cell is an adjective and has its own key.
function test_designer_priority_keys_are_apart()
{
	$source = designer_audit_node_index_test_source();
	pg_assert_true(strpos($source, "_pcCellText(_sdT('Priority support'),") !== false, 'the pricing support cell reads Priority support');
	pg_assert_false(strpos($source, "_sdT('Priority')") !== false, 'the designer no longer uses the field name as an adjective');

	$tr = json_decode((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/local/tr/p.json'), true);
	pg_assert_same('Öncelik', isset($tr['Priority']) ? $tr['Priority'] : null, 'Priority is the field name');
	pg_assert_same('Öncelikli destek', isset($tr['Priority support']) ? $tr['Priority support'] : null, 'Priority support is the adjective');
}
