<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - an agent's edits to a canvas's board and scenes: its size by a
 * preset of the New image dialog or in pixels, the open scene's background,
 * and the scenes a presentation is made of (add, choose, remove, a
 * transition, a duration). Each is the PHP twin of the image editor's own
 * step (M.applyBoard(), M.sceneAdd(), M.sceneOpen(), M.sceneDelete(),
 * M.sceneSet() in assets/js/pg_image_editor_model.js), so the document is
 * one the editor opens as if a person had made it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The board sizes an agent names, as the New image dialog offers them
 * (M.BOARD_PRESETS).
 *
 * @return array name => [width, height]
 */
function ws_canvas_board_presets()
{
    return array(
        'square'   => array(1080, 1080),
        'portrait' => array(1080, 1350),
        'story'    => array(1080, 1920),
        'share'    => array(1200, 630),
        'banner'   => array(1920, 600),
    );
}

/**
 * The most scenes an agent's canvas holds. The editor holds 50
 * (M.SCENE_MAX); an agent stops sooner, so a presentation stays well under
 * the 1 MB a document may weigh.
 *
 * @return int
 */
function ws_canvas_board_scene_max()
{
    return 30;
}

/**
 * The actions of a scene operation.
 *
 * @return string[]
 */
function ws_canvas_board_scene_actions()
{
    return array('add', 'choose', 'remove', 'transition', 'duration');
}

/**
 * A scene's transition kinds (M.SCENE_TRANSITIONS).
 *
 * @return string[]
 */
function ws_canvas_board_transitions()
{
    return array('none', 'fade', 'slide', 'zoom', 'wipe');
}

/**
 * Math.round(): a half goes up, also below zero, where PHP's round() goes
 * away from zero. Pure.
 *
 * @param float|int $value
 * @return float
 */
function ws_canvas_board_round($value)
{
    return floor($value + 0.5);
}

/**
 * Is it a JSON number (typeof … === 'number')? Pure.
 *
 * @param mixed $value
 * @return bool
 */
function ws_canvas_board_is_number($value)
{
    return is_int($value) || is_float($value);
}

/**
 * A whole number of an operation within min and max, else null. Pure.
 *
 * @param mixed $value
 * @param int   $min
 * @param int   $max
 * @return int|null
 */
function ws_canvas_board_whole($value, $min, $max)
{
    if (!ws_canvas_board_is_number($value) || ((float) $value !== floor((float) $value)) || ($value < $min) || ($value > $max)) {
        return null;
    }

    return (int) $value;
}

/**
 * A layer moved by -dx, -dy, as M.mapLayer() moves it for a translation:
 * a line's ends, a drawing's points, a path's nodes and a box around its
 * centre, a turned box's angle written back in -180 to 180 when it had one.
 * Pure.
 *
 * @param mixed $layer decoded as arrays
 * @param int   $dx
 * @param int   $dy
 * @return mixed
 */
function ws_canvas_board_shift($layer, $dx, $dy)
{
    if (!is_array($layer)) {
        return $layer;
    }

    if (ws_canvas_board_is_number($layer['x1'] ?? null)) {
        foreach (array('x1', 'x2') as $key) {
            $layer[$key] = ($layer[$key] ?? 0) - $dx;
        }

        foreach (array('y1', 'y2') as $key) {
            $layer[$key] = ($layer[$key] ?? 0) - $dy;
        }
    }

    if (isset($layer['pts']) && is_array($layer['pts']) && (array_values($layer['pts']) === $layer['pts'])) {
        foreach ($layer['pts'] as $i => $pt) {
            $layer['pts'][$i] = array(($pt[0] ?? 0) - $dx, ($pt[1] ?? 0) - $dy);
        }
    }

    if (isset($layer['nodes']) && is_array($layer['nodes']) && (array_values($layer['nodes']) === $layer['nodes'])) {
        foreach ($layer['nodes'] as $i => $node) {
            foreach (array('x', 'ix', 'ox') as $key) {
                $layer['nodes'][$i][$key] = ($node[$key] ?? 0) - $dx;
            }

            foreach (array('y', 'iy', 'oy') as $key) {
                $layer['nodes'][$i][$key] = ($node[$key] ?? 0) - $dy;
            }
        }
    }

    if (ws_canvas_board_is_number($layer['x'] ?? null) && ws_canvas_board_is_number($layer['w'] ?? null)) {
        $w = $layer['w'];
        $h = $layer['h'] ?? 0;

        // The editor moves the centre and puts the box back around it.
        $layer['x'] = ($layer['x'] + $w / 2 - $dx) - $w / 2;
        $layer['y'] = (($layer['y'] ?? 0) + $h / 2 - $dy) - $h / 2;

        if (!in_array((string) ($layer['type'] ?? ''), array('text', 'sticker', 'image', 'glyph'), true)) {
            $rot = ws_canvas_board_round((0 + (ws_canvas_board_is_number($layer['rot'] ?? null) ? $layer['rot'] : 0)) * 1e6) / 1e6;
            $rot = fmod(fmod($rot, 360) + 360, 360);
            $rot = ($rot > 180) ? $rot - 360 : $rot;

            if ($rot || array_key_exists('rot', $layer)) {
                $layer['rot'] = ($rot == (int) $rot) ? (int) $rot : $rot;
            }
        }
    }

    return $layer;
}

