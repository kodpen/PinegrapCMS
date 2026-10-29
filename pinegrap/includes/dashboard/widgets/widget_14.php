<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 14 - Subscriptions: the Kodpen subscription of this installation.
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

function pg_dashboard_widget_14($request, $user)
{
    // this widget is just for kodpen customers. many api options are removed for security reason.
    // if the user is admin and has a SUBSCRIPTION_ID
    if (($user['role'] < 1) && (SUBSCRIPTION_ID != '') && (SUBSCRIPTION_ID != ' ') && (SUBSCRIPTION_ID != NULL)) {
        $API = '59593DS72233483322T669223344';
        if ($API != NULL and $API != '') {
            $request = array();
            $request['hostname'] = HOSTNAME_SETTING;
            $request['url'] = URL_SCHEME . HOSTNAME_SETTING . PATH;
            $request['version'] = VERSION;
            $request['edition'] = EDITION;
            $request['uname'] = function_exists('php_uname') ? php_uname() : PHP_OS; // disable_functions on some hosts
            $request['os'] = PHP_OS;
            $request['web_server'] = $_SERVER['SERVER_SOFTWARE'];
            $request['php_version'] = phpversion();
            $request['mysql_version'] = db("SELECT VERSION()");
            $request['installer'] = INSTALLER;
            $request['private_label'] = PRIVATE_LABEL;
            $data = encode_json($request);
            $REQUEST = 'get';
            // pg_curl_run() sends this installation's User-Agent (without one
            // the licence server's firewall refused the request), verifies the
            // certificate through pg_curl_tls() and uses PROXY_ADDRESS.
            $answer = pg_curl_run('POST', 'https://www.kodpen.com/api2?API=' . $API . '&REQUEST=' . $REQUEST . '&SECRET=' . SUBSCRIPTION_ID, pg_license_request_options($data, 0));
            $response = $answer['body'];
            $data = decode_json($response);

            // The remote service can be unreachable or answer with something that is not JSON,
            // in which case decode_json() returns null.
            if (is_array($data) == false) {
                $data = array();
            }

            foreach (array(
                'D_name',
                'D_Host',
                'D_start_d',
                'D_end_d',
                'Hosting',
                'H_Host',
                'H_Domain',
                'H_start_d',
                'H_end_d',
                'SSL_author',
                'SSL_Domain',
                'SSL_start_d',
                'SSL_end_d',
                'P_KEY',
                'P_start_d',
                'P_end_d') as $remote_key) {
                if (isset($data[$remote_key]) == false) {
                    $data[$remote_key] = '';
                }
            }

            $D_name = $data['D_name'];
            $D_Host = $data['D_Host'];
            $D_start_d = $data['D_start_d'];
            $D_end_d = $data['D_end_d'];
            $Hosting = $data['Hosting'];
            $H_Host = $data['H_Host'];
            $H_Domain = $data['H_Domain'];
            $H_start_d = $data['H_start_d'];
            $H_end_d = $data['H_end_d'];
            $SSL_author = $data['SSL_author'];
            $SSL_Domain = $data['SSL_Domain'];
            $SSL_start_d = $data['SSL_start_d'];
            $SSL_end_d = $data['SSL_end_d'];
            $P_KEY = $data['P_KEY'];
            $P_start_d = $data['P_start_d'];
            $P_end_d = $data['P_end_d'];

            $today = date_create(date("d-m-Y"));
            $D_start_d_formatted = date_create(date("d-m-Y", strtotime($D_start_d)));
            $D_end_d_formatted = date_create(date("d-m-Y", strtotime($D_end_d)));
            $H_start_d_formatted = date_create(date("d-m-Y", strtotime($H_start_d)));
            $H_end_d_formatted = date_create(date("d-m-Y", strtotime($H_end_d)));
            $SSL_start_d_formatted = date_create(date("d-m-Y", strtotime($SSL_start_d)));
            $SSL_end_d_formatted = date_create(date("d-m-Y", strtotime($SSL_end_d)));
            $P_start_d_formatted = date_create(date("d-m-Y", strtotime($P_start_d)));
            $P_end_d_formatted = date_create(date("d-m-Y", strtotime($P_end_d)));

            $D_interval = date_diff($D_end_d_formatted, $today);
            $D_interval_dif = date_diff($D_end_d_formatted, $D_start_d_formatted);
            $D_countdown = $D_interval->format('%a');
            $D_day_dif = $D_interval_dif->format('%a');
            $H_interval = date_diff($H_end_d_formatted, $today);
            $H_interval_dif = date_diff($H_end_d_formatted, $H_start_d_formatted);
            $H_countdown = $H_interval->format('%a');
            $H_day_dif = $H_interval_dif->format('%a');
            $SSL_interval = date_diff($SSL_end_d_formatted, $today);
            $SSL_interval_dif = date_diff($SSL_end_d_formatted, $SSL_start_d_formatted);
            $SSL_countdown = $SSL_interval->format('%a');
            $SSL_day_dif = $SSL_interval_dif->format('%a');
            $P_interval = date_diff($P_end_d_formatted, $today);
            $P_interval_dif = date_diff($P_end_d_formatted, $P_start_d_formatted);
            $P_countdown = $P_interval->format('%a');
            $P_day_dif = $P_interval_dif->format('%a');

            // Render a single subscription row (Bootstrap Icons, linear progress bar)
            $render_sub_row = function ($end_date, $total_days, $remaining_days, $bs_icon, $heading, $subline = '') {
                if (!$end_date)
                    return '';
                $today_dt = date_create(date('Y-m-d'));
                $end_dt = date_create(date('Y-m-d', strtotime($end_date)));
                $is_over = ($today_dt >= $end_dt);
                if ($is_over) {
                    $color = '#a1a1a1';
                    $pct = 100;
                    $badge_text = lang('Over');
                    $row_state = ' fg-2';
                } else {
                    $rd = (int) $remaining_days;
                    if ($rd <= 7) {
                        $color = '#ef4444';
                    } elseif ($rd <= 30) {
                        $color = '#f59e0b';
                    } else {
                        $color = '#10b981';
                    }
                    $pct = ($total_days > 0) ? min(100, (int) round((($total_days - $rd) / $total_days) * 100)) : 0;
                    $badge_text = $rd . ' ' . lang('days remaining');
                    $row_state = '';
                }
                return '
                <div class="px-3 py-3 border-bottom' . $row_state . '">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div class="d-flex align-items-center overflow-hidden me-3">
                            <i class="bi ' . $bs_icon . ' me-3 flex-shrink-0" style="color:' . $color . ';font-size:15px"></i>
                            <span class="fw-semibold text-truncate" style="font-size:13px">' . h($heading) . '</span>
                        </div>
                        <span class="badge rounded-pill flex-shrink-0" style="background:' . $color . '22;color:color-mix(in srgb, ' . $color . ' 50%, var(--pg-fg-emphasis));font-size:10px;white-space:nowrap">' . $badge_text . '</span>
                    </div>'
                    . ($subline ? '<small class="fg-secondary d-block mb-1" style="padding-left:23px">' . h($subline) . '</small>' : '') .
                    '<div class="rounded-pill ms-1" style="height:4px;background:var(--pg-bg-2);overflow:hidden">
                        <div class="rounded-pill h-100" style="width:' . $pct . '%;background:' . $color . '"></div>
                    </div>
                </div>';
            };

            $OUTPUT_DOMAIN_ROWS = '';
            if ($D_Host && $D_name) {
                $OUTPUT_DOMAIN_ROWS = $render_sub_row($D_end_d, $D_day_dif, $D_countdown, 'bi-globe2', lang('Domain'), $D_name);
            }
            $OUTPUT_HOSTING_ROWS = '';
            if ($H_Host && $Hosting) {
                $OUTPUT_HOSTING_ROWS = $render_sub_row($H_end_d, $H_day_dif, $H_countdown, 'bi-server', lang('Hosting'), $H_Host);
            }
            $OUTPUT_SSL_ROWS = '';
            if ($SSL_author && $SSL_Domain) {
                $OUTPUT_SSL_ROWS = $render_sub_row($SSL_end_d, $SSL_day_dif, $SSL_countdown, 'bi-shield-lock-fill', lang('SSL Certificate'), $SSL_Domain);
            }
            $OUTPUT_P_ROWS = '';
            if ($P_KEY) {
                $OUTPUT_P_ROWS = $render_sub_row($P_end_d, $P_day_dif, $P_countdown, 'bi-award-fill', lang('Software License'));
            }

            $output_rows = $OUTPUT_DOMAIN_ROWS . $OUTPUT_HOSTING_ROWS . $OUTPUT_SSL_ROWS . $OUTPUT_P_ROWS;

            // An unreachable licence server, or an answer without
            // any of the four records, leaves every row empty.
            if ($output_rows === '') {
                $output_rows = pg_widget_empty('bi-cloud-slash', lang('Subscription details could not be loaded.'));
            }

            $output_data = '
            <div class="card-body p-0" style="overflow-x:hidden;overflow-y:auto">
                ' . $output_rows . '
            </div>';

            //return success json output
            $response = array(
                'status' => 'success',
                'message' => 'Action Success',
                'data' => $output_data,
            );
            return $response;
        }
    } else {
        $response = array(
            'status' => 'error',
            'message' => 'Access denied'
        );
        return $response;
    }
}
