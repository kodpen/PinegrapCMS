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

// 2026.11.0 - the release that follows 2026.4.10, the first calendar-versioned
// number (docs/memory/release/version-numbering.md).
//
// One entry point, one step per subsystem, called in the order the work was
// done. The numbers in the comments are labels only (general work from 11.0.1,
// ERP 11.0.58-11.0.69 and from 11.0.90, API 11.0.70-11.0.79, the workspace
// 11.0.80-11.0.89 and 11.0.110-11.0.119); the order of the calls is what runs.
function upgrade_to_2026_11_0() {

	upgrade_2026_11_0_legal_documents();        // 11.0.1
	upgrade_2026_11_0_price_history();          // 11.0.2
	upgrade_2026_11_0_ops_alerts();             // 11.0.20
	upgrade_2026_11_0_update_backup();          // 11.0.21
	upgrade_2026_11_0_maintenance_mode();       // 11.0.22
	upgrade_2026_11_0_meta_tags();              // 11.0.3

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

// Product price history (includes/fn/price_history.php).
//
// product_price_history keeps one row each time the price a product sells at
// changes: the product, the sales channel ('web' for products.price, the
// shop's own price), the price in minor units, the base currency it was in,
// when it changed and what changed it (initial, panel, import, api, offer,
// erp). The "price before the discount" is the lowest price of the 10 or 30
// days before a reduction, and this is what it is read from. Rows are
// appended and only removed with their product; KEY idx_product_channel_time
// serves both the newest row of a product (the recorder's comparison) and the
// rows of a period (the reference price). source is a VARCHAR rather than an
// ENUM: a label added later must not turn into '' on servers that have not
// run its step.
//
// products.compare_at_price (NULL: none) is the merchant's own "was" price for
// an article without an offer. It is added without copying the table where
// the server can do that, and copied otherwise: the catalogue is not a table
// that grows with visits, and a shop of a hundred thousand articles copies in
// seconds.
//
// The history starts with one 'initial' row per product: its price at the
// upgrade, stamped with the time of the upgrade (PHP's clock, which the
// recorder uses too). That is all that is known for certain; products.timestamp
// is not proof that the price stood still, since other edits move it too and
// an older writer may have changed the price without it. A product that
// already has a 'web' row is left alone, so a second run adds nothing.
function upgrade_2026_11_0_price_history() {

	install_create_table('product_price_history', "CREATE TABLE product_price_history (
		id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
		product_id  INT UNSIGNED NOT NULL DEFAULT 0,
		channel     VARCHAR(64) NOT NULL DEFAULT 'web',
		price       INT NOT NULL DEFAULT 0,
		currency    VARCHAR(3) NOT NULL DEFAULT '',
		changed_at  INT UNSIGNED NOT NULL DEFAULT 0,
		source      VARCHAR(16) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		KEY idx_product_channel_time (product_id, channel, changed_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	$columns = array(
		'compare_at_price' => "INT NULL DEFAULT NULL",
	);

	if (!install_add_columns_instant('products', $columns)) {

		foreach ($columns as $column => $definition) {

			install_add_column('products', $column, $definition);

		}

	}

	// The base currency, read the way init.php reads it, USD when none is
	// marked. Only capital letters reach the statement.
	$currency = (string) db_value("SELECT code FROM currencies WHERE base = '1' LIMIT 1");
	$currency = substr((string) preg_replace('/[^A-Z]/', '', strtoupper($currency)), 0, 3);

	if ($currency === '') {

		$currency = 'USD';

	}

	db("INSERT INTO product_price_history (product_id, channel, price, currency, changed_at, source)
		SELECT products.id, 'web', products.price, '" . $currency . "', '" . time() . "', 'initial'
		FROM products
		WHERE NOT EXISTS (
			SELECT 1
			FROM product_price_history AS history
			WHERE history.product_id = products.id AND history.channel = 'web'
		)");

	$started = max(0, (int) mysqli_affected_rows(db::$con));

	if ($started > 0) {

		install_ran('product_price_history: the current price of ' . $started . ' product(s) recorded as the start of their history');

	} else {

		install_skipped('product_price_history: every product already has a price history');

	}

	install_note('Products: every change to a product\'s own price is now recorded with its date, starting from the price each product has today. It is the record the "lowest price of the last 30 days" shown beside a discount will be read from.');

}

// Operations alerts (2026.11.0, 11.0.20; includes/fn/ops_alerts.php, the
// Alerts card under Site Settings, General).
//
// A failed or missing backup, a scheduled task that stopped, changed software
// files, a burst of PHP errors, low disk space, failing mail or webhooks and a
// certificate about to run out are told on the panel bell, by e-mail and on
// subscribed devices. On after the upgrade, both channels included, to the
// site's own address while no recipient is typed: the point is that nobody has
// to have thought of it. The four thresholds are per check, 0 switching that
// check off. The alerts themselves are notification rows (action 'ops'), so
// there is no table.
function upgrade_2026_11_0_ops_alerts() {

	install_add_column('config', 'ops_alerts', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1");

	install_add_column('config', 'ops_alert_email', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1");

	install_add_column('config', 'ops_alert_push', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1");

	install_add_column('config', 'ops_alert_recipients', "TEXT NULL");

	install_add_column('config', 'ops_alert_error_rate', "INT UNSIGNED NOT NULL DEFAULT 50");

	install_add_column('config', 'ops_alert_disk_percent', "TINYINT UNSIGNED NOT NULL DEFAULT 10");

	install_add_column('config', 'ops_alert_mail_failures', "INT UNSIGNED NOT NULL DEFAULT 10");

	install_add_column('config', 'ops_alert_cert_days', "TINYINT UNSIGNED NOT NULL DEFAULT 14");

	install_note('Operations alerts: a failed or missing backup, a scheduled task that stopped, changed software files, a burst of PHP errors, low disk space, failing mail or webhooks and an expiring certificate are now told on the panel bell, by e-mail to the site address and on subscribed devices. They are set under Site Settings, General, Alerts.');

}

// The backup an update takes before it replaces the files (2026.11.0, 11.0.21;
// pg_update_backup_create() in includes/fn/update.php, the switch on the
// Update Channel card).
//
// On by default: the files the package replaces and the database go into
// data/backups/pre-update-<version>-<time>.zip, and Repair Software rolls the
// files back from it. Off is for a site too large to back up within one
// request; then the update replaces the files without a backup, as before.
function upgrade_2026_11_0_update_backup() {

	install_add_column('config', 'update_backup', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1");

	install_note('Software update: before an update replaces the files, the files it replaces and the database are backed up to data/backups/pre-update-….zip, and Repair Software can roll the files back. It can be switched off under Site Settings, General, Update Channel.');

}

// Maintenance mode (2026.11.0, 11.0.22; includes/fn/maintenance_mode.php, the
// Maintenance Mode card under Site Settings, General).
//
// Off: nothing changes for a site that never switches it on. While on, a
// visitor asking for a page gets a 503 with Retry-After until the optional end
// time (0 = none); signed-in managers and above, and the listed addresses,
// see the site.
function upgrade_2026_11_0_maintenance_mode() {

	install_add_column('config', 'maintenance_mode', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('config', 'maintenance_until', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('config', 'maintenance_allow_ips', "TEXT NULL");

	install_note('Maintenance mode: visitors can be shown a maintenance page (503, with the time the site is expected back) while managers, administrators and listed addresses see the site. It is under Site Settings, General, Maintenance Mode; health.php answers uptime monitors.');

}

// Custom meta tags in the head (2026.11.0, 11.0.3; includes/fn/meta_tags.php,
// the Meta Tags groups of the visual designer's Settings).
//
// style.style_meta_tags holds the design's list, written on every page of the
// design; page.page_meta_tags a page's own, which wins over the design's for
// the same name. Both are JSON lists of name/content pairs, NULL or '' for
// none. The designer's design-wide list had no column before: what it showed
// was kept in the browser only and never reached the site.
//
// Added without copying the tables where the server can do that; they carry
// the page trees and the generated HTML, so a copy is slow on a big site.
function upgrade_2026_11_0_meta_tags() {

	$tables = array(
		'style' => array('style_meta_tags' => "TEXT NULL"),
		'page'  => array('page_meta_tags' => "TEXT NULL"),
	);

	foreach ($tables as $table => $columns) {

		if (!install_add_columns_instant($table, $columns)) {

			foreach ($columns as $column => $definition) {

				install_add_column($table, $column, $definition);

			}

		}

	}

	install_note('Visual designer: meta tags added under Settings, Design now reach every page of the design, and each page can have meta tags of its own under Settings, Page. A page tag replaces a design tag, and either replaces the Open Graph or Twitter tag of the same name the site writes itself.');

}
