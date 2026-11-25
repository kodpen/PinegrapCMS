<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Figma start's import: the ticked frames of a file become the pages of
 * a new design. Three steps, each its own request of the New design window,
 * so the window shows progress and no request runs long:
 *
 * - start: one GET nodes for the ticked frames, each frame converted
 *   (pg_figma_convert_frame()), the addresses of its pictures asked for in
 *   at most a few calls (image fills, vectors as SVG), and the colours and
 *   fonts found offered as the design's variables;
 * - frame: one frame's pictures and vectors downloaded by the server into
 *   the file manager (Figma/<file>), each with the size limit of an upload,
 *   only picture types, vectors through the SVG allowlist, at most
 *   pg_figma_max_pictures() an import;
 * - finish: a component placed on two frames or more becomes a shared
 *   component, the pages and the variables are kept for the editor, and the
 *   report says per frame what was not carried.
 *
 * Between the steps the work is kept in a file under data/temp named after
 * the session, so a large file does not ride on every request's session.
 * The token never leaves the server: the browser gets ids, names, counts
 * and the report.
 *
 * The pure parts are tested in tests/figma_start_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/figma_import.php');

/**
 * The most pictures and vectors one import fetches.
 */
function pg_figma_max_pictures()
{
    return 200;
}

// ── Pure ────────────────────────────────────────────────────────────────────

/**
 * The components placed on two frames or more, which become shared
 * components. Pure.
 *
 * @param array $frames converted frames (pg_figma_convert_frame()), each
 *                      with 'instances'
 * @return array component id => array('name', 'frames' => count), in the
 *               order they were first met
 */
function pg_figma_shared_plan($frames)
{
    $seen = array();

    foreach ((array) $frames as $index => $frame) {
        foreach (isset($frame['instances']) ? (array) $frame['instances'] : array() as $inst) {
            $cid = isset($inst['component_id']) ? (string) $inst['component_id'] : '';

            if ($cid === '') {
                continue;
            }

            if (!isset($seen[$cid])) {
                $name = isset($inst['component_name']) ? trim((string) $inst['component_name']) : '';
                $seen[$cid] = array('name' => ($name !== '') ? $name : 'Component', 'frames' => array());
            }

            $seen[$cid]['frames'][$index] = true;
        }
    }

    $plan = array();

    foreach ($seen as $cid => $entry) {
        if (count($entry['frames']) >= 2) {
            $plan[$cid] = array('name' => $entry['name'], 'frames' => count($entry['frames']));
        }
    }

    return $plan;
}

/**
 * The node of $tree with $id, depth first; null when none. Pure.
 */
function pg_figma_tree_node($tree, $id)
{
    if (isset($tree['_id']) && $tree['_id'] === $id) {
        return $tree;
    }

    foreach (isset($tree['children']) ? (array) $tree['children'] : array() as $child) {
        $found = pg_figma_tree_node($child, $id);

        if ($found !== null) {
            return $found;
        }
    }

    return null;
}

/**
 * $tree with the node $id given to $change (node => node). Pure.
 */
function pg_figma_tree_change($tree, $id, $change)
{
    if (isset($tree['_id']) && $tree['_id'] === $id) {
        return $change($tree);
    }

    if (!empty($tree['children'])) {
        foreach ($tree['children'] as $i => $child) {
            $tree['children'][$i] = pg_figma_tree_change($child, $id, $change);
        }
    }

    return $tree;
}

/**
 * The tree of each shared component: the first instance met, as the root
 * of a component tree. Pure.
 *
 * @param array $frames converted frames
 * @param array $plan   pg_figma_shared_plan()
 * @return array component id => array('tree', 'frame' => the index of the
 *               frame it was taken from, whose node ids it keeps)
 */
function pg_figma_shared_trees($frames, $plan)
{
    $trees = array();

    foreach ((array) $frames as $index => $frame) {
        foreach (isset($frame['instances']) ? (array) $frame['instances'] : array() as $inst) {
            $cid = (string) $inst['component_id'];

            if (!isset($plan[$cid]) || isset($trees[$cid])) {
                continue;
            }

            $node = pg_figma_tree_node($frame['tree'], $inst['node']);

            if ($node !== null) {
                unset($node['props']['customName']);
                $trees[$cid] = array('frame' => $index,
                    'tree' => array('_id' => 'sd_1', 'type' => 'root', 'props' => array(), 'children' => array($node)));
            }
        }
    }

    return $trees;
}

