<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Canva start of the New design window: the pure parts of
 * includes/fn/canva_start.php (a file's kind from its bytes, a page's name,
 * the colours of a picture and of several together), the start's row and
 * pane, and the sketch assets/js/designer_canva.js writes for the new
 * design (run in Node when it is there).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/canva_start.php');
require_once(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');

// The kind is read from the bytes, never from the name.
function test_canva_file_kind()
{
	pg_assert_same('png', pg_canva_file_kind("\x89PNG\r\n\x1a\n\0\0\0\rIHDR"), 'a PNG');
	pg_assert_same('jpg', pg_canva_file_kind("\xFF\xD8\xFF\xE0\0\x10JFIF"), 'a JPG');
	pg_assert_same('pdf', pg_canva_file_kind('%PDF-1.7' . "\n"), 'a PDF');
	pg_assert_same('', pg_canva_file_kind('<?php echo 1;'), 'a script');
	pg_assert_same('', pg_canva_file_kind("\x89PNG"), 'cut short');
	pg_assert_same('', pg_canva_file_kind(''), 'empty');
}

// A PNG or a JPG is taken; a PDF and anything else say why not.
function test_canva_kind_refusal()
{
	pg_assert_same('', pg_canva_kind_refusal('png', 'home.png'), 'a PNG');
	pg_assert_same('', pg_canva_kind_refusal('jpg', 'home.jpg'), 'a JPG');
	pg_assert_true(strpos(pg_canva_kind_refusal('pdf', 'deck.pdf'), 'deck.pdf') !== false, 'a PDF names the file');
	pg_assert_true(strpos(pg_canva_kind_refusal('', 'home.canva'), 'home.canva') !== false, 'another file names the file');
	pg_assert_true(pg_canva_kind_refusal('pdf', 'a.pdf') !== pg_canva_kind_refusal('', 'a.pdf'), 'a PDF has its own reason');
}

function test_canva_page_name()
{
	pg_assert_same('Home page', pg_canva_page_name('Home-page.png'), 'dashes read as spaces');
	pg_assert_same('Ürün listesi 2', pg_canva_page_name('C:/x/Ürün_listesi__2.PNG'), 'a folder and the extension go, letters stay');
	pg_assert_same('', pg_canva_page_name('.png'), 'nothing left');
	pg_assert_same(120, mb_strlen(pg_canva_page_name(str_repeat('a', 200) . '.png'), 'UTF-8'), 'cut to the sketch\'s length');
}

// Greys, white and black are the page's ground and text, not its brand.
function test_canva_colors_from_pixels()
{
	$pixels = array_merge(
		array_fill(0, 500, 0xFFFFFF),
		array_fill(0, 200, 0x111111),
		array_fill(0, 100, 0x808080),
		array_fill(0, 120, 0x2563EB),
		array_fill(0, 60, 0xF59E0B),
		array_fill(0, 20, 0x2563EA)
	);
	$colors = pg_canva_colors_from_pixels($pixels);
	pg_assert_same(2, count($colors), 'two brand colours; the near shades share a box');
	pg_assert_same('#2563eb', $colors[0]['hex'], 'the most used first');
	pg_assert_same(0.14, $colors[0]['w'], 'its share of all the pixels');
	pg_assert_same('#f59e0b', $colors[1]['hex'], 'the next');
	pg_assert_same(array(), pg_canva_colors_from_pixels(array_fill(0, 50, 0xF4F4F4)), 'a grey page gives none');
	pg_assert_same(array(), pg_canva_colors_from_pixels(array()), 'no pixels');
}

// A picture the upload cap shrank on its way in is measured whole: the
// size given is the one before the shrink, the stored file is smaller.
function test_canva_measure_reads_the_stored_size()
{
	if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng')) {
		return;
	}
	$path = @tempnam(sys_get_temp_dir(), 'pgcvm');
	$img = imagecreatetruecolor(128, 64);
	imagefilledrectangle($img, 0, 0, 63, 63, 0x2563EB);
	imagefilledrectangle($img, 64, 0, 127, 63, 0xF59E0B);
	imagepng($img, $path);
	pg_image_destroy($img);
	$colors = pg_canva_measure($path, 'png', 256, 128);
	@unlink($path);
	pg_assert_same(2, count($colors), 'both halves are measured');
	pg_assert_true($colors[0]['w'] > 0.45 && $colors[1]['w'] > 0.45, 'each half is half of the picture');
}

