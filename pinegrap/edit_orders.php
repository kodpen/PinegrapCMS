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

validate_token_field();

include_once('liveform.class.php');

// if at least one order was selected
if (!empty($_POST['orders'])) {

    // Counted by each action below.
    $number_of_orders = 0;

    switch ($_POST['action'] ?? '') {

        case 'export_orders_for_parasut':
            //This feature is used for invoicing orders via the parasut.com application with the help of a draft excel file.

            // The product VAT rate goes into every line: checked before any file is built.
            $product_tax_rate = pg_order_parasut_tax_rate($_POST['tax_rate_for_parasut'] ?? '');
            if ($product_tax_rate === null) {
                $liveform_view_orders = new liveform('view_orders');
                $liveform_view_orders->add_error(lang('The product tax rate must be a number from 0 to 100.'));
                break;
            }
            $product_tax_rate_included = $_POST['tax_rate_included_for_parasut'] ?? '';

            // Both sheets are filled cell by cell, row number => column => value,
            // and written by pg_xlsx_write_layout() under Paraşüt's own heads
            // (includes/fn/parasut_sheet.php). Typed text is always a text cell,
            // never a formula: a checkout name starting with '=' stays as typed.
            $invoice_cells = array();
            $contact_cells = array();

            $organization_name = '';
            // if ORGANIZATION_NAME defined and ORGANIZATION_NAME is not empty, use it as $organization_name 
            if(defined('ORGANIZATION_NAME') && ORGANIZATION_NAME != ''){
                $organization_name = ORGANIZATION_NAME;
            }else{
                //else ORGANIZATION_NAME  empty or not defined.
                // use HOSTNAME_SETTING as $organization_name
                if(defined('HOSTNAME_SETTING')){
                    $organization_name = HOSTNAME_SETTING;
                }else{
                    //else HOSTNAME_SETTING empty or not defined.
                    // use this.
                    $organization_name = 'Pinegrap CMS';
                }
            }

            // Both sheets' data starts on row 4. The invoice sheet takes a row per
            // line of an order, the contacts sheet one per order.
            $i_obj_1 = PG_PARASUT_SHEET_FIRST_ROW;
            $i_obj_2 = PG_PARASUT_SHEET_FIRST_ROW;

            //count orders.
            $number_of_orders = 0;
            // Cancelled orders and open carts are not sales: left out, counted.
            $parasut_skipped = 0;
            foreach ($_POST['orders'] as $order_id) {
                //$invoice_cells is the invoice sheet, $contact_cells the customers sheet.
                //we generate 2 file to merge, parasut wants first customer information before complete E-Invoice.
            
                //get order information
                $query = "SELECT
                    orders.id,
                    orders.billing_first_name,
                    orders.billing_last_name,
                    orders.status,
                    orders.order_number,
                    user.user_username as username,
                    orders.total,
                    orders.order_date,
                    orders.billing_email_address,
                    orders.billing_company,
                    orders.billing_phone_number,
                    orders.billing_fax_number,
                    orders.billing_address_1,
                    orders.billing_address_2,
                    orders.billing_zip_code,
                    orders.billing_city,
                    orders.billing_state,
                    orders.custom_field_1,
                    orders.custom_field_2,
                    orders.surcharge,
                    orders.shipping,
                    orders.discount,
                    orders.payment_installment,
                    orders.installment_charges,
                    contacts.tax_number AS contact_tax_number
                FROM orders
                LEFT JOIN user ON orders.user_id = user.user_id
                LEFT JOIN contacts ON orders.contact_id = contacts.id
                WHERE ((orders.status = 'complete') OR (orders.status = 'exported'))
                AND orders.id = ?";
                $result=db_q($query, array((string) $order_id)) or output_error('Query failed.');
                $row = mysqli_fetch_array($result);
                // The query reads only completed and exported orders.
                if (!pg_order_parasut_exportable($row['status'] ?? null)) {
                    $parasut_skipped++;
                    continue;
                }
                $billing_first_name = $row['billing_first_name'];
                $billing_last_name = $row['billing_last_name'];
                $order_date = $row['order_date'];
                $order_number = $row['order_number'];
                $username = $row['username'];
                $total = $row['total'];
                $billing_email_address = $row['billing_email_address'];
                $billing_company = $row['billing_company'];
                $billing_phone_number = $row['billing_phone_number'];
                $billing_fax_number = $row['billing_fax_number'];
                $billing_address_1 = $row['billing_address_1'];
                $billing_address_2 = $row['billing_address_2'];
                $billing_zip_code = $row['billing_zip_code'];
                $billing_city = $row['billing_city'];
                $billing_state = $row['billing_state'];
                $custom_field_1 = $row['custom_field_1'];
                $custom_field_2 = $row['custom_field_2'];
                $payment_installment = $row['payment_installment'];
                $installment_charges = $row['installment_charges'];
                $surcharge = $row['surcharge'];
                $shipping = $row['shipping'];
                 

                $customer_billing_name = pg_turkish_title_case($billing_first_name . ' ' . $billing_last_name);
                $billing_combined_address = $billing_address_1 . $billing_address_2 . ' ' . $billing_zip_code;
                //PARASUT_TC_IN_FIELD republic of turkey identification number
                //this is show us where we get republic of turkey identification number (its not required but easy with it.)
                $identification_number = '';
                if( defined('PARASUT_TC_IN_FIELD')  && PARASUT_TC_IN_FIELD != 'do not use'){
                    if(PARASUT_TC_IN_FIELD == 'custom_field_1'){
                        $identification_number = $custom_field_1;
                    }elseif(PARASUT_TC_IN_FIELD == 'custom_field_2'){
                        $identification_number = $custom_field_2;
                    }elseif(PARASUT_TC_IN_FIELD == 'tax_number'){
                        $identification_number = $row['contact_tax_number'];
                    }
                }
                // The contacts sheet's columns B, K, L and N-Q stay empty.
                $contact_cells[$i_obj_2] = array(
                    'A' => (string) $customer_billing_name,     // FİRMA ADI MÜŞTERİ ÜNVANI*
                    'C' => 'M',                                 // MÜŞTERİ/TEDARİKÇİ*
                    'D' => (string) $billing_email_address,     // E-POSTA
                    'E' => 'Pinegrap CMS',                      // KATEGORİ
                    'F' => (string) $billing_combined_address,  // ADRES
                    'G' => (string) $billing_city,              // İLÇE
                    'H' => (string) $billing_state,             // İL
                    'I' => (string) $billing_phone_number,      // TELEFON
                    'J' => (string) $billing_fax_number,        // FAKS
                    'M' => (string) $identification_number,     // TC KİMLİK NO
                );
                $i_obj_2++;

                // The invoice's own columns go on its first row; its first product
                // line shares that row.
                $invoice_cells[$i_obj_1] = array(
                    'A' => (string) $customer_billing_name,                 // MÜŞTERİ ÜNVANI*
                    'B' => $organization_name . ' - ' . $order_number,      // FATURA AÇIKLAMASI
                    'C' => array('date' => date('Y-m-d H:i', time())),      // FATURA TARİHİ*
                    'F' => array('date' => date('Y-m-d H:i', $order_date)), // VADE TARİHİ (order date)
                    'H' => 'Fatura',                                        // FATURA TÜRÜ
                    'K' => 'Pinegrap CMS',                                  // KATEGORİ
                );

                $query = "SELECT
                    order_items.product_id as product_id,
                    order_items.price as price,
                    order_items.product_name as product_name,
                    order_items.quantity as quantity
                FROM order_items
                WHERE order_id = ?";

                $result = db_q($query, array((string) $order_id)) or output_error('Query failed.');
                if($result){
                    $order_items = array();
                    $line_totals = array();
                    while($order_item = mysqli_fetch_assoc($result)) {
                        $order_items[] = $order_item;
                        $line_totals[] = (int) $order_item['price'] * (int) $order_item['quantity'];
                    }

                    // The order discount (an order offer or code) sits only on the
                    // order: each line carries its share in İNDİRİM TUTARI, so the
                    // invoice Paraşüt builds comes to what the customer paid.
                    $line_discounts = pg_order_parasut_line_discounts((int) $row['discount'], $line_totals);

                    foreach ($order_items as $line_key => $order_item) {
                        $order_item_product_id = $order_item['product_id'];
                        $price = (int) $order_item['price'];
                        $product_name = $order_item['product_name'];
                        $quantity = (int) $order_item['quantity'];

                        $product_discount = $line_discounts[$line_key];

                        //if product name is not empty use it
                        if(db("SELECT short_description FROM products WHERE id = '" . escape($order_item_product_id) . "'") != ''){
                            $product_name = db("SELECT short_description FROM products WHERE id = '" . escape($order_item_product_id) . "'");
                        }

                        //if tax_rate_included_for_parasut checked
                        if($product_tax_rate_included == 1){
                            //deduct tax rate from product price, because parasut require tax, calculates and adds to the price.
                            //Pinegrap calculates tax by customer tax zone and add it to order and order can contain taxed product or untaxed.
                            if($product_tax_rate > 0){
                                $price = $price / (1+$product_tax_rate/100); //tax deducted product price  = price / (1+tax rate /100)
                                // The discount came off the same VAT-inclusive prices.
                                $product_discount = $product_discount / (1+$product_tax_rate/100);
                            }
                        }

                        // M (description) and N (warehouse) stay empty.
                        $invoice_cells[$i_obj_1] = (isset($invoice_cells[$i_obj_1]) ? $invoice_cells[$i_obj_1] : array()) + array(
                            'L' => (string) $product_name,      // HİZMET/ÜRÜN*
                            'O' => $quantity,                   // MİKTAR*
                            'P' => ($price / 100),              // BİRİM FİYATI*
                            'Q' => ($product_discount / 100),   // İNDİRİM TUTARI
                            'R' => $product_tax_rate,           // KDV ORANI*
                            'S' => 0,                           // ÖİV ORANI
                        );
                        $i_obj_1++;
                    }

                }else{
                    $i_obj_1++;
                }

                // Installment, shipping and surcharge each follow as a line of
                // their own, net of the dialog's VAT rate.
                $fee_lines = array();
                if($installment_charges){
                    $fee_lines[] = array(lang('Order installment fee') . ' (' . $payment_installment . 'x)', (int) $installment_charges);
                }
                if($shipping){
                    $fee_lines[] = array(lang('Order shipping fee'), (int) $shipping);
                }
                if($surcharge){
                    $fee_lines[] = array(lang('Order Surcharge fee'), (int) $surcharge);
                }
                foreach ($fee_lines as $fee_line) {
                    $fee = $fee_line[1];
                    if($product_tax_rate > 0){
                        $fee = $fee / (1+$product_tax_rate/100); //tax deducted product price  = price / (1+tax rate /100)
                    }
                    $invoice_cells[$i_obj_1] = (isset($invoice_cells[$i_obj_1]) ? $invoice_cells[$i_obj_1] : array()) + array(
                        'L' => $fee_line[0],    // HİZMET/ÜRÜN*
                        'O' => 1,               // MİKTAR*
                        'P' => ($fee / 100),    // BİRİM FİYATI*
                        'Q' => 0,               // İNDİRİM TUTARI
                        'R' => $product_tax_rate, // KDV ORANI*
                        'S' => 0,               // ÖİV ORANI
                    );
                    $i_obj_1++;
                }

                db("UPDATE orders SET parasut_exported = 1 WHERE id = '" . escape($order_id) . "'");
                $number_of_orders++;
            }

            // Nothing to send: no empty workbooks.
            if ($number_of_orders === 0) {
                $liveform_view_orders = new liveform('view_orders');
                $liveform_view_orders->add_warning(lang(array('string'=>'{var:1} order(s) were not completed or were cancelled and skipped','vars'=>array(pg_format_number($parasut_skipped, 0)) )) );
                break;
            }

            //Generate a date for zip and file names.
            $output_date = date('Y_m_d_H_i_s',time());
            // The files wait in data/temp (no web access) until the zip is sent.
            $temps_location = PG_FUNCTIONS_DIR . '/data/temp/';
            //generate parasut temp folder name.
            $temp_folder_name = 'parasut_' . $output_date;
            //check if temp folder is exist else generate one for save excel files inside.
            if (!file_exists($temps_location . $temp_folder_name)) {
                mkdir($temps_location . $temp_folder_name, 0777, true);
            }

            // Dates as d.mm.yyyy, amounts with two decimals, the VAT rate whole.
            $invoice_layout = pg_parasut_sheet_layout('invoices');
            $invoice_layout['formats'] = array(2 => 'd.mm.yyyy', 5 => 'd.mm.yyyy', 15 => '#,##0.00', 16 => '#,##0.00', 17 => '#,##', 18 => '#,##0.00');
            pg_xlsx_write_layout($invoice_layout, pg_parasut_sheet_rows($invoice_cells, PG_PARASUT_SHEET_FIRST_ROW), $temps_location . $temp_folder_name . '/parasut_satis_faturalari_' . $output_date . '.xlsx');
            pg_xlsx_write_layout(pg_parasut_sheet_layout('contacts'), pg_parasut_sheet_rows($contact_cells, PG_PARASUT_SHEET_FIRST_ROW), $temps_location . $temp_folder_name . '/parasut_musteri_ve_tedarikciler_' . $output_date . '.xlsx');

			$dir = $temps_location.$temp_folder_name;
			$zip_file = $temps_location.$temp_folder_name.'/'.$temp_folder_name.'.zip';			
			// Get real path for our folder
			$rootPath = realpath($dir);
			
			// Initialize archive object
			$zip = new ZipArchive();
			$zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
			
			// Create recursive directory iterator
			/** @var SplFileInfo[] $files */
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($rootPath),
				RecursiveIteratorIterator::LEAVES_ONLY
			);
			
			foreach ($files as $name => $file)
			{
				// Skip directories (they would be added automatically)
				if (!$file->isDir())
				{
					// Get real and relative path for current file
					$filePath = $file->getRealPath();
					$relativePath = substr($filePath, strlen($rootPath) + 1);
			
					// Add current file to archive
					$zip->addFile($filePath, $relativePath);
				}
			}
			// Zip archive will be created only after closing object
			$zip->close();

            // if at least one order was exported, then log activity
            if ($number_of_orders > 0) {
                log_activity(lang(array('string'=>'{var:1} order(s) were exported for parasut.com','vars'=>array(pg_format_number($number_of_orders, 0)) )) , $_SESSION['sessionusername']);
            }
            // The notice is stored before the zip is sent: nothing may follow the zip's bytes.
            $liveform_view_orders = new liveform('view_orders');
            $liveform_view_orders->add_notice(lang(array('string'=>'{var:1} order(s) and the information of the contact(s) they are connected to have been exported with {var:2}.zip file.','vars'=>array(pg_format_number($number_of_orders, 0),$temp_folder_name) )) );
            if ($parasut_skipped > 0) {
                $liveform_view_orders->add_warning(lang(array('string'=>'{var:1} order(s) were not completed or were cancelled and skipped','vars'=>array(pg_format_number($parasut_skipped, 0)) )) );
            }

			header('Content-Description: File Transfer');
			header("Content-type: application/zip"); 
			header("Content-Disposition: attachment; filename=$temp_folder_name.zip");
			header("Content-length: " . filesize($zip_file));
			header("Pragma: no-cache"); 
			header("Expires: 0"); 
			$handle = fopen($zip_file, "rb");
			while (!feof($handle)){
				echo fread($handle, 8192);
			}
			fclose($handle);

            // Remove this export's folder only: another export may be writing its own.
			$it = new RecursiveDirectoryIterator($temps_location . $temp_folder_name, RecursiveDirectoryIterator::SKIP_DOTS);
			$files = new RecursiveIteratorIterator($it,
			         RecursiveIteratorIterator::CHILD_FIRST);
			foreach($files as $file) {
			    if ($file->isDir()){
			        rmdir($file->getRealPath());
			    } else {
			        unlink($file->getRealPath());
			    }
			}
			rmdir($temps_location . $temp_folder_name);

            exit;

            break;
        // if the user selected to remove card data for orders, then do that
        case 'remove_card_data':
            // The number is masked to its last four digits and the security
            // code emptied (pg_order_card_data_clear()).
            foreach ($_POST['orders'] as $order_id) {
                if (pg_order_card_data_clear($order_id)) {
                    $number_of_orders++;
                }
            }
            
            // if credit card data was removed from more than 0 orders, then log activity
            if ($number_of_orders > 0) {
                log_activity(lang(array('string'=>'Card data was removed from {var:1} order(s).','vars'=>array(pg_format_number($number_of_orders, 0)))), $_SESSION['sessionusername']);
            }
            
            $liveform_view_orders = new liveform('view_orders');
            $liveform_view_orders->add_notice( lang(array('string'=>'Card data was removed from {var:1} order(s).','vars'=>array(pg_format_number($number_of_orders, 0)))) );
            
            break;
            
        // if orders are being deleted
        case 'delete':

            require_once(dirname(__FILE__) . '/delete_order.php');

            foreach ($_POST['orders'] as $order_id) {

                $order['id'] = $order_id;

                $response = delete_order(array('order' => $order));

                if ($response['status'] == 'error') {
                    output_error(h($response['message']));
                }

                $number_of_orders++;
            }
            
            // if at least one order was deleted, then log activity
            if ($number_of_orders > 0) {
                log_activity(lang(array('string'=>'{var:1} order(s) were deleted','vars'=>array(pg_format_number($number_of_orders, 0)) )) , $_SESSION['sessionusername']);
            }
            
            $liveform_view_orders = new liveform('view_orders');
            $liveform_view_orders->add_notice(lang(array('string'=>'{var:1} order(s) were deleted','vars'=>array(pg_format_number($number_of_orders, 0)) )) );

            break;

        // ── Bulk cancel selected orders ────────────────────────────────
        // Admin-only path (this script already gated by validate_user +
        // validate_ecommerce_access on entry). We loop sequentially so
        // each call's Iyzipay refund attempt + email send is atomic and
        // we can surface a per-outcome counter to the operator. Errors
        // do NOT halt the loop — they accumulate in counters so a single
        // bad row (already shipped, already cancelled, etc.) doesn't
        // strand the remaining selections.
        case 'cancel':

            // validate_user() returns the PK under "id" — NOT "user_id" (that
            // is the raw column name, used by the api.php auth path).
            // Reading the wrong key silently recorded cancelled_by = 0, which
            // reports interpret as "customer self-service".
            $cancel_admin_user_id = (int) (isset($user['id']) ? $user['id'] : 0);
            $bulk_cancelled = 0;
            $bulk_skipped_already = 0;
            $bulk_skipped_shipped = 0;
            $bulk_skipped_incomplete = 0;
            $bulk_failed = 0;
            // The cancelled orders with their refund outcome, for the money
            // notices below (pg_order_bulk_cancel_refunds()).
            $bulk_cancelled_refunds = array();

            foreach ($_POST['orders'] as $order_id) {
                // An incomplete order is a customer's open cart, not an order:
                // the row's Cancel button is not shown for it either.
                if ((string) db_value("SELECT status FROM orders WHERE id = '" . (int) $order_id . "'") === 'incomplete') {
                    $bulk_skipped_incomplete++;
                    continue;
                }
                $audit_before = pg_audit_fetch('orders', $order_id);
                $res = process_order_cancellation(
                    (int) $order_id,
                    '',                       // operator-typed reason not collected for bulk
                    true,                     // is_admin
                    $cancel_admin_user_id
                );
                if ($res['status'] === 'success') {
                    pg_audit_record('cancel', 'orders', $order_id, $audit_before);
                    $bulk_cancelled_refunds[] = array(
                        'order'         => ((array) db_item("SELECT * FROM orders WHERE id = '" . (int) $order_id . "'")) + array('id' => (int) $order_id),
                        'refund_status' => (string) ($res['refund_status'] ?? ''),
                    );
                }
                switch ($res['status']) {
                    case 'success': $bulk_cancelled++;       break;
                    case 'already': $bulk_skipped_already++; break;
                    case 'shipped': $bulk_skipped_shipped++; break;
                    default:        $bulk_failed++;          break;
                }
            }

            if ($bulk_cancelled > 0) {
                log_activity(
                    lang(array(
                        'string' => '{var:1} order(s) were cancelled',
                        'vars'   => array(pg_format_number($bulk_cancelled, 0)),
                    )),
                    $_SESSION['sessionusername']
                );
            }

            $liveform_view_orders = new liveform('view_orders');
            $liveform_view_orders->add_notice(
                lang(array(
                    'string' => '{var:1} order(s) were cancelled',
                    'vars'   => array(pg_format_number($bulk_cancelled, 0)),
                ))
            );

            // What the cancellations owe the customers, as the per-row Cancel
            // button tells it (cancel_order.php): a paid order cancelled here
            // may need its money returned by hand.
            $bulk_refunds = pg_order_bulk_cancel_refunds($bulk_cancelled_refunds);
            $bulk_numbers = function (array $numbers) {
                return '#' . implode(', #', $numbers);
            };
            if ($bulk_refunds['refunded']) {
                $liveform_view_orders->add_notice(lang(array(
                    'string' => 'The payment has been refunded for order(s) {var:1}.',
                    'vars'   => array($bulk_numbers($bulk_refunds['refunded'])))));
            }
            if ($bulk_refunds['transfer']) {
                $liveform_view_orders->add_warning(lang(array(
                    'string' => 'Order(s) {var:1}: {var:2}',
                    'vars'   => array($bulk_numbers($bulk_refunds['transfer']), pg_order_refund_instruction('Offline Payment')))));
            }
            if ($bulk_refunds['provider']) {
                $liveform_view_orders->add_warning(lang(array(
                    'string' => 'Order(s) {var:1}: {var:2}',
                    'vars'   => array($bulk_numbers($bulk_refunds['provider']), pg_order_refund_instruction('')))));
            }
            if ($bulk_refunds['gift_card']) {
                $liveform_view_orders->add_warning(lang(array(
                    'string' => 'Gift cards paid {var:1} of order(s) {var:2}. Return the amounts to the cards; the site log names them.',
                    'vars'   => array(pg_format_money($bulk_refunds['gift_card_amount'] / 100, BASE_CURRENCY_SYMBOL), $bulk_numbers($bulk_refunds['gift_card'])))));
            }
            if ($bulk_skipped_already > 0) {
                $liveform_view_orders->add_warning(
                    lang(array(
                        'string' => '{var:1} order(s) were already cancelled and skipped',
                        'vars'   => array(pg_format_number($bulk_skipped_already, 0)),
                    ))
                );
            }
            if ($bulk_skipped_incomplete > 0) {
                $liveform_view_orders->add_warning(
                    lang(array(
                        'string' => '{var:1} order(s) were never completed and skipped',
                        'vars'   => array(pg_format_number($bulk_skipped_incomplete, 0)),
                    ))
                );
            }
            if ($bulk_skipped_shipped > 0) {
                $liveform_view_orders->add_warning(
                    lang(array(
                        'string' => '{var:1} order(s) had already shipped and were skipped',
                        'vars'   => array(pg_format_number($bulk_skipped_shipped, 0)),
                    ))
                );
            }
            if ($bulk_failed > 0) {
                $liveform_view_orders->add_error(
                    lang(array(
                        'string' => '{var:1} order(s) could not be cancelled',
                        'vars'   => array(pg_format_number($bulk_failed, 0)),
                    ))
                );
            }

            break;

        // Mark the selected orders shipped without a tracking number: every
        // address gets today's ship date and its lines are shipped in full,
        // and the customer gets the shipping e-mail, through update_order(),
        // the order screen's own save. No parcel is
        // recorded, so a marked order still counts as unshipped for the
        // customer's cancel gate. Orders that cannot or need not be marked are
        // skipped and counted (pg_shipping_bulk_mark_skip()).
        case 'mark_shipped':

            require_once(PG_FUNCTIONS_DIR . '/includes/fn/shipping_import.php');
            require_once(PG_FUNCTIONS_DIR . '/update_order.php');

            $bulk_marked = 0;
            $bulk_skipped = array('cancelled' => 0, 'incomplete' => 0, 'no_address' => 0, 'shipped' => 0, 'shipped_in_full' => 0);
            $bulk_failed = 0;
            $today = date('Y-m-d');

            foreach ($_POST['orders'] as $order_id) {

                $order_id = (int) $order_id;

                $status = db_value("SELECT status FROM orders WHERE id = '" . $order_id . "'");

                if ($status === null || $status === false) {
                    $bulk_failed++;
                    continue;
                }

                $recipients = db_items(
                    "SELECT id, ship_date, delivery_date FROM ship_tos
                    WHERE order_id = '" . $order_id . "' ORDER BY id");

                $items = array();
                $left = false;

                foreach ($recipients as $recipient) {
                    $items[$recipient['id']] = db_items(
                        "SELECT id, quantity, shipped_quantity FROM order_items
                        WHERE ship_to_id = '" . (int) $recipient['id'] . "' AND order_id = '" . $order_id . "'");

                    $left = $left || pg_shipping_bulk_mark_left($items[$recipient['id']]);
                }

                $skip = pg_shipping_bulk_mark_skip((string) $status, count($recipients), _order_has_shipped($order_id), $left);

                if ($skip !== '') {
                    $bulk_skipped[$skip]++;
                    continue;
                }

                $request = array();

                foreach ($recipients as $recipient) {
                    $request[] = pg_shipping_bulk_mark_recipient($recipient, $items[$recipient['id']], $today);
                }

                $outcome = update_order(array('order' => array('id' => $order_id, 'recipients' => $request)));

                if (is_array($outcome) && (($outcome['status'] ?? '') === 'success')) {
                    $bulk_marked++;
                } else {
                    $bulk_failed++;
                }
            }

            if ($bulk_marked > 0) {
                log_activity(
                    lang(array(
                        'string' => '{var:1} order(s) were marked shipped',
                        'vars'   => array(pg_format_number($bulk_marked, 0)),
                    )),
                    $_SESSION['sessionusername']
                );
            }

            $liveform_view_orders = new liveform('view_orders');
            $liveform_view_orders->add_notice(
                lang(array(
                    'string' => '{var:1} order(s) were marked shipped',
                    'vars'   => array(pg_format_number($bulk_marked, 0)),
                ))
            );

            foreach ($bulk_skipped as $reason => $count) {

                if ($count <= 0) {
                    continue;
                }

                $vars = array(pg_format_number($count, 0));

                switch ($reason) {
                    case 'cancelled':
                        $message = lang(array('string' => '{var:1} order(s) were cancelled and skipped', 'vars' => $vars));
                        break;
                    case 'incomplete':
                        $message = lang(array('string' => '{var:1} order(s) were never completed and skipped', 'vars' => $vars));
                        break;
                    case 'no_address':
                        $message = lang(array('string' => '{var:1} order(s) have no shipping address and were skipped', 'vars' => $vars));
                        break;
                    case 'shipped_in_full':
                        $message = lang(array('string' => '{var:1} order(s) were already shipped in full and skipped', 'vars' => $vars));
                        break;
                    default:
                        $message = lang(array('string' => '{var:1} order(s) already have a tracking number and were skipped', 'vars' => $vars));
                        break;
                }

                $liveform_view_orders->add_warning($message);
            }

            if ($bulk_failed > 0) {
                $liveform_view_orders->add_error(
                    lang(array(
                        'string' => '{var:1} order(s) could not be marked shipped',
                        'vars'   => array(pg_format_number($bulk_failed, 0)),
                    ))
                );
            }

            break;
    }
}

header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_orders.php');