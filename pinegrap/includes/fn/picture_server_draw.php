<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A translated Design Canvas picture drawn by the server with nobody in the
 * panel. While the waiting mark is set (includes/fn/picture_translation.php)
 * the scheduled translation job draws the queue's rows of every folder
 * outside the Recycle Bin whose picture is a canvas this server draws whole
 * (includes/fn/image_draw.php): the language's words are put into the
 * layers and each changed text is fitted to its source's box as the Design
 * Canvas fits it (M.languageDoc(), M.languageFit() of
 * assets/js/pg_image_editor_model.js, twinned here on shared fixtures), with
 * the server's own letter widths. A row it cannot draw whole (a sticker, an
 * effect, a picture layer, a font it has no file of, a photo) is left as it
 * is for an open panel. A picture drawn here is kept as a browser's is, with
 * drawn_by 0.
 *
 * Loaded where it is used (translation_job.php), not by functions.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/image_draw.php');

/**
 * A number rounded as the editor's Math.round(n * k) / k rounds it (half
 * up, also below zero). Pure.
 *
 * @param float $value
 * @param int   $places
 * @return float
 */
function pg_picture_tr_js_round($value, $places)
{
    $k = pow(10, $places);

    return floor((float) $value * $k + 0.5) / $k;
}

/**
 * How much a translated text (w1 x h1 at the source's size) shrinks to fit
 * its source's box (w0 x h0): never grown, never under 70% of the size;
 * marked when it still does not fit there. Twin of M.languageFit(), run on
 * the same fixtures (tests/fixtures/picture_language/fit.json). Pure.
 *
 * @param float $w0
 * @param float $h0
 * @param float $w1
 * @param float $h1
 * @return array scale, mark
 */
function pg_picture_tr_language_fit($w0, $h0, $w1, $h1)
{
    // LANGUAGE_FLOOR of the editor's model.
    $floor = 0.7;
    $scale = 1;

    if (($w0 > 0) && ($w1 > $w0)) {
        $scale = min($scale, $w0 / $w1);
    }

    if (($h0 > 0) && ($h1 > $h0)) {
        $scale = min($scale, $h0 / $h1);
    }

    if ($scale < $floor - 1e-9) {
        return array('scale' => $floor, 'mark' => true);
    }

    return array('scale' => pg_picture_tr_js_round($scale, 4), 'mark' => false);
}

/**
 * A canvas document with a language's words in its layers, by layer id and
 * field; a text with no translation keeps the source's words. Answers the
 * new document and, by layer id, each text layer whose words changed as it
 * was, to fit the new words to. Twin of M.languageDoc(), run on the same
 * fixtures (tests/fixtures/picture_language/doc.json). Pure.
 *
 * @param mixed $doc   the document, arrays or objects
 * @param array $texts pg_picture_tr_language_texts(): id, field, text (null
 *                     where none is translated)
 * @return array|null doc, source; null without a list of layers
 */
function pg_picture_tr_language_doc($doc, $texts)
{
    $doc = is_object($doc) ? json_decode((string) json_encode($doc), true) : $doc;
    $layers = (is_array($doc) && isset($doc['layers'])) ? $doc['layers'] : null;

    // Array.isArray(): a list, not a keyed object.
    if (!is_array($layers) || ($layers && (array_keys($layers) !== range(0, count($layers) - 1)))) {
        return null;
    }

    $by = array();
    $source = array();

    foreach ((array) $texts as $text) {
        if (is_array($text) && isset($text['text']) && is_string($text['text']) && ($text['text'] !== '')) {
            $by[(isset($text['id']) ? (string) $text['id'] : '') . "\n" . (isset($text['field']) ? (string) $text['field'] : '')] = $text['text'];
        }
    }

    foreach ($layers as $i => $layer) {
        $type = (is_array($layer) && isset($layer['type'])) ? $layer['type'] : '';
        $field = ($type === 'text') ? 'text' : (($type === 'sticker') ? 'label' : '');
        $id = (is_array($layer) && isset($layer['id']) && is_scalar($layer['id'])) ? (string) $layer['id'] : '';
        $key = $id . "\n" . $field;

        if (($field === '') || ($id === '') || !array_key_exists($key, $by) || ($by[$key] === (isset($layer[$field]) ? $layer[$field] : null))) {
            continue;
        }

        if ($field === 'text') {
            $source[$id] = $layer;
        }

        $doc['layers'][$i][$field] = $by[$key];
    }

    return array('doc' => $doc, 'source' => $source);
}

