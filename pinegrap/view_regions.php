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
 *              2016–2025 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();
validate_area_access($user, 'designer');

$liveform = new liveform('view_regions');

$number_of_results = 0;

$filter = $_GET['filter'];

$filter_for_links = '&filter=' . $filter;
$output_filter_for_links = h($filter_for_links);
$output_table_headers = '';
$output_table_contents = '';
switch ($filter) {
    case 'all_ad_regions':
        
        // if sort was set, update session
        if (isset($_REQUEST['sort'])) {
            // store sort in session
            $_SESSION['software']['design']['view_regions']['all_ad_regions']['sort'] = $_REQUEST['sort'];

            // clear order
            $_SESSION['software']['design']['view_regions']['all_ad_regions']['order'] = '';
        }
        
        // if order was set, update session
        if (isset($_REQUEST['order'])) {
            // store sort in session
            $_SESSION['software']['design']['view_regions']['all_ad_regions']['order'] = $_REQUEST['order'];
        }
        
        
        // Set the heading and subheading.
        $heading = lang('All Ad Regions');
        $subheading = lang('All ad regions that can be added to any page style and display rotating ads created by any site manager.');
        
        // Add the create button to the button bar.
        $button_bar_button = '<a class="btn btn-sm btn-primary m-1" href="add_ad_region.php"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>';
        
        // Set the sort option
        switch($_SESSION['software']['design']['view_regions']['all_ad_regions']['sort']) {
            case lang('Name'):
                $sort_column = 'name';
                break;
            case lang('Display Type'):
                $sort_column = 'display_type';
                break;
            case lang('Created'):
                $sort_column = 'created_timestamp';
                break;
            case lang('Last Modified'):
                $sort_column = 'last_modified_timestamp';
                break;
            default:
                $sort_column = 'last_modified_timestamp';
                $_SESSION['software']['design']['view_regions']['all_ad_regions']['sort'] = lang('Last Modified');
        }
        
        if ($_SESSION['software']['design']['view_regions']['all_ad_regions']['order']) {
            $asc_desc = $_SESSION['software']['design']['view_regions']['all_ad_regions']['order'];
        } elseif ($sort_column == 'last_modified_timestamp') {
            $asc_desc = 'desc';
            $_SESSION['software']['design']['view_regions']['all_ad_regions']['order'] = 'desc';
        } else {
            $asc_desc = 'asc';
            $_SESSION['software']['design']['view_regions']['all_ad_regions']['order'] = 'asc';
        }
        
        // get total number of ad regions
        $query = "SELECT COUNT(id) FROM ad_regions";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        $all_regions = $row[0];
        
        // Output table headings
        $output_table_headers .= '
            <tr>
                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                <th>' . get_column_heading(lang('Name'), $_SESSION['software']['design']['view_regions']['all_ad_regions']['sort'], $_SESSION['software']['design']['view_regions']['all_ad_regions']['order'], $output_filter_for_links) . '</th>
                <th>' . get_column_heading(lang('Display Type'), $_SESSION['software']['design']['view_regions']['all_ad_regions']['sort'], $_SESSION['software']['design']['view_regions']['all_ad_regions']['order'], $output_filter_for_links) . '</th>
                <th>' . lang('Duration') . '</th>
                <th class="text-center">' . lang('Autoplay') . '</th>
                <th>' . lang('Interval') . '</th>
                <th class="text-center">' . lang('Continuous') . '</th>
                <th>' . get_column_heading(lang('Created'), $_SESSION['software']['design']['view_regions']['all_ad_regions']['sort'], $_SESSION['software']['design']['view_regions']['all_ad_regions']['order'], $output_filter_for_links) . '</th>
                <th>' . get_column_heading(lang('Last Modified'), $_SESSION['software']['design']['view_regions']['all_ad_regions']['sort'], $_SESSION['software']['design']['view_regions']['all_ad_regions']['order'], $output_filter_for_links) . '</th>
            </tr>';

      
        $query = 
            "SELECT 
                id,
                name,
                display_type,
                transition_duration,
                slideshow,
                slideshow_interval,
                slideshow_continuous,
                created_user.user_username as created_username,
                ad_regions.created_timestamp,
                last_modified_user.user_username as last_modified_username,
                ad_regions.last_modified_timestamp
            FROM ad_regions
            LEFT JOIN user as created_user ON ad_regions.created_user_id = created_user.user_id
            LEFT JOIN user as last_modified_user ON ad_regions.last_modified_user_id = last_modified_user.user_id
            ORDER BY " . $sort_column . " " . $asc_desc;
        $result = mysqli_query(db::$con, $query) or output_error('Query failed');
        
        while ($row = mysqli_fetch_array($result)){
            $id = $row['id'];
            $name = $row['name'];
            $display_type = $row['display_type'];
            $transition_duration = $row['transition_duration'];
            $slideshow = $row['slideshow'];
            $slideshow_interval = $row['slideshow_interval'];
            $slideshow_continuous = $row['slideshow_continuous'];
            $created_timestamp = $row['created_timestamp'];
            $last_modified_timestamp = $row['last_modified_timestamp'];
            
            if (isset($row['created_username']) == TRUE) {
                $created_username = lang(array('string'=>'by {var:1}','vars'=>array( h($row['created_username']) ) ) );
            }

            if (isset($row['last_modified_username']) == TRUE) {
                $last_modified_username = lang(array('string'=>'by {var:1}','vars'=>array( h($row['last_modified_username']) ) ) );
            }
            
            // output link url
            $output_link_url = 'edit_ad_region.php?id=' . $id;
            
            // Increment the amount of results
            $number_of_results++;
            
            // if the display type is static then set output value
            if ($display_type == 'static') {
                $output_display_type = lang('Static');
                $output_transition_duration = '';
                $output_slideshow = '';
                $output_slideshow_interval = '';
                $output_slideshow_continuous = '';
                
            // else the display type is dynamic so set output value
            } else {
                $output_display_type = lang('Dynamic');
                
                // if transition duration is 0, then set to empty string
                if ($transition_duration == 0) {
                    $output_transition_duration = '';
                    
                // else transition duration is not 0, so set to value
                } else {
                    $output_transition_duration = number_format($transition_duration);
                }
                
                $output_slideshow = '';
                $output_slideshow_interval = '';
                $output_slideshow_continuous = '';
                
                // if slideshow is enabled, then prepare to output slideshow values
                if ($slideshow == 1) {
                    $output_slideshow = '<span class="material-icons">task_alt</span>';
                    $output_slideshow_interval = $slideshow_interval;

                    // If the slideshow is continuous, then output check mark.
                    if ($slideshow_continuous == 1) {
                        $output_slideshow_continuous = '<span class="material-icons">task_alt</span>';
                    }
                }
            }
            
            // Output table.
            $output_table_contents .= '
                <tr>
                    <td class="align-middle text-start">
                        <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                    </td>
                    <td class="align-middle chart_label text-nowrap">' . h($name) . '</td>
                    <td class="align-middle">' . $output_display_type . '</td>
                    <td class="align-middle">' . $output_transition_duration . '</td>
                    <td class="align-middle text-center" nowrap>' . $output_slideshow . '</td>
                    <td class="align-middle">' . $output_slideshow_interval . '</td>
                    <td class="align-middle text-center" nowrap>' . $output_slideshow_continuous . '</td>
                    <td class="align-middle">' . get_relative_time(array('timestamp' => $created_timestamp)) . ' ' . $created_username . '</td>
                    <td class="align-middle">' . get_relative_time(array('timestamp' => $last_modified_timestamp)) . ' ' . $last_modified_username . '</td>
                </tr>';
        }
        break;
        
    case 'all_login_regions':
        
        // if sort was set, update session
        if (isset($_REQUEST['sort'])) {
            // store sort in session
            $_SESSION['software']['design']['view_regions']['all_login_regions']['sort'] = $_REQUEST['sort'];

            // clear order
            $_SESSION['software']['design']['view_regions']['all_login_regions']['order'] = '';
        }
        
        // if order was set, update session
        if (isset($_REQUEST['order'])) {
            // store sort in session
            $_SESSION['software']['design']['view_regions']['all_login_regions']['order'] = $_REQUEST['order'];
        }
        
        

        
        
        // Set the heading and subheading.
        $heading = lang('All Login Regions');
        $subheading = lang('All login regions that add the login process to any page.');
        
        // Add the create button to the button bar.
        $button_bar_button = '<a class="btn btn-sm btn-primary m-1" href="add_login_region.php"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>';
        
        // Set the sort option
        switch($_SESSION['software']['design']['view_regions']['all_login_regions']['sort']) {
            case lang('Name'):
                $sort_column = 'name';
                break;
            case lang('Not Logged In Header'):
                $sort_column = 'not_logged_in_header';
                break;
            case lang('Show Login Form'):
                $sort_column = 'login_form';
                break;
            case lang('Not Logged In Footer'):
                $sort_column = 'not_logged_in_footer';
                break;
            case lang('Logged In Header'):
                $sort_column = 'logged_in_header';
                break;
            case lang('Logged In Footer'):
                $sort_column = 'logged_in_footer';
                break;
            case lang('Created'):
                $sort_column = 'created_timestamp';
                break;
            case lang('Last Modified'):
                $sort_column = 'last_modified_timestamp';
                break;
            default:
                $sort_column = 'last_modified_timestamp';
                $_SESSION['software']['design']['view_regions']['all_login_regions']['sort'] = lang('Last Modified');
        }
        
        if ($_SESSION['software']['design']['view_regions']['all_login_regions']['order']) {
            $asc_desc = $_SESSION['software']['design']['view_regions']['all_login_regions']['order'];
        } elseif ($sort_column == 'last_modified_timestamp') {
            $asc_desc = 'desc';
            $_SESSION['software']['design']['view_regions']['all_login_regions']['order'] = 'desc';
        } else {
            $asc_desc = 'asc';
            $_SESSION['software']['design']['view_regions']['all_login_regions']['order'] = 'asc';
        }
        
        // get total number of login regions
        $query = "SELECT COUNT(id) FROM login_regions";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        $all_regions = $row[0];
        
        // Output table headings
        $output_table_headers = '            
            <tr>
                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                <th>' . get_column_heading(lang('Name'), $_SESSION['software']['design']['view_regions']['all_login_regions']['sort'], $_SESSION['software']['design']['view_regions']['all_login_regions']['order'], $output_filter_for_links) . '</th>
                <th>' . get_column_heading(lang('Not Logged In Header'), $_SESSION['software']['design']['view_regions']['all_login_regions']['sort'], $_SESSION['software']['design']['view_regions']['all_login_regions']['order'], $output_filter_for_links) . '</th>
                <th class="text-center text-nowrap">' . get_column_heading(lang('Show Login Form'), $_SESSION['software']['design']['view_regions']['all_login_regions']['sort'], $_SESSION['software']['design']['view_regions']['all_login_regions']['order'], $output_filter_for_links) . '</th>
                <th>' . get_column_heading(lang('Not Logged In Footer'), $_SESSION['software']['design']['view_regions']['all_login_regions']['sort'], $_SESSION['software']['design']['view_regions']['all_login_regions']['order'], $output_filter_for_links) . '</th>
                <th>' . get_column_heading(lang('Logged In Header'), $_SESSION['software']['design']['view_regions']['all_login_regions']['sort'], $_SESSION['software']['design']['view_regions']['all_login_regions']['order'], $output_filter_for_links) . '</th>
                <th>' . get_column_heading(lang('Logged In Footer'), $_SESSION['software']['design']['view_regions']['all_login_regions']['sort'], $_SESSION['software']['design']['view_regions']['all_login_regions']['order'], $output_filter_for_links) . '</th>
                <th>' . get_column_heading(lang('Created'), $_SESSION['software']['design']['view_regions']['all_login_regions']['sort'], $_SESSION['software']['design']['view_regions']['all_login_regions']['order'], $output_filter_for_links) . '</th>
                <th>' . get_column_heading(lang('Last Modified'), $_SESSION['software']['design']['view_regions']['all_login_regions']['sort'], $_SESSION['software']['design']['view_regions']['all_login_regions']['order'], $output_filter_for_links) . '</th>
            </tr>
            ';

        
        $query = 
            "SELECT 
                id,
                name,
                not_logged_in_header,
                login_form,
                not_logged_in_footer,
                logged_in_header,
                logged_in_footer,
                created_timestamp,
                last_modified_timestamp,
                created_user.user_username as created_username,
                last_modified_user.user_username as last_modified_username
            FROM login_regions
            LEFT JOIN user as created_user ON login_regions.created_user_id = created_user.user_id
            LEFT JOIN user as last_modified_user ON login_regions.last_modified_user_id = last_modified_user.user_id
            ORDER BY " . $sort_column . " " . $asc_desc;
        $result = mysqli_query(db::$con, $query) or output_error('Query failed');
        
        while ($row = mysqli_fetch_array($result)){
            $id = $row['id'];
            $name = $row['name'];
            $not_logged_in_header = $row['not_logged_in_header'];
            $login_form = $row['login_form'];
            $not_logged_in_footer = $row['not_logged_in_footer'];
            $logged_in_header = $row['logged_in_header'];
            $logged_in_footer = $row['logged_in_footer'];
            $created_timestamp = $row['created_timestamp'];
            $last_modified_timestamp = $row['last_modified_timestamp'];
            
            if (isset($row['created_username']) == TRUE) {
                $created_username = lang(array('string'=>'by {var:1}','vars'=>array( h($row['created_username']) ) ) );
            }

            if (isset($row['last_modified_username']) == TRUE) {
                $last_modified_username = lang(array('string'=>'by {var:1}','vars'=>array( h($row['last_modified_username']) ) ) );
            }



            
            // if not_logged_in_header is longer than 50 characters
            if (mb_strlen($not_logged_in_header) > 50) {
                $not_logged_in_header = h(mb_substr($not_logged_in_header, 0, 50) . '...');
            }
            
            // if login form is enabled, then output check mark
            if ($login_form == 1) {
                $login_form = '<span class="material-icons">task_alt</span>';
                
            // else login form is disabled, so do not output check mark
            } else {
                $login_form = '';
            }
            
            // if not_logged_in_footer is longer than 50 characters
            if (mb_strlen($not_logged_in_footer) > 50) {
                $not_logged_in_footer = h(mb_substr($not_logged_in_footer, 0, 50) . '...');
            }
            
            // if logged_in_header is longer than 50 characters
            if (mb_strlen($logged_in_header) > 50) {
                $logged_in_header = h(mb_substr($logged_in_header, 0, 50) . '...');
            }
            
            // if logged_in_footer is longer than 50 characters
            if (mb_strlen($logged_in_footer) > 100) {
                $logged_in_footer = h(mb_substr($logged_in_footer, 0, 50) . '...');
            }
            
            // output link url
            $output_link_url = 'edit_login_region.php?id=' . $id;
            
            // Increment the amount of results
            $number_of_results++;
            
            // Output table.
            $output_table_contents .= '
                <tr>
                    <td class="align-middle text-start">
                        <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                    </td>
                    <td class="align-middle chart_label text-nowrap">' . h($name) . '</td>
                    <td class="align-middle">' . $not_logged_in_header . '</td>
                    <td class="align-middle tex-center">' . $login_form . '</td>
                    <td class="align-middle">' . $not_logged_in_footer . '</td>
                    <td class="align-middle">' . $logged_in_header . '</td>
                    <td class="align-middle">' . $logged_in_footer . '</td>
                    <td class="align-middle">' . get_relative_time(array('timestamp' => $created_timestamp)) . ' ' . $created_username . '</td>
                    <td class="align-middle">' . get_relative_time(array('timestamp' => $last_modified_timestamp)) . ' ' . $last_modified_username . '</td>
                </tr>';
        }
        break;
    case 'all_dynamic_regions':
        
        // if sort was set, update session
        if (isset($_REQUEST['sort'])) {
            // store sort in session
            $_SESSION['software']['design']['view_regions']['all_dynamic_regions']['sort'] = $_REQUEST['sort'];

            // clear order
            $_SESSION['software']['design']['view_regions']['all_dynamic_regions']['order'] = '';
        }
        
        // if order was set, update session
        if (isset($_REQUEST['order'])) {
            // store sort in session
            $_SESSION['software']['design']['view_regions']['all_dynamic_regions']['order'] = $_REQUEST['order'];
        }
        
        

     
        
        // Set the heading and subheading.
        $heading = lang('All Dynamic Regions');
        $subheading = lang('All dynamic regions that contain PHP code and can be added to any page style.');
        
        // Add the create button to the button bar.
        $button_bar_button = '<a class="btn btn-sm btn-primary m-1" href="add_dynamic_region.php"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>';
        
        // output dynamic regions
        // if user's role is above a designer role then output dynamic region area
        if ($user['role'] < 1) {
            // Set the sort option
            switch($_SESSION['software']['design']['view_regions']['all_dynamic_regions']['sort']) {
                case lang('Name'):
                    $sort_column = 'dregion_name';
                    break;
                case lang('Code Preview'):
                    $sort_column = 'dregion_code';
                    break;
                case lang('Last Modified'):
                    $sort_column = 'dregion_timestamp';
                    break;
                default:
                    $sort_column = 'dregion_timestamp';
                    $_SESSION['software']['design']['view_regions']['all_dynamic_regions']['sort'] = lang('Last Modified');
            }
            
            if ($_SESSION['software']['design']['view_regions']['all_dynamic_regions']['order']) {
                $asc_desc = $_SESSION['software']['design']['view_regions']['all_dynamic_regions']['order'];
            } elseif ($sort_column == 'dregion_timestamp') {
                $asc_desc = 'desc';
                $_SESSION['software']['design']['view_regions']['all_dynamic_regions']['order'] = 'desc';
            } else {
                $asc_desc = 'asc';
                $_SESSION['software']['design']['view_regions']['all_dynamic_regions']['order'] = 'asc';
            }
            
            // get total number of dynamic regions
            $query = "SELECT COUNT(dregion_id) FROM dregion";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_row($result);
            $all_regions = $row[0];
            
            // Output table headings
            $output_table_headers .= '
            <tr>
                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                <th>' . get_column_heading(lang('Name'), $_SESSION['software']['design']['view_regions']['all_dynamic_regions']['sort'], $_SESSION['software']['design']['view_regions']['all_dynamic_regions']['order'], $output_filter_for_links) . '</th>
                <th>' . get_column_heading(lang('Code Preview'), $_SESSION['software']['design']['view_regions']['all_dynamic_regions']['sort'], $_SESSION['software']['design']['view_regions']['all_dynamic_regions']['order'], $output_filter_for_links) . '</th>
                <th>' . get_column_heading(lang('Last Modified'), $_SESSION['software']['design']['view_regions']['all_dynamic_regions']['sort'], $_SESSION['software']['design']['view_regions']['all_dynamic_regions']['order'], $output_filter_for_links) . '</th>
            </tr>
            ';
            
         
            
            $query = 
                "SELECT 
                    dregion_id,
                    dregion_name,
                    dregion_code,
                    dregion_timestamp,
                    last_modified_user.user_username as user_username
                FROM dregion
                LEFT JOIN user as last_modified_user ON dregion.dregion_user = last_modified_user.user_id
                ORDER BY $sort_column $asc_desc";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed');
            
            while ($row = mysqli_fetch_array($result)){

                $user_username = '';

                if (isset($row['user_username']) == TRUE) {
                    $user_username = lang(array('string'=>'by {var:1}','vars'=>array( h($row['user_username']) ) ) );
                }


                // get preview of content
                $code_preview = h(mb_substr($row['dregion_code'], 0, 50));
                
                // Output link url
                $output_link_url = 'edit_dynamic_region.php?id='.$row['dregion_id'];
                
                // Increment the amount of results
                $number_of_results++;
                
                // Output table
                $output_table_contents .= '
                <tr>
                    <td class="align-middle text-start">
                        <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                    </td>
                    <td class="align-middle chart_label text-nowrap">' . h($row['dregion_name']) . '</td>
                    <td class="align-middle">'.$code_preview.'</td>
                    <td class="align-middle">'. get_relative_time(array('timestamp' => $row['dregion_timestamp'])) .' '. $user_username .'</td>
                </tr>';
            }
        }
        break;

    case 'all_designer_regions':
        
        // if sort was set, update session
        if (isset($_REQUEST['sort'])) {
            // store sort in session
            $_SESSION['software']['design']['view_regions']['all_designer_regions']['sort'] = $_REQUEST['sort'];

            // clear order
            $_SESSION['software']['design']['view_regions']['all_designer_regions']['order'] = '';
        }
        
        // if order was set, update session
        if (isset($_REQUEST['order'])) {
            // store sort in session
            $_SESSION['software']['design']['view_regions']['all_designer_regions']['order'] = $_REQUEST['order'];
        }
        
       
       
        
        // Set the heading and subheading.
        $heading = lang('All Designer Regions');
        $subheading =lang('All designer regions of shared content that can be added to any page style and updated during page editing by any Site Designer.');
        
        // Add the create button to the button bar.
        $button_bar_button = '<a class="btn btn-sm btn-primary m-1" href="add_designer_region.php"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>';
        
        // Set the sort option
        switch($_SESSION['software']['design']['view_regions']['all_designer_regions']['sort']) {
            case lang('Name'):
                $sort_column = 'cregion_name';
                break;
            case lang('Content Preview'):
                $sort_column = 'cregion_content';
                break;
            case lang('Last Modified'):
                $sort_column = 'cregion_timestamp';
                break;
            default:
                $sort_column = 'cregion_timestamp';
                $_SESSION['software']['design']['view_regions']['all_designer_regions']['sort'] = lang('Last Modified');
        }

        if ($_SESSION['software']['design']['view_regions']['all_designer_regions']['order']) {
            $asc_desc = $_SESSION['software']['design']['view_regions']['all_designer_regions']['order'];
        } elseif ($sort_column == 'cregion_timestamp') {
            $asc_desc = 'desc';
            $_SESSION['software']['design']['view_regions']['all_designer_regions']['order'] = 'desc';
        } else {
            $asc_desc = 'asc';
            $_SESSION['software']['design']['view_regions']['all_designer_regions']['order'] = 'asc';
        }
        
        // Get total number of designer regions.
        $query = "SELECT COUNT(cregion_id) FROM cregion WHERE cregion_designer_type = 'yes'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        $all_regions = $row[0];
        
        // Output table headings
        $output_table_headers = '
        <tr>
            <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
            <th>' . get_column_heading(lang('Name'), $_SESSION['software']['design']['view_regions']['all_designer_regions']['sort'], $_SESSION['software']['design']['view_regions']['all_designer_regions']['order'], $output_filter_for_links) . '</th>
            <th>' . get_column_heading(lang('Content Preview'), $_SESSION['software']['design']['view_regions']['all_designer_regions']['sort'], $_SESSION['software']['design']['view_regions']['all_designer_regions']['order'], $output_filter_for_links) . '</th>
            <th>' . get_column_heading(lang('Last Modified'), $_SESSION['software']['design']['view_regions']['all_designer_regions']['sort'], $_SESSION['software']['design']['view_regions']['all_designer_regions']['order'], $output_filter_for_links) . '</th>
        </tr>';
        
       

        // Query to get the database information
        $query = 
            "SELECT 
                cregion_id,
                cregion_name,
                cregion_content,
                cregion_timestamp,
                cregion_designer_type,
                last_modified_user.user_username as user_username
            FROM cregion
            LEFT JOIN user as last_modified_user ON cregion.cregion_user = last_modified_user.user_id
            WHERE cregion_designer_type = 'yes'
            ORDER BY $sort_column $asc_desc";

        $result = mysqli_query(db::$con, $query) or output_error('Query failed');

        while ($row = mysqli_fetch_array($result)){

            $user_username = '';

            if (isset($row['user_username']) == TRUE) {
                $user_username = lang(array('string'=>'by {var:1}','vars'=>array( h($row['user_username']) ) ) );
            }
            
            $content_preview = h(mb_substr($row['cregion_content'], 0, 50));

            $output_link_url = 'edit_designer_region.php?id=' . $row['cregion_id'];
            
            // Increment the amount of results
            $number_of_results++;
            
            // Output table
            $output_table_contents .= '
                <tr>
                    <td class="align-middle text-start">
                        <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                    </td>
                    <td class="align-middle chart_label text-nowrap">' . h($row['cregion_name']) . '</td>
                    <td class="align-middle">' . $content_preview . '</td>
                    <td class="align-middle">' . get_relative_time(array('timestamp' => $row['cregion_timestamp'])) . ' ' . $user_username . '</td>
                </tr>';
        }
        break;

    case 'all_common_regions':
    default:
        
        // if sort was set, update session
        if (isset($_REQUEST['sort'])) {
            // store sort in session
            $_SESSION['software']['design']['view_regions']['all_common_regions']['sort'] = $_REQUEST['sort'];

            // clear order
            $_SESSION['software']['design']['view_regions']['all_common_regions']['order'] = '';
        }
        
        // if order was set, update session
        if (isset($_REQUEST['order'])) {
            // store sort in session
            $_SESSION['software']['design']['view_regions']['all_common_regions']['order'] = $_REQUEST['order'];
        }
        
       

       
        
        // Set the heading and subheading.
        $heading = lang('All Common Regions');
        $subheading =lang('All common regions of shared content that can be added to any page style and updated during page editing by any site manager.');
        
        // Add the create button to the button bar.
        $button_bar_button = '<a class="btn btn-sm btn-primary m-1" href="add_common_region.php"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>';
        
        // Set the sort option
        switch($_SESSION['software']['design']['view_regions']['all_common_regions']['sort']) {
            case lang('Name'):
                $sort_column = 'cregion_name';
                break;
            case lang('Content Preview'):
                $sort_column = 'cregion_content';
                break;
            case lang('Last Modified'):
                $sort_column = 'cregion_timestamp';
                break;
            default:
                $sort_column = 'cregion_timestamp';
                $_SESSION['software']['design']['view_regions']['all_common_regions']['sort'] = lang('Last Modified');
        }

        if ($_SESSION['software']['design']['view_regions']['all_common_regions']['order']) {
            $asc_desc = $_SESSION['software']['design']['view_regions']['all_common_regions']['order'];
        } elseif ($sort_column == 'cregion_timestamp') {
            $asc_desc = 'desc';
            $_SESSION['software']['design']['view_regions']['all_common_regions']['order'] = 'desc';
        } else {
            $asc_desc = 'asc';
            $_SESSION['software']['design']['view_regions']['all_common_regions']['order'] = 'asc';
        }
        
        // Get total number of common regions.
        $query = "SELECT COUNT(cregion_id) FROM cregion WHERE cregion_designer_type = 'no'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        $all_regions = $row[0];
        
        // Output table headings
        $output_table_headers = '
        <tr>
            <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
            <th>' . get_column_heading(lang('Name'), $_SESSION['software']['design']['view_regions']['all_common_regions']['sort'], $_SESSION['software']['design']['view_regions']['all_common_regions']['order'], $output_filter_for_links) . '</th>
            <th>' . get_column_heading(lang('Content Preview'), $_SESSION['software']['design']['view_regions']['all_common_regions']['sort'], $_SESSION['software']['design']['view_regions']['all_common_regions']['order'], $output_filter_for_links) . '</th>
            <th>' . get_column_heading(lang('Last Modified'), $_SESSION['software']['design']['view_regions']['all_common_regions']['sort'], $_SESSION['software']['design']['view_regions']['all_common_regions']['order'], $output_filter_for_links) . '</th>
        </tr>';
        
        

        // Query to get the database information
        $query = 
            "SELECT
                cregion_id,
                cregion_name,
                cregion_content,
                cregion_timestamp,
                cregion_designer_type,
                last_modified_user.user_username as user_username
            FROM cregion
            LEFT JOIN user as last_modified_user ON cregion.cregion_user = last_modified_user.user_id
            WHERE cregion_designer_type = 'no'
            ORDER BY $sort_column $asc_desc";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed');

        while ($row = mysqli_fetch_array($result)){

            $user_username = '';

            if (isset($row['user_username']) == TRUE) {
                $user_username = lang(array('string'=>'by {var:1}','vars'=>array( h($row['user_username']) ) ) );
            }
            
            $content_preview = h(mb_substr($row['cregion_content'], 0, 50));
            
            $output_link_url = 'edit_common_region.php?id=' . $row['cregion_id'];
            
            // Increment the amount of results
            $number_of_results++;
            
            // Output table
            $output_table_contents .= '
                <tr>
                    <td class="align-middle text-start">
                        <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                    </td>
                    <td class="align-middle chart_label text-nowrap">' . h($row['cregion_name']) . '</td>
                    <td class="align-middle">' . $content_preview . '</td>
                    <td class="align-middle">' . get_relative_time(array('timestamp' => $row['cregion_timestamp'])) . ' ' . $user_username . '</td>
                </tr>';
        }
        break;
}

echo
    pg_page_shell([
        'title'=> $heading,
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>$heading
    ]) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
               
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 text-center text-md-start">
                        <h2 class="d-inline-block " data-bs-content="' . $subheading . '" title="' . $heading . '">' . $heading . '</h2>
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            ' . $button_bar_button . '
                        </nav>
                    </div>
                </div>
                <div class="card my-4">
                    <div class="card-body p-0 position-relative">
                        <table class="chart table-hover table " style="width:100%;display:none">
                            <thead>' . $output_table_headers . '</thead>
                            <tbody>' . $output_table_contents . '</tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>' .
    output_footer();

$liveform->remove_form();