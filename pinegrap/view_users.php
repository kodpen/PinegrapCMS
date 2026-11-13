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

// A cell of the list changed in place (pg_list_edit_request() in
// includes/fn/output.php). Answered in JSON, before anything below draws the
// screen; the checks and the rights are the user screen's
// (pg_user_quick_update()). The fields depend on the editor's role and the
// view, so they are read for the signed-in user; without one the request is
// refused below.
if (pg_list_edit_requested()) {
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_list.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_list_edit.php');

    $quick_user = (defined('USER_LOGGED_IN') && USER_LOGGED_IN) ? validate_user() : null;
    $quick_filter = pg_user_list_filter((isset($_GET['filter']) && is_string($_GET['filter'])) ? $_GET['filter'] : 'default');

    $edit = pg_list_edit_request(pg_user_quick_edit_columns_for($quick_user, $quick_filter), function ($user) {
        // The list's own gate (pg_user_list_source()); each write checks
        // the user's role against the editor's.
        return isset($user['role']) && is_numeric($user['role']) && ((int) $user['role'] <= 2);
    });

    $saved = pg_user_quick_update($edit['user'], $edit['id'], $edit['field'], $edit['value'], $quick_filter);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

$liveform = new liveform('view_users');

$user = validate_user();
validate_area_access($user, 'manager');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_list.php');

// One filter per view, from the address. A name the list does not know lists
// like All My Users (pg_user_list_filter()).
$filter = pg_user_list_filter($_GET['filter'] ?? 'default');
$view = pg_user_list_view($user, $filter);
$sort_columns = $view['sort_columns'];
$sort_default = $view['sort_default'];
$sort_levels = $view['sort_levels'];
$heading = $view['heading'];
$subheading = $view['subheading'];

// The list table driver prints the first rows; the next ones come from its
// list_table_rows action (includes/panel/lists.php) as the list scrolls.
$list_source = pg_user_list_source($user, array('filter' => $filter));

if ($list_source === null) {
    output_error(lang('Access denied'));
}

$list_page = pg_list_table_page($list_source, array(), null);

if ($list_page === false) {
    output_error(lang('Query failed.'));
}

// Delete Selected sits in the list's selection bar, which shows while rows
// are selected, and posts the selection to delete_users.php. That screen
// reads it with the list's own query and rights, and never deletes the
// last administrator.
$output_bulk_buttons = '<button type="button" value="Delete Selected" class="btn btn-sm btn-solid theme-danger" data-confirm-content="' . h(lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('users'))))) . '"><i class="bi bi-trash me-1" aria-hidden="true"></i>' . lang('Delete Selected') . '</button>';

echo
    pg_page_shell(
        array(
            'title'=> lang($heading),
            'extra classes'=>'users pg-measure-list',
            'icon'=>'account',
            'heading'=>lang($heading),
            'heading_description' => ($subheading ?? lang('Panel users and what they can access')),
        )
    ). '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '

                <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . h(lang('Button Bar')) . '">
                            <a class="btn btn-sm btn-solid theme-primary m-1 " href="add_user.php?send_to=' . h(urlencode(REQUEST_URL)) . '"><span class="bi bi-plus-circle me-3"></span>' . lang(array('string'=>'Create') ) . '</a>
                            <a class="btn btn-sm btn-link pg-link-secondary py-0 m-1" href="import_users.php?send_to=' . h(urlencode(REQUEST_URL)) . '"><span class="bi bi-box-arrow-in-right me-1"></span>' . lang(array('string'=>'Import') ) . '</a>
                            <div class="pg-toolbar-grow"></div>
                            <label class="form-control form-control-sm form-adorn rounded-pill pg-toolbar-search">
                                <span class="form-adorn-text" aria-hidden="true"><i class="bi bi-search"></i></span>
                                <input type="search" class="form-ghost" data-pg-list-search="view_users" placeholder="' . h(lang('Search')) . '" aria-label="' . h(lang('Search')) . '">
                            </label>
                            ' . pg_list_sort_menu('view_users.php', $sort_columns, $sort_levels, $sort_default, array('filter' => $filter)) . '
                            <form id="search_form" action="view_users.php" method="get" class="search_form">
                                <div class="input-group input-group-sm">
                                    <label class="input-group-text material-icons" title="' . lang('Content that viewed') . '" for="filter_select">visibility</label>
                                    <select id="filter_select" aria-label="' . h(lang('Content that viewed')) . '" name="filter" class="form-control" title="' . lang('Content that viewed') . '" onchange="submit_form(\'search_form\')">' . get_filter_options($view['filters'], $filter) . '</select>
                                </div>
                            </form>
                </nav>
                <form name="form" action="delete_users.php" method="post" class="disable_shortcut">
                    ' . get_token_field() . '
                    <input type="hidden" name="selection" />
                    <input type="hidden" name="list_params" value="' . h(encode_json(array('filter' => $filter))) . '" />
                    <input type="hidden" name="send_to" value="' . h(REQUEST_URL) . '" />
                    ' . pg_list_table_html($list_source, $list_page, lang($heading), $output_bulk_buttons) . '
                </form>
            </div>
        </div>
</main>
<script>
    // Delete Selected posts what the list has selected (delete_users.php
    // resolves it with the list\'s own query and the user\'s rights).
    (function () {
        var root = document.querySelector(\'.pg-list-table[data-pg-list-table="view_users"]\');

        if (root) {
            root.addEventListener(\'pg-list-table:selection\', function (event) {
                document.form.selection.value = JSON.stringify(event.detail.payload);
            });
        }
    }());
</script>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' .
    output_footer();

$liveform->remove_form('view_users');
