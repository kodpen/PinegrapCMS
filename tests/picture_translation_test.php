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

// Runs $script with a script of assets/js (the editor's model unless named)
// as $name, M by default, and answers its JSON; null without Node. The script
// goes through a file, never `node -e`: on Windows the shell's quoting turns
// the double quotes of an inline script into spaces.
function picture_translation_node($script, $module = 'pg_image_editor_model.js', $name = 'M')
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

	$file = @tempnam(sys_get_temp_dir(), 'pgpictr');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var ' . $name . ' = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/' . $module) . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// A translated text is fitted to the source's box: it shrinks to fit, never
// under 70% of the source's size; one that still does not fit is drawn at
// 70% and marked.
function test_picture_translation_fit_floor_and_mark()
{
	$r = picture_translation_node('console.log(JSON.stringify(['
		. ' M.languageFit(200, 50, 180, 50),'
		. ' M.languageFit(200, 50, 250, 50),'
		. ' M.languageFit(200, 70, 200, 100),'
		. ' M.languageFit(200, 50, 400, 50),'
		. ' M.languageFit(0, 0, 0, 0)'
		. ']));');

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'model loaded');
	pg_assert_same(array('scale' => 1, 'mark' => false), $r[0], 'a shorter text keeps its size');
	pg_assert_same(array('scale' => 0.8, 'mark' => false), $r[1], 'a longer one shrinks to the width');
	pg_assert_same(array('scale' => 0.7, 'mark' => false), array('scale' => round($r[2]['scale'], 4), 'mark' => $r[2]['mark']), 'more lines shrink to the height, 70% still fits');
	pg_assert_same(array('scale' => 0.7, 'mark' => true), $r[3], 'never under 70%, and marked');
	pg_assert_same(array('scale' => 1, 'mark' => false), $r[4], 'an empty box is left alone');
}

// The language's words go into the layers by id and field; a text with no
// translation keeps the source's words; the source layers are kept apart.
// Draw all takes the languages that are translated or out of date.
function test_picture_translation_language_doc()
{
	$r = picture_translation_node('var doc = { v: 1, artboard: { width: 100, height: 50 }, layers: ['
		. ' { id: "t1", type: "text", text: "Sale", size: 40 },'
		. ' { id: "t2", type: "text", text: "199 TL only", size: 20 },'
		. ' { id: "s1", type: "sticker", label: "New" },'
		. ' { id: "r1", type: "rect" } ] };'
		. 'var put = M.languageDoc(doc, [{ id: "t1", field: "text", text: "Rebajas" }, { id: "t2", field: "text", text: null }, { id: "s1", field: "label", text: "Nuevo" }, { id: "x", field: "text", text: "Lost" }]);'
		. 'console.log(JSON.stringify({'
		. ' texts: put.doc.layers.map(function (l) { return l.text || l.label || ""; }),'
		. ' fitted: Object.keys(put.source),'
		. ' kept: doc.layers[0].text,'
		. ' none: M.languageDoc(null, []),'
		. ' drawable: [M.languageDrawable({ total: 2, translated: 2 }), M.languageDrawable({ total: 2, translated: 1 }), M.languageDrawable({ total: 0, translated: 0 }), M.languageDrawable({ total: 2, translated: 2, rtl: true })],'
		. ' all: M.languagesToDraw([{ code: "de", state: "translated", total: 2, translated: 2 }, { code: "fr", state: "out_of_date", total: 2, translated: 2 }, { code: "es", state: "drawn", total: 2, translated: 2 }, { code: "it", state: "not_translated", total: 2, translated: 1 }, { code: "ar", state: "translated", rtl: true, total: 2, translated: 2 }, { code: "nl", state: "look", total: 2, translated: 2 }, { code: "pt", state: "out_of_date", total: 2, translated: 1 }, { code: "sv", state: "translated", total: 0, translated: 0 }])'
		. '}));');

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'model loaded');
	pg_assert_same(array('Rebajas', '199 TL only', 'Nuevo', ''), $r['texts'], 'translations in place, an untranslated text kept');
	pg_assert_same(array('t1'), $r['fitted'], 'only a text layer whose words changed is fitted');
	pg_assert_same('Sale', $r['kept'], 'the source document is not changed');
	pg_assert_same(null, $r['none'], 'no document, nothing to draw');
	pg_assert_same(array(true, false, false, false), $r['drawable'], 'Draw: only with every text translated, never right to left');
	pg_assert_same(array('de', 'fr'), $r['all'], 'Draw all: translated and out of date, never right to left');
	pg_assert_false(in_array('pt', $r['all'], true), 'Draw all: an out-of-date language with a text not translated is left out');
}

