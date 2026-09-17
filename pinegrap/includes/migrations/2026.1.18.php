<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1.18. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1_18() {
    // Add default Parasut product/service ID for invoice line items.
    // Parasut requires each sales_invoice_detail to reference a product/service entity.
    install_add_column('config', 'parasut_default_product_id', "VARCHAR(50) NOT NULL DEFAULT ''");
    // Store each product's Parasut counterpart ID so we don't recreate it on every invoice.
    install_add_column('products', 'parasut_product_id', "VARCHAR(50) NOT NULL DEFAULT ''");
    // Default warehouse (stock location) ID for e-irsaliye shipment document details.
    // Auto-fetched from Parasut on first use; override in settings for multi-warehouse setups.
    install_add_column('config', 'parasut_default_warehouse_id', "VARCHAR(50) NOT NULL DEFAULT ''");
}
