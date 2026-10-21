<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pictures a content operator (manager, user) is offered in the
 * visual designer outside a marked area: an image content node and a bare
 * <img>, whose address the save takes (_pg_dm_take_image_src()), and not an
 * inline background image, which the save keeps as stored. The designer's own
 * functions (_sdCanEditNode(), _sdNodeImage(), _sdNodeContentImage()) are cut
 * out of assets/js/style_designer.js and run under Node with the editor's
 * state stubbed; on a machine without Node the run is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one function declaration of the designer, braces matched
// (strings in these functions hold no braces); '' when it is not found.
function designer_content_image_test_function($source, $name)
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

// Runs the three functions with the stubs in $state and prints what $script
// gives as JSON; null when there is no Node.
function designer_content_image_test_run($script)
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

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$functions = '';

	foreach (array('_sdCanEditNode', '_sdNodeImage', '_sdNodeContentImage') as $name) {
		$found = designer_content_image_test_function($source, $name);
		pg_assert_true($found !== '', $name . ' is in the designer');
		$functions .= $found . "\n";
	}

	$stubs = 'var _collab = { mayEdit: true }; var level = "content"; var area = false;'
		. 'function _sdIsContentLevel() { return level === "content"; }'
		. 'function _sdInClosedShared() { return false; }'
		. 'function _sdInEditableArea() { return area; }'
		. 'function _sdIsTextNode(n) { return !!(n && n.props && typeof n.props.text === "string"); }'
		. 'function getAttrStyleProp(n, p) { return (p === "background-image" && n.bg) ? n.bg : ""; }'
		. 'function setAttrStyleProp() {} function setNodeAttr() {}';

	$file = @tempnam(sys_get_temp_dir(), 'pgsdimage');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $stubs . "\n" . $functions . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		return array('error' => implode("\n", $out));
	}

	return json_decode(implode("\n", $out), true);
}

// Outside a marked area the content level is offered the image content node
// and <img>, never the background image; a designer, and anyone inside a
// marked area, is offered all three.
function test_designer_content_image_offered_where_the_save_takes_it()
{
	$result = designer_content_image_test_run(
		'var image = { type: "content", props: { contentType: "image", src: "/a.jpg" } };'
		. 'var img = { type: "semantic", props: { tag: "img", _attrs: [{ name: "src", value: "/b.jpg" }] } };'
		. 'var hero = { type: "semantic", props: { tag: "section" }, bg: "url(/hero.jpg)" };'
		. 'var plain = { type: "semantic", props: { tag: "div" } };'
		. 'var ask = function () { return [image, img, hero, plain].map(function (n) { return _sdCanEditNode(n, "image"); }); };'
		. 'var content = ask();'
		. 'var heroImage = _sdNodeImage(hero) ? _sdNodeImage(hero).url : null;'
		. 'area = true; var inArea = ask(); area = false;'
		. 'level = "full"; var full = ask();'
		. 'console.log(JSON.stringify({ content: content, inArea: inArea, full: full, heroImage: heroImage }));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(array(true, true, false, false), $result['content'], 'content level, outside a marked area: image, <img>, not the background');
	pg_assert_same(array(true, true, true, true), $result['inArea'], 'inside a marked area the whole node is theirs');
	pg_assert_same(array(true, true, true, true), $result['full'], 'a designer');
	pg_assert_same('/hero.jpg', $result['heroImage'], 'the background image is still found for the designer\'s tools');
}