// The list's state of a language: from its row when drawn or out of date,
// else from how many of the texts are translated; after a draw the row is
// Drawn, or Look by hand when a text did not fit.
function test_picture_translation_list_states()
{
	pg_assert_same('not_translated', pg_picture_tr_list_state(null, 3, 0), 'no row, nothing translated');
	pg_assert_same('not_translated', pg_picture_tr_list_state(array('state' => 0, 'fit' => 0), 3, 2), 'partly translated');
	pg_assert_same('translated', pg_picture_tr_list_state(array('state' => 0, 'fit' => 0), 3, 3), 'every text translated, not drawn');
	pg_assert_same('not_translated', pg_picture_tr_list_state(null, 0, 0), 'no texts');
	pg_assert_same('out_of_date', pg_picture_tr_list_state(array('state' => 2, 'fit' => 0), 3, 3), 'out of date');

	foreach (array(0, 1, 2) as $before) {
		pg_assert_same('drawn', pg_picture_tr_list_state(array('state' => pg_picture_tr_state_after($before, 'draw'), 'fit' => 0), 3, 3), 'a draw from ' . $before . ' is Drawn');
		pg_assert_same('look', pg_picture_tr_list_state(array('state' => pg_picture_tr_state_after($before, 'draw'), 'fit' => 1), 3, 1), 'a draw that did not fit is Look by hand');
	}

	pg_assert_same('out_of_date', pg_picture_tr_list_state(array('state' => pg_picture_tr_state_after(1, 'save'), 'fit' => 1), 3, 3), 'a save after a draw: out of date');
}

