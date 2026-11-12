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
validate_area_access($user, 'user');

// This screen always works on one menu, so without an id there is nothing to show.
if ((isset($_GET['id']) == false) || ($_GET['id'] == '')) {
    output_error(lang('Sorry, the page could not be found.'), 404);
}

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM menus WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// get menu name
$query = "SELECT name FROM menus WHERE id = ?";
$result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
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

$output_subheading = '';

if ($user['role'] < 2) {
    $output_subheading = '<span class="small fg-secondary">' . lang('Page Style Body Tag') . ': <code>' . h('<menu>' . $menu_name . '</menu>') . '</code></span>';
}

// Written into the back button's onclick: the browser decodes the attribute
// before the script runs, so it is escaped for JavaScript as well as for HTML.
// Only a path on this site is followed; without one the button opens the
// menu list.
$back_button_url = pg_send_to_url(PATH . SOFTWARE_DIRECTORY . '/view_menus.php');
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
        $query = "SELECT id, name, parent_id FROM menu_items WHERE menu_id = ? ORDER BY sort_order, id";
        $result = db_q($query, array((string) $menu_id)) or output_error('Query failed.');
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
        $html .= '<div class="d-flex align-items-center py-3 px-3 border rounded-4 mb-1 bg-body" style="margin-left:' . $indent_px . 'px; cursor:default">';
        $html .= '<button type="button" class="btn btn-link p-0 me-3 fg-secondary sort-drag-handle" style="cursor:grab; font-size:1.1rem" aria-label="' . h(lang(array('string' => 'Move {var:1}', 'vars' => array($item['name'])))) . '" aria-describedby="sort_keys_hint"><i class="bi bi-grip-vertical" aria-hidden="true"></i></button>';
        // min-width lets a long name without spaces wrap inside the row.
        $html .= '<span class="text-break" style="min-width:0">' . h($item['name']) . '</span>';
        $html .= '</div>';
        $html .= build_sortable_html($menu_id, $grouped_items, $item['id'], $level + 1);
        $html .= '</li>';
    }
    $html .= '</ul>';
    return $html;
}

// Every item of the menu in one query, listed depth first with its depth
// (pg_menu_tree_flatten()); ties in sort_order list by id.
function get_menu_items($menu_id)
{
    $query = 
        "SELECT
            menu_items.id,
            menu_items.parent_id,
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
         WHERE menu_id = ?
         ORDER BY menu_items.sort_order, menu_items.id";
    $result = db_q($query, array((string) $menu_id)) or output_error('Query failed.');
    
    $rows = array();
    
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    
    return pg_menu_tree_flatten($rows);
}

// Get menu items
$menu_items = get_menu_items($_GET['id']);

// Loop through each menu item
foreach ($menu_items as $menu_item) {
    $indentation_padding = 20 * ($menu_item['level'] - 1);
    
    // The item's editor, coming back to this menu.
    $output_link_url = h('edit_menu_item.php?id=' . (int) $menu_item['id'] . '&from=' . urlencode((string) ($_GET['from'] ?? '')) . '&send_to=' . urlencode((string) ($_GET['send_to'] ?? '')));
    
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
        $output_security_check_mark = '<span class="material-icons" role="img" aria-label="' . h(lang('Yes')) . '">task_alt</span>';
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

    // The name opens the item; its indent shows the level. The tree's order
    // is the menu's order, so the table is plain: no sorting, no search.
    // Below md the two date columns step aside so the name and link keep the
    // width; an address has no spaces to wrap at, so it breaks inside its cell
    // (kept at least .pg-cell-wrap wide) instead of widening the table. What
    // still does not fit scrolls inside the card, never the whole page.
    $output_rows .=
        '<tr>
            <td class="align-middle" style="padding-left: ' . (8 + $indentation_padding) . 'px"><a href="' . $output_link_url . '">' . h($menu_item['name']) . '</a></td>
            <td class="align-middle text-break pg-cell-wrap">' . h($link_to_value) . '</td>
            <td class="align-middle">' . $output_security_check_mark . '</td>
            <td class="align-middle d-none md:d-table-cell">' . get_relative_time(array('timestamp' => $menu_item['created_timestamp'])) . $output_created_user . '</td>
            <td class="align-middle d-none md:d-table-cell">' . get_relative_time(array('timestamp' => $menu_item['last_modified_timestamp'])) . $output_last_modifier_user . '</td>
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
    $edit_button = '<a class="btn btn-sm btn-text theme-secondary" href="edit_menu.php?id=' . h(urlencode($_GET['id'])) . '&from=' . h(urlencode(($_GET['from'] ?? ''))) . '&send_to=' . h(urlencode(($_GET['send_to'] ?? ''))) . '"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . lang(array('string'=>'Edit Menu Properties') ) . '</a>';
    $duplicate_button = '<a class="btn btn-sm btn-text theme-secondary" href="duplicate_menu.php?id=' . h(urlencode($_GET['id'])) . '&from=' . h(urlencode(($_GET['from'] ?? ''))) . '&send_to=' . h(urlencode(($_GET['send_to'] ?? ''))) . get_token_query_string_field() . '"><i class="bi bi-copy me-1" aria-hidden="true"></i>' . lang('Duplicate') . '</a>';
}

