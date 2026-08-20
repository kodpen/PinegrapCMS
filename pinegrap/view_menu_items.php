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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'user');

// This screen always works on one menu, so without an id there is nothing to show.
if ((isset($_GET['id']) == false) || ($_GET['id'] == '')) {
    output_error(lang('Sorry, the page could not be found.'), 404);
}

// get menu name
$query = "SELECT name FROM menus WHERE id = '" . escape($_GET['id']) . "'";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);

// Set Variables
$menu_name = $row['name'];
$output_rows = '';

$user_editable_menus = ($user['role'] == 3) ? get_items_user_can_edit('menus', $user['id']) : array();

// if user has a user role and if they do not have access to this menu, output error
if (($user['role'] == 3) && !in_array($_GET['id'], $user_editable_menus)) {
    log_activity(lang(array('string'=>'access denied because user does not have access to edit menu ({var:1})','vars'=>$menu_name)), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

$number_of_results = 0;

if ($user['role'] < 2) {
    $output_subheading = '<p>' . lang('Page Style Body Tag') . ': <strong>' . h('<menu>' . $menu_name . '</menu>') . '</strong></p>';
}

$back_button_url = escape_url(($_GET['send_to'] ?? ''));
// if the user came from the pages tab, then prepare back button label
if (($_GET['from'] ?? '') == 'pages') {
    $output_back_button_label = lang('Back to Page');
    
// else if the user came from the welcome screen, then prepare different back button label
} else if (($_GET['from'] ?? '') == 'welcome') {
    $output_back_button_label = lang('Back to Welcome');
    //we move welcome screen content to widget_api for some reasons and send_to url send from widget_api 
    // so we replace it.
    $back_button_url = 'welcome.php';
    
// else the user came from the design tab, so prepare different back button label
} else {
    $output_back_button_label = lang('Back to Menus');
}

// Build nested sortable HTML for drag-drop reordering.
// Fetches all items in one query and groups by parent_id to avoid N+1 queries.
function build_sortable_html($menu_id, $grouped_items = null, $parent_id = 0, $level = 0)
{
    if ($grouped_items === null) {
        $query = "SELECT id, name, parent_id FROM menu_items WHERE menu_id = '" . escape($menu_id) . "' ORDER BY sort_order";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $grouped_items = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $grouped_items[$row['parent_id']][] = $row;
        }
    }
    if (empty($grouped_items[$parent_id])) {
        return '';
    }
    $indent_px = $level * 20;
    $html = '<ul class="sort-group list-unstyled mb-0" data-parent-id="' . h($parent_id) . '">';
    foreach ($grouped_items[$parent_id] as $item) {
        $html .= '<li class="sort-item" data-id="' . h($item['id']) . '">';
        $html .= '<div class="d-flex align-items-center py-2 px-2 border rounded mb-1 bg-body" style="margin-left:' . $indent_px . 'px; cursor:default">';
        $html .= '<i class="bi bi-grip-vertical me-2 text-muted sort-drag-handle" style="cursor:grab; font-size:1.1rem"></i>';
        $html .= '<span>' . h($item['name']) . '</span>';
        $html .= '</div>';
        $html .= build_sortable_html($menu_id, $grouped_items, $item['id'], $level + 1);
        $html .= '</li>';
    }
    $html .= '</ul>';
    return $html;
}

// function for getting all menu items
function get_menu_items($menu_id, $parent_id = 0, $level = 1)
{
    $menu_items = array();
    
    // get menu items
    $query = 
        "SELECT
            menu_items.id,
            menu_items.name,
            menu_items.link_page_id,
            menu_items.link_url,
            menu_items.security,
            menu_items.created_user_id,
            user.user_username as created_username,
            menu_items.created_timestamp,
            user2.user_username as last_modified_username,
            menu_items.last_modified_timestamp
         FROM menu_items
         LEFT JOIN user ON menu_items.created_user_id = user.user_id
         LEFT JOIN user as user2 ON menu_items.last_modified_user_id = user2.user_id
         WHERE
            (menu_id = '" . escape($menu_id) . "')
            AND (parent_id = '" . escape($parent_id) . "')
         ORDER BY menu_items.sort_order";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    $current_menu_items = array();
    
    // loop through menu items in order to build array
    while ($row = mysqli_fetch_assoc($result)) {
        $current_menu_items[] = $row;
    }
    
    // loop through menu items in order to build array of menu items
    foreach ($current_menu_items as $menu_item) {
        $menu_item['level'] = $level;
        
        $menu_items[] = $menu_item;
        
        // determine if there is a sub menu
        $query = "SELECT COUNT(*) FROM menu_items WHERE parent_id = '" . $menu_item['id'] . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        
        // if there is a sub menu, then get menu items for sub menu
        if ($row[0] > 0) {
            // get menu items for sub menu
            $sub_menu_items = get_menu_items($menu_id, $menu_item['id'], $level + 1);
            
            // add sub menu items to menu items array
            $menu_items = array_merge($menu_items, $sub_menu_items);
        }
    }
    
    return $menu_items;
}

// Get menu items
$menu_items = get_menu_items($_GET['id']);

// Loop through each menu item
foreach ($menu_items as $menu_item) {
    $indentation_padding = 20 * ($menu_item['level'] - 1);
    
    // Add link url for onclick event.
    $output_link_url = 'edit_menu_item.php?id=' . $menu_item['id'] . '&from=' . h(escape_javascript(urlencode(($_GET['from'] ?? '')))) . '&send_to=' . h(escape_javascript(urlencode(($_GET['send_to'] ?? ''))));
    
    $number_of_results++;
    
    $link_to_value = '';
    
    // if there is a link to page id, then get the page name and set it as the link to value
    if ($menu_item['link_page_id'] != 0) {
        $link_to_value = get_page_name($menu_item['link_page_id']);
        
    // else if there is a link URL, then set that to the link to value
    } elseif ($menu_item['link_url'] != '') {
        $link_to_value = $menu_item['link_url'];
    }
    
    // if the link to value is over 50 characters, then truncate the string
    if (mb_strlen($link_to_value) > 50) {
        $link_to_value = mb_substr($link_to_value, 0, 50) . '...';
    }

    $output_security_check_mark = '';

    // If security is enabled, then output check mark.
    if (
        ($menu_item['security'] == 1)
        && ($menu_item['link_page_id'] != 0)
    ) {
        $output_security_check_mark = '<span class="material-icons">task_alt</span>';
    }

    if($menu_item['created_username'] != ''){
        $output_created_user = ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($menu_item['created_username']) ) ) );
    }else{
        $output_created_user = '';
    }

    if($menu_item['last_modified_username'] != ''){
        $output_last_modifier_user = ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($menu_item['last_modified_username']) ) ) );
    }else{
        $output_last_modifier_user = '';
    }

    $output_rows .= 
        '<tr class="pointer" onclick="">
            <td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
            </td>
            <td class="align-middle chart_label" style="padding-left: ' . $indentation_padding . 'px">' . h($menu_item['name']) . '</td>
            <td class="align-middle">' . h($link_to_value) . '</td>
            <td class="align-middle">' . $output_security_check_mark . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $menu_item['created_timestamp'])) . $output_created_user . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $menu_item['last_modified_timestamp'])) . $output_last_modifier_user . '</td>
        </tr>';
}

