<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Video item of the Visual Page Editor's palette: the page's
 * markup (_render_content_html() 'video'), the address rule
 * (pg_designer_media_url()), the editor's twin (CONTENT.video,
 * _sdMediaUrl(), run under Node) and the full-screen hero that lays a video
 * under its content (pg-media-cover).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_video_item_test_source()
{
	static $src = null;
	if ($src === null) {
		$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	}
	return $src;
}

function designer_video_item_test_page($props)
{
	$node = array('type' => 'content', 'props' => array_merge(array('contentType' => 'video'), $props), 'children' => array());
	return trim(preg_replace('/>\s+</', '><', (string) _render_tree_node($node, 0)));
}

// The source of one declaration of the designer, braces matched; '' when
// it is not found.
function designer_video_item_test_cut($source, $start)
{
	$from = strpos($source, $start);
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

// Runs $body under Node after the designer's attribute helpers and its
// Video element; $body fills `out`. null without Node.
function designer_video_item_test_js($body)
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
	$source = designer_video_item_test_source();
	$code_js = '';
	foreach (array('function _sdMediaUrl(', 'function _sdCssLen(', 'function _sdImgDim(', 'function _sdImgSizeCss(', 'function _sdImgInRatio(', 'function _sdImgRatioClass(', 'function _sdSizingValue(', 'function getNodeExtraStyle(', 'function getNodeExtraClasses(', 'var _SD_CONTENT_GLOBAL_ATTRS', 'function _sdContentSizingNode(', 'function _sdContentProps(', 'function _sdUniqueClasses(', 'function _sdDecodeAttr(', 'function _sdUserAttrValue(', 'function _sdFontFamilyCss(', 'function _sdNodeStyle(', 'function _sdNodeTitleAttrs(', 'function _sdFxSpec(', 'function _sdFxNormalize(', 'function _sdFxAttrs(', 'function _sdNodeExtraAttrs(', 'function addGlobalAttrs(') as $start) {
		$found = designer_video_item_test_cut($source, $start);
		pg_assert_true($found !== '', $start . ' is in the designer');
		$code_js .= $found . "\n";
	}
	$video = designer_video_item_test_cut($source, "        video: {\n            label: _sdT('Video')");
	pg_assert_true($video !== '', 'the Video element is in the designer');
	$script = 'function _sdT(s) { return s; }'
		. 'function esc(s) { return s ? String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;") : ""; }'
		. $code_js
		. 'var CONTENT = {' . $video . '};'
		. 'var out = {};'
		. $body
		. 'console.log(JSON.stringify(out));';
	$file = @tempnam(sys_get_temp_dir(), 'pgsdvideo');
	if ($file === false) {
		return null;
	}
	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	if ($code !== 0) {
		return array('error' => implode("\n", $out));
	}
	return json_decode(implode("\n", $out), true);
}

// Attribute order means nothing to a browser.
function designer_video_item_test_canon($html)
{
	return preg_replace_callback('/<([a-z][a-z0-9]*)((?:\s+[^\s=>]+(?:="[^"]*")?)*)\s*>/i', function ($m) {
		preg_match_all('/\s+([^\s=>]+)(?:="([^"]*)")?/', $m[2], $attrs, PREG_SET_ORDER);
		$list = array();
		foreach ($attrs as $a) {
			$list[] = ' ' . $a[1] . (isset($a[2]) ? '="' . $a[2] . '"' : '');
		}
		sort($list);
		return '<' . $m[1] . implode('', $list) . '>';
	}, trim(preg_replace('/>\s+</', '><', (string) $html)));
}

function designer_video_item_test_cases()
{
	return array(
		'plain'          => array('src' => '/files/intro.mp4', 'cssClass' => 'd-block w-100 h-auto'),
		'poster'         => array('src' => '/files/intro.mp4', 'poster' => '/files/intro.jpg', 'cssClass' => 'd-block w-100 h-auto'),
		'escaped'        => array('src' => '/files/a "b" <c>.mp4?x=1&y=2', 'poster' => 'https://example.com/p.jpg?a=1&b=2'),
		'bad poster'     => array('src' => 'https://example.com/v.webm', 'poster' => 'javascript:alert(1)'),
		'no class'       => array('src' => 'v.mp4'),
		'attrs'          => array('src' => 'v.mp4', 'id' => 'film', '_attrs' => array(array('name' => 'autoplay', 'value' => ''), array('name' => 'muted', 'value' => ''), array('name' => 'aria-label', 'value' => 'Our film'))),
		'empty'          => array('src' => ''),
		'javascript src' => array('src' => " JaVa\tScRiPt:alert(1)"),
		'data src'       => array('src' => 'data:video/mp4;base64,AAAA'),
	);
}

function test_designer_video_item_page_markup()
{
	$html = designer_video_item_test_page(array('src' => '/files/intro.mp4', 'poster' => '/files/intro.jpg', 'cssClass' => 'd-block w-100 h-auto'));
	pg_assert_contains('<video src="/files/intro.mp4" poster="/files/intro.jpg" controls preload="metadata" playsinline class="d-block w-100 h-auto">', $html, 'a player with its controls and poster');
	pg_assert_contains('</video>', $html, 'closed');
	pg_assert_false(stripos($html, 'autoplay') !== false, 'no autoplay');

	$attrs = designer_video_item_test_page(array('src' => 'v.mp4', '_attrs' => array(array('name' => 'autoplay', 'value' => ''), array('name' => 'muted', 'value' => ''))));
	pg_assert_false(stripos($attrs, 'autoplay') !== false, 'an autoplay attribute from the Attributes panel is not written');
	pg_assert_contains('muted', $attrs, 'other attributes stay');

	$esc = designer_video_item_test_page(array('src' => '/files/a "b" <c>.mp4?x=1&y=2'));
	pg_assert_contains('src="/files/a &quot;b&quot; &lt;c&gt;.mp4?x=1&amp;y=2"', $esc, 'the address is escaped');
}

