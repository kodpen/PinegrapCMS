<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 10 - Contacts: the most recently added contacts.
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

function pg_dashboard_widget_10($request, $user)
{
    $output_rows = '';
    // if the user has access to contacts, then get contacts
    if (($user['role'] < 3) || ($user['manage_contacts'] == true)) {
        $contacts = array();
        // if the user is above a user role, then get the contacts in a certain way (for performance reasons)
        if ($user['role'] < 3) {
            $query = "SELECT
                    contacts.id,
                    contacts.first_name,
                    contacts.last_name,
                    contacts.email_address,
                    contacts.timestamp,
                    user.user_username as username
                FROM contacts
                LEFT JOIN user ON contacts.user = user.user_id
                ORDER BY contacts.timestamp DESC
                LIMIT 20";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            // loop through the result in order to prepare array of items
            while ($row = mysqli_fetch_assoc($result)) {
                $contacts[] = $row;
            }

            // else the user has a user role, so get the contacts in a different way

        } else {
            $contact_groups = get_items_user_can_edit('contact_groups', $user['id']);

            // if the user has access to at least one contact group, then get contacts
            if (count($contact_groups) > 0) {
                $sql_where = '';

                // loop through the contact groups in order to prepare where SQL statement
                foreach ($contact_groups as $contact_group) {
                    // if there is already where content then add an or
                    if ($sql_where != '') {
                        $sql_where .= ' OR ';
                    }

                    // add condition for this contact group
                    $sql_where .= '(contacts_contact_groups_xref.contact_group_id = ' . $contact_group . ')';
                }

                $query = "SELECT
                        contacts.id,
                        contacts.first_name,
                        contacts.last_name,
                        contacts.email_address,
                        contacts.timestamp,
                        user.user_username as username
                    FROM contacts
                    LEFT JOIN user ON contacts.user = user.user_id
                    LEFT JOIN contacts_contact_groups_xref ON contacts.id = contacts_contact_groups_xref.contact_id
                    WHERE $sql_where
                    GROUP BY contacts.id
                    ORDER BY contacts.timestamp DESC
                    LIMIT 25";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                // loop through the result in order to prepare array of items
                while ($row = mysqli_fetch_assoc($result)) {
                    $contacts[] = $row;
                }
            }
        }

        if (!empty($contacts)) {
            // loop through the contacts, in order to output rows
            foreach ($contacts as $contact) {
                $output_link_url = 'edit_contact.php?id=' . $contact['id'];
                $name = '';
                // if there is a first name, then add it to the name
                if ($contact['first_name'] != '') {
                    $name .= $contact['first_name'];
                }

                // if there is a last name, then add it to the name
                if ($contact['last_name'] != '') {
                    // if the name is not blank, then add space
                    if ($name != '') {
                        $name .= ' ';
                    }

                    $name .= $contact['last_name'];
                }

                // Build initials avatar
                $initials = strtoupper(substr($contact['first_name'], 0, 1) . substr($contact['last_name'], 0, 1));
                if ($initials == '')
                    $initials = strtoupper(substr($contact['username'], 0, 1));
                if ($initials == '')
                    $initials = '?';

                $output_rows .= pg_widget_row(array(
                    'href'  => $output_link_url,
                    'badge' => h($initials),
                    'name'  => h($name ?: $contact['username']),
                    'aside' => get_relative_time(array('timestamp' => $contact['timestamp'])),
                    'meta'  => h($contact['email_address']),
                ));
            }
        } else {
            $output_rows = pg_widget_empty('bi-person-lines-fill', lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Contact'))));
        }
        $output_data = '
            <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
                ' . pg_widget_headline(array(
                    'id' => 'w10_spark',
                    'rgb' => '99,102,241',
                    'unit' => lang('contacts today'),
                    'series' => pg_activity_daily('contacts', 'contacts.timestamp'),
                )) . '
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