// A draw names the picture by a stamp of the file, the language, the
// document and the words it was given: another file, another language, a
// changed document or changed words do not match it.
function test_picture_translation_draw_refusals()
{
	$texts = array(array('id' => 't1', 'field' => 'text', 'text' => 'Rebajas'), array('id' => 't2', 'field' => 'text', 'text' => null));
	$stamp = pg_picture_tr_stamp(7, 'es', str_repeat('a', 40), $texts);
	$facts = array('access' => true, 'language' => true, 'rtl' => false, 'live' => true, 'sent' => $stamp, 'stamp' => $stamp);

	pg_assert_same(40, strlen($stamp), 'a SHA-1');
	pg_assert_false($stamp === pg_picture_tr_stamp(8, 'es', str_repeat('a', 40), $texts), 'another file');
	pg_assert_false($stamp === pg_picture_tr_stamp(7, 'de', str_repeat('a', 40), $texts), 'another language');
	pg_assert_false($stamp === pg_picture_tr_stamp(7, 'es', str_repeat('b', 40), $texts), 'another document');
	pg_assert_false($stamp === pg_picture_tr_stamp(7, 'es', str_repeat('a', 40), array($texts[0])), 'other words');

	pg_assert_same('', pg_picture_tr_draw_refusal($facts), 'a right draw goes through');
	pg_assert_same('access', pg_picture_tr_draw_refusal(array_merge($facts, array('access' => false))), 'no right to the folder');
	pg_assert_same('language', pg_picture_tr_draw_refusal(array_merge($facts, array('language' => false))), 'not a target language');
	pg_assert_same('rtl', pg_picture_tr_draw_refusal(array_merge($facts, array('rtl' => true))), 'right to left is not drawn');
	pg_assert_same('changed', pg_picture_tr_draw_refusal(array_merge($facts, array('live' => false))), 'no live canvas document');
	pg_assert_same('stamp', pg_picture_tr_draw_refusal(array_merge($facts, array('sent' => pg_picture_tr_stamp(8, 'es', str_repeat('a', 40), $texts)))), 'the picture of another file');
	pg_assert_same('stamp', pg_picture_tr_draw_refusal(array_merge($facts, array('sent' => pg_picture_tr_stamp(7, 'de', str_repeat('a', 40), $texts)))), 'the picture of another language');
	pg_assert_same('stamp', pg_picture_tr_draw_refusal(array_merge($facts, array('sent' => ''))), 'no stamp');
	pg_assert_same('untranslated', pg_picture_tr_draw_refusal(array_merge($facts, array('missing' => 1))), 'a text not translated into the language');
	pg_assert_same(1, pg_picture_tr_untranslated($texts), 'one text without a translation');
	pg_assert_same(0, pg_picture_tr_untranslated(array($texts[0])), 'every text translated');
	pg_assert_same(1, pg_picture_tr_untranslated(array(array('id' => 'a', 'field' => 'text', 'text' => ''))), 'an empty translation is none');

	// The draw asks the editor's file check (the folder's edit right)
	// before it reads or writes anything.
	$body = picture_translation_body('includes/fn/picture_translation.php', 'pg_picture_tr_draw_answer');
	$check = strpos($body, 'pg_image_editor_file(');

	pg_assert_true($check !== false, 'the draw checks the file and the right');
	pg_assert_true($check < strpos($body, 'pg_picture_tr_draw_canvas('), 'before a canvas\'s picture is taken');
	pg_assert_true($check < strpos($body, 'pg_picture_tr_draw_photo('), 'before a photo\'s is drawn');
	pg_assert_true(strpos($body, 'pg_page_cache_mark_dirty()') !== false, 'a draw marks the page cache');
	pg_assert_true(strpos($body, "'missing'  => pg_picture_tr_untranslated(\$texts)") !== false, 'the draw counts the texts not translated');
	pg_assert_true(strpos($body, 'count($texts), count($texts) - pg_picture_tr_untranslated($texts))') !== false, 'the state after a draw is read from the real count');

	// The target languages live in a translate file only pg_tr_load() reads.
	foreach (array('pg_picture_tr_draw_answer', 'pg_picture_tr_languages_answer') as $function) {
		$code = picture_translation_body('includes/fn/picture_translation.php', $function);
		$load = strpos($code, 'pg_tr_load()');

		pg_assert_true(($load !== false) && ($load < strpos($code, 'pg_tr_target_languages()')), $function . ' loads the translate files first');
	}
}

// A language's picture has a name beside its source's and is left out of
// the File Manager, the file pickers and Where used.
function test_picture_translation_pictures_hidden()
{
	pg_assert_same('banner-de.png', pg_picture_tr_picture_name('banner.jpg', 'de'), 'the stem and the language');
	pg_assert_same('banner-pt-br.png', pg_picture_tr_picture_name('banner.png', 'pt-BR'), 'lower case');
	pg_assert_same('', pg_picture_tr_hidden_sql('files.id', false), 'nothing before the table');
	pg_assert_same(' AND (files.id NOT IN (SELECT picture_file_id FROM file_translation))', pg_picture_tr_hidden_sql('files.id', true), 'the table\'s pictures left out');

	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_list.php');
	pg_assert_same(2, substr_count($list, "pg_picture_tr_hidden_sql('files.id')"), 'both File Manager lists');
	pg_assert_true(strpos(picture_translation_body('includes/fn/picker.php', 'pg_picker_where_for'), 'pg_picture_tr_hidden_sql(') !== false, 'the file pickers');
	pg_assert_true(strpos(picture_translation_body('includes/fn/image_editor.php', 'pg_image_editor_action_sticker_files'), 'pg_picture_tr_hidden_sql(') !== false, 'the Design Canvas\'s pictures from Files');
	pg_assert_true(strpos(picture_translation_body('includes/explorer/handle_file_usage.php', 'pg_explorer_handle_file_usage'), 'pg_picture_tr_is_picture(') !== false, 'Where used');
}

