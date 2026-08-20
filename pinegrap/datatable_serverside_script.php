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
// Add header in order to start response.
header('Content-Type: application/json');

$user = validate_user();

/*
 * DataTables example server-side processing script.
 *
 * Please note that this script is intentionally extremely simple to show how
 * server-side processing can be implemented, and probably shouldn't be used as
 * the basis for a large complex system. It is suitable for simple use cases as
 * for learning.
 *
 * See http://datatables.net/usage/server-side for full details on the server-
 * side processing requirements of DataTables.
 *
 * @license MIT - http://datatables.net/license_mit
 */
 
/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * Easy set variables
 */
 
// DB table to use
$table = 'files';
 
// Table's primary key
$primaryKey = 'id';
 
// Array of database columns which should be read and sent back to DataTables.
// The `db` parameter represents the column name in the database, while the `dt`
// parameter represents the DataTables column identifier. In this case simple
// indexes
$columns = array(
    array( 'db' => 'id', 'dt' => 0 ),
    array( 'db' => 'name', 'dt' => 1 ),
    array( 'db' => 'type',  'dt' => 2 ),
    array( 'db' => 'size',   'dt' => 3,
    'formatter' => function( $d) {
        return convert_bytes_to_string($d);
    } ),
    array( 'db' => 'timestamp',     'dt' => 4,
    'formatter' => function( $d) {
        return date('Y/m/d H:i:s', $d);
    } ),
);
// SQL server connection information
$sql_details = array(
    'user' => DB_USERNAME,
    'pass' => DB_PASSWORD,
    'db'   => DB_DATABASE,
    'host' => DB_HOST
);

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * If you just want to use the basic configuration for DataTables with PHP
 * server-side, there is no need to edit below this line.
 */
 
 
require( 'assets/DataTables/ssp.class.php' );

function utf8ize($d) {
    if (is_array($d)) {
        foreach ($d as $k => $v) {
            $d[$k] = utf8ize($v);
        }
    } else if (is_string ($d)) {
        return utf8_encode($d);
    }
    return $d;
}
$folders_that_user_has_access_to = array();
global $user;
// if user is a basic user, then get folders that user has access to
if ($user['role'] == 3) {
    $folders_that_user_has_access_to = get_folders_that_user_has_access_to($user['id']);
}
// if design files should not be included, then include filter
if ($design == false) {
    $sql_design = "AND (design = 0)";
}

echo json_encode(utf8ize( SSP::complex( $_GET, $sql_details, $table, $primaryKey, $columns, null, "type = 'gif' || type = 'jpg'|| type = 'jpeg'|| type = 'png'|| type = 'bmp'|| type = 'tif'|| type = 'tiff' $sql_design AND (attachment = 0)" ) ) );

