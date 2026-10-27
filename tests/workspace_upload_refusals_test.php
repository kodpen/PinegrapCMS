<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what a Workspace channel refuses (includes/workspace/files.php):
 * Office files that carry macros, beside the page and script types, and the
 * sentence that says why; the edit a macro file already in a channel is no
 * longer offered; and the groups the file manager's "Where used" draws from
 * the Workspace messages and tasks that carry a file.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/files.php');

// The Office forms that carry macros: Word, Excel and PowerPoint documents,
// templates, add-ins, shows and slides.
function ws_upload_refusals_test_macro_extensions()
{
	return array('docm', 'dotm', 'xlsm', 'xltm', 'xlam', 'pptm', 'potm', 'ppsm', 'ppam', 'sldm');
}

// The part of a source file from $start up to $end (or the end of the file).
function ws_upload_refusals_test_between($source, $start, $end)
{
	$from = strpos($source, $start);

	if ($from === false) {
		return '';
	}

	$to = strpos($source, $end, $from + strlen($start));

	return ($to === false) ? substr($source, $from) : substr($source, $from, $to - $from);
}

// Every macro form is refused whatever its case, and as a part of the name
// before the last one too: "q3.xlsm.zip" is refused the way "page.html.txt" is.
function test_ws_upload_macro_extensions_are_refused()
{
	foreach (ws_upload_refusals_test_macro_extensions() as $extension) {
		pg_assert_true(ws_upload_refused('report.' . $extension), 'report.' . $extension);
		pg_assert_true(ws_upload_refused('REPORT.' . strtoupper($extension)), 'REPORT.' . strtoupper($extension));
		pg_assert_true(ws_upload_refused('q3.' . $extension . '.zip'), 'q3.' . $extension . '.zip');
	}
}

// The same Office files without macros, the legacy binary forms and a PDF
// are still taken.
function test_ws_upload_macro_free_office_files_are_taken()
{
	foreach (array('report.docx', 'book.xlsx', 'deck.pptx', 'form.xltx', 'letter.dotx', 'theme.potx', 'old.doc', 'old.xls', 'big.xlsb', 'scan.pdf') as $name) {
		pg_assert_false(ws_upload_refused($name), $name);
	}
}

// A name is judged as prepare_file_name() stores it too: a character it turns
// into an underscore and trims, a transliterated letter, a name cut to 100
// characters or a trailing dot can leave a macro or page extension behind.
function test_ws_upload_refused_matches_the_stored_name()
{
	$refused = array(
		'q3.xlsm!', 'q3.xlsm_', 'q3.xlsm~', 'q3.xlsm#', "q3.do\xC3\xA7m", "q3.xlsm\xE2\x80\x8B",
		str_repeat('a', 95) . '.xlsmzzzzzz', 'x.svg!',
	);

	foreach ($refused as $name) {
		pg_assert_true(ws_upload_refused($name), json_encode($name));
	}

	// A name whose stored form only changes its letters, its brackets or the
	// characters after a safe extension is still taken; one written entirely
	// in another script is stored with "file" in front and is not a dotfile.
	foreach (array('report.xlsx', 'Rapor özet.docx', 'отчет.xlsx', 'photo[1].png', 'notes.js_backup', 'q3.xlsx!') as $name) {
		pg_assert_false(ws_upload_refused($name), $name);
	}

	pg_assert_same(lang(array('string' => 'Sorry, {var:1} was not accepted. Office files with macros cannot be added here; save it without macros (.docx, .xlsx or .pptx) and try again.', 'vars' => 'q3.xlsm!')), ws_upload_refused_message('q3.xlsm!'), 'q3.xlsm! gets the macro sentence');
	pg_assert_same(pg_upload_blocked_message('x.svg!'), ws_upload_refused_message('x.svg!'), 'x.svg! keeps the file manager sentence');
}