/**
 * $tree with each instance of a shared component placed as a shared_ref
 * (its children empty: the content is the component's row). Pure.
 *
 * @param array $tree      a converted frame's tree
 * @param array $instances its 'instances'
 * @param array $rows      component id => array('id', 'name')
 * @return array
 */
function pg_figma_place_shared($tree, $instances, $rows)
{
    foreach ((array) $instances as $inst) {
        $cid = (string) $inst['component_id'];

        if (!isset($rows[$cid])) {
            continue;
        }

        $row = $rows[$cid];
        $tree = pg_figma_tree_change($tree, $inst['node'], function ($node) use ($row) {
            return array('_id' => $node['_id'], 'type' => 'shared_ref',
                'props' => array('sharedId' => (int) $row['id'], 'sharedName' => (string) $row['name']), 'children' => array());
        });
    }

    return $tree;
}

/**
 * Whether a colour reads as a brand colour: opaque, with some hue, neither
 * near black nor near white. Pure.
 */
function pg_figma_brand_like($hex)
{
    if (!preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/', $hex, $m)) {
        return false;
    }

    $r = hexdec($m[1]) / 255;
    $g = hexdec($m[2]) / 255;
    $b = hexdec($m[3]) / 255;
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $light = ($max + $min) / 2;
    $sat = ($max === $min) ? 0 : ($max - $min) / (1 - abs(2 * $light - 1));

    return ($sat >= 0.25) && ($light >= 0.15) && ($light <= 0.85);
}

/**
 * The variables found in the converted frames, as the card offers them:
 * brand and second colour (the most used colours with a hue, a colour
 * whose style is named brand or primary first), the further colours, the
 * heading and body fonts (the largest and the most used text style). Each
 * says where it came from: the file's style name, or the frames' values.
 * Pure.
 *
 * @param array $frames converted frames
 * @return array brand, accent, more (list), heading, body: each
 *               array('value', 'source' => style name, '' for the frames)
 *               or null
 */
function pg_figma_found_variables($frames)
{
    $colors = array();
    $styles = array();

    foreach ((array) $frames as $frame) {
        foreach (isset($frame['colors']) ? (array) $frame['colors'] : array() as $c) {
            $v = (string) $c['value'];

            if (!isset($colors[$v])) {
                $colors[$v] = array('value' => $v, 'source' => '', 'count' => 0, 'order' => count($colors));
            }

            $colors[$v]['count'] += (int) $c['count'];

            if (($colors[$v]['source'] === '') && !empty($c['style'])) {
                $colors[$v]['source'] = (string) $c['style'];
            }
        }

        foreach (isset($frame['text_styles']) ? (array) $frame['text_styles'] : array() as $t) {
            if ((string) $t['font_family'] !== '') {
                $styles[] = $t;
            }
        }
    }

    $brand_like = array_values(array_filter($colors, function ($c) {
        return pg_figma_brand_like($c['value']);
    }));
    usort($brand_like, function ($a, $b) {
        $named_a = (bool) preg_match('/brand|primary/i', $a['source']);
        $named_b = (bool) preg_match('/brand|primary/i', $b['source']);

        if ($named_a !== $named_b) {
            return $named_a ? -1 : 1;
        }

        return ($b['count'] - $a['count']) ?: ($a['order'] - $b['order']);
    });

    $row = function ($c) {
        return $c ? array('value' => $c['value'], 'source' => $c['source']) : null;
    };

    $found = array(
        'brand'   => $row(isset($brand_like[0]) ? $brand_like[0] : null),
        'accent'  => $row(isset($brand_like[1]) ? $brand_like[1] : null),
        'more'    => array(),
        'heading' => null,
        'body'    => null,
    );

    foreach (array_slice($brand_like, 2, 6) as $c) {
        $found['more'][] = $row($c);
    }

    if ($styles) {
        $biggest = $styles[0];
        $most = $styles[0];

        foreach ($styles as $t) {
            if ($t['font_size'] > $biggest['font_size']) {
                $biggest = $t;
            }

            if ($t['count'] > $most['count']) {
                $most = $t;
            }
        }

        $found['heading'] = array('value' => (string) $biggest['font_family'], 'source' => (string) $biggest['style']);
        $found['body'] = array('value' => (string) $most['font_family'], 'source' => (string) $most['style']);
    }

    return $found;
}