// Every picture counts the same; close colours join the more used one.
function test_canva_pick_colors()
{
	$lists = array(
		array(array('hex' => '#2563eb', 'w' => 0.3), array('hex' => '#f59e0b', 'w' => 0.1)),
		array(array('hex' => '#2a66ee', 'w' => 0.2), array('hex' => '#059669', 'w' => 0.15)),
		array(array('hex' => '#e11d48', 'w' => 0.05), array('hex' => 'red', 'w' => 0.9), 'x', array('hex' => '#7c3aed', 'w' => 0)),
	);
	pg_assert_same(array('#2563eb', '#059669', '#f59e0b', '#e11d48'), pg_canva_pick_colors($lists, 5), 'the blues are one; bad entries are skipped');
	pg_assert_same(array('#2563eb', '#059669'), pg_canva_pick_colors($lists, 2), 'at most the number asked');
	pg_assert_same(array(), pg_canva_pick_colors('x', 5), 'not a list');
}

// The row sits after Template (and Figma) and before ZIP file; its pane
// is drawn by its own part, with the sentence that the pictures are a
// model to build on.
function test_canva_start_row_and_pane()
{
	$kinds = pg_designer_start_kinds(true, true);
	$keys = array_map(function ($k) { return $k['key']; }, $kinds);
	pg_assert_same(array('blank', 'template', 'figma', 'canva', 'document', 'import', 'paste'), $keys, 'the plan\'s order');
	pg_assert_same('add_system_style.php?framework=bootstrap5&start=canva', pg_designer_start_go_href('canva', 'bootstrap5', false), 'opens a Bootstrap 5 design in the Sketch tool');

	$html = pg_designer_start_window(array(), false);
	pg_assert_true(preg_match('/id="sd-ns-pane-canva"[^>]*data-part="canva" data-part-stamp="[^"]*"/', $html) === 1, 'the pane loads its part');
	pg_assert_true(strpos($html, 'class="sd-cv"') !== false, 'the drop place');
	pg_assert_true(strpos($html, 'not layers you can edit') !== false, 'the one sentence');
	pg_assert_true(strpos($html, 'type="file"') !== false && strpos($html, ' multiple') !== false, 'several files at once');
	pg_assert_same('', pg_designer_start_part_attrs('blank'), 'a start without a part');
}

