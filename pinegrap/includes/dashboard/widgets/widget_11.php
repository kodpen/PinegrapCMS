<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 11 - Users: the user accounts of the site.
 *
 * Loaded and called by includes/dashboard/widgets.php; see that file for the
 * contract every widget follows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_WIDGETS')) {
    exit;
}

function pg_dashboard_widget_11($request, $user)
{
    $output_rows = '';
    if ($user['role'] < 3) {
        $sql_where = "";
        // if the user is not an administrator, then prepare where condition for role
        if ($user['role'] > 0) {
            $sql_where = "WHERE user.user_role > '" . $user['role'] . "'";
        }
        $users = array();
        $query = "SELECT
                user.user_id as id,
                user.user_username as username,
                user.user_email as email_address,
                user.user_timestamp as timestamp,
                last_modified_user.user_username as last_modified_username
            FROM user
            LEFT JOIN user as last_modified_user ON user.user_user = last_modified_user.user_id
            $sql_where
            ORDER BY user.user_timestamp DESC
            LIMIT 20";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // loop through the result in order to prepare array of items
        while ($row = mysqli_fetch_assoc($result)) {
            $users[] = $row;
        }

        $output_user_rows = '';
        if (!empty($users)) {
            // loop through the users, in order to output rows
            // we are using the variable name $recent_user instead of $user, because $user is a reserved variable for storing user information
            // there was a bug where the start page link in the header would not appear because were using the $user variable
            foreach ($users as $recent_user) {
                $output_link_url = 'edit_user.php?id=' . $recent_user['id'];
                $u_initial = strtoupper(substr($recent_user['username'], 0, 1));
                if ($u_initial == '')
                    $u_initial = '?';

                $output_rows .= pg_widget_row(array(
                    'href'  => $output_link_url,
                    'badge' => h($u_initial),
                    'name'  => h($recent_user['username']),
                    'aside' => get_relative_time(array('timestamp' => $recent_user['timestamp'])),
                    'meta'  => h($recent_user['email_address']),
                ));
            }
        } else {
            $output_rows = pg_widget_empty('bi-people', lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('User'))));
        }

        $output_data = '
            <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
                <div class="pg-list">' . $output_rows . '</div>
            </div>';

        //return success json output
        $response = array(
            'status' => 'success',
            'message' => 'Action Success',
            'data' => $output_data,
        );
        return $response;

    } else {
        $response = array(
            'status' => 'error',
            'message' => 'Access denied'
        );
        return $response;
    }
}
