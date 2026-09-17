<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1.16. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1_16() {
	// Barcode feature: per-product barcode storage and label designer settings.
	install_create_table('product_barcodes', "CREATE TABLE  product_barcodes (
		id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
		product_id  INT UNSIGNED NOT NULL,
		barcode     VARCHAR(100) NOT NULL,
		barcode_type VARCHAR(20) NOT NULL DEFAULT 'CODE128',
		created_at  DATETIME NOT NULL,
		updated_at  DATETIME NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY uq_barcode  (barcode),
		INDEX idx_product_id   (product_id)
	)" . ENGINE);

	// Config columns: enable toggle, default type, label dimensions, template JSON.
	install_add_column('config', 'barcode_enabled', "TINYINT NOT NULL DEFAULT 0");
	install_add_column('config', 'barcode_default_type', "VARCHAR(20) NOT NULL DEFAULT 'CODE128'");
	install_add_column('config', 'barcode_label_width', "SMALLINT NOT NULL DEFAULT 60");
	install_add_column('config', 'barcode_label_height', "SMALLINT NOT NULL DEFAULT 40");
	install_add_column('config', 'barcode_label_template', "TEXT");
}