function test_designer_video_item_address_rule()
{
	pg_assert_same('/files/a.mp4', pg_designer_media_url(" /files/a.mp4\n"), 'trimmed');
	pg_assert_same('https://example.com/a.mp4', pg_designer_media_url('https://example.com/a.mp4'), 'a web address');
	foreach (array('javascript:alert(1)', ' JAVASCRIPT:x', "java\tscript:x", "java\nscript:x", 'vbscript:x', 'livescript:x', 'data:video/mp4;base64,AAAA', "\x01javascript:x") as $bad) {
		pg_assert_same('', pg_designer_media_url($bad), 'refused: ' . json_encode($bad));
	}
	pg_assert_same('', pg_designer_media_url(array('x')), 'not text');

	pg_assert_same('', designer_video_item_test_page(array('src' => '')), 'no address: no player');
	pg_assert_same('', designer_video_item_test_page(array('src' => 'javascript:alert(1)')), 'a script address: no player');
	$bad_poster = designer_video_item_test_page(array('src' => 'v.mp4', 'poster' => 'javascript:alert(1)'));
	pg_assert_false(strpos($bad_poster, 'poster') !== false, 'a script poster is left out');
}

function test_designer_video_item_editor_matches_page()
{
	$cases = designer_video_item_test_cases();
	$r = designer_video_item_test_js(
		'var cases = ' . json_encode($cases) . ';'
		. 'Object.keys(cases).forEach(function (k) {'
		. '  var p = Object.assign({ contentType: "video" }, cases[k]);'
		. '  var node = { type: "content", props: p };'
		. '  var P = _sdContentProps(node, p);'
		. '  out[k] = addGlobalAttrs(CONTENT.video.toHTML(P, ""), node, P);'
		. '});'
		. 'out.urls = [" /a.mp4\n", "java\tscript:x", "DATA:x", "https://x/y.mp4", "\u0001javascript:x"].map(_sdMediaUrl);'
		. 'out.defaults = CONTENT.video.defaultProps;'
	);
	if ($r === null) {
		pg_assert_true(true, 'Node is not installed: the twin is checked by the page side only');
		return;
	}
	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	foreach ($cases as $label => $props) {
		pg_assert_same(designer_video_item_test_canon(designer_video_item_test_page($props)), designer_video_item_test_canon($r[$label]), $label);
	}
	$php_urls = array_map('pg_designer_media_url', array(" /a.mp4\n", "java\tscript:x", 'DATA:x', 'https://x/y.mp4', "\x01javascript:x"));
	pg_assert_same($php_urls, $r['urls'], 'the address rule is the same on both sides');
	pg_assert_same(array('src' => '', 'poster' => '', 'cssClass' => 'd-block w-100 h-auto'), $r['defaults'], 'no autoplay, loop or muted prop');
}

function test_designer_video_item_palette_and_canvas()
{
	$js = designer_video_item_test_source();
	pg_assert_contains("var voidContentTypes = ['image', 'video',", $js, 'a video takes no children');
	pg_assert_contains("'t:video':            { h: '<video>'", $js, 'the Components tip card');
	pg_assert_contains("window.sdOpenFilePicker('sd-prop-video-src', 'src')", $js, 'double-click opens the File Manager');
	pg_assert_contains("onclick=\"window.sdOpenImagePicker(\\'sd-prop-video-poster\\')\"", $js, 'the poster takes the image picker');
	pg_assert_contains("ph.className = 'sd-img-placeholder sd-video-placeholder';", $js, 'the canvas draws a placeholder without an address');
}

// The full-screen hero lays a dropped video under its overlay: the band
// carries pg-media-cover, its rule covers the band with the video behind
// the overlay, and the rule only lets clicks through when a video is there.
function test_designer_video_item_hero_cover()
{
	$js = designer_video_item_test_source();
	pg_assert_contains("cssClass: 'd-flex overflow-hidden pg-screen pg-bleed pg-media-cover', customName: _sdT('Hero (Full-Screen Media)')", $js, 'the hero band carries the class');
	pg_assert_contains("'spotlight-media':      ['pg-screen', 'pg-bleed', 'pg-shade-bottom', 'pg-shade-tint', 'pg-display-xxl', 'pg-media-cover']", $js, 'the hero brings the rule');
	$source = _pg_designer_block_css_source();
	$rule = isset($source['rules']['pg-media-cover']) ? $source['rules']['pg-media-cover'] : '';
	pg_assert_contains('.pg-media-cover { position: relative; isolation: isolate; }', $rule, 'the band is the video\'s box and its own stacking context');
	pg_assert_contains('.pg-media-cover video { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; z-index: -1; }', $rule, 'the video covers the band, under the overlay');
	pg_assert_contains('.pg-media-cover:has(video) .pg-shade-bottom { pointer-events: none; }', $rule, 'a hero without a video keeps its overlay as it was');
	pg_assert_false(strpos($rule, ' > ') !== false, 'descendant selectors only: the canvas wraps every node');
	pg_assert_contains(".pg-media-cover .sd-wrap[data-content-type=\"video\"]:has(> video) { position:static !important;", $js, 'on the canvas the wrapper is not the video\'s box');
}
