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
validate_area_access($user, 'designer');

$liveform = new liveform('view_menus');

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value) == TRUE) {
        $_SESSION['software']['design']['view_menus'][$key] = trim($value);
    }
}


// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['design']['view_menus']['sort']) == false) {
    $_SESSION['software']['design']['view_menus']['sort'] = '';
}

switch (($_SESSION['software']['design']['view_menus']['sort'] ?? '')) {
    case lang('Name'):
        $sort_column = 'menus.name';
        break;
        
    case lang('Effect'):
        $sort_column = 'menus.effect';
        break;

    case lang('Created'):
        $sort_column = 'menus.created_timestamp';
        break;

    case lang('Last Modified'):
        $sort_column = 'menus.last_modified_timestamp';
        break;

    default:
        $sort_column = 'menus.last_modified_timestamp';
        $_SESSION['software']['design']['view_menus']['sort'] = lang('Last Modified');
        $_SESSION['software']['design']['view_menus']['order'] = 'desc';
        break;
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['design']['view_menus']['order']) == false) {
    $_SESSION['software']['design']['view_menus']['order'] = 'asc';
}

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
    ORDER BY $sort_column " . escape(($_SESSION['software']['design']['view_menus']['order'] ?? ''));

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
                    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Expand') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-chevron-right"></i></button>
                    <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
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
        'heading'=>lang('All Menus')
    ]) . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="' . lang('All shared menus that can be added to any page style and managed by any site manager.') . '" title="' . lang('All Menus') . '">' . lang('All Menus') . '</h2>
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1 " href="add_menu.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                <th>' . get_column_heading(lang('Name'), ($_SESSION['software']['design']['view_menus']['sort'] ?? ''), ($_SESSION['software']['design']['view_menus']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Effect'), ($_SESSION['software']['design']['view_menus']['sort'] ?? ''), ($_SESSION['software']['design']['view_menus']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Created'), ($_SESSION['software']['design']['view_menus']['sort'] ?? ''), ($_SESSION['software']['design']['view_menus']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['design']['view_menus']['sort'] ?? ''), ($_SESSION['software']['design']['view_menus']['order'] ?? '')) . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>' .
output_footer();

$liveform->remove_form();