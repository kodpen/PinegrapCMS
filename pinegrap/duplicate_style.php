<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'designer');

validate_token_field();

// get original style information
$query =
    "SELECT
        style_name as name,
        style_type as type,
        style_layout as layout,
        style_empty_cell_width_percentage AS empty_cell_width_percentage,
        style_code as code,
        style_head AS head,
        social_networking_position,
        theme_id,
        additional_body_classes,
        collection,
        layout_type,
        style_tree_json as tree_json
    FROM style
    WHERE style_id = '" . escape($_GET['id']) . "'";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$style = mysqli_fetch_assoc($result);

$original_style_name = $style['name'];

$new_style_name = get_unique_name(array(
    'name' => $original_style_name,
    'type' => 'style'));

// if the style is a system style, then update style name in body class to new style name
if ($style['type'] == 'system') {
    $style['code'] = str_replace(get_class_name($original_style_name), get_class_name($new_style_name), $style['code']);
}

// create new style
$query =
    "INSERT INTO style (
        style_name,
        style_type,
        style_layout,
        style_empty_cell_width_percentage,
        style_code,
        style_head,
        social_networking_position,
        theme_id,
        additional_body_classes,
        collection,
        layout_type,
        style_tree_json,
        style_user,
        style_timestamp)
    VALUES (
        '" . escape($new_style_name) . "',
        '" . escape($style['type']) . "',
        '" . escape($style['layout']) . "',
        '" . escape($style['empty_cell_width_percentage']) . "',
        '" . escape($style['code']) . "',
        '" . escape($style['head']) . "',
        '" . $style['social_networking_position'] . "',
        '" . escape($style['theme_id']) . "',
        '" . escape($style['additional_body_classes']) . "',
        '" . escape($style['collection']) . "',
        '" . e($style['layout_type']) . "',
        '" . escape($style['tree_json']) . "',
        '" . escape($user['id']) . "',
        UNIX_TIMESTAMP())";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$new_style_id = mysqli_insert_id(db::$con);

// Multi-page visual design: the shared assets sit on the style and every
// attached page owns its layout. Copy both halves — a duplicated design with
// no pages would open as an empty editor, and a duplicate that left the
// pages pointing at the ORIGINAL style would silently edit that one.
// Page names get the same "(2)" uniqueness treatment as the style name.
if ($style['layout'] === 'visual_designer' && pg_multi_page_design_ready()) {
    db("UPDATE style dst, style src
        SET dst.style_custom_css   = src.style_custom_css,
            dst.style_custom_js    = src.style_custom_js,
            dst.style_custom_fonts = src.style_custom_fonts
        WHERE dst.style_id = '" . (int)$new_style_id . "'
          AND src.style_id = '" . (int)$_GET['id'] . "'");

    $src_pages = db_items(
        "SELECT * FROM page
         WHERE page_style = '" . (int)$_GET['id'] . "'
           AND layout_type = 'system'
         ORDER BY page_id ASC");
    foreach ((array)$src_pages as $src_page) {
        $new_page_name = get_unique_name(array(
            'name' => $src_page['page_name'],
            'type' => 'page'));
        // Copy every column except the identity ones, then repoint the
        // style FK. SELECT * + drop-identity rather than a hand-written
        // column list, so a column added to `page` later is not silently
        // left behind (same reasoning as pg_pb_copy_row()).
        $cols = array();
        $vals = array();
        foreach ($src_page as $col => $val) {
            if ($col === 'page_id') continue;
            $cols[] = '`' . $col . '`';
            if ($col === 'page_name')       { $vals[] = "'" . e($new_page_name) . "'"; continue; }
            if ($col === 'page_style')      { $vals[] = "'" . (int)$new_style_id . "'"; continue; }
            if ($col === 'page_home')       { $vals[] = "''"; continue; }   // never two home pages
            $vals[] = ($val === null) ? 'NULL' : "'" . e($val) . "'";
        }
        db("INSERT INTO page (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")");
    }
}

// if the style is a system style, then duplicate cells in database
if ($style['type'] == 'system') {
    // get cells from database in order to duplicate them
    $query =
        "SELECT
            area,
            `row`, # Backticks for reserved word.
            col,
            region_type,
            region_name
        FROM system_style_cells
        WHERE style_id = '" . escape($_GET['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    $cells = array();
    
    // loop through the cells in order to add them to array
    while ($row = mysqli_fetch_assoc($result)) {
        $cells[] = $row;
    }
    
    // loop through the cells in order to add them to the database
    foreach ($cells as $cell) {
        $query =
            "INSERT INTO system_style_cells (
                style_id,
                area,
                `row`, # Backticks for reserved word.
                col,
                region_type,
                region_name)
            VALUES (
                '$new_style_id',
                '" . $cell['area'] . "',
                '" . $cell['row'] . "',
                '" . $cell['col'] . "',
                '" . $cell['region_type'] . "',
                '" . escape($cell['region_name']) . "')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    }
}
log_activity(lang(array('string'=>'{var:1} ({var:2}) was duplicated','vars'=>array(lang('page style'), $original_style_name) )), $_SESSION['sessionusername']);

header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_' . $style['type'] . '_style.php?id=' . $new_style_id);
?>