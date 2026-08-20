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
if ( !isset( $_GET['request'] ) && empty( $_GET['request'] ) && $_GET['request'] != 'action'){
    include('init.php');
    $user = validate_user();
    validate_ecommerce_access($user);
    license_check(array('output'=>'validate'));
    echo    
    pg_page_shell(
        array(
            'title'=> lang('Product Inventory Quantity Increase'),
            'extra classes'=>'products',
            'icon'=>'store',
            'heading'=>lang('Product Inventory Quantity Increase'),
            'cancel' => true,
            'auto_main' => false,
        
            'breadcrumb' => array(array('label' => lang('All Products'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_products.php'), array('label' => lang('Product Inventory Quantity Increase'))),
        )
    )  . '
    <style></style>
        <main class="container mb-5" style="min-height:calc(100vh - 175px)" id="content">
            <div class="row">
                <div class="col-12">
                    <div class="row mb-2  flex-wrap">
                        <div class="col-12 col-sm-12 col-md-6 col-xl-9 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('You can make product inventory quantity increase transactions with the barcode of the products from the local business/warehouse. Click anywhere on this page before scanning the barcode. Also, do not use the keyboard on this page, otherwise it will detect it as a barcode.') . '" title="' . lang('Product Inventory Quantity Increase') . '">' . lang('Product Inventory Quantity Increase') . '</h2>
                            
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12 my-2 col-md-auto">
                    <div class="card border-4 border-success">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 col-md-auto">
                                    <label for="scanner_input" class="form-label" data-bs-content="' . lang('-Last Scan: shows last readed scan for barcode scanners and no need to any action.</br>-Manuel Scan: if scanner read wrong or dont read, you can input manuel and read it with \'Read\' button.') . '" title="' . lang('Last Scan & Manuel Read') . '">' . lang('Last Scan & Manuel Read') . ' (' . lang('what is this?') . ')</label>
                                    <div class="input-group my-2">
                                        <label for="scanner_input" class="input-group-text">' . lang('Barcode') . ':</label>
                                        <input type="text" value=""  placeholder="' . lang('Barcode') . '" class="form-control text-center text-md-start" id="scanner_input" ></input>
                                    </div>
                                    <div class="input-group my-2">
                                        <label for="scanner_quantity_input" class="input-group-text">' . lang('Quantity') . ':</label>
                                        <input type="number" min="1" value="1"  placeholder="' . lang('Quantity') . '" class="form-control text-center text-md-start" id="scanner_quantity_input" ></input>
                                    </div>
                                    <div class="text-center">
                                        <button type="button" onclick="read_manual()" class="btn btn-sm btn-white border my-1">' . lang('Read') . '</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 my-2 col-md">
                    <div class="card border-4 border-success">
                        <div id="action_logs" class="card-body overflow-auto" style="max-height:500px;">
                            <table class="table-hover table">
                                <thead>
                                    <tr>
                                        <th class="align-middle">' . lang('ID/SKU') . '</th>
                                        <th class="align-middle">' . lang('Short Description') . '</th>
                                        <th class="align-middle text-center">' . lang('Inventory Quantity') . '</th>
                                        <th class="align-middle text-center">' . lang('Added Quantity') . '</th>
                                        <th class="align-middle text-center">' . lang('New Inventory Quantity') . '</th>
                                        <th class="align-middle text-center">' . lang('Action Time') . '</th>
                                    </tr>
                                </thead>
                                <tbody><tr class="remove_this_first_scan"><td colspan="6" class="text-center">' . lang('No action yet') . '</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                $(window).focus(function(){
                    $("body").focus();
                });
                /*
                 * jQuery Scanner Detection
                 *
                 * Copyright (c) 2013 Julien Maurel
                 *
                 * Licensed under the MIT license:
                 * http://www.opensource.org/licenses/mit-license.php
                 *
                 * Project home:
                 * https://github.com/julien-maurel/jQuery-Scanner-Detection
                 *
                 * Version: 1.2.1
                 *
                 */
                !function(e){e.fn.scannerDetection=function(n){if("string"==typeof n)return this.each(function(){this.scannerDetectionTest(n)}),this;if(!1===n)return this.each(function(){this.scannerDetectionOff()}),this;var t={onComplete:!1,onError:!1,onReceive:!1,onKeyDetect:!1,timeBeforeScanTest:200,avgTimeByChar:30,minLength:2,endChar:[9,13],startChar:[],ignoreIfFocusOn:!1,scanButtonKeyCode:!0,scanButtonLongPressThreshold:3,onScanButtonLongPressed:!1,stopPropagation:!1,preventDefault:!1};return"function"==typeof n&&(n={onComplete:n}),n="object"!=typeof n?e.extend({},t):e.extend({},t,n),this.each(function(){var t=this,o=e(t),r=0,i=0,c="",s=!1,a=!1,f=0,u=function(){r=0,c="",f=0};t.scannerDetectionOff=function(){o.unbind("keydown.scannerDetection"),o.unbind("keypress.scannerDetection")},t.isFocusOnIgnoredElement=function(){if(!n.ignoreIfFocusOn)return!1;if("string"==typeof n.ignoreIfFocusOn)return e(":focus").is(n.ignoreIfFocusOn);if("object"==typeof n.ignoreIfFocusOn&&n.ignoreIfFocusOn.length)for(var t=e(":focus"),o=0;o<n.ignoreIfFocusOn.length;o++)if(t.is(n.ignoreIfFocusOn[o]))return!0;return!1},t.scannerDetectionTest=function(e){return e&&(r=i=0,c=e),f||(f=1),c.length>=n.minLength&&i-r<c.length*n.avgTimeByChar?(n.onScanButtonLongPressed&&f>n.scanButtonLongPressThreshold?n.onScanButtonLongPressed.call(t,c,f):n.onComplete&&n.onComplete.call(t,c,f),o.trigger("scannerDetectionComplete",{string:c}),u(),!0):(n.onError&&n.onError.call(t,c),o.trigger("scannerDetectionError",{string:c}),u(),!1)},o.data("scannerDetection",{options:n}).unbind(".scannerDetection").bind("keydown.scannerDetection",function(e){if(!1!==n.scanButtonKeyCode&&e.which==n.scanButtonKeyCode)f++,e.preventDefault(),e.stopImmediatePropagation();else if(r&&-1!==n.endChar.indexOf(e.which)||!r&&-1!==n.startChar.indexOf(e.which)){var i=jQuery.Event("keypress",e);i.type="keypress.scannerDetection",o.triggerHandler(i),e.preventDefault(),e.stopImmediatePropagation()}n.onKeyDetect&&n.onKeyDetect.call(t,e),o.trigger("scannerDetectionKeyDetect",{evt:e})}).bind("keypress.scannerDetection",function(e){this.isFocusOnIgnoredElement()||(n.stopPropagation&&e.stopImmediatePropagation(),n.preventDefault&&e.preventDefault(),r&&-1!==n.endChar.indexOf(e.which)?(e.preventDefault(),e.stopImmediatePropagation(),s=!0):r||-1===n.startChar.indexOf(e.which)?(void 0!==e.which&&(c+=String.fromCharCode(e.which)),s=!1):(e.preventDefault(),e.stopImmediatePropagation(),s=!1),r||(r=Date.now()),i=Date.now(),a&&clearTimeout(a),s?(t.scannerDetectionTest(),a=!1):a=setTimeout(t.scannerDetectionTest,n.timeBeforeScanTest),n.onReceive&&n.onReceive.call(t,e),o.trigger("scannerDetectionReceive",{evt:e}))})}),this}}(jQuery);
                $(document).scannerDetection({
                	timeBeforeScanTest: 200, // wait for the next character for upto 200ms
                	avgTimeByChar: 100, // its not a barcode if a character takes longer than 100ms
                	onComplete: function(barcode, qty){

                        if ($("input#scanner_input,input#scanner_quantity_input").is( ":focus" )) {
                            
                        }else{
                            
                            barcode = barcode.replace(/\*/g, "-");   
                            $("input#scanner_input").val(barcode);
                            var quantity = $("input#scanner_quantity_input").val();
                            request_barcode(barcode,quantity);
                          
                        }
                    } // main callback function	
                });
                
                function read_manual(){
                    var barcode = $("input#scanner_input").val();
                    var quantity = $("input#scanner_quantity_input").val();
                    request_barcode(barcode,quantity);
                }

                function request_barcode(barcode,quantity){
                    //we reset quantity to 1 if its not.
                    if($("input#scanner_quantity_input") != 1){
                        $("input#scanner_quantity_input").val(1);
                    }
                    
                    if(barcode != "" && quantity != "" && quantity >= 1){
                        $.ajax({
                            contentType: "application/json",
                            url: "barcode_increase_inventory.php?request=action",
                            data: JSON.stringify({
                                action: "update_inventory_quantity",
                                token: software_token,
                                barcode: barcode,
                                quantity: quantity
                            }),
                            type: "POST",
                            success: function(response) {
                                if(response.status == "success"){
                                    if( $(".remove_this_first_scan").length > 0){
                                        $(".remove_this_first_scan").remove();
                                    }
                                    var currentdate = new Date(); 
                                    var datetime = currentdate.getDate() + "/"
                                                + (currentdate.getMonth()+1)  + "/" 
                                                + currentdate.getFullYear() + " "  
                                                + currentdate.getHours() + ":"  
                                                + currentdate.getMinutes() + ":" 
                                                + currentdate.getSeconds();
                                    $("#action_logs tbody").prepend("<tr>\
                                        <td class=\'align-middle\'>" + response.data.name + "</td>\
                                        <td class=\'align-middle\'>" + response.data.short_description + "</td>\
                                        <td class=\'align-middle text-center\'>" + response.data.inventory_quantity + "</td>\
                                        <td class=\'align-middle text-center text-success fw-bolder\'>" + response.data.quantity + "</td>\
                                        <td class=\'align-middle text-center h5 fw-bolder \'>" + response.data.new_inventory_quantity + "</td>\
                                        <td class=\'align-middle \'>" + datetime + "</td>\
                                    </tr>");

                                }
                                if(response.status == "error"){
                                    alert(response.status + ": " + response.message);
                                }
                            },
                            error: function() {
                                alert("Please try again");
                            }
                        });
                    }
                }
            </script>
        </main>
    ' . output_footer();
}else{
// else this is a ajax request
    $request = json_decode(@file_get_contents('php://input'), true);
    include('init.php');
    // Add header in order to start response.
    header('Content-Type: application/json');
    // If a user was not found then respond with an error.
    if (!USER_LOGGED_IN) {
        respond(array(
            'status' => 'error',
            'message' => 'Invalid login.'));
    }
    // User Must be logged in.
    $user = validate_user();
    validate_ecommerce_access($user);

    switch ($user['role']){
        case '0':
            $role = lang('administrator');
        break;
        case '1':
            $role = lang('designer');
        break;
        case '2':
            $role = lang('manager');
        break;
        case '3':
            $role = lang('user');
        break;
    }
    $action = $request['action'];
    $token = $request['token'];
    switch ($action) {
        case 'update_inventory_quantity':
            $barcode = $request['barcode'];
            $quantity = $request['quantity'];
            $query = "SELECT 
            
                id,
                name,
                short_description,
                inventory,
                inventory_quantity
                FROM products
                WHERE name = '" . escape($barcode) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed');
            $row = mysqli_fetch_assoc($result);
            if (mysqli_num_rows($result) == 0){
                $response = array(
                    'status' => 'error',
                    'message' => lang('No product found with this barcode/sku/id.') );
                echo encode_json($response);
                exit();
                break;
            }
            $id = $row['id'];
            $name = $row['name'];
            $short_description = $row['short_description'];
            $inventory = $row['inventory'];
            $inventory_quantity = $row['inventory_quantity'];
            
            $new_inventory_quantity = $inventory_quantity + $quantity;

            $sql_out_of_stock = '';
            if($new_inventory_quantity <= 1 && $inventory == 1){
                $sql_out_of_stock = "out_of_stock = '0',";
            }


            // update the product
            $query =
                "UPDATE products
                SET
                    inventory_quantity = '" . escape($new_inventory_quantity) . "',
                    $sql_out_of_stock
                    user = '" . $user['id'] . "',
                    timestamp = UNIX_TIMESTAMP()
                WHERE name = '" . escape($barcode) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed');
            // we get data to use
            $data = array(
                'id' => $id,
                'name' => $name,
                'short_description' => $short_description,
                'inventory' => $inventory,
                'inventory_quantity' => $inventory_quantity,
                'new_inventory_quantity'=> $new_inventory_quantity,
                'quantity'=> '+' . $quantity,
            );
            //return success json output
            $response = array(
                'status' => 'success',
                'message' => lang('Transaction successfull'),
                'data' => $data
                );
            echo encode_json($response);  
            exit();
            break;
        
        default:
            $response = array(
                'status' => 'error',
                'message' => 'Invalid action.');
            echo encode_json($response);
            exit();
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
}
?>