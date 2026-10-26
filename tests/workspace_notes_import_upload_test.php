<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how the notes import takes its upload
 * (includes/workspace/notes_import.php, workspace_notes_import.php and
 * openImport() in assets/js/workspace.js): the cap fits one request, the
 * kinds of file are one list the screen and the stage both read, a file PHP
 * did not take in whole is named, the session is let go before the files are
 * read, and the screen shows the file manager's steps with texts that exist.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_import.php');

// The part of a source file from $start up to $end (or the end of the file).
function pg_test_notes_upload_between($source, $start, $end)
{
	$from = strpos($source, $start);

	if ($from === false) {
		return '';
	}

	$to = strpos($source, $end, $from + strlen($start));

	return ($to === false) ? substr($source, $from) : substr($source, $from, $to - $from);
}

// openImport() in workspace.js.
function pg_test_notes_upload_screen()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	return pg_test_notes_upload_between($script, 'function openImport()', 'function attach(files)');
}

// key => English text of every entry of a strings function, read from its
// source: lang('…') and ws_js_template('…', n) alike.
function pg_test_notes_upload_texts($file, $function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/' . $file);
	$body = pg_test_notes_upload_between($source, 'function ' . $function . '()', "\n}\n");
	$texts = array();

	preg_match_all("/'([a-z0-9_]+)'\\s*=>\\s*(?:lang|ws_js_template)\\('((?:[^'\\\\]|\\\\.)*)'/", $body, $matches, PREG_SET_ORDER);

	foreach ($matches as $match) {
		$texts[$match[1]] = str_replace(array("\\'", '\\\\'), array("'", '\\'), $match[2]);
	}

	return $texts;
}

// Loose files go in one request, so the cap is the smallest of the import's
// own, one file's and the request's room; a limit the server does not set
// leaves the import's own.
function test_workspace_notes_import_upload_max_fits_one_request()
{
	$mb = 1048576;

	pg_assert_same(64 * $mb - 65536, ws_note_import_upload_max(array('file_max' => 64 * $mb, 'request_max' => 64 * $mb - 65536)), 'post_max_size equal to upload_max_filesize');
	pg_assert_same(2 * $mb, ws_note_import_upload_max(array('file_max' => 2 * $mb, 'request_max' => 8 * $mb - 65536)), 'PHP defaults');
	pg_assert_same(WS_NOTE_IMPORT_UPLOAD_MAX, ws_note_import_upload_max(array('file_max' => 0, 'request_max' => 0)), 'no limit set');
	pg_assert_same(WS_NOTE_IMPORT_UPLOAD_MAX, ws_note_import_upload_max(array('file_max' => 500 * $mb, 'request_max' => 500 * $mb)), 'limits above the import');
}

// add_file.php's sentences: each names the file, and they differ.
function test_workspace_notes_import_upload_error_names_the_file()
{
	pg_assert_same('', ws_note_import_upload_error('Toplantı.md', UPLOAD_ERR_OK, 2097152), 'nothing to say');

	$sentences = array();

	foreach (array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_PARTIAL, UPLOAD_ERR_CANT_WRITE) as $error) {
		$sentence = ws_note_import_upload_error('Toplantı.md', $error, 2097152);

		pg_assert_contains('Toplantı.md', $sentence, 'error ' . $error);
		$sentences[] = $sentence;
	}

	pg_assert_same(3, count(array_unique($sentences)), 'one sentence a kind');
	pg_assert_contains('2', $sentences[0], 'the size names the server limit');
	pg_assert_same(ws_note_import_upload_error('a.md', UPLOAD_ERR_INI_SIZE, 2097152), ws_note_import_upload_error('a.md', UPLOAD_ERR_FORM_SIZE, 2097152), 'the form limit reads as the server one');
	pg_assert_same(ws_note_import_upload_error('a.md', UPLOAD_ERR_CANT_WRITE, 0), ws_note_import_upload_error('a.md', UPLOAD_ERR_NO_TMP_DIR, 0), 'server faults read alike');
}

// The stage refuses a file PHP did not take in whole with that sentence,
// not with a nameless one.
function test_workspace_notes_import_upload_stage_names_the_file()
{
	$viewer = array('id' => 7, 'role' => 0, 'member' => true);
	$part = ws_note_import_stage($viewer, array(array('name' => 'Toplantı.md', 'tmp_name' => '', 'size' => 10, 'error' => UPLOAD_ERR_PARTIAL)));
	$large = ws_note_import_stage($viewer, array(array('name' => 'Arşiv.zip', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_INI_SIZE)));

	pg_assert_false($part['ok'], 'a part cut short is refused');
	pg_assert_same(ws_note_import_upload_error('Toplantı.md', UPLOAD_ERR_PARTIAL, 0), $part['error'], 'with the sentence that names it');
	pg_assert_false($large['ok'], 'a file over the server limit is refused');
	pg_assert_contains('Arşiv.zip', $large['error'], 'and named');
}