// A photo with text layers is drawn as well as a canvas: its live document
// is the newest save over a base row; the language's picture keeps the
// photo's format.
function test_picture_translation_photo_kinds()
{
	$canvas = picture_translation_doc(array());
	$photo = array('v' => 1, 'source' => array('fileId' => 7, 'width' => 800, 'height' => 600, 'base' => 2), 'geometry' => array('rot' => 0), 'layers' => array());

	pg_assert_same('canvas', pg_picture_tr_doc_kind($canvas), 'an artboard is a canvas');
	pg_assert_same('photo', pg_picture_tr_doc_kind($photo), 'a source picture is a photo');
	pg_assert_same('photo', pg_picture_tr_doc_kind(json_decode(json_encode($photo))), 'decoded as objects too');
	pg_assert_same('', pg_picture_tr_doc_kind(array('v' => 1, 'geometry' => array())), 'neither');
	pg_assert_same('', pg_picture_tr_doc_kind(null), 'no document');
	pg_assert_same('', pg_picture_tr_doc_kind(array('v' => 1, 'source' => array('base' => 2), 'artboard' => array('width' => 9, 'height' => 9))), 'both is neither');

	pg_assert_same('summer-de.jpg', pg_picture_tr_picture_name('summer.jpg', 'de', 'jpg'), 'a photo keeps its format');
	pg_assert_same('summer-de.webp', pg_picture_tr_picture_name('summer.jpg', 'de', 'webp'), 'or the export\'s');
	pg_assert_same('summer-de.png', pg_picture_tr_picture_name('summer.jpg', 'de'), 'a canvas is a PNG');

	// The pixels are made from the base the live document names, never a
	// base the request names, through the save's own steps.
	$body = picture_translation_body('includes/fn/picture_translation.php', 'pg_picture_tr_draw_photo');
	pg_assert_true(strpos($body, 'pg_image_editor_photo_parts(') !== false, 'the browser\'s overlay and masks taken as a save takes them');
	pg_assert_true(strpos($body, 'pg_image_editor_photo_pixels(') !== false, 'drawn on the base as a save draws');
	pg_assert_true(strpos($body, "\$live['base']") !== false, 'from the live document\'s base');
	pg_assert_false(strpos($body, "\$request['base']") !== false, 'not one the request names');
	pg_assert_true(strpos($body, "'first_frame'] = true") !== false, 'an animated photo gives its first frame');

	// The save reads the redaction from its recipe after the parts moved out.
	pg_assert_true(strpos(picture_translation_body('includes/fn/image_editor.php', 'pg_image_editor_save'), "!empty(\$edit['redact'])") !== false, 'a redacted save is still marked');

	$r = picture_translation_node('var doc = { v: 1, source: { fileId: 3, width: 800, height: 600, base: 2 }, geometry: { rot: 0 }, layers: [ { id: "t1", type: "text", text: "Sale" } ] };'
		. 'var put = M.languageDoc(M.versionOpenDoc(doc, 9), [{ id: "t1", field: "text", text: "Rebajas" }]);'
		. 'console.log(JSON.stringify({ text: put.doc.layers[0].text, file: put.doc.source.fileId, base: put.doc.source.base === undefined, fitted: Object.keys(put.source) }));');

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_same(array('text' => 'Rebajas', 'file' => 9, 'base' => true, 'fitted' => array('t1')), $r, 'a photo\'s document takes the words as a canvas\'s does');
}