/**
 * The new design's variables from what was found: the look's defaults with
 * the brand colour, the second colour and the two fonts in place. Pure.
 *
 * @param array  $found   pg_figma_found_variables()
 * @param string $look
 * @param string $palette
 * @return array array('model', 'css'); model null when nothing was found
 */
function pg_figma_variables_start($found, $look, $palette)
{
    $values = array();

    foreach (array('brand' => 'brand', 'accent' => 'accent') as $from => $key) {
        if (!empty($found[$from]['value'])) {
            $values[$key] = array('light' => $found[$from]['value'], 'dark' => $found[$from]['value']);
        }
    }

    foreach (array('heading' => 'font_heading', 'body' => 'font_body') as $from => $key) {
        if (!empty($found[$from]['value'])) {
            $values[$key] = '"' . $found[$from]['value'] . '", system-ui, sans-serif';
        }
    }

    if (!$values) {
        return array('model' => null, 'css' => '');
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/design_variables.php');
    $defaults = pg_design_variables_defaults($look, $palette);
    $model = $defaults;
    $model['values'] = array_merge($defaults['values'], $values);
    $model = pg_design_variables_normalize($model, $defaults);

    return array('model' => $model, 'css' => pg_design_variables_css($model, $defaults));
}

/**
 * What a report code of the converter means on the page, in a sentence.
 *
 * @param string $code
 * @return string
 */
function pg_figma_report_words($code)
{
    switch ((string) $code) {
        case 'type':
            return lang('A Figma-only layer (a sticky, connector, slice, widget or table) has no place on a page and was left out.');
        case 'rotation':
            return lang('The layer is rotated in Figma; on the page it stands straight.');
        case 'effect':
            return lang('A blur has no match on the page and was left out.');
        case 'fill':
            return lang('Only the top fill was kept; a radial or angular gradient, a video or a fill under it was left out.');
        case 'limit':
            return lang('The frame is too large; the rest of it from here on was left out.');
        case 'picture':
            return lang('The picture could not be fetched; the place stays empty.');
        case 'pictures':
            return lang('More pictures than one import fetches; the place stays empty.');
    }

    return lang('This layer could not be carried.');
}

/**
 * One frame of the report: its name, its kind (whole, notes or failed) and
 * the notes, each naming the layer and what happened. Pure but for lang().
 *
 * @param array $frame the import's frame (name, report, failed)
 * @return array name, state, notes => list of array('layer', 'text')
 */
function pg_figma_report_frame($frame)
{
    $notes = array();

    foreach (isset($frame['report']) ? (array) $frame['report'] : array() as $r) {
        $notes[] = array('layer' => (string) $r['name'], 'text' => pg_figma_report_words($r['code']));
    }

    return array(
        'name'  => (string) $frame['name'],
        'state' => !empty($frame['failed']) ? 'failed' : ($notes ? 'notes' : 'whole'),
        'notes' => $notes,
    );
}

/**
 * The file name a fetched picture is stored under: the frame's and the
 * layer's names as a slug, and the type's extension. Pure.
 */
function pg_figma_picture_name($frame, $layer, $ext)
{
    $slug = function ($s) {
        $s = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', (string) $s));

        return trim(substr($s, 0, 40), '-');
    };
    $name = trim($slug($frame) . '-' . $slug($layer), '-');

    return (($name !== '') ? $name : 'figma') . '.' . $ext;
}

/**
 * $tree with a fetched picture's address put in: an image node's src, or a
 * box's background-image. Pure.
 *
 * @param array  $tree
 * @param array  $picture the converter's entry (node, kind)
 * @param string $url     the file's address on the site
 * @return array
 */
function pg_figma_set_picture($tree, $picture, $url)
{
    return pg_figma_tree_change($tree, $picture['node'], function ($node) use ($picture, $url) {
        if ($picture['kind'] === 'background') {
            $style = isset($node['props']['inlineStyle']) ? (string) $node['props']['inlineStyle'] : '';
            $node['props']['inlineStyle'] = (($style !== '') ? $style . ';' : '') . 'background-image:url("' . str_replace(array('"', '\\', "\n", "\r"), '', $url) . '")';
        } else {
            $node['props']['src'] = $url;
        }

        return $node;
    });
}

/**
 * The picture type a downloaded file is, from its first bytes: png, jpg,
 * gif, webp or svg; '' for anything else, which is not stored. Pure.
 */
function pg_figma_picture_type($head)
{
    $head = (string) $head;

    if (strncmp($head, "\x89PNG", 4) === 0) {
        return 'png';
    }

    if (strncmp($head, "\xFF\xD8\xFF", 3) === 0) {
        return 'jpg';
    }

    if (strncmp($head, 'GIF8', 4) === 0) {
        return 'gif';
    }

    if ((strncmp($head, 'RIFF', 4) === 0) && (substr($head, 8, 4) === 'WEBP')) {
        return 'webp';
    }

    if (preg_match('/^\s*(<\?xml[^>]*>\s*)?<svg[\s>]/i', $head)) {
        return 'svg';
    }

    return '';
}

// ── The import ──────────────────────────────────────────────────────────────

/**
 * The file that keeps an import between its steps: under data/temp, named
 * after the session and the import, so another session cannot open it.
 */
function pg_figma_import_path($id)
{
    if (!preg_match('/^[a-f0-9]{16}$/', (string) $id)) {
        return '';
    }

    return PG_FUNCTIONS_DIR . '/data/temp/figma_' . sha1(session_id() . '|' . $id) . '.json';
}

function pg_figma_import_load($id)
{
    $path = pg_figma_import_path($id);
    $data = ($path !== '' && is_file($path)) ? json_decode((string) file_get_contents($path), true) : null;

    return is_array($data) ? $data : null;
}

function pg_figma_import_keep($id, $data)
{
    $path = pg_figma_import_path($id);

    return ($path !== '') && (@file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false);
}

/**
 * Keeps a frame step's work, unless "Stop" removed the import while the
 * step was fetching: Stop deleted the files the work file listed then, so
 * the files this step fetched are deleted here and nothing is written back.
 *
 * @param string $id
 * @param array  $data    the import with this step's work
 * @param array  $fetched the files this step stored, each array('id', 'name')
 * @return bool false when the import was stopped
 */
function pg_figma_import_keep_step($id, $data, $fetched)
{
    if (pg_figma_import_load($id) === null) {
        if ($fetched) {
            require_once(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_bin.php');

            foreach ($fetched as $file) {
                pg_delete_file_record(array('id' => (int) $file['id'], 'name' => (string) $file['name']));
            }
        }

        return false;
    }

    return pg_figma_import_keep($id, $data);
}

/**
 * Starts the import of the ticked frames: reads them in one GET nodes call,
 * converts each, asks for the addresses of the pictures (one call for the
 * image fills, one per hundred vectors), and keeps the work.
 *
 * @param string $key a file key read by pg_figma_read_file()
 * @param array  $ids the ticked frame ids
 * @return array ok, id, frames => list of array('id', 'name'), found
 *               (pg_figma_found_variables()), error
 */
function pg_figma_import_start($key, $ids)
{
    $fail = function ($kind, $retry = 0) {
        return array('ok' => false, 'error' => array('kind' => $kind, 'retry' => $retry));
    };
    $connection = pg_figma_connection();

    if (!$connection || !isset($_SESSION['pg_figma_files'][$key]['list'])) {
        return $fail('not_connected');
    }

    $list = $_SESSION['pg_figma_files'][$key]['list'];
    $known = pg_figma_frame_ids($list);
    $ids = array_values(array_unique(array_intersect(array_map('strval', (array) $ids), $known)));

    if (!$ids || (count($ids) > pg_figma_max_frames())) {
        return $fail('other');
    }

    @set_time_limit(120);
    $read = pg_figma_get('/v1/files/' . rawurlencode($key) . '/nodes?ids=' . rawurlencode(implode(',', $ids)), $connection['token'], 90);

    if (!$read['ok']) {
        return array('ok' => false, 'error' => $read['error']);
    }

    $frames = array();
    $vectors = array();
    $fills = false;
    $budget = pg_figma_max_pictures();

    foreach ($ids as $fid) {
        $answer = pg_figma_answer_frame($read['data'], $fid);
        $frame = $answer ? pg_figma_convert_frame($answer['document'], $answer['styles'], $answer['components']) : null;

        if ($frame === null) {
            $frames[] = array('id' => $fid, 'name' => $fid, 'failed' => true, 'tree' => null, 'pictures' => array(),
                'instances' => array(), 'colors' => array(), 'text_styles' => array(), 'report' => array());
            continue;
        }

        $frame['id'] = $fid;
        $frame['failed'] = false;

        // Over the import's picture budget a place stays empty, and says so.
        foreach ($frame['pictures'] as $i => $p) {
            if ($budget <= 0) {
                $frame['report'][] = array('figma' => $p['figma'], 'name' => '', 'code' => 'pictures');
                unset($frame['pictures'][$i]);
                continue;
            }

            $budget--;

            if ($p['kind'] === 'vector') {
                $vectors[] = $p['figma'];
            } else {
                $fills = true;
            }
        }

        $frame['pictures'] = array_values($frame['pictures']);
        $frames[] = $frame;
    }

    $urls = array('fills' => array(), 'vectors' => array());

    if ($fills) {
        $images = pg_figma_get('/v1/files/' . rawurlencode($key) . '/images', $connection['token'], 45);

        if ($images['ok'] && isset($images['data']['meta']['images']) && is_array($images['data']['meta']['images'])) {
            $urls['fills'] = $images['data']['meta']['images'];
        } elseif (!$images['ok'] && ($images['error']['kind'] === 'limit')) {
            return array('ok' => false, 'error' => $images['error']);
        }
    }

    foreach (array_chunk(array_values(array_unique($vectors)), 100) as $chunk) {
        $svg = pg_figma_get('/v1/images/' . rawurlencode($key) . '?format=svg&ids=' . rawurlencode(implode(',', $chunk)), $connection['token'], 60);

        if ($svg['ok'] && isset($svg['data']['images']) && is_array($svg['data']['images'])) {
            $urls['vectors'] += $svg['data']['images'];
        } elseif (!$svg['ok'] && ($svg['error']['kind'] === 'limit')) {
            return array('ok' => false, 'error' => $svg['error']);
        }
    }

    $id = bin2hex(random_bytes(8));
    $data = array('key' => $key, 'name' => (string) $list['name'], 'frames' => $frames, 'urls' => $urls,
        'files' => array(), 'by_ref' => array(), 'folder' => 0, 'shared' => array(), 'done' => false);

    if (!pg_figma_import_keep($id, $data)) {
        return $fail('other');
    }

    $_SESSION['pg_figma_import'] = $id;

    return array('ok' => true, 'id' => $id, 'error' => null,
        'frames' => array_map(function ($f) {
            return array('id' => $f['id'], 'name' => $f['name'], 'failed' => !empty($f['failed']));
        }, $frames),
        'found' => pg_figma_found_variables($frames));
}

/**
 * Downloads one address into the file manager as a picture: within the
 * size limit of an upload, only a picture type, a vector through the SVG
 * allowlist.
 *
 * @return array|null array('id', 'name') of the stored file, null when not stored
 */
function pg_figma_store_picture($url, $name, $folder, $file_max)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/cloud_file_picker.php');

    if (!is_string($url) || (strncmp($url, 'https://', 8) !== 0)) {
        return null;
    }

    $temp = tempnam(sys_get_temp_dir(), 'pgfg');

    if ($temp === false) {
        return null;
    }

    $got = pg_cloud_picker_download($url, array(), $file_max, $temp);
    $type = $got['ok'] ? pg_figma_picture_type((string) file_get_contents($temp, false, null, 0, 512)) : '';

    if ($type === 'svg') {
        $clean = pg_svg_sanitize((string) file_get_contents($temp));
        $type = (($clean !== '') && (@file_put_contents($temp, $clean) !== false)) ? 'svg' : '';
    }

    $stored = null;

    if ($type !== '') {
        clearstatcache(true, $temp);
        $put = pg_file_store_upload($temp, preg_replace('/\.[a-z]+$/', '.' . $type, $name), filesize($temp), array(
            'folder' => $folder, 'description' => '', 'design' => ($type === 'svg') ? '1' : null,
            'image_settings' => function_exists('pg_image_settings') ? pg_image_settings() : array(), 'file_max' => $file_max));

        if ($put['ok']) {
            $stored = array('id' => (int) $put['file']['id'], 'name' => (string) $put['file']['name']);
        } else {
            log_activity((string) $put['message']);
        }
    }

    @unlink($temp);

    return $stored;
}

/**
 * Fetches one frame's pictures and vectors into the file manager and puts
 * their addresses into its tree. A picture used again (one image fill on
 * several layers) is stored once.
 *
 * @param string $id    the import (pg_figma_import_start())
 * @param int    $index the frame's place in the import
 * @param array  $user
 * @return array ok, pictures, vectors, notes, failed
 */
function pg_figma_import_frame($id, $index, $user)
{
    $data = pg_figma_import_load($id);
    $index = (int) $index;

    if (!$data || !isset($data['frames'][$index])) {
        return array('ok' => false);
    }

    $frame = $data['frames'][$index];
    $out = array('ok' => true, 'pictures' => 0, 'vectors' => 0, 'notes' => 0, 'failed' => !empty($frame['failed']));
    $fetched = array();

    if (!$out['failed'] && $frame['pictures']) {
        @set_time_limit(300);

        if (!$data['folder']) {
            require_once(PG_FUNCTIONS_DIR . '/includes/designer_import.php');
            $ctx = (object) array('folders' => array(), 'user_id' => (int) $user['id']);
            $data['folder'] = pg_di_folder_path(array('Figma', ($data['name'] !== '') ? $data['name'] : $data['key']), $ctx);
        }

        $limits = pg_upload_limits();

        foreach ($frame['pictures'] as $p) {
            $vector = ($p['kind'] === 'vector');
            $ref = $vector ? '' : (string) $p['image_ref'];
            $url = $vector
                ? (isset($data['urls']['vectors'][$p['figma']]) ? $data['urls']['vectors'][$p['figma']] : '')
                : (isset($data['urls']['fills'][$ref]) ? $data['urls']['fills'][$ref] : '');
            $file = ($ref !== '' && isset($data['by_ref'][$ref])) ? $data['by_ref'][$ref] : null;
            $layer = pg_figma_tree_node($frame['tree'], $p['node']);

            if ($file === null) {
                $file = pg_figma_store_picture($url, pg_figma_picture_name($frame['name'], isset($layer['props']['customName']) ? $layer['props']['customName'] : '', $vector ? 'svg' : 'png'), $data['folder'], (int) $limits['file_max']);

                if ($file) {
                    $data['files'][] = $file;
                    $fetched[] = $file;

                    if ($ref !== '') {
                        $data['by_ref'][$ref] = $file;
                    }
                }
            }

            if ($file === null) {
                $frame['report'][] = array('figma' => $p['figma'], 'name' => isset($layer['props']['customName']) ? $layer['props']['customName'] : '', 'code' => 'picture');
                continue;
            }

            $frame['tree'] = pg_figma_set_picture($frame['tree'], $p, OUTPUT_PATH . $file['name']);
            $out[$vector ? 'vectors' : 'pictures']++;
        }
    }

    $frame['counts'] = array('pictures' => $out['pictures'], 'vectors' => $out['vectors']);
    $frame['pictures'] = array();
    $data['frames'][$index] = $frame;
    $out['notes'] = count($frame['report']);

    if (!pg_figma_import_keep_step($id, $data, $fetched)) {
        return array('ok' => false);
    }

    return $out;
}

/**
 * Ends the import: a component on two frames or more becomes a shared
 * component (its row marked import:, so leaving the design unpublished
 * removes it again), the pages and the variables are kept for the editor,
 * and the report is made.
 *
 * @param string $id
 * @param array  $user
 * @param bool   $variables whether the found variables are used
 * @return array ok, report
 */
function pg_figma_import_finish($id, $user, $variables)
{
    $data = pg_figma_import_load($id);

    if (!$data) {
        return array('ok' => false);
    }

    $made = array_values(array_filter($data['frames'], function ($f) {
        return empty($f['failed']) && is_array($f['tree']);
    }));
    $plan = pg_figma_shared_plan($made);
    $rows = array();
    $now = time();

    foreach (pg_figma_shared_trees($made, $plan) as $cid => $taken_from) {
        $name = mb_substr($plan[$cid]['name'], 0, 90);
        $unique = $name;

        for ($i = 1; (int) db_value("SELECT COUNT(*) FROM shared_components WHERE name = '" . e($unique) . "'") > 0 && $i < 1000; $i++) {
            $unique = $name . ' [' . $i . ']';
        }

        db("INSERT INTO shared_components (name, description, tree_json, category, created_by, created_at, updated_at)
            VALUES ('" . e($unique) . "', '', '" . e(pg_designer_tree_encode($taken_from['tree'])) . "', '" . e(mb_substr('import:figma/' . $data['key'], 0, 100)) . "', '" . (int) $user['id'] . "', '$now', '$now')");
        $sid = (int) mysqli_insert_id(db::$con);

        if ($sid > 0) {
            $rows[$cid] = array('id' => $sid, 'name' => $unique) + $taken_from;
        }
    }

    // A shared component placed inside another is placed by reference
    // there too. Node ids are a frame's own, so only the instances of the
    // frame the tree was taken from are looked for.
    $shared = array();

    foreach ($rows as $cid => $row) {
        $inner = $rows;
        unset($inner[$cid]);
        $tree = pg_figma_place_shared($row['tree'], $made[$row['frame']]['instances'], $inner);
        db("UPDATE shared_components SET tree_json = '" . e(pg_designer_tree_encode($tree)) . "' WHERE id = '" . (int) $row['id'] . "' LIMIT 1");
        $shared[] = array('id' => $row['id'], 'name' => $row['name'], 'tree' => $tree);
    }

    $pages = array();
    $taken = array();
    $report = array();
    $counts = array('pictures' => 0, 'vectors' => 0, 'notes' => 0, 'failed' => 0);

    foreach ($data['frames'] as $f) {
        $report[] = pg_figma_report_frame($f);

        if (!empty($f['failed']) || !is_array($f['tree'])) {
            $counts['failed']++;
            continue;
        }

        $counts['pictures'] += isset($f['counts']['pictures']) ? (int) $f['counts']['pictures'] : 0;
        $counts['vectors'] += isset($f['counts']['vectors']) ? (int) $f['counts']['vectors'] : 0;
        $counts['notes'] += count($f['report']);
        $slug = trim(strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $f['name'])), '-');
        $name = _pg_tpl_unique_page_name(($slug !== '') ? substr($slug, 0, 80) : 'page', $taken);
        $taken[mb_strtolower($name, 'UTF-8')] = true;
        $pages[] = array('key' => 'figma' . count($pages), 'page_name' => $name, 'page_title' => (string) $f['name'],
            'tree' => pg_figma_place_shared($f['tree'], $f['instances'], $rows));
    }

    $found = pg_figma_found_variables($made);
    $data['ready'] = array(
        'pages'      => $pages,
        'shared'     => $shared,
        'style_name' => mb_substr($data['name'], 0, 100),
        'variables'  => $variables ? pg_figma_variables_start($found, '', '') : array('model' => null, 'css' => ''),
    );
    $data['frames'] = array();
    $data['done'] = true;
    pg_figma_import_keep($id, $data);

    return array('ok' => true, 'report' => array(
        'name'     => $data['name'],
        'made'     => count($pages),
        'total'    => count($report),
        'counts'   => $counts + array('shared' => count($shared), 'variables' => $variables ? count(array_filter(array($found['brand'], $found['accent'], $found['heading'], $found['body']))) : 0),
        'folder'   => 'Figma/' . $data['name'],
        'frames'   => $report,
    ));
}

