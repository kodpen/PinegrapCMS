<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the unsaved-changes guard of panel code fields
 * (pg_code_editor_attach() in includes/fn/editor.php) and of the product
 * create/edit screen (assets/js/product_builder.js): leaving the page asks
 * while there are changes, and a real form submit does not.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Draws an editable and a read-only field in a PHP process of its own: the
// field's first script names the facade on OUTPUT_PATH, which the tests
// after this one must not find defined.
function pg_unsaved_guard_attach_output()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgunsaved');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, '<?php' . "\n"
		. "define('PG_TEST_RUNNER', true);\n"
		. "define('OUTPUT_PATH', '/');\n"
		. "define('OUTPUT_SOFTWARE_DIRECTORY', 'pinegrap');\n"
		. 'require ' . var_export(PG_FUNCTIONS_DIR . '/functions.php', true) . ";\n"
		. "echo json_encode(array("
		. "'edit' => pg_code_editor_attach(array('id' => 'code', 'code_type' => 'css')),"
		. "'ro' => pg_code_editor_attach(array('id' => 'code', 'code_type' => 'css', 'readonly' => true))));\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$res = json_decode(implode("\n", $out), true);

	return is_array($res) ? $res : false;
}

// An editable code field asks before the page is left while its text differs
// from what the editor held when it arrived; a read-only field never asks.
function test_unsaved_guard_code_field_asks_while_changed()
{
	$res = pg_unsaved_guard_attach_output();

	if ($res === null) {
		return;
	}

	if ($res === false) {
		pg_assert_true(false, 'the process answers');
		return;
	}

	$edit = $res['edit'];

	pg_assert_contains('window.addEventListener("beforeunload"', $edit, 'an editable field listens for leaving');
	pg_assert_contains('var saved = editor.getValue();', $edit, 'the baseline is the editor\'s own value after attach');
	pg_assert_contains('editor.getValue() === saved', $edit, 'it asks only while the text differs');
	pg_assert_contains('e.returnValue = "";', $edit, 'the browser shows its own prompt');
	pg_assert_false(strpos($res['ro'], 'beforeunload') !== false, 'a read-only field never asks');
}

// A field with no form (edit_page_layout.php guards its own), a field in the
// settings dialog (it tracks its own changes) and a released editor are
// left out; the field's own submit stops the question unless it was
// prevented. The submit is heard through jQuery, because the Ctrl+S fallback
// calls jQuery's form.submit(), which no native listener hears.
function test_unsaved_guard_code_field_skips_and_submit()
{
	$res = pg_unsaved_guard_attach_output();

	if (!is_array($res)) {
		return;
	}

	$edit = $res['edit'];

	pg_assert_contains('var form = field.form;', $edit, 'the field\'s form is read');
	pg_assert_contains('closest("#pg_settings_modal")', $edit, 'the settings dialog is left out');
	pg_assert_contains('window.jQuery(form).on("submit"', $edit, 'the submit is heard through jQuery');
	pg_assert_contains('isDefaultPrevented()', $edit, 'a prevented submit still asks');
	pg_assert_contains('window.pgCodeEditors[field.id] !== instance', $edit, 'a released editor no longer asks');
}

// The product screen takes a snapshot of the form, the variant rows, the
// attribute choices and the rich text editors after its first render and
// asks before leaving while the state differs, except during its own submit.
function test_unsaved_guard_product_builder_asks_while_changed()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/product_builder.js');
	$from = strpos($js, '    function pbState() {');
	$state = ($from !== false) ? substr($js, $from, strpos($js, "\n    }\n", $from) - $from) : '';

	pg_assert_true($state !== '', 'pbState() is found');
	pg_assert_contains('$form.serializeArray()', $state, 'the form fields are read');
	pg_assert_contains('collectVariants()', $state, 'the variant rows are read');
	pg_assert_contains('collectAttributeMeta()', $state, 'the attribute choices are read');
	pg_assert_contains('checkDirty()', $js, 'CKEditor fields say whether they changed');
	pg_assert_contains('isDirty()', $js, 'TinyMCE fields say whether they changed');
	pg_assert_contains("window.addEventListener('beforeunload'", $js, 'leaving is heard');
	pg_assert_contains('pbSnapshot = pbState();', $js, 'the snapshot is taken');
	pg_assert_contains('!pbChanged(pbSnapshot) && !pbRichDirty()', $js, 'it asks only while the state differs');
	pg_assert_contains('pbFields(now.fields) !== pbFields(snapshot.fields)', $js, 'the rich text fields are filtered on both sides');
	pg_assert_contains('pbSubmit.isDefaultPrevented()', $js, 'a prevented (blank name) save still asks');
}