// A part with no file in it (a hand-made request; the screen sends files[])
// is no file: the answer to an empty upload, not a server fault.
function test_workspace_notes_import_upload_stage_part_without_file()
{
	$viewer = array('id' => 7, 'role' => 0, 'member' => true);
	$choose = lang('Choose Markdown (.md) or text (.txt) files, one .zip of them, Evernote exports (.enex) or one web page (.html).');
	$empty = ws_note_import_stage($viewer, array(array('name' => '', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE)));
	$bare = ws_note_import_stage($viewer, array(array('name' => 'a.md', 'tmp_name' => '', 'size' => 0)));

	pg_assert_false($empty['ok'], 'refused');
	pg_assert_same($choose, $empty['error'], 'as an empty upload');
	pg_assert_same($choose, $bare['error'], 'an entry with no error code reads the same');
}

// One list of kinds: the stage reads it, the screen gets it with its config.
function test_workspace_notes_import_upload_extensions_are_one_list()
{
	pg_assert_same(array('md', 'markdown', 'txt', 'zip', 'enex', 'html', 'htm'), ws_note_import_extensions());

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notes_import.php');
	$stage = pg_test_notes_upload_between($source, 'function ws_note_import_stage(', "\n}\n");

	pg_assert_true($stage !== '', 'ws_note_import_stage() is found');
	pg_assert_true(strpos($stage, 'ws_note_import_extensions()') !== false, 'the stage reads the list');
	pg_assert_false(strpos($stage, "'markdown', 'txt'") !== false, 'and keeps no copy of it');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

	pg_assert_true(strpos($screen, "'types' => ws_note_import_extensions()") !== false, 'the screen config carries it');
}

// What the stage logs: a part cut short (a cancel) or over a limit is the
// person's to fix and stays out of the error log; nothing goes to the Site
// Log.
function test_workspace_notes_import_upload_stage_logs_server_faults_only()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notes_import.php');
	$stage = pg_test_notes_upload_between($source, 'function ws_note_import_stage(', "\n}\n");

	pg_assert_true($stage !== '', 'ws_note_import_stage() is found');
	pg_assert_false(strpos($stage, 'log_activity(') !== false, 'no Site Log line');
	pg_assert_true(preg_match('/if \(!in_array\(\$error, array\(([^)]*)\), true\)\) \{\s*ws_note_import_log_failure\(/', $stage, $match) === 1, 'the codes left out of the error log are one list');

	foreach (array('UPLOAD_ERR_OK', 'UPLOAD_ERR_INI_SIZE', 'UPLOAD_ERR_FORM_SIZE', 'UPLOAD_ERR_PARTIAL') as $code) {
		pg_assert_true(strpos($match[1] ?? '', $code) !== false, $code . ' is not logged');
	}

	pg_assert_same(4, count(explode(',', $match[1] ?? '')), 'and no other code');
}

// The stage's token is made and kept before the files are read, the session
// let go, and only then the time limit raised: a file session's lock would
// hold every other request of the person for as long as staging runs.
function test_workspace_notes_import_upload_session_let_go_before_staging()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_notes_import.php');
	$upload = pg_test_notes_upload_between($source, "if (\$step === 'upload') {", "\n}\n");

	$kept = strpos($upload, "\$_SESSION['software']['ws_notes_import'] = \$upload_token;");
	$closed = strpos($upload, 'session_write_close();');
	$limit = strpos($upload, "if (function_exists('set_time_limit')) {");
	$staged = strpos($upload, 'ws_note_import_stage($viewer, ws_notes_import_files($_FILES[\'files\'] ?? null), $upload_token)');

	pg_assert_true($kept !== false, 'the token is kept in the session');
	pg_assert_true(($closed !== false) && ($kept < $closed), 'before the session is closed');
	pg_assert_true(($limit !== false) && ($closed < $limit), 'the time limit is raised after that');
	pg_assert_true(($staged !== false) && ($limit < $staged), 'and the stage made with that token');

	// set_time_limit is one of the functions hosts disable; PHP 8 throws then.
	$calls = preg_match_all('/set_time_limit\(\d/', $source);
	$guarded = preg_match_all("/if \\(function_exists\\('set_time_limit'\\)\\) \\{\\s*@set_time_limit\\(\\d/", $source);

	pg_assert_true(($calls > 0) && ($calls === $guarded), 'never called unguarded');

	$library = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notes_import.php');

	pg_assert_true(strpos($library, 'function ws_note_import_stage($viewer, $files, $token = \'\')') !== false, 'the stage takes the token');
}