include_once('liveform.class.php');
$liveform = new liveform('view_menu_items');

// Build sortable HTML for drag-drop reordering panel
$sortable_html = build_sortable_html($_GET['id']);

$edit_button = '';
$duplicate_button = '';
$reorder_button = '';

// If this user is an admin or designer, then show edit and duplicate buttons
if ($user['role'] < 2) {
    $edit_button = '<a class="btn btn-sm btn-primary m-1 " href="edit_menu.php?id=' . h(urlencode($_GET['id'])) . '&from=' . h(urlencode(($_GET['from'] ?? ''))) . '&send_to=' . h(urlencode(($_GET['send_to'] ?? ''))) . '" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="material-icons me-2">edit</span>' . lang(array('string'=>'Edit Menu Properties') ) . '</a>';
    $duplicate_button =
    '<div class=" btn-group btn-group-sm flex-wrap">
        <a class="btn btn-link link-secondary py-0 m-1" href="duplicate_menu.php?id=' . h(urlencode($_GET['id'])) . '&from=' . h(urlencode(($_GET['from'] ?? ''))) . '&send_to=' . h(urlencode(($_GET['send_to'] ?? ''))) . get_token_query_string_field() . '" data-loading-content="' . lang('Duplicating') . '" ><span class="material-icons me-1">control_point_duplicate</span>' . lang('Duplicate') . '</a>
    </div>';
}

// Show reorder button if user has access to edit this menu and there are items
if ($sortable_html && (($user['role'] < 3) || in_array($_GET['id'], $user_editable_menus))) {
    $reorder_button = '<button type="button" id="toggle_reorder" class="btn btn-sm btn-outline-secondary m-1"><i class="bi bi-arrow-down-up me-2"></i>' . lang('Sort Items') . '</button>';
}

