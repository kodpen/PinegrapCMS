<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a picture which fails to load on the designer canvas takes the
 * page's no-image picture, as the published page does, instead of the
 * browser's broken glyph drawn as an empty strip one line high.
 * _sdImgFallback() is cut out of assets/js/style_designer.src.js and run under
 * Node with a stand-in element (skipped on a machine without Node).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_canvas_image_fallback_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

function designer_canvas_image_fallback_test_function($source, $name)
{
	$from = strpos($source, '    function ' . $name . '(');
	pg_assert_true($from !== false, 'the designer has ' . $name . '()');
	$to = strpos($source, "\n    function ", $from + 10);
	pg_assert_true($to !== false, $name . '() has an end');

	return substr($source, $from, $to - $from);
}

// One error swaps in the fallback and marks the picture; a second error (the
// fallback failing too) changes nothing; no fallback address, no swap.
function test_designer_canvas_image_fallback_swaps_once()
{
	$code = 1;
	$out = array();
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return;
	}

	$script = designer_canvas_image_fallback_test_function(designer_canvas_image_fallback_test_source(), '_sdImgFallback') . '
function fake() {
    var a = { src: "https://example.com/a.jpg", srcset: "a.jpg 1x" }, h = [];
    return {
        a: a, sets: 0,
        addEventListener: function (t, f) { if (t === "error") h.push(f); },
        removeAttribute: function (n) { delete a[n]; },
        setAttribute: function (n, v) { a[n] = v; if (n === "src") this.sets++; },
        fail: function () { h.forEach(function (f) { f(); }); }
    };
}
var i = fake();
_sdImgFallback(i, "/software/assets/images/no-image.svg");
i.fail(); i.fail();
var j = fake();
_sdImgFallback(j, "");
j.fail();
console.log(JSON.stringify([i.a.src, i.sets, "srcset" in i.a, "data-sd-img-failed" in i.a, j.a.src, j.sets]));';
	$file = tempnam(sys_get_temp_dir(), 'pgimgfb');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));

	pg_assert_same(array('/software/assets/images/no-image.svg', 1, false, true, 'https://example.com/a.jpg', 0), json_decode(implode("\n", $out), true));
}

// The image content renderer arms the fallback before it sets the address,
// with the page's own no-image picture.
function test_designer_canvas_image_render_uses_the_fallback()
{
	$source = designer_canvas_image_fallback_test_source();

	pg_assert_contains("inner = doc.createElement('img');\n                    _sdImgFallback(inner, _sdNoImageUrl());\n                    inner.src = p.src;", $source);
	pg_assert_contains("'/assets/images/no-image.svg'", designer_canvas_image_fallback_test_function($source, '_sdNoImageUrl'));
}
