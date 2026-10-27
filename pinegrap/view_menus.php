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

$liveform = new liveform('view_menus');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'effect'        => lang('Effect'),
    'created'       => lang('Created'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_menus', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'menus.name',
    'effect'        => 'menus.effect',
    'created'       => 'menus.created_timestamp',
    'last_modified' => 'menus.last_modified_timestamp',
));

// get total number of menus
$query = "SELECT COUNT(id) FROM menus";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$all_menus = $row[0];

// get all menus
$query =
    "SELECT
        menus.id,
        menus.name,
        menus.effect,
        menus.created_timestamp,
        menus.last_modified_timestamp,
        menus.last_modified_user_id,
        created_user.user_username as created_username,
        last_modified_user.user_username as last_modified_username
    FROM menus
    LEFT JOIN user as created_user ON menus.created_user_id = created_user.user_id
    LEFT JOIN user as last_modified_user ON menus.last_modified_user_id = last_modified_user.user_id
    ORDER BY $order_by";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$menus = array();

while ($row = mysqli_fetch_assoc($result)) {
    $menus[] = $row;
}

$output_rows = '';

// if there is at least one result to display
if ($menus) {

    foreach ($menus as $menu) {
        if ($menu['created_username']) {
            $created_username = $menu['created_username'];
        } else {
            $created_username = '[' . lang('Unknown') . ']';
        }

        if ($menu['last_modified_username']) {
            $last_modified_username = $menu['last_modified_username'];
        } else {
            $last_modified_username = '[' . lang('Unknown') . ']';
        }

        $output_link_url = 'view_menu_items.php?id=' . $menu['id'] . '&from=design&send_to=' . h(escape_javascript(urlencode(REQUEST_URL)));
        
        $output_effect = h($menu['effect']);
        
        // if the effect is set to none, then update value to be "None"
        if ($output_effect == '') {
            $output_effect = lang('None');
        }
        
        $output_rows .=
            '<tr>
                <td class="align-middle text-start action-buttons">
                    <button type="button" class="m-1 btn-data-control btn btn-outline theme-primary border-2 " title="' . lang('Expand') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-chevron-right"></i></button>
                    <!--<button type="button" class="m-1 btn-data-control btn btn-outline theme-danger border-2 " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
                </td>
                <td class="align-middle chart_label">' . h($menu['name']) . '</td>
                <td class="align-middle">' . $output_effect . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $menu['created_timestamp'])) . ' ' . h($created_username) . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $menu['last_modified_timestamp'])) . ' ' . h($last_modified_username) . '</td>
            </tr>';
    }
}

echo
pg_page_shell([
        'title'=> lang('All Menus'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('All Menus'),
        'heading_description' => lang('All shared menus that can be added to any page style and managed by any site manager.'),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            <div class="row mb-3  flex-wrap">
                <div class="col-12 text-center md:text-start">
                    
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-solid theme-primary m-1 " href="add_menu.php"><span class="bi bi-plus-circle me-3"></span>' . lang(array('string'=>'Create') ) . '</a>
                        <div class="pg-toolbar-grow"></div>
                        ' . pg_list_sort_menu('view_menus.php', $sort_columns, $sort_levels, $sort_default) . '
                    </nav>
                </div>
            </div>
            <div class="card my-7">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                <th>' . get_column_heading(lang('Name'), $sort_levels, '', '', 'name') . '</th>
                                <th>' . get_column_heading(lang('Effect'), $sort_levels, '', '', 'effect') . '</th>
                                <th>' . get_column_heading(lang('Created'), $sort_levels, '', '', 'created') . '</th>
                                <th>' . get_column_heading(lang('Last Modified'), $sort_levels, '', '', 'last_modified') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();