// Show reorder button if user has access to edit this menu and there are items
if ($sortable_html && (($user['role'] < 3) || in_array($_GET['id'], $user_editable_menus))) {
    $reorder_button = '<button type="button" id="toggle_reorder" class="btn btn-sm btn-text theme-secondary"><i class="bi bi-arrow-down-up me-1" aria-hidden="true"></i>' . lang('Sort Items') . '</button>';
}

echo
pg_page_shell( array(
    'title' => h($menu_name) . ' | ' . lang('Edit Menu'),
    'icon' => 'design',
    'heading' => lang('Edit Menu'),
    'cancel'=>array('enable'=>true,'title'=>$output_back_button_label, 'onclick'=>'window.location.href=\'' . h(escape_javascript($back_button_url)) . '\'') ) ) . '
<main id="content" class="container-fluid">
    ' . pg_screen_toolbar(array(
        'primary'   => array('label' => lang('Create'), 'url' => 'add_menu_item.php?menu_id=' . urlencode($_GET['id']) . '&from=' . urlencode(($_GET['from'] ?? '')) . '&send_to=' . urlencode(($_GET['send_to'] ?? '')), 'icon' => 'bi-plus-circle'),
        'secondary' => array($edit_button, $duplicate_button, $reorder_button),
        'grow'      => '<span class="fw-semibold text-break me-3">' . h($menu_name) . '</span>' . $output_subheading,
    )) . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            <div id="menu_items_table_card" class="card my-7">
                <input type="hidden" name="menu_id" value="' . h($_GET['id']) . '" />
                <div class="card-body p-0 position-relative table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th scope="col">' . lang('Name') . '</th>
                                <th scope="col">' . lang('Link') . '</th>
                                <th scope="col">' . lang('Security') . '</th>
                                <th scope="col" class="d-none md:d-table-cell">' . lang('Created') . '</th>
                                <th scope="col" class="d-none md:d-table-cell">' . lang('Last Modified') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>

            <div id="reorder_panel" class="card my-7" style="display:none">
                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                    ' . lang('Sort Items') . '
                </div>
                <div class="card-body">
                    <p class="fg-secondary small">' . lang('Drag items to reorder or move to a different level.') . '</p>
                    <p id="sort_keys_hint" class="fg-secondary small">' . h(lang('With the keyboard, focus a grip: Up and Down move the item, Right moves it under the item above when that item has sub-items, Left moves it out one level.')) . '</p>
                    <div id="sortable_tree">
                        ' . $sortable_html . '
                    </div>
                </div>
            </div>
            <nav class="buttons navigation text-center position-sticky mb-7" id="reorder_actions" style="bottom:.5rem;display:none" aria-label="' . h(lang('Reorder Buttons')) . '">
                <div class="container">
                    <div class="btn-group flex-wrap justify-content-center">
                        <button type="button" id="save_sort_order" class="btn my-1 btn-solid theme-success"><i class="bi bi-check-lg me-3"></i><span class="pg-btn-label">' . lang('Save Order') . '</span></button>
                        <button type="button" id="cancel_reorder" class="btn my-1 btn-outline theme-secondary">' . lang('Cancel') . '</button>
                    </div>
                </div>
            </nav>
            <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/sortablejs/Sortable.min.js"></script>
            <script>
            (function() {
                var menuId = ' . (int)$_GET['id'] . ';
                var saveUrl = \'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/api.php\';
                var sortableReady = false;
                var sortables = [];
                // The tree as drawn, put back on Cancel.
                var savedTree = $(\'#sortable_tree\').html();

                $(\'#toggle_reorder\').on(\'click\', function() {
                    $(\'#menu_items_table_card\').hide();
                    $(\'#reorder_panel\').show();
                    $(\'#reorder_actions\').show();
                    if (!sortableReady) {
                        initSortable();
                    }
                });

                // Cancel drops the moves not saved: the tree is drawn again and
                // its lists are set up anew on the next Sort Items.
                $(\'#cancel_reorder\').on(\'click\', function() {
                    $.each(sortables, function(i, sortable) {
                        sortable.destroy();
                    });
                    sortables = [];
                    sortableReady = false;
                    $(\'#sortable_tree\').html(savedTree);
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

                // SortableJS (assets/lib/sortablejs/): every level is one list in
                // a shared group, so an item can move to another level; the
                // moved item and its children are indented for the new level.
                function initSortable() {
                    sortableReady = true;
                    $(\'#sortable_tree .sort-group\').each(function() {
                        sortables.push(Sortable.create(this, {
                            group: \'menu-items\',
                            handle: \'.sort-drag-handle\',
                            draggable: \'.sort-item\',
                            ghostClass: \'sort-placeholder\',
                            animation: 150,
                            fallbackOnBody: true,
                            swapThreshold: 0.65,
                            onAdd: function(evt) {
                                updateIndentation($(evt.item), getGroupLevel($(evt.to)));
                            }
                        }));
                    });
                }

                // The keyboard path: Up and Down swap an item with its sibling;
                // Right moves it to the end of the sub-items of the item above
                // and Left moves it out after its parent, only into groups the
                // tree already has. Save Order reads the tree as after a drag.
                $(\'#sortable_tree\').on(\'keydown\', \'.sort-drag-handle\', function(event) {
                    var $item = $(this).closest(\'.sort-item\');
                    var $target = null;

                    if (event.key === \'ArrowUp\' || event.key === \'ArrowDown\') {
                        var $other = event.key === \'ArrowUp\' ? $item.prev(\'.sort-item\') : $item.next(\'.sort-item\');
                        if ($other.length) {
                            if (event.key === \'ArrowUp\') {
                                $item.insertBefore($other);
                            } else {
                                $item.insertAfter($other);
                            }
                        }
                    } else if (event.key === \'ArrowRight\') {
                        $target = $item.prev(\'.sort-item\').children(\'.sort-group\');
                        if ($target.length) {
                            $target.append($item);
                        }
                    } else if (event.key === \'ArrowLeft\') {
                        var $parent = $item.parent().closest(\'.sort-item\');
                        if ($parent.length) {
                            $target = $parent.parent();
                            $item.insertAfter($parent);
                        }
                    } else {
                        return;
                    }

                    event.preventDefault();

                    if ($target && $target.length) {
                        updateIndentation($item, getGroupLevel($item.parent()));
                    }

                    this.focus();

                    var $siblings = $item.parent().children(\'.sort-item\');
                    if (typeof window.pgAnnounce === \'function\') {
                        window.pgAnnounce(' . str_replace('</', '<\/', encode_json(lang('Position {position} of {total}'))) . '
                            .replace(\'{position}\', $siblings.index($item) + 1)
                            .replace(\'{total}\', $siblings.length));
                    }
                });

                $(\'#save_sort_order\').on(\'click\', function() {
                    var btn = $(this);
                    btn.prop(\'disabled\', true);

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
                                btn.prop(\'disabled\', false);
                            }
                        },
                        error: function() {
                            alert(\'' . lang('An error occurred.') . '\');
                            btn.prop(\'disabled\', false);
                        }
                    });
                });
            })();
            </script>
        </div>
    </div>
</main>
' .
    output_footer();

$liveform->unmark_errors();
$liveform->clear_notices();