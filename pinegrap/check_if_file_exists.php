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
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// this script is called by Javascript (via AJAX) to determine if the file name for a file, is already in use
// it returns a response in XML format
include('init.php');
$user = validate_user();
validate_area_access($user, 'user');

// The file name is one value; one sent as an array (?file_name[]=x) counts as
// not given: it ended in an error page.
pg_request_single_values(array('file_name'));

// Without a file name there is nothing to clash with: the answer is
// "upload", as before, without the PHP notices.
$file_name = prepare_file_name($_GET['file_name'] ?? '');

// check if there is already a file with the file name
$query = "SELECT id, design, folder
         FROM files
         WHERE name = ?";
$result = db_q($query, array((string) $file_name)) or output_error('Query failed.');

// if file name is not in use
if (mysqli_num_rows($result) == 0) {
    $response = 'upload';
    
// else file name is in use
} else {
    $row = mysqli_fetch_assoc($result);
    
    // If user is designer or greater
    if ($user['role'] < 2) {
        
        // Allow overwrite
        $response = 'overwrite';
        
    // Else if the user is a manager or below.
    } else {
        
        // if user has access to existing file, then user has permission to overwrite file
        if ((check_edit_access($row['folder']) == true) && ($row['design'] == 0)) {
            $response = 'overwrite';
            
        // else user does not have access to existing file
        } else {
            $response = 'access denied';
        }
    }
}

header("Content-type: text/xml");
print
'<?xml version="1.0" encoding="utf-8" ?>
<response>' . $response . '</response>';
?>