/**
 * The document with its board at a new size around its middle (the Canvas
 * tool's centre anchor, M.boardAnchored() and M.applyBoard()): the layers
 * of every scene move the other way, so the artwork stays in the middle of
 * the new board. Pure.
 *
 * @param array $doc
 * @param array $op  preset, or width and height
 * @return array|string the document, or the refusal (size)
 */
function ws_canvas_board_size($doc, $op)
{
    $presets = ws_canvas_board_presets();
    $has_preset = array_key_exists('preset', $op);
    $has_size = array_key_exists('width', $op) || array_key_exists('height', $op);

    if ($has_preset === $has_size) {
        return 'size';
    }

    if ($has_preset) {
        if (!is_string($op['preset']) || !isset($presets[$op['preset']])) {
            return 'size';
        }

        list($w, $h) = $presets[$op['preset']];
    } else {
        $w = ws_canvas_board_whole($op['width'] ?? null, 16, 8000);
        $h = ws_canvas_board_whole($op['height'] ?? null, 16, 8000);

        if (($w === null) || ($h === null)) {
            return 'size';
        }
    }

    $w0 = (int) ($doc['artboard']['width'] ?? $w);
    $h0 = (int) ($doc['artboard']['height'] ?? $h);
    $dx = (int) ws_canvas_board_round(($w0 - $w) * 0.5);
    $dy = (int) ws_canvas_board_round(($h0 - $h) * 0.5);

    $doc['artboard']['width'] = $w;
    $doc['artboard']['height'] = $h;

    if ($dx || $dy) {
        $shift = function ($layers) use ($dx, $dy) {
            return array_map(function ($layer) use ($dx, $dy) {
                return ws_canvas_board_shift($layer, $dx, $dy);
            }, $layers);
        };

        $doc['layers'] = $shift((array) ($doc['layers'] ?? array()));

        foreach ((array) ($doc['scenes'] ?? array()) as $i => $scene) {
            if (is_array($scene) && isset($scene['layers']) && is_array($scene['layers'])) {
                $doc['scenes'][$i]['layers'] = $shift($scene['layers']);
            }
        }
    }

    return $doc;
}

/**
 * The document with the open scene's background: #rrggbb or transparent.
 * Pure.
 *
 * @param array $doc
 * @param array $op
 * @return array|string the document, or the refusal (background)
 */
function ws_canvas_board_background($doc, $op)
{
    $value = $op['background'] ?? null;

    if (!is_string($value) || !preg_match('/^(#[0-9a-fA-F]{6}|transparent)$/', $value)) {
        return 'background';
    }

    $doc['artboard']['background'] = strtolower($value);

    return $doc;
}

/**
 * The document's motion as the editor reads it (M.docMotion()). Pure.
 *
 * @param array $doc
 * @return array duration, loop, base
 */
