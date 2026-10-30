<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the File Manager actions that write a new copy on every
 * run. "Save a copy as PNG/JPG/WebP" on an SVG posts to
 * image_editor_save.php in copy mode, which always writes a new file and
 * row; its buttons are type=button, so the window guard never saw them and
 * a double click saved two copies. Import ZIP (its own screen and the File
 * Manager dialog) and Import ZIP for the Visual Page Editor upload, unzip
 * and write rows before they answer, often longer than the window guard's
 * 3 seconds; a later press imported the whole archive a second time.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function file_copies_send_once_source($file)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $file);
}

// The text of a JavaScript function from its header to the next function
// header at the same indentation.
function file_copies_send_once_function($src, $header)
{
	$start = strpos($src, $header);
	pg_assert_true($start !== false, $header . ' is there');
	$end = strpos($src, "\n    function ", (int) $start + strlen($header));
	return (string) substr($src, (int) $start, ($end === false) ? null : $end - $start);
}

// One conversion at a time: the flag is checked first, the three buttons are
// disabled while it runs and enabled again on every outcome.
function test_rasterize_svg_runs_once()
{
	$src = file_copies_send_once_source('view_folders.php');
	$fn = file_copies_send_once_function($src, 'function rasterizeSvg(file, type) {');

	pg_assert_contains('var rasterizing = false;', $src, 'a module-level flag');
	pg_assert_contains('if (rasterizing) { return; }', $fn, 'a run in progress turns a press away');
	pg_assert_contains('setBusy(true);', $fn, 'the flag is set');
	pg_assert_contains('rasterizing = busy;', $fn, 'the flag follows the busy state');
	pg_assert_contains('[data-file-tool="rasterize"]', $fn, 'all three buttons are reached');
	pg_assert_contains('button.disabled = busy;', $fn, 'the buttons follow the flag');
	pg_assert_false(strpos($fn, 'spinner-border') !== false, 'the labels stay as they are');

	// Every outcome ends the run: the answer (refusal or success), a fetch
	// failure, an image that does not load, a canvas that cannot export.
	pg_assert_true(substr_count($fn, 'settle();') >= 4, 'every outcome clears the flag');
	pg_assert_contains('picture.onerror = function () { settle();', $fn, 'a picture that does not load ends the run');
	pg_assert_contains('catch (e) { settle();', $fn, 'a canvas that cannot export ends the run');
	pg_assert_contains('.catch(function () { settle();', $fn, 'a network failure ends the run');
}

// The File Manager's two ZIP dialogs send once and say the import runs.
function test_file_manager_zip_dialogs_send_once()
{
	$src = file_copies_send_once_source('view_folders.php');

	foreach (array('import_zip', 'import_design_zip') as $id) {
		pg_assert_contains('<form id="' . $id . '_form" ', $src, $id . ' form has an id');
		pg_assert_contains('id="' . $id . '_status" role="status" aria-live="polite"', $src, $id . ' has a status line');
	}

	pg_assert_contains("['import_zip', 'import_design_zip'].forEach(", $src, 'both dialogs are guarded');
	pg_assert_contains('window.addEventListener(\'submit\', function (event) {', $src, 'the listener sits on window');
	pg_assert_contains('if (sent) { event.preventDefault(); return; }', $src, 'a later submit is cancelled');
	pg_assert_contains('window.addEventListener(\'pageshow\', function (event) {', $src, 'a restored page resets it');
	pg_assert_contains("form.setAttribute('aria-busy', 'true');", $src, 'the form is busy while the import runs');
	pg_assert_contains("encode_json(lang('Importing…'))", $src, 'the status line says the import is running');
}

// The standalone Import ZIP screen does the same.
function test_import_zip_screen_sends_once()
{
	$src = file_copies_send_once_source('import_zip.php');

	pg_assert_contains('<form name="form" id="import_zip_form" ', $src, 'the form has an id');
	pg_assert_contains('id="import_zip_status" role="status" aria-live="polite"', $src, 'a status line is there');
	pg_assert_contains('window.addEventListener("submit", function (event) {', $src, 'the listener sits on window');
	pg_assert_contains('if (import_zip_sent) { event.preventDefault(); return; }', $src, 'a later submit is cancelled');
	pg_assert_contains('window.addEventListener("pageshow", function (event) {', $src, 'a restored page resets it');
	pg_assert_contains('form.setAttribute("aria-busy", "true");', $src, 'the form is busy while the import runs');
	pg_assert_contains("escape_javascript(lang('Importing…'))", $src, 'the status line says the import is running');
}
