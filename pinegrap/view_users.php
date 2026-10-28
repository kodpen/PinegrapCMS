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

// The rows come one page at a time from the user_list_rows action
// (includes/panel/lists.php); the table carries what it asks with.
$output_list_params = h(encode_json(array('filter' => $filter)));

echo
    pg_page_shell(
        array(
            'title'=> lang($heading),
            'extra classes'=>'users',
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
                            <a class="btn btn-sm btn-solid theme-primary m-1 " href="add_user.php?send_to=' . h(REQUEST_URL) . '"><span class="bi bi-plus-circle me-3"></span>' . lang(array('string'=>'Create') ) . '</a>
                            <form id="export_form" class="disable_shortcut d-inline-block" method="get">
                                <div class=" btn-group btn-group-sm flex-wrap">
                                    <a class="btn btn-link pg-link-secondary py-0 m-1" href="import_users.php?send_to=' . h(REQUEST_URL) . '"><span class="bi bi-box-arrow-in-right me-1"></span>' . lang(array('string'=>'Import') ) . '</a>
                                </div>
                            </form>
                            <div class="pg-toolbar-grow"></div>
                            ' . pg_list_sort_menu('view_users.php', $sort_columns, $sort_levels, $sort_default, array('filter' => $filter)) . '
                            <form id="search_form" action="view_users.php" method="get" class="search_form">
                                <input type="hidden" name="filter" value="' . h($filter) . '">
                                <div class="input-group input-group-sm">
                                    <label class="input-group-text material-icons" title="' . lang('Content that viewed') . '" for="filter_select">visibility</label>
                                    <select id="filter_select" aria-label="' . h(lang('Content that viewed')) . '" name="filter" class="form-control" title="' . lang('Content that viewed') . '" onchange="submit_form(\'search_form\')">' . get_filter_options($view['filters'], $filter) . '</select>
                                </div>
                            </form>
                </nav>
                <div class="card my-7">
                    <div class="card-body p-0 position-relative">
                        <form name="form"  action="delete_users.php" method="post" class="disable_shortcut"> 
                            ' . get_token_field() . '
                            <input type="hidden" name="send_to" value="' . h(REQUEST_URL) . '" />
                            <table class="chart table-hover table " style="width:100%;display:none" data-pg-list-action="user_list_rows" data-pg-list-params="' . $output_list_params . '">
                                <thead>
                                    <tr>
                                        <th class="noVis">
                                            <div class="form-field">
                                                <div class="switch"><input class="" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox" id="select_all"></div>
                                            </div>
                                        </th>
                                        <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                        ' . implode("\n                                        ", $view['columns']) . '
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                            <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                                <div class="container">
                                    <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">
                                        <button type="button" value="Delete Selected" class=" btn mb-1 mt-1 btn-solid theme-danger disabled" data-confirm-content="' . lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('users')))) . '"><span class="material-icons me-3">delete</span>' . lang(array('string'=>'Delete Selected') ) . '</button>
                                    </div>
                                </div>
                            </nav>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    
</main>' .
    output_footer();

$liveform->remove_form('view_users');