// The sketch the part writes: a frame a page in their order, each with its
// picture, the height in frame units kept within the sketch's limits.
function test_canva_sketch_of_pages()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return;
	}
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/designer_canva.js');
	$script = "var window = {};\n" . $js . "\nvar c = window.sdStartParts.canva;\n"
		. "console.log(JSON.stringify({\n"
		. " sketch: c.sketchOf([{ name: 'Home', src: '/home.png', width: 1920, height: 1080 }, { name: 'Long', src: '/long.png', width: 10, height: 1000000 }]),\n"
		. " refusals: [c.refusalOf({ name: 'a.PNG', type: '', size: 5 }, { bytes: 10 }), c.refusalOf({ name: 'a.pdf', type: 'application/pdf', size: 5 }, { bytes: 10 }),\n"
		. "  c.refusalOf({ name: 'a.canva', type: '', size: 5 }, { bytes: 10 }), c.refusalOf({ name: 'a.jpg', type: 'image/jpeg', size: 50 }, { bytes: 10 })]\n"
		. "}));\n";
	$file = tempnam(sys_get_temp_dir(), 'pgcv') . '.js';
	file_put_contents($file, $script);
	$lines = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);
	pg_assert_same(0, $code, 'node ran: ' . implode("\n", $lines));
	$r = json_decode(implode("\n", $lines), true);

	pg_assert_same(3, $r['sketch']['n'], 'the next id');
	pg_assert_same(array(1, 2), array_column($r['sketch']['pages'], 'id'), 'one frame a page, in order');
	pg_assert_same(array('src' => '/home.png', 'h' => 270, 'off' => false), $r['sketch']['pages'][0]['pic'], '480 wide: 1920 × 1080 is 270 high');
	pg_assert_same(20000, $r['sketch']['pages'][1]['pic']['h'], 'a very tall picture is held to the sketch\'s limit');
	pg_assert_true($r['sketch']['pages'][1]['x'] > $r['sketch']['pages'][0]['x'], 'side by side');
	pg_assert_same(array('', 'pdf', 'notPng', 'tooBig'), $r['refusals'], 'what the browser refuses before sending');
}

// Files chosen or dropped together come in by their names' number order,
// as Canva numbers a design's pages (1.png, 2.png, …, 10.png), not in the
// order the browser lists them; equal names keep their order.
function test_canva_files_in_page_order()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return;
	}
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/designer_canva.js');
	$script = "var window = {};\n" . $js . "\nvar c = window.sdStartParts.canva;\n"
		. "var names = function (list) { return c.inPageOrder(list.map(function (n, i) { return { name: n, i: i }; })).map(function (f) { return f.name + '#' + f.i; }); };\n"
		. "console.log(JSON.stringify([\n"
		. " names(['1.png', '10.png', '2.png', '3.png']),\n"
		. " names(['Page 10 - Home.png', 'page 2 - About.png', 'Page 1 - Shop.png']),\n"
		. " names(['b.png', 'a.png', 'a.png'])\n"
		. "]));\n";
	$file = tempnam(sys_get_temp_dir(), 'pgcv') . '.js';
	file_put_contents($file, $script);
	$lines = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);
	pg_assert_same(0, $code, 'node ran: ' . implode("\n", $lines));
	$r = json_decode(implode("\n", $lines), true);

	pg_assert_same(array('1.png#0', '2.png#2', '3.png#3', '10.png#1'), $r[0], 'Canva\'s numbered pages, 10 after 9');
	pg_assert_same(array('Page 1 - Shop.png#2', 'page 2 - About.png#1', 'Page 10 - Home.png#0'), $r[1], 'numbers inside a name, any case');
	pg_assert_same(array('a.png#1', 'a.png#2', 'b.png#0'), $r[2], 'equal names keep their order');
}

// A drop deletes only ids this start stored in this session: an id asked
// for that the session did not store is never deleted.
function test_canva_drop_ids()
{
	pg_assert_same(array(4, 9), pg_canva_drop_ids(array(4, '9', 12, 4, 0, -3, 'x', array(1)), array(9, 4, 5)), 'only stored ids, each once');
	pg_assert_same(array(), pg_canva_drop_ids(array(4), array()), 'nothing stored');
	pg_assert_same(array(), pg_canva_drop_ids('4', array(4)), 'a list only');
}

// The bin, the cross on an upload that reached the server, Cancel and the
// window's close drop the start's pages; Create design keeps them. A source
// check: the window is a browser.
function test_canva_abandoned_start_drops()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/designer_canva.js');
	pg_assert_contains("sub_action: 'canva_drop'", $js);
	pg_assert_contains("pgUI.dialog.on(win, 'hidden'", $js, 'the window\'s close is heard');
	pg_assert_true(substr_count($js, 'drop(s, ') >= 3, 'the bin, the cross and the close drop pages');
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');
	pg_assert_contains("case 'canva_drop':", $php);
}