// The queue: the languages' rows waiting or out of date whose every text is
// translated, of the languages that are drawn (no right to left), in a
// bounded list.
function test_picture_translation_queue_sql()
{
	$sql = pg_picture_tr_queue_sql(array('de', 'fr'), 200);

	pg_assert_contains('ft.state IN (0, 2)', $sql, 'waiting and out of date rows');
	pg_assert_contains("ft.language IN ('de', 'fr')", $sql, 'only the languages drawn');
	pg_assert_contains("t.id IS NULL OR t.text = ''", $sql, 'a row with an untranslated text is left out');
	pg_assert_contains('EXISTS (SELECT 1 FROM translation_uses', $sql, 'a picture with texts');
	pg_assert_contains('LIMIT 200', $sql, 'bounded');
	pg_assert_same('', pg_picture_tr_queue_sql(array(), 200), 'no language, no query');
	pg_assert_contains("ft.language IN ('pt-BR')", pg_picture_tr_queue_sql(array('pt-BR'), 5), 'a code with a region');
	pg_assert_false(strpos(pg_picture_tr_queue_sql(array("x' OR '1"), 5), "OR '1") !== false, 'a code with a quote does not reach the query');

	// The Pictures group offers the queue; its view prints the editor and
	// the queue's own script.
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/translations.php');
	pg_assert_contains("'draw' => 1", $screen, 'a link to the queue');
	pg_assert_contains('assets/js/picture_queue.js', $screen, 'the queue\'s script');
	pg_assert_contains('pg_picture_tr_queue(', $screen, 'the queue\'s rows');
	pg_assert_contains('data-pq-reason="1"', $screen, 'the reason a row waits is its own part, dropped once drawn');
	pg_assert_contains('pg-list-row-head pg-list-row-head-wrap', $screen, 'the pill goes under a name it would cut');
	pg_assert_contains('data-pq-edit="1" hidden', $screen, 'a row to look at by hand offers the Design Canvas, hidden until the run ends');
	pg_assert_contains('data-pg-image-edit="\' . h($queue_item[\'name\'])', $screen, 'the button opens the row\'s picture');
	pg_assert_contains('class="pg-button pg-button-quiet" id="pg_pq_stop"', $screen, 'Stop is quiet');
	pg_assert_contains('class="pg-button pg-button-quiet pg-button-sm" href="\' . h($url(array(\'draw\' => 1', $screen, 'the group\'s button is quiet');
}

// The queue's script: the count left and the state a draw's answer gives.
function test_picture_translation_queue_script()
{
	$r = picture_translation_node('console.log(JSON.stringify({'
		. ' left: Q.left([{ status: "waiting" }, { status: "drawing" }, { status: "drawn" }, { status: "failed" }, { status: "waiting" }]),'
		. ' drawn: Q.statusOf({ status: "success", state: "drawn" }),'
		. ' look: Q.statusOf({ status: "success", state: "look" }),'
		. ' refused: Q.statusOf({ status: "error", message: "No" }),'
		. ' none: Q.statusOf(null),'
		. ' skipped: Q.statusOf("skipped"),'
		. ' reason: ["waiting", "drawing", "drawn", "look", "skipped", "failed"].map(Q.reasonShown),'
		. ' edit: [Q.editShown("look", false), Q.editShown("look", true), Q.editShown("drawn", false), Q.editShown("failed", false)],'
		. ' tone: [Q.buttonTone(true), Q.buttonTone(false)],'
		. ' end: [[{ status: "drawn" }], [{ status: "drawn" }, { status: "look" }, { status: "look" }], [{ status: "failed" }, { status: "look" }]].map(function (rows) {'
		. '  return Q.endText(rows, { done: "D", doneLook: "L{n}", doneFailed: "F{n}" }); })'
		. '}));', 'picture_queue.js', 'Q');

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_same(array('left' => 3, 'drawn' => 'drawn', 'look' => 'look', 'refused' => 'failed', 'none' => 'failed', 'skipped' => 'skipped',
		'reason' => array(true, true, false, false, false, false), 'edit' => array(true, false, false, false),
		'tone' => array('pg-button-quiet', 'pg-button-fill'), 'end' => array('D', 'L2', 'F1')), $r, 'left counts what is waiting or drawing; an answer gives the row\'s state; the reason goes once drawn; Look by hand offers the editor once the run ends; Stop is quiet, Continue the fill; the end line counts the rows to look at by hand, after the failed ones');
}
