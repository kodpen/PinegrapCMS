<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Canvas templates: canvases of Files a person marked as templates ("Save
 * as template" in the image editor), which an agent lists and fills to make
 * a Workspace canvas. The list is one config_text row holding file ids, so
 * marking a template needs no column.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/config_text.php');

/**
 * The most templates a site keeps.
 */
define('PG_CANVAS_TEMPLATES_MAX', 50);

/**
 * The config_text row of the list.
 */
define('PG_CANVAS_TEMPLATES_KEY', 'canvas_templates');

/**
 * The file ids a stored list holds: positive whole numbers, each once, at
 * most PG_CANVAS_TEMPLATES_MAX, in their order. Pure.
 *
 * @param mixed $text the row's value
 * @return int[]
 */
function pg_canvas_templates_parse($text)
{
    $list = is_string($text) ? json_decode($text, true) : null;
    $ids = array();

    foreach (is_array($list) ? $list : array() as $id) {
        if (is_int($id) && ($id > 0) && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }

    return array_slice($ids, 0, PG_CANVAS_TEMPLATES_MAX);
}

/**
 * The list with a file put in (on) or taken out, or null when it is full.
 * Pure.
 *
 * @param int[] $ids
 * @param int   $file_id
 * @param bool  $on
 * @return int[]|null
 */
function pg_canvas_templates_toggle($ids, $file_id, $on)
{
    $ids = array_values(array_diff(array_map('intval', (array) $ids), array((int) $file_id)));

    if (!$on) {
        return $ids;
    }

    if (count($ids) >= PG_CANVAS_TEMPLATES_MAX) {
        return null;
    }

    $ids[] = (int) $file_id;

    return $ids;
}

/**
 * The file ids of the site's templates.
 *
 * @return int[]
 */
function pg_canvas_templates_ids()
{
    return pg_canvas_templates_parse(pg_config_text(PG_CANVAS_TEMPLATES_KEY));
}

/**
 * Keeps the list.
 *
 * @param int[] $ids
 * @return bool
 */
function pg_canvas_templates_save($ids)
{
    return (bool) pg_config_text_save(PG_CANVAS_TEMPLATES_KEY, json_encode(array_values(array_map('intval', $ids))));
}

/**
 * What an agent reads of a template's document: the artboard's size and
 * background, and its named layers with their kinds (text, picture, shape,
 * icon, svg or the editor's other kinds) from the bottom up. Pure.
 *
 * @param mixed $doc decoded as arrays
 * @return array width, height, background, layers [name, kind, text]
 */
function pg_canvas_template_summary($doc)
{
    $board = (is_array($doc) && isset($doc['artboard']) && is_array($doc['artboard'])) ? $doc['artboard'] : array();
    $layers = array();

    foreach ((is_array($doc) && isset($doc['layers']) && is_array($doc['layers'])) ? $doc['layers'] : array() as $layer) {
        if (!is_array($layer) || !isset($layer['name']) || !is_string($layer['name']) || ($layer['name'] === '')) {
            continue;
        }

        $kind = pg_canvas_template_kind($layer);

        $layers[] = array(
            'name' => $layer['name'],
            'kind' => $kind,
            'text' => ($kind === 'text') ? (string) ($layer['text'] ?? '') : null,
        );
    }

    return array(
        'width'      => (int) ($board['width'] ?? 0),
        'height'     => (int) ($board['height'] ?? 0),
        'background' => (isset($board['background']) && is_string($board['background'])) ? $board['background'] : '#ffffff',
        'layers'     => $layers,
    );
}

/**
 * A layer's kind as an agent names it: text, picture (the editor's image),
 * shape (rect, ellipse, line), icon (a glyph), else the editor's own type.
 * Pure.
 *
 * @param array $layer
 * @return string
 */
function pg_canvas_template_kind($layer)
{
    $type = (string) ($layer['type'] ?? '');
    $kinds = array('text' => 'text', 'image' => 'picture', 'rect' => 'shape', 'ellipse' => 'shape', 'line' => 'shape', 'glyph' => 'icon');

    return $kinds[$type] ?? $type;
}

/**
 * The templates as they are now: files still there that are still canvases,
 * with their name and summary. A file gone or changed outside the editor is
 * left out, not taken off the list.
 *
 * @return array[] id, name, summary
 */
function pg_canvas_templates_rows()
{
    $ids = pg_canvas_templates_ids();
    $rows = array();

    if (empty($ids) || !function_exists('pg_image_editor_canvas_open_doc')) {
        return $rows;
    }

    $names = array();

    foreach ((array) db_items("SELECT id, name FROM files WHERE id IN (" . implode(',', $ids) . ")") as $file) {
        $names[(int) $file['id']] = (string) $file['name'];
    }

    foreach ($ids as $id) {
        $doc = isset($names[$id]) ? pg_image_editor_canvas_open_doc($id) : null;

        if ($doc) {
            $rows[] = array('id' => $id, 'name' => $names[$id], 'summary' => pg_canvas_template_summary(json_decode(json_encode($doc), true)));
        }
    }

    return $rows;
}

/**
 * Marks a canvas file as a template or takes the mark off. The caller has
 * checked that the person may edit the file.
 *
 * @param int  $file_id
 * @param bool $on
 * @return array ok, error, template (whether it is one now)
 */
function pg_canvas_template_mark($file_id, $on)
{
    $file_id = (int) $file_id;

    if ($on && !pg_image_editor_canvas_open_doc($file_id)) {
        return array('ok' => false, 'error' => lang('Only a canvas saved with its layers can be a template.'), 'template' => false);
    }

    // Read and written as one claim, so two people marking at once do not
    // drop each other's template; a lost claim reads again.
    for ($try = 0; $try < 3; $try++) {
        $read = pg_schema_has('config_text') ? (string) db_value("SELECT value FROM config_text WHERE name = '" . e(PG_CANVAS_TEMPLATES_KEY) . "'") : '';
        $ids = pg_canvas_templates_toggle(pg_canvas_templates_parse($read), $file_id, (bool) $on);

        if ($ids === null) {
            return array('ok' => false, 'error' => lang(array('string' => 'A site keeps at most {var:1} templates.', 'vars' => PG_CANVAS_TEMPLATES_MAX)), 'template' => false);
        }

        $value = json_encode($ids);

        if (($value === $read) || pg_config_text_claim(PG_CANVAS_TEMPLATES_KEY, $read, $value)) {
            return array('ok' => true, 'error' => '', 'template' => (bool) $on);
        }
    }

    return array('ok' => false, 'error' => lang('Sorry, the file could not be saved.'), 'template' => !$on);
}
