<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1.17. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1_17() {
	// Allow multiple barcodes per product (drop the unique-per-product constraint
	// that was accidentally included in 2026.1.16). Each barcode value stays globally unique.
	// Sites upgrading from before 2026.1.15 never had the key, and the helper only drops
	// what is there.
	install_drop_index('product_barcodes', 'uq_product');

	// Parasut API V4 direct integration.
	// Adds OAuth2 credential storage to config and API object tracking to orders.
	// The existing enable_parasut toggle now also activates direct API calls
	// (e-fatura / e-irsaliye) in addition to the legacy Excel export.

	// Config: API credentials and sandbox flag
	install_add_column('config', 'parasut_client_id', "VARCHAR(255) NOT NULL DEFAULT ''");
	install_add_column('config', 'parasut_client_secret', "VARCHAR(255) NOT NULL DEFAULT ''");
	install_add_column('config', 'parasut_username', "VARCHAR(255) NOT NULL DEFAULT ''");
	install_add_column('config', 'parasut_password', "VARCHAR(255) NOT NULL DEFAULT ''");
	install_add_column('config', 'parasut_company_id', "VARCHAR(50) NOT NULL DEFAULT ''");
	install_add_column('config', 'parasut_use_sandbox', "TINYINT NOT NULL DEFAULT 0");

	// Orders: track the Parasut objects created via API for each order
	install_add_column('orders', 'parasut_contact_id', "VARCHAR(50) NOT NULL DEFAULT ''");
	install_add_column('orders', 'parasut_invoice_id', "VARCHAR(50) NOT NULL DEFAULT ''");
	install_add_column('orders', 'parasut_shipment_id', "VARCHAR(50) NOT NULL DEFAULT ''");

	// Parasut contact linking and Turkish tax fields for contacts.
	// parasut_contact_id: links a Pinegrap contact to its counterpart in Parasut (synced on first invoice).
	// tax_number: VKN (companies) or TCKN (individuals) — required by Parasut for proper invoicing.
	// tax_office: Vergi dairesi — required for company invoices in Turkey.
	install_add_column('contacts', 'parasut_contact_id', "VARCHAR(50) NOT NULL DEFAULT ''");
	install_add_column('contacts', 'tax_number', "VARCHAR(20) NOT NULL DEFAULT ''");
	install_add_column('contacts', 'tax_office', "VARCHAR(100) NOT NULL DEFAULT ''");

	// Extend parasut_tc_in_field ENUM to support the native contacts.tax_number column.
	install_modify_column('config', 'parasut_tc_in_field', "ENUM('do not use', 'custom_field_1', 'custom_field_2', 'tax_number') NOT NULL DEFAULT 'do not use'");
}