echo
pg_page_shell( array('cancel'=>array('enable'=>true,'title'=>$output_back_button_label, 'onclick'=>'window.location.href=\'' . h($back_button_url) . '\'') ) ) . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="' . lang('Update, add, or remove menu items.') . '" title="' . lang('Edit Menu') . '">[' . h($menu_name) . ']</h2>
                    ' . $output_subheading . '
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1 " href="add_menu_item.php?menu_id=' . h(urlencode($_GET['id'])) . '&from=' . h(urlencode(($_GET['from'] ?? ''))) . '&send_to=' . h(urlencode(($_GET['send_to'] ?? ''))) . '" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                        ' . $edit_button . '
                        ' . $duplicate_button . '
                        ' . $reorder_button . '
                    </nav>
                </div>
            </div>
            <div id="menu_items_table_card" class="card my-4">
                <input type="hidden" name="menu_id" value="' . h($_GET['id']) . '" />
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                <th>' . lang('Name') . '</th>
                                <th>' . lang('Link') . '</th>
                                <th>' . lang('Security') . '</th>
                                <th>' . lang('Created') . '</th>
                                <th>' . lang('Last Modified') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>

            <div id="reorder_panel" class="card my-4" style="display:none">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('Sort Items') . '
                </div>
                <div class="card-body">
                    <p class="text-muted small">' . lang('Drag items to reorder or move to a different level.') . '</p>
                    <div id="sortable_tree">
                        ' . $sortable_html . '
                    </div>
                </div>
            </div>
            <nav class="buttons navigation text-center position-sticky mb-4" id="reorder_actions" style="bottom:.5rem;display:none" aria-label="reorder buttons">
                <div class="container">
                    <div class="btn-group flex-wrap justify-content-center">
                        <button type="button" id="save_sort_order" class="btn my-1 btn-success"><i class="bi bi-check-lg me-2"></i><span class="btn-text">' . lang('Save Order') . '</span></button>
                        <button type="button" id="cancel_reorder" class="btn my-1 btn-outline-secondary">' . lang('Cancel') . '</button>
                    </div>
                </div>
            </nav>
            <script>
            (function() {
                var menuId = ' . (int)$_GET['id'] . ';
                var saveUrl = \'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/api.php\';

                $(\'#toggle_reorder\').on(\'click\', function() {
                    $(\'#menu_items_table_card\').hide();
                    $(\'#reorder_panel\').show();
                    $(\'#reorder_actions\').show();
                    if (!$(\'#sortable_tree .sort-group\').hasClass(\'ui-sortable\')) {
                        initSortable();
                    }
                });

                $(\'#cancel_reorder\').on(\'click\', function() {
                    $(\'#reorder_panel\').hide();
                    $(\'#reorder_actions\').hide();
                    $(\'#menu_items_table_card\').show();
                });

                function getGroupLevel($group) {
                    return $group.parents(\'.sort-group\').length;
                }

                function updateIndentation($item, level) {
                    $item.children(\'div.d-flex\').css(\'margin-left\', (level * 20) + \'px\');
                    $item.children(\'.sort-group\').children(\'.sort-item\').each(function() {
                        updateIndentation($(this), level + 1);
                    });
                }

                function initSortable() {
                    $(\'#sortable_tree .sort-group\').sortable({
                        handle: \'.sort-drag-handle\',
                        items: \'> .sort-item\',
                        connectWith: \'.sort-group\',
                        tolerance: \'pointer\',
                        cursor: \'grabbing\',
                        revert: 150,
                        placeholder: \'sort-placeholder\',
                        receive: function(event, ui) {
                            var newLevel = getGroupLevel($(this));
                            updateIndentation(ui.item, newLevel);
                        }
                    });
                }

                $(\'#save_sort_order\').on(\'click\', function() {
                    var btn = $(this);
                    var originalHtml = btn.html();
                    btn.prop(\'disabled\', true).html(\'<span class="spinner-border spinner-border-sm me-2"></span>' . lang('Saving') . '\');

                    var groups = [];
                    $(\'#sortable_tree .sort-group\').each(function() {
                        var parentId = $(this).data(\'parent-id\');
                        var order = [];
                        $(this).children(\'.sort-item\').each(function() {
                            order.push($(this).data(\'id\'));
                        });
                        if (order.length > 0) {
                            groups.push({ parent_id: parentId, order: order });
                        }
                    });

                    $.ajax({
                        url: saveUrl,
                        type: \'POST\',
                        contentType: \'application/json\',
                        data: JSON.stringify({ action: \'sort_menu_items\', token: software_token, menu_id: menuId, groups: groups }),
                        success: function(response) {
                            if (response.status === \'success\') {
                                window.location.reload();
                            } else {
                                alert(response.message || \'' . lang('An error occurred.') . '\');
                                btn.prop(\'disabled\', false).html(originalHtml);
                            }
                        },
                        error: function() {
                            alert(\'' . lang('An error occurred.') . '\');
                            btn.prop(\'disabled\', false).html(originalHtml);
                        }
                    });
                });
            })();
            </script>
        </div>
    </div>
</main>' .
    output_footer();

$liveform->unmark_errors();
$liveform->clear_notices();