/**
 * What the editor opens with for a finished import: the pages as unsaved
 * tabs, the shared components, the design's name and its variables. The
 * work file is removed: the editor holds the pages now.
 *
 * @param string $id
 * @return array|null pages, shared, style_name, variables (model, css)
 */
function pg_figma_import_ready($id)
{
    $data = pg_figma_import_load($id);

    if (!$data || empty($data['done']) || empty($data['ready'])) {
        return null;
    }

    @unlink(pg_figma_import_path($id));

    if (isset($_SESSION['pg_figma_import']) && $_SESSION['pg_figma_import'] === $id) {
        unset($_SESSION['pg_figma_import']);
    }

    return $data['ready'];
}

/**
 * Stops an import: what was fetched is removed from the file manager, the
 * shared components it made are deleted, and its work file goes.
 *
 * @param string $id
 * @return bool
 */
function pg_figma_import_stop($id)
{
    $data = pg_figma_import_load($id);

    if (!$data) {
        return false;
    }

    if ($data['files']) {
        require_once(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_bin.php');

        foreach ($data['files'] as $file) {
            pg_delete_file_record(array('id' => (int) $file['id'], 'name' => (string) $file['name']));
        }
    }

    foreach (isset($data['ready']['shared']) ? $data['ready']['shared'] : array() as $row) {
        db("DELETE FROM shared_components WHERE id = '" . (int) $row['id'] . "' AND category LIKE 'import:figma/%' LIMIT 1");
    }

    @unlink(pg_figma_import_path($id));

    return true;
}
