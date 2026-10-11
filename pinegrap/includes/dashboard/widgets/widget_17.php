<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 17 - Site Logs: the most recent log entries.
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

function pg_dashboard_widget_17($request, $user)
{
    $output_rows = '';
    if ($user['role'] < 3) {

        $query = "SELECT 
                    log_id, 
                    log_description, 
                    log_ip, 
                    log_user, 
                    log_timestamp 
                  FROM log 
                  ORDER BY log_timestamp DESC LIMIT 20";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed');
        while ($row = mysqli_fetch_assoc($result)) {
            $site_logs[] = $row;
        }

        // if there is at least one result to display
        if (!empty($site_logs)) {
            foreach ($site_logs as $site_log) {
                $log_id = $site_log['log_id'];
                $log_timestamp = $site_log['log_timestamp'];
                $log_description = $site_log['log_description'];
                $log_ip = $site_log['log_ip'];
                $log_user = $site_log['log_user'];
                // if the username is blank, then set to UNKNOWN
                if ($log_user == '') {
                    $log_user = lang('UNKNOWN');
                }
                // output style row
                $output_rows .= pg_widget_row(array(
                    'href'  => 'view_log.php',
                    'badge' => '<i class="bi bi-journal-text"></i>',
                    'name'  => h($log_user),
                    'aside' => get_relative_time(array('timestamp' => $log_timestamp)),
                    'meta'  => convert_text_to_html($log_description) . ' &middot; IP ' . h($log_ip),
                ));

            }
        } else {
            $output_rows = pg_widget_empty('bi-journal-text', lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Site Log'))));
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