/**
 * A language's document with each changed text fitted to its source's box,
 * as the Design Canvas's drawLanguage() fits it: the size shrunk by
 * pg_picture_tr_language_fit(), the box kept on the side its text is
 * aligned to (M.textFit(): the middle for centred text), its middle kept
 * upright. Pure but for $layout.
 *
 * @param array    $put    pg_picture_tr_language_doc()
 * @param callable $layout a text layer => its laid out box (w, h), null
 *                         when it cannot be laid out
 * @return array|null doc, fit (1 when a text did not fit at the smallest
 *                    size); null when a text cannot be laid out
 */
function pg_picture_tr_language_fitted($put, $layout)
{
    $doc = $put['doc'];
    $mark = false;

    foreach ($doc['layers'] as $i => $layer) {
        $id = (is_array($layer) && isset($layer['id']) && is_scalar($layer['id'])) ? (string) $layer['id'] : '';

        if (($id === '') || !isset($put['source'][$id])) {
            continue;
        }

        $src = $put['source'][$id];
        $before = call_user_func($layout, $src);
        $wide = call_user_func($layout, $layer);

        if (!$before || !$wide) {
            return null;
        }

        $fit = pg_picture_tr_language_fit($before['w'], $before['h'], $wide['w'], $wide['h']);
        $size = (isset($src['size']) && is_numeric($src['size']) && ((float) $src['size'] != 0)) ? (float) $src['size'] : 1.0;
        $layer['size'] = pg_picture_tr_js_round($size * $fit['scale'], 2);
        $after = call_user_func($layout, $layer);

        if (!$after) {
            return null;
        }

        $w0 = (isset($src['w']) && is_numeric($src['w'])) ? (float) $src['w'] : 0.0;
        $x = (isset($src['x']) && is_numeric($src['x'])) ? (float) $src['x'] : 0.0;
        $align = isset($src['align']) ? $src['align'] : '';

        if ($w0 && ($align === 'center')) {
            $x += ($w0 - $after['w']) / 2;
        } elseif ($w0 && ($align === 'right')) {
            $x += $w0 - $after['w'];
        }

        $layer['x'] = pg_picture_tr_js_round($x, 2);
        $layer['w'] = $after['w'];
        $layer['h'] = $after['h'];
        $layer['y'] = pg_picture_tr_js_round(((isset($src['y']) && is_numeric($src['y'])) ? (float) $src['y'] : 0.0) + ($before['h'] - $after['h']) / 2, 2);
        $mark = $mark || $fit['mark'];
        $doc['layers'][$i] = $layer;
    }

    return array('doc' => $doc, 'fit' => $mark ? 1 : 0);
}

/**
 * The box a text layer is laid out in by this server: its font file's own
 * letter widths, as pg_image_draw_plan() lays it out for drawing (a hidden
 * or clear layer measured as shown). Null when the server would not draw
 * it (no file of its face, a letter the file lacks, a script or an effect
 * it does not draw).
 *
 * @param array $layer
 * @param array $can   pg_image_draw_can()
 * @return array|null w, h
 */
function pg_picture_tr_server_layout($layer, $can)
{
    unset($layer['hidden']);
    $layer['opacity'] = 1;

    $plan = pg_image_draw_plan(array('artboard' => array('width' => 1, 'height' => 1), 'layers' => array($layer)), $can);
    $item = (($plan['error'] === '') && (count($plan['items']) === 1)) ? $plan['items'][0] : null;

    return ($item && isset($item['layout'])) ? array('w' => (float) $item['layout']['w'], 'h' => (float) $item['layout']['h']) : null;
}