// The screen: picked files listed before anything is sent, the server's
// rules over the files that go, a real progress bar with cancel, a reading
// step, a leave guard for the long steps, and no copy of the kinds.
function test_workspace_notes_import_upload_screen_shows_the_steps()
{
	$screen = pg_test_notes_upload_screen();

	pg_assert_true($screen !== '', 'openImport() is found');

	foreach (array(
		'new XMLHttpRequest()'                  => 'the upload is sent with XMLHttpRequest',
		"upload.addEventListener('progress'"    => 'which reports its progress',
		"upload.addEventListener('load'"        => 'and when the last byte left',
		'.abort()'                              => 'the upload can be cancelled',
		"t('uploading')"                        => 'the file manager\'s label',
		"t('percent'"                           => 'with the percent key the screen already has',
		"t('notes_import_reading')"             => 'a reading step',
		'progressBar('                          => 'the bar is the shared one',
		"'beforeunload'"                        => 'leaving asks first',
		'limits.types'                          => 'the kinds come from the server',
		'webkitGetAsEntry'                      => 'a dropped folder is told apart',
		"'data-bs-keyboard', 'false'"           => 'Escape does not close it',
		"phase === 'send'"                      => 'late upload events are ignored',
		'event.repeat'                          => 'a held key does not press the next button',
		'Date.now() - pressed'                  => 'nor does the second tap or press that started the upload',
		'entry.file.size > limits.max'          => 'one file is held to the cap by its own size',
		"setAttribute('role', 'alert')"         => 'a refused upload is announced',
		"t('notes_import_upload')"              => 'an Upload button starts it',
		"t('notes_import_request_failed')"      => 'a lost connection is said in words',
		"t('notes_import_too_large_server')"    => 'a web server\'s own refusal too',
	) as $needle => $label) {
		pg_assert_true(strpos($screen, $needle) !== false, $label);
	}

	pg_assert_false(strpos($screen, "'.md,.markdown,.txt,.zip,.enex'") !== false, 'no copy of the kinds');
	pg_assert_false(strpos($screen, 'function busy(') !== false, 'the spinner step is gone');

	// Picking only lists the files; the button sends them.
	$change = pg_test_notes_upload_between($screen, "input.addEventListener('change'", '});');

	pg_assert_true($change !== '', 'the picker is found');
	pg_assert_false(strpos($change, 'upload(') !== false, 'picking does not send');
}

// Each picked file is a note of its own, so two todo.md from two folders are
// two rows. The same file twice (name, size and time) is one row, and a
// listed file of that name gives way only when the browser can no longer
// read it: it changed on disk after it was picked, which fails every
// request with it, and this is the file picked again. The files of one pick
// or drop are matched only against the rows listed before it.
function test_workspace_notes_import_upload_repick_keeps_namesakes()
{
	$screen = pg_test_notes_upload_screen();
	$add = pg_test_notes_upload_between($screen, 'function add(entries)', 'function check()');
	$probe = pg_test_notes_upload_between($screen, 'function probe(entries)', 'function add(entries)');

	pg_assert_true(($add !== '') && ($probe !== ''), 'add() and probe() are found');
	pg_assert_true(strpos($add, 'lastModified') !== false, 'the same file is known by its name, size and time');
	pg_assert_true(strpos($add, 'probe(') !== false, 'the listed files of a picked name are read first');
	pg_assert_true(strpos($add, '.stale') !== false, 'only one the browser cannot read gives way');
	pg_assert_true(strpos($add, 'var known = picked.slice();') !== false, 'a pick is matched against the rows listed before it');
	pg_assert_true(preg_match('/picked\[picked\.indexOf\(\w+\)\]\s*=\s*entry;/', $add) === 1, 'the file picked again takes the row in place');
	pg_assert_true(strpos($probe, 'new FileReader()') !== false && strpos($probe, '.slice(0, 1)') !== false, 'a file is tried by its first byte');
	pg_assert_true(strpos($probe, 'entry.stale = true;') !== false, 'one that cannot be read is marked');
}

