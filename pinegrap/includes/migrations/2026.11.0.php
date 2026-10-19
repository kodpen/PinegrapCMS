<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.11.0. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

// 2026.11.0 - the release that follows 2026.4.10.
//
// One entry point, one step per subsystem, called in the order the work was
// done. The numbers in the comments are labels only and keep the ranges the
// earlier versions used with the 11.0. prefix (general work from 11.0.1, ERP
// 11.0.58-11.0.69 and from 11.0.90, API 11.0.70-11.0.79, the workspace
// 11.0.80-11.0.89); the order of the calls is what runs.
function upgrade_to_2026_11_0() {

	upgrade_2026_11_0_legal_documents();        // 11.0.1

}

// The pre-information form and the distance sales contract a customer accepts
// at checkout. The two templates and the switch live on the config row; every
// accepted copy is kept in order_documents exactly as it was shown, so a later
// template edit never changes what a customer agreed to.
function upgrade_2026_11_0_legal_documents() {

	install_add_column('config', 'ecommerce_legal_documents', "TINYINT(1) NOT NULL DEFAULT 0");
	install_add_column('config', 'ecommerce_preinfo_template', "TEXT NULL");
	install_add_column('config', 'ecommerce_contract_template', "TEXT NULL");

	install_create_table('order_documents', "CREATE TABLE order_documents (
		id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
		order_id            INT UNSIGNED NOT NULL DEFAULT 0,
		type                ENUM('preinfo','contract','kvkk') NOT NULL,
		html                MEDIUMTEXT NULL,
		accepted_at         INT UNSIGNED NULL DEFAULT NULL,
		ip                  VARCHAR(45) NOT NULL DEFAULT '',
		user_agent          VARCHAR(255) NOT NULL DEFAULT '',
		created_at          INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_order_type (order_id, type)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Checkout legal documents: the pre-information form and the distance sales contract can be switched on under Site Settings, Ecommerce, Legal Documents. They stay off until the templates are reviewed and the switch is turned on.');

}
