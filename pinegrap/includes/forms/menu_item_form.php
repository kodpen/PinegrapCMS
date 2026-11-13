<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The menu item form, shared by the create and the edit screens
 * (add_menu_item.php, edit_menu_item.php): the form and status columns
 * with the save bar, the page or URL a menu item links to and the
 * addresses it refuses, the sort order of a new last item and the
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
 * The form column (Menu Item, Link Menu Item To and Classes cards) and the
 * sticky status column (a Summary of the menu, what the item links to and
 * where it opens, plus the screen's own rows), in the edit screen layout of
 * product_builder.php. Below lg the status column follows the form in
 * source order.
 *
 * @param liveform $liveform
 * @param array $form  'parent_id_options' (<option> markup of
 *                     get_menu_item_options()), 'extra' (fields printed
 *                     after the parent: the edit screen's position),
 *                     'menu_name',
 *                     'menu_url' (the menu items screen, escaped for HTML),
 *                     'status' (extra Summary rows, HTML)
 * @return string
 */
function pg_menu_item_form_cards($liveform, $form = array())
{
    $form = array_merge(array('parent_id_options' => '', 'extra' => '', 'menu_name' => '', 'menu_url' => '', 'status' => ''), $form);

    $link_target_options =
        array(
            lang('Same Window')=>'Same Window',
            lang('New Window')=>'New Window');

    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Menu Item') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang('Menu Item Name') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>'100', 'required'=>'required')) . '
                                        </div>
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="link_target" class="form-label">' . lang('Link Target') . '</label>
                                            ' . $liveform->output_field(array('type'=>'select', 'name'=>'link_target', 'id'=>'link_target', 'class'=>'form-control', 'options'=>$link_target_options)) . '
                                        </div>
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="parent_id" class="form-label">' . lang('Parent Menu Item') . '</label>
                                            <select name="parent_id" id="parent_id" class="form-control">' . $form['parent_id_options'] . '</select>
                                        </div>' . $form['extra'] . '
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Link Menu Item To') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 xl:col-5 my-3">
                                            <label for="link_page_id" class="form-label">' . lang('Page') . '</label>
                                            ' . pg_picker_field('page', 'link_page_id', $liveform->get_field_value('link_page_id')) . pg_picker_script() . '
                                            <div class="form-field mt-3">
                                                <div class="switch">' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'security', 'id'=>'security', 'value'=>'1', 'class'=>'no-bs', 'aria-describedby'=>'security_hint')) . '</div>
                                                <label class="" for="security">' . lang('Enable Security') . '</label>
                                            </div>
                                            <div class="form-text" id="security_hint">' . lang('only show Menu Item if Visitor has access to view Page') . '</div>
                                        </div>
                                        <div class="col-12 xl:col-2 my-3 xl:text-center">
                                            <span class="form-label d-block">-' . lang('or') . '-</span>
                                        </div>
                                        <div class="col-12 xl:col-5 my-3">
                                            <label for="link_url" class="form-label">' . lang('URL') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'link_url', 'id'=>'link_url', 'class'=>'form-control', 'maxlength'=>'255')) . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Classes') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 my-3">
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
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 pg-title-section">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-0">
                                            <div class="py-2 border-bottom">
                                                <dt class="fw-normal">' . lang('Menu') . '</dt>
                                                <dd class="mb-0 fw-bold text-break">' . ($form['menu_url'] !== '' ? '<a href="' . $form['menu_url'] . '">' . h($form['menu_name']) . '</a>' : h($form['menu_name'])) . '</dd>
                                            </div>
                                            <div class="py-2 border-bottom">
                                                <dt class="fw-normal">' . lang('Links To') . '</dt>
                                                <dd class="mb-0 fw-bold text-break" id="menu_item_summary_link" data-none="' . h(lang('Nothing')) . '"></dd>
                                            </div>
                                            <div class="d-flex justify-content-between py-2' . ($form['status'] !== '' ? ' border-bottom' : '') . '">
                                                <dt class="fw-normal">' . lang('Link Target') . '</dt>
                                                <dd class="mb-0 fw-bold" id="menu_item_summary_target"></dd>
                                            </div>' . $form['status'] . '
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            (function () {
                                // A chosen page blanks the URL and a typed URL resets the
                                // page: the item links to one of them.
                                function summarize() {
                                    var page = $(\'input[name="link_page_id"]\');
                                    var url = $.trim($(\'#link_url\').val());
                                    var $link = $(\'#menu_item_summary_link\');
                                    if (page.val() !== \'\') {
                                        $link.text($.trim($(\'#link_page_id\').val()));
                                    } else {
                                        $link.text(url !== \'\' ? url : $link.data(\'none\'));
                                    }
                                    var target = document.getElementById(\'link_target\');
                                    $(\'#menu_item_summary_target\').text(target.selectedIndex >= 0 ? $.trim(target.options[target.selectedIndex].text) : \'\');
                                }
                                $(document).on(\'change\', \'input[name="link_page_id"]\', function () {
                                    if (this.value !== \'\') {
                                        $(\'#link_url\').val(\'\');
                                    }
                                    summarize();
                                });
                                $(document).on(\'input\', \'#link_url\', function () {
                                    if (this.value != \'\' && window.pgPicker) {
                                        window.pgPicker.clear(document.getElementById(\'link_page_id\'));
                                    }
                                    summarize();
                                });
                                $(document).on(\'change\', \'#link_target\', summarize);
                                summarize();
                            })();
                        </script>';
}

/**
 * The Summary row of the edit screen: how many items sit directly under
 * this one (Delete removes them with it).
 *
 * @param int $child_count
 * @return string
 */
function pg_menu_item_form_children_status($child_count)
{
    return '
                                            <div class="d-flex justify-content-between py-2">
                                                <dt class="fw-normal">' . lang('Submenu Items') . '</dt>
                                                <dd class="mb-0 fw-bold" id="menu_item_summary_children">' . (int) $child_count . '</dd>
                                            </div>';
}

/**
 * The screen row: the cards (pg_menu_item_form_cards()) and the sticky save
 * bar with the given buttons.
 *
 * @param string $cards    pg_menu_item_form_cards()
 * @param string $buttons  The buttons inside the bar's group
 * @return string
 */
function pg_menu_item_form_layout($cards, $buttons)
{
    return '
                    <div class="row">' . $cards . '
                        <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                            <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                                <div class="container">
                                    <div class="btn-group flex-wrap justify-content-center">' . $buttons . '
                                    </div>
                                </div>
                            </nav>
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

    return array('link_page_id' => '', 'link_url' => trim((string) ($post['link_url'] ?? '')));
}

/**
 * Why an address cannot be a menu item's URL, or '' when it can: http,
 * https, mailto or tel, or one on this site (a path, a query or an anchor).
 * Anything a browser would run (javascript:, data:), a protocol-relative
 * address and a backslash are refused, and so are spaces and control
 * characters, which browsers skip while they read the scheme. The panel
 * screens and the external API share this rule.
 *
 * @param string $url
 * @return string  The translated message, or ''
 */
function pg_menu_item_url_problem($url)
{
    $url = trim((string) $url);

    if ($url === '') {
        return '';
    }

    if (preg_match('/[\x00-\x20\x7f]/', $url)) {
        return lang('The address cannot contain spaces or control characters.');
    }

    if (
        (preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):/', $url, $found) && !in_array(strtolower($found[1]), array('http', 'https', 'mailto', 'tel'), true))
        || strpos($url, '//') === 0
        || strpos($url, '\\') !== false
    ) {
        return lang('The address must be a web, e-mail or phone address, or a path on this site.');
    }

    return '';
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
 * The ids of a level in their new order after an item is saved on it.
 * The position is 'top' or the id of the sibling the item goes below; on a
 * new parent, or when the position names no sibling of this level, the item
 * goes last, because the position list was drawn for the level it left.
 *
 * @param array $sibling_ids  The level's other ids, in their sort order
 * @param int|string $item_id
 * @param string $position
 * @param bool $parent_changed
 * @return array
 */
function pg_menu_item_form_level_order($sibling_ids, $item_id, $position, $parent_changed)
{
    $position = (string) $position;
    $order = array();
    $placed = false;

    if (!$parent_changed && $position === 'top') {
        $order[] = $item_id;
        $placed = true;
    }

    foreach ($sibling_ids as $sibling_id) {
        $order[] = $sibling_id;

        if (!$parent_changed && !$placed && ((string) $sibling_id === $position)) {
            $order[] = $item_id;
            $placed = true;
        }
    }

    if (!$placed) {
        $order[] = $item_id;
    }

    return $order;
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

/**
 * The stored link target of a posted value: 'New Window' or, for anything
 * else, 'Same Window' (the column's two values). Pure.
 *
 * @param mixed $value
 * @return string
 */
function pg_menu_item_form_link_target($value)
{
    return ($value === 'New Window') ? 'New Window' : 'Same Window';
}

/**
 * Whether a posted parent may hold the item: 0 (top level) always; any
 * other id only when it is an item of the same menu and lies outside the
 * item's own subtree, so a save never stores a cycle (which hides the item
 * and everything below it) or a parent in another menu. Walks the parents
 * upward from the posted one, at most 1000 steps. Pure apart from $lookup.
 *
 * @param int|string $menu_id
 * @param int|string $item_id   The edited item, 0 on create
 * @param mixed $parent_id      The posted parent
 * @param callable $lookup  function (int $id): array('menu_id', 'parent_id') or null
 * @return bool
 */
function pg_menu_item_parent_allowed($menu_id, $item_id, $parent_id, $lookup)
{
    if (!is_scalar($parent_id) || (preg_match('/^\d*$/', trim((string) $parent_id)) !== 1)) {
        return false;
    }

    $parent_id = (int) $parent_id;
    $item_id = (int) $item_id;

    if ($parent_id === 0) {
        return true;
    }

    $row = $lookup($parent_id);

    if (!is_array($row) || ((int) $row['menu_id'] !== (int) $menu_id)) {
        return false;
    }

    $current = $parent_id;

    for ($steps = 0; $steps < 1000; $steps++) {
        if ($current === $item_id) {
            return false;
        }

        $current = (int) $row['parent_id'];

        if ($current === 0) {
            return true;
        }

        $row = $lookup($current);

        // A broken chain (a parent that no longer exists) cannot lead back
        // to the item.
        if (!is_array($row)) {
            return true;
        }
    }

    return false;
}

/**
 * The rows the menu screen's Save Order writes, or null when the posted
 * tree cannot be stored as it is: a group's parent is neither 0 nor an item
 * of the menu now (deleted meanwhile, or another menu's), an item is placed
 * twice, or the moves would put an item under itself or one of its own
 * sub-items. Such a parent hides the item and everything under it from the
 * site and from the menu screen. Items no longer in the menu are skipped.
 * Pure.
 *
 * @param mixed $groups    posted list of array('parent_id', 'order' => item ids)
 * @param array $parent_of item id => parent id, every item of the menu now
 * @return array|null list of array(item id, parent id, sort order)
 */
function pg_menu_item_sort_plan($groups, array $parent_of)
{
    if (!is_array($groups)) {
        return null;
    }

    $plan = array();
    $placed = array();

    foreach ($groups as $group) {
        $parent = (is_array($group) && isset($group['parent_id'])) ? $group['parent_id'] : 0;

        if (!is_scalar($parent) || (preg_match('/^\d*$/', trim((string) $parent)) !== 1)) {
            return null;
        }

        $parent = (int) $parent;

        if (($parent !== 0) && !array_key_exists($parent, $parent_of)) {
            return null;
        }

        $order = (is_array($group) && isset($group['order']) && is_array($group['order'])) ? $group['order'] : array();
        $sort_order = 1;

        foreach ($order as $item_id) {
            $item_id = is_scalar($item_id) ? (int) $item_id : 0;

            if (($item_id <= 0) || !array_key_exists($item_id, $parent_of)) {
                continue;
            }

            if (isset($placed[$item_id])) {
                return null;
            }

            $placed[$item_id] = $parent;
            $plan[] = array($item_id, $parent, $sort_order++);
        }
    }

    // Every moved item must still reach the top level; a parent outside the
    // menu (an orphan stored before) ends the walk without a cycle.
    $after = $placed + $parent_of;
    $limit = count($after) + 1;

    foreach ($placed as $item_id => $parent) {
        $at = $parent;

        for ($steps = 0; ($at !== 0) && array_key_exists($at, $after); $steps++) {
            if (($at === $item_id) || ($steps > $limit)) {
                return null;
            }

            $at = (int) $after[$at];
        }
    }

    return $plan;
}

/**
 * The database lookup of pg_menu_item_parent_allowed(): a menu item's menu
 * and parent, or null.
 *
 * @return callable
 */
function pg_menu_item_parent_lookup()
{
    return function ($id) {
        $result = db_q("SELECT menu_id, parent_id FROM menu_items WHERE id = ?", array((string) (int) $id)) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);

        return $row ? $row : null;
    };
}
