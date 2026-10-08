<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 13 - Forms: the most recently submitted forms.
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

function pg_dashboard_widget_13($request, $user)
{
    $output_rows = '';
    // if the user has access to manage forms, then get submitted forms
    if (($user['role'] < 3) || ($user['manage_forms'] == true)) {
        $submitted_forms = array();
        // if the user is above a user role, then get the submitted forms in a certain way
        if ($user['role'] < 3) {
            $query = "SELECT
                    forms.id,
                    forms.reference_code as reference_code,
                    custom_form_pages.form_name,
                    user.user_username as username,
                    contacts.first_name,
                    contacts.last_name,
                    forms.last_modified_timestamp as timestamp,
                    last_modified_user.user_username as last_modified_username
                FROM forms
                LEFT JOIN custom_form_pages ON forms.page_id = custom_form_pages.page_id
                LEFT JOIN user ON forms.user_id = user.user_id
                LEFT JOIN contacts ON forms.contact_id = contacts.id
                LEFT JOIN user as last_modified_user ON forms.last_modified_user_id = last_modified_user.user_id
                ORDER BY forms.last_modified_timestamp DESC
                LIMIT 200";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            // loop through the result in order to prepare array of items
            while ($row = mysqli_fetch_assoc($result)) {
                $submitted_forms[] = $row;
            }

            // else the user has a user role, so get the submitted forms in a different way

        } else {
            $custom_forms = array();
            $folders_that_user_has_access_to = get_folders_that_user_has_access_to($user['id']);

            // get all custom forms in order to determine which the user has access to
            $query = "SELECT
                    page_id,
                    page_folder as folder_id
                FROM page
                WHERE " . pg_form_page_sql('page') . "";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            // loop through the result in order to prepare array of items
            while ($row = mysqli_fetch_assoc($result)) {
                // if user has access to the custom form then add it to array
                if (check_folder_access_in_array($row['folder_id'], $folders_that_user_has_access_to) == true) {
                    $custom_forms[] = $row;
                }
            }

            // if the user has access to at least one custom form, then get submitted forms
            if (count($custom_forms) > 0) {
                $sql_where = "";

                // loop through the custom forms in order to prepare where SQL conditions
                foreach ($custom_forms as $custom_form) {
                    // if there is already where content then add an or
                    if ($sql_where != "") {
                        $sql_where .= " OR ";
                    }

                    // add condition for this custom form
                    $sql_where .= "(forms.page_id = '" . $custom_form['page_id'] . "')";
                }

                $query = "SELECT
                        forms.id,
                        custom_form_pages.form_name,
                        user.user_username as username,
                        forms.last_modified_timestamp as timestamp,
                        last_modified_user.user_username as last_modified_username
                    FROM forms
                    LEFT JOIN custom_form_pages ON forms.page_id = custom_form_pages.page_id
                    LEFT JOIN user ON forms.user_id = user.user_id
                    LEFT JOIN user as last_modified_user ON forms.last_modified_user_id = last_modified_user.user_id
                    WHERE $sql_where
                    ORDER BY forms.last_modified_timestamp DESC
                    LIMIT 25";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                // loop through the result in order to prepare array of items
                while ($row = mysqli_fetch_assoc($result)) {
                    $submitted_forms[] = $row;
                }
            }
        }

        // ── Headline ────────────────────────────────────────────────────────────
        //
        // submitted_timestamp, not last_modified_timestamp. The
        // three KPI tiles that used to sit here counted forms that
        // had been *edited*, which put an old form touched today
        // into today's activity and made this figure mean something
        // different from the orders and contacts figures beside it
        // on the dashboard.
        //
        // It is also a real query now rather than a tally of the
        // rows this widget happened to fetch, which was capped and
        // therefore undercounted a busy day.
        $output_kpi = pg_widget_headline(array(
            'id' => 'w13_spark',
            'rgb' => '14,165,233',
            'unit' => lang('forms today'),
            'series' => pg_activity_daily('forms', 'forms.submitted_timestamp'),
        ));

        // ── Rows ─────────────────────────────────────────────────────────────────
        $output_rows = '';
        $display_forms = array_slice($submitted_forms, 0, 20);

        if (!empty($display_forms)) {
            foreach ($display_forms as $submitted_form) {
                $link = 'edit_submitted_form.php?id=' . $submitted_form['id'];

                // Prefer contact name, fall back to username, then [Unknown]
                $contact_name = trim(
                    ($submitted_form['first_name'] ?? '') . ' ' . ($submitted_form['last_name'] ?? '')
                );
                if ($contact_name == '') {
                    $contact_name = ($submitted_form['username'] != '')
                        ? $submitted_form['username']
                        : '[' . lang('Unknown') . ']';
                }

                $form_name = h($submitted_form['form_name']);
                $ref = isset($submitted_form['reference_code']) ? h($submitted_form['reference_code']) : '';
                $time = get_relative_time(array('timestamp' => $submitted_form['timestamp']));

                $output_rows .= pg_widget_row(array(
                    'href'  => $link,
                    'badge' => '<i class="bi bi-file-earmark-text"></i>',
                    'name'  => h($contact_name),
                    'aside' => $time,
                    'meta'  => $form_name . ($ref != '' ? ' &middot; ' . $ref : ''),
                ));
            }
        } else {
            $output_rows = pg_widget_empty('bi-file-earmark-text', lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Form'))));
        }

        $output_data = '
            <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
                ' . $output_kpi . '
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