function ws_canvas_board_motion($doc)
{
    $m = (isset($doc['motion']) && is_array($doc['motion'])) ? $doc['motion'] : array();
    $duration = (ws_canvas_board_is_number($m['duration'] ?? null) && $m['duration']) ? ws_canvas_board_round($m['duration']) : 5000;
    $loop = (ws_canvas_board_is_number($m['loop'] ?? null) && $m['loop']) ? ws_canvas_board_round($m['loop']) : 0;

    return array(
        'duration' => (int) max(1000, min(60000, $duration)),
        'loop'     => ($loop > 0) ? (int) $loop : 0,
        'base'     => in_array($m['base'] ?? null, array('none', 'zoomin', 'zoomout', 'panleft', 'panright'), true) ? $m['base'] : 'none',
    );
}

/**
 * A scene's duration in ms (M.sceneDuration()). Pure.
 *
 * @param mixed $ms
 * @return int
 */
function ws_canvas_board_scene_duration($ms)
{
    $ms = (ws_canvas_board_is_number($ms) && $ms) ? ws_canvas_board_round($ms) : 3000;

    return (int) max(1000, min(60000, $ms));
}

/**
 * A transition to the next scene with its defaults (M.sceneTransition()).
 * Pure.
 *
 * @param mixed $tr
 * @return array kind, ms
 */
function ws_canvas_board_transition($tr)
{
    $tr = is_array($tr) ? $tr : array();
    $ms = (ws_canvas_board_is_number($tr['ms'] ?? null) && $tr['ms']) ? $tr['ms'] : 600;

    return array(
        'kind' => in_array($tr['kind'] ?? null, ws_canvas_board_transitions(), true) ? $tr['kind'] : 'none',
        'ms'   => (int) max(100, min(2000, ws_canvas_board_round($ms / 100) * 100)),
    );
}

/**
 * How many scenes the document holds: 1 without doc.scenes. Pure.
 *
 * @param array $doc
 * @return int
 */
function ws_canvas_board_scene_count($doc)
{
    return (isset($doc['scenes']) && is_array($doc['scenes'])) ? max(1, count($doc['scenes'])) : 1;
}

/**
 * The place in doc.scenes of a scene named by its id, else by a name one
 * scene alone has; the open scene for null; -1 when none. Pure.
 *
 * @param array $doc
 * @param mixed $ref
 * @return int
 */
function ws_canvas_board_scene_index($doc, $ref)
{
    $list = (isset($doc['scenes']) && is_array($doc['scenes'])) ? array_values($doc['scenes']) : array();

    if ($ref === null) {
        $ref = $doc['scene'] ?? null;
    }

    if (!is_string($ref) || ($ref === '')) {
        return -1;
    }

    $named = array();

    foreach ($list as $i => $scene) {
        if (($scene['id'] ?? null) === $ref) {
            return $i;
        }

        if (($scene['name'] ?? null) === $ref) {
            $named[] = $i;
        }
    }

    return (count($named) === 1) ? $named[0] : -1;
}

/**
 * Opens the scene id (M.sceneOpen()): the open one's layers, background
 * and duration go into its entry, the other's come out of it. Pure.
 *
 * @param array  $doc
 * @param string $id
 * @return array
 */
function ws_canvas_board_scene_open($doc, $id)
{
    $from = ws_canvas_board_scene_index($doc, null);
    $to = -1;

    foreach ((array) ($doc['scenes'] ?? array()) as $i => $scene) {
        if (($scene['id'] ?? null) === $id) {
            $to = $i;
        }
    }

    if (($to < 0) || ($to === $from)) {
        return $doc;
    }

    $motion = ws_canvas_board_motion($doc);
    $board = (array) ($doc['artboard'] ?? array());

    if ($from >= 0) {
        $doc['scenes'][$from]['layers'] = (array) ($doc['layers'] ?? array());
        $doc['scenes'][$from]['background'] = is_string($board['background'] ?? null) ? $board['background'] : '#ffffff';
        $doc['scenes'][$from]['duration'] = $motion['duration'];
    }

    $next = $doc['scenes'][$to];
    $doc['layers'] = (isset($next['layers']) && is_array($next['layers'])) ? array_values($next['layers']) : array();

    if (isset($doc['artboard'])) {
        $doc['artboard']['background'] = is_string($next['background'] ?? null) ? $next['background'] : '#ffffff';
    }

    $doc['motion'] = array_merge((isset($doc['motion']) && is_array($doc['motion'])) ? $doc['motion'] : $motion, array('duration' => ws_canvas_board_scene_duration($next['duration'] ?? null)));
    unset($doc['scenes'][$to]['layers'], $doc['scenes'][$to]['background'], $doc['scenes'][$to]['duration']);
    $doc['scene'] = $id;

    return $doc;
}

