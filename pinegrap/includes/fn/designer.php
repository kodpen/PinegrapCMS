<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: The visual designer: system styles, tree validation and expansion, node and component rendering, page/style saving, CSS output.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}
// initialize function that can generate HTML for a system style based on the layout and cells that were submitted which are in the areas array
function get_system_style_code($layout, $areas, $style_name, $empty_cell_width_percentage, $additional_body_classes)
{
    $output_viewport_meta_tag = '';
    // if the layout is one column mobile, then add viewport meta tag,
    // so that mobile devices will know that a page has been optimized for mobile
    if ($layout == 'one_column_mobile') {
        $output_viewport_meta_tag = '<meta name="viewport" content="width=device-width,minimum-scale=1,maximum-scale=1,user-scalable=no">';
    }
    // prepare HTML header
    $code = '<!DOCTYPE html>

        <html lang="en">

            <head>

                <meta charset="utf-8">

                <title></title>

                ' . $output_viewport_meta_tag . '

                <meta_tags></meta_tags>

            </head>' . "\n";
    $output_additional_body_classes = '';
    // If there is an additional body class, then prepare value with space before it so it can be outputted.
    if ($additional_body_classes != '') {
        $output_additional_body_classes = ' ' . h($additional_body_classes);
    }
    // prepare body differently based on the layout
    switch ($layout) {
        case 'one_column':
            $code .= '            <body class="one_column ' . $output_additional_body_classes . '">
                    <div id="site_border">
                        <div id="site_top">' . get_system_style_area_code($areas['site_top']['rows'], $empty_cell_width_percentage) . '</div>
                        <div id="site_header">' . get_system_style_area_code($areas['site_header']['rows'], $empty_cell_width_percentage) . '</div>
                        <div id="area_border">
                            <div id="area_header">' . get_system_style_area_code($areas['area_header']['rows'], $empty_cell_width_percentage) . '</div>
                            <div id="page_wrapper">
                                <div id="page_border">
                                    <div id="page_header">' . get_system_style_area_code($areas['page_header']['rows'], $empty_cell_width_percentage) . '</div>
                                    <div id="page_content">' . get_system_style_area_code($areas['page_content']['rows'], $empty_cell_width_percentage) . '</div>
                                    <div id="page_footer">' . get_system_style_area_code($areas['page_footer']['rows'], $empty_cell_width_percentage) . '</div>
                                </div>
                                <div id="area_footer">' . get_system_style_area_code($areas['area_footer']['rows'], $empty_cell_width_percentage) . '</div>
                            </div>
                        </div>
                        <div id="site_footer_border">
                            <div id="site_footer">' . get_system_style_area_code($areas['site_footer']['rows'], $empty_cell_width_percentage) . '</div>
                        </div>
                    </div>
                </body>' . "\n";
            break;
        case 'one_column_email':
            $code .= '            <body class="one_column_email ' . $output_additional_body_classes . '">
                    <div id="email_border">
                        <div id="site_top">' . get_system_style_area_code($areas['site_top']['rows'], $empty_cell_width_percentage) . '</div>
                        <div id="site_header">' . get_system_style_area_code($areas['site_header']['rows'], $empty_cell_width_percentage) . '</div>
                        <div id="area_border">
                            <div id="area_header">' . get_system_style_area_code($areas['area_header']['rows'], $empty_cell_width_percentage) . '</div>
                            <div id="page_wrapper">
                                <div id="page_border">
                                    <div id="page_header">' . get_system_style_area_code($areas['page_header']['rows'], $empty_cell_width_percentage) . '</div>
                                    <div id="page_content">' . get_system_style_area_code($areas['page_content']['rows'], $empty_cell_width_percentage) . '</div>
                                    <div id="page_footer">' . get_system_style_area_code($areas['page_footer']['rows'], $empty_cell_width_percentage) . '</div>
                                </div>
                                <div id="area_footer">' . get_system_style_area_code($areas['area_footer']['rows'], $empty_cell_width_percentage) . '</div>
                            </div>
                        </div>
                        <div id="site_footer_border">
                            <div id="site_footer">' . get_system_style_area_code($areas['site_footer']['rows'], $empty_cell_width_percentage) . '</div>
                        </div>
                    </div>
                </body>' . "\n";
            break;
        case 'one_column_mobile':
            $code .= '            <body class="one_column_mobile ' . $output_additional_body_classes . '">
                    <div id="mobile_border">
                        <div id="site_top">' . get_system_style_area_code($areas['site_top']['rows'], $empty_cell_width_percentage) . '</div>
                        <div id="site_header">' . get_system_style_area_code($areas['site_header']['rows'], $empty_cell_width_percentage) . '</div>
                        <div id="area_border">
                            <div id="area_header">' . get_system_style_area_code($areas['area_header']['rows'], $empty_cell_width_percentage) . '</div>
                            <div id="page_wrapper">
                                <div id="page_border">
                                    <div id="page_header">' . get_system_style_area_code($areas['page_header']['rows'], $empty_cell_width_percentage) . '</div>
                                    <div id="page_content">' . get_system_style_area_code($areas['page_content']['rows'], $empty_cell_width_percentage) . '</div>
                                    <div id="page_footer">' . get_system_style_area_code($areas['page_footer']['rows'], $empty_cell_width_percentage) . '</div>
                                </div>
                                <div id="area_footer">' . get_system_style_area_code($areas['area_footer']['rows'], $empty_cell_width_percentage) . '</div>
                            </div>
                        </div>
                        <div id="site_footer_border">
                            <div id="site_footer">' . get_system_style_area_code($areas['site_footer']['rows'], $empty_cell_width_percentage) . '</div>
                        </div>
                    </div>
                </body>' . "\n";
            break;
        case 'two_column_sidebar_left':
            $code .= '            <body class="two_column_sidebar_left ' . $output_additional_body_classes . '">
                    <div id="site_border">
                        <div id="site_top">' . get_system_style_area_code($areas['site_top']['rows'], $empty_cell_width_percentage) . '</div>
                        <div id="site_header">' . get_system_style_area_code($areas['site_header']['rows'], $empty_cell_width_percentage) . '</div>
                        <div id="area_border">
                            <div id="area_header">' . get_system_style_area_code($areas['area_header']['rows'], $empty_cell_width_percentage) . '</div>
                            <div id="page_wrapper">
                                <div id="page_border">
                                    <div id="page_header">' . get_system_style_area_code($areas['page_header']['rows'], $empty_cell_width_percentage) . '</div>
                                    <div id="page_content" style="float: right; width: 69%">' . get_system_style_area_code($areas['page_content']['rows'], $empty_cell_width_percentage) . '</div>
                                    <div id="sidebar" style="float: left; width: 30%">' . get_system_style_area_code($areas['sidebar']['rows'], $empty_cell_width_percentage) . '</div>
                                    <div style="clear: both"></div>
                                    <div id="page_footer">' . get_system_style_area_code($areas['page_footer']['rows'], $empty_cell_width_percentage) . '</div>
                                </div>
                                <div id="area_footer">' . get_system_style_area_code($areas['area_footer']['rows'], $empty_cell_width_percentage) . '</div>
                            </div>
                        </div>
                        <div id="site_footer_border">
                            <div id="site_footer">' . get_system_style_area_code($areas['site_footer']['rows'], $empty_cell_width_percentage) . '</div>
                        </div>
                    </div>
                </body>' . "\n";
            break;
        case 'two_column_sidebar_right':
            $code .= '            <body class="two_column_sidebar_right ' . $output_additional_body_classes . '">
                    <div id="site_border">
                        <div id="site_top">' . get_system_style_area_code($areas['site_top']['rows'], $empty_cell_width_percentage) . '</div>
                        <div id="site_header">' . get_system_style_area_code($areas['site_header']['rows'], $empty_cell_width_percentage) . '</div>
                        <div id="area_border">
                            <div id="area_header">' . get_system_style_area_code($areas['area_header']['rows'], $empty_cell_width_percentage) . '</div>
                            <div id="page_wrapper">
                                <div id="page_border">
                                    <div id="page_header">' . get_system_style_area_code($areas['page_header']['rows'], $empty_cell_width_percentage) . '</div>
                                    <div id="page_content" style="float: left; width: 69%">' . get_system_style_area_code($areas['page_content']['rows'], $empty_cell_width_percentage) . '</div>
                                    <div id="sidebar" style="float: right; width: 30%">' . get_system_style_area_code($areas['sidebar']['rows'], $empty_cell_width_percentage) . '</div>
                                    <div style="clear: both"></div>
                                    <div id="page_footer">' . get_system_style_area_code($areas['page_footer']['rows'], $empty_cell_width_percentage) . '</div>
                                </div>
                                <div id="area_footer">' . get_system_style_area_code($areas['area_footer']['rows'], $empty_cell_width_percentage) . '</div>
                            </div>
                        </div>
                        <div id="site_footer_border">
                            <div id="site_footer">' . get_system_style_area_code($areas['site_footer']['rows'], $empty_cell_width_percentage) . '</div>
                        </div>
                    </div>
                </body>' . "\n";
            break;
        case 'three_column_sidebar_left':
            $code .= '            <body class="three_column_sidebar_left ' . $output_additional_body_classes . '">
                    <div id="site_border">
                        <div id="site_top">' . get_system_style_area_code($areas['site_top']['rows'], $empty_cell_width_percentage) . '</div>
                        <div id="site_header">' . get_system_style_area_code($areas['site_header']['rows'], $empty_cell_width_percentage) . '</div>
                        <div id="area_border">
                            <div id="area_header">' . get_system_style_area_code($areas['area_header']['rows'], $empty_cell_width_percentage) . '</div>
                            <div id="page_wrapper">
                                <div id="page_border">
                                    <div id="page_header">' . get_system_style_area_code($areas['page_header']['rows'], $empty_cell_width_percentage) . '</div>
                                    <div id="sidebar" style="float: left; width: 22%">' . get_system_style_area_code($areas['sidebar']['rows'], $empty_cell_width_percentage) . '</div>
                                    <div id="page_content" style="float: right; width: 70%">
                                        <div id="page_content_left" style="float: left; width: 74%">' . get_system_style_area_code($areas['page_content_left']['rows'], $empty_cell_width_percentage) . '</div>
                                        <div id="page_content_right" style="float: right; width: 23%">' . get_system_style_area_code($areas['page_content_right']['rows'], $empty_cell_width_percentage) . '</div>
                                        <div style="clear: both"></div>
                                    </div>
                                    <div style="clear: both"></div>
                                    <div id="page_footer">' . get_system_style_area_code($areas['page_footer']['rows'], $empty_cell_width_percentage) . '</div>
                                </div>
                                <div id="area_footer">' . get_system_style_area_code($areas['area_footer']['rows'], $empty_cell_width_percentage) . '</div>
                            </div>
                        </div>
                        <div id="site_footer_border">
                            <div id="site_footer">' . get_system_style_area_code($areas['site_footer']['rows'], $empty_cell_width_percentage) . '</div>
                        </div>
                    </div>
                </body>' . "\n";
            break;
    }
    // prepare HTML footer
    $code .= '        </html>';
    return $code;
}
function get_system_style_area_code($rows, $empty_cell_width_percentage)
{
    $code = '';
    // if the empty cell width percentage is blank, then set it to 0
    if ($empty_cell_width_percentage == '') {
        $empty_cell_width_percentage = 0;
    }
    // loop through the rows in order to prepare code for each row
    foreach ($rows as $row_key => $row) {
        // Add wrapper divs so banded designs with Advanced Styling is possible within the Theme Designer
        $rk = $row_key + 1;
        $code .= '<div class="rband r' . $rk . ' clr"><div class="rwrap r' . $rk . '">';
        // loop through the cells in this row in order to prepare code for each one
        foreach ($row['cells'] as $cell_key => $cell) {
            $output_row_number = $row_key + 1;
            $output_column_number = $cell_key + 1;
            $output_style = '';
            // if there is more than 1 cell in this row, ONLY then prepare styling for this row
            if (count($row['cells']) > 1) {
                // if this is not the second cell or there is more than 2 cells in this row, then the float is left
                if (($cell_key != 1) || (count($row['cells']) > 2)) {
                    $output_style = ' style="float: left;';
                    // else this is the second cell and there is not more than 2 cells in this row
                } else {
                    $output_style = ' style="float: right;';
                }
                // determine width and complete $output_style
                if ($empty_cell_width_percentage != 0) { // page style contains default empty cell width
                    $empty_cell_counter = 0;
                    // go through all cells in the current row to get the total number of empty cells
                    foreach ($row['cells'] as $cell2_key => $cell2) {
                        if ($cell2['region_type'] == '') {
                            $empty_cell_counter++;
                        }
                    }
                    // determine cell width based on whether it is empty
                    if ($cell['region_type'] != '') {
                        $output_width = ((100 - ($empty_cell_counter * $empty_cell_width_percentage)) / (count($row['cells']) - $empty_cell_counter)) - .6;
                    } else { // empty so fix the width
                        $output_width = $empty_cell_width_percentage - .7; // round down for v9 responsive cells
                    }
                } else {
                    // no default empty cell width was found in page style so set all cells to the same size
                    $output_width = (100 / count($row['cells'])) - .6;
                }
                // round down the final cell width so the cell doesn't wrap unintentionally within narrow areas
                $output_style .= ' width: ' . round($output_width, 2) . '%; padding: .1em;"';
            }
            $region_class = '';
            // prepare the region tag differently based on the region type
            switch ($cell['region_type']) {
                case 'ad':
                    $output_region = '<ad>' . $cell['region_name'] . '</ad>';
                    $region_class = ' ad_' . str_replace(' ', '_', $cell['region_name']);
                    break;
                case 'cart':
                    $output_region = '<cart></cart>';
                    $region_class = ' cart';
                    break;
                case 'common':
                    $output_region = '<cregion>' . $cell['region_name'] . '</cregion>';
                    $region_class = ' cregion_' . str_replace(' ', '_', $cell['region_name']);
                    break;
                case 'designer':
                    $output_region = '<cregion>' . $cell['region_name'] . '</cregion>';
                    $region_class = ' cregion_' . str_replace(' ', '_', $cell['region_name']);
                    break;
                case 'dynamic':
                    $output_region = '<dregion>' . $cell['region_name'] . '</dregion>';
                    $region_class = ' dregion_' . str_replace(' ', '_', $cell['region_name']);
                    break;
                case 'login':
                    $output_region = '<login>' . $cell['region_name'] . '</login>';
                    $region_class = ' login_' . str_replace(' ', '_', $cell['region_name']);
                    break;
                case 'menu':
                    $output_region = '<menu>' . $cell['region_name'] . '</menu><div style="clear: both"></div>';
                    $region_class = ' menu_' . str_replace(' ', '_', $cell['region_name']);
                    break;
                case 'menu_sequence':
                    $output_region = '<menu_sequence>' . $cell['region_name'] . '</menu_sequence>';
                    $region_class = ' menu_sequence_' . str_replace(' ', '_', $cell['region_name']);
                    break;
                case 'mobile_switch':
                    $output_region = '<mobile_switch></mobile_switch>';
                    $region_class = ' mobile_switch';
                    break;
                case 'page':
                    $output_region = '<pregion></pregion>';
                    $region_class = ' pregion';
                    break;
                case 'pdf':
                    $output_region = '<pdf></pdf>';
                    $region_class = ' pdf';
                    break;
                case 'system':
                    // if there is a region name, then output it in the system tag
                    if ($cell['region_name'] != '') {
                        $output_region = '<system>' . $cell['region_name'] . '</system>';
                        $region_class = ' system_' . str_replace(' ', '_', $cell['region_name']);
                        // else output the tag by itself
                    } else {
                        $output_region = '<system></system>';
                        $region_class = ' system';
                    }
                    break;
                case 'tag_cloud':
                    $output_region = '<tcloud>' . $cell['region_name'] . '</tcloud>';
                    $region_class = ' tcloud_' . str_replace(' ', '_', $cell['region_name']);
                    break;
                default:
                    $output_region = '';
                    break;
            }
            $code .= '<div class="r' . $output_row_number . 'c' . $output_column_number . h($region_class) . ' c' . $output_column_number . '"' . $output_style . '>' . $output_region . '</div>';
        }
        // closed additional wrapper divs
        $code .= '</div></div>';
    }
    // If there are any content areas found, wrap them all in a div so Advanced Styling is possible within the Theme Designer
    if ($code != '') {
        return '<div class="wrap">' . $code . '</div>';
    } else {
        return $code;
    }
}
// Generate style_code HTML from the new Visual Pinegrap Editor tree JSON structure
function generate_style_code_from_tree($tree, $style_name, $additional_body_classes = '')
{
    if (!$tree || !is_array($tree)) return '';

    // The <body> tag, assembled from every place the designer lets somebody
    // describe it. `class`, `style` and `id` each have TWO sources — a
    // dedicated control (Body Options) and the Attributes panel at the bottom
    // — and the rule for all three is MERGE, never drop.
    //
    // They used to be dropped: an attribute named class/style/id typed into
    // the Attributes panel was skipped here as a "duplicate" of the dedicated
    // emitter. The operator typed it, the panel kept it, the canvas showed
    // it, and the published page silently did not have it. An input that
    // stores what you type and then throws it away is worse than one that
    // refuses it.
    $_root_props = isset($tree['props']) && is_array($tree['props']) ? $tree['props'] : array();

    $_attr_class = array();
    $_attr_style = array();
    $_attr_id    = '';
    $_body_extra_attrs = '';
    if (isset($_root_props['_attrs']) && is_array($_root_props['_attrs'])) {
        foreach ($_root_props['_attrs'] as $_a) {
            if (!is_array($_a) || empty($_a['name'])) continue;
            $_an = mb_strtolower(trim((string)$_a['name']));
            $_av = isset($_a['value']) ? trim((string)$_a['value']) : '';
            if ($_an === 'class') { if ($_av !== '') $_attr_class[] = $_av; continue; }
            if ($_an === 'style') { if ($_av !== '') $_attr_style[] = rtrim($_av, '; '); continue; }
            if ($_an === 'id')    { if ($_av !== '' && $_attr_id === '') $_attr_id = $_av; continue; }
            $_body_extra_attrs .= ' ' . h($_an) . '="' . h($_av) . '"';
        }
    }

    // Class: the style-level "Additional Body Classes", the root node's own
    // cssClass, then anything typed as a class attribute.
    $_root_cls   = isset($_root_props['cssClass']) ? trim($_root_props['cssClass']) : '';
    $_body_class_parts = array();
    if ($additional_body_classes != '') $_body_class_parts[] = $additional_body_classes;
    if ($_root_cls !== '')              $_body_class_parts[] = $_root_cls;
    foreach ($_attr_class as $_c)       $_body_class_parts[] = $_c;
    $body_class = $_body_class_parts ? h(implode(' ', $_body_class_parts)) : '';

    // Style: the body font picker, then any inline style, then anything
    // typed as a style attribute. The font is written with entity-escaped
    // quotes because the whole declaration goes into a double-quoted
    // attribute; a multi-word family without them tokenises as keywords.
    $_root_font   = isset($_root_props['fontFamily'])  ? trim($_root_props['fontFamily'])  : '';
    $_root_inline = isset($_root_props['inlineStyle']) ? trim($_root_props['inlineStyle']) : '';
    $_style_parts = array();
    if ($_root_font !== '')   $_style_parts[] = 'font-family:&#039;' . h($_root_font) . '&#039;,sans-serif';
    if ($_root_inline !== '') $_style_parts[] = h(rtrim($_root_inline, '; '));
    foreach ($_attr_style as $_st) $_style_parts[] = h($_st);
    $body_style = $_style_parts ? ' style="' . implode(';', $_style_parts) . '"' : '';

    // Id: the dedicated field wins, the attribute is the fallback. Two ids
    // on one tag is not a merge, so one of them has to be authoritative.
    $_root_id = isset($_root_props['id']) ? trim($_root_props['id']) : '';
    if ($_root_id === '') $_root_id = $_attr_id;
    if ($_root_id !== '') {
        $_body_extra_attrs = ' id="' . h($_root_id) . '"' . $_body_extra_attrs;
    }

    $code = '<!DOCTYPE html>

        <html lang="en">

            <head>

                <meta charset="utf-8">

                <title></title>

                <meta name="viewport" content="width=device-width, initial-scale=1">

                <meta_tags></meta_tags>

            </head>

            <body' . ($body_class ? ' class="' . $body_class . '"' : '') . $body_style . $_body_extra_attrs . '>' . "\n";

    // Process children of the root container node
    if (isset($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as $child) {
            $code .= _render_tree_node($child, 2);
        }
    }

    $code .= '    </body>

        </html>';

    return $code;
}

// Save a Visual Pinegrap Editor system style (INSERT or UPDATE).
// Returns the style_id. Pass $data['style_id'] for UPDATE, omit for INSERT.
// ========================= TREE JSON VALIDATION (D5) =========================
//
// Validates a style tree_json string before it is saved to the DB.
// Returns array:
//   'ok'           bool    — false = hard error, do not save
//   'error'        string  — human-readable message (when !ok)
//   'warnings'     array   — non-blocking notices (e.g. orphan cleanup)
//   'cleaned_json' string  — re-encoded JSON with orphans removed (use in place of original)
//
function _validate_and_clean_tree_json($json)
{
    $valid_types    = array('root','container','area','row','col','component','content','region','semantic','shared_ref');
    $required_props = array(
        'area'       => array('areaId'),
        'component'  => array('componentType'),
        // `regionName` is no longer unconditionally required — singleton
        // region types (page, cart, mobile_switch, pdf, comments_block,
        // system) emit a name-less marker and a blank name is correct.
        // _vt_walk applies the conditional check based on regionType.
        'region'     => array('regionType'),
        'shared_ref' => array('sharedId'),
        'semantic'   => array('tag'),
    );

    // Empty tree — REJECT. A blank style_tree_json reaching this validator
    // means the designer JS never populated the hidden field (e.g. accidental
    // native form submit via Enter on a property input). Saving the empty
    // string would overwrite the stored design with nothing — a destructive
    // no-op masquerading as a successful save. Force the caller to surface a
    // hard error instead so the user knows the save did not go through.
    if ($json === '' || $json === null) {
        return array(
            'ok'           => false,
            'error'        => lang('An empty design cannot be saved. Refresh the page and try again.'),
            'warnings'     => array(),
            'cleaned_json' => '',
        );
    }

    // JSON parse check
    $tree = json_decode($json, true);
    if ($tree === null) {
        return array('ok' => false, 'error' => lang('Tree JSON is invalid (parse error).'), 'warnings' => array(), 'cleaned_json' => '');
    }

    // Root node check
    if (!isset($tree['type']) || $tree['type'] !== 'root') {
        return array('ok' => false, 'error' => lang('Tree root node is missing or invalid.'), 'warnings' => array(), 'cleaned_json' => '');
    }

    $errors   = array();
    $warnings = array();

    // Recursive schema walk
    _vt_walk($tree, 0, $valid_types, $required_props, $errors);
    if (!empty($errors)) {
        return array('ok' => false, 'error' => implode('; ', array_slice($errors, 0, 5)), 'warnings' => array(), 'cleaned_json' => '');
    }

    // Collect all sharedIds referenced in the tree
    $used_ids = array();
    _vt_collect_shared_ids($tree, $used_ids);

    // Orphan + cycle check (batch SELECT)
    $orphan_ids = array();
    if (!empty($used_ids)) {
        $ids_str  = implode(',', array_map('intval', array_keys($used_ids)));
        $found    = array();
        $rows     = db_items("SELECT id, tree_json FROM shared_components WHERE id IN ($ids_str)");
        if ($rows) {
            foreach ($rows as $r) {
                $fid = (int)$r['id'];
                $found[$fid] = true;
                // Cycle/nesting check: shared_component must not itself contain shared_refs (Phase 1)
                if ($r['tree_json'] !== '') {
                    $inner = json_decode($r['tree_json'], true);
                    $inner_ids = array();
                    if ($inner) _vt_collect_shared_ids($inner, $inner_ids);
                    if (!empty($inner_ids)) {
                        $errors[] = lang(array('string' => 'Shared component id={var:1} contains nested shared references (not allowed in Phase 1).', 'vars' => $fid));
                    }
                }
            }
        }
        if (!empty($errors)) {
            return array('ok' => false, 'error' => implode('; ', $errors), 'warnings' => array(), 'cleaned_json' => '');
        }
        // Mark orphans (referenced but not found in DB)
        foreach ($used_ids as $sid => $_) {
            if (!isset($found[(int)$sid])) {
                $orphan_ids[] = (int)$sid;
                $warnings[]   = lang(array('string' => 'Orphan shared component id={var:1} was removed from the tree.', 'vars' => $sid));
            }
        }
    }

    // Remove orphans and re-encode
    if (!empty($orphan_ids)) {
        _vt_remove_orphans($tree, $orphan_ids);
    }
    $cleaned = pg_designer_tree_encode($tree);

    return array('ok' => true, 'error' => '', 'warnings' => $warnings, 'cleaned_json' => ($cleaned !== '' ? $cleaned : $json));
}

// Walk tree array and accumulate schema errors.
function _vt_walk(&$node, $depth, $valid_types, $required_props, &$errors)
{
    if (!is_array($node)) return;
    $type = isset($node['type']) ? $node['type'] : '';
    if (!$type || !in_array($type, $valid_types, true)) {
        $errors[] = 'Invalid node type: "' . $type . '"';
        return;
    }
    if (isset($required_props[$type])) {
        foreach ($required_props[$type] as $prop) {
            $val = isset($node['props'][$prop]) ? $node['props'][$prop] : null;
            if ($val === null || $val === '') {
                $errors[] = '[' . $type . '] prop "' . $prop . '" missing';
            }
        }
    }
    // Region: regionName only required for the types that resolve by name
    // (cregion / dregion / menu / ad / tag_cloud). Singletons exempt.
    if ($type === 'region') {
        $rt = isset($node['props']['regionType']) ? $node['props']['regionType'] : '';
        $name_required = array('ad'=>1,'common'=>1,'designer'=>1,'dynamic'=>1,'menu'=>1,'menu_sequence'=>1,'tag_cloud'=>1);
        if (isset($name_required[$rt])) {
            $rn = isset($node['props']['regionName']) ? $node['props']['regionName'] : '';
            if ($rn === null || $rn === '') {
                $errors[] = '[region] type "' . $rt . '" requires non-empty regionName';
            }
        }
    }
    if ($depth > 60) { $errors[] = 'Tree too deep (possible cycle)'; return; }
    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child) {
            _vt_walk($child, $depth + 1, $valid_types, $required_props, $errors);
        }
        unset($child);
    }
}

