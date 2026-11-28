<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the document bar's Picture button (renderDocument() in
 * assets/js/pg_office_view.js): a picture's kind by its first bytes, the
 * refusals and limits it shares with the writer (office_docx_picture.php),
 * the picker kind of the file window and the parts a unit test cannot press.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs a Node script after the view's source; its JSON output, or null when
// Node cannot run.
function office_docx_picture_bar_test_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgpb');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, "var window = {};\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js')
		. "\nvar P = window.pgOfficeView.docPicture;\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function test_office_docx_picture_bar_kinds_and_refusals()
{
	$result = office_docx_picture_bar_test_node(<<<'JS'
var M = 1024 * 1024;
console.log(JSON.stringify({
    png: P.pictureType([0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A, 0]),
    jpeg: P.pictureType(new Uint8Array([0xFF, 0xD8, 0xFF, 0xE0])),
    gif87: P.pictureType([0x47, 0x49, 0x46, 0x38, 0x37, 0x61]),
    gif89: P.pictureType([0x47, 0x49, 0x46, 0x38, 0x39, 0x61]),
    gifBad: P.pictureType([0x47, 0x49, 0x46, 0x38, 0x38, 0x61]),
    webp: P.pictureType([0x52, 0x49, 0x46, 0x46, 0, 0, 0, 0]),
    svg: P.pictureType([0x3C, 0x73, 0x76, 0x67]),
    short: P.pictureType([0x89, 0x50]),
    none: P.pictureType(null),
    refusals: [
        P.pictureRefusal('', 10, 0),
        P.pictureRefusal('png', 2 * M, 0),
        P.pictureRefusal('png', 2 * M + 1, 0),
        P.pictureRefusal('jpeg', 2 * M, 6 * M),
        P.pictureRefusal('gif', 1, 8 * M - 1),
        P.pictureRefusal('gif', 2, 8 * M - 1)
    ],
    limits: [P.limits.one, P.limits.all]
}));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same('png', $result['png']);
	pg_assert_same('jpeg', $result['jpeg']);
	pg_assert_same('gif', $result['gif87']);
	pg_assert_same('gif', $result['gif89']);
	foreach (array('gifBad', 'webp', 'svg', 'short', 'none') as $key) {
		pg_assert_same('', $result[$key], $key . ' is no picture the writer takes');
	}

	pg_assert_same(array('doc_picture_type', '', 'doc_picture_large', '', '', 'doc_picture_room'), $result['refusals'], 'type, one picture\'s and all pictures\' limits');

	require_once PG_FUNCTIONS_DIR . '/includes/fn/office.php';
	pg_assert_same(array(PG_OFFICE_MAX_IMAGE, PG_OFFICE_MAX_IMAGES), $result['limits'], 'the editor\'s picture limits are the writer\'s');
}

function test_office_docx_picture_bar_picker_kind()
{
	require_once PG_FUNCTIONS_DIR . '/includes/fn/picker.php';

	$kinds = pg_picker_kinds();

	pg_assert_true(isset($kinds['office_picture']), 'the file window picks from its own kind');
	pg_assert_same(array('png', 'jpg', 'jpeg', 'gif'), $kinds['office_picture']['types'], 'only the types the writer takes');
	pg_assert_true($kinds['office_picture']['pictures'] && $kinds['office_picture']['upload'], 'drawn as pictures, with Upload');
	pg_assert_same('ok', pg_picker_access_verdict('office_picture', 3, true), 'a User who may edit files picks');
}

function test_office_docx_picture_bar_source()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js');
	$php = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/office.php');

	foreach (array('doc_picture', 'doc_picture_choose', 'doc_picture_loading', 'doc_picture_none', 'doc_picture_upload', 'doc_picture_failed', 'doc_picture_inside') as $key) {
		pg_assert_true(strpos($source, 'strings.' . $key) !== false, 'the bar uses ' . $key);
		pg_assert_true(strpos($php, "'" . $key . "'") !== false, 'the strings hold ' . $key);
	}

	foreach (array('doc_picture_type', 'doc_picture_large', 'doc_picture_room') as $key) {
		pg_assert_true(strpos($php, "'" . $key . "'") !== false, 'the strings hold ' . $key);
	}

	pg_assert_contains("picture: { data: picture.data, name: picture.name }", $source, 'a save sends the writer\'s picture entry');
	pg_assert_contains("'.office-added, .office-added-table, .office-added-picture'", $source, 'Undo keeps pictures put in');
	pg_assert_contains("kind: 'office_picture'", file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php'), 'the file window opens its picker');
	pg_assert_contains("api('ws_channel_files', { channel_id: data.channel_id })", file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js'), 'a channel lists its pictures');
	pg_assert_contains("\$answer['channel_id'] = (int) \$channel['id'];", file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php'), 'the preview answer names its channel');
}
