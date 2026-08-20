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
ini_set('memory_limit', '512M');
$user = validate_user();
validate_area_access($user, 'manager');

include_once('liveform.class.php');
$liveform = new liveform('view_log');

// Path to error log file (can be extended later to include other locations)
$error_log_path = 'error_log';
$base_dir = dirname(__FILE__);
$candidates = array(
    'software_directory' => $base_dir . DIRECTORY_SEPARATOR . $error_log_path,
    'main_directory'         => $base_dir . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . $error_log_path,
    'installation_directory'       => $base_dir . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . $error_log_path,
);

if (!$_POST) {

    // Get number of all timestamps.
    $query = "SELECT COUNT(log_id) "
        ."FROM log ";
    $result = mysqli_query(db::$con, $query) or output_error("Query failed.");
    $row = mysqli_fetch_row($result);
    $all_logs = $row[0];

    // get oldest timestamp
    $query = "SELECT MIN(log_timestamp) "
        ."FROM log ";
    $result = mysqli_query(db::$con, $query) or output_error("Query failed.");
    $row = mysqli_fetch_row($result);
    $oldest_timestamp = $row[0];
    // When the log table is empty MIN() returns NULL, which makes date() emit a
    // deprecation notice on PHP 8.1+ and pollutes the error log we are displaying here.
    if (!$oldest_timestamp) {
        $oldest_timestamp = time();
    }


    // if advanced filters value was passed in the query string
    if (isset($_REQUEST['advanced_filters']) == true) {
        // if advanced filters should be turned on
        if ($_REQUEST['advanced_filters'] == 'true') {
            $_SESSION['software']['settings']['view_log']['advanced_filters'] = true;

        // else advanced filters should be turned off
        } else {
            $_SESSION['software']['settings']['view_log']['advanced_filters'] = false;
        }
    }

    $output_control_buttons = '';
    if ((MASS_DELETION == true) || (defined('USER_ROLE') && (USER_ROLE < 1))) {
        $checked = (!empty($_SESSION['software']['settings']['view_log']['error_log'])) ? 'checked' : '';
    
        $delete_logs_button = '';
        if (MASS_DELETION == true && $all_logs > 0) {
            $delete_logs_button = '
                <button type="submit" name="delete_all_site_logs" value="Delete Site Logs" class="btn btn-link link-danger py-0 mb-2 bi bi-trash bi-me-2" onclick="event.preventDefault(); var b=this; pgConfirm({title:\'' . lang('Delete All Site Logs') . '\', message:\'' . lang('WARNING: All site logs will be permanently deleted. View All button will allow you to see which logs will be deleted before you actually delete them. If you would like to continue with the deletion, please click OK. Otherwise, please click Cancel.') . '\', confirmText:\'' . lang('Delete') . '\', cancelText:\'' . lang('Cancel') . '\', variant:\'danger\'}).then(function(ok){if(ok){b.disabled=true;b.form.appendChild(Object.assign(document.createElement(\'input\'),{type:\'hidden\',name:\'delete_all_site_logs\',value:\'Delete Site Logs\'}));b.form.submit();}}); return false;">' . lang('Delete All Site Logs') . '</button>';
        }
    
        $delete_error_logs_button = '';
        $error_log_controls = '';
        if (defined('USER_ROLE') && (USER_ROLE < 1)) {

            if(
                !empty($_SESSION['software']['settings']['view_log']['error_log'])
                && (
                    file_exists($candidates['software_directory'])
                    || file_exists($candidates['main_directory'])
                    || file_exists($candidates['installation_directory'])
                )
            ) {
                $delete_error_logs_button = '<button type="submit" name="delete_all_error_logs" value="Delete Error Logs"  value="1" class="btn btn-link link-danger py-0 mb-2 bi bi-trash bi-me-2" onclick="event.preventDefault(); var b=this; pgConfirm({title:\'' . lang('Delete All Error Logs') . '\', message:\'' . lang('WARNING: error_log files will be permanently deleted around Software. If you would like to continue with the deletion, please click OK. Otherwise, please click Cancel.') . '\', confirmText:\'' . lang('Delete') . '\', cancelText:\'' . lang('Cancel') . '\', variant:\'danger\'}).then(function(ok){if(ok){b.disabled=true;b.form.appendChild(Object.assign(document.createElement(\'input\'),{type:\'hidden\',name:\'delete_all_error_logs\',value:\'Delete Error Logs\'}));b.form.submit();}}); return false;">' . lang('Delete All Error Logs') . '</button>';
            }
            
        
            $error_log_controls = '
                <div class="form-check form-switch me-3 align-middle">
                    <input class="form-check-input" type="checkbox" name="error_log_toggle" id="error_log_toggle" ' . $checked . '>
                    <label class="form-check-label" for="error_log_toggle">' . lang('Show Error Logs') . '</label>
                </div>
                <script>
                  document.getElementById("error_log_toggle").addEventListener("change", function() {
                    document.getElementById("view_log_controls").submit();
                  });
                </script>';
        }
    
        $output_control_buttons = '
            <form action="view_log.php" method="post" name="view_log_controls" id="view_log_controls" class="d-inline-block disable_shortcut">
                ' . get_token_field() . '
                ' . $delete_logs_button . '
                ' . $delete_error_logs_button . '
                ' . $error_log_controls . '
            </form>';
    }

    // if view all was selected
    if (isset($_GET['view']) && ($_GET['view'] == 'all')) {
        $start_month = date('m', $oldest_timestamp);
        $start_day = date('d', $oldest_timestamp);
        $start_year = date('Y', $oldest_timestamp);
        $stop_month = date('m');
        $stop_day = date('d');
        $stop_year = date('Y');

    // if date range has not be completed yet
    } elseif (!isset($_REQUEST['start_month']) || !$_REQUEST['start_month']) {
        // assign default values to start and stop dates (default to ~31 days ago)
        $start_month = date('m', time() - 2678400);
        $start_day = date('d', time() - 2678400);
        $start_year = date('Y', time() - 2678400);
        $stop_month = date('m');
        $stop_day = date('d');
        $stop_year = date('Y');

    } else {
        $start_month = $_REQUEST['start_month'];
        $start_day = $_REQUEST['start_day'];
        $start_year = $_REQUEST['start_year'];
        $stop_month = $_REQUEST['stop_month'];
        $stop_day = $_REQUEST['stop_day'];
        $stop_year = $_REQUEST['stop_year'];

    }
    // get minimum year from oldest timestamp
    $oldest_year = date('Y', $oldest_timestamp);
    $current_year = date('Y');
    // create html for year options
    $year_options = '';
    for ($i = $oldest_year; $i <= $current_year; $i++) {
        $year_options .= '<option value="'.$i.'">'.$i.'</option>';
    }
    // get timestamps for start and stop dates
    $start_timestamp = mktime (0, 0, 0, $start_month, $start_day, $start_year);
    $stop_timestamp = mktime (23, 59, 59, $stop_month, $stop_day, $stop_year);

    // get query data for URL
    $keys_and_values = h("&start_month=$start_month&start_day=$start_day&start_year=$start_year&stop_month=$stop_month&stop_day=$stop_day&stop_year=$stop_year");

    switch((isset($_GET['sort']) ? $_GET['sort'] : '')) {
        case lang('Time'):
        $sort_column = 'log_timestamp';
        break;
        case lang('User'):
        $sort_column = 'log_user';
        break;
        case lang('Description'):
        $sort_column = 'log_description';
        break;
        case lang('IP Address'):
        $sort_column = 'log_ip';
        break;
        default:
        $sort_column = 'log_timestamp';
    }
    if(isset($_GET['sort'])) {
        $asc_desc = isset($_GET['order']) ? $_GET['order'] : '';
    } else {
        $asc_desc = 'DESC';
    }
    if((isset($_GET['sort']) && ($_GET['sort'] == 'log_timestamp')) && (!isset($_GET['order']) || !$_GET['order'])) {
        $asc_desc = 'ASC';
    }

    // Determine whether to include error_log entries (only for admins and if enabled in settings)
    $include_error_logs = (defined('USER_ROLE') && (USER_ROLE < 1) && !empty($_SESSION['software']['settings']['view_log']['error_log']));

    $output_rows = '';
    $number_of_results = 0;

    // Renders the description cell.  error_log entries are frequently multi line stack
    // traces that push every other row off the screen, so long messages are clipped to a
    // few lines and can be expanded on demand.  Error messages also get a copy button so
    // the trace can be pasted somewhere else without selecting it by hand.
    $description_index = 0;
    $render_description = function ($text, $is_error) use (&$description_index) {
        // convert_text_to_html() already runs nl2br() internally, so it must not be wrapped again.
        $message_html = convert_text_to_html($text);
        $is_long = ((substr_count($text, "\n") > 2) || (strlen($text) > 300));

        $description_index++;
        $message_id = 'pg_log_msg_' . $description_index;

        $tools = '';
        if ($is_long) {
            $tools .= '<button type="button" class="btn btn-link btn-sm p-0 text-decoration-none pg-log-toggle"'
                . ' data-target="' . $message_id . '"'
                . ' data-more="' . h(lang('Show More')) . '"'
                . ' data-less="' . h(lang('Show Less')) . '">'
                . '<i class="bi bi-chevron-down me-1"></i><span class="pg-log-toggle-label">' . lang('Show More') . '</span></button>';
        }
        if ($is_error) {
            $tools .= '<button type="button" class="btn btn-link btn-sm p-0 ms-3 text-decoration-none pg-log-copy"'
                . ' data-target="' . $message_id . '"'
                . ' data-copy="' . h(lang('Copy')) . '"'
                . ' data-copied="' . h(lang('Copied')) . '">'
                . '<i class="bi bi-clipboard me-1"></i><span class="pg-log-copy-label">' . lang('Copy') . '</span></button>';
        }

        $classes = 'pg-log-msg text-break';
        if ($is_error) {
            $classes .= ' pg-log-msg-code';
        }
        if ($is_long) {
            $classes .= ' pg-log-clip';
        }

        $output = '<div id="' . $message_id . '" class="' . $classes . '">' . $message_html . '</div>';
        if ($tools != '') {
            $output .= '<div class="pg-log-tools">' . $tools . '</div>';
        }
        return $output;
    };

    if (!$include_error_logs) {
        // Fast path: only DB logs are needed — stream results directly without building intermediate arrays
        $query = "SELECT log_id, log_description, log_ip, log_user, log_timestamp "
            ."FROM log "
            ."WHERE (log_timestamp >= $start_timestamp) AND (log_timestamp <= $stop_timestamp) "
            ."ORDER BY $sort_column $asc_desc, log_id $asc_desc ";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed');

        while ($row = mysqli_fetch_assoc($result)) {
            $log_id = $row['log_id'];
            $log_description = $row['log_description'];
            $log_ip = isset($row['log_ip']) ? $row['log_ip'] : '';
            $log_user = $row['log_user'] === '' ? 'UNKNOWN' : $row['log_user'];
            $log_timestamp = $row['log_timestamp'];
            $log_location = lang('Database');
            $number_of_results++;

            $output_rows .= '
            <tr id="' . h($log_id) . '" class="unselectable ">
                <td>'. get_relative_time(array('timestamp' => $log_timestamp)). '</td>
                <td><span title="' . h($log_location) . '" class="badge bg-success me-1">' . lang('Site Log') . '</span><span class="d-none">pglogsite</span></td>
                <td>' . h($log_user) . '</td>
                <td>' . $render_description($log_description, false) . '</td>
                <td>' . ($log_ip != '' ? h($log_ip) : '<span class="text-muted">&mdash;</span>') . '</td>
            </tr>';
        }
    } else {
        // Full path: collect DB logs into array, gather error_log entries, merge and sort
        $db_logs = array();
        $query = "SELECT log_id, log_description, log_ip, log_user, log_timestamp "
            ."FROM log "
            ."WHERE (log_timestamp >= $start_timestamp) AND (log_timestamp <= $stop_timestamp) ";
        // keep SQL ordering as a stable base; final ordering will be enforced after merging
        $query .= "ORDER BY $sort_column $asc_desc, log_id $asc_desc ";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed');
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['log_user'] == '') {
                $row['log_user'] = 'UNKNOWN';
            }
            $row['log_type'] = 'Site Log';
            $row['log_location'] = lang('Database');
            
            $db_logs[] = $row;
        }

        // Read error_log files from possible locations
        $error_logs = array();


        foreach ($candidates as $locationmark => $candidate_path) {
            $candidate_path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $candidate_path);
            $candidate_real = @realpath($candidate_path) ?: $candidate_path;

            if (!file_exists($candidate_real) || !is_readable($candidate_real) || !is_file($candidate_real)) {
            continue;
            }

            $lines = @file($candidate_real, FILE_IGNORE_NEW_LINES);
            if ($lines === false) {
            continue;
            }

            $grouped = array();
            $current_index = -1;

            foreach ($lines as $raw_line) {
            $line = rtrim($raw_line);
            if ($line === '') {
                if ($current_index >= 0) {
                $grouped[$current_index]['lines'][] = $line;
                }
                continue;
            }

            $is_new_entry = false;
            $date_candidate = null;

            if (preg_match('/^\[?([0-9]{1,2}-[A-Za-z]{3}-[0-9]{4}\s+[0-9:]+)/', $line, $m)) {
                $is_new_entry = true;
                $date_candidate = $m[1];
            } elseif (preg_match('/^\[?([0-9]{4}-[0-9]{2}-[0-9]{2}\s+[0-9:]+)/', $line, $m)) {
                $is_new_entry = true;
                $date_candidate = $m[1];
            } elseif (preg_match('/^\[?([0-9]{4}\/[0-9]{2}\/[0-9]{2}\s+[0-9:]+)/', $line, $m)) {
                $is_new_entry = true;
                $date_candidate = str_replace('/', '-', $m[1]);
            }

            if ($is_new_entry) {
                $grouped[] = array(
                'date_candidate' => $date_candidate,
                'lines' => array($line),
                );
                $current_index++;
            } else {
                if ($current_index >= 0) {
                $grouped[$current_index]['lines'][] = $line;
                } else {
                $grouped[] = array(
                    'date_candidate' => null,
                    'lines' => array($line),
                );
                $current_index++;
                }
            }
            }

            $file_mtime = @filemtime($candidate_real) ?: time();
            $local_index = 0;
            foreach ($grouped as $g) {
            $timestamp = null;
            if (!empty($g['date_candidate'])) {
                $ts = @strtotime($g['date_candidate']);
                if ($ts !== false) {
                $timestamp = $ts;
                }
            }
            if ($timestamp === null) {
                $timestamp = $file_mtime;
            }
            $locationlabel = $locationmark;
            switch ($locationmark) {
                case 'software_directory':
                $locationlabel = lang('Software Directory');
                break;
                case 'main_directory':
                $locationlabel = lang('Main Directory');
                break;
                case 'installation_directory':
                $locationlabel = lang('Installation Directory');
                break;
            }
            if (($timestamp >= $start_timestamp) && ($timestamp <= $stop_timestamp)) {
                $error_logs[] = array(
                'log_id' => 'error_' . $locationmark . '_' . $local_index,
                'log_type' => 'Error Log',
                'log_description' => implode("\n", $g['lines']),
                'log_ip' => '',
                'log_user' => '[' . lang('SYSTEM') . ']',
                'log_location' => h($locationlabel),
                'log_timestamp' => $timestamp
                );
                $local_index++;
            }
            }
        }

        // Merge and sort
        $all_entries = array_merge($db_logs, $error_logs);

        if (strtoupper($asc_desc) === 'ASC') {
            usort($all_entries, function($a, $b) {
            if ($a['log_timestamp'] == $b['log_timestamp']) {
                return strcmp($a['log_id'], $b['log_id']);
            }
            return ($a['log_timestamp'] < $b['log_timestamp']) ? -1 : 1;
            });
        } else {
            usort($all_entries, function($a, $b) {
            if ($a['log_timestamp'] == $b['log_timestamp']) {
                return strcmp($b['log_id'], $a['log_id']);
            }
            return ($a['log_timestamp'] > $b['log_timestamp']) ? -1 : 1;
            });
        }

        // Build rows
        foreach ($all_entries as $row) {
            $log_id = $row['log_id'];
            $log_description = $row['log_description'];
            $log_ip = isset($row['log_ip']) ? $row['log_ip'] : '';
            $log_user = isset($row['log_user']) ? $row['log_user'] : '';
            if ($log_user == '') {
            $log_user = 'UNKNOWN';
            }
            $log_location = isset($row['log_location']) ? $row['log_location'] : '';
            $log_type = isset($row['log_type']) ? $row['log_type'] : 'Site Log';


            // Hidden tokens let the type filter buttons search this column without
            // depending on the translated badge text.
            $output_log_type = '';
            $row_class = '';
            $is_error_entry = false;
            if($log_type == 'Site Log') {
            $output_log_type = '<span title="' . $log_location . '" class="badge bg-success me-1">' . h(lang($log_type)) . '</span><span class="d-none">pglogsite</span>';
            } else {
            $is_error_entry = true;
            // Determine badge color based on message severity
            // warning -> bg-warning, fatal/error/uncaught/exception -> bg-danger (default for non-warning)
            $badge_class = 'bg-danger';
            $type_token = 'pglogerror';
            $row_class = ' pg-log-row-error';
            if (preg_match('/\bwarning\b/i', $log_description)) {
                // bg-warning needs dark text, the default white is unreadable on yellow.
                $badge_class = 'bg-warning text-dark';
                $type_token = 'pglogwarn';
                $row_class = ' pg-log-row-warn';
            } elseif (preg_match('/\b(fatal|fatal error|uncaught|uncaught exception|exception|error)\b/i', $log_description)) {
                $badge_class = 'bg-danger';
            }
            $output_log_type = '<span title="' . $log_location . '" class="badge ' . $badge_class . ' me-1">' . h(lang($log_type)) . '</span><span class="d-none">' . $type_token . '</span>';
            }


            $log_timestamp = $row['log_timestamp'];

            $number_of_results++;

            $output_rows .= '
            <tr id="' . h($log_id) . '" class="unselectable' . $row_class . '">
                <td>'. get_relative_time(array('timestamp' => $log_timestamp)). '</td>
                <td>' . $output_log_type  . '</td>
                <td>' . h($log_user) . '</td>
                <td>' . $render_description($log_description, $is_error_entry) . '</td>
                <td>' . ($log_ip != '' ? h($log_ip) : '<span class="text-muted">&mdash;</span>') . '</td>
            </tr>
            ';
        }
    }


    // Quick date range shortcuts.  The advanced filter panel needs six dropdowns for the
    // most common ranges, so the ranges people actually use are exposed as one click links.
    $today_timestamp = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
    // Carry the active sort over so switching the range does not silently reset the order.
    $output_sort_query = '';
    if (isset($_REQUEST['sort']) && ($_REQUEST['sort'] != '')) {
        $output_sort_query .= '&amp;sort=' . h(urlencode($_REQUEST['sort']));
    }
    if (isset($_REQUEST['order']) && ($_REQUEST['order'] != '')) {
        $output_sort_query .= '&amp;order=' . h(urlencode($_REQUEST['order']));
    }
    $quick_ranges = array(
        array('label' => lang('Today'),        'from' => $today_timestamp),
        array('label' => lang('Last 7 Days'),  'from' => $today_timestamp - (6 * 86400)),
        array('label' => lang('Last 30 Days'), 'from' => $today_timestamp - (29 * 86400)),
    );
    $output_quick_ranges = '';
    foreach ($quick_ranges as $quick_range) {
        $range_start = $quick_range['from'];
        $range_url = 'view_log.php?start_month=' . date('m', $range_start)
            . '&amp;start_day=' . date('d', $range_start)
            . '&amp;start_year=' . date('Y', $range_start)
            . '&amp;stop_month=' . date('m')
            . '&amp;stop_day=' . date('d')
            . '&amp;stop_year=' . date('Y')
            . $output_sort_query;
        // A range is active when both ends match what is currently being displayed.
        $range_is_active = ((date('Y-m-d', $start_timestamp) == date('Y-m-d', $range_start))
            && (date('Y-m-d', $stop_timestamp) == date('Y-m-d')));
        $output_quick_ranges .= '<a class="btn ' . ($range_is_active ? 'btn-primary' : 'btn-outline-secondary') . '" href="' . $range_url . '">' . $quick_range['label'] . '</a>';
    }
    // "View All" reaches back to the oldest entry.  The deletion warning already tells the
    // user about this button, so it has to be reachable from the screen.
    $view_all_is_active = ((date('Y-m-d', $start_timestamp) == date('Y-m-d', $oldest_timestamp))
        && (date('Y-m-d', $stop_timestamp) == date('Y-m-d')));
    $output_quick_ranges .= '<a class="btn ' . ($view_all_is_active ? 'btn-primary' : 'btn-outline-secondary') . '" href="view_log.php?view=all' . $output_sort_query . '">' . lang('View All') . '</a>';

    // Type filter buttons.  They only make sense when error_log entries are mixed in,
    // otherwise every row is a site log.  Filtering happens client side on the already
    // rendered table, so no page reload is needed.
    $output_type_filters = '';
    if ($include_error_logs) {
        $output_type_filters = '
                        <div class="btn-group btn-group-sm my-1 ms-sm-2" role="group" aria-label="' . h(lang('Type')) . '">
                            <button type="button" class="btn btn-outline-secondary active pg-log-type-filter" data-token="">' . lang('All') . '</button>
                            <button type="button" class="btn btn-outline-secondary pg-log-type-filter" data-token="pglogsite">' . lang('Site Log') . '</button>
                            <button type="button" class="btn btn-outline-secondary pg-log-type-filter" data-token="pglogerror">' . lang('Error Log') . '</button>
                            <button type="button" class="btn btn-outline-secondary pg-log-type-filter" data-token="pglogwarn">' . lang('Warning') . '</button>
                        </div>';
    }

    // Summary of what is currently on screen: how many entries and for which range.
    $output_range_label = get_absolute_time(array('timestamp' => $start_timestamp, 'type' => 'date', 'format' => 'plain_text'))
        . ' &ndash; '
        . get_absolute_time(array('timestamp' => $stop_timestamp, 'type' => 'date', 'format' => 'plain_text'));
    $output_result_summary = '
                        <span class="badge bg-secondary-subtle text-secondary-emphasis border fw-normal">
                            <i class="bi bi-list-ul me-1"></i>' . lang(array(
                                'string' => '{var:1} record{suffix:1}',
                                'vars'   => array(number_format($number_of_results)),
                                'suffix' => array(($number_of_results == 1) ? '' : 's')
                            )) . '
                        </span>
                        <span class="text-muted small ms-2"><i class="bi bi-calendar3 me-1"></i>' . $output_range_label . '</span>';

    $output_toolbar = '
                <div class="row align-items-center mb-2 g-2">
                    <div class="col-12 col-lg-8 text-center text-lg-start">
                        <div class="btn-group btn-group-sm my-1" role="group" aria-label="' . h(lang('Date Range')) . '">
                            ' . $output_quick_ranges . '
                        </div>' . $output_type_filters . '
                    </div>
                    <div class="col-12 col-lg-4 text-center text-lg-end">
                        ' . $output_result_summary . '
                    </div>
                </div>';


    // if the advanced filters are off
    if (empty($_SESSION['software']['settings']['view_log']['advanced_filters'])) {
        $output_advanced_filters_value = 'true';
        $output_advanced_filters_label = lang('Add Advanced Filters');
        $output_advanced_filters = '';
        $advanced_filters_icon = 'filter_list';
        $output_advanced_filters_class = 'btn-primary';
        $output_advanced_filters = '';

    // else the advanced filters are on
    } else {
        $output_advanced_filters_value = 'false';
        $output_advanced_filters_label = lang('Remove Advanced Filters');
        $advanced_filters_icon = 'filter_list_off';
        $output_advanced_filters_class = 'btn-danger';

        // The selects below are re-synced by the script at the bottom of the form, but the
        // session keys do not exist on a first visit, which would raise undefined index notices.
        $saved_filters = isset($_SESSION['software']['settings']['view_log']) ? ($_SESSION['software']['settings']['view_log'] ?? '') : array();
        $saved_start_month = isset($saved_filters['start_month']) ? $saved_filters['start_month'] : $start_month;
        $saved_start_day   = isset($saved_filters['start_day'])   ? $saved_filters['start_day']   : $start_day;
        $saved_stop_month  = isset($saved_filters['stop_month'])  ? $saved_filters['stop_month']  : $stop_month;
        $saved_stop_day    = isset($saved_filters['stop_day'])    ? $saved_filters['stop_day']    : $stop_day;

        $output_advanced_filters = '
        <div class="advanced_filters advanced-filter-bar position-fixed-md" id="advanced_filters">
            <div class="p-2 border justify-content-between d-flex flex-wrap header">
                <p class="m-0"><span class="material-icons pe-1">filter_list</span>' . lang('Filters') . '</p>
                <a class="btn btn-close" title="' . $output_advanced_filters_label . '" href="view_log.php?advanced_filters=' . $output_advanced_filters_value . '"></a>
            </div>
            <form class="advanced-filter-body p-2 pt-0 disable_shortcut" id="search_advanced" action="view_log.php" method="get" name="form">
                <div class="row">
                    <div class="col-12">
                        <h5 class="text-success fw-bold mt-4 mb-2">' . lang('Date Range') . '</h5>
                    </div>
                    <div class="col-12">
                        <label class="form-label">' . lang('From') . '</label>
                        <select class="form-select my-1" name="start_month">' . select_month($saved_start_month) . '</select>
                        <div class="input-group input-group-sm">
                            <select class="form-select my-1" name="start_day">' . select_day($saved_start_day) . '</select>
                            <select class="form-select my-1" name="start_year">' . $year_options . '</select>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">' . lang('To') . '</label>
                        <select class="form-select my-1" name="stop_month">' . select_month($saved_stop_month) . '</select>
                        <div class="input-group input-group-sm">
                            <select class="form-select my-1" name="stop_day">' . select_day($saved_stop_day) . '</select>
                            <select class="form-select my-1" name="stop_year">' . $year_options . '</select>
                        </div>
                    </div>
                    <div class="col-12 text-center position-sticky my-2" style="bottom:.5rem;">
                        <button type="submit" name="submit_data" value="Update" data-loading-content="' . lang('Updating') . '" class="btn btn-primary my-1">
                            <i class="material-icons me-2">sync</i>' . lang('Update') . '
                        </button>
                    </div>
                </div>
        
                <!-- Prevent undefined index warnings by checking if keys exist -->
                <input type="hidden" name="sort" value="' . (isset($_REQUEST['sort']) ? h($_REQUEST['sort']) : '') . '">
                <input type="hidden" name="order" value="' . (isset($_REQUEST['order']) ? h($_REQUEST['order']) : '') . '">
        
                <script>
                    // Set initial values for date selectors using escaped JS-safe values
                    document.form.start_month.value = "' . escape_javascript($start_month) . '";
                    document.form.start_day.value = "' . escape_javascript($start_day) . '";
                    document.form.start_year.value = "' . escape_javascript($start_year) . '";
                    document.form.stop_month.value = "' . escape_javascript($stop_month) . '";
                    document.form.stop_day.value = "' . escape_javascript($stop_day) . '";
                    document.form.stop_year.value = "' . escape_javascript($stop_year) . '";
                </script>
            </form>
        </div>';

    }



    echo
    
    pg_page_shell(
        array(
            'title'=> lang('Site Log'),
            'extra classes'=>'setting',
            'icon'=>'setting',
            'heading'=> lang('Site Log'),
            'cancel'=>array(
                'enable'=>'true',
                'title'=>lang('Return to Settings'),
                'url'=>'settings.php'
            ),
            'breadcrumb' => array(
                array('label' => lang('Settings'), 'url' => 'settings.php'),
                array('label' => lang('Site Log')),
            ),
            'pre_main_html' => $output_advanced_filters,
        )
    ) . '
        <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 col-md-6 col-xl-9 text-center text-md-start">
                        <h2 class="d-inline-block " data-bs-content="' . lang('Audit all website events and changes by any site visitor or user.') . '" title="' . lang('Site Log') . '">' . lang('Site Log') . '</h2>
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            ' . $output_control_buttons . '
                        </nav>
                    </div>
                    <div class="col-12 col-sm-12 col-md-6 col-xl-3 ">
                        <div class="row justify-content-center justify-content-md-end">
                            <form action="view_log.php" method="get" name="form" class="search_form col-auto">
                                <div class="input-group input-group-sm">   
                                    <a class="btn btn-sm  my-1 ' . $output_advanced_filters_class . '" data-loading-content=" " title="' . $output_advanced_filters_label . '" aria-label="' . $output_advanced_filters_label . '" href="view_log.php?advanced_filters=' . $output_advanced_filters_value . '" ><i class="material-icons">'. $advanced_filters_icon . '</i></a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                ' . $output_toolbar . '
                <div class="card my-4">
                    <div class="card-body p-0 position-relative">
                        <table class="chart table-hover table " style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th>' .asc_or_desc(lang('Time'),'view_log', $keys_and_values). '</th>
                                    <th>' .asc_or_desc(lang('Type'),'view_log', $keys_and_values). '</th>
                                    <th>' .asc_or_desc(lang('User'),'view_log', $keys_and_values). '</th>
                                    <th>' .asc_or_desc(lang('Description'),'view_log', $keys_and_values). '</th>
                                    <th>' .asc_or_desc(lang('IP Address'),'view_log', $keys_and_values). '</th>
                                </tr>
                            </thead>
                            ' . $output_rows . '
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <style>
        /* Clipped log messages: show the first few lines and fade out the rest. */
        .pg-log-msg { white-space: normal; }
        .pg-log-msg-code { font-family: var(--bs-font-monospace); font-size: .8125rem; }
        .pg-log-clip { position: relative; max-height: 4.8em; overflow: hidden; }
        .pg-log-clip::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 1.6em;
            background: linear-gradient(to bottom, transparent, var(--bs-body-bg, #fff));
            pointer-events: none;
        }
        .pg-log-tools { margin-top: .25rem; }
        /* Severity stripe on the first cell, subtler than tinting the whole row. */
        tr.pg-log-row-error > td:first-child { box-shadow: inset 3px 0 0 var(--bs-danger); }
        tr.pg-log-row-warn > td:first-child { box-shadow: inset 3px 0 0 var(--bs-warning); }
    </style>
    <script>
        // Delegated handlers so they keep working after DataTables redraws the table.
        // Registered without waiting for jQuery, which is resolved lazily on click.
        document.addEventListener("click", function (event) {
            var target = event.target;
            if (!target || !target.closest) {
                return;
            }

            // Expand / collapse a clipped log message.
            var toggle = target.closest(".pg-log-toggle");
            if (toggle) {
                event.preventDefault();
                var message = document.getElementById(toggle.getAttribute("data-target"));
                if (!message) {
                    return;
                }
                var collapsed = message.classList.toggle("pg-log-clip");
                var label = toggle.querySelector(".pg-log-toggle-label");
                var icon = toggle.querySelector("i");
                if (label) {
                    label.textContent = collapsed ? toggle.getAttribute("data-more") : toggle.getAttribute("data-less");
                }
                if (icon) {
                    icon.className = collapsed ? "bi bi-chevron-down me-1" : "bi bi-chevron-up me-1";
                }
                return;
            }

            // Copy the full message text to the clipboard.
            var copy = target.closest(".pg-log-copy");
            if (copy) {
                event.preventDefault();
                var source = document.getElementById(copy.getAttribute("data-target"));
                if (!source || !navigator.clipboard) {
                    return;
                }
                navigator.clipboard.writeText(source.innerText).then(function () {
                    var copy_label = copy.querySelector(".pg-log-copy-label");
                    var copy_icon = copy.querySelector("i");
                    if (copy_label) {
                        copy_label.textContent = copy.getAttribute("data-copied");
                    }
                    if (copy_icon) {
                        copy_icon.className = "bi bi-check2 me-1";
                    }
                    setTimeout(function () {
                        if (copy_label) {
                            copy_label.textContent = copy.getAttribute("data-copy");
                        }
                        if (copy_icon) {
                            copy_icon.className = "bi bi-clipboard me-1";
                        }
                    }, 1500);
                });
                return;
            }

            // Filter the table by log type using the hidden token in the type column.
            var type_filter = target.closest(".pg-log-type-filter");
            if (type_filter) {
                event.preventDefault();
                var buttons = document.querySelectorAll(".pg-log-type-filter");
                for (var i = 0; i < buttons.length; i++) {
                    buttons[i].classList.remove("active");
                }
                type_filter.classList.add("active");

                if (!window.jQuery || !window.jQuery.fn.dataTable) {
                    return;
                }
                var table = window.jQuery("table.chart");
                if (!table.length || !window.jQuery.fn.dataTable.isDataTable(table)) {
                    return;
                }
                // Column 1 is the type column.
                table.DataTable().column(1).search(type_filter.getAttribute("data-token")).draw();
            }
        });
    </script>' . output_footer();

     $liveform->remove_form('settings');
} else {

    
    validate_token_field();


    // save advanced filter settings
    if (isset($_POST['start_month'])) {
        $_SESSION['software']['settings']['view_log']['start_month'] = $_POST['start_month'];
    }
    if (isset($_POST['start_day'])) {
        $_SESSION['software']['settings']['view_log']['start_day'] = $_POST['start_day'];
    }
    if (isset($_POST['start_year'])) {
        $_SESSION['software']['settings']['view_log']['start_year'] = $_POST['start_year'];
    }
    if (isset($_POST['stop_month'])) {
        $_SESSION['software']['settings']['view_log']['stop_month'] = $_POST['stop_month'];
    }
    if (isset($_POST['stop_day'])) {
        $_SESSION['software']['settings']['view_log']['stop_day'] = $_POST['stop_day'];
    }
    if (isset($_POST['stop_year'])) {
        $_SESSION['software']['settings']['view_log']['stop_year'] = $_POST['stop_year'];
    }

    // save error log setting
    $_SESSION['software']['settings']['view_log']['error_log'] = isset($_POST['error_log_toggle']);

    // Handle deletion actions (POST)
    if (isset($_POST['delete_all_site_logs']) && (MASS_DELETION === true)) {
    
        // Count logs first
        $query = "SELECT COUNT(log_id) FROM log";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        $number_of_logs = (int)$row[0];
    
        if ($number_of_logs > 0) {
            // Delete all logs in a single query
            $query = "DELETE FROM log";
            mysqli_query(db::$con, $query) or output_error('Query failed.');
        
            $liveform->add_notice(
                lang(array(
                    'string' => '{var:1} site log(s) were deleted.',
                    'vars'   => array(number_format($number_of_logs))
                ))
            );
        } else {
            $liveform->mark_error(lang('No site log were deleted'));
        }
    }
    
    // Delete error_log files (allowed regardless of MASS_DELETION)
    if (isset($_POST['delete_all_error_logs'])) {
    
        $deleted = 0;
    
        foreach ($candidates as $locationmark => $candidate_path) {
            $candidate_path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $candidate_path);
            $candidate_real = @realpath($candidate_path) ?: $candidate_path;
        
            if (!file_exists($candidate_real) || !is_file($candidate_real)) {
                continue;
            }
        
            // Try to unlink; if not writable, attempt to chmod then unlink
            if (is_writable($candidate_real)) {
                if (@unlink($candidate_real)) {
                    $deleted++;
                }
            } else {
                @chmod($candidate_real, 0666);
                if (@unlink($candidate_real)) {
                    $deleted++;
                }
            }
        }
    
        if ($deleted > 0) {
            $liveform->add_notice(lang(array('string'=>'{var:1} error_log file(s) deleted.','vars'=>array($deleted))));
        } else {
            $liveform->mark_error(lang('No error_log files were deleted.'));
        }
    }
    
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_log.php');
    exit;
}