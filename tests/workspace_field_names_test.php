<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Names screen readers get for the fields of the Workspace's drawers and
 * dialogs (assets/js/workspace.js). The shared helpers draw a caption for a
 * field: formRow() points it at the field (an id-less control gets one, a
 * wrapper's first unnamed field, a picker box is named as a group),
 * saLabelled() ties its label to the field of quick edit and quick create,
 * and ask() names the question dialog and its first unnamed detail control
 * with the question. The pickers carry their own names and states.
 *
 * Read from source: the helpers build DOM nodes the test runner has no
 * browser for.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_field_names_test_source()
{
	static $source = null;

	if ($source === null) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	}

	return $source;
}

// The text of a JavaScript block from the first "$start" to its matching
// closing brace, or '' when there is none.
function workspace_field_names_test_block($start)
{
	$source = workspace_field_names_test_source();
	$at = strpos($source, $start);

	if ($at === false) {
		return '';
	}

	$open = strpos($source, '{', $at);
	$depth = 0;

	for ($i = $open, $n = strlen($source); $i < $n; $i++) {
		if ($source[$i] === '{') {
			$depth++;
		} elseif ($source[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				return substr($source, $at, $i - $at + 1);
			}
		}
	}

	return '';
}

function test_workspace_field_names_form_row_names_its_field()
{
	$row = workspace_field_names_test_block('function formRow(');

	pg_assert_true($row !== '', 'workspace.js has formRow()');
	pg_assert_true(strpos($row, 'labelTarget(control)') !== false, 'formRow() finds the field its caption names (a wrapper\'s first field too)');
	pg_assert_true(strpos($row, "nextId('ws-field-')") !== false, 'formRow() gives an id-less field an id for its caption');
	pg_assert_true(strpos($row, "setAttribute('aria-labelledby', caption.id)") !== false, 'formRow() names a picker box with its caption');

	$target = workspace_field_names_test_block('function labelTarget(');

	pg_assert_true(strpos($target, "getAttribute('role')") !== false, 'a picker box (a role of its own) is named as a group, not through a field inside it');
	pg_assert_true(strpos($target, 'input:not([type="hidden"]), select, textarea') !== false, 'a wrapper (an input group) names its first field');
}

function test_workspace_field_names_pickers_are_named_groups()
{
	$people = workspace_field_names_test_block('function peoplePicker(');

	pg_assert_true(strpos($people, "box.setAttribute('role', 'group')") !== false, 'a people picker is a group its caption names');

	$set = workspace_field_names_test_block('box.set = function (ids)');

	pg_assert_true(strpos($set, "setAttribute('aria-pressed'") !== false, 'peoplePicker().set() (a task-check suggestion) keeps aria-pressed with .active');

	$color = workspace_field_names_test_block('function colorPicker(');

	pg_assert_true(strpos($color, "box.setAttribute('aria-label', t('color'))") !== false, 'the colour radiogroup has a name, as the icon grid has');

	$ref = workspace_field_names_test_block('function refSearchField(');

	pg_assert_true(strpos($ref, "typeSelect.setAttribute('aria-label', t('ref_kind'))") !== false, 'the record search\'s kind select has a name');

	$strings = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

	pg_assert_true(strpos($strings, "'ref_kind'") !== false && strpos($strings, "lang('Kind of record')") !== false, 'ref_kind is a Workspace string on the existing "Kind of record" text');
}

function test_workspace_field_names_drawers_name_late_and_extra_fields()
{
	$note = workspace_field_names_test_block('noteForwardForm: function');

	pg_assert_true(strpos($note, "picker.setAttribute('aria-label', t('fwd_note_pick'))") !== false, 'the note picker, put into its slot after formRow(), gets the caption\'s name');

	$share = workspace_field_names_test_block('function sharePeople(');

	pg_assert_true(strpos($share, "right.setAttribute('aria-label', t('notes_share_right'))") !== false, 'the notes share Can read / Can edit select has a name');

	$notes = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notes.php');

	pg_assert_true(strpos($notes, "'notes_share_right'") !== false, 'notes_share_right is a notes string');
}

function test_workspace_field_names_quick_edit_label_points_at_its_field()
{
	$labelled = workspace_field_names_test_block('function saLabelled(');

	pg_assert_true(strpos($labelled, 'labelTarget(node)') !== false, 'saLabelled() finds the field (or the first one inside the node)');
	pg_assert_true(strpos($labelled, '.htmlFor = ') !== false, 'saLabelled() ties its label to the field');

	// tests/workspace_change_limits_test.php reads saValueInput() up to saLabelled().
	$source = workspace_field_names_test_source();
	$value = strpos($source, 'function saValueInput(');
	$next = ($value === false) ? false : strpos($source, "\n    function ", $value + 1);

	pg_assert_true(($next !== false) && (strpos($source, 'function saLabelled(', $next) === $next + 5), 'saValueInput() is still directly followed by saLabelled()');
}

function test_workspace_field_names_question_dialog_is_named()
{
	$ask = workspace_field_names_test_block('function ask(');

	pg_assert_true(strpos($ask, "setAttribute('aria-labelledby', 'ws-ask-text')") !== false, 'the question dialog is named by its question');
	pg_assert_true(strpos($ask, 'id="ws-ask-text"') !== false, 'the question paragraph carries the id the dialog names');
	pg_assert_true(strpos($ask, 'hasName(') !== false, 'ask() names only a detail control that has no name yet');
}
