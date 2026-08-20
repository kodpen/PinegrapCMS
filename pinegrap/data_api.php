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




// If login info was included in the request, then store it, so that initialize_user() can login user.
if ((isset($_GET['username']))&&($_GET['username'] != '')) {
    define('API_USERNAME', $_GET['username']);
    define('API_PASSWORD', md5($_GET['password']));
}

if(isset($_GET['action'])){
    $action = $_GET['action'];
}else{
    $action = 'Null';
}
if(isset($_GET['token'])){
    $token = $_GET['token'];
}

// Add header in order to start response.
header('Content-Type: application/json');

switch ($action) {
    case 'GETProductListSetup':
        $user = validate_user();
        //validate_token();
        validate_ecommerce_access($user);
        $columns = array();

        
        $columns[] = array(
            'name' => 'action/select-all',
            'title' => '<span id="selectallcheckbox" class="d-block text-center bi bi-circle"></span>',
            'orderable' => false,
            'searchable' => false,
            'width' => '24px',
            'className' => ''
        );
        if ($_GET['buttons'] == true) {
            $columns[] = array(
                'name' => 'actions',
                'title' => lang(''),
                'orderable' => false,
                'searchable' => false,
                'className' => 'no-selection',
                'width' => '24px',
            );
        };
        $columns[] = array('title' => lang('ID/SKU'));
        $columns[] = array('title' => lang('Short Description'));
        $columns[] = array('title' => lang('Price'));
        $columns[] = array('title' => lang('Last Modified'));

       

        $output = array(
            'title' => lang('All Products'),
            'description' => 'View All Products that you can access.',
            'fixedColumns' => array(
                'start' => 1,
                'end' => 0
            ),
            'scrollCollapse' => true,
            'stateSave' => true,
            'stateDuration',
            'select' => array(
                'style' => 'multi+shift',
                'items' => 'row',
                'selector' => 'tr td:not(.no-selection)',
                'info' => false
            ),
            'columns' => $columns,
            'order' => array(
                4, 'desc'
            )
        );
        
        echo respond($output);
        break;

    case 'GETProductList':
        $user = validate_user();
     
        //validate_token();
        validate_ecommerce_access($user);
        $array = array();
        // SQL server connection information
        $sql_details = array(
            'user' => DB_USERNAME,
            'pass' => DB_PASSWORD,
            'db'   => DB_DATABASE,
            'host' => DB_HOST,
            'charset' => 'utf8'
        );
        // DB table to use
        $table = 'products';

        // Table's primary key
        $primaryKey = 'id';
        //$join = "LEFT JOIN products_groups_xref ON product_groups.id = product_group";

        //filters
        $whereResult = null;
        $whereAll = "";
        $join = null;
        // Array of database columns which should be read and sent back to DataTables.
        // The `db` parameter represents the column name in the database, while the `dt`
        // parameter represents the DataTables column identifier. In this case simple
        // indexes




        $columns = array();

        $columns[] = array( 
            'db' => 'id',
            'dt' => 'DT_RowId'
        );
        $columns[] = array(
            'db' => 'id',
            'dt' => increase(array('reset'=>true)),
            'formatter' => function( $d, $row ) { 
                return '<span class="d-block text-center bi fakeselectallcheckbox"></span>';
            },
        );

        if ($_GET['buttons'] == true) {
            $columns[] = array(
                'db' => 'id',
                'dt' => increase(),
                'formatter' => function( $d, $row ) { 
                    return '<span class="btn-group">
                        <a class="btn-data-control btn btn-outline-primary bi bi-pencil"></a>
                        <button type="button" class="btn-data-control btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" data-bs-reference="parent">
                            <span class="visually-hidden">Toggle Dropdown</span>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Action</a></li>
                            <li><a class="dropdown-item" href="#">Another action</a></li>
                            <li><a class="dropdown-item" href="#">Something else here</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#">Separated link</a></li>
                        </ul>
                    </span>';
                },
            );
        };
        $columns[] = array(
            'db' => 'name',
            'dt' => increase(),
        );
        
        $columns[] = array( 
            'db' => 'short_description', 
            'dt' => increase(),
        );
        
        $columns[] = array( 
            'db' => 'price', 
            'dt' => increase(),
            'formatter' => function( $d, $row ) {
                return prepare_amount($d / 100);
            }
        );
        $columns[] = array(
            'db' => 'timestamp',
            'dt' => increase(),
            'formatter' => function( $d, $row ) {
                return$d;
            }
        );
        require( 'assets/DataTables/ssp.class.php');
        echo respond(SSP::complex( $_GET, $sql_details, $table, $primaryKey, $columns, $whereResult, $whereAll, $join));
        break;

    default:
        $response = array(
            'status' => 'error',
            'message' => 'Invalid action.');
        echo respond($response);
        break;
}

function respond($response) {
    echo encode_json($response);
    exit;
}

// A token is required to be passed in the request for session login requests
// that update an item.
function validate_token() {

    global $token;

    // If the user passed a username and password in this request
    // and did not login via a session, then token validation is not
    // necessary, so return true.
    if (defined('API_USERNAME')) {
        return true;
    }

    // If the token does not exist in the session,
    // or the passed token does not match the token from the session,
    // then this might be a CSRF attack so respond with an error.
    if (
        ($_SESSION['software']['token'] == '')
        || ($token != $_SESSION['software']['token'])
    ) {
        respond(array(
            'status' => 'error',
            'message' => 'Invalid token.'));
    }
}