/**
 * A new scene after the open one, opened (M.sceneAdd()): empty, or a copy
 * of the open one with its duration and transition. The document's own
 * content becomes its first scene first (M.sceneStart()). Pure.
 *
 * @param array $doc
 * @param bool  $copy
 * @return array
 */
function ws_canvas_board_scene_add($doc, $copy)
{
    if (!isset($doc['scenes']) || !is_array($doc['scenes']) || !$doc['scenes']) {
        $doc['scenes'] = array(array('id' => 's1', 'name' => '', 'transition' => ws_canvas_board_transition(null)));
        $doc['scene'] = 's1';
        $doc['motion'] = ws_canvas_board_motion($doc);
    }

    $doc['scenes'] = array_values($doc['scenes']);
    $at = ws_canvas_board_scene_index($doc, null);
    $taken = array_column($doc['scenes'], 'id');
    $n = count($doc['scenes']) + 1;

    while (in_array('s' . $n, $taken, true)) {
        $n++;
    }

    $board = (array) ($doc['artboard'] ?? array());
    $open = ($at >= 0) ? $doc['scenes'][$at] : array();

    array_splice($doc['scenes'], $at + 1, 0, array(array(
        'id'         => 's' . $n,
        'name'       => '',
        'transition' => ws_canvas_board_transition($copy ? ($open['transition'] ?? null) : null),
        'layers'     => $copy ? (array) ($doc['layers'] ?? array()) : array(),
        'background' => is_string($board['background'] ?? null) ? $board['background'] : '#ffffff',
        'duration'   => $copy ? ws_canvas_board_motion($doc)['duration'] : 3000,
    )));

    return ws_canvas_board_scene_open($doc, 's' . $n);
}

/**
 * Deletes a scene (M.sceneDelete()), opening its neighbour when it was
 * open; with one scene left the document has none. Pure.
 *
 * @param array $doc
 * @param int   $at  its place in doc.scenes
 * @return array
 */
function ws_canvas_board_scene_delete($doc, $at)
{
    $doc['scenes'] = array_values($doc['scenes']);
    $id = $doc['scenes'][$at]['id'];

    if (($doc['scene'] ?? null) === $id) {
        $doc = ws_canvas_board_scene_open($doc, $doc['scenes'][($at + 1 < count($doc['scenes'])) ? $at + 1 : $at - 1]['id']);
    }

    array_splice($doc['scenes'], $at, 1);

    if (count($doc['scenes']) === 1) {
        unset($doc['scenes'], $doc['scene']);
    }

    return $doc;
}

/**
 * The document with a scene operation applied: action add (copy, name,
 * duration optional), choose (scene), remove (scene), transition (scene
 * optional, the open one; kind; ms 100 to 2000) or duration (scene
 * optional; duration 1000 to 60000 ms). A scene is named by its id or by
 * a name one scene alone has. Pure.
 *
 * @param array $doc
 * @param array $op
 * @return array|string the document, or the refusal (scene, scenes)
 */
