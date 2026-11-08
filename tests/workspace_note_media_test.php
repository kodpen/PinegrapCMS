<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a file of this site shown in a note
 * (includes/workspace/note_media.php): which lines link only to a file of
 * this site, how a picture, a player and a file card are drawn, and that a
 * file the site does not hold stays a link.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/render.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/files.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/note_media.php');

function test_workspace_note_media_line_own_file()
{
	pg_assert_same(
		array('label' => 'Plan.pdf', 'url' => 'https://example.com/plan.pdf', 'name' => 'plan.pdf'),
		ws_note_media_line('[Plan.pdf](https://example.com/plan.pdf)', 'example.com/')
	);
	pg_assert_same('my file.png', ws_note_media_line('  [Photo](http://Example.com/shop/my%20file.png)  ', 'example.com/shop/')['name'], 'either scheme, host case, path, decoded');
}

function test_workspace_note_media_line_not_a_file()
{
	$lines = array(
		'[x](https://other.com/plan.pdf)'           => 'another host',
		'[x](https://example.com.evil.com/a.pdf)'   => 'a host that only starts the same',
		'[x](https://example.com/folder/a.pdf)'     => 'a folder',
		'[x](https://example.com/a.pdf?download=1)' => 'a query',
		'[x](https://example.com/about)'            => 'a page, no extension',
		'[x](https://example.com/.htaccess)'        => 'a hidden name',
		'[x](https://example.com/a%2Fb.pdf)'        => 'an encoded slash',
		'See [x](https://example.com/a.pdf)'        => 'words before the link',
		'[x](https://example.com/a.pdf) here'       => 'words after the link',
		'- [x](https://example.com/a.pdf)'          => 'a list item',
		'https://example.com/a.pdf'                 => 'a bare address',
	);

	foreach ($lines as $line => $why) {
		pg_assert_same(null, ws_note_media_line($line, 'example.com/'), $why);
	}

	pg_assert_same(null, ws_note_media_line('[x](https://example.com/a.pdf)', ''), 'no site address known');
}

function test_workspace_note_media_html_kinds()
{
	$image = ws_note_media_html(array('label' => 'Cat', 'url' => 'https://example.com/cat.jpg', 'name' => 'cat.jpg'), 2048);
	pg_assert_true(strpos($image, '<img src="https://example.com/cat.jpg" alt="Cat" loading="lazy"') !== false, 'a lazy picture');
	pg_assert_true(strpos($image, 'data-ws-media="image"') !== false, 'the viewer hook');

	$video = ws_note_media_html(array('label' => 'Clip', 'url' => 'https://example.com/a.mp4', 'name' => 'a.mp4'), 1);
	pg_assert_true(strpos($video, '<video src="https://example.com/a.mp4" controls preload="metadata"') !== false, 'a video player');

	$audio = ws_note_media_html(array('label' => 'Talk', 'url' => 'https://example.com/a.mp3', 'name' => 'a.mp3'), 1);
	pg_assert_true(strpos($audio, '<audio src="https://example.com/a.mp3" controls') !== false, 'a sound player');

	$pdf = ws_note_media_html(array('label' => 'Plan', 'url' => 'https://example.com/plan.pdf', 'name' => 'plan.pdf'), 500);
	pg_assert_true(strpos($pdf, 'bi-file-earmark-pdf') !== false, 'the PDF icon');
	pg_assert_true(strpos($pdf, '<span class="ws-media-name">Plan</span>') !== false, 'the name');
	pg_assert_true(strpos($pdf, '<span class="ws-media-size">500 B</span>') !== false, 'the size');
	pg_assert_true(strpos($pdf, 'data-ws-media="pdf"') !== false, 'the preview hook');

	$zip = ws_note_media_html(array('label' => 'All', 'url' => 'https://example.com/all.zip', 'name' => 'all.zip'), 1);
	pg_assert_true(strpos($zip, 'bi-file-earmark-zip') !== false && strpos($zip, 'data-ws-media="file"') !== false, 'any other file a card');
}

function test_workspace_note_media_html_escapes()
{
	$html = ws_note_media_html(array('label' => '"><script>x</script>', 'url' => 'https://example.com/a.png?"x', 'name' => 'a.png'), 1);

	pg_assert_true(strpos($html, '<script>') === false, 'the label escaped');
	pg_assert_true(strpos($html, '?"x') === false, 'the address escaped');
}

function test_workspace_note_media_blocks_only_existing_files()
{
	ws_note_media_files(array(), array('cat.jpg' => 2048));

	$blocks = ws_note_media_blocks(array(
		'Words',
		'[Cat](https://example.com/cat.jpg)',
		'[Gone](https://example.com/gone.jpg)',
		'[Other](https://other.com/cat.jpg)',
	), 'example.com/');

	pg_assert_same(array(1), array_keys($blocks), 'only the file the site holds');
	pg_assert_true(strpos($blocks[1], 'ws-media-image') !== false, 'drawn as a picture');

	ws_note_media_files(array(), array());
}
