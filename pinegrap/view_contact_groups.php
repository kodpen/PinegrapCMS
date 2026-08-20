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
validate_contacts_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_contact_groups');

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value) == TRUE) {
        $_SESSION['software']['view_contacts']['view_contact_groups'][$key] = trim($value);
    }
}

// if user has access to manage contact groups, then prepare to output add contact group button
if ($user['role'] < 3) {
    $output_add_contact_group_button = '
    <nav id="button_bar" class="navigation " aria-label="Button Bar">
        <a class="btn btn-sm btn-primary m-1 " href="add_contact_group.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
    </nav>';

// else user does not have access to manage contact groups, so prepare to not output buttons for contact groups
} else {
    $output_add_contact_group_button = '';
}

$keys_and_values = '';

// these are only ever incremented further below, so they have to start out at zero
$all_contact_groups = 0;
$my_contact_groups = 0;

// if the sort is not set yet, then default it to empty so that the switch below falls through
// to its default case
if (isset($_SESSION['software']['view_contacts']['view_contact_groups']['sort']) == false) {
    $_SESSION['software']['view_contacts']['view_contact_groups']['sort'] = '';
}

// If a screen was passed and it is a positive integer, then use it.
// These checks are necessary in order to avoid SQL errors below for a bogus screen value.
if (
    isset($_REQUEST['screen'])
    and $_REQUEST['screen']
    and is_numeric($_REQUEST['screen'])
    and $_REQUEST['screen'] > 0
    and $_REQUEST['screen'] == round($_REQUEST['screen'])
) {
    $screen = (int) $_REQUEST['screen'];

// Otherwise, use the default, which is the first screen.
} else {
    $screen = 1;
}

// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['view_contacts']['view_contact_groups']['sort']) == false) {
    $_SESSION['software']['view_contacts']['view_contact_groups']['sort'] = '';
}

switch (($_SESSION['software']['view_contacts']['view_contact_groups']['sort'] ?? '')) {
    case lang('Name'):
        $sort_column = 'contact_groups.name';
        break;

    case lang('Description'):
        $sort_column = 'contact_groups.description';
        break;

    case lang('Subscription'):
        $sort_column = 'contact_groups.email_subscription';
        break;

    case lang('Type'):
        $sort_column = 'contact_groups.email_subscription_type';
        break;

    case lang('Created'):
        $sort_column = 'contact_groups.created_timestamp';
        break;

    case lang('Last Modified'):
        $sort_column = 'contact_groups.last_modified_timestamp';
        break;

    default:
        $sort_column = 'contact_groups.last_modified_timestamp';
        $_SESSION['software']['view_contacts']['view_contact_groups']['sort'] = lang('Last Modified');
        $_SESSION['software']['view_contacts']['view_contact_groups']['order'] = 'desc';
        break;
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['view_contacts']['view_contact_groups']['order']) == false) {
    $_SESSION['software']['view_contacts']['view_contact_groups']['order'] = 'asc';
}

// get all contact groups
$query =
    "SELECT
        contact_groups.id,
        contact_groups.name,
        contact_groups.description,
        contact_groups.email_subscription,
        contact_groups.email_subscription_type,
        created_user.user_username as created_username,
        contact_groups.created_timestamp,
        last_modified_user.user_username as last_modified_username,
        contact_groups.last_modified_timestamp
    FROM contact_groups
    LEFT JOIN user AS created_user ON contact_groups.created_user_id = created_user.user_id
    LEFT JOIN user AS last_modified_user ON contact_groups.last_modified_user_id = last_modified_user.user_id
    ORDER BY $sort_column " . escape(($_SESSION['software']['view_contacts']['view_contact_groups']['order'] ?? ''));

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$contact_groups = array();

while ($row = mysqli_fetch_assoc($result)) {

    // Add one to all contact groups.
    $all_contact_groups++;

    // if user has access to contact group then add contact group to contact groups array
    if (validate_contact_group_access($user, $row['id']) == true) {
        $contact_groups[] = $row;

        // Add one to all contact groups.
        $my_contact_groups++;
    }
}

$output_rows = '';

// if there is at least one result to display
if ($contact_groups) {

    foreach ($contact_groups as $contact_group) {
        if ($contact_group['email_subscription']) {
            $email_subscription = '<span class="material-icons">task_alt</span>';

            if ($contact_group['email_subscription_type'] == 'open') {
                $email_subscription_type = '<span class="badge fw-light bg-primary">' . lang('Open') . '</span>';
            } else {
                $email_subscription_type = '<span class="badge fw-light bg-warning">' . lang('Closed') . '</span>';
            }

            $description = $contact_group['description'];

        } else {
            $email_subscription = '';
            $email_subscription_type = '';
            $description = '';
        }


        $created_username = '';
            
        if ($contact_group['created_username']) {
            $created_username = lang(array('string'=>'by {var:1}','vars'=>array( h($contact_group['created_username']) ) ) );
        }
        
        $last_modified_username = '';
        
        if ($contact_group['last_modified_username']) {
            $last_modified_username = lang(array('string'=>'by {var:1}','vars'=>array( h($contact_group['last_modified_username']) ) ) );
        }

        $output_link_url = 'edit_contact_group.php?id=' . $contact_group['id'];

        $output_rows .=
            '<tr class="unselectable">
			    <td class="align-middle text-start action-buttons">
                    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                    <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
                </td>
                <td class="align-middle chart_label">' . h($contact_group['name']) . '</td>
                <td class="align-middle text-center">' . $email_subscription . '</td>
                <td class="align-middle">' . $email_subscription_type . '</td>
                <td class="align-middle">' . h($description) . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $contact_group['created_timestamp'])) . ' ' . $created_username . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $contact_group['last_modified_timestamp'])) . ' ' . $last_modified_username . '</td>
            </tr>';
    }
}

print
    pg_page_shell(
        array(
            'title'=> lang('All Contact Groups'),
            'extra classes'=>'contact',
            'icon'=>'contact', 
            'heading'=>lang('All Contact Groups'),
                    
        )
    ) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                   
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 text-center text-md-start">
                        <h2 class="d-inline-block " data-bs-content="' . lang('All contact groups used to collect and organize contacts and subscribers') . '" title="' . lang('All Contact Groups') . '">' . lang('All Contact Groups') . '</h2>
                        ' . $output_add_contact_group_button . '
                    </div>
                </div>
                <div class="card my-4">
                    <table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th> 
                                <th>' . get_column_heading(lang('Name'), ($_SESSION['software']['view_contacts']['view_contact_groups']['sort'] ?? ''), ($_SESSION['software']['view_contacts']['view_contact_groups']['order'] ?? '')) . '</th>
                                <th class="text-center">' . get_column_heading(lang('Subscription'), ($_SESSION['software']['view_contacts']['view_contact_groups']['sort'] ?? ''), ($_SESSION['software']['view_contacts']['view_contact_groups']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Type'), ($_SESSION['software']['view_contacts']['view_contact_groups']['sort'] ?? ''), ($_SESSION['software']['view_contacts']['view_contact_groups']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Description'), ($_SESSION['software']['view_contacts']['view_contact_groups']['sort'] ?? ''), ($_SESSION['software']['view_contacts']['view_contact_groups']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Created'), ($_SESSION['software']['view_contacts']['view_contact_groups']['sort'] ?? ''), ($_SESSION['software']['view_contacts']['view_contact_groups']['order'] ?? '')) . '</th>
                                <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['view_contacts']['view_contact_groups']['sort'] ?? ''), ($_SESSION['software']['view_contacts']['view_contact_groups']['order'] ?? '')) . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>' .
    output_footer();




$liveform->remove_form();
?>