/**
 * A language's picture of a canvas as this server would draw it: the
 * language's words in the layers, each changed text fitted with the
 * server's letter widths, then pg_image_draw_plan()'s verdict on that
 * document. Draws nothing; reads font files.
 *
 * @param mixed $doc   the live canvas document, arrays or objects
 * @param array $texts pg_picture_tr_language_texts()
 * @param array $can   pg_image_draw_can()
 * @param float $until the time the budget ends, 0 for none
 * @return array error ('' | kind | untranslated | a plan's error), plan,
 *               fit (1 when a text did not fit at the smallest size)
 */
function pg_picture_tr_server_plan($doc, $texts, $can, $until = 0.0)
{
    $none = array('error' => 'kind', 'plan' => null, 'fit' => 0);

    // A photo is drawn on its base by the save's steps: a browser's work.
    if (pg_picture_tr_doc_kind($doc) !== 'canvas') {
        return $none;
    }

    if (!$texts || pg_picture_tr_untranslated($texts)) {
        return array('error' => 'untranslated') + $none;
    }

    $put = pg_picture_tr_language_doc($doc, $texts);

    if (!$put) {
        return $none;
    }

    $fitted = pg_picture_tr_language_fitted($put, function ($layer) use ($can) {
        return pg_picture_tr_server_layout($layer, $can);
    });

    if (!$fitted) {
        // The reason the whole document gives for the text it cannot lay out.
        $why = pg_image_draw_plan($put['doc'], $can)['error'];

        return array('error' => ($why !== '') ? $why : 'text') + $none;
    }

    $plan = pg_image_draw_plan($fitted['doc'], $can, null, $until);

    return array('error' => $plan['error'], 'plan' => $plan, 'fit' => $fitted['fit']);
}

/**
 * One queue row drawn by the server and kept, when its file is a canvas
 * outside the Recycle Bin and no ERP document, every text is translated and
 * the server draws the whole document. The words and the document are read
 * again before the picture is kept: a save or a translation meanwhile
 * leaves the row for the next run, as a browser's stale stamp does.
 *
 * @param array $row   the queue's row: file_id, language, name, folder
 * @param array $can   pg_image_draw_can()
 * @param float $until the time this drawing's budget ends
 * @return string '' when drawn, else why the row was left
 */
function pg_picture_tr_server_draw_row($row, $can, $until)
{
    $file_id = (int) $row['file_id'];
    $language = (string) $row['language'];

    if (pg_folder_binned((int) $row['folder']) || pg_files_include_erp_document(array($file_id))) {
        return 'folder';
    }

    $live = pg_picture_tr_live_doc($file_id, (string) $row['name']);

    if (!$live || ($live['kind'] !== 'canvas')) {
        return 'kind';
    }

    $texts = pg_picture_tr_language_texts($file_id, $language);
    $stamp = pg_picture_tr_stamp($file_id, $language, $live['hash'], $texts);
    $drawn = pg_picture_tr_server_plan($live['doc'], $texts, $can, $until);

    if ($drawn['error'] !== '') {
        return $drawn['error'];
    }

    $plan = $drawn['plan'];
    $png = pg_image_draw_guarded(function () use ($plan, $until) {
        return pg_image_draw_paint($plan, $until);
    });
    $size = is_string($png) ? @getimagesizefromstring($png) : false;

    if (!$size || ($size[0] !== $plan['width']) || ($size[1] !== $plan['height']) || ($size[2] !== IMAGETYPE_PNG)) {
        return 'paint';
    }

    $path = FILE_DIRECTORY_PATH . '/picture-language.' . (function_exists('getmypid') ? getmypid() : uniqid()) . '.' . mt_rand() . '.tmp';

    if (@file_put_contents($path, $png) !== strlen($png)) {
        @unlink($path);

        return 'write';
    }

    // Made smaller where that keeps every pixel, as a browser's picture is.
    pg_process_image_file($path);

    $now = pg_picture_tr_live_doc($file_id, (string) $row['name']);

    if (!$now || !hash_equals($stamp, pg_picture_tr_stamp($file_id, $language, $now['hash'], pg_picture_tr_language_texts($file_id, $language)))) {
        @unlink($path);

        return 'changed';
    }

    $kept = pg_picture_tr_keep(array('id' => $file_id, 'name' => (string) $row['name'], 'folder' => (int) $row['folder']), $language,
        array('path' => $path, 'extension' => 'png'), $drawn['fit'], (string) $live['hash'], 0);

    @unlink($path);

    return ($kept[0]['status'] === 'success') ? '' : 'keep';
}