// A file the browser cannot read is left out of the upload like a file of
// another kind, and named after a request that failed in the browser: the
// screen said only "The request failed. Please try again." while every
// retry failed the same way.
function test_workspace_notes_import_upload_unreadable_file_named()
{
	$screen = pg_test_notes_upload_screen();
	$check = pg_test_notes_upload_between($screen, 'function check()', 'function drawPick(');
	$draw = pg_test_notes_upload_between($screen, 'function drawPick(', 'function drawSending()');
	$upload = pg_test_notes_upload_between($screen, 'function upload()', 'function makesNote(');
	$send = pg_test_notes_upload_between($screen, 'function sendFiles(', 'function closeButton(');

	pg_assert_true(($check !== '') && ($draw !== '') && ($upload !== '') && ($send !== ''), 'check(), drawPick(), upload() and sendFiles() are found');
	pg_assert_true(strpos($check, "entry.state = 'stale';") !== false, 'an unreadable file is left out');
	pg_assert_true(strpos($draw, "t('notes_import_unreadable')") !== false, 'and its row says so');
	pg_assert_true(strpos($draw, 'var said = error || result.error;') !== false && strpos($draw, 'result.error || error') === false, 'a refused upload is said ahead of the list\'s own rule');
	pg_assert_true(strpos($send, 'error.network = true;') !== false, 'a request the browser could not send is told apart');
	pg_assert_true(strpos($upload, 'error.network') !== false && strpos($upload, 'probe(') !== false, 'after it the files sent are read');
	pg_assert_true(strpos($upload, "t('notes_import_cannot_read'") !== false, 'and the ones that cannot be read are named');
}

// The preview and the result take the focus from the button their redraw
// removes. In the preview it is something that commits nothing, never
// Import: the footer gate keeps a stray Enter off it only for 800 ms.
function test_workspace_notes_import_upload_preview_and_result_take_the_focus()
{
	$screen = pg_test_notes_upload_screen();
	$preview = pg_test_notes_upload_between($screen, 'function drawPreview(data)', 'function run()');
	$result = pg_test_notes_upload_between($screen, 'function drawResult(', "node.addEventListener('hide.bs.modal'");

	pg_assert_true(($preview !== '') && ($result !== ''), 'drawPreview() and drawResult() are found');
	pg_assert_true(preg_match('/(\w+)\.tabIndex = -1;/', $preview, $match) === 1, 'something in the preview can take the focus');
	pg_assert_true(strpos($preview, ($match[1] ?? '?') . '.focus()') !== false, 'and takes it');
	pg_assert_false(strpos($preview, 'go.focus()') !== false, 'Import does not');
	pg_assert_true(strpos($result, '.focus()') !== false, 'the result takes it after the import');
}

// The bar of the reading step (and of a size the browser cannot tell) is
// Bootstrap's striped, animated one inside the shared .ws-progress. The
// colour rules of .ws-progress set the colour only: the background shorthand
// would reset the stripes' background-image, and the full bar would stand
// still and read as finished.
function test_workspace_notes_import_upload_moving_bar_shows_stripes()
{
	pg_assert_true(strpos(pg_test_notes_upload_screen(), "'progress-bar progress-bar-striped progress-bar-animated'") !== false, 'the moving bar is striped and animated');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	foreach (array('.ws-progress .progress-bar {', '.ws-progress.ws-progress-full .progress-bar {') as $selector) {
		$rule = pg_test_notes_upload_between($css, "\n" . $selector, '}');

		pg_assert_true($rule !== '', $selector . ' is found');
		pg_assert_true(strpos($rule, 'background-color:') !== false, $selector . ' sets its colour');
		pg_assert_false(preg_match('/[\s;{]background\s*:/', $rule) === 1, $selector . ' leaves the background image alone');
	}
}

// Every text the import screen asks for is defined; a missing key would show
// as its own name (t() in workspace.js).
function test_workspace_notes_import_upload_screen_keys_defined()
{
	$defined = pg_test_notes_upload_texts('notes.php', 'ws_notes_js_strings') + pg_test_notes_upload_texts('screen.php', 'ws_js_strings');

	pg_assert_true(isset($defined['notes_import_title']) && isset($defined['uploading']), 'both string lists are read');

	// A key put together at run time ('notes_import_count_' + kind) is left
	// out.
	preg_match_all("/\\bt\\('([a-z0-9_]+)'(?!\\s*\\+)/", pg_test_notes_upload_screen(), $matches);

	foreach (array_unique($matches[1]) as $key) {
		pg_assert_true(isset($defined[$key]), $key . ' is defined');
	}
}

// The notes import texts are known in tr.json. check_lang.php sees lang()
// calls only, not the English text of a ws_js_template().
function test_workspace_notes_import_upload_texts_known_in_tr()
{
	$texts = pg_test_notes_upload_texts('notes.php', 'ws_notes_js_strings');
	$checked = 0;

	foreach ($texts as $key => $english) {
		if (strpos($key, 'notes_import') !== 0) {
			continue;
		}

		$checked++;
		pg_assert_true(lang(array('string' => $english, 'language' => 'tr', 'if_known' => true)) !== null, $key . ': ' . $english);
	}

	pg_assert_true(($checked > 0) && isset($texts['notes_import_title'], $texts['notes_import_sent']), 'the import texts are read');
}