function ws_canvas_board_scene($doc, $op)
{
    $action = $op['action'] ?? null;

    if (!is_string($action) || !in_array($action, ws_canvas_board_scene_actions(), true)) {
        return 'scene';
    }

    $duration = null;

    if (array_key_exists('duration', $op)) {
        $duration = ws_canvas_board_whole($op['duration'], 1000, 60000);

        if ($duration === null) {
            return 'scene';
        }
    }

    if ($action === 'add') {
        if (ws_canvas_board_scene_count($doc) >= ws_canvas_board_scene_max()) {
            return 'scenes';
        }

        if (array_key_exists('copy', $op) && !is_bool($op['copy'])) {
            return 'scene';
        }

        $name = array_key_exists('name', $op) ? (is_string($op['name']) ? trim($op['name']) : null) : '';

        if (($name === null) || (mb_strlen($name) > 80)) {
            return 'scene';
        }

        foreach ((array) ($doc['scenes'] ?? array()) as $scene) {
            if (($name !== '') && ((($scene['id'] ?? null) === $name) || (($scene['name'] ?? null) === $name))) {
                return 'scene';
            }
        }

        $doc = ws_canvas_board_scene_add($doc, !empty($op['copy']));
        $at = ws_canvas_board_scene_index($doc, null);
        $doc['scenes'][$at]['name'] = $name;

        if ($duration !== null) {
            $doc['motion']['duration'] = ws_canvas_board_scene_duration($duration);
        }

        return $doc;
    }

    $ref = array_key_exists('scene', $op) ? $op['scene'] : null;

    if (($ref !== null) && !is_string($ref)) {
        return 'scene';
    }

    $at = ws_canvas_board_scene_index($doc, $ref);

    // A canvas of one scene has no scene to choose, remove or lead out of.
    if (($at < 0) && (($action !== 'duration') || ($ref !== null))) {
        return 'scene';
    }

    if ($action === 'choose') {
        return ws_canvas_board_scene_open($doc, $doc['scenes'][$at]['id']);
    }

    if ($action === 'remove') {
        return ws_canvas_board_scene_delete($doc, $at);
    }

    if ($action === 'duration') {
        if ($duration === null) {
            return 'scene';
        }

        // The open scene's duration is the document's (M.sceneSet()).
        if (($at < 0) || (($doc['scenes'][$at]['id'] ?? null) === ($doc['scene'] ?? null))) {
            $doc['motion'] = array_merge(ws_canvas_board_motion($doc), array('duration' => ws_canvas_board_scene_duration($duration)));
        } else {
            $doc['scenes'][$at]['duration'] = ws_canvas_board_scene_duration($duration);
        }

        return $doc;
    }

    // transition: the move from this scene into the next one.
    $kind = $op['kind'] ?? null;
    $ms = array_key_exists('ms', $op) ? ws_canvas_board_whole($op['ms'], 100, 2000) : null;

    if (!is_string($kind) || !in_array($kind, ws_canvas_board_transitions(), true) || (array_key_exists('ms', $op) && ($ms === null))) {
        return 'scene';
    }

    $patch = array('kind' => $kind);

    if ($ms !== null) {
        $patch['ms'] = $ms;
    }

    $doc['scenes'][$at]['transition'] = ws_canvas_board_transition(array_merge(ws_canvas_board_transition($doc['scenes'][$at]['transition'] ?? null), $patch));

    return $doc;
}

/**
 * The scenes as an agent reads them, in playing order: id, name, whether
 * it is open, its number of layers, background, duration and the
 * transition into the next one; an empty list for a canvas of one scene.
 * Pure.
 *
 * @param mixed $doc decoded as arrays
 * @return array[]
 */
function ws_canvas_board_scenes_read($doc)
{
    if (!is_array($doc) || (ws_canvas_board_scene_count($doc) < 2)) {
        return array();
    }

    $board = (array) ($doc['artboard'] ?? array());
    $motion = ws_canvas_board_motion($doc);
    $read = array();

    foreach (array_values($doc['scenes']) as $scene) {
        if (!is_array($scene)) {
            continue;
        }

        $open = (($scene['id'] ?? null) === ($doc['scene'] ?? null));
        $layers = $open ? ($doc['layers'] ?? array()) : ($scene['layers'] ?? array());
        $background = $open ? ($board['background'] ?? null) : ($scene['background'] ?? null);

        $read[] = array(
            'id'         => (string) ($scene['id'] ?? ''),
            'name'       => (isset($scene['name']) && is_string($scene['name']) && ($scene['name'] !== '')) ? $scene['name'] : null,
            'open'       => $open,
            'layers'     => is_array($layers) ? count($layers) : 0,
            'background' => is_string($background) ? $background : '#ffffff',
            'duration'   => $open ? $motion['duration'] : ws_canvas_board_scene_duration($scene['duration'] ?? null),
            'transition' => ws_canvas_board_transition($scene['transition'] ?? null),
        );
    }

    return $read;
}