/**
 * The queue's rows of every folder drawn one after another inside a time
 * budget, with what it needs handed in. A query that finds nothing clears
 * the mark (when it is still the one read before it); a row starts only
 * while one drawing's time (pg_image_draw_limits()) is left; once a row is
 * drawn the queue's query is asked again, which clears the mark when
 * nothing is left. A row left to a panel keeps the mark.
 *
 * @param array         $languages the codes a picture is drawn in
 * @param float         $budget    seconds
 * @param callable      $query     SQL => rows, false when it failed
 * @param callable      $draw      (row, until) => '' when drawn, else why
 *                                 it was left
 * @param string|null   $path      the mark's file
 * @param callable|null $clock     => the time now in seconds
 * @return array rows (found), drawn, left, stopped (the budget ended first)
 */
function pg_picture_tr_draw_due_with($languages, $budget, $query, $draw, $path = null, $clock = null)
{
    $clock = $clock ? $clock : function () {
        return microtime(true);
    };
    $summary = array('rows' => 0, 'drawn' => 0, 'left' => 0, 'stopped' => false);
    $seen = pg_picture_tr_mark_token($path);
    $sql = pg_picture_tr_queue_sql($languages, 200);
    $rows = ($sql !== '') ? call_user_func($query, $sql) : array();

    if (!is_array($rows)) {
        return $summary;
    }

    if (!$rows) {
        pg_picture_tr_mark_clear($seen, $path);

        return $summary;
    }

    $summary['rows'] = count($rows);
    $one = pg_image_draw_limits()['seconds'];
    $end = call_user_func($clock) + $budget;

    foreach ($rows as $row) {
        $now = call_user_func($clock);

        if ($now + $one > $end) {
            $summary['stopped'] = true;
            break;
        }

        if (call_user_func($draw, $row, $now + $one) === '') {
            $summary['drawn']++;
        } else {
            $summary['left']++;
        }
    }

    if ($summary['drawn'] > 0) {
        pg_picture_tr_mark_check_with($languages, $query, $path);
    }

    return $summary;
}

/**
 * The scheduled job's draw: the waiting pictures this server draws whole,
 * inside a time budget. Nothing is read while the mark is clear, and
 * nothing is drawn where the server draws no shapes or no text.
 *
 * @param float $budget seconds
 * @return array rows, drawn, left, stopped
 */
function pg_picture_tr_draw_due($budget = 20)
{
    $none = array('rows' => 0, 'drawn' => 0, 'left' => 0, 'stopped' => false);

    if (!pg_picture_tr_marked()) {
        return $none;
    }

    $can = pg_image_draw_can();

    if (empty($can['shapes']) || empty($can['text']) || !pg_picture_tr_ready() || !pg_picture_tr_store_ready()) {
        return $none;
    }

    pg_tr_load();

    return pg_picture_tr_draw_due_with(pg_picture_tr_languages(), $budget, 'db_items', function ($row, $until) use ($can) {
        return pg_picture_tr_server_draw_row($row, $can, $until);
    });
}
