<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The menu item form, shared by the create and the edit screens
 * (add_menu_item.php, edit_menu_item.php): the Options card, the page or
 * URL a menu item links to, the sort order of a new last item and the
 * menu's last modified stamp. The screens keep what differs: the edit
 * screen loads the record, offers the position and delete, and reorders
 * the level it is saved on.
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
 * The Options card: name, link target, parent, the page or URL the item
 * links to, its classes, and what the screen adds after them.
 *
 * @param liveform $liveform
 * @param string $parent_id_options  <option> markup of get_menu_item_options()
 * @param string $extra  Fields printed after the classes (the edit screen's position)
 * @return string
 */
function pg_menu_item_form_card($liveform, $parent_id_options, $extra = '')
{
    $link_target_options =
        array(
            lang('Same Window')=>'Same Window',
            lang('New Window')=>'New Window');

    return '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Options') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-6 lg:col-4 my-3">
                                            <label for="name" class="form-label">' . lang('Menu Item Name') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>'100')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 md:col-6 lg:col-4 my-3">
                                            <label for="link_target" class="form-label">' . lang('Link Target') . '</label>
                                            ' . $liveform->output_field(array('type'=>'select', 'name'=>'link_target', 'id'=>'link_target', 'class'=>'form-control', 'options'=>$link_target_options)) . '
                                        </div>
                                        <div class="col-12 md:col-6 lg:col-4 my-3">
                                            <label for="parent_id" class="form-label">' . lang('Parent Menu Item') . '</label>
                                            <select name="parent_id" id="parent_id" class="form-control">' . $parent_id_options . '</select>
                                        </div>
                                        <div class="col-12 mt-7"><h3 class="h5">' . lang('Link Menu Item To') . '</h3></div>
                                        <div class="col-12 md:col-12 lg:col-5 my-3">
                                            <label for="link_page_id" class="form-label">' . lang('Page') . '</label>
                                            <div class="input-group flex-wrap">
                                                ' . $liveform->output_field(array('type'=>'select', 'name'=>'link_page_id', 'id'=>'link_page_id', 'class'=>'form-control', 'style'=>'min-width:100px', 'options'=>get_page_options(), 'onclick'=>'if (this.selectedIndex != 0) {document.getElementById(\'link_url\').value = \'\'}')) . '
                                                <div class="input-group-text">
                                                    <div class="form-field">
                                                        <div class="switch">' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'security', 'id'=>'security', 'value'=>'1', 'class'=>'no-bs', 'aria-describedby'=>'security_hint')) . '</div>
                                                        <label class="" for="security">' . lang('Enable Security') . '</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-text" id="security_hint">' . lang('only show Menu Item if Visitor has access to view Page') . '</div>
                                        </div>
                                        <div class="col-12 md:col-12 lg:col-2  my-3 lg:text-center">
                                            <label class="form-label">-' . lang('or') . '-</label>
                                        </div>
                                        <div class="col-12 md:col-6 lg:col-5 my-3">
                                            <label for="link_url" class="form-label">' . lang('URL') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'link_url', 'id'=>'link_url', 'class'=>'form-control', 'maxlength'=>'255')) . '
                                        </div>
                                        <div class="col-12 mt-5 mb-3">
                                            <label for="item_class" class="form-label">' . lang('Classes') . '</label>
                                            <div class="input-group input-group-sm d-flex flex-wrap border rounded-4 border-2">
                                                <div class="input-group-text bg-reset border-0">&lt;li class="</div>
                                                ' . $liveform->output_field(array(
                                                    'type' => 'text',
                                                    'name' => 'class',
                                                    'id' => 'item_class',
                                                    'class' => 'form-control tagin border-0 min-width-tagin',
                                                    'data-placeholder' => lang('Add classes'),
                                                    'data-pg-chips' => '',
                                                    'data-pg-separator' => ' ',
                                                    'maxlength' => '255')) . pg_fields_script() . '
                                                <div class="input-group-text bg-reset border-0">"&gt;</div>
                                            </div>
                                            <div class="form-text text-end">' . lang('One or More Custom Classes for the Menu Item. Separate classes with a space') . '</div>
                                        </div>
                                        ' . $extra . '
                                        <script>
                                            $("#link_url").bind("input", function(){
                                                if ($("#link_url").val() != "") {
                                                    document.getElementById(\'link_page_id\').selectedIndex = 0
                                                }
                                            });
                                        </script>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
}

/**
 * What a posted menu item links to: a chosen page wins and blanks the URL,
 * otherwise the URL is kept.
 *
 * @param array $post
 * @return array  'link_page_id', 'link_url'
 */
function pg_menu_item_form_link($post)
{
    if (!empty($post['link_page_id'])) {
        return array('link_page_id' => $post['link_page_id'], 'link_url' => '');
    }

    return array('link_page_id' => '', 'link_url' => $post['link_url'] ?? '');
}

/**
 * The sort order that puts an item last on its level.
 *
 * @param int|string $menu_id
 * @param int|string $parent_id
 * @return int
 */
function pg_menu_item_form_last_sort_order($menu_id, $parent_id)
{
    $query = "SELECT sort_order
             FROM menu_items
             WHERE
                 (menu_id = ?)
                 AND (parent_id = ?)
             ORDER BY sort_order DESC
             LIMIT 1";
    $result = db_q($query, array((string) $menu_id, (string) $parent_id)) or output_error('Query failed.');

    $row = mysqli_fetch_assoc($result);

    // An empty level starts at 1.
    return (int) ($row['sort_order'] ?? 0) + 1;
}

/**
 * Stamps the menu as modified now by the user.
 *
 * @param int|string $menu_id
 * @param int|string $user_id
 */
function pg_menu_item_form_touch_menu($menu_id, $user_id)
{
    $query =
        "UPDATE menus
        SET
            last_modified_user_id = ?,
            last_modified_timestamp = UNIX_TIMESTAMP()
        WHERE id = ?";
    db_q($query, array((string) $user_id, (string) $menu_id)) or output_error('Query failed.');
}