// Collect all sharedId values from shared_ref nodes into $acc array (keyed by id).
function _vt_collect_shared_ids($node, &$acc)
{
    if (!is_array($node)) return;
    if (isset($node['type']) && $node['type'] === 'shared_ref') {
        $sid = isset($node['props']['sharedId']) ? (int)$node['props']['sharedId'] : 0;
        if ($sid > 0) $acc[$sid] = true;
        return; // black box
    }
    if (isset($node['children'])) {
        foreach ($node['children'] as $child) _vt_collect_shared_ids($child, $acc);
    }
}

// Remove shared_ref nodes whose sharedId is in $orphan_ids from the tree (recursive).
function _vt_remove_orphans(&$node, $orphan_ids)
{
    if (!is_array($node) || !isset($node['children'])) return;
    $orphan_set = array_flip($orphan_ids); // id → true for O(1) lookup
    $node['children'] = array_values(array_filter($node['children'], function ($child) use ($orphan_set) {
        if (!is_array($child)) return true;
        if (isset($child['type']) && $child['type'] === 'shared_ref') {
            $sid = isset($child['props']['sharedId']) ? (int)$child['props']['sharedId'] : 0;
            return !isset($orphan_set[$sid]);
        }
        return true;
    }));
    foreach ($node['children'] as &$child) {
        _vt_remove_orphans($child, $orphan_ids);
    }
    unset($child);
}

// ========================= SHARED COMPONENT PLACEHOLDER EXPANDER (F9) =========================
//
// Expand `<!--pg-shared-ref:ID-->` markers in an HTML string into fresh renders of
// `shared_components.tree_json`. Called from page-render paths (get_page_content.php, email,
// preview, etc.) after style_code is loaded from the DB.
//
// Guarantees / invariants:
//   * Single pass over the string — O(n) regex, batch SELECT for all ids referenced.
//   * Cycle-safe: Phase-1 validation (`_validate_and_clean_tree_json`) already blocks shared
//     components from containing nested shared_refs, so the rendered inner HTML will not
//     contain any markers. A depth cap is kept as a safety net.
//   * Missing / deleted shared components → marker is replaced with an empty string (layout
//     is preserved).
//
function _expand_shared_refs($html, $depth = 0)
{
    if ($depth >= 8) return $html; // safety net — should never fire (nested refs blocked)
    if (strpos($html, '<!--pg-shared-ref:') === false) return $html;

    // Collect every referenced id in one pass
    if (!preg_match_all('/<!--pg-shared-ref:(\d+)-->/', $html, $matches)) return $html;
    $ids = array_unique(array_map('intval', $matches[1]));
    $ids = array_values(array_filter($ids, function ($i) { return $i > 0; }));
    if (empty($ids)) return $html;

    // Batch fetch — one query for every distinct shared id on the page
    // Also fetch system_region_config to detect system widgets.
    $ids_str = implode(',', $ids);
    $rows    = db_items("SELECT id, tree_json, system_region_config FROM shared_components WHERE id IN ($ids_str)");
    $by_id   = array();
    if ($rows) {
        foreach ($rows as $r) {
            $by_id[(int)$r['id']] = array(
                'tree_json'            => $r['tree_json'],
                'system_region_config' => $r['system_region_config'],
            );
        }
    }

    // Render each id's tree_json once, then do a single str_replace per marker.
    // System widgets (those with system_region_config) emit a dedicated marker instead
    // of being rendered as static HTML — get_page_content.php processes them later
    // via _expand_system_widgets(), which wires them into the dynamic render pipeline.
    $replacements = array();
    foreach ($ids as $sid) {
        // Diagnostic comments are admin-visible only (view-source). They appear when:
        //   - shared_components row missing (deleted but still referenced)
        //   - tree_json column NULL/empty (widget never persisted its tree)
        //   - tree_json invalid JSON (corruption)
        //   - tree renders to empty (root.children=[] — empty widget body)
        // Comments are HTML — invisible in browsers, regex-matchable for support.
        if (!isset($by_id[$sid])) {
            $replacements[$sid] = '<!-- pg-shared-ref:' . $sid . ' missing in shared_components -->';
            continue;
        }

        // System widget: defer to dynamic renderer
        if (!empty($by_id[$sid]['system_region_config'])) {
            $replacements[$sid] = '<!--pg-system-widget:' . $sid . '-->';
            continue;
        }

        $tree_json_raw = isset($by_id[$sid]['tree_json']) ? $by_id[$sid]['tree_json'] : '';
        if ($tree_json_raw === '' || $tree_json_raw === null) {
            $replacements[$sid] = '<!-- pg-shared-ref:' . $sid . ' tree_json empty in DB (designer never saved or _flushDirtyShared did not fire) -->';
            continue;
        }

        $sc_tree = json_decode($tree_json_raw, true);
        if (!is_array($sc_tree)) {
            $replacements[$sid] = '<!-- pg-shared-ref:' . $sid . ' tree_json not valid JSON -->';
            continue;
        }
        // _render_tree_node emits markers for any shared_ref it encounters. Phase-1 validation
        // forbids nested shared_refs in shared_components.tree_json, so this *should* produce
        // marker-free HTML. Still, recurse defensively with depth guard.
        $rendered = _render_tree_node($sc_tree, 0);
        if (strpos($rendered, '<!--pg-shared-ref:') !== false) {
            $rendered = _expand_shared_refs($rendered, $depth + 1);
        }
        if (trim($rendered) === '') {
            // Tree parsed fine but produced no visible output — most common cause:
            // root.children=[] (widget body is empty). Emit a hint so admin sees it.
            $rendered = '<!-- pg-shared-ref:' . $sid . ' rendered empty (tree has no children) -->';
        }
        $replacements[$sid] = $rendered;
    }

    // Single-pass replace using preg_replace_callback (stable, no re-scan of replacement text)
    return preg_replace_callback(
        '/<!--pg-shared-ref:(\d+)-->/',
        function ($m) use ($replacements) {
            $sid = (int)$m[1];
            return isset($replacements[$sid]) ? $replacements[$sid] : '';
        },
        $html
    );
}

// ========================= SYSTEM WIDGET MARKER EXPANSION =======================================
//
// `_expand_shared_refs()` emits `<!--pg-system-widget:ID-->` for every shared component that
// carries a `system_region_config` JSON. This function resolves those markers by calling the
// appropriate dynamic renderer (get_form_list_view.php, catalog renderer, etc.) using the
// compiled templates stored in system_region_config.
//
// URL param namespace: `page_number` / `query` GET strings are shared across the page —
// multiple system widgets on the same page therefore share pagination/search state.
// (We don't use bare `page` because Pinegrap's router reserves it for the URL slug.)
//
// Called from get_page_content.php immediately after _expand_system_widgets() in the pipeline.
function _expand_system_widgets($html, $mode = 'preview', $email = false)
{
    if (strpos($html, '<!--pg-system-widget:') === false) return $html;

    if (!preg_match_all('/<!--pg-system-widget:(\d+)-->/', $html, $matches)) return $html;
    $ids     = array_unique(array_map('intval', $matches[1]));
    $ids     = array_values(array_filter($ids, function ($i) { return $i > 0; }));
    if (empty($ids)) return $html;

    // Batch fetch configs + tree_json
    $ids_str = implode(',', $ids);
    $rows    = db_items(
        "SELECT id, name, tree_json, system_region_config
         FROM shared_components
         WHERE id IN ($ids_str) AND system_region_config IS NOT NULL"
    );
    $configs = array();
    if ($rows) {
        foreach ($rows as $r) {
            $cfg = json_decode($r['system_region_config'], true);
            if (!is_array($cfg)) continue;
            $cfg['_name']      = $r['name'];
            $cfg['_tree_json'] = $r['tree_json'];  // stored for template compilation
            $configs[(int)$r['id']] = $cfg;
        }
    }

    return preg_replace_callback(
        '/<!--pg-system-widget:(\d+)-->/',
        function ($m) use ($configs, $mode, $email) {
            $sid = (int)$m[1];
            if (!isset($configs[$sid])) return '';

            $cfg         = $configs[$sid];
            $region_type = isset($cfg['regionType']) ? $cfg['regionType'] : '';
            $tree_json   = isset($cfg['_tree_json']) ? $cfg['_tree_json'] : '';

            // The widget points at a custom form via page.page_id. Field name evolved:
            //   custom_form_page_id  ← current (matches Pinegrap's existing convention)
            //   form_page_id         ← prior iteration
            //   source_page          ← original (page name string)
            // All three resolve to the same thing — page.page_id of the custom form.
            $form_pid = 0;
            if (isset($cfg['custom_form_page_id']) && (int)$cfg['custom_form_page_id'] > 0) {
                $form_pid = (int)$cfg['custom_form_page_id'];
            } elseif (isset($cfg['form_page_id']) && (int)$cfg['form_page_id'] > 0) {
                $form_pid = (int)$cfg['form_page_id'];
            } elseif (!empty($cfg['source_page'])) {
                $form_pid = (int)db_value(
                    "SELECT page_id FROM page WHERE page_name = '" . e($cfg['source_page']) . "' LIMIT 1"
                );
            }

            // 'form_list_view' (current scope) — list submissions of the chosen custom form.
            // Legacy regionType values are accepted so older widgets keep rendering
            // without requiring users to re-save them.
            $is_form_list_view = (
                $region_type === 'form_list_view' ||
                $region_type === 'submitted_forms_list' ||
                $region_type === 'form_list' ||
                $region_type === 'blog_list'
            );
            if ($is_form_list_view && $form_pid > 0) {
                // Pass the raw tree_json to _render_system_widget_form_list — it splits
                // the tree itself (loop_area extraction) and decides what to render
                // statically vs per-record. _apply_bindings() inside _render_*_html turns
                // any node.props._bindings entries into ^^fieldName^^ tokens that get
                // replaced per-record by the renderer.
                if (!$tree_json) return '<!-- pg-system-widget:' . $sid . ' has no tree_json (designer never saved) -->';
                return _render_system_widget_form_list($form_pid, $tree_json, $sid, $cfg);
            }

            // 'catalog_listing' — list products from a chosen product_group (or all
            // groups when product_group_id = 0). Loop_area/static split is handled
            // inside the renderer, mirroring the form_list_view pattern.
            if ($region_type === 'catalog_listing') {
                if (!$tree_json) return '<!-- pg-system-widget:' . $sid . ' has no tree_json (designer never saved) -->';
                $product_group_id = isset($cfg['product_group_id']) ? (int)$cfg['product_group_id'] : 0;
                return _render_system_widget_catalog_listing($product_group_id, $tree_json, $sid, $cfg);
            }

            // 'catalog_item_view' — single product detail. Resolves the product from
            // the URL path segment (/page_name/product_address_name) or ?pid=N fallback.
            // Renders the loop_area template ONCE. Companion to catalog_listing: list rows
            // link here via ^^__detail_url^^. Optional product_group_id acts as a
            // security filter (restricts rendering to products in that group).
            if ($region_type === 'catalog_item_view') {
                if (!$tree_json) return '<!-- pg-system-widget:' . $sid . ' has no tree_json (designer never saved) -->';
                $product_group_id = isset($cfg['product_group_id']) ? (int)$cfg['product_group_id'] : 0;
                return _render_system_widget_catalog_item_view($product_group_id, $tree_json, $sid, $cfg);
            }

            // 'form_item_view' — single submitted-form detail. Reads ?r= from URL,
            // resolves to one row of `forms` joined to `form_data`, replaces tokens
            // in the loop_area template ONCE (no per-record iteration). Companion
            // to form_list_view: list rows link here via ^^__detail_url^^.
            if ($region_type === 'form_item_view' && $form_pid > 0) {
                if (!$tree_json) return '<!-- pg-system-widget:' . $sid . ' has no tree_json (designer never saved) -->';
                return _render_system_widget_form_item_view($form_pid, $tree_json, $sid, $cfg);
            }

            // 'my_account' — logged-in user's account info and optionally their form
            // submissions. No required source param (reads USER_ID from session).
            if ($region_type === 'my_account') {
                if (!$tree_json) return '<!-- pg-system-widget:' . $sid . ' has no tree_json (designer never saved) -->';
                return _render_system_widget_my_account($tree_json, $sid, $cfg);
            }

            // 'login_form' — the sign-in form, posting to index.php. $mode: an
            // editor sees the form even while signed in.
            if ($region_type === 'login_form') {
                if (!$tree_json) return '<!-- pg-system-widget:' . $sid . ' has no tree_json (designer never saved) -->';
                return _render_system_widget_login_form($tree_json, $sid, $cfg, $mode);
            }

            // 'forgot_password' — the reset-request form, posting to
            // forgot_password.php.
            if ($region_type === 'forgot_password') {
                if (!$tree_json) return '<!-- pg-system-widget:' . $sid . ' has no tree_json (designer never saved) -->';
                return _render_system_widget_forgot_password($tree_json, $sid, $cfg, $mode);
            }

            // 'search_results' — renders a search result list from $_GET['q'].
            // Static tokens: ^^__search_query^^, ^^__result_count^^.
            // Loop tokens (per result): ^^__result_title^^, ^^__result_url^^,
            //   ^^__result_excerpt^^, ^^__result_type^^.
            if ($region_type === 'search_results') {
                if (!$tree_json) return '<!-- pg-system-widget:' . $sid . ' has no tree_json (designer never saved) -->';
                return _render_system_widget_search_results($tree_json, $sid, $cfg);
            }

            // 'registration' — the sign-up form, posting to
            // registration_entrance.php. $mode: an editor sees the form even
            // while signed in.
            if ($region_type === 'registration') {
                if (!$tree_json) return '<!-- pg-system-widget:' . $sid . ' has no tree_json (designer never saved) -->';
                return _render_system_widget_registration($tree_json, $sid, $cfg, $mode);
            }

            // 'shopping_cart' — renders the active session cart with loop_area per item.
            // Static tokens: ^^__cart_total^^, ^^__cart_count^^, ^^__checkout_url^^.
            // Loop tokens: ^^__item_name^^, ^^__item_qty^^, ^^__item_price^^,
            //              ^^__item_total^^, ^^__item_image^^, ^^__item_url^^,
            //              ^^__item_remove_url^^.
            if ($region_type === 'shopping_cart') {
                if (!$tree_json) return '<!-- pg-system-widget:' . $sid . ' has no tree_json (designer never saved) -->';
                return _render_system_widget_shopping_cart($tree_json, $sid, $cfg);
            }

            // 'express_order' — single-page checkout: cart + billing + shipping (when needed)
            //   + payment methods. Submits to legacy `express_order.php` action handler so
            //   all order/payment plumbing (including Iyzipay 3DS, PayPal Express, etc.)
            //   is reused unchanged. Designer uses bindable sections for layout control.
            //
            //   Bindable sections (designer-placed via _bindings.section='X'):
            //     cart_summary, special_offer, gift_card_code,
            //     shipping_address, billing_address, custom_billing,
            //     payment_methods, order_totals, terms_checkbox
            //
            //   Bindable actions (designer-placed via _bindings.action='X'):
            //     submit_purchase  → name=submit_purchase_now (final order submit)
            //     submit_update    → name=submit_update (recalc cart, formnovalidate)
            //
            //   The renderer also handles 3DSecure callbacks (`?mode=...`) by forwarding
            //   the request to express_order.php verbatim — so paid-by-3DS flows that
            //   land back on the page complete cleanly without re-rendering the form.
            if ($region_type === 'express_order') {
                if (!$tree_json) return '<!-- pg-system-widget:' . $sid . ' has no tree_json (designer never saved) -->';
                return _render_system_widget_express_order($tree_json, $sid, $cfg);
            }

            // 'order_view' — renders a single completed order (id from $_GET['order_id']).
            // Static tokens: ^^__order_no^^, ^^__order_date^^, ^^__order_status^^,
            //                ^^__order_total^^, ^^__shipping_address^^.
            // Loop tokens: ^^__item_name^^, ^^__item_qty^^, ^^__item_price^^,
            //              ^^__item_total^^, ^^__item_image^^.
            if ($region_type === 'order_view') {
                if (!$tree_json) return '<!-- pg-system-widget:' . $sid . ' has no tree_json (designer never saved) -->';
                return _render_system_widget_order_view($tree_json, $sid, $cfg);
            }

            // 'membership' — the membership activation form, posting to
            // membership_entrance.php. $mode: an editor sees the form even
            // while signed in.
            if ($region_type === 'membership') {
                if (!$tree_json) return '<!-- pg-system-widget:' . $sid . ' has no tree_json (designer never saved) -->';
                return _render_system_widget_membership($tree_json, $sid, $cfg, $mode);
            }

            // 'custom_form' — a form drawn in the editor; its controls are its
            // fields. No loop_area iteration.
            // Tokens: ^^__form_title^^, ^^__error_message^^, ^^__success_message^^
            // $mode decides whether office-use-only controls are shown.
            if ($region_type === 'custom_form') {
                if (!$tree_json) return '<!-- pg-system-widget:' . $sid . ' has no tree_json (designer never saved) -->';
                return _render_system_widget_custom_form($tree_json, $sid, $cfg, $mode);
            }

            // 'calendar_view' — renders a calendar event list with loop_area per event.
            // Static tokens: ^^__month_name^^, ^^__year^^, ^^__prev_month_url^^, ^^__next_month_url^^
            // Loop tokens:   ^^__event_title^^, ^^__event_date^^, ^^__event_time^^,
            //                ^^__event_url^^, ^^__event_excerpt^^
            if ($region_type === 'calendar_view') {
                if (!$tree_json) return '<!-- pg-system-widget:' . $sid . ' has no tree_json (designer never saved) -->';
                return _render_system_widget_calendar_view($tree_json, $sid, $cfg);
            }

            // Future region types handled here.
            // For now return empty — renders nothing rather than a broken page.
            return '';
        },
        $html
    );
}

// Walk a widget tree (deep clone) and locate the FIRST loop_area node. Replaces it
// in-place with a custom_html marker (`<!--pg-loop-slot-->`) so the static portion
// of the widget renders normally with a placeholder where the loop output will land.
// Returns: array(
//   'static_tree'    => deep-cloned tree with loop_area replaced by marker (or original
//                       tree if no loop_area was found),
//   'loop_children'  => the loop_area's children array (per-record template), or null
//                       if no loop_area exists.
// )
function _split_widget_tree($tree)
{
    if (!is_array($tree)) return array('static_tree' => $tree, 'loop_children' => null);
    // Deep-clone via JSON round-trip — the tree is small (a single widget), so this is fine.
    $clone = json_decode(json_encode($tree), true);
    $loop_children = null;

    $marker_node = array(
        'type'     => 'content',
        'props'    => array('contentType' => 'custom_html', 'html' => '<!--pg-loop-slot-->'),
        'children' => array(),
    );

    $walk = function (&$node) use (&$walk, &$loop_children, $marker_node) {
        if (!is_array($node) || empty($node['children']) || $loop_children !== null) return;
        foreach ($node['children'] as $i => $child) {
            if ($loop_children !== null) return;
            if (isset($child['type']) && $child['type'] === 'loop_area') {
                $loop_children = isset($child['children']) ? $child['children'] : array();
                $node['children'][$i] = $marker_node;
                return;
            }
            $walk($node['children'][$i]);
        }
    };
    $walk($clone);

    return array('static_tree' => $clone, 'loop_children' => $loop_children);
}

// Helper: build a URL query string with one or more keys removed.
// Avoids chained `_pg_sw_build_query` calls when clearing multiple filters
// at once (active filter chips' clear-all link, price chip's bilateral
// pmin+pmax clear).
function _build_clear_filters_query($keys_to_clear)
{
    $params = (is_array($_GET)) ? $_GET : array();
    foreach ($keys_to_clear as $k) {
        if (isset($params[$k])) unset($params[$k]);
    }
    return http_build_query($params);
}

// Build a URL query string with ONE parameter changed (or removed if value is '').
// Optionally clears a SECOND parameter at the same time — used by the search box
// "clear" button which resets both the query and the page index in one step.
// Preserves all OTHER current $_GET params verbatim.
function _pg_sw_build_query($key, $value, $reset_key = null, $reset_value = null)
{
    $params = (is_array($_GET)) ? $_GET : array();
    if ($value === '' || $value === null) {
        unset($params[$key]);
    } else {
        $params[$key] = (string)$value;
    }
    if ($reset_key !== null) {
        if ($reset_value === '' || $reset_value === null) {
            unset($params[$reset_key]);
        } else {
            $params[$reset_key] = (string)$reset_value;
        }
    }
    return http_build_query($params);
}

/**
 * Append/replace a single query-string parameter on a URL while keeping
 * everything else (path, fragment, other query keys) intact.
 *
 * Used by catalog_listing's "Sepete Ekle (sayfada kal)" mode to produce
 * URLs like "/urunler?cart_added=42" so the post-add redirect lands on the
 * exact same listing page (with the toast trigger appended) instead of
 * losing pagination/filter state through a full page swap.
 *
 * Strips the existing key first so repeat clicks don't accumulate
 * duplicate `cart_added=` entries on the URL.
 */
function _pg_sw_append_query($url, $key, $value)
{
    if ($url === '') return '?' . urlencode($key) . '=' . urlencode($value);
    $hash_pos = strpos($url, '#');
    $hash = '';
    if ($hash_pos !== false) {
        $hash = substr($url, $hash_pos);
        $url  = substr($url, 0, $hash_pos);
    }
    $q_pos = strpos($url, '?');
    $base  = $q_pos === false ? $url : substr($url, 0, $q_pos);
    $query = $q_pos === false ? ''  : substr($url, $q_pos + 1);
    if ($query !== '') {
        $pairs = explode('&', $query);
        $kept  = array();
        foreach ($pairs as $pair) {
            $eq = strpos($pair, '=');
            $pname = $eq === false ? $pair : substr($pair, 0, $eq);
            if (urldecode($pname) === $key) continue;
            $kept[] = $pair;
        }
        $kept[] = urlencode($key) . '=' . urlencode($value);
        return $base . '?' . implode('&', $kept) . $hash;
    }
    return $base . '?' . urlencode($key) . '=' . urlencode($value) . $hash;
}

// ========================= CUSTOM PHP MARKER EXPANSION ==========================================
//
// `_render_tree_node` emits `<!--pg-custom-php:BASE64-->` for every `custom_php` content node.
// This function decodes and evals the PHP at page-render time so the output reflects live state
// (date(), DB queries, session, request params — anything PHP can see at render).
//
// Security model: a custom_php node can only be written by a designer/admin. A manager or
// user save goes through pg_designer_merge_restricted_tree(), which restores every
// custom_php node from the stored tree and drops any the submission added (see
// _pg_dm_locked_kind in includes/designer_access.php), so only trusted authors can plant
// these markers. The source never lives in style_code as executable text — it sits inside
// an HTML comment until this expansion runs. Output is captured via ob_start/ob_get_clean so
// echo'd HTML lands where the marker was.
//
// Errors are caught and rendered as an inline HTML comment (admin-visible in view-source) so a
// broken snippet never takes down the page.
function _expand_custom_php($html)
{
    if (strpos($html, '<!--pg-custom-php:') === false) return $html;

    return preg_replace_callback(
        '/<!--pg-custom-php:([A-Za-z0-9+\/=]*)-->/',
        function ($m) {
            $src = base64_decode($m[1], true);
            if ($src === false) return '<!-- pg-custom-php: invalid base64 -->';
            ob_start();
            try {
                // @ intentionally omitted — PHP warnings/notices surface in error logs while
                // still allowing the catch below to handle fatal-like exceptions.
                eval($src);
                $out = ob_get_clean();
            } catch (\Throwable $e) {
                ob_end_clean();
                $out = '<!-- pg-custom-php error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . ' -->';
            } catch (\Exception $e) {
                // PHP 5.6/7.0 legacy — \Throwable introduced in 7.0; keep both for safety.
                ob_end_clean();
                $out = '<!-- pg-custom-php error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . ' -->';
            }
            return $out;
        },
        $html
    );
}

// ========================= TREE JSON SANITIZATION (F1/F7 — server-side defense in depth) =====
//
// Walk a decoded tree and force every `shared_ref` node's children to `[]`. Invoked at save
// time so even a malformed client that tries to ship inline-expanded shared content cannot
// pollute `style_tree_json`. This is the server-side twin of `_sanitizeDynamicPlaceholders`
// in style_designer.js — either one is sufficient, both together make the invariant bulletproof.
function _sanitize_dynamic_placeholders(&$node)
{
    if (!is_array($node)) return;
    if (isset($node['type']) && $node['type'] === 'shared_ref') {
        $node['children'] = array();
        return; // black box — no deeper walk needed
    }
    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child) _sanitize_dynamic_placeholders($child);
        unset($child);
    }
}

/**
 * Re-type the object-valued keys of a decoded tree so json_encode() writes
 * them as `{}`.
 *
 * json_decode($json, true) turns an empty JSON object into an empty PHP
 * array, and json_encode() writes an empty PHP array as `[]`. A node saved
 * with `"props": {}` therefore comes back to the editor as `"props": []` —
 * a JavaScript ARRAY. A property set on it (`props.cssClass = '...'`) is
 * accepted and then dropped by JSON.stringify, so the change lives until
 * the next save and no further. The <body> node of every new page is the
 * usual victim: its props start empty.
 *
 * The keys that hold objects are known, so the empty ones are re-typed
 * before encoding: `props`, `props._bindings`, `props._cf`,
 * `props._cf.ids`. Real lists (`children`, `_attrs`, `_styles`) are left
 * as they are. Walks shared_ref children too — a widget tree passes
 * through here as well.
 */
function pg_designer_tree_objects(&$node)
{
    if (!is_array($node)) return;
    if (array_key_exists('props', $node)) {
        if (is_array($node['props'])) {
            if (empty($node['props'])) {
                $node['props'] = new stdClass();
            } else {
                foreach (array('_bindings', '_cf') as $k) {
                    if (isset($node['props'][$k]) && is_array($node['props'][$k]) && empty($node['props'][$k])) {
                        $node['props'][$k] = new stdClass();
                    }
                }
                if (isset($node['props']['_cf']) && is_array($node['props']['_cf'])
                    && isset($node['props']['_cf']['ids']) && is_array($node['props']['_cf']['ids'])
                    && empty($node['props']['_cf']['ids'])) {
                    $node['props']['_cf']['ids'] = new stdClass();
                }
            }
        }
    }
    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child) pg_designer_tree_objects($child);
        unset($child);
    }
}

