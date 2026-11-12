<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a canvas made from a template (includes/fn/canvas_templates.php):
 * the template's document with the words and pictures an agent gives for its
 * named layers. A text layer takes words, a picture layer a file; a name the
 * template does not have, or one of another kind, is refused with the names
 * it has.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/canvas_templates.php');

/**
 * The most names one fill gives.
 */
define('WS_CANVAS_FILL_MAX', 50);

/**
 * A template's document with the fill laid in. Pure but for the resolver.
 *
 * The document's own marks go: an agent's picture and maker are set by the
 * caller, and the template's versions are not the new canvas's.
 *
 * @param array    $doc      the template's document, decoded as arrays
 * @param mixed    $fill     name => words (text layer), or a file (picture
 *                           layer): its id, its address, or {file_id|file}
 * @param callable|null $resolve op => file (id, url) or null
 *                           (ws_canvas_write_file_resolver())
 * @return array error ('' | fill | name | kind | text | picture), name (the
 *               one refused), names (the template's named layers as
 *               "name (kind)"), doc
 */
function ws_canvas_template_fill($doc, $fill, $resolve = null)
{
    $names = array();

    foreach (pg_canvas_template_summary($doc)['layers'] as $layer) {
        $names[] = $layer['name'] . ' (' . $layer['kind'] . ')';
    }

    $fail = function ($error, $name = '') use ($names) {
        return array('error' => $error, 'name' => (string) $name, 'names' => $names, 'doc' => null);
    };

    unset($doc['picture'], $doc['by']);

    if (($fill === null) || ($fill === array())) {
        return array('error' => '', 'name' => '', 'names' => $names, 'doc' => $doc);
    }

    if (!is_array($fill) || (array_values($fill) === $fill) || (count($fill) > WS_CANVAS_FILL_MAX)) {
        return $fail('fill');
    }

    foreach ($fill as $name => $value) {
        $name = (string) $name;
        $at = ws_canvas_template_layer($doc, $name);

        if ($at < 0) {
            return $fail('name', $name);
        }

        $kind = pg_canvas_template_kind($doc['layers'][$at]);

        if ($kind === 'text') {
            if (!is_string($value) || (mb_strlen($value) > 2000)) {
                return $fail('text', $name);
            }

            $doc['layers'][$at]['text'] = $value;
            continue;
        }

        if ($kind !== 'picture') {
            return $fail('kind', $name);
        }

        if (is_int($value)) {
            $op = array('file_id' => $value);
        } elseif (is_string($value)) {
            $op = array('file' => $value);
        } else {
            $op = is_array($value) ? $value : array();
        }

        $found = $resolve ? call_user_func($resolve, $op) : null;

        if (!is_array($found) || empty($found['id']) || !isset($found['url'])) {
            return $fail('picture', $name);
        }

        $doc['layers'][$at]['src'] = (string) $found['url'];
        $doc['layers'][$at]['fileId'] = (int) $found['id'];
        unset($doc['layers'][$at]['fileName']);
    }

    return array('error' => '', 'name' => '', 'names' => $names, 'doc' => $doc);
}

/**
 * The layer one name alone names in a document; -1 otherwise. Pure.
 *
 * @param array  $doc
 * @param string $name
 * @return int
 */
function ws_canvas_template_layer($doc, $name)
{
    $found = array();

    foreach ((array) ($doc['layers'] ?? array()) as $i => $layer) {
        if (is_array($layer) && isset($layer['name']) && ((string) $layer['name'] === $name) && ($name !== '')) {
            $found[] = (int) $i;
        }
    }

    return (count($found) === 1) ? $found[0] : -1;
}

/**
 * The words of a refusal of ws_canvas_template_fill().
 *
 * @param array $result
 * @return string
 */
function ws_canvas_template_fill_error_text($result)
{
    $names = empty($result['names']) ? lang('(none)') : implode(', ', $result['names']);

    switch ($result['error']) {
        case 'fill':
            return lang(array('string' => 'fill is an object of at most {var:1} layer names, each with its words or its picture.', 'vars' => WS_CANVAS_FILL_MAX));
        case 'name':
            return lang(array('string' => 'The template has no layer named {var:1}. Its named layers: {var:2}.', 'vars' => array($result['name'], $names)));
        case 'kind':
            return lang(array('string' => 'The layer {var:1} is neither text nor a picture, so it is not filled. Its named layers: {var:2}.', 'vars' => array($result['name'], $names)));
        case 'text':
            return lang(array('string' => 'The text layer {var:1} takes words, at most 2000 letters. Its named layers: {var:2}.', 'vars' => array($result['name'], $names)));
        default:
            return lang(array('string' => 'The picture layer {var:1} takes a file of this channel, or with files:read a public file of the site, by its id or address. Its named layers: {var:2}.', 'vars' => array($result['name'], $names)));
    }
}

/**
 * A template's document by its file id, decoded as arrays; null when the
 * file is no template or no longer a canvas.
 *
 * @param int $file_id
 * @return array|null
 */
function ws_canvas_template_doc($file_id)
{
    if (!in_array((int) $file_id, pg_canvas_templates_ids(), true) || !function_exists('pg_image_editor_canvas_open_doc')) {
        return null;
    }

    $doc = pg_image_editor_canvas_open_doc((int) $file_id);

    return $doc ? json_decode(json_encode($doc), true) : null;
}
