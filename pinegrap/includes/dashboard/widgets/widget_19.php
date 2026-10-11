<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 19 - Email Campaigns: the latest email campaigns.
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

function pg_dashboard_widget_19($request, $user)
{
    if ($user['role'] < 3) {

        $output_campaigns = array();

        // Query to fetch latest 20 email campaigns
        $query = "SELECT
                    email_campaigns.id as id,
                    email_campaigns.type,
                    email_campaigns.subject,
                    email_campaigns.status,
                    email_campaigns.purpose,
                    email_campaigns.start_time,
                    email_campaigns.created_user_id,
                    email_campaigns.created_timestamp,
                    email_campaigns.last_modified_timestamp,
                    created_user.user_username as created_username,
                    last_modified_user.user_username as last_modified_username
                FROM email_campaigns
                LEFT JOIN user as created_user ON email_campaigns.created_user_id = created_user.user_id
                LEFT JOIN user as last_modified_user ON email_campaigns.last_modified_user_id = last_modified_user.user_id
                ORDER BY email_campaigns.last_modified_timestamp DESC
                LIMIT 20";

        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        while ($row = mysqli_fetch_assoc($result)) {
            $output_campaigns[] = $row;
        }

        $output_campaign_rows = '';

        if (!empty($output_campaigns)) {

            foreach ($output_campaigns as $output_campaign) {

                // Prepare start time display if campaign job is enabled
                if (email_campaign_job_enabled()) {
                    if (isset($output_campaign['start_time']) && $output_campaign['start_time'] == '0000-00-00 00:00:00') {
                        $start_time = '';
                    } else {
                        $start_time = isset($output_campaign['start_time']) ? get_relative_time(array(
                            'timestamp' => strtotime($output_campaign['start_time'])
                        )) : '';
                    }
                }

                // Get total number of recipients
                $query = "SELECT COUNT(*) FROM email_recipients WHERE email_campaign_id = '" . $output_campaign['id'] . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $row = mysqli_fetch_row($result);
                $number_of_email_recipients = $row[0];

                // Get number of completed recipients
                $query = "SELECT COUNT(*) FROM email_recipients WHERE email_campaign_id = '" . $output_campaign['id'] . "' AND complete = '1'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $row = mysqli_fetch_row($result);
                $number_of_completed_email_recipients = $row[0];

                $progress_percentage = ($number_of_email_recipients > 0)
                    ? pg_format_number($number_of_completed_email_recipients / $number_of_email_recipients * 100, 0)
                    : '100';

                $output_link_url = 'edit_email_campaign.php?id=' . $output_campaign['id'] . '&amp;send_to=' . h(escape_javascript(urlencode(REQUEST_URL)));

                $campaigns_created_username = !empty($output_campaign['created_username'])
                    ? $output_campaign['created_username']
                    : '[' . lang('Unknown') . ']';

                $campaigns_last_modified_username = !empty($output_campaign['last_modified_username'])
                    ? $output_campaign['last_modified_username']
                    : '[' . lang('Unknown') . ']';

                switch ($output_campaign['purpose']) {
                    case 'transactional':
                        $output_purpose = lang('Transactional');
                        break;
                    case 'commercial':
                        $output_purpose = lang('Commercial');
                        break;
                    default:
                        $output_purpose = '';
                }

                // Status badge color
                switch ($output_campaign['status']) {
                    case 'complete':
                        $s_col = '#10b981';
                        break;
                    case 'sending':
                        $s_col = '#3b82f6';
                        break;
                    case 'paused':
                        $s_col = '#f59e0b';
                        break;
                    default:
                        $s_col = '#a1a1a1';
                        break;
                }

                // Build campaign row HTML
                $output_campaign_rows .= '
                <div class="d-flex align-items-center px-2 py-2 border-bottom pointer" onclick="window.location.href=\'' . $output_link_url . '\'" style="gap:8px;cursor:pointer">
                    <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded" style="width:30px;height:30px;background:rgba(0,0,0,.05)">
                        <i class="bi bi-megaphone" style="color:var(--campaigns-color);font-size:14px"></i>
                    </div>
                    <div class="flex-fill overflow-hidden">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="fw-semibold text-truncate" style="font-size:13px">' . h($output_campaign['subject']) . '</span>
                            <span class="badge rounded-pill flex-shrink-0 ms-1" style="background:' . $s_col . '22;color:' . $s_col . ';font-size:10px">' . h(get_email_campaign_status_name($output_campaign['status'])) . '</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between gap-1">
                            <div class="flex-fill" style="height:4px;background:rgba(0,0,0,.08);border-radius:2px">
                                <div style="width:' . $progress_percentage . '%;height:100%;background:#3b82f6;border-radius:2px"></div>
                            </div>
                            <span class="text-muted flex-shrink-0" style="font-size:10px">' . $progress_percentage . '%</span>
                            <span class="text-muted flex-shrink-0" style="font-size:10px">' . get_relative_time(array('timestamp' => $output_campaign['created_timestamp'])) . '</span>
                        </div>
                    </div>
                </div>';
            }

        } else {
            $output_campaign_rows = pg_widget_empty('bi-megaphone', lang(array(
                'string' => 'There is no {var:1} right now.',
                'vars' => lang('Email Campaign')
            )));
        }

        $output_data = '
        <div class="card-body p-0" style="overflow-x:hidden;overflow-y:auto">
            ' . $output_campaign_rows . '
        </div>';

        // Return success JSON response
        $response = array(
            'status' => 'success',
            'message' => 'Action Success',
            'data' => $output_data
        );
        return $response;

    } else {
        // Return error JSON response for unauthorized access
        $response = array(
            'status' => 'error',
            'message' => 'Access denied'
        );
        return $response;
    }
}