// What a channel says when it refuses a file: a macro file is told why and
// what to send instead; any other refused name gets the file manager's
// sentence, as before.
function test_ws_upload_macro_inspect_names_the_reason()
{
	$macro = lang(array('string' => 'Sorry, {var:1} was not accepted. Office files with macros cannot be added here; save it without macros (.docx, .xlsx or .pptx) and try again.', 'vars' => 'report.xlsm'));
	$inspected = ws_upload_inspect('report.xlsm', "PK\x03\x04 a workbook");

	pg_assert_false($inspected['ok'], 'report.xlsm is refused');
	pg_assert_same($macro, $inspected['error'], 'the macro sentence');
	pg_assert_contains('report.xlsm', $inspected['error'], 'the sentence names the file');

	pg_assert_same(pg_upload_blocked_message('logo.svg'), ws_upload_inspect('logo.svg', '<svg/>')['error'], 'an SVG keeps the file manager sentence');
	pg_assert_same(pg_upload_blocked_message(''), ws_upload_inspect('', 'bytes')['error'], 'no name keeps the general sentence');
}

// The macro sentence for a macro part anywhere after the first, the file
// manager's sentence for every other refused name and for one the file
// manager refuses too.
function test_ws_upload_macro_message_for_other_names()
{
	foreach (array('logo.svg', 'page.html', 'shell.php', '.htaccess', 'shell.php.docm', '.xlsm') as $name) {
		pg_assert_same(pg_upload_blocked_message($name), ws_upload_refused_message($name), $name);
	}

	pg_assert_same(pg_upload_blocked_message(''), ws_upload_refused_message(''), 'no name');

	foreach (array('report.docm', 'Q3.DOCM.zip', 'dir/deck.ppsm') as $name) {
		pg_assert_same(lang(array('string' => 'Sorry, {var:1} was not accepted. Office files with macros cannot be added here; save it without macros (.docx, .xlsx or .pptx) and try again.', 'vars' => $name)), ws_upload_refused_message($name), $name);
	}
}

// Someone outside the Workspace, or no file, is answered before anything is
// read: nothing listed, nothing counted.
function test_ws_file_usage_without_a_reader()
{
	$nothing = array('messages' => array(), 'tasks' => array(), 'truncated' => false);

	pg_assert_same($nothing, ws_file_usage(array('member' => false, 'id' => 1, 'role' => 0), 12), 'not a member');
	pg_assert_same($nothing, ws_file_usage(array(), 12), 'no rights at all');
	pg_assert_same($nothing, ws_file_usage(array('member' => true, 'id' => 1, 'role' => 0), 0), 'no file');
}

