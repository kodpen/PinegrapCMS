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
include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

$liveform = new liveform('view_products');

// Modify Selected asks for its changes in a dialog of the products list
// and of the product group screen (pg_product_modify_dialog()); this file
// only takes the post.
if (!$_POST) {
    go(PATH . SOFTWARE_DIRECTORY . '/view_products.php');

// Otherwise the form has been submitted, so process it.
} else {

    validate_token_field();

    // The products the list's selection stands for, read with the list's own
    // query and the user's rights (pg_product_list_selected_ids()): the rows
    // picked one by one, or the whole view by its condition. A product in the
    // Recycle Bin is on no view, so it is never among them.
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/product_list.php');
    $product_ids = pg_product_list_selected_ids($user, $_POST['selection'] ?? '', $_POST['list_params'] ?? '');

    if ($product_ids === false) {
        output_error(lang('Query failed.'));
    }

    if ($product_ids === null) {
        output_error(lang('Too many products are selected. Select fewer products and try again.') . ' <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
    }

    // The shared readers of the price amount and the stock quantity.
    require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_products_bulk.php');

	$edit_change_price_method = (string) ($_POST['edit_change_price_method'] ?? '');
	$price_value = 0;
	$price_value_refused = false;
	if(in_array($edit_change_price_method, array('1', '2', '3', '4'), true)){
		// The amount is read the panel's way (1.250 on a Turkish panel is
		// twelve hundred and fifty), as the Catalog Manager's bulk price change
		// reads it; it is a number above zero, the method says which way it goes.
		$price_value = pg_products_mass_price_value($edit_change_price_method, $_POST['edit_price_value'] ?? '');
		if($price_value === null){
			$price_value = 0;
			$price_value_refused = true;
			$edit_change_price_method = '';
			// Numbered, so a tax rate error in the same submit does not replace it.
			$liveform->add_error(lang('Enter an amount for the price change.'));
		}
	}

    // A stock process with no figure, a tax rate to set with none or one
    // outside 0..100, or a zone both allowed and disallowed stops the whole
    // change before any product is touched, with its own message.
    if (($_POST['action'] ?? '') === 'edit') {
        $mass_edit_problem = pg_products_mass_edit_problem($_POST);
        $mass_edit_messages = array(
            'quantity_missing' => lang('Enter an inventory quantity.'),
            'tax_rate_missing' => lang('Enter a tax rate, or choose to follow the tax zone.'),
            'tax_rate_range' => lang('Enter a tax rate between 0 and 100.'),
            'zones_overlap' => lang('A shipping zone cannot be both allowed and disallowed.'),
        );

        if ($mass_edit_problem !== '') {
            $liveform->add_error($mass_edit_messages[$mass_edit_problem]);
            go(PATH . SOFTWARE_DIRECTORY . '/view_products.php');
        }
    }

    // The stock quantity is read once, the panel's way (1.200 on a Turkish
    // panel is twelve hundred), with the Catalog Manager's reader. A figure
    // that is not a whole count of zero or more stops the whole change
    // before any product is touched: MySQL would keep 1 of 1.200.

    $quantity_process = (string) ($_POST['edit_inventory_quantity_process'] ?? '');
    $quantity_value = null;

    if (!in_array($quantity_process, array('value', 'increase', 'decrease'), true)) {
        $quantity_process = '';
    }

    if (($quantity_process !== '') && (trim((string) ($_POST['edit_inventory_quantity'] ?? '')) !== '')) {
        $quantity_value = pg_products_mass_quantity_value($_POST['edit_inventory_quantity']);

        if ($quantity_value === null) {
            $liveform->add_error(lang('Enter an inventory quantity.'));
            go(PATH . SOFTWARE_DIRECTORY . '/view_products.php');
        }

        // Past what products.inventory_quantity holds: MySQL would store the
        // column's top without a word.
        if (pg_inventory_quantity_too_large($quantity_value)) {
            $liveform->add_error(lang(array('string' => '{var:1} is too large.', 'vars' => array(lang('Inventory Quantity')))));
            go(PATH . SOFTWARE_DIRECTORY . '/view_products.php');
        }
    }

    // With stock locations the quantity goes to one location (0 = the
    // default) through the writer, which keeps the total the sum.
    $quantity_by_location = ($quantity_value !== null) && (bool) pg_stock_screen_locations();
    $quantity_location = (int) ($_POST['edit_inventory_location'] ?? 0);

    // If at least one product was selected then continue.
    if ($product_ids) {
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
                    || ($quantity_value !== null)
					|| (($_POST['edit_change_price_method'] != '') && !$price_value_refused)
                    || (($_POST['edit_tax_rate_method'] ?? '') != '')
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

                    $number_of_unchanged_price_products = 0;
                    $number_of_too_large_price_products = 0;
                    $number_of_too_large_quantity_products = 0;
                    $number_of_price_changed_products = 0;
                    $number_of_binned_products = 0;
                    $change_besides_price = pg_products_mass_change_besides_price($_POST, $quantity_value);

                    // Loop through selected products in order to edit them.
                    foreach ($product_ids as $product_id) {
                        // A product in the Recycle Bin is not changed, as on
                        // Edit Product and in the Catalog Manager: the list
                        // does not show one, but a selection made before it
                        // was binned in another tab still posts it.
                        if (pg_catalog_product_binned($product_id)) {
                            $number_of_binned_products++;
                            continue;
                        }

                        $sql_enabled = '';

                        $audit_before = pg_audit_fetch('products', $product_id);
					
						if($edit_change_price_method != ''){
							$query = "SELECT 
                            products.id as id, 
                            products.price as price,
                            products.name as name,
                            price,
                            products.short_description as short_description 
                            FROM products  WHERE id = ?";
							$result = db_q($query, array((string) $product_id)) or output_error('Query failed.');
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

                            // The SEO keyword check applies only to an enabled product
                            // and only stale rows are recalculated, as the product
                            // screen save and the list's quick edit do.
                            $sql_enabled = "enabled = '" . e($_POST['edit_enabled'] ?? '') . "', seo_analysis_current = '0',";
                        }

						// Price Update
						$sql_new_prices ='';
						// A change that would lower a price to zero or below keeps
						// the old price, whichever method (pg_bulk_price_change()).
						if(in_array((string) $edit_change_price_method, array('1', '2', '3', '4'), true)){
							$change = pg_bulk_price_change($row['price'], $edit_change_price_method, $price_value);
							$price = $change['price'];
							if($change['refused']){
								// A result past what products.price holds is
								// refused with its own count, not cut to the top.
								if ($change['too_large']) {
									$number_of_too_large_price_products++;
									$liveform->mark_error('Price Too Large', lang(array( 'string'=>'{var:1} product(s) were not changed because their new price would be too large.','vars'=>array($number_of_too_large_price_products) )) );
								} else {
									$number_of_unchanged_price_products++;
									$liveform->mark_error('Update Error', lang(array( 'string'=>'{var:1} product(s) were not changed because their price would fall to zero or below.','vars'=>array($number_of_unchanged_price_products) )) );
								}

								// Nothing else was asked for this product: it
								// is neither written nor counted as updated.
								if (!$change_besides_price) {
									continue;
								}
							} else {
								$sql_new_prices = "price = '" . e($price) . "',";
								$number_of_price_changed_products++;
							}
						}
                    
                        $sql_inventory_quantity = '';
                        $new_inventory_quantity = null;
                        // The stock as stored, read once when this edit may
                        // move the count or the tracking.
                        $stock_stored = null;
                        if (($quantity_value !== null) || (($_POST['edit_inventory'] ?? '') !== '')) {
                            $result = db_q("SELECT inventory, inventory_quantity, out_of_stock FROM products WHERE id = ?", array((string) $product_id)) or output_error('Query failed.');
                            $stock_stored = mysqli_fetch_assoc($result);
                        }
                        // The new count, worked out from the stored one by the
                        // Catalog Manager's rule: never below zero.
                        if (($quantity_value !== null) && !$quantity_by_location) {
                            $inventory_quantity = (int) ($stock_stored['inventory_quantity'] ?? 0);
                            $new_inventory_quantity = pg_explorer_bulk_product_new_quantity($inventory_quantity, $quantity_process, $quantity_value);

                            // An increase past what the column holds keeps the
                            // stock as it is and is counted, not cut to the top.
                            if (pg_inventory_quantity_too_large($new_inventory_quantity)) {
                                $number_of_too_large_quantity_products++;
                                $new_inventory_quantity = null;

                                // Nothing else was asked for this product: it
                                // is neither written nor counted as updated.
                                if (($sql_new_prices === '') && !pg_products_mass_change_besides_price($_POST, null)) {
                                    continue;
                                }
                            } else {
                                $new_inventory_quantity = (int) $new_inventory_quantity;
                                $sql_inventory_quantity = "inventory_quantity = '" . $new_inventory_quantity . "',";
                            }
                        }

                        // Stock tracking on or off, when it was chosen.
                        $sql_inventory = '';
                        if($_POST['edit_inventory'] != ''){
                            $sql_inventory = "inventory = '" . (((string) $_POST['edit_inventory'] === '1') ? '1' : '0') . "',";
                        }                        
                        // The tax rate is set or cleared, never nudged: a rate is
                        // a statement about what the article is, not a quantity to
                        // step up and down. Clearing writes NULL rather than zero,
                        // because the two mean different things - follow the zone,
                        // versus zero-rated.
                        $sql_tax_rate = '';
                        $tax_rate_method = $_POST['edit_tax_rate_method'] ?? '';

                        if ($tax_rate_method === 'zone') {
                            $sql_tax_rate = "tax_rate = NULL,";
                        } elseif ($tax_rate_method === 'set') {
                            $new_tax_rate = parse_tax_rate($_POST['edit_tax_rate_value'] ?? '');

                            // A missing or out-of-range rate was refused above
                            // (pg_products_mass_edit_problem()).
                            if ($new_tax_rate !== NULL) {
                                $sql_tax_rate = "tax_rate = '" . e($new_tax_rate) . "',";
                            }
                        }

                        // The out-of-stock mark by the shared rule: a tracked
                        // product left with nothing is marked (stamped the day
                        // it ran out), tracking off or stock above zero clears
                        // it. An untouched tracking choice stays '' (strict
                        // test: on PHP 7 '' == 0 read as "tracking off").
                        $sql_out_of_stock = '';
                        if ($stock_stored) {
                            $tracking_choice = (string) ($_POST['edit_inventory'] ?? '');
                            if ($tracking_choice !== '') {
                                $tracking_choice = ($tracking_choice === '1') ? '1' : '0';
                            }
                            $sql_out_of_stock = pg_products_bulk_out_of_stock_sql($stock_stored, $tracking_choice, $new_inventory_quantity);
                            if ($sql_out_of_stock !== '') {
                                $sql_out_of_stock .= ',';
                            }
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

                        // The stock as stored, when this edit may move it: compared
                        // with the row after the write to announce the products whose
                        // stock really moved. A selection on the list is a few rows
                        // an operator changed on purpose, not a product import, so
                        // each one is announced the way a single save is.
                        $stock_was = (($sql_inventory_quantity !== '') || ($sql_inventory !== '') || $quantity_by_location)
                            ? db_item("SELECT inventory, inventory_quantity FROM products WHERE id = '" . escape($product_id) . "'")
                            : null;

                        // Update product enabled property, if necessary, and timestamp.
                        db(
                            "UPDATE products
                            SET
                                $sql_enabled
								$sql_new_prices
                                $sql_inventory_quantity
                                $sql_inventory
                                $sql_out_of_stock
                                $sql_tax_rate
                                timestamp = UNIX_TIMESTAMP(),
                                user = '" . USER_ID . "'
                            WHERE id = '" . escape($product_id) . "'");

                        pg_audit_record('update', 'products', $product_id, $audit_before);

                        // The quantity at the chosen location, once tracking
                        // is written. A product that does not track stock has
                        // no rows and is left alone, a bundle follows its
                        // components; one the change would take past the top
                        // is counted.
                        if ($quantity_by_location && !pg_bundle_ids_among(array((int) $product_id))) {
                            $location_result = pg_stock_bulk_write($product_id, $quantity_location, $quantity_process, $quantity_value, 'bulk_edit');

                            if ($location_result['error'] === 'too_many') {
                                $number_of_too_large_quantity_products++;
                            }

                            pg_stock_refresh_out_of_stock($product_id);
                        }

                        // A bundle's stock (and a summed price) is worked
                        // out from its components: whatever this edit wrote
                        // there is put back (includes/fn/bundles.php).
                        pg_bundle_refresh((int) $product_id);

                        if ($sql_new_prices !== '') {
                            pg_price_history_queue($product_id, 'panel');
                        }

                        // Price or stock may have moved on this row. Queued per
                        // product rather than once for the selection, because
                        // the queue is keyed on the product and folds repeats.
                        if (function_exists('pg_marketplace_product_changed')) {
                            pg_marketplace_product_changed((int)$product_id);
                        }

                        if ($stock_was) {
                            $stock_now = db_item("SELECT inventory, inventory_quantity FROM products WHERE id = '" . escape($product_id) . "'");
                            $stock_delta = $stock_now ? pg_product_stock_delta(
                                ((string) $stock_was['inventory'] === '1'),
                                $stock_was['inventory_quantity'],
                                ((string) $stock_now['inventory'] === '1'),
                                $stock_now['inventory_quantity']) : null;

                            if ($stock_delta) {
                                pg_product_stock_changed((int) $product_id, $stock_delta[0], $stock_delta[1], 'bulk_edit');
                            }
                        }

                        $number_of_products++;
						
                    }
                    
                    // A history row for each product whose price moved, none
                    // for one left as it was (a decrease that would have gone
                    // to zero or below keeps its price).
                    pg_price_history_flush();

                    if ($number_of_binned_products > 0) {
                        $liveform->add_error(lang(array('string' => '{var:1} product(s) were not changed because they are in the Recycle Bin.', 'vars' => array($number_of_binned_products))));
                    }

                    if ($number_of_too_large_quantity_products > 0) {
                        $liveform->add_error(lang(array('string' => '{var:1} product(s) kept their stock quantity because the new quantity would be too large.', 'vars' => array($number_of_too_large_quantity_products))));
                    }

                    // If at least one product was modified, then log activity.
                    if ($number_of_products > 0) {
                        // Each change is a verb-free phrase; the count and
                        // the verb live in one notice key, so the sentence
                        // can be translated as a whole.
                        $changes = array();

                        if ($_POST['edit_enabled'] != '') {
                            $changes[] = ($_POST['edit_enabled'] == '1') ? lang('enabled') : lang('disabled');
                        }

                        if ($_POST['edit_inventory'] != '') {
                            $changes[] = ($_POST['edit_inventory'] == '1') ? lang('stock tracking on') : lang('stock tracking off');
                        }

                        if ($quantity_value !== null) {
                            $changes[] = lang('stock quantity changed');
                        }

                        if (
                            ($_POST['edit_allowed_zones'])
                            || ($_POST['edit_disallowed_zones'])
                        ) {
                            $changes[] = lang('shipping zones changed');
                        }

                        // Only when a price really moved: a refused cut
                        // keeps the old price and has its own error.
                        if ($number_of_price_changed_products > 0) {
                            $changes[] = lang('price changed');
                        }

                        if ($sql_tax_rate != '') {
                            $changes[] = ($tax_rate_method === 'zone') ? lang('tax rate cleared') : lang('tax rate changed');
                        }

                        if ($changes) {
                            if ($number_of_products == 1) {
                                $message = lang(array('string' => '{var:1} product was updated ({var:2}).', 'vars' => array($number_of_products, implode(', ', $changes))));
                            } else {
                                $message = lang(array('string' => '{var:1} products were updated ({var:2}).', 'vars' => array($number_of_products, implode(', ', $changes))));
                            }

                            log_activity($message, $_SESSION['sessionusername']);
                            $liveform->add_notice(h($message));
                        }
                    }
                } elseif (!$price_value_refused) {
                    // Nothing was chosen: the list comes back with a word
                    // rather than silently, as if something had changed.
                    $liveform->add_error(lang('Select a field to change.'));
                }
                
                break;

            // Assign a new barcode to each selected product.
            case 'assign_barcodes':
                if (defined('BARCODE_ENABLED') && BARCODE_ENABLED && $product_ids) {
                    $bc_type  = BARCODE_DEFAULT_TYPE;
                    $assigned = 0;
                    $now      = date('Y-m-d H:i:s');

                    foreach ($product_ids as $pid) {
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
                            // Taken when another barcode or another product's
                            // code is the same: a scan would reach either.
                            $exists = db_value("SELECT COUNT(*) FROM product_barcodes WHERE barcode = '" . e($barcode) . "'")
                                || pg_product_name_holder($barcode, $pid, false);
                            $attempts++;
                        } while ($exists && $attempts < 30);

                        if ($exists) continue; // skip if no unique barcode found

                        db("INSERT INTO product_barcodes (product_id, barcode, barcode_type, created_at, updated_at)
                            VALUES ('" . e($pid) . "', '" . e($barcode) . "', '" . e($bc_type) . "', '" . e($now) . "', '" . e($now) . "')");
                        $assigned++;
                    }

                    if ($assigned > 0) {
                        log_activity(lang(array('string' => '{var:1} barcode(s) assigned.', 'vars' => array($assigned))), $_SESSION['sessionusername']);
                        $liveform->add_notice(lang(array('string' => '{var:1} barcode(s) assigned.', 'vars' => array($assigned))));
                    }
                }
                break;

            // If products are being deleted, then delete them.
            case 'delete':
                $number_of_products = 0;
                $number_of_components = 0;

                // To the Recycle Bin when the catalogue has one, the way the
                // Catalog Manager deletes: a product deleted by mistake from
                // this list comes back with its images, groups, prices and
                // links from the Catalog Manager's Recycle Bin. Emptying the
                // bin runs the permanent delete. Before the upgrade that adds
                // the bin, the permanent delete is all there is.
                $binning = pg_catalog_bin_ready();

                if ($binning == false) {
                    include_once('product_builder.php');
                }

                foreach ($product_ids as $product_id) {
                    // Cast once, up front: the value comes straight out of $_POST.
                    $product_id = (int) $product_id;

                    if ($product_id <= 0) {
                        continue;
                    }

                    // A bundle component stays: both paths below refuse it, and
                    // it is counted apart so the screen says why it is still listed.
                    if (pg_bundle_is_component($product_id)) {
                        $number_of_components++;
                        continue;
                    }

                    $audit_before = pg_audit_fetch('products', $product_id);

                    if ($binning) {

                        // False for a product that is gone or already in the
                        // bin; neither is counted as deleted here.
                        if (pg_catalog_bin_product($product_id, $user['id']) === false) {
                            continue;
                        }

                        pg_audit_record('recycle', 'products', $product_id, $audit_before);

                    } else {

                        // The product screen's own delete: the row and its
                        // images, group and zone links, attributes, barcodes,
                        // form fields, keywords, short links, offer rules and
                        // stored SEO findings, and the product.deleted event.
                        if (!pg_pb_delete_product($product_id)) {
                            continue;
                        }
                    }

                    $number_of_products++;
                }
                
                // If at least one product was deleted, then log this information and output a notice.
                if (($number_of_products > 0) && $binning) {

                    log_activity(lang(array('string' => '{var:1} product(s) were moved to the Recycle Bin.', 'vars' => array($number_of_products))), $_SESSION['sessionusername']);
                    $liveform->add_notice(lang(array('string' => '{var:1} product(s) were moved to the Recycle Bin.', 'vars' => array($number_of_products))));

                } elseif ($number_of_products > 0) {
                    if ($number_of_products == 1) {
                        $message = lang(array('string' => '{var:1} product was deleted.', 'vars' => array($number_of_products)));
                    } else {
                        $message = lang(array('string' => '{var:1} products were deleted.', 'vars' => array($number_of_products)));
                    }

                    log_activity($message, $_SESSION['sessionusername']);
                    $liveform->add_notice(h($message));
                }

                $refusal = pg_bundle_bulk_delete_refusal($number_of_components);

                if ($refusal !== '') {
                    $liveform->mark_error('bundle_component', h($refusal));
                }
                
                break;
        }
    }

    go(PATH . SOFTWARE_DIRECTORY . '/view_products.php');
}