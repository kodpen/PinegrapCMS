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

include_once('liveform.class.php');
$liveform = new liveform('view_short_links');

// Ensure session arrays exist before usage
if (!isset($_SESSION['software']['view_short_links'])) {
    $_SESSION['software']['view_short_links'] = [];
}

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value)) {
        $_SESSION['software']['view_short_links'][$key] = trim($value);
    }
}

// avoid undefined index warning
$sort = isset($_SESSION['software']['view_short_links']['sort']) ? ($_SESSION['software']['view_short_links']['sort'] ?? '') : null;

switch ($sort) {
    case lang('Name'):
        $sort_column = 'short_links.name';
        break;

    case lang('Destination Type'):
        $sort_column = 'short_links.destination_type';
        break;

    case lang('Created'):
        $sort_column = 'short_links.created_timestamp';
        break;

    case lang('Last Modified'):
        $sort_column = 'short_links.last_modified_timestamp';
        break;

    default:
        $sort_column = 'short_links.last_modified_timestamp';
        $_SESSION['software']['view_short_links']['sort'] = lang('Last Modified');
        $_SESSION['software']['view_short_links']['order'] = 'desc';
        break;
}

// if order is not set, set to ascending
if (!isset($_SESSION['software']['view_short_links']['order'])) {
    $_SESSION['software']['view_short_links']['order'] = 'asc';
}

$all_short_links = 0;

// get the total number of short links
$query = "SELECT COUNT(*) FROM short_links";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$all_short_links = $row[0];



// Get all short links.
$query =
    "SELECT
        short_links.id,
        short_links.name,
        short_links.destination_type,
        page.page_name,
        page.page_folder AS folder_id,
        product_groups.address_name AS product_group_address_name,
        products.address_name AS product_address_name,
        short_links.tracking_code,
        short_links.url,
        created_user.user_username AS created_username,
        short_links.created_timestamp,
        last_modified_user.user_username AS last_modified_username,
        short_links.last_modified_timestamp
    FROM short_links
    LEFT JOIN page ON short_links.page_id = page.page_id
    LEFT JOIN product_groups ON short_links.product_group_id = product_groups.id
    LEFT JOIN products ON short_links.product_id = products.id
    LEFT JOIN user AS created_user ON short_links.created_user_id = created_user.user_id
    LEFT JOIN user AS last_modified_user ON short_links.last_modified_user_id = last_modified_user.user_id
    ORDER BY $sort_column " . escape(($_SESSION['software']['view_short_links']['order'] ?? ''));
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$short_links = mysqli_fetch_items($result);

// If this user has a user role then remove short links that the user does not have access to.
// A user has access to a short link if he/she has edit rights to the short link's page
// or for url type: created the short link.
if (USER_ROLE == 3) {
    $folders_that_user_has_access_to = get_folders_that_user_has_access_to(USER_ID);

    // Loop through the short links in order to remove short links that user does not have access to.
    foreach ($short_links as $key => $short_link) {
        // Determine if the user has access to the short link differently based on the destination type.
        switch ($short_link['destination_type']) {
            default:
                // If the user does not have edit access to the page's folder, then remove short link.
                if (check_folder_access_in_array($short_link['folder_id'], $folders_that_user_has_access_to) == false) {
                    unset($short_link);
                }

                break;

            case 'url':
                // If this user is not the user that created the short link, then remove short link.
                if (USER_USERNAME != $short_link['created_username']) {
                    unset($short_link);
                }

                break;
        }
    }

    // Refresh the indexes of the array so the code further below works.
    $short_links = array_values($short_links);
}

$output_rows = '';

// if there is at least one result to display
if ($short_links) {
    

    foreach ($short_links as $short_link) {
        $output_destination_type = '';
        $output_destination = '';

        switch ($short_link['destination_type']) {
            case 'page':
                $output_destination_type = lang('Page');

                $output_destination = h(encode_url_path($short_link['page_name']));

                break;

            case 'product_group':
                $output_destination_type = lang('Product Group');

                $output_destination = h(encode_url_path($short_link['page_name'])) . '/' . h(encode_url_path($short_link['product_group_address_name']));

                break;

            case 'product':
                $output_destination_type = lang('Product');

                $output_destination = h(encode_url_path($short_link['page_name'])) . '/' . h(encode_url_path($short_link['product_address_name']));

                break;

            case 'url':
                $output_destination_type = lang('URL');

                $output_destination = h($short_link['url']);

                break;
        }

        // If there is a tracking code and the destination type is a certain type,
        // then add tracking code to destination.
        if (
            ($short_link['tracking_code'] != '')
            &&
            (
                ($short_link['destination_type'] == 'page')
                || ($short_link['destination_type'] == 'product_group')
                || ($short_link['destination_type'] == 'product')
            )
        ) {
            $output_destination .= '?t=' . h(urlencode($short_link['tracking_code']));
        }

        $created_username = '';
        
        if ($short_link['created_username']) {
            $created_username = ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($short_link['created_username']) ) ) );
        }
        
        $last_modified_username = '';
        
        if ($short_link['last_modified_username']) {
            $last_modified_username = ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($short_link['last_modified_username']) ) ) );
        }

        $output_rows .=
            '<tr>
                <td class="align-middle text-start action-buttons">
                    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'edit_short_link.php?id=' . $short_link['id'] . '\'"><i class="bi bi-pencil"></i></button>
                    <a href="' . OUTPUT_PATH . h($short_link['name']) . '" class="m-1 btn-data-control btn btn-outline-secondary border-2 " data-loading-content=" " title="' . lang('Visit') . '" ><i class="bi bi-link"></i></a>
                </td>
                <td class="align-middle chart_label">' . h($short_link['name']) . '</td>
                <td class="align-middle">' . $output_destination_type . '</td>
                <td class="align-middle">' . $output_destination . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $short_link['created_timestamp'])) . $created_username . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $short_link['last_modified_timestamp'])) . $last_modified_username . '</td>
            </tr>';
    }
}



print
pg_page_shell(
    array(
        'title'=> lang('Short Links'),
        'extra classes'=>'page',
        'icon'=>'page', 
        'heading'=>lang('Short Links'),
        
    )
) . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="' . lang('All shortcut aliases, that I have access to, for Pages, Product Groups, Products, and URLs.') . '" title="' . lang('My Short Links') . '">' . lang('My Short Links') . '</h2>
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1 " href="add_short_link.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                <th>' . get_column_heading(lang('Name'), ($_SESSION['software']['view_short_links']['sort'] ?? ''), ($_SESSION['software']['view_short_links']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Destination Type'), ($_SESSION['software']['view_short_links']['sort'] ?? ''), ($_SESSION['software']['view_short_links']['order'] ?? '')) . '</th>
                                <th>' . lang('Destination') . '</th>
                                <th>' . get_column_heading(lang('Created'), ($_SESSION['software']['view_short_links']['sort'] ?? ''), ($_SESSION['software']['view_short_links']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['view_short_links']['sort'] ?? ''), ($_SESSION['software']['view_short_links']['order'] ?? '')) . '</th>
                            </tr>
                        </thead>
                        <tbody>
                            ' . $output_rows . '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>' .
output_footer();

$liveform->remove_form();