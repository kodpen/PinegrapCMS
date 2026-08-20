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
$user = validate_user();
validate_ecommerce_access($user);

$liveform = new liveform('view_products');

// If the form has not been submitted yet, then output form.
if (!$_POST) {
    $enabled_options = array(
        lang(array('string'=>'Select to change {var:1}','vars'=>array(lang('Status')) )) => '',
        lang('Enable') => '1',
        lang('Disable') => '0'
    );
    if (ECOMMERCE_SHIPPING) {
        $zones = db_items(
            "SELECT
                id,
                name
            FROM zones
            ORDER BY name");
    }
	$price_change_method_options = array(
        lang(array('string'=>'Select a {var:1}','vars'=>array(lang('Price Change Method')) )) => '',
        lang(array('string'=>'increase (eg. + 30.00 {var:1})','vars'=>array(BASE_CURRENCY_SYMBOL) )) => '2',
        lang(array('string'=>'decrease (eg. - 30.00 {var:1})','vars'=>array(BASE_CURRENCY_SYMBOL) )) => '1',
        lang('increase percentage (eg. + 30%)') => '4',
        lang('decrease percentage (eg. - 30%)') => '3',
    );
    $inventory_options = array(
        lang(array('string'=>'Select a {var:1}','vars'=>array(lang('Option for Track Inventroy')) ))=>'',
        lang('Enable Track Inventory')=>'1',
        lang('Disable Track Inventory')=>'0',
    );
    $inventory_quantity_process_options = array(
        lang(array('string'=>'Select a {var:1}','vars'=>array(lang('Process')) ))=>'',
        lang('Enter an Inventory Quantity instead of the Current Inventory Quantity.')=>'value',
        lang('Increase Current Inventory Quantity(+)')=>'increase',
        lang('Decrease Current Inventory Quantity(-)')=>'decrease',
    );
    require('assets/templates/edit_products.php');

// Otherwise the form has been submitted, so process it.
} else {

    validate_token_field();

	$edit_change_price_method = $_POST['edit_change_price_method'];
	if(($edit_change_price_method == '1') || ($edit_change_price_method == '2')){
		$raw_price_value = $_POST['edit_price_value'];
		// remove commas from price
		$price_value = str_replace(',', '', $raw_price_value);
		// convert price from dollars to cents
		$price_value = $price_value * 100;
	}
	else if(($edit_change_price_method == '3') || ($edit_change_price_method == '4')){
		$price_value = $_POST['edit_price_value'];
	}

    // If at least one product was selected then continue.
    if ($_POST['products']) {
        $number_of_products = 0;
        
        switch ($_POST['action']) {
            // If products are being edited, proceed.
            case 'edit':
                // If at least one action was selected, then continue.
                if (
                    ($_POST['edit_enabled'] != '')
                    || ($_POST['edit_allowed_zones'])
                    || ($_POST['edit_disallowed_zones'])
                    || ($_POST['edit_inventory'] != '')
                    || ($_POST['edit_inventory_quantity'] != '')
					|| ($_POST['edit_change_price_method'] != '')
                ) {
                    if ($_POST['edit_allowed_zones']) {
                        $allowed_zones = explode(',', $_POST['edit_allowed_zones']);
                    } else {
                        $allowed_zones = array();
                    }
                    
                    if ($_POST['edit_disallowed_zones']) {
                        $disallowed_zones = explode(',', $_POST['edit_disallowed_zones']);
                    } else {
                        $disallowed_zones = array();
                    }

					$number_of_undecerease_products =0;
					
                    // Loop through selected products in order to edit them.
                    foreach ($_POST['products'] as $product_id) {
                        $sql_enabled = '';
					
						if($edit_change_price_method != ''){
							$query = "SELECT 
                            products.id as id, 
                            products.price as price,
                            products.name as name,
                            price,
                            products.short_description as short_description 
                            FROM products  WHERE id = '" . escape($product_id) . "'";
							$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
							$row = mysqli_fetch_assoc($result);
							$price = $row['price'];
						}	
						
                        // If the status pick list was selected, then update status for product.
                        if ($_POST['edit_enabled'] != '') {
                            // Delete any existing tag cloud data for this product.
                            db("DELETE FROM tag_cloud_keywords WHERE (item_id = '" . escape($product_id) . "') AND (item_type = 'product')");

                            // If products are being enabled, and there are xref records for this product,
                            // then deal with adding keywords for tag clouds.
                            if (
                                ($_POST['edit_enabled'] == '1')
                                && (db_value("SELECT COUNT(*) FROM tag_cloud_keywords_xref WHERE (item_id = '" . escape($product_id) . "') AND (item_type = 'product')") > 0)
                            ) {
                                // Create an array of keywords.
                                $keywords = explode(',', db_value("SELECT keywords FROM products WHERE id = '" . escape($product_id) . "'"));

                                // Remove spaces from the beginning and end of all keywords.
                                $keywords = array_map('trim', $keywords);

                                // Remove blank keywords.
                                $keywords = array_filter($keywords, 'strlen');

                                // Remove duplicate keywords.
                                $keywords = array_unique($keywords);

                                // Loop through the keywords in order to add them to database.
                                foreach ($keywords as $keyword) {
                                    db(
                                        "INSERT INTO tag_cloud_keywords 
                                        (
                                            keyword, 
                                            item_id, 
                                            item_type
                                        ) VALUES (
                                            '" . escape($keyword) . "',
                                            '" . escape($product_id) . "',
                                            'product')");
                                }
                            }

                            $sql_enabled = "enabled = '" . e($_POST['edit_enabled'] ?? '') . "',";
                        }

						// Price Update
						$sql_new_prices ='';
						if($edit_change_price_method != ''){
							if($edit_change_price_method == '1'){
								$price = $row['price'];
								$price = $price - $price_value;
								if($price <= '0'){
									$price =  $row['price'];
									$number_of_undecerease_products++;
									$liveform->mark_error('Update Error', lang(array( 'string'=>'{var:1} product(s) price cant be decerease. Product price equal or smaller than 0!','vars'=>array($number_of_undecerease_products) )) ); 
								}
								$sql_new_prices = "price = '" . e($price) . "',";
							}
							else if($edit_change_price_method == '2'){
								$price = $row['price'];
								$price = $price + $price_value;
								$sql_new_prices = "price = '" . e($price) . "',";
							} 
							else if($edit_change_price_method == '3') {
								$price = $row['price'];
								$price = ((100 - $price_value) / 100) * $price;
								if($price <= '0'){
									$price =  $row['price'];
									$number_of_undecerease_products++;
									$liveform->mark_error('Update Error', lang(array( 'string'=>'{var:1} product(s) price cant be decerease. Product price equal or smaller than 0!','vars'=>array($number_of_undecerease_products) )) ); 
								}
								$sql_new_prices = "price = '" . e($price) . "',";
							}
							else if($edit_change_price_method == '4') {
								$price = $row['price'];
								$price = ((100 + $price_value) / 100) * $price;
								$sql_new_prices = "price = '" . e($price) . "',";
							}
						}
                    
                        $sql_inventory_quantity = '';
                        $new_inventory_quantity = '';
                        // if inventroy quantity and is process is not empty
                        if($_POST['edit_inventory_quantity_process'] != '' && $_POST['edit_inventory_quantity'] != ''){
                            $query = "SELECT 
                            products.id as id, 
                            inventory,
                            inventory_quantity
                            FROM products  WHERE id = '" . escape($product_id) . "'";
                            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                            $row = mysqli_fetch_assoc($result);
                            $inventory_quantity = $row['inventory_quantity'];
                            // its value, item inventory_quantity update with new value
                            if($_POST['edit_inventory_quantity_process'] == 'value'){
                                $sql_inventory_quantity = "inventory_quantity = '" . e($_POST['edit_inventory_quantity'] ?? '') . "',";
                                $new_inventory_quantity = $_POST['edit_inventory_quantity'];
                            // else its not value we check old value and process
                            }else if($_POST['edit_inventory_quantity_process'] == 'increase'){
                                $sql_inventory_quantity = "inventory_quantity = '" . e($inventory_quantity + $_POST['edit_inventory_quantity']) . "',";
                                $new_inventory_quantity = $inventory_quantity + $_POST['edit_inventory_quantity'];
                            }else if($_POST['edit_inventory_quantity_process'] == 'decrease'){
                                // if new value is bigger than old we output 0
                                if(  $inventory_quantity <= $_POST['edit_inventory_quantity'] ){
                                    $sql_inventory_quantity = "inventory_quantity = '0',";
                                    $new_inventory_quantity = '0';
                                }else{
                                    $sql_inventory_quantity = "inventory_quantity = '" . e($inventory_quantity - $_POST['edit_inventory_quantity']) . "',";
                                    $new_inventory_quantity = $inventory_quantity - $_POST['edit_inventory_quantity'];
                                }
                            }
                        }

                        // if user select inventroy option value, we set value to sql inventory.
                        $sql_inventory = '';
                        if($_POST['edit_inventory'] != ''){
                            $sql_inventory = "inventory = '" . e($_POST['edit_inventory'] ?? '') . "',";
                        }                        
                        // if inventroy tracking is (0/1) disabled or new quantity is bigger than 0 we remove out_of_stock status
                        $sql_out_of_stock = '';
                        if ($_POST['edit_inventory'] == 0 || ($new_inventory_quantity != '' && $new_inventory_quantity > 0) ) {
                            $sql_out_of_stock = "out_of_stock = '0',";
                        }

                        // Loop through all checked allowed zones, in order to allow product for them.
                        foreach ($allowed_zones as $zone_id) {
                            // If this product is not already allowed for this zone, then allow it.
                            if (
                                !db_value(
                                    "SELECT product_id
                                    FROM products_zones_xref
                                    WHERE
                                        (product_id = '" . e($product_id) . "')
                                        AND (zone_id = '" . e($zone_id) . "')")
                            ) {
                                db(
                                    "INSERT INTO products_zones_xref (
                                        product_id,
                                        zone_id)
                                    VALUES (
                                        '" . e($product_id) . "',
                                        '" . e($zone_id) . "')");
                            }
                        }
                        
                        // Loop through all checked disallowed zones
                        // so we can disallow this product for each one.
                        foreach ($disallowed_zones as $zone_id) {
                            db(
                                "DELETE FROM products_zones_xref
                                WHERE
                                    (product_id = '" . e($product_id) . "')
                                    AND (zone_id = '" . e($zone_id) . "')");
                        }

                        // Update product enabled property, if necessary, and timestamp.
                        db(
                            "UPDATE products
                            SET
                                $sql_enabled
								$sql_new_prices
                                $sql_inventory_quantity
                                $sql_inventory
                                $sql_out_of_stock
                                timestamp = UNIX_TIMESTAMP(),
                                user = '" . USER_ID . "'
                            WHERE id = '" . escape($product_id) . "'");

                        $number_of_products++;
						
                    }
                    
                    // If at least one product was modified, then log activity.
                    if ($number_of_products > 0) {
                        $log_message = '';
                        
                        // If the enabled property was selected to be edited then set message for log.
                        if ($_POST['edit_enabled'] != '') {
                            // If the log message is not blank, then add separator.
                            if ($log_message != '') {
                                $log_message .= ',';
                            }

                            if ($number_of_products == 1) {
                                $verb = 'was';
                            } else {
                                $verb = 'were';
                            }

                            if ($_POST['edit_enabled'] == '1') {
                                $log_message .= lang($verb . ' enabled');
                            } else {
                                $log_message .= lang($verb . ' disabled');
                            }
                        }


                        // If the inventory property was selected to be edited then set message for log.
                        if ($_POST['edit_inventory'] != '') {
                            // If the log message is not blank, then add separator.
                            if ($log_message != '') {
                                $log_message .= ',';
                            }

                            if ($number_of_products == 1) {
                                $verb = 'was';
                            } else {
                                $verb = 'were';
                            }

                            if ($_POST['edit_inventory'] == '1') {
                                $log_message .= lang($verb . ' set stock tracking enabled');
                            } else {
                                $log_message .= lang($verb . ' set stock tracking disabled');
                            }
                        }
                    
                        // If the edit_inventory_quantity_process property was selected to be edited and edit_inventory_quantity has value then set message for log.
                        if($_POST['edit_inventory_quantity_process'] != '' && $_POST['edit_inventory_quantity'] != ''){
                            // If the log message is not blank, then add separator.
                            if ($log_message != '') {
                                $log_message .= ',';
                            }

                            if ($number_of_products == 1) {
                                $verb = 'was';
                            } else {
                                $verb = 'were';
                            }
                            $log_message .= lang(' had inventory quantity changed');
                        }




                        // If there were allowed or disallowed zones then set message for log.
                        if (
                            ($_POST['edit_allowed_zones'])
                            || ($_POST['edit_disallowed_zones'])
                        ) {
                            // If the log message is not blank, then add separator.
                            if ($log_message != '') {
                                $log_message .= ',';
                            }

                            $log_message .=  lang(' had shipping zones allowed/disallowed');
                        }


						// If the enabled property was selected to be edited then set message for log.
                        if ($edit_change_price_method != '')  {
                            // If the log message is not blank, then add separator.
                            if ($log_message != '') {
                                $log_message .= ',';
                            }
                            $log_message .= lang(' had price changed');

                        }

                        // If there is a log message, then log it and add notice.
                        if ($log_message != '') {
                            $plural_suffix = '';

                            if ($number_of_products > 1) {
                                $plural_suffix = 's';
                            }
                            log_activity(lang(array('string'=>'{var:1} product{suffix:1} {var:2}.','vars'=>array($number_of_products,$log_message),'suffix'=>$plural_suffix )), $_SESSION['sessionusername']);
                            $liveform->add_notice(lang(array('string'=>'{var:1} product{suffix:1} {var:2}.','vars'=>array($number_of_products,h($log_message)),'suffix'=>$plural_suffix )));
                        }
                    }
                }
                
                break;

            // Assign a new barcode to each selected product.
            case 'assign_barcodes':
                if (defined('BARCODE_ENABLED') && BARCODE_ENABLED && !empty($_POST['products'])) {
                    $bc_type  = BARCODE_DEFAULT_TYPE;
                    $assigned = 0;
                    $now      = date('Y-m-d H:i:s');

                    foreach ($_POST['products'] as $pid) {
                        $pid = (int)$pid;
                        if (!$pid) continue;

                        // Skip products that already have at least one barcode.
                        if (db_value("SELECT COUNT(*) FROM product_barcodes WHERE product_id = '" . e($pid) . "'")) continue;

                        $attempts = 0; $barcode = ''; $exists = 1;
                        do {
                            if ($bc_type === 'EAN13') {
                                $d = ''; for ($i = 0; $i < 12; $i++) $d .= rand(0, 9);
                                $s = 0; for ($i = 0; $i < 12; $i++) $s += ($i % 2 === 0 ? 1 : 3) * (int)$d[$i];
                                $barcode = $d . ((10 - ($s % 10)) % 10);
                            } elseif ($bc_type === 'UPC') {
                                $d = ''; for ($i = 0; $i < 11; $i++) $d .= rand(0, 9);
                                $s = 0; for ($i = 0; $i < 11; $i++) $s += ($i % 2 === 0 ? 3 : 1) * (int)$d[$i];
                                $barcode = $d . ((10 - ($s % 10)) % 10);
                            } else {
                                $barcode = str_pad($pid, 5, '0', STR_PAD_LEFT) . str_pad(rand(0, 9999999), 7, '0', STR_PAD_LEFT);
                            }
                            $exists = db_value("SELECT COUNT(*) FROM product_barcodes WHERE barcode = '" . e($barcode) . "'");
                            $attempts++;
                        } while ($exists && $attempts < 30);

                        if ($exists) continue; // skip if no unique barcode found

                        db("INSERT INTO product_barcodes (product_id, barcode, barcode_type, created_at, updated_at)
                            VALUES ('" . e($pid) . "', '" . e($barcode) . "', '" . e($bc_type) . "', '" . e($now) . "', '" . e($now) . "')");
                        $assigned++;
                    }

                    if ($assigned > 0) {
                        log_activity(lang(array('string' => '{var:1} barcode(s) assigned.', 'vars' => array($assigned))));
                        $liveform->add_notice(lang(array('string' => '{var:1} barcode(s) assigned.', 'vars' => array($assigned))));
                    }
                }
                break;

            // If products are being deleted, then delete them.
            case 'delete':
                $number_of_products = 0;

                foreach ($_POST['products'] as $product_id) {
                    // Cast once, up front. Everything below interpolates this
                    // value into SQL and several of the statements did so raw,
                    // straight out of $_POST.
                    $product_id = (int) $product_id;

                    $query = "DELETE FROM products ".
                             "WHERE id = '$product_id'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    // Stored SEO findings for this product. Guarded on the
                    // tables existing, because a product can be deleted on an
                    // installation that has not run the upgrades that create
                    // them.
                    //
                    // The seo_link rows matter in both directions: a link from
                    // a deleted product's description still counts as an
                    // incoming link when the graph decides whether a page is
                    // an orphan, so leaving it behind hides a real orphan
                    // forever.
                    if (db_item("SHOW TABLES LIKE 'seo_issue'")) {
                        db("DELETE FROM seo_issue WHERE (entity_type = 'product') AND (entity_id = '$product_id')");
                    }

                    if (db_item("SHOW TABLES LIKE 'seo_link'")) {
                        db("DELETE FROM seo_link WHERE (from_type = 'product') AND (from_id = '$product_id')");
                        db("DELETE FROM seo_link WHERE (to_type = 'product') AND (to_id = '$product_id')");
                    }
                    
                    // delete product references in products_groups_xref
                    $query = "DELETE FROM products_groups_xref ".
                             "WHERE product = '$product_id'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    
                    // delete product references in products_zones_xref
                    $query = "DELETE FROM products_zones_xref ".
                             "WHERE product_id = '$product_id'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    
                    // delete form fields for this product
                    $query = "DELETE FROM form_fields WHERE (product_id = '" . $product_id . "') AND (product_id != '0')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    
                    // delete form field options for this product
                    $query = "DELETE FROM form_field_options WHERE (product_id = '" . $product_id . "') AND (product_id != '0')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    // Delete target options for this product.
                    db("DELETE FROM target_options WHERE (product_id = '" . $product_id . "') AND (product_id != '0')");

                    db("DELETE FROM products_images_xref WHERE product = '" . e($product_id) . "'");

                    db("DELETE FROM products_attributes_xref WHERE product_id = '" . e($product_id) . "'");

                    db("DELETE FROM product_submit_form_fields WHERE (product_id = '" . e($product_id) . "')");
                    
                    // delete all of the keywords for this product
                    $query = "DELETE FROM tag_cloud_keywords WHERE (item_id = '" . escape($product_id) . "') AND (item_type = 'product')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    
                    // delete all of the keywords xref records for this product
                    $query = "DELETE FROM tag_cloud_keywords_xref WHERE (item_id = '" . escape($product_id) . "') AND (item_type = 'product')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

                    // Check if this product has short links, in order to determine if we need to delete them.
                    $query =
                        "SELECT COUNT(*)
                        FROM short_links
                        WHERE
                            (destination_type = 'product')
                            AND (product_id = '" . escape($product_id) . "')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    $row = mysqli_fetch_row($result);

                    // If a short link exists, then delete short links for this product.
                    if ($row[0] != 0) {
                        $query =
                            "DELETE FROM short_links
                            WHERE
                                (destination_type = 'product')
                                AND (product_id = '" . escape($product_id) . "')";
                        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    }

                    // Delete offer rule associations with product.
                    db("DELETE FROM offer_rules_products_xref WHERE product_id = '" . e($product_id) . "'");
                    
                    $number_of_products++;
                }
                
                // If at least one product was deleted, then log this information and output a notice.
                if ($number_of_products > 0) {
                    if ($number_of_products == 1) {
                        $plural_suffix = '';
                        $verb = 'was';
                    } else {
                        $plural_suffix = 's';
                        $verb = 'were';
                    }

                    log_activity(lang(array('string'=>'{var:1} product{suffix:1} ' . $verb . ' deleted','vars'=>array($number_of_products),'suffix'=>$plural_suffix )), $_SESSION['sessionusername']);
                    $liveform->add_notice(lang(array('string'=>'{var:1} product{suffix:1} ' . $verb . ' deleted','vars'=>array($number_of_products),'suffix'=>$plural_suffix )));
                }
                
                break;
        }
    }

    go(PATH . SOFTWARE_DIRECTORY . '/view_products.php');
}