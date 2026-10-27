<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Photo Gallery system widget (kind
 * 'photo_gallery'): the classic "photo gallery" page drawn by the designer.
 * The page's folder is the gallery: each sub-folder the visitor may view is
 * an album, shown with its first photo and the number of photos in it, and
 * the images of the folder are its photos. An album opens on the same page
 * with ?folder_id=<album>, as on the classic page; a folder outside the
 * page's folder, or one the visitor may not view, is never opened.
 *
 * The main loop repeats the albums of the folder shown, then its photos.
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
 * The file types a gallery shows as photos (the classic page's list).
 *
 * @return array
 */
function pg_gal_image_types()
{
    return array('gif', 'jpg', 'jpeg', 'png', 'bmp', 'tif', 'tiff');
}

/**
 * The folder a gallery shows: the album asked for when it lies below the
 * page's folder, else the page's folder.
 *
 * @param int      $root_id   the page's folder
 * @param mixed    $requested ?folder_id= ('' or null for none)
 * @param callable $parent_of folder id => its parent id (0 at the top)
 * @return int
 */
function pg_gal_folder_shown($root_id, $requested, $parent_of)
{
    $root_id = (int)$root_id;
    if (!is_scalar($requested) || !preg_match('/^\d{1,18}$/', (string)$requested)) return $root_id;
    $id = (int)$requested;
    if ($id === $root_id || $id <= 0) return $root_id;
    // Walk up from the album; a loop in the stored parents ends the walk.
    $seen = array();
    for ($at = (int)call_user_func($parent_of, $id); $at > 0 && !isset($seen[$at]); $at = (int)call_user_func($parent_of, $at)) {
        if ($at === $root_id) return $id;
        $seen[$at] = true;
    }
    return $root_id;
}

/**
 * The rows of a gallery: its albums (name, first photo, number of photos),
 * then its photos. Raw values; the renderer escapes them once.
 *
 * @param array  $albums   rows of id, name, photo (the first photo's file
 *                         name, '' for none), photos (count)
 * @param array  $photos   rows of id, name, description
 * @param string $page_url the gallery page's address
 * @param string $base     the site's path before a file name
 * @return array
 */
function pg_gal_rows($albums, $photos, $page_url, $base = '/')
{
    $out = array();
    $row = function ($album, $id, $name, $url, $image, $description, $count) {
        return array(
            '__gallery_item_id'          => (string)$id,
            '__gallery_item_name'        => (string)$name,
            '__gallery_item_url'         => (string)$url,
            '__gallery_item_image_url'   => (string)$image,
            '__gallery_item_description' => (string)$description,
            '__gallery_item_photo_count' => $album ? (string)(int)$count : '',
            '__gallery_item_count_label' => $album ? lang(array('string' => '{var:1} Photo{suffix:1}', 'vars' => array((int)$count), 'suffix' => array((int)$count > 1 ? 's' : ''))) : '',
            '__gallery_item_is_album'    => $album ? '1' : '',
            '__gallery_item_is_photo'    => $album ? '' : '1',
        );
    };
    foreach (is_array($albums) ? $albums : array() as $a) {
        if ((string)($a['name'] ?? '') === '') continue;
        $photo = (string)($a['photo'] ?? '');
        $out[] = $row(true, (int)$a['id'], $a['name'], $page_url . '?folder_id=' . (int)$a['id'],
            $photo !== '' ? $base . encode_url_path($photo) : '', '', (int)($a['photos'] ?? 0));
    }
    foreach (is_array($photos) ? $photos : array() as $p) {
        if ((string)($p['name'] ?? '') === '') continue;
        $url = $base . encode_url_path((string)$p['name']);
        $out[] = $row(false, (int)$p['id'], $p['name'], $url, $url, (string)($p['description'] ?? ''), 0);
    }
    return $out;
}

/**
 * The widget's visibility flags.
 *
 * @param array $rows     pg_gal_rows()
 * @param bool  $in_album an album is open (the back link shows)
 * @return array
 */
function pg_gal_flags($rows, $in_album)
{
    $albums = 0;
    $photos = 0;
    foreach ((array)$rows as $r) {
        if (!empty($r['__gallery_item_is_album'])) $albums++; else $photos++;
    }
    $gal_flags = array(
        'has_gallery_items' => ($albums + $photos) > 0,
        'no_gallery_items'  => ($albums + $photos) === 0,
        'has_albums'        => $albums > 0,
        'has_photos'        => $photos > 0,
        'in_album'          => (bool)$in_album,
    );
    return $gal_flags;
}

// The albums and photos of $folder_id the visitor may see, as the classic
// page reads them.
function _pg_gal_read($folder_id)
{
    $folder_id = (int)$folder_id;
    $types = "'" . implode("', '", pg_gal_image_types()) . "'";
    $albums = array();
    foreach ((array)db_items("SELECT folder_id AS id, folder_name AS name FROM folder WHERE folder_parent = '" . $folder_id . "' ORDER BY folder_order, folder_name") as $f) {
        if (!check_view_access($f['id'])) continue;
        $f['photos'] = (int)db_value("SELECT COUNT(*) FROM files WHERE folder = '" . (int)$f['id'] . "' AND type IN (" . $types . ") AND design = '0'");
        $f['photo'] = $f['photos'] ? (string)db_value("SELECT name FROM files WHERE folder = '" . (int)$f['id'] . "' AND type IN (" . $types . ") AND design = '0' ORDER BY name LIMIT 1") : '';
        $albums[] = $f;
    }
    $photos = (array)db_items("SELECT id, name, description FROM files WHERE folder = '" . $folder_id . "' AND type IN (" . $types . ") AND design = '0' ORDER BY name");
    return array($albums, $photos);
}

// ============================================================================
// photo_gallery
// ============================================================================

// Render a 'photo_gallery' system widget.
//
// loop_area: one row per album of the folder shown, then one per photo.
// Row tokens (pg_gal_rows()): ^^__gallery_item_id^^,
// ^^__gallery_item_name^^, ^^__gallery_item_url^^ (the album on this page,
// or the photo), ^^__gallery_item_image_url^^ (the photo, an album's first
// photo, empty for an empty album), ^^__gallery_item_description^^,
// ^^__gallery_item_photo_count^^, ^^__gallery_item_count_label^^,
// ^^__gallery_item_is_album^^, ^^__gallery_item_is_photo^^ ('1' or empty,
// for conditions).
// Tokens: ^^__album_name^^ (the folder shown), ^^__back_url^^ (the album
// above, the link drops at the top).
// Flags: pg_gal_flags(). Messages: 'photo_gallery'.
function _render_system_widget_photo_gallery($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';
    // The albums a visitor sees depend on the folders they may view.
    if (function_exists('pg_page_cache_personal')) pg_page_cache_personal('access');

    $page_id = _pg_sa_page_id();
    $root_id = ($page_id > 0) ? (int)db_value("SELECT page_folder FROM page WHERE page_id = '" . $page_id . "'") : 0;
    $page_url = ($page_id > 0) ? (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . encode_url_path((string)get_page_name($page_id)) : '';
    $parent_of = function ($id) {
        return (int)db_value("SELECT folder_parent FROM folder WHERE folder_id = '" . (int)$id . "'");
    };
    $folder_id = $root_id;
    if ($root_id > 0 && $mode !== 'background') {
        $folder_id = pg_gal_folder_shown($root_id, isset($_GET['folder_id']) ? $_GET['folder_id'] : null, $parent_of);
        if ($folder_id !== $root_id && !check_view_access($folder_id)) $folder_id = $root_id;
    }
    $rows = array();
    if ($folder_id > 0) {
        list($albums, $photos) = _pg_gal_read($folder_id);
        $rows = pg_gal_rows($albums, $photos, $page_url, defined('OUTPUT_PATH') ? OUTPUT_PATH : '/');
    }
    $in_album = ($folder_id !== $root_id);
    $back_url = '';
    if ($in_album) {
        $parent = $parent_of($folder_id);
        $back_url = $page_url . ($parent !== $root_id ? '?folder_id=' . $parent : '');
    }

    $gal_flags = pg_gal_flags($rows, $in_album);
    _eo_apply_visibility_bindings($tree, $gal_flags);
    _pg_member_drop_empty_links($tree, array('__back_url' => $back_url));
    _pg_inject_messages_node($tree, 'photo_gallery');

    $split = _split_widget_tree($tree);
    $loop_template = '';
    if ($split['loop_children'] === null) {
        $static_html = trim(_render_tree_node($tree, 0, 0));
    } else {
        foreach ($split['loop_children'] as $child) $loop_template .= _render_tree_node($child, 0, 0);
        $loop_template = trim($loop_template);
        $static_html = trim(_render_tree_node($split['static_tree'], 0, 0));
    }
    $loop_rendered = '';
    if ($loop_template !== '') {
        foreach ($rows as $i => $row) {
            $row_html = pg_sw_sweep_tokens(_pg_sw_fill_tokens($loop_template, pg_sw_escape_token_values($row)));
            $loop_rendered .= pg_sw_uniquify_row_ids($row_html, $widget_id, $i + 1);
        }
    }
    $static_values = pg_sw_escape_token_values(array(
        '__album_name' => $folder_id > 0 ? (string)db_value("SELECT folder_name FROM folder WHERE folder_id = '" . $folder_id . "'") : '',
        '__back_url'   => $back_url,
    ));
    $static_html = pg_sw_sweep_tokens(_pg_sw_fill_tokens($static_html, $static_values));
    return str_replace('<!--pg-loop-slot-->', $loop_rendered, $static_html);
}