// The Workspace's groups in "Where used": the messages first, then the
// tasks, an empty one left out, plain text throughout.
function test_ws_file_usage_groups()
{
	pg_assert_same(array(), ws_file_usage_groups(array('messages' => array(), 'tasks' => array(), 'truncated' => false)), 'nothing found');
	pg_assert_same(array(), ws_file_usage_groups(array()), 'no answer');

	$message = array(
		'id' => 7, 'channel_id' => 3, 'channel' => 'Sales <team>', 'sender' => 'Ada', 'kind' => 'message',
		'excerpt' => 'The Q3 figures & notes', 'file_name' => 'q3.xlsx', 'url' => '/pinegrap/workspace.php?channel=3&message=7',
	);
	$tagged = array(
		'id' => 5, 'channel_id' => 4, 'channel' => 'Board', 'sender' => 'Lin', 'kind' => 'message',
		'excerpt' => '', 'url' => '/pinegrap/workspace.php?channel=4&message=5',
	);
	$carrying = array(
		'id' => 4, 'channel_id' => 4, 'channel' => 'Board', 'sender' => 'Lin', 'kind' => 'message',
		'excerpt' => '', 'file_name' => 'minutes.docx', 'url' => '/pinegrap/workspace.php?channel=4&message=4',
	);

	$groups = ws_file_usage_groups(array('messages' => array($message, $tagged, $carrying), 'tasks' => array(), 'truncated' => false));

	pg_assert_same(1, count($groups), 'messages only: one group');
	pg_assert_same(lang('Workspace messages'), $groups[0]['label']);
	pg_assert_same(array(
		array('name' => 'Sales <team>', 'note' => 'The Q3 figures & notes', 'url' => '/pinegrap/workspace.php?channel=3&message=7'),
		array('name' => 'Board', 'note' => '', 'url' => '/pinegrap/workspace.php?channel=4&message=5'),
		array('name' => 'Board', 'note' => 'minutes.docx', 'url' => '/pinegrap/workspace.php?channel=4&message=4'),
	), $groups[0]['items'], 'channel, excerpt or the file name, the url as it came; nothing escaped here');

	$task = array('id' => 9, 'title' => 'Check the <b>figures</b>', 'status' => 'doing', 'status_label' => 'In progress', 'url' => '/pinegrap/workspace_tasks.php?task=9');
	$groups = ws_file_usage_groups(array('messages' => array($message), 'tasks' => array($task), 'truncated' => true));

	pg_assert_same(2, count($groups), 'both: two groups');
	pg_assert_same(lang('Workspace messages'), $groups[0]['label'], 'messages first');
	pg_assert_same(lang('Workspace tasks'), $groups[1]['label'], 'tasks second');
	pg_assert_same(array(array('name' => 'Check the <b>figures</b>', 'note' => 'In progress', 'url' => '/pinegrap/workspace_tasks.php?task=9')), $groups[1]['items'], 'a task by its title and status');

	$groups = ws_file_usage_groups(array('messages' => array(), 'tasks' => array($task), 'truncated' => false));

	pg_assert_same(1, count($groups), 'tasks only: one group');
	pg_assert_same(lang('Workspace tasks'), $groups[0]['label']);
}

// A macro file posted before channels refused them still opens, read only:
// its edited copy or replacement would be refused, so editing is not offered,
// and a save sent from a window opened earlier is refused before any work.
function test_ws_upload_macro_file_is_not_offered_for_editing()
{
	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$office = ws_upload_refusals_test_between($actions, "case 'ws_file_office':", "case 'ws_file_office_save':");

	pg_assert_true($office !== '', 'the ws_file_office case is found');
	pg_assert_true(strpos($office, "!ws_upload_refused(\$message['file_name'])") !== false, 'editing is refused for a name a channel refuses');

	$edits = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/file_edits.php');
	$target = ws_upload_refusals_test_between($edits, 'function ws_file_edit_target(', "\n}\n");

	pg_assert_true(strpos($target, "ws_upload_refused(\$message['file_name'])") !== false, 'the save target asks the channel rule');
	pg_assert_true(strpos($target, "ws_upload_refused_message(\$message['file_name'])") !== false, 'and answers with its sentence');
}

// "Where used" asks the Workspace only when the module is on and installed,
// and only through the helper that keeps to the channels the person may read.
function test_ws_file_usage_is_asked_by_where_used()
{
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_file_usage.php');
	$case = ws_upload_refusals_test_between($explorer, 'function pg_explorer_handle_file_usage(', "'file' => \$usage_name,");

	pg_assert_true($case !== '', 'the explorer_file_usage handler is found');

	$flag = strpos($case, "defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED");
	$bootstrap = strpos($case, "require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');");
	$enabled = strpos($case, 'ws_enabled()');
	$usage = strpos($case, '= ws_file_usage(');

	pg_assert_true($flag !== false, 'the module flag is asked');
	pg_assert_true(($bootstrap !== false) && ($flag < $bootstrap), 'the Workspace is loaded behind the flag');
	pg_assert_true(($enabled !== false) && ($bootstrap < $enabled), 'and asked whether it is installed');
	pg_assert_true(($usage !== false) && ($enabled < $usage), 'before ws_file_usage() is called');
	pg_assert_true(strpos($case, 'ws_file_usage_groups(') !== false, 'its answer becomes the explorer groups');
}