/**
 * The one way a tree array becomes stored JSON: object keys re-typed
 * (pg_designer_tree_objects), then encoded with the flags every tree
 * writer uses. Returns '' when encoding fails.
 */
function pg_designer_tree_encode($tree)
{
    if (!is_array($tree)) return '';
    pg_designer_tree_objects($tree);
    $json = json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return ($json === false) ? '' : $json;
}

/**
 * Decode stored tree JSON for the editor payload: associative arrays with
 * the object keys re-typed, so a tree stored with `[]` in an object slot
 * (written before pg_designer_tree_encode existed) still reaches the
 * browser as `{}`. Returns null for empty or invalid JSON.
 */
function pg_designer_tree_decode($json)
{
    $json = (string)$json;
    if ($json === '') return null;
    $tree = json_decode($json, true);
    if (!is_array($tree)) return null;
    pg_designer_tree_objects($tree);
    return $tree;
}

// Drop entries from a designer assets JSON (page_custom_css / page_custom_js)
// whose backing file is no longer in the `files` table. Out-of-band deletes
// (e.g. a "Sil (kalıcı)" via API followed by a page reload before the user
// hit Save) can leave the page row referencing a phantom file: the JSON
// keeps the entry, the on-disk file is gone, and the asset re-appears in
// the editor with a broken URL. Filtering on load drops the ghost so the
// next Save persists a clean JSON. Returns the (possibly cleaned) JSON
// string. Non-JSON input passes through unchanged for legacy plain text.
function _filter_orphan_designer_files($json_str)
{
    if (!is_string($json_str) || $json_str === '') return $json_str;
    $arr = json_decode($json_str, true);
    if (!is_array($arr)) return $json_str;
    $clean = array();
    $changed = false;
    foreach ($arr as $entry) {
        if (!is_array($entry)) { $clean[] = $entry; continue; }
        // Only managed entries (created via designer_file/create) are
        // grounded in a `files` row. CDN externals, locked Bootstrap
        // sentinels and legacy inline rows pass through untouched.
        if (!empty($entry['_file']) && !empty($entry['name'])) {
            $exists = db_value("SELECT id FROM files WHERE name = '" . escape($entry['name']) . "' LIMIT 1");
            if (!$exists) { $changed = true; continue; }
        }
        $clean[] = $entry;
    }
    if (!$changed) return $json_str;
    return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Stamp who wrote each note, and when.
 *
 * Called by every path that writes a page tree, because a note is a message
 * to the next person and a message whose sender the browser supplies is one
 * anybody can sign with anybody's name.
 *
 * The rule is narrow on purpose: a note whose TEXT is unchanged keeps the
 * author it already had, so saving a page does not re-attribute every note on
 * it to whoever happened to press Save.
 */
function pg_stamp_note_authors(&$tree, $page_id, $user)
{
    if (!is_array($tree) || (int)$page_id <= 0) return;

    $stored_notes = array();
    $prev_json = pg_page_tree_json((int)$page_id);
    if ($prev_json !== '') {
        $prev = json_decode($prev_json, true);
        if (is_array($prev)) {
            $collect = function ($n) use (&$collect, &$stored_notes) {
                if (!is_array($n)) return;
                if (isset($n['_id']) && isset($n['props']['_notes'])) {
                    $stored_notes[(string)$n['_id']] = array(
                        'text' => (string)$n['props']['_notes'],
                        'by'   => isset($n['props']['_notes_by']) ? (string)$n['props']['_notes_by'] : '',
                        'at'   => isset($n['props']['_notes_at']) ? (int)$n['props']['_notes_at'] : 0,
                    );
                }
                if (!empty($n['children']) && is_array($n['children'])) foreach ($n['children'] as $c) $collect($c);
            };
            $collect($prev);
        }
    }

    $author = '';
    $uid = is_array($user) ? (int)(isset($user['id']) ? $user['id'] : 0) : (int)$user;
    if (function_exists('pg_collab_user_brief')) {
        $brief = pg_collab_user_brief($uid);
        if (is_array($brief) && !empty($brief['name'])) $author = (string)$brief['name'];
    }
    if ($author === '' && isset($_SESSION['sessionusername'])) $author = (string)$_SESSION['sessionusername'];

    $stamp = function (&$n) use (&$stamp, $stored_notes, $author) {
        if (!is_array($n)) return;
        if (!empty($n['props']['_notes'])) {
            $id   = isset($n['_id']) ? (string)$n['_id'] : '';
            $prev = ($id !== '' && isset($stored_notes[$id])) ? $stored_notes[$id] : null;
            if ($prev && $prev['text'] === (string)$n['props']['_notes'] && $prev['by'] !== '') {
                $n['props']['_notes_by'] = $prev['by'];
                $n['props']['_notes_at'] = $prev['at'];
            } else {
                $n['props']['_notes_by'] = $author;
                $n['props']['_notes_at'] = time();
            }
        } else if (isset($n['props']) && is_array($n['props'])) {
            unset($n['props']['_notes_by'], $n['props']['_notes_at']);
        }
        if (!empty($n['children']) && is_array($n['children'])) {
            foreach ($n['children'] as &$c) $stamp($c);
            unset($c);
        }
    };
    $stamp($tree);
}


function save_system_style($data)
{
    $style_id        = !empty($data['style_id'])   ? (int)$data['style_id']   : 0;
    $user_id         = isset($data['user_id'])      ? (int)$data['user_id']    : 0;
    $name            = isset($data['name'])         ? $data['name']            : '';
    $tree_json       = isset($data['style_tree_json']) ? $data['style_tree_json'] : '';
    // get_theme_options() offers "no theme" as ''; the column is an integer.
    $theme_id        = isset($data['theme_id'])     ? (int)$data['theme_id']   : 0;
    $abc             = isset($data['additional_body_classes']) ? $data['additional_body_classes'] : '';
    $collection      = isset($data['collection'])   ? $data['collection']      : 'a';
    $style_head      = isset($data['style_head'])   ? $data['style_head']      : '';
    $empty_cell_pct  = isset($data['style_empty_cell_width_percentage']) ? $data['style_empty_cell_width_percentage'] : '';

    $tree = json_decode($tree_json, true);

    // INVARIANT: `shared_ref` nodes must never carry inline-expanded children into the DB.
    // Sanitize defensively even if the client already did — belt + suspenders — and re-encode
    // so `style_tree_json` is the post-sanitize canonical form.
    //
    // Note authors are NOT stamped here: this function has no page, and on
    // every migrated install it receives no tree at all — the tree belongs to
    // the page and is written by pg_designer_save_page(), which is where the
    // stamping lives. A call here read $page_id and $user, neither of which
    // exists in this scope, so it silently did nothing.
    if (is_array($tree)) {
        _sanitize_dynamic_placeholders($tree);
        $tree_json = pg_designer_tree_encode($tree);
    }

    $style_code = ($tree && is_array($tree)) ? generate_style_code_from_tree($tree, $name, $abc) : '';

    // Shared design assets (multi-page designer). Written only when the
    // columns exist AND the caller supplied them; an older caller that
    // passes no assets leaves the stored values alone.
    $sql_assets_set   = '';
    $sql_assets_field = '';
    $sql_assets_value = '';
    if (pg_multi_page_design_ready() && array_key_exists('style_custom_css', $data)) {
        $_css   = escape((string)(isset($data['style_custom_css'])   ? $data['style_custom_css']   : ''));
        $_js    = escape((string)(isset($data['style_custom_js'])    ? $data['style_custom_js']    : ''));
        $_fonts = escape((string)(isset($data['style_custom_fonts']) ? $data['style_custom_fonts'] : ''));
        $sql_assets_set   = "style_custom_css = '$_css', style_custom_js = '$_js', style_custom_fonts = '$_fonts',";
        $sql_assets_field = 'style_custom_css, style_custom_js, style_custom_fonts,';
        $sql_assets_value = "'$_css', '$_js', '$_fonts',";
    }

    // The style's own tree/code are a FALLBACK now — every page carries its
    // own since the multi-page designer. Callers that no longer have a tree
    // for the style (they save pages separately) pass none, and we must not
    // wipe the stored fallback with an empty string.
    $sql_tree_set = '';
    if ($tree_json !== '') {
        $sql_tree_set = "style_code = '" . escape($style_code) . "', style_tree_json = '" . escape($tree_json) . "',";
    }

    $sql_social_set   = '';
    $sql_social_field = '';
    $sql_social_value = '';
    if (SOCIAL_NETWORKING == TRUE && isset($data['social_networking_position'])) {
        $snp              = escape($data['social_networking_position']);
        $sql_social_set   = "social_networking_position = '$snp',";
        $sql_social_field = 'social_networking_position,';
        $sql_social_value = "'$snp',";
    }

    if ($style_id > 0) {
        db("UPDATE style SET
                style_name                       = '" . escape($name) . "',
                style_type                       = 'system',
                style_layout                     = 'visual_designer',
                $sql_tree_set
                style_head                       = '" . escape($style_head) . "',
                style_empty_cell_width_percentage= '" . escape($empty_cell_pct) . "',
                $sql_social_set
                $sql_assets_set
                theme_id                         = '" . escape($theme_id) . "',
                additional_body_classes          = '" . escape($abc) . "',
                collection                       = '" . escape($collection) . "',
                style_user                       = '$user_id',
                style_timestamp                  = UNIX_TIMESTAMP()
            WHERE style_id = '$style_id'");
        return $style_id;
    } else {
        db("INSERT INTO style (
                style_name,
                style_type,
                style_layout,
                style_empty_cell_width_percentage,
                style_head,
                style_code,
                style_tree_json,
                $sql_social_field
                $sql_assets_field
                theme_id,
                additional_body_classes,
                collection,
                style_user,
                style_timestamp)
            VALUES (
                '" . escape($name) . "',
                'system',
                'visual_designer',
                '" . escape($empty_cell_pct) . "',
                '" . escape($style_head) . "',
                '" . escape($style_code) . "',
                '" . escape($tree_json) . "',
                $sql_social_value
                $sql_assets_value
                '" . escape($theme_id) . "',
                '" . escape($abc) . "',
                '" . escape($collection) . "',
                '$user_id',
                UNIX_TIMESTAMP())");
        return mysqli_insert_id(db::$con);
    }
}

/**
 * Every page that belongs to a visual design, shaped for the editor's tab bar.
 *
 * A design (one `style` row, style_layout = 'visual_designer') owns any number
 * of pages through the `page_style` foreign key. Each page carries its own
 * layout tree; the design carries the shared assets. This returns the pages
 * in creation order with everything the editor needs to open a tab without a
 * second round-trip: identity, the per-page settings the "Bu sayfa" settings
 * tab edits, and the tree JSON.
 *
 * Legacy bridge: a design saved by the single-page editor has ONE page whose
 * name equals the style name, and — before the 2026.4.4 migration ran — that
 * page may still carry an empty page_tree_json while the tree sits on the
 * style. pg_page_tree_sql_expr() resolves the fallback. A page that matches
 * by name but was never linked (page_style = 0, pre-2026.1 data) is linked
 * on the way through, exactly as the old editor did.
 *
 * @param int    $style_id
 * @param string $style_name  Used only for the legacy name-match fallback
 * @return array              List of page descriptors (possibly empty)
 */
/**
 * Folder ids inside the recycle bin — the bin folder itself and every
 * descendant. A page sent to the bin keeps its page_style link (that is what
 * brings it back onto its design when restored), so anything listing a
 * design's pages has to exclude these folders explicitly.
 */
function pg_recycle_bin_folder_ids()
{
    static $ids = null;
    if ($ids !== null) return $ids;

    $ids = array();
    $bin = (int)pg_recycle_bin_folder_id();
    if ($bin <= 0) return $ids;

    $children = array();
    $rows = db_items("SELECT folder_id, folder_parent FROM folder");
    foreach ((is_array($rows) ? $rows : array()) as $r) {
        $children[(int)$r['folder_parent']][] = (int)$r['folder_id'];
    }
    $ids   = array($bin);
    $stack = array($bin);
    while (count($stack) > 0) {
        $f = array_pop($stack);
        if (empty($children[$f])) continue;
        foreach ($children[$f] as $c) {
            $ids[]   = $c;
            $stack[] = $c;
        }
    }
    return $ids;
}

/**
 * The editor's UI text keys: every _sdT('…') key in
 * assets/js/style_designer.js. The keys are English source text, so
 * the JS file itself is the single list — there is no second copy to keep in
 * step. Scanning 2.5 MB on every editor load would be wasteful; the result is
 * cached in data/temp next to the file's mtime and size, and rebuilt when
 * the file changes.
 *
 * @return array  Keys, unescaped (\' → ', \\ → \), unique, in file order
 */
function pg_designer_i18n_keys()
{
    static $keys = null;
    if ($keys !== null) return $keys;
    $keys = array();

    $js = PG_FUNCTIONS_DIR . '/assets/js/style_designer.js';
    if (!is_file($js)) return $keys;
    $stamp = @filemtime($js) . ':' . @filesize($js);

    $cache = PG_FUNCTIONS_DIR . '/data/temp/designer_i18n_keys.json';
    if (is_file($cache)) {
        $cached = json_decode((string)@file_get_contents($cache), true);
        if (is_array($cached) && isset($cached['stamp'], $cached['keys']) && $cached['stamp'] === $stamp && is_array($cached['keys'])) {
            $keys = $cached['keys'];
            return $keys;
        }
    }

    $source = (string)@file_get_contents($js);
    if ($source !== '' && preg_match_all("/_sdT\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $source, $m)) {
        $seen = array();
        foreach ($m[1] as $raw) {
            $key = str_replace(array("\\'", '\\\\'), array("'", '\\'), $raw);
            if ($key === '' || isset($seen[$key])) continue;
            $seen[$key] = true;
            $keys[] = $key;
        }
    }
    @file_put_contents($cache, json_encode(array('stamp' => $stamp, 'keys' => $keys), JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $keys;
}

/**
 * key → lang(key) for the editor (sdDesign.i18n). Empty when the site's
 * language is English: the keys are the English text and the client falls
 * back to the key, so there is nothing to send.
 */
function pg_designer_i18n_map()
{
    if (lang(array('info' => '')) === 'en') return array();
    $map = array();
    foreach (pg_designer_i18n_keys() as $key) {
        $value = lang($key);
        if ($value !== $key) $map[$key] = $value;
    }
    return $map;
}

/** SQL fragment (" AND col NOT IN (...)") that drops binned pages; '' without a bin. */
function pg_designer_not_binned_sql($column = 'page.page_folder')
{
    $ids = pg_recycle_bin_folder_ids();
    if (count($ids) === 0) return '';
    return " AND $column NOT IN (" . implode(',', array_map('intval', $ids)) . ")";
}

/**
 * Ids of the styles the visual editor made (style_layout = 'visual_designer'),
 * once per request. Empty array when there are none.
 */
function pg_visual_design_style_ids()
{
    static $ids = null;
    if ($ids === null) {
        $ids = array();
        $rows = db_items("SELECT style_id FROM style WHERE style_layout = 'visual_designer'");
        foreach ((is_array($rows) ? $rows : array()) as $r) $ids[(int)$r['style_id']] = true;
    }
    return $ids;
}

/**
 * Is this a visual-editor page? Yes when the page's OWN style is a visual
 * design, or when the page carries its own layout tree. A page that uses a
 * custom style, or inherits its style from the folder, is not — whatever
 * its layout_type says: that column reads 'system' for every standard page,
 * the add-page screen never even shows it.
 *
 * $page needs page_style; has_tree is optional (1/0).
 */
function pg_page_is_visual_design($page)
{
    $style_id = isset($page['page_style']) ? (int)$page['page_style'] : 0;
    if ($style_id > 0) {
        $ids = pg_visual_design_style_ids();
        if (isset($ids[$style_id])) return true;
    }
    return !empty($page['has_tree']);
}

/**
 * Where a page is edited: the visual editor for its own pages
 * (edit_system_style.php?id=<design>&page=<page>), edit_page.php for the
 * rest. Relative to the software directory.
 */
function pg_page_edit_url($page)
{
    if (pg_page_is_visual_design($page) && (int)$page['page_style'] > 0) {
        return 'edit_system_style.php?id=' . (int)$page['page_style'] . '&page=' . (int)$page['page_id'];
    }
    return 'edit_page.php?id=' . (int)$page['page_id'];
}

/**
 * What pg_page_is_visual_design() and pg_page_edit_url() need, by page id.
 *
 * For callers holding nothing but an id. page_tree_json only exists once
 * 4.22 has run, so it is asked for only where pg_multi_page_design_ready()
 * says it is there - selecting it unconditionally would make the query fail
 * on a site running this code before its upgrade.
 *
 * Memoized: the toolbar heading and the page panel both ask, and they ask
 * the same question about the same page within one request.
 *
 * Returns false when there is no such page.
 */
function pg_page_edit_row($page_id)
{
    static $cache = array();

    $page_id = (int)$page_id;

    if (!isset($cache[$page_id])) {
        $tree = pg_multi_page_design_ready() ? "(page_tree_json <> '') AS has_tree" : "0 AS has_tree";
        $cache[$page_id] = db_item("SELECT page_id, page_style, " . $tree . " FROM page WHERE page_id = '" . $page_id . "' LIMIT 1");
    }

    return $cache[$page_id];
}

/**
 * pg_page_edit_url() for a page id. '' when there is no such page.
 */
function pg_page_edit_url_by_id($page_id)
{
    $page = pg_page_edit_row($page_id);

    return is_array($page) ? pg_page_edit_url($page) : '';
}

/**
 * How many folders and LIVE pages use a style. Pages waiting in the
 * recycle bin do not count: as far as the operator is concerned they are
 * deleted, and a design they were the last pages of is empty.
 */
function pg_designer_style_live_usage($style_id)
{
    $style_id = (int)$style_id;
    return (int)db_value(
        "SELECT (SELECT COUNT(folder_id) FROM folder WHERE folder_style = '$style_id' OR mobile_style_id = '$style_id')
              + (SELECT COUNT(page_id)   FROM page   WHERE (page_style = '$style_id' OR mobile_style_id = '$style_id')" . pg_designer_not_binned_sql('page_folder') . ")");
}

function pg_designer_load_pages($style_id, $style_name = '')
{
    $style_id = (int)$style_id;
    if ($style_id <= 0) return array();

    $noindex_ready = pg_page_noindex_ready();
    $multi_ready   = pg_multi_page_design_ready();

    $cols = "page.page_id, page.page_name, page.page_folder, page.page_title,
             page.page_meta_description, page.page_search, page.page_search_keywords,
             page.sitemap, page.page_home,
             " . ($noindex_ready ? "page.noindex, page.nofollow," : "'0' AS noindex, '0' AS nofollow,") . "
             page.comments, page.comments_label, page.comments_allow_new_comments, page.comments_rating,
             page.page_timestamp,
             " . pg_page_tree_sql_expr() . " AS tree_json";

    $rows = db_items(
        "SELECT $cols
         FROM page
         INNER JOIN style ON page.page_style = style.style_id
         WHERE page.page_style = '$style_id'
           AND page.layout_type = 'system'" . pg_designer_not_binned_sql() . "
         ORDER BY page.page_id ASC");
    if (!is_array($rows)) $rows = array();

    // Legacy bridge: nothing linked, but a same-named system page exists.
    if (empty($rows) && $style_name !== '') {
        $orphan_id = (int)db_value(
            "SELECT page_id FROM page
             WHERE page_name = '" . e($style_name) . "'
               AND layout_type = 'system'
               AND (page_style = 0 OR page_style IS NULL)
             LIMIT 1");
        if ($orphan_id > 0) {
            db("UPDATE page SET page_style = '$style_id' WHERE page_id = '$orphan_id'");
            $rows = db_items(
                "SELECT $cols
                 FROM page
                 INNER JOIN style ON page.page_style = style.style_id
                 WHERE page.page_id = '$orphan_id'");
            if (!is_array($rows)) $rows = array();
        }
    }

    $out = array();
    foreach ($rows as $r) {
        $out[] = array(
            'page_id'               => (int)$r['page_id'],
            'page_name'             => (string)$r['page_name'],
            'page_folder'           => (int)$r['page_folder'],
            'page_title'            => (string)$r['page_title'],
            'page_meta_description' => (string)$r['page_meta_description'],
            'page_search'           => !empty($r['page_search']) ? 1 : 0,
            'page_search_keywords'  => (string)$r['page_search_keywords'],
            'page_sitemap'          => !empty($r['sitemap']) ? 1 : 0,
            'page_home'             => ((string)$r['page_home'] === 'yes') ? 1 : 0,
            'page_noindex'          => !empty($r['noindex']) ? 1 : 0,
            'page_nofollow'         => !empty($r['nofollow']) ? 1 : 0,
            'pg_comments'           => !empty($r['comments']) ? 1 : 0,
            'pg_comments_label'     => (string)$r['comments_label'],
            'pg_comments_allow_new' => !empty($r['comments_allow_new_comments']) ? 1 : 0,
            'pg_comments_rating'    => !empty($r['comments_rating']) ? 1 : 0,
            'page_timestamp'        => (int)$r['page_timestamp'],
            'tree_json'             => (string)$r['tree_json'],
        );
    }
    return $out;
}

/**
 * Pages that could be attached to a design from the "Sayfa Seç" picker.
 *
 * Only pages the visual editor made (layout_type = 'system'), excluding the
 * ones already on this design. Each row says which design it currently
 * belongs to so the picker can warn before moving it: attaching a page to
 * this design swaps its shared assets for this design's.
 */
function pg_designer_selectable_pages($style_id)
{
    $style_id = (int)$style_id;
    // `layout_type = 'system'` alone is not the test — the legacy system
    // layouts (interior, emailer-inline, …) use it too and their pages have
    // no tree the editor could open. A visual-designer page is one whose
    // style was made by the editor, or that already carries its own tree.
    $own_tree = pg_multi_page_design_ready()
        ? " OR (page.page_tree_json IS NOT NULL AND page.page_tree_json <> '')"
        : '';
    $rows = db_items(
        "SELECT page.page_id, page.page_name, page.page_title,
                page.page_style, style.style_name AS current_style_name
         FROM page
         LEFT JOIN style ON page.page_style = style.style_id
         WHERE page.layout_type = 'system'
           AND page.page_style <> '$style_id'
           AND (style.style_layout = 'visual_designer'$own_tree)" . pg_designer_not_binned_sql() . "
         ORDER BY page.page_name ASC");
    if (!is_array($rows)) return array();
    $out = array();
    foreach ($rows as $r) {
        $out[] = array(
            'page_id'            => (int)$r['page_id'],
            'page_name'          => (string)$r['page_name'],
            'page_title'         => (string)$r['page_title'],
            'current_style_id'   => (int)$r['page_style'],
            'current_style_name' => (string)$r['current_style_name'],
        );
    }
    return $out;
}

/**
 * Validate and persist ONE page of a visual design.
 *
 * Everything that used to be inline in edit_system_style.php's POST branch —
 * the page-name uniqueness check, tree validation and cleaning, the
 * noindex/sitemap coupling, the role gate on "set as home", the comments
 * flags — now lives here so the editor can save several pages in one request
 * and add_system_style.php / the API can share the exact same rules.
 *
 * $page keys (all optional except page_name for a new page):
 *   page_id (0 = create), page_name, tree_json, page_folder, page_title,
 *   page_meta_description, page_search, page_search_keywords, page_sitemap,
 *   page_home, page_noindex, page_nofollow, pg_comments, pg_comments_label,
 *   pg_comments_allow_new, pg_comments_rating
 *
 * Returns array('ok' => bool, 'page_id' => int, 'errors' => [...], 'warnings' => [...]).
 * On error nothing is written for this page.
 *
 * $dry_run = true runs the validation and returns without touching the
 * database. The multi-page save calls it that way for every page first, so
 * a refused page never leaves its siblings half-written (MyISAM — no
 * transactions to fall back on).
 */
function pg_designer_save_page($style_id, $page, $user, $dry_run = false)
{
    $style_id = (int)$style_id;
    $page_id  = isset($page['page_id']) ? (int)$page['page_id'] : 0;
    $errors   = array();
    $warnings = array();

    require_once(PG_FUNCTIONS_DIR . '/includes/designer_access.php');

    // A content-level operator edits what the page SAYS, not where it lives.
    // Its name is its address, its folder is its permission and its home flag
    // is the site's front door; all three are restored from the stored row
    // before anything else reads them — including the duplicate-name check,
    // which would otherwise refuse the save over a name the operator never
    // chose. Title, description and keywords are content and stay editable.
    $restricted = !pg_designer_is_full($user);
    if ($restricted && $page_id > 0) {
        $stored_row = db_item("SELECT page_name, page_folder, page_home FROM page WHERE page_id = '$page_id' LIMIT 1");
        if (is_array($stored_row)) {
            $page['page_name']   = (string)$stored_row['page_name'];
            $page['page_folder'] = (int)$stored_row['page_folder'];
            $page['page_home']   = ((string)$stored_row['page_home'] === 'yes') ? 1 : 0;
        }
    }

    $name = trim((string)(isset($page['page_name']) ? $page['page_name'] : ''));
    if ($name === '') {
        $errors[] = lang('Page name is required.');
    } else {
        $dup = (int)db_value(
            "SELECT page_id FROM page
             WHERE page_name = '" . e($name) . "'"
             . ($page_id > 0 ? " AND page_id <> '$page_id'" : '') . "
             LIMIT 1");
        if ($dup > 0) {
            $errors[] = lang(array('string' => 'The page name "{var:1}" is already in use.', 'vars' => $name));
        }
    }

    // Tree — a brand-new tab that was never touched arrives with no tree.
    // Give it the smallest valid one rather than refusing; the page exists
    // the moment the operator names it, empty canvas and all.
    $tree_raw = isset($page['tree_json']) ? (string)$page['tree_json'] : '';
    if ($tree_raw === '' && $page_id === 0) {
        $tree_raw = '{"type":"root","props":{},"children":[]}';
    }
    $vt = _validate_and_clean_tree_json($tree_raw);
    if (!$vt['ok']) {
        $errors[] = ($name !== '' ? $name . ': ' : '') . $vt['error'];
    }
    foreach ((array)$vt['warnings'] as $w) $warnings[] = $w;

    if (!empty($errors)) {
        return array('ok' => false, 'page_id' => $page_id, 'errors' => $errors, 'warnings' => $warnings);
    }
    if ($dry_run) {
        return array('ok' => true, 'page_id' => $page_id, 'errors' => array(), 'warnings' => $warnings);
    }

    $tree_json = !empty($vt['cleaned_json']) ? $vt['cleaned_json'] : $tree_raw;
    $tree = json_decode($tree_json, true);

    // ── The permission gate ────────────────────────────────────────────
    //
    // A content-level operator's tree is NOT written. The stored tree is
    // walked and only the changes their level allows are taken from the
    // submission (text anywhere, whole subtrees inside a marked editable
    // area). Everything else is discarded.
    //
    // This is the gate, not the greyed-out nodes in the editor: a POST does
    // not have to come from that screen, and refusing individual edits after
    // the fact would mean enumerating every way a tree can differ. Copying
    // only what is allowed needs no such list — a change that is not copied
    // has not happened.
    if (is_array($tree) && $restricted) {
        if ($page_id <= 0) {
            // No stored page to merge into, and a content-level operator has
            // no business creating one.
            return array('ok' => false, 'page_id' => 0,
                'errors' => array(lang('You do not have access to add pages.')), 'warnings' => $warnings);
        }
        if (pg_designer_page_access($page_id, $user) !== 'edit') {
            return array('ok' => false, 'page_id' => $page_id,
                'errors' => array(lang('You do not have access to edit this page.')), 'warnings' => $warnings);
        }
        $stored_json = pg_page_tree_json($page_id);
        $stored_tree = ($stored_json !== '') ? json_decode($stored_json, true) : null;
        if (is_array($stored_tree)) {
            $tree = pg_designer_merge_restricted_tree($stored_tree, $tree);
        } else {
            // No stored tree to merge into (a page saved before the per-page
            // tree existed). The submission is written, minus the nodes this
            // level may never create: a custom_php node is eval()ed on every
            // public render, so accepting one here would be code execution.
            // The same marker typed into an ordinary text prop is defused,
            // since the renderers emit that text into the page raw.
            if (_pg_dm_locked_kind($tree) !== '') {
                $tree = array('type' => 'root', 'props' => array(), 'children' => array());
            }
            pg_designer_drop_locked_nodes($tree);
            _pg_dm_neutralise_markers($tree);
        }
    }

    // After the merge, so a content-level operator's note is stamped too, and
    // before the encode. The multi-page editor writes here, not through
    // save_system_style(), so a note flushed with an ordinary page save had
    // no author on it at all until this call existed.
    pg_stamp_note_authors($tree, $page_id, $user);

    if (is_array($tree)) {
        _sanitize_dynamic_placeholders($tree);
        $tree_json = pg_designer_tree_encode($tree);
    }

    // Body classes come from the design, not the page.
    $abc = (string)db_value("SELECT additional_body_classes FROM style WHERE style_id = '$style_id' LIMIT 1");
    $tree_code = is_array($tree) ? generate_style_code_from_tree($tree, $name, $abc) : '';

    $flag = function ($k, $default = 0) use ($page) {
        if (!array_key_exists($k, $page)) return $default;
        $v = $page[$k];
        return ($v === 1 || $v === '1' || $v === true) ? 1 : 0;
    };

    $search   = $flag('page_search', 1);
    $sitemap  = $flag('page_sitemap', 1);
    $noindex  = 0;
    $nofollow = 0;
    if (pg_page_noindex_ready() && $flag('page_noindex')) {
        $noindex  = 1;
        $nofollow = $flag('page_nofollow');
        // A page closed to search engines has no business in the site map.
        // The switch is disabled on screen while noindex is on, but a POST
        // does not have to come from that screen.
        $sitemap = 0;
    }
    // Only staff may promote a page to home; other roles keep the stored
    // flag (UPDATE) or get '' (INSERT).
    $can_set_home = ((int)$user['role'] < 3);
    $home_str     = ($can_set_home && $flag('page_home')) ? 'yes' : '';


    $folder      = (int)(isset($page['page_folder']) ? $page['page_folder'] : 0);
    // Folder 0 is no folder: the page would be invisible to the file
    // manager and could never be sent to (or restored from) the recycle bin.
    if ($folder <= 0 || !db_value("SELECT COUNT(*) FROM folder WHERE folder_id = '$folder'")) {
        $folder = (int)db_value("SELECT folder_id FROM folder WHERE folder_parent = '0' ORDER BY folder_id LIMIT 1");
    }
    $title       = (string)(isset($page['page_title']) ? $page['page_title'] : '');
    $meta_desc   = (string)(isset($page['page_meta_description']) ? $page['page_meta_description'] : '');
    $keywords    = $search ? (string)(isset($page['page_search_keywords']) ? $page['page_search_keywords'] : '') : '';
    $c_on        = $flag('pg_comments');
    $c_allow     = $flag('pg_comments_allow_new', 1);
    $c_rating    = $flag('pg_comments_rating');
    $c_label     = trim((string)(isset($page['pg_comments_label']) ? $page['pg_comments_label'] : ''));

    $multi_ready = pg_multi_page_design_ready();
    $sql_tree_set = $multi_ready
        ? "page_tree_json = '" . e($tree_json) . "', page_tree_code = '" . e($tree_code) . "',"
        : '';
    $sql_noindex_set = pg_page_noindex_ready()
        ? "noindex = '$noindex', nofollow = '$nofollow',"
        : '';

    if ($page_id > 0) {
        $prev = db_item("SELECT page_search, page_search_keywords FROM page WHERE page_id = '$page_id' LIMIT 1");
        $home_set = $can_set_home ? "page_home = '" . e($home_str) . "'," : '';
        db("UPDATE page SET
                page_name                   = '" . e($name) . "',
                page_style                  = '$style_id',
                page_folder                 = '$folder',
                page_title                  = '" . e($title) . "',
                page_meta_description       = '" . e($meta_desc) . "',
                page_search                 = '$search',
                page_search_keywords        = '" . e($keywords) . "',
                sitemap                     = '$sitemap',
                $sql_noindex_set
                $home_set
                $sql_tree_set
                comments                    = '$c_on',
                comments_label              = '" . e($c_label) . "',
                comments_allow_new_comments = '$c_allow',
                comments_rating             = '$c_rating',
                page_timestamp              = UNIX_TIMESTAMP(),
                page_user                   = '" . (int)$user['id'] . "'
            WHERE page_id = '$page_id'");
        update_tag_cloud_keywords_for_page(
            $page_id, $search, $keywords,
            is_array($prev) ? $prev['page_search'] : 0,
            is_array($prev) ? $prev['page_search_keywords'] : '');
        log_activity(lang(array('string' => 'page ({var:1}) was modified', 'vars' => array($name))), $_SESSION['sessionusername']);
    } else {
        $sql_noindex_cols = pg_page_noindex_ready() ? 'noindex, nofollow,' : '';
        $sql_noindex_vals = pg_page_noindex_ready() ? "'$noindex', '$nofollow'," : '';
        $sql_tree_cols    = $multi_ready ? 'page_tree_json, page_tree_code,' : '';
        $sql_tree_vals    = $multi_ready ? "'" . e($tree_json) . "', '" . e($tree_code) . "'," : '';
        db("INSERT INTO page (
                page_name, page_folder, page_type, layout_type, page_home, page_search,
                page_search_keywords, page_timestamp, page_user, page_style, page_title,
                page_meta_description, comments_disallow_new_comment_message, sitemap,
                $sql_noindex_cols
                $sql_tree_cols
                comments, comments_label, comments_allow_new_comments, comments_rating)
            VALUES (
                '" . e($name) . "', '$folder', 'standard', 'system', '" . e($home_str) . "', '$search',
                '" . e($keywords) . "', UNIX_TIMESTAMP(), '" . (int)$user['id'] . "', '$style_id', '" . e($title) . "',
                '" . e($meta_desc) . "', '', '$sitemap',
                $sql_noindex_vals
                $sql_tree_vals
                '$c_on', '" . e($c_label) . "', '$c_allow', '$c_rating')");
        $page_id = (int)mysqli_insert_id(db::$con);
        update_tag_cloud_keywords_for_page($page_id, $search, $keywords, 0, '');
        log_activity(lang(array('string' => 'page ({var:1}) was created', 'vars' => array($name))), $_SESSION['sessionusername']);
    }

    // Un-migrated database: the only place the tree can live is the style.
    // Keep the old single-page behaviour so nothing is lost.
    if (!$multi_ready) {
        db("UPDATE style SET style_tree_json = '" . e($tree_json) . "', style_code = '" . e($tree_code) . "'
            WHERE style_id = '$style_id'");
    }

    return array('ok' => true, 'page_id' => $page_id, 'errors' => array(), 'warnings' => $warnings);
}

/**
 * Write the inline CSS / JS / JSON assets of a design to disk and keep the
 * `files` table in step. Extracted from edit_system_style.php so the
 * multi-page save can call it once per design instead of once per page.
 *
 * Errors are deliberately swallowed (@mysqli_query, not db()): this runs
 * inside an AJAX save whose JSON reply must not be polluted by an HTML error
 * page from output_error().
 */
function pg_designer_sync_asset_files($custom_css_json, $custom_js_json, $user_id)
{
    if (!defined('FILE_DIRECTORY_PATH') || !db::$con) return;
    foreach (array($custom_css_json, $custom_js_json) as $json_field) {
        $assets = @json_decode((string)$json_field, true);
        if (!is_array($assets)) continue;
        foreach ($assets as $asset) {
            if (empty($asset['type']) || empty($asset['name']) || !isset($asset['content'])) continue;
            if (!in_array($asset['type'], array('css', 'js', 'json'))) continue;
            $ext = strtolower(pathinfo($asset['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, array('css', 'js', 'json'))) continue;
            @file_put_contents(FILE_DIRECTORY_PATH . '/' . $asset['name'], $asset['content']);
            $size = (int)strlen($asset['content']);
            $nm   = mysqli_real_escape_string(db::$con, $asset['name']);
            $ex   = mysqli_real_escape_string(db::$con, $ext);
            $uid  = (int)$user_id;
            $chk  = @mysqli_query(db::$con, "SELECT name FROM files WHERE name = '$nm' LIMIT 1");
            if ($chk && mysqli_num_rows($chk) === 0) {
                @mysqli_query(db::$con,
                    "INSERT INTO files (name, folder, description, type, size, user, timestamp)
                     VALUES ('$nm', '0', '', '$ex', '$size', '$uid', UNIX_TIMESTAMP())");
            } else {
                @mysqli_query(db::$con,
                    "UPDATE files SET size = '$size', timestamp = UNIX_TIMESTAMP() WHERE name = '$nm'");
            }
        }
    }
}

// Recursive function to render a single tree node and its children as HTML.
// $depth tracks shared_ref nesting to prevent infinite recursion (legacy — now unused since
// shared_ref emits a marker instead of recursing; kept for backward-compat signature).
//
// ARCHITECTURE (placeholder-only):
// shared_ref nodes are NOT inline-expanded here. Instead, we emit a lightweight HTML comment
// marker (`<!--pg-shared-ref:ID-->`) which `get_page_content.php` replaces with a fresh render
// of `shared_components.tree_json` at page-render time. This means style_code in the DB never
// contains a baked-in copy of shared content, so editing a shared component instantly reflects
// on every page that references it (no style_code rebuild required).
// Build inline style + class strings from sizing/positioning/aspect-ratio props on a node.
// Mirrors getNodeExtraStyle + getNodeExtraClasses on the JS side so the canvas preview and
// the saved frontend HTML stay visually identical.
//
// Returns array('style' => 'width:100px;...', 'class' => 'ratio ratio-16x9 ...').
// Both can be empty strings; caller appends them to its own style/class concatenations.
function _build_node_sizing_attrs($props)
{
    $style_parts = array();
    $cls_parts   = array();
    if (!is_array($props)) return array('style' => '', 'class' => '');

    // Inline-style props — pass user-entered CSS values through h() for attribute safety.
    // (Designer/admin role is required to save trees; values are still escaped defensively.)
    $style_props_map = array(
        'width'      => 'width',
        'height'     => 'height',
        'minWidth'   => 'min-width',
        'maxWidth'   => 'max-width',
        'minHeight'  => 'min-height',
        'maxHeight'  => 'max-height',
        'overflow'   => 'overflow',
        'overflowX'  => 'overflow-x',
        'overflowY'  => 'overflow-y',
        'top'        => 'top',
        'right'      => 'right',
        'bottom'     => 'bottom',
        'left'       => 'left',
    );
    foreach ($style_props_map as $prop_key => $css_key) {
        if (!empty($props[$prop_key])) {
            $style_parts[] = $css_key . ':' . h($props[$prop_key]);
        }
    }
    // z-index is unitless integer — sanitize to digits + 'auto'
    if (!empty($props['zIndex'])) {
        $zi = (string)$props['zIndex'];
        if ($zi === 'auto' || preg_match('/^-?\d+$/', $zi)) {
            $style_parts[] = 'z-index:' . $zi;
        }
    }
    // Aspect ratio custom value (CSS variable used by Bootstrap .ratio rule)
    if (!empty($props['aspectRatioEnabled']) && (isset($props['aspectRatio']) ? $props['aspectRatio'] : '') === 'custom' && !empty($props['aspectRatioCustom'])) {
        $style_parts[] = '--bs-aspect-ratio:' . h($props['aspectRatioCustom']);
    }

    // Class-based utilities + Bootstrap .ratio
    if (!empty($props['aspectRatioEnabled'])) {
        $cls_parts[] = 'ratio';
        $ar = isset($props['aspectRatio']) ? $props['aspectRatio'] : '';
        if ($ar !== '' && $ar !== 'custom' && preg_match('/^\d+x\d+$/', $ar)) {
            $cls_parts[] = 'ratio-' . $ar;
        }
    }
    if (!empty($props['srOnly']))           $cls_parts[] = 'visually-hidden';
    if (!empty($props['focusRing']))        $cls_parts[] = 'focus-ring';
    if (!empty($props['placeholderMode'])) {
        $cls_parts[] = 'placeholder';
        $anim = isset($props['placeholderAnim']) ? $props['placeholderAnim'] : '';
        if     ($anim === 'glow') $cls_parts[] = 'placeholder-glow';
        elseif ($anim === 'wave') $cls_parts[] = 'placeholder-wave';
    }

    return array(
        'style' => implode(';', $style_parts),
        'class' => implode(' ', $cls_parts),
    );
}

// Returns true when `$children` (the node's direct children array) contains
// an anchor element with `stretched-link` in its cssClass. Used by
// _render_tree_node to auto-promote a node to position-relative so Bootstrap's
// stretched-link mechanic works without manual designer fixes.
function _has_stretched_link_child($children)
{
    if (!is_array($children)) return false;
    foreach ($children as $c) {
        if (!is_array($c) || empty($c['type'])) continue;
        $cls = (isset($c['props']) && isset($c['props']['cssClass'])) ? (string)$c['props']['cssClass'] : '';
        if ($cls === '' || strpos($cls, 'stretched-link') === false) continue;
        // Word-boundary check — guard against e.g. "non-stretched-link" matches.
        $words = preg_split('/\s+/', $cls);
        if (!in_array('stretched-link', $words, true)) continue;
        // Anchor-ness: content/link OR semantic/<a>
        $is_anchor =
            ($c['type'] === 'content' && isset($c['props']['contentType']) && $c['props']['contentType'] === 'link')
            || ($c['type'] === 'semantic' && isset($c['props']['tag']) && strtolower((string)$c['props']['tag']) === 'a');
        if ($is_anchor) return true;
    }
    return false;
}

// Returns true when the node's cssClass already includes any Bootstrap
// position utility (`position-static|relative|absolute|fixed|sticky`). When
// true we skip the auto-add so an explicit user choice is never overwritten.
function _props_has_position_class($props)
{
    if (!is_array($props) || empty($props['cssClass'])) return false;
    $words = preg_split('/\s+/', (string)$props['cssClass']);
    static $position_classes = array(
        'position-static', 'position-relative', 'position-absolute',
        'position-fixed',  'position-sticky'
    );
    foreach ($position_classes as $pc) {
        if (in_array($pc, $words, true)) return true;
    }
    return false;
}

/**
 * Emit the pg_lightbox CSS+JS asset tags exactly once per request.
 * Subsequent calls return ''. Called from the carousel render so the assets
 * are only loaded on pages that actually have a lightbox-enabled carousel.
 *
 * The version query string busts the browser cache on every script update —
 * since the assets live in the software dir (a tracked path in pinegrap/),
 * a file-mtime based suffix would require an extra disk stat per request, so
 * we use a stable string and bump it by hand when the lightbox JS changes.
 */
function _pg_lightbox_assets_once()
{
    static $emitted = false;
    if ($emitted) return '';
    $emitted = true;
    // Toolbar labels for pg_lightbox.js, which falls back to the English key.
    $labels = array(
        'Zoom out'                       => lang('Zoom out'),
        'Zoom in'                        => lang('Zoom in'),
        'Reset'                          => lang('Reset'),
        'Full screen'                    => lang('Full screen'),
        'Close'                          => lang('Close'),
        'Previous'                       => lang('Previous'),
        'Next'                           => lang('Next'),
        'The image could not be loaded.' => lang('The image could not be loaded.'),
    );
    return '<link rel="stylesheet" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/css/pg_lightbox.css?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/css/pg_lightbox.css') . '">
    <script>window.pgLightboxLabels = ' . encode_json($labels) . ';</script>
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_lightbox.js?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_lightbox.js') . '" defer></script>';
}

/**
 * Emit Fancybox v6 (Fancyapps) assets exactly once per request.
 *
 * Fancybox v6 replaces the legacy pg_lightbox custom build for the new
 * carousel content type. Provides:
 *   • Click-to-fullscreen lightbox with keyboard nav, swipe, slideshow
 *   • Built-in panzoom plugin for zoom-on-hover (when enabled)
 *   • Toolbar with download / fullscreen / slideshow buttons
 *
 * Files are bundled LOCALLY under assets/lib/fancybox/ rather than
 * pulled from a CDN — keeps everything self-hosted (no third-party
 * latency, works offline / on intranets, no privacy concerns about
 * leaking visitor IPs to jsDelivr).
 *
 * Plus a small layout stylesheet (pg_carousel_layout.css) for the new
 * thumbs-position layouts (left/right vertical strip with proper flex).
 */
/**
 * Emit the system-widget layout CSS once per request.
 *
 * pg_carousel_layout.css carries:
 *   • Carousel layout primitives (used by content/carousel)
 *   • Unified price block (.pg-price-block strike-through styling)
 *   • Empty-element hiding rules (auto-hide discount tokens when blank)
 *   • Variant chooser visual states
 *
 * Called from EVERY system widget render (catalog_listing, catalog_item_view,
 * shopping_cart, …) — not just carousel. The price block + discount
 * auto-hide rules need to be available wherever a product price renders,
 * so loading this CSS on the carousel only would skip catalog cards on
 * pages without a carousel.
 */
function _pg_widget_layout_css_once()
{
    static $emitted = false;
    if ($emitted) return '';
    $emitted = true;
    $base = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/')
          . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : 'software')
          . '/assets/css/';
    $v = '3';   // bump on layout css changes
    return '<link rel="stylesheet" href="' . h($base) . 'pg_carousel_layout.css?v=' . $v . '">';
}

function _pg_fancybox_assets_once()
{
    // Loads Fancyapps Fancybox v6 (lightbox + zoom + slideshow + keyboard
    // nav + swipe). Files live under assets/lib/fancybox/ — bundled
    // locally so the page works without network access AND privacy/perf
    // is better than CDN hot-linking. Init code is embedded as a single
    // global handler so any element with `data-fancybox` becomes a
    // lightbox trigger automatically; the carousel render below stamps
    // those attributes onto every slide image.
    static $emitted = false;
    if ($emitted) return '';
    $emitted = true;
    $base = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/')
          . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : 'software')
          . '/assets/';
    $fb_v = '6';   // bump on Fancybox update
    return _pg_widget_layout_css_once()
         . '<link rel="stylesheet" href="' . h($base) . 'lib/fancybox/fancybox.css?v=' . $fb_v . '">'
         . '<script src="' . h($base) . 'lib/fancybox/fancybox.umd.js?v=' . $fb_v . '" defer></script>'
         // One global init — calls Fancybox.bind() after the script
         // finishes loading. Idempotent (Fancybox.bind tracks selectors
         // and won't double-register). Catches any [data-fancybox] in
         // the document, so per-widget renders only need to stamp the
         // attribute. Re-bind hook for AJAX-injected content (variant
         // chooser swap) is exposed as window.pgFancyboxRebind().
         . '<script>(function(){'
         . 'function pgInit(){'
         .   'if(!window.Fancybox){setTimeout(pgInit,80);return;}'
         .   'try{window.Fancybox.bind("[data-fancybox]",{Hash:false});}catch(_){}'
         . '}'
         . 'window.pgFancyboxRebind=function(){'
         .   'try{if(window.Fancybox){window.Fancybox.unbind("[data-fancybox]");window.Fancybox.bind("[data-fancybox]",{Hash:false});}}catch(_){}'
         . '};'
         . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",pgInit);}else{pgInit();}'
         . '})();</script>';
}

/**
 * Render the thumbnail strip for a carousel.
 *
 * Fancybox v6 lightbox triggers require the image inside an <a data-fancybox>
 * wrapper. We use the anchor for click-to-fullscreen AND keep the
 * data-bs-slide-to attribute on the SAME element for active-thumbnail sync
 * (so clicking a thumb both opens the lightbox AND advances the carousel).
 *
 * @param array  $slides       List of image URLs (in render order).
 * @param string $crl_id       Carousel root element id (for Bootstrap target).
 * @param int    $thumb_size   Square thumbnail size in pixels.
 * @param bool   $lightbox     When true, thumbs are Fancybox lightbox triggers.
 * @param string $fb_gallery   Fancybox gallery name (must match main carousel).
 * @param string $pos          'top' | 'bottom' | 'left' | 'right' | 'none'
 * @return string              HTML for the thumbnail strip.
 */
function _pg_render_carousel_thumbs($slides, $crl_id, $thumb_size, $lightbox, $fb_gallery, $pos)
{
    if (!is_array($slides) || count($slides) <= 1 || $pos === 'none') return '';
    // Vertical orientation when thumbs are on the side; otherwise horizontal.
    // Width/height of the strip itself is shaped by pg_carousel_layout.css
    // based on the .pg-carousel-thumbs-{pos} parent class.
    $is_vertical = ($pos === 'left' || $pos === 'right');
    $align_cls   = $is_vertical ? 'flex-column' : 'flex-row flex-wrap';
    $out = '<div class="pg-carousel-thumbs d-flex gap-2 ' . $align_cls . '"'
         . ' data-pg-carousel-target="' . h($crl_id) . '">';
    foreach ($slides as $_si => $_slide) {
        $_thumb_style = 'width:' . $thumb_size . 'px;height:' . $thumb_size . 'px;object-fit:cover;cursor:pointer';
        $_active_cls  = ($_si === 0 ? ' active' : '');
        if ($lightbox) {
            // Thumbs use `data-fancybox-trigger` (NOT `data-fancybox`).
            // That way they open the EXISTING gallery (defined by the
            // main slide anchors) rather than registering as additional
            // gallery items. Without this distinction Fancybox would see
            // 10 anchors for a 5-slide gallery and show "1 / 10" with
            // duplicate entries. `data-fancybox-index="N"` tells
            // Fancybox which slide to open. `data-bs-slide-to` ALSO
            // lives on the anchor so the click syncs the inline
            // Bootstrap carousel position before the lightbox opens.
            $out .= '<a href="' . h($_slide) . '"'
                  . ' data-fancybox-trigger="' . h($fb_gallery) . '"'
                  . ' data-fancybox-index="' . (int)$_si . '"'
                  . ' data-bs-target="#' . h($crl_id) . '" data-bs-slide-to="' . (int)$_si . '"'
                  . ' class="pg-carousel-thumb-link">'
                  . '<img src="' . h($_slide) . '"'
                  . ' class="pg-carousel-thumb' . $_active_cls . '"'
                  . ' style="' . $_thumb_style . '"'
                  . ' alt="" loading="lazy">'
                  . '</a>';
        } else {
            // No lightbox — bare <img> with Bootstrap slide-sync only.
            $out .= '<img src="' . h($_slide) . '"'
                  . ' data-bs-target="#' . h($crl_id) . '" data-bs-slide-to="' . (int)$_si . '"'
                  . ' class="pg-carousel-thumb' . $_active_cls . '"'
                  . ' style="' . $_thumb_style . '"'
                  . ' alt="" loading="lazy">';
        }
    }
    $out .= '</div>';
    return $out;
}

/**
 * Per-carousel-instance init script — wires:
 *   • Active thumbnail border sync on Bootstrap carousel slide events
 *   • Optional zoom-on-hover via background-image positioning (lightweight,
 *     no Fancybox panzoom needed for the basic effect)
 *
 * The script is emitted INLINE at the end of the carousel HTML so it
 * lives WITH the carousel (DOM lifecycle). When the designer removes the
 * carousel from the tree, this init code goes with it — no orphaned event
 * listeners, no leaked window references.
 */
function _pg_render_carousel_init_script($crl_id, $hover_zoom)
{
    $cid_js = json_encode($crl_id);
    $hz_js  = $hover_zoom ? 'true' : 'false';
    return '<script>(function(){'
        . 'var cid=' . $cid_js . ';'
        . 'var hz=' . $hz_js . ';'
        . 'function init(){'
        .   'var car=document.getElementById(cid);if(!car)return;'
        .   'var thumbs=document.querySelectorAll(\'[data-pg-carousel-target="\'+cid+\'"] .pg-carousel-thumb\');'
        // Active thumb sync — listen to Bootstrap's slide event.
        .   'car.addEventListener("slid.bs.carousel",function(ev){'
        .     'var idx=ev.to;'
        .     'thumbs.forEach(function(t,i){t.classList.toggle("active",i===idx);});'
        .   '});'
        // Manual thumb click → also sync (Bootstrap data-bs-slide-to fires
        // the slide event, so this is redundant — but keep for safety).
        .   'thumbs.forEach(function(t,i){'
        .     't.addEventListener("click",function(){'
        .       'thumbs.forEach(function(o){o.classList.remove("active");});'
        .       't.classList.add("active");'
        .     '});'
        .   '});'
        // Zoom-on-hover effect — uses background-image + background-position
        // tracking. Cheaper than Fancybox panzoom for the basic magnifier.
        .   'if(hz){'
        .     'var imgs=car.querySelectorAll(".pg-zoom-on-hover");'
        .     'imgs.forEach(function(el){'
        .       'var src=el.getAttribute("data-pg-zoom-src");if(!src)return;'
        // The actual zoom layer is an absolute-positioned div appended to the
        // carousel-item — keeps the original <img> intact for layout/SEO.
        .       'var item=el.closest(".carousel-item");if(!item)return;'
        .       'item.style.position=item.style.position||"relative";'
        .       'item.style.overflow="hidden";'
        .       'el.addEventListener("mousemove",function(e){'
        .         'var r=el.getBoundingClientRect();'
        .         'var x=((e.clientX-r.left)/r.width)*100;'
        .         'var y=((e.clientY-r.top)/r.height)*100;'
        .         'var z=item.querySelector(".pg-zoom-layer");'
        .         'if(!z){z=document.createElement("div");z.className="pg-zoom-layer";'
        .         'z.style.cssText="position:absolute;inset:0;background-repeat:no-repeat;background-size:200%;pointer-events:none;opacity:0;transition:opacity .2s";'
        .         'z.style.backgroundImage=\'url("\'+src+\'")\';item.appendChild(z);}'
        .         'z.style.backgroundPosition=x+"% "+y+"%";'
        .         'z.style.opacity="1";'
        .       '});'
        .       'el.addEventListener("mouseleave",function(){'
        .         'var z=item.querySelector(".pg-zoom-layer");if(z)z.style.opacity="0";'
        .       '});'
        .     '});'
        .   '}'
        . '}'
        . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",init);}else{init();}'
        . '})();</script>';
}

function _render_tree_node($node, $indent = 0, $depth = 0)
{
    if (!$node || !isset($node['type'])) return '';

    $pad = str_repeat('    ', $indent);
    $html = '';
    $type = $node['type'];
    $props = isset($node['props']) ? $node['props'] : array();
    $children = isset($node['children']) ? $node['children'] : array();

    // Bootstrap's `.stretched-link` only stretches to the nearest *positioned*
    // ancestor — without `position: relative` on a parent it expands to <body>
    // and silently breaks. Detect a stretched-link `<a>` in the current node's
    // direct children and auto-promote this node to `position-relative` (only
    // when it doesn't already carry an explicit position-* class). Designer
    // can still override by adding their own `position-static` etc. manually.
    if (_has_stretched_link_child($children) && !_props_has_position_class($props)) {
        if (!isset($props['cssClass']) || $props['cssClass'] === '') {
            $props['cssClass'] = 'position-relative';
        } else {
            $props['cssClass'] = trim($props['cssClass'] . ' position-relative');
        }
    }

    $_sz = _build_node_sizing_attrs($props);  // {style, class} — applied per-case below

    switch ($type) {
        case 'root':
            // Tree-root sentinel — renders ONLY its children (no wrapper element).
            // shared_components.tree_json is wrapped in a root node by the JS designer
            // (createNode('root', {}, [...])) — without this case, calling
            // _render_tree_node($shared_tree) on a fresh shared component returned ''
            // and the frontend showed an empty div. Same applies to system widgets.
            foreach ($children as $child) { $html .= _render_tree_node($child, $indent, $depth); }
            break;

        case 'loop_area':
            // Inside a static (non-loop) render path, loop_area is invisible — it
            // simply lets its children flow through as if the loop_area didn't exist.
            // The actual loop semantics (per-row repetition + token replacement) live
            // in _render_system_widget_form_list which scans the tree for a loop_area
            // and treats it specially. This case is a fallback for any other code path
            // that walks the tree (e.g. preview, debug).
            foreach ($children as $child) { $html .= _render_tree_node($child, $indent, $depth); }
            break;

        case 'recipient_loop_area':
            // Express-order multi-recipient template wrapper. Transparent passthrough
            // when walked outside the EO bindings path; the EO renderer extracts the
            // children via _eo_split_recipient_loop_area() before getting here so this
            // case is mainly a safety fallback.
            foreach ($children as $child) { $html .= _render_tree_node($child, $indent, $depth); }
            break;

        case 'container':
            $class = (!empty($props['fluid'])) ? 'container-fluid' : 'container';
            if (!empty($props['cssClass'])) $class .= ' ' . h($props['cssClass']);
            if ($_sz['class'] !== '') $class .= ' ' . $_sz['class'];
            // Merge sizing inline style with any user-supplied style attr.
            $_styleParts = array();
            if ($_sz['style'] !== '') $_styleParts[] = $_sz['style'];
            $_us = _user_attr_value($props, 'style');
            if ($_us !== '') $_styleParts[] = $_us;
            $_szAttr = $_styleParts ? ' style="' . h(implode(';', $_styleParts)) . '"' : '';
            $extra_attrs = _render_extra_attrs($props);
            $html .= $pad . '<div class="' . $class . '"' . $_szAttr . $extra_attrs . '>' . "\n";
            foreach ($children as $child) { $html .= _render_tree_node($child, $indent + 1, $depth); }
            $html .= $pad . '</div>' . "\n";
            break;

        case 'area':
            $area_id = !empty($props['areaName']) ? $props['areaName'] : 'section';
            $area_cls_combined = !empty($props['cssClass']) ? $props['cssClass'] : '';
            if ($_sz['class'] !== '') $area_cls_combined = trim($area_cls_combined . ' ' . $_sz['class']);
            $area_class = $area_cls_combined !== '' ? ' class="' . h($area_cls_combined) . '"' : '';
            $_styleParts = array();
            if ($_sz['style'] !== '') $_styleParts[] = $_sz['style'];
            $_us = _user_attr_value($props, 'style');
            if ($_us !== '') $_styleParts[] = $_us;
            $_szAttr = $_styleParts ? ' style="' . h(implode(';', $_styleParts)) . '"' : '';
            // Skip id — built-in already from areaName.
            $extra_attrs = _render_extra_attrs($props, array('id'));
            $html .= $pad . '<div id="' . h($area_id) . '"' . $area_class . $_szAttr . $extra_attrs . '>' . "\n";
            foreach ($children as $child) { $html .= _render_tree_node($child, $indent + 1, $depth); }
            $html .= $pad . '</div>' . "\n";
            break;

        case 'row':
            $rc = 'row';
            if (!empty($props['gutter'])) $rc .= ' g-' . h($props['gutter']);
            if (!empty($props['justify'])) $rc .= ' justify-content-' . h($props['justify']);
            if (!empty($props['align'])) $rc .= ' align-items-' . h($props['align']);
            if (!empty($props['cssClass'])) $rc .= ' ' . h($props['cssClass']);
            if ($_sz['class'] !== '') $rc .= ' ' . $_sz['class'];
            $_styleParts = array();
            if ($_sz['style'] !== '') $_styleParts[] = $_sz['style'];
            $_us = _user_attr_value($props, 'style');
            if ($_us !== '') $_styleParts[] = $_us;
            $_szAttr = $_styleParts ? ' style="' . h(implode(';', $_styleParts)) . '"' : '';
            $extra_attrs = _render_extra_attrs($props);
            $html .= $pad . '<div class="' . $rc . '"' . $_szAttr . $extra_attrs . '>' . "\n";
            foreach ($children as $child) { $html .= _render_tree_node($child, $indent + 1, $depth); }
            $html .= $pad . '</div>' . "\n";
            break;

        case 'col':
            $cc = _build_col_classes($props);
            if (!empty($props['cssClass'])) $cc .= ' ' . h($props['cssClass']);
            if ($_sz['class'] !== '') $cc .= ' ' . $_sz['class'];
            $_styleParts = array();
            if ($_sz['style'] !== '') $_styleParts[] = $_sz['style'];
            $_us = _user_attr_value($props, 'style');
            if ($_us !== '') $_styleParts[] = $_us;
            $_szAttr = $_styleParts ? ' style="' . h(implode(';', $_styleParts)) . '"' : '';
            $extra_attrs = _render_extra_attrs($props);
            $html .= $pad . '<div class="' . $cc . '"' . $_szAttr . $extra_attrs . '>' . "\n";
            foreach ($children as $child) { $html .= _render_tree_node($child, $indent + 1, $depth); }
            $html .= $pad . '</div>' . "\n";
            break;

        case 'semantic':
            // Apply data bindings — semantic <a>/<img>/<button> inside a system widget can
            // have text, href, src bound to feed fields (^^baslik^^, ^^href^^, ^^gorsel^^).
            $props = _apply_bindings($props);
            $tag = !empty($props['tag']) ? $props['tag'] : 'div';
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $tag)) $tag = 'div';
            $tag_lc = strtolower($tag);
            $attrs = '';
            if (!empty($props['id'])) $attrs .= ' id="' . h($props['id']) . '"';
            // Combine cssClass + sizing classes into a single class attribute
            $sem_cls_parts = array();
            if (!empty($props['cssClass'])) $sem_cls_parts[] = $props['cssClass'];
            if ($_sz['class'] !== '')       $sem_cls_parts[] = $_sz['class'];
            $sem_cls_combined = trim(implode(' ', $sem_cls_parts));
            if ($sem_cls_combined !== '')   $attrs .= ' class="' . h($sem_cls_combined) . '"';
            // Tag-specific attributes — emitted directly so bindings on href/src work.
            // Track which builtin attrs we emit so the _attrs loop below can skip
            // duplicates. Otherwise an _attrs entry named 'href' would render a
            // SECOND href="..." next to the bound one, and a stale literal value
            // (e.g. a paginated URL accidentally pasted into the Attrs panel)
            // could shadow ^^__detail_url^^ in browsers that take the first
            // attribute. We always force the binding/builtin to win.
            $builtin_attrs_emitted = array();
            if ($tag_lc === 'a') {
                if (!empty($props['href']))   { $attrs .= ' href="' . h($props['href']) . '"';   $builtin_attrs_emitted['href']   = true; }
                if (!empty($props['target'])) { $attrs .= ' target="' . h($props['target']) . '"'; $builtin_attrs_emitted['target'] = true; }
                if (!empty($props['rel']))    { $attrs .= ' rel="' . h($props['rel']) . '"';    $builtin_attrs_emitted['rel']    = true; }
            } elseif ($tag_lc === 'img') {
                if (!empty($props['src'])) { $attrs .= ' src="' . h($props['src']) . '"'; $builtin_attrs_emitted['src'] = true; }
                if (!empty($props['alt'])) { $attrs .= ' alt="' . h($props['alt']) . '"'; $builtin_attrs_emitted['alt'] = true; }
            }
            // Sizing inline style — appended BEFORE _attrs so user-defined style attr can override
            $sem_style_parts = array();
            if ($_sz['style'] !== '') $sem_style_parts[] = $_sz['style'];
            if (!empty($props['_attrs']) && is_array($props['_attrs'])) {
                foreach ($props['_attrs'] as $a) {
                    // Skip `id` — always sourced from props.id to avoid duplicate attributes
                    if (!empty($a['name']) && $a['name'] !== 'id' && $a['name'] !== 'class') {
                        if ($a['name'] === 'style') {
                            // Merge user-defined style with sizing style; later wins on duplicate keys
                            if (!empty($a['value'])) $sem_style_parts[] = $a['value'];
                        } elseif (isset($builtin_attrs_emitted[$a['name']])) {
                            // A bound/builtin value already covers this attribute — skip the
                            // _attrs entry to avoid emitting a duplicate (which would shadow
                            // the binding token in some browsers).
                            continue;
                        } else {
                            $attrs .= ' ' . h($a['name']) . '="' . h(isset($a['value']) ? $a['value'] : '') . '"';
                        }
                    }
                }
            }
            if (!empty($sem_style_parts)) $attrs .= ' style="' . h(implode(';', $sem_style_parts)) . '"';
            $void_tags = array('area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr');
            if (in_array($tag_lc, $void_tags)) {
                $html .= $pad . '<' . $tag . $attrs . '>' . "\n";
            } else {
                $inner_text = isset($props['text']) && $props['text'] !== '' ? h($props['text']) : '';
                $html .= $pad . '<' . $tag . $attrs . '>' . $inner_text . "\n";
                foreach ($children as $child) { $html .= _render_tree_node($child, $indent + 1, $depth); }
                $html .= $pad . '</' . $tag . '>' . "\n";
            }
            break;

        case 'region':
            $html .= $pad . _render_region_tag($props) . "\n";
            break;

        case 'component':
            $html .= _render_component_html($props, $pad);
            break;

        case 'content':
            $html .= _render_content_html($props, $pad);
            break;

        case 'shared_ref':
            // INVARIANT: never inline-expand shared content here. Emit a marker that
            // `get_page_content.php` (or any page-render pipeline) replaces with a fresh
            // render of `shared_components.tree_json` at request time. This is what guarantees
            // that a shared component edit propagates to every referencing style without
            // rebuilding their style_code caches.
            //
            // Marker format: `<!--pg-shared-ref:ID-->` (HTML comment — invisible in browsers,
            // regex-matchable on the server). See `_expand_shared_refs()` for the expander.
            $sc_id = isset($props['sharedId']) ? (int)$props['sharedId'] : 0;
            if ($sc_id > 0) {
                $html .= $pad . '<!--pg-shared-ref:' . $sc_id . '-->' . "\n";
            }
            break;
    }

    return $html;
}

// Build Bootstrap column classes from column props
function _build_col_classes($props)
{
    $classes = array();
    $xs = isset($props['xs']) ? $props['xs'] : '';
    $sm = isset($props['sm']) ? $props['sm'] : '';
    $md = isset($props['md']) ? $props['md'] : '';
    $lg = isset($props['lg']) ? $props['lg'] : '';
    $xl = isset($props['xl']) ? $props['xl'] : '';
    $xxl = isset($props['xxl']) ? $props['xxl'] : '';

    if ($xs && $xs !== '' && $xs !== 'none') {
        // 'col'  → equal-width sentinel (Bootstrap class is bare `col`)
        // 'auto' → content-fit width (`col-auto`)
        // anything else → numeric span (`col-N`)
        // Without the explicit 'col' branch, the else fell into
        // `col-` . $xs and produced the malformed class `col-col` on the
        // frontend (iframe was unaffected because buildColClasses in JS
        // handles 'col' correctly — keep PHP and JS in sync).
        if ($xs === 'auto')     $classes[] = 'col-auto';
        else if ($xs === 'col') $classes[] = 'col';
        else                    $classes[] = 'col-' . $xs;
    } else if (!$sm && !$md && !$lg && !$xl && !$xxl) {
        $classes[] = 'col-12';
    }
    $bps = array('sm' => $sm, 'md' => $md, 'lg' => $lg, 'xl' => $xl, 'xxl' => $xxl);
    foreach ($bps as $bp => $val) {
        if ($val && $val !== '' && $val !== 'none') {
            $classes[] = ($val === 'auto') ? 'col-' . $bp . '-auto' : 'col-' . $bp . '-' . $val;
        }
    }
    if (!empty($props['offsetXs']) && $props['offsetXs'] !== 'none') $classes[] = 'offset-' . $props['offsetXs'];
    if (!empty($props['offsetMd']) && $props['offsetMd'] !== 'none') $classes[] = 'offset-md-' . $props['offsetMd'];
    if (!empty($props['order']) && $props['order'] !== 'default') $classes[] = 'order-' . $props['order'];
    if (!empty($props['cssClass'])) $classes[] = $props['cssClass'];
    return implode(' ', $classes);
}

// Render a region node as its custom tag (pregion, cregion, system, etc.)
function _render_region_tag($props)
{
    $type = isset($props['regionType']) ? $props['regionType'] : '';
    $name = isset($props['regionName']) ? h($props['regionName']) : '';

    switch ($type) {
        case 'page': return '<pregion></pregion>';
        case 'system': return $name ? '<system>' . $name . '</system>' : '<system></system>';
        case 'common': case 'designer': return '<cregion>' . $name . '</cregion>';
        case 'dynamic': return '<dregion>' . $name . '</dregion>';
        case 'menu': return '<menu>' . $name . '</menu><div style="clear: both"></div>';
        case 'menu_sequence': return '<menu_sequence>' . $name . '</menu_sequence>';
        case 'login': return '<login>' . $name . '</login>';
        case 'ad': return '<ad>' . $name . '</ad>';
        case 'cart': return '<cart></cart>';
        case 'tag_cloud': return '<tcloud>' . $name . '</tcloud>';
        case 'mobile_switch': return '<mobile_switch></mobile_switch>';
        case 'pdf': return '<pdf></pdf>';
        case 'comments_block': return '<!--pg-comments-block-->';
        default: return '';
    }
}

// Render a Bootstrap component node as HTML
// ── Extra-attribute helpers used by every content / component renderer ──
//
// Emit `id` + any user-defined extras stored on `props._attrs[]` as a
// ready-to-splice attribute string (with leading space, no trailing
// space). Designers add data-bs-*, aria-*, role, onclick, custom data-*
// and other one-off attributes via the bottom Attributes panel; without
// this helper those entries lived only in the iframe preview and never
// reached the rendered page (popover `data-bs-toggle` was the trigger).
//
// $skip_names: lowercase names already emitted as built-in attrs by the
//   caller (e.g. 'href' on a link, 'src'/'alt' on an image). Skipped to
//   avoid duplicate attribute emissions which the HTML5 parser collapses
//   to the FIRST occurrence — a stale `_attrs` entry could otherwise
//   shadow a fresher built-in/binding value.
//
// 'class' and 'style' are NEVER emitted here — they need to merge with
// case-specific class/style and so are handled in the caller via
// _user_attr_value(). 'id' is sourced from props.id and rendered only
// when the caller didn't already include it in $skip_names.
function _render_extra_attrs($props, $skip_names = array())
{
    $skip_map = array();
    foreach ($skip_names as $n) $skip_map[strtolower((string)$n)] = true;
    $out = '';
    if (empty($skip_map['id']) && !empty($props['id'])) {
        $out .= ' id="' . h($props['id']) . '"';
    }
    if (!empty($props['_attrs']) && is_array($props['_attrs'])) {
        foreach ($props['_attrs'] as $a) {
            if (!is_array($a) || empty($a['name'])) continue;
            $name  = (string)$a['name'];
            $lname = strtolower($name);
            if ($lname === 'class' || $lname === 'style' || $lname === 'id') continue;
            if (isset($skip_map[$lname])) continue;
            $val = isset($a['value']) ? (string)$a['value'] : '';
            $out .= ' ' . h($name) . '="' . h($val) . '"';
        }
    }
    return $out;
}

// Look up a user-defined attribute value by name from props._attrs[].
// Used by callers that already emit their own `style="..."` or `class="..."`
// to merge the user-supplied value into the existing one. Returns the
// string value, or '' when the name is not present.
function _user_attr_value($props, $name)
{
    if (empty($props['_attrs']) || !is_array($props['_attrs'])) return '';
    $needle = strtolower((string)$name);
    foreach ($props['_attrs'] as $a) {
        if (!is_array($a) || empty($a['name'])) continue;
        if (strtolower((string)$a['name']) === $needle) {
            return isset($a['value']) ? (string)$a['value'] : '';
        }
    }
    return '';
}

// Compose the full inline `style="..."` value for any leaf renderer.
// Combines (in this order, last wins on duplicate keys):
//   • props.fontFamily       — Font picker (Body / heading / etc.).
//   • props.inlineStyle      — Legacy direct-input field.
//   • $extra (caller-built)  — node-specific built-in styles (e.g. icon's
//                              font-size, image's object-position, hero's
//                              background, spacer's height, sizing helpers).
//   • _attrs.style           — user-typed style="..." in the Attrs panel.
// Returns ' style="..."' (leading space, ready to splice) or '' when nothing.
// The iframe canvas applies these same parts via JS — keeping this list in
// sync with assets/js/style_designer.js's getNodeExtraStyle() prevents the
// classic "looks right in editor, wrong on the rendered page" bug.
function _compose_inline_style($props, $extra = '')
{
    $parts = array();
    if (!empty($props['fontFamily'])) {
        // Quote family names that contain spaces; the picker stores the bare
        // family ('IBM Plex Mono'), CSS needs it quoted plus a generic fallback.
        $fam = (string)$props['fontFamily'];
        $needs_quote = (strpos($fam, ' ') !== false) || preg_match('/^[0-9]/', $fam);
        $parts[] = "font-family:" . ($needs_quote ? "'" . str_replace("'", '', $fam) . "'" : $fam) . ",sans-serif";
    }
    if (!empty($props['inlineStyle'])) {
        $parts[] = rtrim((string)$props['inlineStyle'], '; ');
    }
    if ($extra !== '' && $extra !== null) {
        $parts[] = trim($extra, '; ');
    }
    $u = _user_attr_value($props, 'style');
    if ($u !== '') $parts[] = trim($u, '; ');
    if (!$parts) return '';
    return ' style="' . h(implode(';', $parts)) . '"';
}

function _render_component_html($props, $pad)
{
    // Apply data bindings — components inside a system widget can have text/href bound
    $props = _apply_bindings($props);
    $ct = isset($props['componentType']) ? $props['componentType'] : '';
    $html = '';

    switch ($ct) {
        case 'navbar':
            $brand = h(isset($props['brand']) ? $props['brand'] : 'Brand');
            $theme = isset($props['theme']) ? $props['theme'] : 'dark';
            $bg = isset($props['bg']) ? $props['bg'] : 'dark';
            $expand = isset($props['expand']) ? $props['expand'] : 'lg';
            $fixed = !empty($props['fixed']) ? ' ' . h($props['fixed']) : '';
            $nav_uid = 'navbarNav' . substr(md5(uniqid()), 0, 5);
            $u_style = _user_attr_value($props, 'style');
            $extra_attrs = _render_extra_attrs($props);
            $html .= $pad . '<nav class="navbar navbar-expand-' . h($expand) . ' navbar-' . h($theme) . ' bg-' . h($bg) . $fixed . '"'
                . ($u_style !== '' ? ' style="' . h($u_style) . '"' : '')
                . $extra_attrs . '>' . "\n";
            $html .= $pad . '    <div class="container-fluid">' . "\n";
            $html .= $pad . '        <a class="navbar-brand" href="#">' . $brand . '</a>' . "\n";
            $html .= $pad . '        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#' . $nav_uid . '" aria-controls="' . $nav_uid . '" aria-expanded="false"><span class="navbar-toggler-icon"></span></button>' . "\n";
            $html .= $pad . '        <div class="collapse navbar-collapse" id="' . $nav_uid . '"><ul class="navbar-nav ms-auto"><li class="nav-item"><a class="nav-link" href="#">Home</a></li><li class="nav-item"><a class="nav-link" href="#">About</a></li><li class="nav-item"><a class="nav-link" href="#">Contact</a></li></ul></div>' . "\n";
            $html .= $pad . '    </div>' . "\n";
            $html .= $pad . '</nav>' . "\n";
            break;

        case 'hero':
            $title = h(isset($props['title']) ? $props['title'] : 'Welcome');
            $subtitle = h(isset($props['subtitle']) ? $props['subtitle'] : '');
            $btnText = h(isset($props['btnText']) ? $props['btnText'] : '');
            $bgColor = h(isset($props['bgColor']) ? $props['bgColor'] : '#6366f1');
            $textColor = isset($props['textColor']) ? $props['textColor'] : 'white';
            $padding = isset($props['padding']) ? $props['padding'] : '5';
            // Built-in background style + any user-supplied style merge.
            $hero_style_parts = array('background:' . $bgColor);
            $u_style = _user_attr_value($props, 'style');
            if ($u_style !== '') $hero_style_parts[] = $u_style;
            $extra_attrs = _render_extra_attrs($props);
            $html .= $pad . '<div class="py-' . h($padding) . ' text-center text-' . h($textColor) . '" style="' . implode(';', $hero_style_parts) . '"' . $extra_attrs . '>' . "\n";
            $html .= $pad . '    <div class="container">' . "\n";
            $html .= $pad . '        <h1 class="display-4 fw-bold">' . $title . '</h1>' . "\n";
            if ($subtitle) $html .= $pad . '        <p class="lead mb-4">' . $subtitle . '</p>' . "\n";
            if ($btnText) $html .= $pad . '        <a href="#" class="btn btn-light btn-lg">' . $btnText . '</a>' . "\n";
            $html .= $pad . '    </div>' . "\n";
            $html .= $pad . '</div>' . "\n";
            break;

        case 'card':
            $u_style = _user_attr_value($props, 'style');
            $extra_attrs = _render_extra_attrs($props);
            $html .= $pad . '<div class="card"'
                . ($u_style !== '' ? ' style="' . h($u_style) . '"' : '')
                . $extra_attrs . '>' . "\n";
            if (!empty($props['showHeader'])) $html .= $pad . '    <div class="card-header">' . h(isset($props['headerText']) ? $props['headerText'] : '') . '</div>' . "\n";
            if (!empty($props['showImage'])) $html .= $pad . '    <img src="" class="card-img-top" alt="">' . "\n";
            $html .= $pad . '    <div class="card-body">' . "\n";
            $html .= $pad . '        <h5 class="card-title">' . h(isset($props['title']) ? $props['title'] : '') . '</h5>' . "\n";
            $html .= $pad . '        <p class="card-text">' . h(isset($props['text']) ? $props['text'] : '') . '</p>' . "\n";
            $html .= $pad . '    </div>' . "\n";
            if (!empty($props['showFooter'])) $html .= $pad . '    <div class="card-footer text-muted">' . h(isset($props['footerText']) ? $props['footerText'] : '') . '</div>' . "\n";
            $html .= $pad . '</div>' . "\n";
            break;

        case 'carousel':
            $slides = isset($props['slides']) ? intval($props['slides']) : 3;
            $fade = !empty($props['fade']) ? ' carousel-fade' : '';
            $u_style = _user_attr_value($props, 'style');
            // Skip 'id' / 'data-bs-ride' so user attrs don't duplicate the
            // built-ins emitted just below.
            $extra_attrs = _render_extra_attrs($props, array('id', 'data-bs-ride'));
            $html .= $pad . '<div id="mainCarousel" class="carousel slide' . $fade . '" data-bs-ride="carousel"'
                . ($u_style !== '' ? ' style="' . h($u_style) . '"' : '')
                . $extra_attrs . '>' . "\n";
            if (!empty($props['indicators'])) {
                $html .= $pad . '    <div class="carousel-indicators">' . "\n";
                for ($i = 0; $i < $slides; $i++) $html .= $pad . '        <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="' . $i . '"' . ($i === 0 ? ' class="active"' : '') . '></button>' . "\n";
                $html .= $pad . '    </div>' . "\n";
            }
            $html .= $pad . '    <div class="carousel-inner">' . "\n";
            for ($i = 0; $i < $slides; $i++) $html .= $pad . '        <div class="carousel-item' . ($i === 0 ? ' active' : '') . '"><img src="" class="d-block w-100" alt="Slide ' . ($i+1) . '"></div>' . "\n";
            $html .= $pad . '    </div>' . "\n";
            if (!empty($props['controls'])) {
                $html .= $pad . '    <button class="carousel-control-prev" type="button" data-bs-target="#mainCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>' . "\n";
                $html .= $pad . '    <button class="carousel-control-next" type="button" data-bs-target="#mainCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>' . "\n";
            }
            $html .= $pad . '</div>' . "\n";
            break;

        case 'alert_box':
            $alertType = isset($props['type']) ? $props['type'] : 'info';
            $text = h(isset($props['text']) ? $props['text'] : '');
            $dismissible = !empty($props['dismissible']) ? ' alert-dismissible fade show' : '';
            $u_style = _user_attr_value($props, 'style');
            // Skip 'role' from user attrs so the built-in role="alert" wins.
            $extra_attrs = _render_extra_attrs($props, array('role'));
            $html .= $pad . '<div class="alert alert-' . h($alertType) . $dismissible . '" role="alert"'
                . ($u_style !== '' ? ' style="' . h($u_style) . '"' : '')
                . $extra_attrs . '>' . $text;
            if (!empty($props['dismissible'])) $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="' . h(lang('Close')) . '"></button>';
            $html .= '</div>' . "\n";
            break;

        case 'accordion':
            $items = isset($props['items']) ? intval($props['items']) : 3;
            $flush = !empty($props['flush']) ? ' accordion-flush' : '';
            $u_style = _user_attr_value($props, 'style');
            $extra_attrs = _render_extra_attrs($props, array('id'));
            $html .= $pad . '<div class="accordion' . $flush . '" id="accordionMain"'
                . ($u_style !== '' ? ' style="' . h($u_style) . '"' : '')
                . $extra_attrs . '>' . "\n";
            for ($i = 0; $i < $items; $i++) {
                $html .= $pad . '    <div class="accordion-item">' . "\n";
                $html .= $pad . '        <h2 class="accordion-header"><button class="accordion-button' . ($i > 0 ? ' collapsed' : '') . '" type="button" data-bs-toggle="collapse" data-bs-target="#collapse' . $i . '">Accordion Item #' . ($i+1) . '</button></h2>' . "\n";
                $html .= $pad . '        <div id="collapse' . $i . '" class="accordion-collapse collapse' . ($i === 0 ? ' show' : '') . '" data-bs-parent="#accordionMain"><div class="accordion-body">Content for item ' . ($i+1) . '.</div></div>' . "\n";
                $html .= $pad . '    </div>' . "\n";
            }
            $html .= $pad . '</div>' . "\n";
            break;

        case 'tabs':
            $tabCount = isset($props['tabCount']) ? intval($props['tabCount']) : 3;
            $pill = !empty($props['pill']);
            $u_style = _user_attr_value($props, 'style');
            // Skip 'role' (built-in) — id flows through; user can override.
            $extra_attrs = _render_extra_attrs($props, array('role'));
            $html .= $pad . '<ul class="nav nav-' . ($pill ? 'pills' : 'tabs') . '" role="tablist"'
                . ($u_style !== '' ? ' style="' . h($u_style) . '"' : '')
                . $extra_attrs . '>' . "\n";
            for ($i = 0; $i < $tabCount; $i++) $html .= $pad . '    <li class="nav-item"><button class="nav-link' . ($i === 0 ? ' active' : '') . '" data-bs-toggle="tab" data-bs-target="#tab' . $i . '">Tab ' . ($i+1) . '</button></li>' . "\n";
            $html .= $pad . '</ul>' . "\n";
            $html .= $pad . '<div class="tab-content p-3">' . "\n";
            for ($i = 0; $i < $tabCount; $i++) $html .= $pad . '    <div class="tab-pane fade' . ($i === 0 ? ' show active' : '') . '" id="tab' . $i . '">Content for Tab ' . ($i+1) . '</div>' . "\n";
            $html .= $pad . '</div>' . "\n";
            break;

        case 'btn':
            $text      = h(isset($props['text'])    ? $props['text']    : 'Button');
            $href      = h(isset($props['href'])     ? $props['href']    : '#');
            $variant   = isset($props['variant'])    ? $props['variant'] : 'primary';
            $outline   = !empty($props['outline'])   ? 'outline-'       : '';
            $size      = !empty($props['size'])      ? ' btn-' . h($props['size']) : '';
            $block     = !empty($props['block'])     ? ' d-block w-100' : '';
            $active    = !empty($props['active'])    ? ' active'        : '';
            $disabled  = !empty($props['disabled'])  ? ' disabled'      : '';
            $target    = !empty($props['target'])    ? ' target="' . h($props['target']) . '"' : '';
            $rel       = !empty($props['rel'])       ? ' rel="'    . h($props['rel'])    . '"' : '';
            $btn_element = isset($props['btnElement']) ? $props['btnElement'] : 'a';
            $tag = ($btn_element === 'button' || $btn_element === 'input') ? $btn_element : 'a';

            // Class composition with deduplication.
            // The btn component has historically emitted `btn btn-{variant}`
            // unconditionally + appended `cssClass`. When the saved tree has
            // a designer-set `cssClass: 'btn btn-outline-secondary'`, the
            // result was `btn btn-primary btn btn-outline-secondary` — both
            // colour classes apply, producing the wrong visual (CSS source
            // order picks one, leaving the other's properties as fallback,
            // breaking the white-on-white text fail seen with merged classes).
            //
            // Fix: when cssClass contains any explicit btn-{variant} or
            // btn-outline-X class, the designer-supplied class wins — we
            // emit JUST `btn` from the auto-class side and let cssClass
            // provide the variant. Otherwise the legacy auto path runs.
            $cls_extra_str = !empty($props['cssClass']) ? trim((string)$props['cssClass']) : '';
            $cls_extra_arr = $cls_extra_str !== '' ? preg_split('/\s+/', $cls_extra_str) : array();
            $designer_has_btn_variant = false;
            foreach ($cls_extra_arr as $_c) {
                if (preg_match('/^btn-(?:primary|secondary|success|danger|warning|info|light|dark|link|outline-(?:primary|secondary|success|danger|warning|info|light|dark))$/', $_c)) {
                    $designer_has_btn_variant = true;
                    break;
                }
            }
            if ($designer_has_btn_variant) {
                // Dedupe leading 'btn' so we don't emit `class="btn btn btn-primary"`.
                $cls_extra_arr = array_values(array_filter($cls_extra_arr, function ($c) { return $c !== 'btn'; }));
                $cls = 'btn ' . h(implode(' ', $cls_extra_arr)) . $size . $block . $active . $disabled;
            } else {
                $cls_extra = $cls_extra_str !== '' ? ' ' . h($cls_extra_str) : '';
                $cls = 'btn btn-' . $outline . h($variant) . $size . $block . $active . $disabled . $cls_extra;
            }
            $style_attr = _compose_inline_style($props);
            // Skip-list per output element type. CRITICAL: only the <input>
            // variant uses the `value` prop as its own value attr — for
            // <button> elements `value` is part of the form payload (e.g.
            // name=submit_update_cart value=1) and MUST NOT be skipped.
            // Previously `value` was unconditionally in the skip list,
            // which silently stripped form-submit values from bindable cart
            // / coupon / cart_update buttons → POSTs arrived with empty
            // submit_update_cart= and the cart action handler's
            // !empty($_POST['submit_update_cart']) check failed.
            if ($tag === 'a') {
                $btn_extra = _render_extra_attrs($props, array('type', 'href', 'target', 'rel'));
                $html .= $pad . '<a href="' . $href . '"' . $target . $rel . ' class="' . $cls . '"' . $style_attr . $btn_extra . '>' . $text . '</a>' . "\n";
            } elseif ($tag === 'input') {
                // <input> uses the button label as its `value` attr — skip
                // any user-supplied 'value' in _attrs so the explicit one wins.
                $btn_extra = _render_extra_attrs($props, array('type', 'value', 'href', 'target', 'rel'));
                $btn_type  = isset($props['btnType']) ? h($props['btnType']) : 'button';
                $html .= $pad . '<input type="' . $btn_type . '" value="' . $text . '" class="' . $cls . '"' . $style_attr . $btn_extra . '>' . "\n";
            } else {
                // <button> — keep `value` in _attrs (required for form submit).
                $btn_extra = _render_extra_attrs($props, array('type', 'href', 'target', 'rel'));
                $btn_type  = isset($props['btnType']) ? h($props['btnType']) : 'button';
                $html .= $pad . '<button type="' . $btn_type . '" class="' . $cls . '"' . $style_attr . $btn_extra . '>' . $text . '</button>' . "\n";
            }
            break;
    }

    return $html;
}

// Apply data bindings to a props array. If $props['_bindings'] = ['text' => 'baslik', ...]
// is set, returns a copy of $props with bound props overridden by ^^fieldName^^ tokens.
// Used by _render_content_html / _render_component_html / _render_tree_node so nodes
// inside a system widget render with feed placeholders instead of literal sample text.
//
// Side-effect: stamps `data-pg-bind="<prop>:<field>;…"` onto the rendered
// element (via _attrs). The catalog_item_view variant chooser JS reads this
// attribute when the visitor picks a different variant from a select-type
// group, finds every bound element, and live-updates the bound prop with
// the new product's value (no full page reload). Rendering still substitutes
// the ^^token^^ on the SSR pass so the page works without JS.
function _apply_bindings($props)
{
    if (empty($props['_bindings']) || !is_array($props['_bindings'])) return $props;
    // Only these binding props produce render output that the variant
    // chooser JS can swap. Everything else (action, section) is a
    // backend WIRING marker — it doesn't put a value into the DOM, so
    // including it in data-pg-bind would just confuse the JS updater.
    static $renderable_props = array('text' => 1, 'html' => 1, 'src' => 1, 'alt' => 1, 'href' => 1, 'value' => 1);
    $bind_pairs = array();
    foreach ($props['_bindings'] as $prop_name => $field_name) {
        if (!$field_name) continue;
        // Sanitize: field tokens must be [a-z0-9_] only
        $safe = preg_replace('/[^a-z0-9_]/i', '', (string)$field_name);
        if ($safe === '') continue;
        $props[$prop_name] = '^^' . $safe . '^^';
        // Skip non-renderable bindings (action, section) — these are wiring
        // markers handled by per-widget binding pre-processes, not values
        // the variant chooser JS should swap into the DOM.
        if (!isset($renderable_props[$prop_name])) continue;
        // Strip leading underscores from the field name for the JS hook
        // (the API response uses bare keys like 'name', 'price_formatted',
        // 'image_url' — not '__name', '__price_formatted', '__image_url').
        $field_clean = preg_replace('/^__+/', '', $safe);
        $bind_pairs[] = $prop_name . ':' . $field_clean;
    }
    if (!empty($bind_pairs)) {
        if (!isset($props['_attrs']) || !is_array($props['_attrs'])) $props['_attrs'] = array();
        // Drop any previous data-pg-bind so re-renders don't accumulate.
        $props['_attrs'] = array_values(array_filter($props['_attrs'], function ($a) {
            return !(is_array($a) && isset($a['name']) && $a['name'] === 'data-pg-bind');
        }));
        $props['_attrs'][] = array('name' => 'data-pg-bind', 'value' => implode(';', $bind_pairs));
    }
    return $props;
}

// Render a content node as HTML
function _render_content_html($props, $pad)
{
    // Apply data bindings first — system widget feed tokens override literal values
    $props = _apply_bindings($props);
    $ct = isset($props['contentType']) ? $props['contentType'] : '';
    $html = '';

    switch ($ct) {
        case 'heading':
            $tag = isset($props['tag']) ? $props['tag'] : 'h2';
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $tag)) $tag = 'h2';
            $text = isset($props['text']) ? $props['text'] : '';
            $cls = '';
            if (!empty($props['align'])) $cls .= 'text-' . h($props['align']);
            if (!empty($props['cssClass'])) $cls .= ($cls ? ' ' : '') . h($props['cssClass']);
            $style_attr  = _compose_inline_style($props);
            $extra_attrs = _render_extra_attrs($props);
            $html .= $pad . '<' . $tag
                . ($cls ? ' class="' . $cls . '"' : '')
                . $style_attr . $extra_attrs . '>' . $text . '</' . $tag . '>' . "\n";
            break;

        case 'link':
            $text   = isset($props['text']) ? $props['text'] : '';
            $href   = h(isset($props['href'])     ? $props['href']     : '#');
            $cls    = !empty($props['cssClass'])  ? ' class="' . h($props['cssClass']) . '"' : '';
            $target = !empty($props['target'])    ? ' target="' . h($props['target']) . '"' : '';
            $style_attr  = _compose_inline_style($props);
            $extra_attrs = _render_extra_attrs($props, array('href', 'target'));
            $html  .= $pad . '<a href="' . $href . '"' . $cls . $target
                . $style_attr . $extra_attrs . '>' . $text . '</a>' . "\n";
            break;

        case 'icon':
            $icon = h(isset($props['iconName']) ? $props['iconName']  : 'bi-star');
            $cls  = 'bi ' . $icon;
            if (!empty($props['cssClass'])) $cls .= ' ' . h($props['cssClass']);
            // Pass icon's font-size as the case-specific extra; _compose_inline_style
            // handles fontFamily + inlineStyle + user style merge.
            $extra_style = !empty($props['fontSize']) ? 'font-size:' . h($props['fontSize']) . 'px' : '';
            $style_attr  = _compose_inline_style($props, $extra_style);
            $extra_attrs = _render_extra_attrs($props);
            $html .= $pad . '<i class="' . $cls . '"' . $style_attr . $extra_attrs . '></i>' . "\n";
            break;

        case 'paragraph':
            $text = isset($props['text']) ? $props['text'] : '';
            $cls = '';
            if (!empty($props['lead'])) $cls .= 'lead';
            if (!empty($props['align'])) $cls .= ($cls ? ' ' : '') . 'text-' . h($props['align']);
            if (!empty($props['cssClass'])) $cls .= ($cls ? ' ' : '') . h($props['cssClass']);
            $style_attr  = _compose_inline_style($props);
            $extra_attrs = _render_extra_attrs($props);
            $html .= $pad . '<p'
                . ($cls ? ' class="' . $cls . '"' : '')
                . $style_attr . $extra_attrs . '>' . $text . '</p>' . "\n";
            break;

        case 'span':
            // Inline text — companion to heading/paragraph for the cases
            // where you need bound text (price, badge, label) inline next
            // to other content. Designer can stack a strike-through
            // <span> with the original price beside the discounted price
            // <span>, drop a small "Yeni!" badge after a heading, etc.
            $text = isset($props['text']) ? $props['text'] : '';
            $cls = !empty($props['cssClass']) ? h($props['cssClass']) : '';
            $style_attr  = _compose_inline_style($props);
            $extra_attrs = _render_extra_attrs($props);
            $html .= $pad . '<span'
                . ($cls ? ' class="' . $cls . '"' : '')
                . $style_attr . $extra_attrs . '>' . $text . '</span>' . "\n";
            break;

        case 'image':
            // Placeholder URL for the no-image fallback. Used both for empty
            // src AND as the onerror swap-target so visitors never see the
            // browser's broken-image glyph + alt text combo (which made
            // catalog cards with missing photos look unprofessional).
            $_pg_no_img = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/')
                        . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : 'software')
                        . '/assets/images/no-image.svg';
            $raw_src = isset($props['src']) ? (string)$props['src'] : '';
            // Empty src or src that's just `#` → render the placeholder
            // directly. Bound product-image tokens that resolve to '' (no
            // image_name on the product row) flow through this branch.
            if ($raw_src === '' || $raw_src === '#') {
                $src = h($_pg_no_img);
            } else {
                $src = h($raw_src);
            }
            $alt = h(isset($props['alt']) ? $props['alt'] : '');
            $cls = '';
            if (!empty($props['fluid'])) $cls .= 'img-fluid';
            if (!empty($props['rounded'])) $cls .= ($cls ? ' ' : '') . 'rounded';
            if (!empty($props['cssClass'])) $cls .= ($cls ? ' ' : '') . h($props['cssClass']);
            $img_w_raw = !empty($props['width'])  ? (string)$props['width']  : '';
            $img_h_raw = !empty($props['height']) ? (string)$props['height'] : '';
            $w_attr  = $img_w_raw !== '' ? ' width="'  . h($img_w_raw) . '"' : '';
            $h_attr  = $img_h_raw !== '' ? ' height="' . h($img_h_raw) . '"' : '';
            // Image's case-specific style: object-position. Everything else
            // (fontFamily — rare on <img> but possible, inlineStyle, user style)
            // is composed by the helper.
            $extra_style = !empty($props['objectPosition']) ? 'object-position:' . h($props['objectPosition']) : '';
            $obj_pos     = _compose_inline_style($props, $extra_style);
            $img_extra   = _render_extra_attrs($props, array('src', 'alt', 'width', 'height', 'onerror'));
            // onerror handler: when the bound src 404s, swap to the
            // placeholder. The `this.onerror=null` guard prevents an
            // infinite loop if even the placeholder ever fails to load.
            $onerror_attr = ' onerror="this.onerror=null;this.src=\'' . h($_pg_no_img) . '\'"';
            $img_tag = '<img src="' . $src . '" alt="' . $alt . '"' . $w_attr . $h_attr
                . ($cls ? ' class="' . $cls . '"' : '') . $obj_pos . $img_extra . $onerror_attr . '>';

            // Aspect Ratio wrapper: Bootstrap's .ratio class only works on a parent div.
            // When width/height are set on the image, ALSO mirror them as inline-style on
            // the wrapper so the ratio applies to a fixed size (instead of the default 100%
            // width). Numeric inputs ("100") get a 'px' suffix; values with units pass through
            // ("50%", "200px", "20rem", etc.).
            //
            // Overflow on a ratio'd image must apply to the WRAPPER, not the
            // <img> itself — the wrapper is the box .ratio sizes, so clipping
            // (object-fit:cover edges, scale-up, etc.) only works when the
            // wrapper has overflow:hidden. We pull `overflow*` props out of
            // the image's inline style and onto the wrapper when ratio is on.
            $ar = isset($props['aspectRatio']) ? $props['aspectRatio'] : '';
            if ($ar !== '' && preg_match('/^[0-9]+x[0-9]+$/', $ar)) {
                $wrapper_style_parts = array();
                if ($img_w_raw !== '') {
                    $wrapper_style_parts[] = 'width:' . (preg_match('/^\d+$/', $img_w_raw) ? ($img_w_raw . 'px') : h($img_w_raw));
                }
                if ($img_h_raw !== '') {
                    $wrapper_style_parts[] = 'height:' . (preg_match('/^\d+$/', $img_h_raw) ? ($img_h_raw . 'px') : h($img_h_raw));
                }
                // Hoist overflow / overflow-x / overflow-y to the wrapper.
                if (!empty($props['overflow']))  $wrapper_style_parts[] = 'overflow:'   . h($props['overflow']);
                if (!empty($props['overflowX'])) $wrapper_style_parts[] = 'overflow-x:' . h($props['overflowX']);
                if (!empty($props['overflowY'])) $wrapper_style_parts[] = 'overflow-y:' . h($props['overflowY']);
                // border-radius classes on the IMG would round just the img,
                // but with overflow:hidden on the wrapper the rounded corners
                // need to be on the wrapper too. Mirror the image's rounded
                // utility classes to the wrapper so the clip respects them.
                $wrapper_class = 'ratio ratio-' . h($ar);
                $img_cls_arr = preg_split('/\s+/', $cls);
                foreach ($img_cls_arr as $_ic) {
                    if ($_ic !== '' && (strpos($_ic, 'rounded') === 0)) {
                        $wrapper_class .= ' ' . $_ic;
                    }
                }
                $wrapper_style_attr = $wrapper_style_parts
                    ? ' style="' . implode(';', $wrapper_style_parts) . '"'
                    : '';
                $html .= $pad . '<div class="' . $wrapper_class . '"' . $wrapper_style_attr . '>' . "\n"
                      . $pad . '    ' . $img_tag . "\n"
                      . $pad . '</div>' . "\n";
            } else {
                $html .= $pad . $img_tag . "\n";
            }
            break;

        case 'divider':
            $margin = isset($props['margin']) ? $props['margin'] : '4';
            $cls = 'my-' . h($margin);
            if (!empty($props['cssClass'])) $cls .= ' ' . h($props['cssClass']);
            $style_attr  = _compose_inline_style($props);
            $extra_attrs = _render_extra_attrs($props);
            $html .= $pad . '<hr class="' . $cls . '"' . $style_attr . $extra_attrs . '>' . "\n";
            break;

        case 'spacer':
            $height = isset($props['height']) ? $props['height'] : '40';
            $cls = !empty($props['cssClass']) ? ' class="' . h($props['cssClass']) . '"' : '';
            $style_attr  = _compose_inline_style($props, 'height:' . h($height) . 'px');
            $extra_attrs = _render_extra_attrs($props);
            $html .= $pad . '<div' . $style_attr . $cls . $extra_attrs . '></div>' . "\n";
            break;

        case 'custom_html':
            $rawHtml = isset($props['html']) ? $props['html'] : '';
            $html .= $pad . $rawHtml . "\n";
            break;

        case 'custom_php':
            // Dynamic PHP block. Emit a base64 marker containing the source; the frontend
            // pipeline (`_expand_custom_php` in get_page_content.php) decodes and evals it
            // at page-render time so every request gets fresh output (date(), DB queries, etc.).
            // Raw PHP never lands in style_code as executable text — only inside a comment,
            // neutralized for passive readers. Only a designer/admin can write this node:
            // a manager or user save is merged by pg_designer_merge_restricted_tree(), which
            // keeps the stored custom_php nodes and drops any the submission added.
            $phpSrc = isset($props['php']) ? $props['php'] : '';
            $b64    = base64_encode($phpSrc);
            $html .= $pad . '<!--pg-custom-php:' . $b64 . '-->' . "\n";
            break;

        case 'applied_offers':
            // Marker for the cart widget render to substitute with the
            // live "Uygulanan Teklifler" alert HTML at request time.
            // Substitution happens in the static_values pass via the
            // `__applied_offers` token. Outside the cart widget the
            // marker simply disappears (token never gets replaced).
            $html .= $pad . '<!--pg-applied-offers-placeholder-->' . "\n";
            break;

        case 'breadcrumb':
            // Marker for catalog_item_view / catalog_listing render to
            // substitute with the live breadcrumb HTML (Ana Katalog →
            // Group → Subgroup → Product). Each widget detects the
            // marker via strpos AFTER its own breadcrumb computation.
            // Outside a catalog widget the marker simply disappears.
            $html .= $pad . '<!--pg-breadcrumb-placeholder-->' . "\n";
            break;

        case 'messages':
            // PHP session messages (errors + warnings + notices) from the liveform system.
            // formName filters to a specific liveform; widgets auto-fill this in their
            // pre-process pass so each widget instance only shows its own form's alerts
            // (e.g. catalog_item_view's messages node gets formName='catalog_detail').
            // When formName is empty, every pending message on the page is collected.
            //
            // CONSUME pattern — after rendering, the liveform's session entry is wiped
            // (mirrors `$liveform->remove_form()` in legacy custom-style pages). Without
            // this, the alert would replay on every subsequent page load. The visitor
            // still sees each alert at LEAST ONCE because we render BEFORE clearing.
            //
            // Storage layout (matches liveform.class.php):
            //   - errors: $_SESSION[…][lf_name][lf_idx][field_name]['error']=TRUE +
            //             [field_name]['error_message']='…'  (field-by-field)
            //   - notices / warnings: plain string arrays at the index root
            //
            // Markup mirrors liveform::output_errors() so theme CSS (software_error /
            // software_notice / software_warning + alert + alert-dismissible) applies.
            $msg_form  = !empty($props['formName']) ? $props['formName'] : '';
            $msg_cls   = !empty($props['cssClass']) ? ' ' . h($props['cssClass']) : '';
            $msg_parts = array();
            $msg_consume_targets = array();  // [[lf_name, lf_idx], …]

            if (!empty($_SESSION['software']['liveforms']) && is_array($_SESSION['software']['liveforms'])) {
                foreach ($_SESSION['software']['liveforms'] as $lf_name => $lf_indexes) {
                    // Both sides cast: a liveform keyed by a page id is stored
                    // under a NUMERIC array key (PHP converts '1131' to 1131),
                    // while formName arrives as the string it was written as.
                    // Strict comparison made every numerically-named form's
                    // messages invisible — which is every custom form, since
                    // custom_form.php keys its liveform by page_id.
                    if ($msg_form !== '' && (string)$lf_name !== (string)$msg_form) continue;
                    if (!is_array($lf_indexes)) continue;
                    foreach ($lf_indexes as $lf_idx => $lf_data) {
                        if (!is_array($lf_data)) continue;
                        $rendered_anything = false;
                        // Errors — field-level
                        $err_lines = '';
                        foreach ($lf_data as $_lf_key => $_lf_field) {
                            if (!is_array($_lf_field)) continue;
                            if (isset($_lf_field['error']) && $_lf_field['error'] == TRUE
                                && !empty($_lf_field['error_message'])) {
                                $err_lines .= '<p class="error">' . $_lf_field['error_message'] . '</p>';
                            }
                        }
                        if ($err_lines !== '') {
                            $msg_parts[] =
                                '<div class="software_error alert alert-danger alert-dismissible show' . $msg_cls . '" role="alert">' .
                                    '<button type="button" style="display:none;" class="btn-close no-popover" data-bs-dismiss="alert" aria-label="Close"></button>' .
                                    '<h5 class="alert-heading description">' . (function_exists('lang') ? lang('An error occurred') : 'An error occurred') . ':</h5>' .
                                    $err_lines .
                                '</div>';
                            $rendered_anything = true;
                        }
                        // Notices
                        if (!empty($lf_data['notices']) && is_array($lf_data['notices'])) {
                            $ntc_lines = '';
                            foreach ($lf_data['notices'] as $ntc) {
                                $ntc_lines .= '<p class="notice">' . $ntc . '</p>';
                            }
                            if ($ntc_lines !== '') {
                                $msg_parts[] =
                                    '<div class="software_notice alert alert-success alert-dismissible show' . $msg_cls . '" role="alert">' .
                                        '<button type="button" style="display:none;" class="btn-close no-popover" data-bs-dismiss="alert" aria-label="Close"></button>' .
                                        $ntc_lines .
                                    '</div>';
                                $rendered_anything = true;
                            }
                        }
                        // Warnings
                        if (!empty($lf_data['warnings']) && is_array($lf_data['warnings'])) {
                            $wrn_lines = '';
                            foreach ($lf_data['warnings'] as $wrn) {
                                $wrn_lines .= '<p class="warning">' . $wrn . '</p>';
                            }
                            if ($wrn_lines !== '') {
                                $msg_parts[] =
                                    '<div class="software_warning alert alert-warning alert-dismissible show' . $msg_cls . '" role="alert">' .
                                        '<button type="button" style="display:none;" class="btn-close no-popover" data-bs-dismiss="alert" aria-label="Close"></button>' .
                                        $wrn_lines .
                                    '</div>';
                                $rendered_anything = true;
                            }
                        }
                        if ($rendered_anything) {
                            $msg_consume_targets[] = array($lf_name, $lf_idx);
                        }
                    }
                }
            }

            if ($msg_parts) {
                $html .= $pad . implode("\n" . $pad, $msg_parts) . "\n";
            }

            // Consume — clear session entries for every liveform we just rendered.
            // Done AFTER markup is built so the alert lands on this page; next
            // page load won't replay because the entries are gone.
            foreach ($msg_consume_targets as $_t) {
                unset($_SESSION['software']['liveforms'][$_t[0]][$_t[1]]);
                if (empty($_SESSION['software']['liveforms'][$_t[0]])) {
                    unset($_SESSION['software']['liveforms'][$_t[0]]);
                }
            }
            break;

        case 'text':
            // Legacy contentType from older add-row/add-col code paths.
            // Render as a plain <span> so existing tables aren't silently empty.
            $text = isset($props['text']) ? $props['text'] : '';
            $cls  = !empty($props['cssClass']) ? ' class="' . h($props['cssClass']) . '"' : '';
            $style_attr  = _compose_inline_style($props);
            $extra_attrs = _render_extra_attrs($props);
            $html .= $pad . '<span' . $cls . $style_attr . $extra_attrs . '>' . $text . '</span>' . "\n";
            break;

        case 'carousel':
            // Bootstrap 5 image carousel. Slides come from props.slides which
            // catalog_item_view's pre-process populates from the current
            // product (products.image_name + products_images_xref) — for
            // select-type product groups, from product_groups.image_name +
            // product_groups_images_xref. Designer just drops the carousel
            // somewhere in the tree; bindings happen at render time.
            //
            // Settings (designer-tunable via props):
            //   thumbsPosition (bottom|top|left|right|none) — thumbnail strip
            //   thumbSize (px), autoplay (bool), interval (ms),
            //   showControls (bool — prev/next arrows, default ON),
            //   showIndicators (bool — dot indicators, default OFF),
            //   lightbox (bool — Fancybox v6 click-to-fullscreen, default ON),
            //   hoverZoom (bool — Fancybox panzoom magnifier, default OFF),
            //   activeBorder (color — active thumbnail border colour)
            $crl_slides = (isset($props['slides']) && is_array($props['slides']))
                              ? $props['slides'] : array();
            $crl_cls    = !empty($props['cssClass']) ? ' ' . h($props['cssClass']) : '';
            $crl_pos    = isset($props['thumbsPosition']) ? $props['thumbsPosition'] : 'bottom';
            if (!in_array($crl_pos, array('bottom','top','left','right','none'), true)) $crl_pos = 'bottom';
            $crl_thumb_size = max(20, min(200, isset($props['thumbSize']) ? (int)$props['thumbSize'] : 60));
            $crl_autoplay   = !empty($props['autoplay']);
            $crl_interval   = max(1000, min(60000, isset($props['interval']) ? (int)$props['interval'] : 5000));
            $crl_controls   = !isset($props['showControls'])   || $props['showControls'];
            // Indicators DEFAULT OFF — most product galleries don't use them
            // (thumbnails fill the same role). Designer can opt in via the
            // Carousel options panel.
            $crl_indicators = !empty($props['showIndicators']);
            $crl_lightbox   = !isset($props['lightbox'])       || $props['lightbox'];
            $crl_hover_zoom = !empty($props['hoverZoom']);
            $crl_active_border = isset($props['activeBorder']) ? (string)$props['activeBorder'] : '#0d6efd';
            // Sanitize: must be #rgb or #rrggbb hex.
            if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $crl_active_border)) $crl_active_border = '#0d6efd';

            if (empty($crl_slides)) {
                // Fallback when no images bound — render the same no-image
                // placeholder as the image content type, instead of a text
                // alert. Keeps the visual rhythm of the page consistent
                // (catalog cards, cart thumbs, product detail carousel all
                // show the same neutral picture frame).
                $_pg_no_img = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/')
                            . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : 'software')
                            . '/assets/images/no-image.svg';
                $html .= $pad . '<div class="pg-carousel-empty">'
                       . '<img src="' . h($_pg_no_img) . '" alt="" class="img-fluid d-block mx-auto"'
                       . ' style="max-height:480px;object-fit:contain"'
                       . ' onerror="this.onerror=null;this.style.display=\'none\'">'
                       . '</div>' . "\n";
                break;
            }

            $crl_id = 'pgC' . mt_rand(100000, 999999);
            // Layout class — pg_lightbox.css defines:
            //   .pg-carousel-thumbs-left  → flex-direction: row, thumbs first
            //   .pg-carousel-thumbs-right → flex-direction: row-reverse
            //   .pg-carousel-thumbs-top   → flex-direction: column-reverse
            //   .pg-carousel-thumbs-bottom→ flex-direction: column (default)
            //   .pg-carousel-thumbs-none  → no thumb strip rendered
            $crl_layout_cls = 'pg-carousel pg-carousel-thumbs-' . $crl_pos;
            // Fancybox gallery ID — stable per carousel instance. Every
            // slide AND thumbnail in this carousel shares the same value
            // so Fancybox groups them as ONE slideshow with prev/next.
            $crl_fb_gallery = $crl_id . '_fb';

            // Load Fancyapps Fancybox v6 (CSS+JS) once per page. The
            // helper also emits a global init that calls Fancybox.bind()
            // for every [data-fancybox] in the document — no per-carousel
            // init needed for the lightbox itself.
            $out = _pg_fancybox_assets_once();

            // Wrapper for layout (thumbs left/right need flex container).
            $crl_wrapper_cls = 'pg-carousel-wrapper';
            if ($crl_pos === 'left' || $crl_pos === 'right') {
                $crl_wrapper_cls .= ' pg-carousel-wrapper-horizontal';
            } elseif ($crl_pos === 'top' || $crl_pos === 'bottom') {
                $crl_wrapper_cls .= ' pg-carousel-wrapper-vertical';
            }
            $out .= '<div class="' . $crl_wrapper_cls . '"'
                  . ' style="--pg-carousel-active-border:' . h($crl_active_border) . '">';

            // For top position: thumbs go FIRST (CSS handles ordering, but
            // semantic ordering improves screen-reader experience too).
            if ($crl_pos === 'top' && count($crl_slides) > 1) {
                $out .= _pg_render_carousel_thumbs($crl_slides, $crl_id, $crl_thumb_size, $crl_lightbox, $crl_fb_gallery, $crl_pos);
            }
            if ($crl_pos === 'left' && count($crl_slides) > 1) {
                $out .= _pg_render_carousel_thumbs($crl_slides, $crl_id, $crl_thumb_size, $crl_lightbox, $crl_fb_gallery, $crl_pos);
            }

            // Main carousel
            $out .= '<div id="' . $crl_id . '" class="carousel slide ' . $crl_layout_cls . $crl_cls . '"';
            if ($crl_autoplay) {
                $out .= ' data-bs-ride="carousel" data-bs-interval="' . $crl_interval . '"';
            }
            $out .= '>';

            // Indicators (dots)
            if ($crl_indicators && count($crl_slides) > 1) {
                $out .= '<div class="carousel-indicators">';
                foreach ($crl_slides as $_si => $_slide) {
                    $out .= '<button type="button" data-bs-target="#' . $crl_id . '" data-bs-slide-to="' . (int)$_si . '"'
                          . ($_si === 0 ? ' class="active" aria-current="true"' : '')
                          . ' aria-label="Slide ' . ($_si + 1) . '"></button>';
                }
                $out .= '</div>';
            }

            // Slide images — when lightbox is on, the <img> is wrapped
            // in <a data-fancybox="<gallery>" href="<full-url>"> so
            // Fancybox v6 picks it up via the global Fancybox.bind()
            // call emitted by _pg_fancybox_assets_once(). Without this
            // anchor wrap (just data-* on the <img>), Fancybox doesn't
            // know what URL to open at full size.
            //
            // Hover zoom is applied via .pg-zoom-on-hover class +
            // data-pg-zoom-src — handled by the per-instance init
            // script at the END of the carousel markup.
            $out .= '<div class="carousel-inner">';
            foreach ($crl_slides as $_si => $_slide) {
                $_active = ($_si === 0) ? ' active' : '';
                $_zoom_cls = $crl_hover_zoom ? ' pg-zoom-on-hover' : '';
                $_zoom_src = $crl_hover_zoom ? ' data-pg-zoom-src="' . h($_slide) . '"' : '';
                if ($crl_lightbox) {
                    $out .= '<div class="carousel-item' . $_active . '">'
                          . '<a href="' . h($_slide) . '"'
                          . ' data-fancybox="' . h($crl_fb_gallery) . '"'
                          . ' class="pg-carousel-slide-link d-block w-100' . $_zoom_cls . '"'
                          . $_zoom_src . '>'
                          . '<img src="' . h($_slide) . '" class="d-block w-100" alt="" loading="lazy">'
                          . '</a>'
                          . '</div>';
                } else {
                    $out .= '<div class="carousel-item' . $_active . '">'
                          . '<img src="' . h($_slide) . '" class="d-block w-100' . $_zoom_cls . '"'
                          . ' alt="" loading="lazy"' . $_zoom_src . '>'
                          . '</div>';
                }
            }
            $out .= '</div>';

            // Prev/next controls
            if ($crl_controls && count($crl_slides) > 1) {
                $out .= '<button class="carousel-control-prev" type="button" data-bs-target="#' . $crl_id . '" data-bs-slide="prev">'
                      . '<span class="carousel-control-prev-icon" aria-hidden="true"></span>'
                      . '<span class="visually-hidden">' . lang('Previous') . '</span></button>';
                $out .= '<button class="carousel-control-next" type="button" data-bs-target="#' . $crl_id . '" data-bs-slide="next">'
                      . '<span class="carousel-control-next-icon" aria-hidden="true"></span>'
                      . '<span class="visually-hidden">' . lang('Next') . '</span></button>';
            }

            $out .= '</div>';  // /carousel

            // For bottom/right: thumbs go AFTER the carousel.
            if (($crl_pos === 'bottom' || $crl_pos === 'right') && count($crl_slides) > 1) {
                $out .= _pg_render_carousel_thumbs($crl_slides, $crl_id, $crl_thumb_size, $crl_lightbox, $crl_fb_gallery, $crl_pos);
            }

            $out .= '</div>';  // /pg-carousel-wrapper

            // Per-instance init script — wires:
            //   • Active thumbnail sync on Bootstrap carousel slide events
            //   • Optional zoom-on-hover effect (pure CSS+JS, no Fancybox needed)
            // Lives at the end of the carousel HTML so removing the carousel
            // also removes this init code (no orphaned event listeners).
            $out .= _pg_render_carousel_init_script($crl_id, $crl_hover_zoom);

            $html .= $pad . $out . "\n";
            break;
    }

    return $html;
}

// Save tree regions to system_style_cells for backward compatibility
function save_tree_cells_to_db($tree, $style_id)
{
    $cells = array();
    _extract_cells_from_tree($tree, 'page_content', $cells);

    foreach ($cells as $idx => $cell) {
        $query =
            "INSERT INTO system_style_cells (
                style_id,
                area,
                `row`,
                col,
                region_type,
                region_name)
            VALUES (
                '" . escape($style_id) . "',
                '" . escape($cell['area']) . "',
                '" . escape($cell['row']) . "',
                '" . escape($cell['col']) . "',
                '" . escape($cell['region_type']) . "',
                '" . escape($cell['region_name']) . "')";
        mysqli_query(db::$con, $query) or output_error('Query failed.');
    }
}

// Recursively extract region cells from the tree, tracking area context
function _extract_cells_from_tree($node, $current_area, &$cells, $row_num = 1, $col_num = 1)
{
    if (!is_array($node)) return;
    $type = isset($node['type']) ? $node['type'] : '';
    $props = isset($node['props']) ? $node['props'] : array();

    // If this is an area node, update current area
    if ($type === 'area' && !empty($props['areaName'])) {
        $current_area = $props['areaName'];
    }

    // If this is a region node, record it
    if ($type === 'region') {
        $cells[] = array(
            'area' => $current_area,
            'row' => count($cells) + 1,
            'col' => 1,
            'region_type' => isset($props['regionType']) ? $props['regionType'] : '',
            'region_name' => isset($props['regionName']) ? $props['regionName'] : ''
        );
        return;
    }

    // Recurse into children
    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $child) {
            _extract_cells_from_tree($child, $current_area, $cells);
        }
    }
}
// initialize function for getting HTML for a layout for the style designer screen
// this function is used by the create/edit system style screens
function get_style_designer_content($layout)
{
    // prepare HTML for areas differently based on the selected layout
    switch ($layout) {
        case 'one_column':
            $output_areas = '<fieldset>

                    <legend>Site Border<span class="theme_fold_css" style="padding:0"><br />#site_border</span></legend>

                    ' . get_style_designer_area_content('site_top') . '

                    ' . get_style_designer_area_content('site_header') . '

                    <fieldset>

                        <legend>Area Border<span class="theme_fold_css" style="padding:0"><br />#area_border</span></legend>

                        ' . get_style_designer_area_content('area_header') . '

                        <fieldset>

                            <legend>Page Wrapper<span class="theme_fold_css" style="padding:0"><br />#page_wrapper</span></legend>

                            <fieldset>

                                <legend>Page Border<span class="theme_fold_css" style="padding:0"><br />#page_border</span></legend>

                                ' . get_style_designer_area_content('page_header') . '

                                ' . get_style_designer_area_content('page_content') . '

                                ' . get_style_designer_area_content('page_footer') . '

                            </fieldset>

                            ' . get_style_designer_area_content('area_footer') . '

                        </fieldset>

                    </fieldset>

                    <fieldset>

                        <legend>Site Footer Border<span class="theme_fold_css" style="padding:0"><br />#site_footer_border</span></legend>

                        ' . get_style_designer_area_content('site_footer') . '

                    </fieldset>

                </fieldset>';
            break;
        case 'one_column_email':
            $output_areas = '<fieldset>

                    <legend>E-mail Border<span class="theme_fold_css" style="padding:0"><br />#email_border</span></legend>

                    ' . get_style_designer_area_content('site_top') . '

                    ' . get_style_designer_area_content('site_header') . '

                    <fieldset>

                        <legend>Area Border<span class="theme_fold_css" style="padding:0"><br />#area_border</span></legend>

                        ' . get_style_designer_area_content('area_header') . '

                        <fieldset>

                            <legend>Page Wrapper<span class="theme_fold_css" style="padding:0"><br />#page_wrapper</span></legend>

                            <fieldset>

                                <legend>Page Border<span class="theme_fold_css" style="padding:0"><br />#page_border</span></legend>

                                ' . get_style_designer_area_content('page_header') . '

                                ' . get_style_designer_area_content('page_content') . '

                                ' . get_style_designer_area_content('page_footer') . '

                            </fieldset>

                            ' . get_style_designer_area_content('area_footer') . '

                        </fieldset>

                    </fieldset>

                    <fieldset>

                        <legend>Site Footer Border<span class="theme_fold_css" style="padding:0"><br />#site_footer_border</span></legend>

                        ' . get_style_designer_area_content('site_footer') . '

                    </fieldset>

                </fieldset>';
            break;
        case 'one_column_mobile':
            $output_areas = '<fieldset>

                    <legend><span style="border: 1px solid #99FF33;padding: 0.25em 0.75em 1em 0.75em;-moz-border-radius-topleft: 5px;-webkit-border-top-left-radius: 5px;border-top-left-radius: 5px;-moz-border-radius-topright: 5px;-webkit-border-top-right-radius: 5px;border-top-right-radius: 5px;-moz-border-radius-bottomleft: 5px;-webkit-border-bottom-left-radius: 5px;border-bottom-left-radius: 5px;-moz-border-radius-bottomright: 5px;-webkit-border-bottom-right-radius: 5px;border-bottom-right-radius: 5px;">Mobile Border</span><span class="theme_fold_css" style="padding: 0"><br />&nbsp;&nbsp;#mobile_border</span></legend>

                    ' . get_style_designer_area_content('site_top') . '

                    ' . get_style_designer_area_content('site_header') . '

                    <fieldset>

                        <legend>Area Border<span class="theme_fold_css" style="padding:0"><br />#area_border</span></legend>

                        ' . get_style_designer_area_content('area_header') . '

                        <fieldset>

                            <legend>Page Wrapper<span class="theme_fold_css" style="padding:0"><br />#page_wrapper</span></legend>

                            <fieldset>

                                <legend>Page Border<span class="theme_fold_css" style="padding:0"><br />#page_border</span></legend>

                                ' . get_style_designer_area_content('page_header') . '

                                ' . get_style_designer_area_content('page_content') . '

                                ' . get_style_designer_area_content('page_footer') . '

                            </fieldset>

                            ' . get_style_designer_area_content('area_footer') . '

                        </fieldset>

                    </fieldset>

                    <fieldset>

                        <legend>Site Footer Border<span class="theme_fold_css" style="padding:0"><br />#site_footer_border</span></legend>

                        ' . get_style_designer_area_content('site_footer') . '

                    </fieldset>

                </fieldset>';
            break;
        case 'two_column_sidebar_left':
            $output_areas = '<fieldset>

                    <legend>Site Border<span class="theme_fold_css" style="padding:0"><br />#site_border</span></legend>

                    ' . get_style_designer_area_content('site_top') . '

                    ' . get_style_designer_area_content('site_header') . '

                    <fieldset>

                        <legend>Area Border<span class="theme_fold_css" style="padding:0"><br />#area_border</span></legend>

                        ' . get_style_designer_area_content('area_header') . '

                        <fieldset>

                            <legend>Page Wrapper<span class="theme_fold_css" style="padding:0"><br />#page_wrapper</span></legend>

                            <fieldset>

                                <legend>Page Border<span class="theme_fold_css" style="padding:0"><br />#page_border</span></legend>

                                ' . get_style_designer_area_content('page_header') . '

                                ' . get_style_designer_area_content('page_content') . '

                                ' . get_style_designer_area_content('sidebar') . '

                                <div class="clear"></div>

                                ' . get_style_designer_area_content('page_footer') . '

                            </fieldset>

                            ' . get_style_designer_area_content('area_footer') . '

                        </fieldset>

                    </fieldset>

                    <fieldset>

                        <legend>Site Footer Border<span class="theme_fold_css" style="padding:0"><br />#site_footer_border</span></legend>

                        ' . get_style_designer_area_content('site_footer') . '

                    </fieldset>

                </fieldset>';
            break;
        case 'two_column_sidebar_right':
            $output_areas = '<fieldset>

                    <legend>Site Border<span class="theme_fold_css" style="padding:0"><br />#site_border</span></legend>

                    ' . get_style_designer_area_content('site_top') . '

                    ' . get_style_designer_area_content('site_header') . '

                    <fieldset>

                        <legend>Area Border<span class="theme_fold_css" style="padding:0"><br />#area_border</span></legend>

                        ' . get_style_designer_area_content('area_header') . '

                        <fieldset>

                            <legend>Page Wrapper<span class="theme_fold_css" style="padding:0"><br />#page_wrapper</span></legend>

                            <fieldset>

                                <legend>Page Border<span class="theme_fold_css" style="padding:0"><br />#page_border</span></legend>

                                ' . get_style_designer_area_content('page_header') . '

                                ' . get_style_designer_area_content('page_content') . '

                                ' . get_style_designer_area_content('sidebar') . '

                                <div class="clear"></div>

                                ' . get_style_designer_area_content('page_footer') . '

                            </fieldset>

                            ' . get_style_designer_area_content('area_footer') . '

                        </fieldset>

                    </fieldset>

                    <fieldset>

                        <legend>Site Footer Border<span class="theme_fold_css" style="padding:0"><br />#site_footer_border</span></legend>

                        ' . get_style_designer_area_content('site_footer') . '

                    </fieldset>

                </fieldset>';
            break;
        case 'three_column_sidebar_left':
            $output_areas = '<fieldset>

                    <legend>Site Border<span class="theme_fold_css" style="padding:0"><br />#site_border</span></legend>

                    ' . get_style_designer_area_content('site_top') . '

                    ' . get_style_designer_area_content('site_header') . '

                    <fieldset>

                        <legend>Area Border<span class="theme_fold_css" style="padding:0"><br />#area_border</span></legend>

                        ' . get_style_designer_area_content('area_header') . '

                        <fieldset>

                            <legend>Page Wrapper<span class="theme_fold_css" style="padding:0"><br />#page_wrapper</span></legend>

                            <fieldset>

                                <legend>Page Border<span class="theme_fold_css" style="padding:0"><br />#page_border</span></legend>

                                ' . get_style_designer_area_content('page_header') . '

                                ' . get_style_designer_area_content('sidebar') . '

                                <fieldset id="page_content">

                                    <legend>Page Content<span class="theme_fold_css" style="padding:0"><br />#page_content</span></legend>

                                    ' . get_style_designer_area_content('page_content_left') . '

                                    ' . get_style_designer_area_content('page_content_right') . '

                                    <div class="clear"></div>

                                </fieldset>

                                <div class="clear"></div>

                                ' . get_style_designer_area_content('page_footer') . '

                            </fieldset>

                            ' . get_style_designer_area_content('area_footer') . '

                        </fieldset>

                    </fieldset>

                    <fieldset>

                        <legend>Site Footer Border<span class="theme_fold_css" style="padding:0"><br />#site_footer_border</span></legend>

                        ' . get_style_designer_area_content('site_footer') . '

                    </fieldset>

                </fieldset>';
            break;
    }
    return '<div id="layout" class="' . $layout . '">

            ' . $output_areas . '

            <div id="edit_cell_properties">

                <table style="margin-bottom: 1.5em">

                    <tr>

                        <td>Region Type:</td>

                        <td>

                            <select id="region_type">

                                <option value=""></option>

                                <option value="ad">Ad</option>

                                <option value="cart">Cart</option>

                                <option value="common">Common</option>

                                <option value="designer">Designer</option>

                                <option value="dynamic">Dynamic</option>

                                <option value="login">Login</option>

                                <option value="menu">Menu</option>

                                <option value="menu_sequence">Menu Sequence</option>

                                <option value="mobile_switch">Mobile Switch</option>

                                <option value="page">Page</option>

                                <option value="pdf">PDF (beta)</option>

                                <option value="system">System</option>

                                <option value="tag_cloud">Tag Cloud</option>

                            </select>

                        </td>

                    </tr>

                    <tr id="region_name_row" style="display: none">

                        <td>Region Name:</td>

                        <td><select id="region_name"></select></td>

                    </tr>

                </table>

                <div style="text-align: center"><input type="button" id="update_cell_properties" value="Update" class="submit-primary" />&nbsp;&nbsp;&nbsp;<input type="button" id="cancel_cell_properties" value="Cancel" class="submit-secondary" /></div>

            </div>

        </div>';
}
// initialize function for getting HTML for a single area for the style designer
// this function is used by the create/edit system style screens
function get_style_designer_area_content($area)
{
    // get area label based on the area
    $output_area_label = ucwords(str_replace("_", " ", $area)) . '<span class="theme_fold_css"><br />#' . $area . '</span>';
    return '<div id="' . $area . '" class="area">

            <div class="area_label">' . $output_area_label . '</div>

            <div class="area_buttons"><img id="' . $area . '_add_row_before" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/style_designer_add_row_before.png" width="22" height="21" border="0" alt="Insert row before" title="Insert row before" class="area_button" /> <img id="' . $area . '_add_row_after" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/style_designer_add_row_after.png" width="22" height="21" border="0" alt="Insert row after" title="Insert row after" class="area_button" /> <img id="' . $area . '_add_column_before" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/style_designer_add_column_before.png" width="22" height="21" border="0" alt="Insert column before" title="Insert column before" class="area_button disabled" /> <img id="' . $area . '_add_column_after" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/style_designer_add_column_after.png" width="22" height="21" border="0" alt="Insert column after" title="Insert column after" class="area_button disabled" /> <img id="' . $area . '_edit_cell_properties" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/style_designer_edit_cell_properties.png" width="22" height="21" border="0" alt="Edit cell properties" title="Edit cell properties" class="area_button disabled" /></div>

            <div class="clear"></div>

            <div class="cells"></div>

        </div>';
}
// initialize function for getting JavaScript for all regions for style designer
// this function is used by the create/edit system style screens
function get_style_designer_regions_for_javascript()
{
    // get ad regions in order to prepare list for JavaScript
    $query = "SELECT name FROM ad_regions ORDER BY name ASC";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $ad_regions = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $ad_regions[] = $row;
    }
    $output_ad_regions_for_javascript = '';
    // loop through the ad regions in order to prepare JavaScript list
    foreach ($ad_regions as $ad_region) {
        // if this is not the first ad region in the list, then add a comma and space
        if ($output_ad_regions_for_javascript != '') {
            $output_ad_regions_for_javascript .= ', ';
        }
        // add ad region to list
        $output_ad_regions_for_javascript .= '"' . escape_javascript($ad_region['name']) . '"';
    }
    // get common regions in order to prepare list for JavaScript
    $query = "SELECT cregion_name as name FROM cregion WHERE cregion_designer_type = 'no' ORDER BY name ASC";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $common_regions = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $common_regions[] = $row;
    }
    $output_common_regions_for_javascript = '';
    // loop through the common regions in order to prepare JavaScript list
    foreach ($common_regions as $common_region) {
        // if this is not the first common region in the list, then add a comma and space
        if ($output_common_regions_for_javascript != '') {
            $output_common_regions_for_javascript .= ', ';
        }
        // add common region to list
        $output_common_regions_for_javascript .= '"' . escape_javascript($common_region['name']) . '"';
    }
    // get designer regions in order to prepare list for JavaScript
    $query = "SELECT cregion_name as name FROM cregion WHERE cregion_designer_type = 'yes' ORDER BY name ASC";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $designer_regions = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $designer_regions[] = $row;
    }
    $output_designer_regions_for_javascript = '';
    // loop through the designer regions in order to prepare JavaScript list
    foreach ($designer_regions as $designer_region) {
        // if this is not the first designer region in the list, then add a comma and space
        if ($output_designer_regions_for_javascript != '') {
            $output_designer_regions_for_javascript .= ', ';
        }
        // add designer region to list
        $output_designer_regions_for_javascript .= '"' . escape_javascript($designer_region['name']) . '"';
    }
    // get dynamic regions in order to prepare list for JavaScript
    $query = "SELECT dregion_name as name FROM dregion ORDER BY name ASC";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $dynamic_regions = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $dynamic_regions[] = $row;
    }
    $output_dynamic_regions_for_javascript = '';
    // loop through the dynamic regions in order to prepare JavaScript list
    foreach ($dynamic_regions as $dynamic_region) {
        // if this is not the first dynamic region in the list, then add a comma and space
        if ($output_dynamic_regions_for_javascript != '') {
            $output_dynamic_regions_for_javascript .= ', ';
        }
        // add dynamic region to list
        $output_dynamic_regions_for_javascript .= '"' . escape_javascript($dynamic_region['name']) . '"';
    }
    // get login regions in order to prepare list for JavaScript
    $query = "SELECT name FROM login_regions ORDER BY name ASC";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $login_regions = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $login_regions[] = $row;
    }
    $output_login_regions_for_javascript = '';
    // loop through the login regions in order to prepare JavaScript list
    foreach ($login_regions as $login_region) {
        // if this is not the first login region in the list, then add a comma and space
        if ($output_login_regions_for_javascript != '') {
            $output_login_regions_for_javascript .= ', ';
        }
        // add login region to list
        $output_login_regions_for_javascript .= '"' . escape_javascript($login_region['name']) . '"';
    }
    // get menu regions in order to prepare list for JavaScript
    $query = "SELECT name FROM menus ORDER BY name ASC";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $menu_regions = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $menu_regions[] = $row;
    }
    $output_menu_regions_for_javascript = '';
    // loop through the menu regions in order to prepare JavaScript list
    foreach ($menu_regions as $menu_region) {
        // if this is not the first menu region in the list, then add a comma and space
        if ($output_menu_regions_for_javascript != '') {
            $output_menu_regions_for_javascript .= ', ';
        }
        // add menu region to list
        $output_menu_regions_for_javascript .= '"' . escape_javascript($menu_region['name']) . '"';
    }
    $output_menu_sequence_for_javascript = '';
    // loop through the menu regions in order to prepare JavaScript list
    foreach ($menu_regions as $menu_region) {
        // if this is not the first menu region in the list, then add a comma and space
        if ($output_menu_sequence_for_javascript != '') {
            $output_menu_sequence_for_javascript .= ', ';
        }
        // add menu region to list
        $output_menu_sequence_for_javascript .= '"' . escape_javascript($menu_region['name']) . '"';
    }
    // get tag cloud regions in order to prepare list for JavaScript
    $query = "SELECT page_name as name FROM page WHERE page_type = 'search results' ORDER BY page_name ASC";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $tag_cloud_regions = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $tag_cloud_regions[] = $row;
    }
    $output_tag_cloud_regions_for_javascript = '';
    // loop through the tag cloud regions in order to prepare JavaScript list
    foreach ($tag_cloud_regions as $tag_cloud_region) {
        // if this is not the first tag cloud region in the list, then add a comma and space
        if ($output_tag_cloud_regions_for_javascript != '') {
            $output_tag_cloud_regions_for_javascript .= ', ';
        }
        // add tag cloud region to list
        $output_tag_cloud_regions_for_javascript .= '"' . escape_javascript($tag_cloud_region['name']) . '"';
    }
    // get pages with certain page types in order to prepare the system region list for JavaScript
    // we don't want to include pages which will create an error (e.g. form item view)
    $query = "SELECT page_name as name

        FROM page

        WHERE

            (page_type = 'folder view')

            || (page_type = 'photo gallery')

            || (page_type = 'custom form')

            || (page_type = 'form list view')

            || (page_type = 'form view directory')

            || (page_type = 'calendar view')

            || (page_type = 'catalog')

            || (page_type = 'express order')

            || (page_type = 'order form')

            || (page_type = 'shopping cart')

            || (page_type = 'search results')

        ORDER BY page_name ASC";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    $system_region_pages = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $system_region_pages[] = $row;
    }
    $output_system_region_pages_for_javascript = '';
    // loop through the system region pages in order to prepare JavaScript list
    foreach ($system_region_pages as $system_region_page) {
        // if this is not the first page in the list, then add a comma and space
        if ($output_system_region_pages_for_javascript != '') {
            $output_system_region_pages_for_javascript .= ', ';
        }
        // add tag cloud region to list
        $output_system_region_pages_for_javascript .= '"' . escape_javascript($system_region_page['name']) . '"';
    }
    return 'var ad_regions = [' . $output_ad_regions_for_javascript . '];

        var common_regions = [' . $output_common_regions_for_javascript . '];

        var designer_regions = [' . $output_designer_regions_for_javascript . '];

        var dynamic_regions = [' . $output_dynamic_regions_for_javascript . '];

        var login_regions = [' . $output_login_regions_for_javascript . '];

        var menu_regions = [' . $output_menu_regions_for_javascript . '];

        var menu_sequence_regions = [' . $output_menu_sequence_for_javascript . '];

        var tag_cloud_regions = [' . $output_tag_cloud_regions_for_javascript . '];

        var system_region_pages = [' . $output_system_region_pages_for_javascript . '];';
}
// Returns region data as a JSON object for Visual Pinegrap Editor (used by edit_system_style.php).
// Parallel to get_style_designer_regions_for_javascript() but returns json_encode'd output instead
// of bare JS variable declarations, so the caller can assign it to a single JS variable.
function get_style_designer_regions_as_json()
{
    $data = array(
        'ad_regions'           => array(),
        'common_regions'       => array(),
        'designer_regions'     => array(),
        'dynamic_regions'      => array(),
        'login_regions'        => array(),
        'menu_regions'         => array(),
        'menu_sequence_regions'=> array(),
        'tag_cloud_regions'    => array(),
        'system_region_pages'  => array(),
    );

    $result = mysqli_query(db::$con, "SELECT name FROM ad_regions ORDER BY name ASC") or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) { $data['ad_regions'][] = $row['name']; }

    $result = mysqli_query(db::$con, "SELECT cregion_name as name FROM cregion WHERE cregion_designer_type = 'no' ORDER BY name ASC") or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) { $data['common_regions'][] = $row['name']; }

    $result = mysqli_query(db::$con, "SELECT cregion_name as name FROM cregion WHERE cregion_designer_type = 'yes' ORDER BY name ASC") or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) { $data['designer_regions'][] = $row['name']; }

    $result = mysqli_query(db::$con, "SELECT dregion_name as name FROM dregion ORDER BY name ASC") or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) { $data['dynamic_regions'][] = $row['name']; }

    $result = mysqli_query(db::$con, "SELECT name FROM login_regions ORDER BY name ASC") or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) { $data['login_regions'][] = $row['name']; }

    $result = mysqli_query(db::$con, "SELECT name FROM menus ORDER BY name ASC") or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        $data['menu_regions'][]            = $row['name'];
        $data['menu_sequence_regions'][]   = $row['name'];
    }

    $result = mysqli_query(db::$con, "SELECT page_name as name FROM page WHERE page_type = 'search results' ORDER BY page_name ASC") or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) { $data['tag_cloud_regions'][] = $row['name']; }

    $result = mysqli_query(db::$con, "SELECT page_name as name FROM page WHERE
            (page_type = 'folder view') OR (page_type = 'photo gallery') OR (page_type = 'custom form') OR
            (page_type = 'form list view') OR (page_type = 'form view directory') OR (page_type = 'calendar view') OR
            (page_type = 'catalog') OR (page_type = 'express order') OR (page_type = 'order form') OR
            (page_type = 'shopping cart') OR (page_type = 'search results')
        ORDER BY page_name ASC") or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) { $data['system_region_pages'][] = $row['name']; }

    // JSON_HEX_TAG ensures < and > are Unicode-escaped (\u003C / \u003E),
    // so a value containing "</script>" never closes the surrounding <script> tag.
    return json_encode($data, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
// create function that will look through $css_properties recursively and find Google font families
function get_google_font_families($array)
{
    $google_font_families = array();
    // loop through the array in order to find Google font families
    foreach ($array as $key => $value) {
        // if this item is a font_family item and the value is a Google font family,
        // then add it to the array
        if (($key == 'font_family') && (mb_substr($value, 0, 2) == '\'-')) {
            $google_font_families[] = $value;
            // else if the value is an array, then run this same function (i.e. by using recursion)
            // in order to look deeper into the array
        } else if (is_array($value) == true) {
            $google_font_families = array_merge($google_font_families, get_google_font_families($value));
        }
    }
    // remove duplicates
    $google_font_families = array_unique($google_font_families);
    return $google_font_families;
}
//this function returns the tint color (tint to white) from the color and degree passed to it
function color_tint($color, $degree)
{
    // gradient calc example: $color = 48823A $degree = 30 (for 30% percent shading)
    //      R = (48 * .3) + (FF * (1-.3)) = C8  //  G = (83 * .3) + (FF * (1-.3)) = D9  // B = (2A * .3) + (FF * (1-.3)) = BF
    //      darker bottom = #48832A; lighter top (30% white tint) = #C8D9BF
    $rgb = hexdec($color);
    $r = ($rgb >> 16) & 0xFF;
    $g = ($rgb >> 8) & 0xFF;
    $b = $rgb & 0xFF;
    $mixcolor1 = ($degree / 100);
    $mixcolor2 = (1 - $mixcolor1);
    $r_tint = ($r * $mixcolor1) + (0xFF * $mixcolor2);
    $g_tint = ($g * $mixcolor1) + (0xFF * $mixcolor2);
    $b_tint = ($b * $mixcolor1) + (0xFF * $mixcolor2);
    $tint_color = mb_strtoupper(dechex(($r_tint << 16) + ($g_tint << 8) + ($b_tint)));
    return $tint_color;
}
// this function prepares the module selector
function prepare_module_selector($module, $selector)
{
    // if the module is not one of the base modules, then add the module selector to the selector
    if (($module != 'base_module') && ($module != 'background_borders_and_spacing') && ($module != 'text') && ($module != 'layout')) {
        switch ($module) {
            case 'headings_general':
                $selector = $selector . ' h1,' . $selector . ' h2,' . $selector . ' h3,' . $selector . ' h4,' . $selector . ' h5,' . $selector . ' h6';
                break;
            case 'heading_1':
                $selector .= ' h1';
                break;
            case 'heading_2':
                $selector .= ' h2';
                break;
            case 'heading_3':
                $selector .= ' h3';
                break;
            case 'heading_4':
                $selector .= ' h4';
                break;
            case 'heading_5':
                $selector .= ' h5';
                break;
            case 'heading_6':
                $selector .= ' h6';
                break;
            case 'image_primary':
                $selector = $selector . ' img.image-primary,' . $selector . ' img.image-left-primary,' . $selector . ' img.image-right-primary';
                break;
            case 'image_secondary':
                $selector = $selector . ' img.image-secondary,' . $selector . ' img.image-left-secondary,' . $selector . ' img.image-right-secondary';
                break;
            case 'links':
                $selector = $selector . ' a:link,' . $selector . ' a:active,' . $selector . ' a:visited';
                break;
            case 'links_hover':
                $selector = $selector . ' a:hover,' . $selector . ' a:focus';
                break;
            case 'paragraph':
                $selector = $selector . ' p';
                break;
            case 'table':
                $selector = $selector . ' table';
                break;
        }
    }
    return $selector;
}
// this function prepares and returns the css properties
function output_css_properties($properties, $object = '')
{
    $output = '';
    // if there are properties, then continue
    if (empty($properties) == false) {
        foreach ($properties as $property => $value) {
            // force importance for button styling so, for example, the theme designer that sets #site_footer a:link {...}
            // doesn't override the custom format buttons.
            if (($object == 'primary_buttons') || ($object == 'secondary_buttons') || ($object == 'primary_buttons_hover') || ($object == 'secondary_buttons_hover')) {
                $make_important = ' !important';
            } else {
                $make_important = '';
            }
            switch ($property) {
                case 'background_type':
                case 'background_color':
                case 'background_image':
                case 'background_horizontal_position':
                case 'background_vertical_position':
                case 'background_repeat':
                    $output_background = '';
                    // if there is a background color, then output the background css property and the add the color to it
                    if ($properties['background_color'] != '') {
                        // add a space so that there is one between the property and value
                        $output_background .= ' ';
                        // if the background color is not transparent, then add a pound sign to the background
                        if ($properties['background_color'] != 'transparent') {
                            $output_background .= '#';
                        }
                        // add the background color value to the background property
                        $output_background .= $properties['background_color'];
                        // unset the property so that we do not loop throug it again
                        unset($properties['background_color']);
                    }
                    // if there is a background image then add it to the background property
                    if ($properties['background_image'] != '') {
                        $output_background .= ' url({path}' . $properties['background_image'] . ')';
                        // unset the property so that we do not loop throug it again
                        unset($properties['background_image']);
                    }
                    // if there is a background horizontal position then add it to the background property
                    if ($properties['background_horizontal_position'] != '') {
                        $output_background .= ' ' . $properties['background_horizontal_position'];
                        // unset the property so that we do not loop throug it again
                        unset($properties['background_horizontal_position']);
                    }
                    // if there is a background horizontal position then add it to the background property
                    if ($properties['background_vertical_position'] != '') {
                        $output_background .= ' ' . $properties['background_vertical_position'];
                        // unset the property so that we do not loop throug it again
                        unset($properties['background_vertical_position']);
                    }
                    // if there is a background repeat then add it to the background property
                    if ($properties['background_repeat'] != '') {
                        $output_background .= ' ' . str_replace('_', '-', $properties['background_repeat']);
                        // unset the property so that we do not loop throug it again
                        unset($properties['background_repeat']);
                    }
                    // if there are properties, then output them
                    if ($output_background != '') {
                        //  if (($object == 'primary_buttons') || ($object == 'secondary_buttons')) {
                        //      $output .= 'background:' . $output_background . ' !important;' . "\r\n";
                        //  } else {
                        $output .= 'background:' . $output_background . $make_important . ';' . "\r\n";
                        //  }
                    }
                    break;
                case 'borders_toggle':
                case 'border_size':
                case 'border_style':
                case 'border_color':
                case 'border_position':
                    $output_border = '';
                    // if the border size is not blank, then add it and remove the property from the array
                    if ($properties['border_size'] != '') {
                        $output_border .= ' ' . $properties['border_size'] . 'px';
                        unset($properties['border_size']);
                    }
                    // if the border color is not blank, then add it and remove the property from the array
                    if ($properties['border_color'] != '') {
                        $output_border .= ' #' . $properties['border_color'];
                        unset($properties['border_color']);
                    }
                    // if there are properties then determine and add position
                    if ($output_border != '') {
                        // add border style
                        $output_border .= ' ' . $properties['border_style'];
                        // if the position is not all, then output the appropriate border property
                        if ($properties['border_position'] != 'all') {
                            switch ($properties['border_position']) {
                                case 'top':
                                    $output_border = 'border-top:' . $output_border;
                                    break;
                                case 'right':
                                    $output_border = 'border-right:' . $output_border;
                                    break;
                                case 'bottom':
                                    $output_border = 'border-bottom:' . $output_border;
                                    break;
                                case 'left':
                                    $output_border = 'border-left:' . $output_border;
                                    break;
                            }
                            // else output the general border property
                        } else {
                            $output_border = 'border:' . $output_border;
                        }
                        // output borders if enabled
                        if ($properties['borders_toggle'] == 1) {
                            $output .= $output_border . $make_important . ';' . "\r\n";
                        }
                    }
                    // unset properties
                    unset($properties['border_style']);
                    unset($properties['border_position']);
                    break;
                case 'font_color':
                    if ($value != '') {
                        //    if (($object == 'primary_buttons') || ($object == 'secondary_buttons')) {
                        //       $output .= 'color: #' . $value . ' !important;' . "\r\n";
                        //   } else {
                        $output .= 'color: #' . $value . $make_important . ';' . "\r\n";
                        //   }
                    }
                    break;
                case 'font_family':
                    if ($value != '') {
                        // check for google font and remove leading font (used only to identify a google font)
                        if (mb_substr($value, 0, 2) == '\'-') {
                            $output .= 'font-family: ' . mb_substr($value, mb_strpos($value, ',', 2) + 1) . $make_important . ';' . "\r\n";
                        } else {
                            // else use total font stack
                            $output .= 'font-family: ' . $value . $make_important . ';' . "\r\n";
                        }
                    }
                    break;
                case 'rounded_corners_toggle':
                case 'rounded_corner_top_left':
                case 'rounded_corner_top_right':
                case 'rounded_corner_bottom_left':
                case 'rounded_corner_bottom_right':
                    // Do nothing. We will handle these below.
                    break;
                case 'shadows_toggle':
                case 'shadow_horizontal_offset':
                case 'shadow_vertical_offset':
                case 'shadow_blur_radius':
                case 'shadow_color':
                    // Do nothing. We will handle these below.
                    break;
                case 'margin_top':
                case 'margin_right':
                case 'margin_bottom':
                case 'margin_left':
                    // if the margin top is not blank, then add it and remove the property from the array
                    if ($properties['margin_top'] != '') {
                        $output .= 'margin-top: ' . $properties['margin_top'] . $make_important . ';' . "\r\n";
                        unset($properties['margin_top']);
                    }
                    // if there is a right margin, and if the position is not the center, then output the margin right property
                    if (($property == 'margin_right') && ($properties['margin_right'] != '') && ($properties['position'] != 'center')) {
                        $output .= 'margin-right: ' . $properties['margin_right'] . $make_important . ';' . "\r\n";
                        unset($properties['margin_right']);
                    }
                    // if the margin bottom is not blank, then add it and remove the property from the array
                    if ($properties['margin_bottom'] != '') {
                        $output .= 'margin-bottom: ' . $properties['margin_bottom'] . $make_important . ';' . "\r\n";
                        unset($properties['margin_bottom']);
                    }
                    // if there is a left margin, and if the position is not the center, then output the margin left property
                    if (($property == 'margin_left') && ($properties['margin_left'] != '') && ($properties['position'] != 'center')) {
                        $output .= 'margin-left: ' . $properties['margin_left'] . $make_important . ';' . "\r\n";
                        unset($properties['margin_left']);
                    }
                    break;
                case 'padding_top':
                case 'padding_right':
                case 'padding_bottom':
                case 'padding_left':
                    // if the padding is not blank, output and add !important to override style designer default padding, then remove from array
                    if ($properties['padding_top'] != '') {
                        $output .= 'padding-top: ' . $properties['padding_top'] . ' !important;' . "\r\n";
                        unset($properties['padding_top']);
                    }
                    if ($properties['padding_right'] != '') {
                        $output .= 'padding-right: ' . $properties['padding_right'] . ' !important;' . "\r\n";
                        unset($properties['padding_right']);
                    }
                    if ($properties['padding_bottom'] != '') {
                        $output .= 'padding-bottom: ' . $properties['padding_bottom'] . ' !important;' . "\r\n";
                        unset($properties['padding_bottom']);
                    }
                    if ($properties['padding_left'] != '') {
                        $output .= 'padding-left: ' . $properties['padding_left'] . ' !important;' . "\r\n";
                        unset($properties['padding_left']);
                    }
                    break;
                case 'position':
                    // if the postion has a value then set a float property or margins
                    if ($value != '') {
                        if ($value != 'center') {
                            $output .= 'float: ' . $value . ';' . "\r\n";
                            // else the position is set to center
                        } else {
                            $output .= 'margin-left: auto;' . "\r\n" . 'margin-right: auto;' . "\r\n";
                        }
                    }
                    break;
                // if width has a value, output and add !important to override style designer default padding
                case 'width':
                    if ($value != '') {
                        $output .= 'width: ' . $value . ' !important;' . "\r\n";
                    }
                    break;
                default:
                    if ($value != '') {
                        $output .= str_replace('_', '-', $property) . ': ' . $value . $make_important . ';' . "\r\n";
                    }
                    break;
            }
        }
        // if this is the primary buttons or secondary buttons object or their hover equivalents, then force values for rounded corners,
        // even if there are no properties because we need to override the rounded corners for form fields
        if (($object == 'primary_buttons') || ($object == 'primary_buttons_hover') || ($object == 'secondary_buttons') || ($object == 'secondary_buttons_hover')) {
            $properties['rounded_corners_toggle'] = 1;
            // if the top left property is blank, then set it to 0
            if ($properties['rounded_corner_top_left'] == '') {
                $properties['rounded_corner_top_left'] = 0;
            }
            // if the top right property is blank, then set it to 0
            if ($properties['rounded_corner_top_right'] == '') {
                $properties['rounded_corner_top_right'] = 0;
            }
            // if the bottom left property is blank, then set it to 0
            if ($properties['rounded_corner_bottom_left'] == '') {
                $properties['rounded_corner_bottom_left'] = 0;
            }
            // if the bottom right property is blank, then set it to 0
            if ($properties['rounded_corner_bottom_right'] == '') {
                $properties['rounded_corner_bottom_right'] = 0;
            }
        }
        // if rounded corners are enabled then output CSS for them
        if ($properties['rounded_corners_toggle'] == 1) {
            // if there is a top left corner radius then output CSS for it
            if ($properties['rounded_corner_top_left'] !== '') {
                $output .= '-moz-border-radius-topleft: ' . $properties['rounded_corner_top_left'] . 'px' . $make_important . ';' . "\r\n" . '-webkit-border-top-left-radius: ' . $properties['rounded_corner_top_left'] . 'px' . $make_important . ';' . "\r\n" . 'border-top-left-radius: ' . $properties['rounded_corner_top_left'] . 'px' . $make_important . ';' . "\r\n";
            }
            // if there is a top right corner radius, then output CSS for it
            if ($properties['rounded_corner_top_right'] !== '') {
                $output .= '-moz-border-radius-topright: ' . $properties['rounded_corner_top_right'] . 'px' . $make_important . ';' . "\r\n" . '-webkit-border-top-right-radius: ' . $properties['rounded_corner_top_right'] . 'px' . $make_important . ';' . "\r\n" . 'border-top-right-radius: ' . $properties['rounded_corner_top_right'] . 'px' . $make_important . ';' . "\r\n";
            }
            // if there is a bottom left corner radius, then output CSS for it
            if ($properties['rounded_corner_bottom_left'] !== '') {
                $output .= '-moz-border-radius-bottomleft: ' . $properties['rounded_corner_bottom_left'] . 'px' . $make_important . ';' . "\r\n" . '-webkit-border-bottom-left-radius: ' . $properties['rounded_corner_bottom_left'] . 'px' . $make_important . ';' . "\r\n" . 'border-bottom-left-radius: ' . $properties['rounded_corner_bottom_left'] . 'px' . $make_important . ';' . "\r\n";
            }
            // if there is a bottom right corner radius, then output CSS for it
            if ($properties['rounded_corner_bottom_right'] !== '') {
                $output .= '-moz-border-radius-bottomright: ' . $properties['rounded_corner_bottom_right'] . 'px' . $make_important . ';' . "\r\n" . '-webkit-border-bottom-right-radius: ' . $properties['rounded_corner_bottom_right'] . 'px' . $make_important . ';' . "\r\n" . 'border-bottom-right-radius: ' . $properties['rounded_corner_bottom_right'] . 'px' . $make_important . ';' . "\r\n";
            }
        }
        // If shadows are enabled then output CSS for them.
        if ($properties['shadows_toggle'] == 1) {
            // If the horizontal offset is blank, then set it to 0.
            if ($properties['shadow_horizontal_offset'] == '') {
                $properties['shadow_horizontal_offset'] = 0;
            }
            // If the veritical offset is blank, then set it to 0.
            if ($properties['shadow_vertical_offset'] == '') {
                $properties['shadow_vertical_offset'] = 0;
            }
            // Add horizontal and vertical offset values regardless of whether there is a value because those values are required.
            $output_shadow = $properties['shadow_horizontal_offset'] . 'px ' . $properties['shadow_vertical_offset'] . 'px';
            // If the shadow blur radius is not blank then add it.
            if ($properties['shadow_blur_radius'] != '') {
                $output_shadow .= ' ' . $properties['shadow_blur_radius'] . 'px';
            }
            // If the shadow color is not blank then add it.
            if ($properties['shadow_color'] != '') {
                $output_shadow .= ' #' . $properties['shadow_color'];
            }
            // Output shadow CSS properties.
            $output .= '-moz-box-shadow: ' . $output_shadow . $make_important . ';' . "\r\n" . '-webkit-box-shadow: ' . $output_shadow . $make_important . ';' . "\r\n" . 'box-shadow: ' . $output_shadow . $make_important . ';' . "\r\n";
        }
    }
    return $output;
}
// Gets the sequence of the given menu
function get_menu_sequence($menu_id, $parent_id = 0, $menu_sequence = array())
{
    global $page_names_in_menu_sequence;
    // if this is the first time we are running this function, then set the page names in menu sequence array
    if (is_array($page_names_in_menu_sequence) == false) {
        $page_names_in_menu_sequence = array();
    }
    $menu_items = array();
    // get menu items
    $query = "SELECT 

            menu_items.id,

            menu_items.name,

            page.page_name as link_page_name

        FROM menu_items

        LEFT JOIN page ON page.page_id = menu_items.link_page_id

        WHERE 

           (menu_items.parent_id = '" . escape($parent_id) . "') 

           AND (menu_items.menu_id = '" . escape($menu_id) . "') 

        ORDER BY menu_items.sort_order";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        $menu_items[] = $row;
    }
    // loop through all menu items in order to get child menu items, and add to the array
    foreach ($menu_items as $menu_item) {
        // if this item has a link page name, and if it does not link to a page that is already in the sequence, then add menu item to array
        if (($menu_item['link_page_name'] != '') && (in_array($menu_item['link_page_name'], $page_names_in_menu_sequence) == false)) {
            // add the item's link page name to the array
            $page_names_in_menu_sequence[] = $menu_item['link_page_name'];
            // add the menu item to the array
            $menu_sequence[] = array(
                'name' => $menu_item['name'],
                'link_page_name' => $menu_item['link_page_name']
            );
        }
        // find out if sub menu exists
        $query = "SELECT COUNT(*) FROM menu_items WHERE parent_id = '" . $menu_item['id'] . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        $row = mysqli_fetch_row($result);
        // if sub menu exists, then add sub menu content to the array
        if ($row[0] > 0) {
            $menu_sequence = get_menu_sequence($menu_id, $menu_item['id'], $menu_sequence);
        }
    }
    return $menu_sequence;
}
