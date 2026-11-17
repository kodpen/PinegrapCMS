<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the translation of a Design Canvas picture's texts: the words a
 * document's layers hold and which of them are kept, the string they share
 * with a page's same words, a flat version giving none, the reasons a file's
 * texts are dropped, the moves of a language row's state, and the hooks that
 * call the module where a picture is saved, restored or deleted and where a
 * translation is written or taken away.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one function in a file under the software directory.
function picture_translation_body($file, $function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, "\nfunction " . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, ($end === false) ? strlen($source) : $end - $start);
}

// A canvas document with the given layers.
function picture_translation_doc($layers)
{
	pg_tr_load();

	return array('v' => 1, 'artboard' => array('width' => 1080, 'height' => 1080, 'background' => '#ffffff'), 'layers' => $layers);
}

// A text layer's text and a sticker's label are read, in layer order, with
// the layer's id and the field they stand in.
function test_picture_translation_reads_text_and_label()
{
	$doc = picture_translation_doc(array(
		array('id' => 'r1', 'type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 10, 'h' => 10),
		array('id' => 't1', 'type' => 'text', 'text' => "  Free   shipping \n on every order ", 'font' => 'sans', 'size' => 40),
		array('id' => 's1', 'type' => 'sticker', 'kind' => 'burst', 'label' => 'New'),
		array('id' => 'g1', 'type' => 'glyph', 'set' => 'emoji', 'code' => 'x'),
	));

	pg_assert_same(array(
		array('node_id' => 't1', 'field' => 'text', 'text' => "Free shipping\non every order", 'hash' => pg_tr_hash("Free shipping\non every order", 'text'), 'position' => 0),
		array('node_id' => 's1', 'field' => 'label', 'text' => 'New', 'hash' => pg_tr_hash('New', 'text'), 'position' => 1),
	), pg_picture_tr_doc_words($doc), 'text and label read, normalized, in order');

	// Decoded as objects, as the editor's save hands it on.
	pg_assert_same(pg_picture_tr_doc_words($doc), pg_picture_tr_doc_words(json_decode(json_encode($doc))), 'object decode reads the same');
}

// What pg_tr_translatable() rejects never enters: no letter, a code, a
// token; a hidden layer, an empty text and a non-string are left out too.
function test_picture_translation_skips_what_is_not_words()
{
	$doc = picture_translation_doc(array(
		array('id' => 'a', 'type' => 'text', 'text' => '%50'),
		array('id' => 'b', 'type' => 'text', 'text' => '₺199'),
		array('id' => 'c', 'type' => 'text', 'text' => 'KT-1'),
		array('id' => 'd', 'type' => 'text', 'text' => '^^price^^'),
		array('id' => 'e', 'type' => 'text', 'text' => '   '),
		array('id' => 'f', 'type' => 'text', 'text' => 'Hidden words', 'hidden' => true),
		array('id' => 'g', 'type' => 'sticker', 'label' => 42),
		array('id' => 'h', 'type' => 'text', 'text' => '199 TL only'),
		array('id' => '', 'type' => 'text', 'text' => 'No id'),
	));

	$words = pg_picture_tr_doc_words($doc);

	pg_assert_same(1, count($words), 'one text kept');
	pg_assert_same('199 TL only', $words[0]['text'], 'a price inside words stays');
	pg_assert_same(0, $words[0]['position'], 'positions count the kept texts');
}

// The same words as a page's are the same string: the hash is the one a
// page's plain text gets.
function test_picture_translation_shares_the_page_string()
{
	$words = pg_picture_tr_doc_words(picture_translation_doc(array(
		array('id' => 't', 'type' => 'text', 'text' => 'Summer sale'),
	)));

	pg_assert_same(pg_tr_hash(pg_tr_normalize('Summer sale', 'text'), 'text'), $words[0]['hash'], 'same hash as a page text');
}

// A flat version (a base row, a document that could not be kept), a broken
// document or one with no layers gives no words.
function test_picture_translation_flat_gives_none()
{
	pg_assert_same(array(), pg_picture_tr_doc_words(null), 'no document');
	pg_assert_same(array(), pg_picture_tr_doc_words(''), 'empty');
	pg_assert_same(array(), pg_picture_tr_doc_words(array('v' => 1)), 'no layers');
	pg_assert_same(array(), pg_picture_tr_doc_words(array('layers' => 'x')), 'layers not a list');
	pg_assert_same(null, pg_picture_tr_row_doc(array('doc_json' => '')), 'a base row has no document');
	pg_assert_same(null, pg_picture_tr_row_doc(array('doc_json' => '{broken')), 'a broken document');
	pg_assert_same(array('v' => 1, 'layers' => array()), pg_picture_tr_row_doc(array('doc_json' => '{"v":1,"layers":[]}')), 'a save row decoded');
}

// A file's texts go when it is deleted, binned or changed outside the editor.
function test_picture_translation_prune_reasons()
{
	pg_assert_same('', pg_picture_tr_prune_reason(true, false, true), 'kept: there, not binned, document live');
	pg_assert_same('deleted', pg_picture_tr_prune_reason(false, false, false), 'deleted');
	pg_assert_same('binned', pg_picture_tr_prune_reason(true, true, true), 'binned');
	pg_assert_same('changed', pg_picture_tr_prune_reason(true, false, false), 'changed outside the editor');
}

// The state moves: 0 waiting, 1 drawn, 2 out of date. A save of the source
// or a changed translation turns only a drawn row out of date; a draw makes
// any row drawn; only a drawn row is shown.
function test_picture_translation_state_moves()
{
	pg_assert_same(0, pg_picture_tr_state_after(0, 'save'), 'waiting stays waiting on a save');
	pg_assert_same(2, pg_picture_tr_state_after(1, 'save'), 'drawn goes out of date on a save');
	pg_assert_same(2, pg_picture_tr_state_after(2, 'save'), 'out of date stays');
	pg_assert_same(0, pg_picture_tr_state_after(0, 'translation'), 'waiting stays on a translation');
	pg_assert_same(2, pg_picture_tr_state_after(1, 'translation'), 'drawn goes out of date on a translation');
	pg_assert_same(1, pg_picture_tr_state_after(0, 'draw'), 'a draw from waiting');
	pg_assert_same(1, pg_picture_tr_state_after(2, 'draw'), 'a draw from out of date');
	pg_assert_same(1, pg_picture_tr_state_after(1, 'unknown'), 'an unknown event changes nothing');
	pg_assert_same(0, pg_picture_tr_state_after(9, 'save'), 'an unknown state reads as waiting');

	pg_assert_true(pg_picture_tr_shown(1), 'drawn is shown');
	pg_assert_false(pg_picture_tr_shown(0), 'waiting is not shown');
	pg_assert_false(pg_picture_tr_shown(2), 'out of date is not shown');
}

// Where a picture is saved, restored or deleted, the module is called after
// the version is written (or the rows are gone).
function test_picture_translation_editor_hooks()
{
	$after = picture_translation_body('includes/fn/image_editor.php', 'pg_file_versions_after_save');
	pg_assert_contains('pg_picture_tr_saved(', $after, 'a replace save refreshes the texts');
	pg_assert_true(strpos($after, 'pg_picture_tr_saved(') > strpos($after, 'pg_file_versions_add('), 'after the version is kept');

	$keep = picture_translation_body('includes/fn/image_editor.php', 'pg_image_editor_canvas_keep');
	pg_assert_contains('pg_picture_tr_saved(', $keep, 'a canvas save refreshes the texts');

	$delete = picture_translation_body('includes/fn/image_editor.php', 'pg_file_versions_delete_file');
	pg_assert_contains('pg_picture_tr_forget(', $delete, 'a deleted file takes its texts and rows');
	pg_assert_true(strpos($delete, 'pg_picture_tr_forget(') < strpos($delete, 'pg_file_versions_ready()'), 'even before the versions table exists');

	$restore = picture_translation_body('includes/fn/image_editor.php', 'pg_image_editor_action_restore');
	pg_assert_contains('pg_picture_tr_saved(', $restore, 'a restore refreshes the texts');

	$sweep = picture_translation_body('includes/fn/image_editor.php', 'pg_file_versions_sweep');
	pg_assert_contains('pg_picture_tr_prune(', $sweep, 'the daily sweep drops binned and changed pictures');

	// Only a canvas in Files asks for its texts; a Workspace canvas (a
	// member's or an agent's) keeps none.
	pg_assert_contains('$words = false)', $keep, 'texts only when asked');
	$save = picture_translation_body('includes/fn/image_editor.php', 'pg_image_editor_save_canvas');
	pg_assert_contains("'saved' : 'new', true)", $save, 'the Files canvas save asks');
	pg_assert_true(strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/canvas.php'), ", 'new', true)") === false, 'the Workspace canvas does not');
}

// Where a translation is written or taken away, the drawn rows of the
// pictures that use it go out of date.
function test_picture_translation_store_hooks()
{
	pg_tr_load();

	$save = picture_translation_body('includes/translate/store.php', 'pg_tr_save_translation');
	pg_assert_contains('pg_picture_tr_translation_written(', $save, 'a written translation');
	pg_assert_true(strpos($save, 'pg_picture_tr_translation_written(') < strpos($save, 'INSERT INTO translations'), 'compared before it is written');
	pg_assert_true(strpos($save, 'pg_tr_reviewed_kept(') < strpos($save, 'pg_picture_tr_translation_written('), 'not when the write is refused');

	$clear = picture_translation_body('includes/translate/store.php', 'pg_tr_clear');
	pg_assert_contains('pg_picture_tr_translations_removed(', $clear, 'Clear translations');

	$action = (string) file_get_contents(PG_FUNCTIONS_DIR . '/translations_action.php');
	pg_assert_contains('pg_picture_tr_translations_removed(', $action, 'an emptied box on the Translations screen');

	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/translate/api.php');
	pg_assert_contains('pg_picture_tr_translations_removed(', $api, 'a translation set back to pending over the API');

	// The whole site counts and sends a picture's texts only within the
	// Pictures group's bound (tests/picture_translation_group_test.php);
	// without the table, none.
	pg_assert_same("u.owner_type NOT IN ('ui', 'file')", pg_tr_owner_where(array()), 'whole site leaves files out before the table');
	pg_assert_same('(0 = 1)', pg_tr_owner_where(array(array('file', '*'))), 'every file is never selected: only those with a row');
}

// The module is loaded with the rest and carries the gate.
function test_picture_translation_module_loaded()
{
	$manifest = (string) file_get_contents(PG_FUNCTIONS_DIR . '/functions.php');
	pg_assert_contains("/includes/fn/picture_translation.php'", $manifest, 'listed in the manifest');
	pg_assert_true(function_exists('pg_picture_tr_saved'), 'loaded');
}
