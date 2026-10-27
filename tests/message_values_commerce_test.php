<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A liveform message is HTML: the values the messages of the product, order,
 * marketplace and content screens carry from stored records, panel input and
 * remote services go in through h(). Each row is a file, one line of it and
 * how many times that line occurs, so an escape that is taken out again is
 * noticed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_message_values_commerce_are_escaped()
{
	$lines = array(
		array('add_order.php', '$liveform_order->add_notice(lang(array(\'string\' => \'Invoice {var:1} created.\', \'vars\' => array(h($res[\'result\'][\'invoice\'][\'number\'])))));', 1),
		array('add_order.php', '$liveform_order->mark_error(\'_error\', h($warning));', 1),
		array('add_product.php', '$liveform_target->add_notice(lang(array(\'string\' => \'product ({var:1}) was created\', \'vars\' => array(h($result[\'name\'])))));', 2),
		array('add_return.php', '$target->mark_error(\'_error\', h($message));', 1),
		array('add_return.php', '$liveform->mark_error($refusal[\'field\'], h($refusal[\'message\']));', 1),
		array('add_return.php', '$liveform_return->add_notice(h(pg_return_staff_notice($recorded)));', 1),
		array('billing_information.php', '$liveform->validate_required_field(\'custom_field_1\', h($custom_field_1_label) . \' is required.\');', 1),
		array('billing_information.php', '$liveform->validate_required_field(\'custom_field_2\', h($custom_field_2_label) . \' is required.\');', 1),
		array('cart_action.php', '$lf->mark_error(\'quick_add_product_id\', lang(array(\'string\' => \'Sorry, {var:1} is not currently available.\', \'vars\' => h($qa_desc))));', 1),
		array('edit_page.php', '$liveform->mark_error(\'canonical_url\', pg_page_canonical_url_message($canonical_check[\'error\'], h($current_page_name)));', 1),
		array('edit_page.php', '$liveform->mark_error(\'max_snippet\', pg_page_max_snippet_message(h($current_page_name)));', 1),
		array('edit_pages.php', '$liveform->add_notice(lang(array(\'string\'=>\'{var:1} page(s) {var:2}\',\'vars\'=>array($number_of_pages,h($log_message)) )) );', 1),
		array('edit_product.php', '$liveform_target->add_notice(lang(array(\'string\' => $deleted_message[\'string\'], \'vars\' => array(h($deleted_name)))));', 1),
		array('edit_product.php', '$notice = lang(array(\'string\' => \'product ({var:1}) was updated\', \'vars\' => array(h($result[\'name\']))));', 1),
		array('edit_product.php', 'log_activity(lang($deleted_message), $_SESSION[\'sessionusername\']);', 1),
		array('import_design_zip.php', '$lf_edit->add_notice(htmlspecialchars($w, ENT_QUOTES | ENT_SUBSTITUTE, \'UTF-8\'));', 1),
		array('import_design_zip.php', '$lf_edit->add_notice(lang(array(\'string\' => \'{var:1} page(s) were imported into "{var:2}".\', \'vars\' => array(count($made[\'page_ids\']), htmlspecialchars($project, ENT_QUOTES | ENT_SUBSTITUTE, \'UTF-8\')))));', 1),
		array('import_users.php', '$combined_errors .= \'<p>\' . lang(array(\'string\'=>\'{var:1} email address(es) already exist. ({var:2})\',\'vars\'=>array($pre_existing_emails_count,htmlspecialchars($pre_existing_email_list, ENT_QUOTES | ENT_SUBSTITUTE, \'UTF-8\')))) . \'</p>\';', 1),
		array('import_users.php', '$combined_errors .= \'<p>\' . lang(array(\'string\'=>\'{var:1} email address(es) were invalid. ({var:2})\',\'vars\'=>array($invalid_emails_count,htmlspecialchars($invalid_email_list, ENT_QUOTES | ENT_SUBSTITUTE, \'UTF-8\')))) . \'</p>\';', 1),
		array('import_users.php', '$combined_errors .= \'<p>\' . lang(array(\'string\'=>\'{var:1} user name(s) already exist. ({var:2})\',\'vars\'=>array($pre_existing_users_count,htmlspecialchars($pre_existing_user_list, ENT_QUOTES | ENT_SUBSTITUTE, \'UTF-8\')))) . \'</p>\';', 1),
		array('includes/designer_screen.php', 'foreach ($errors as $e) $liveform->add_error(h($e));', 1),
		array('marketplace_settings.php', '\'vars\'   => array(h($result[\'error\']))', 3),
		array('marketplace_settings.php', '\'vars\'   => array(h((string)$package[\'remote_order_id\']), h($sent[\'error\']))', 1),
		array('marketplace_settings.php', '\'vars\'   => array(h($read[\'error\']))', 2),
		array('marketplace_settings.php', '\'vars\'   => array(h($sent[\'error\']))', 1),
		array('marketplace_settings.php', '\'vars\'   => array(h((string)$claim[\'order_number\']), h($sent[\'error\']))', 2),
		array('marketplace_settings.php', '\'vars\'   => array(h($reasons[\'error\']))', 1),
		array('marketplace_settings.php', '$liveform->add_warning(h($files[\'error\']));', 1),
		array('shipping_address_and_arrival.php', '$liveform->validate_required_field(\'custom_arrival_date_\' . $liveform->get_field_value(\'arrival_date\'), \'A custom arrival date is required, because you selected \' . h($arrival_date_name) . \'.\');', 1),
		array('update_exchange_rates.php', '\'vars\' => array(h($code))', 1),
	);

	foreach ($lines as $line) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $line[0]);

		pg_assert_same($line[2], substr_count($source, $line[1]), $line[0] . ': ' . $line[1]);
	}
}
