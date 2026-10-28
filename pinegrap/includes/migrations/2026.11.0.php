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
	upgrade_2026_11_0_meta_tags();              // 11.0.3
	upgrade_2026_11_0_product_feeds();          // 11.0.4
	upgrade_2026_11_0_ops_alerts();             // 11.0.5
	upgrade_2026_11_0_update_backup();          // 11.0.6
	upgrade_2026_11_0_maintenance_mode();       // 11.0.7
	upgrade_2026_11_0_shared_component_content(); // 11.0.8
	upgrade_2026_11_0_image_delivery();         // 11.0.9
	upgrade_2026_11_0_sms_outbox();             // 11.0.10
	upgrade_2026_11_0_shipments();              // 11.0.20
	upgrade_2026_11_0_order_returns();          // 11.0.11
	upgrade_2026_11_0_return_reminders();       // 11.0.25
	upgrade_2026_11_0_short_link_redirects();   // 11.0.12
	upgrade_2026_11_0_page_revisions();         // 11.0.15
	upgrade_2026_11_0_page_schedule();          // 11.0.17
	upgrade_2026_11_0_ai_crawlers();            // 11.0.21
	upgrade_2026_11_0_privacy_logs();           // 11.0.16
	upgrade_2026_11_0_page_canonical_robots();  // 11.0.22
	upgrade_2026_11_0_ai_fields();              // 11.0.23
	upgrade_2026_11_0_erp_auto_invoice();       // 11.0.58
	upgrade_2026_11_0_conversion_pack();        // 11.0.14
	upgrade_2026_11_0_erp_order_item_tax_rate(); // 11.0.59
	upgrade_2026_11_0_stripe_payment_intents(); // 11.0.24
	upgrade_2026_11_0_passkeys();               // 11.0.13
	upgrade_2026_11_0_not_found_log();          // 11.0.30
	upgrade_2026_11_0_erp_invoice_recurring_day(); // 11.0.60
	upgrade_2026_11_0_form_field_translate();   // 11.0.26
	upgrade_2026_11_0_form_protection();        // 11.0.28
	upgrade_2026_11_0_form_double_opt_in();     // 11.0.27
	upgrade_2026_11_0_page_cache_generation();  // 11.0.29
	upgrade_2026_11_0_theme_light_selector();   // 11.0.32
	upgrade_2026_11_0_comment_replies();        // 11.0.31
	upgrade_2026_11_0_workspace_channel_icon(); // 11.0.81
	upgrade_2026_11_0_workspace_flows();        // 11.0.80
	upgrade_2026_11_0_workspace_flow_shares(); // 11.0.83
	upgrade_2026_11_0_comment_record_counts();  // 11.0.33
	upgrade_2026_11_0_order_return_refund();    // 11.0.34
	upgrade_2026_11_0_prototype_links();        // 11.0.35
	upgrade_2026_11_0_comment_author_index();   // 11.0.36
	upgrade_2026_11_0_workspace_note_revision(); // 11.0.84
	upgrade_2026_11_0_workspace_note_deletions(); // 11.0.85
	upgrade_2026_11_0_workspace_note_sources(); // 11.0.86
	upgrade_2026_11_0_workspace_app_commands(); // 11.0.87
	upgrade_2026_11_0_marketplace_columns();    // 11.0.37
	upgrade_2026_11_0_audit_feed_tokens();      // 11.0.38
	upgrade_2026_11_0_erp_efatura_threshold();  // 11.0.61
	upgrade_2026_11_0_contact_iys_status();     // 11.0.39
	upgrade_2026_11_0_marketplace_remote_products(); // 11.0.40
	upgrade_2026_11_0_passkey_signin();        // 11.0.41
	upgrade_2026_11_0_delivered_campaigns();    // 11.0.42
	upgrade_2026_11_0_collections();            // 11.0.43
	upgrade_2026_11_0_marketplace_map_code_bin(); // 11.0.44
	upgrade_2026_11_0_shipment_lookup();        // 11.0.45
	upgrade_2026_11_0_session_passkey();        // 11.0.46
	upgrade_2026_11_0_audit_retention();        // 11.0.47
	upgrade_2026_11_0_kvkk_notice();            // 11.0.48
	upgrade_2026_11_0_reference_price_rule();   // 11.0.49
	upgrade_2026_11_0_paypal_checkout();        // 11.0.50
	upgrade_2026_11_0_max_image_preview();      // 11.0.51
	upgrade_2026_11_0_main_host_redirect();     // 11.0.52
	upgrade_2026_11_0_blogposting_author();     // 11.0.53
	upgrade_2026_11_0_record_schema_type();     // 11.0.55
	upgrade_2026_11_0_workspace_scheduled_anchor(); // 11.0.88
	upgrade_2026_11_0_api_device_pairings();    // 11.0.70
	upgrade_2026_11_0_product_bundles();        // 11.0.54
	upgrade_2026_11_0_workspace_voice();        // 11.0.89
	upgrade_2026_11_0_workspace_mail();          // 11.0.82
	upgrade_2026_11_0_workspace_wtables();      // 11.0.110
	upgrade_2026_11_0_workspace_mcp_preamble(); // 11.0.111
	upgrade_2026_11_0_card_security_codes();   // 11.0.56

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

// Operations alerts (2026.11.0, 11.0.5; includes/fn/ops_alerts.php, the
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

// The backup an update takes before it replaces the files (2026.11.0, 11.0.6;
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

// Maintenance mode (2026.11.0, 11.0.7; includes/fn/maintenance_mode.php, the
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

// Product feeds for shopping agents and Merchant Center (2026.11.0, 11.0.4;
// includes/fn/product_feeds.php, product_feed_job.php, feed.php).
//
// config.product_feeds is the setup as JSON: the seller fields and the groups
// left out, and per format (OpenAI JSONL, OpenAI CSV, Google TSV) whether it
// is on, its address token (a hash to find it, the token sealed with the
// site's key to show it again) and how its last build went. The feed files
// themselves are written to data/feeds/ by the daily job.
function upgrade_2026_11_0_product_feeds() {

	install_add_column('config', 'product_feeds', "TEXT NULL");

	install_note('Product feeds: the shop\'s products can be published as an OpenAI product feed (JSONL or CSV) and a Google Merchant Center file (TSV), rebuilt every day at a private address.');

}

// Shared components a content-level operator may edit, the visual designer's
// counterpart of a common region. content_editable opens a component's text,
// images and editable areas to managers and to the users granted it in
// users_shared_components_xref (the users_common_regions_xref model); every
// other component stays the designer's. A system widget never takes the flag.
// Both start empty: nothing changes until a designer opens a component.
function upgrade_2026_11_0_shared_component_content() {

	install_add_column('shared_components', 'content_editable', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");

	install_create_table('users_shared_components_xref', "CREATE TABLE users_shared_components_xref (
		user_id              INT UNSIGNED NOT NULL DEFAULT 0,
		shared_component_id  INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (user_id, shared_component_id),
		KEY idx_shared_component (shared_component_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Visual designer: a shared component can now be opened to content editors. Managers edit every opened component, users the ones granted to them; until a designer opens one, nothing changes.');

}

// How the site's pictures are delivered (2026.11.0, 11.0.9;
// pg_image_delivery_settings() in includes/image_engine.php, the Image
// Optimization card under Site Settings, Features).
//
// image_derivative_formats is a bit set of the modern formats the responsive
// markup offers and get_file.php writes: 1 WebP, 2 AVIF. A number rather than
// a list of names because config takes no VARCHAR (the InnoDB row limit,
// tests/innodb_test.php). Both on by default, which is what the code did
// before there was a setting; a format this server cannot write stays off
// whatever the bit says. The two qualities are the encoder's 1-100 scale of
// each format; JPEG and PNG derivatives keep image_resize_quality (2026.4.4).
function upgrade_2026_11_0_image_delivery() {

	install_add_column('config', 'image_derivative_formats', "TINYINT UNSIGNED NOT NULL DEFAULT 3");

	install_add_column('config', 'image_quality_webp', "TINYINT UNSIGNED NOT NULL DEFAULT 80");

	install_add_column('config', 'image_quality_avif', "TINYINT UNSIGNED NOT NULL DEFAULT 50");

	install_note('Images: which modern formats (WebP, AVIF) pictures are offered in and the quality of each can be set under Site Settings, Features, Image Optimization, where Rebuild Page HTML also gives pages published before this version their responsive images without opening each one in the designer.');

}

// The queue of outgoing text messages (2026.11.0, 11.0.10; pg_sms_enqueue()
// and pg_sms_queue_run() in includes/fn/sms.php, the SMS tab of the mail
// queue screen).
//
// A request that wants an SMS writes a row here and the general job sends it,
// so no visitor waits on the provider. A row is sent again only when the
// provider's answer shows it never took the message (the driver's retry);
// after a timeout, a 5xx or a run that stopped halfway the provider may have
// it, the row becomes 'uncertain' and is not sent again by itself, since a
// text message sent twice cannot be taken back. status is a VARCHAR rather
// than an ENUM so a state added later (a delivery report) needs no step.
// expires_at (0: none) is the time after which a message is no longer worth
// sending, a sign-in code above all. reference names what a message is about
// ('order:438:received'); it is unique, so a writer that runs twice queues
// one message, and NULL for a message about nothing in particular.
// provider and provider_id say who took the message and under which id, for
// a delivery report or a question to the provider. InnoDB, so the claim
// (UPDATE ... WHERE status = 'queued') is a row lock.
function upgrade_2026_11_0_sms_outbox() {

	install_create_table('sms_outbox', "CREATE TABLE sms_outbox (
		id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
		created_at   INT UNSIGNED NOT NULL DEFAULT 0,
		send_after   INT UNSIGNED NOT NULL DEFAULT 0,
		expires_at   INT UNSIGNED NOT NULL DEFAULT 0,
		status       VARCHAR(16) NOT NULL DEFAULT 'queued',
		attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
		last_error   VARCHAR(500) NOT NULL DEFAULT '',
		claimed_at   INT UNSIGNED NOT NULL DEFAULT 0,
		sent_at      INT UNSIGNED NOT NULL DEFAULT 0,
		kind         VARCHAR(16) NOT NULL DEFAULT '',
		recipient    VARCHAR(16) NOT NULL DEFAULT '',
		body         TEXT NOT NULL,
		reference    VARCHAR(64) NULL DEFAULT NULL,
		provider     VARCHAR(32) NOT NULL DEFAULT '',
		provider_id  VARCHAR(64) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		UNIQUE KEY uq_reference (reference),
		KEY idx_due (status, send_after),
		KEY idx_created (created_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('SMS: outgoing text messages wait in a queue that the scheduled general job sends, so no visitor waits on the SMS provider. The queue is on the SMS tab of the mail queue, under Settings, Jobs.');

}

// The shipment row (2026.11.0, 11.0.20; docs/memory/commerce/shipment-row-schema.md).
//
// shipping_tracking_numbers held one thing per parcel: its number. Which
// carrier took it, how far it has got, when it left and when it arrived were
// worked out again at every read, from the shipping method's code, the
// number's shape and the ship_tos dates - which checkout writes as estimates.
// The parcel row now carries them itself, so a status a carrier reports, a
// carrier a fulfilment system names and the day a number was recorded have a
// place to be written once and read back as they were.
//
//   carrier         a key of pg_shipping_carriers() ('yurtici', 'ups'...),
//                   '' when the carrier is still worked out from the method
//   tracking_url    the carrier's own page for this parcel when its API gives
//                   one; '' builds the address from the carrier's pattern
//   status          one of created, in_transit, out_for_delivery, delivered,
//                   returned, exception (values in code, not an ENUM: the
//                   list grows as carriers report more), status_at its time
//   shipped_at      when the parcel left, delivered_at when it arrived (unix
//                   times; the ship_tos dates stay the estimates they are)
//   created_at      when the row was written
//   source          who wrote it: panel, api, marketplace, carrier, csv
//   format_warning  1 when the number does not look like the carrier's
//                   (a warning only, it never blocks a save)
//
// The same number recorded twice for one address said nothing new, and the
// panel, the external API and the marketplace import each checked for it
// themselves before writing. The pair is unique from now on, so the check is
// the table's; rows that already repeat a pair are reduced to the first one
// written before the key is added, or the ALTER would be refused.
//
// shipped_at is filled from ship_tos.ship_date for the parcels that have a
// number, the way erp_order_shipment() reads it: only a date that is not in
// the future, because a date beyond today is checkout's estimate and not the
// day anything left. The remaining rows keep 0 and the write path fills them
// as they are touched.
//
// shipping_methods.carrier names the carrier a method ships with, so the
// method's code no longer has to spell it; '' keeps today's recognition.
// order_delivered joins the automatic e-mail actions for the campaign a
// parcel's arrival will start.
function upgrade_2026_11_0_shipments() {

	install_add_column('shipping_tracking_numbers', 'carrier', "VARCHAR(20) NOT NULL DEFAULT ''");

	install_add_column('shipping_tracking_numbers', 'tracking_url', "VARCHAR(500) NOT NULL DEFAULT ''");

	install_add_column('shipping_tracking_numbers', 'status', "VARCHAR(20) NOT NULL DEFAULT 'created'");

	install_add_column('shipping_tracking_numbers', 'status_at', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('shipping_tracking_numbers', 'shipped_at', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('shipping_tracking_numbers', 'delivered_at', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('shipping_tracking_numbers', 'created_at', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('shipping_tracking_numbers', 'source', "VARCHAR(16) NOT NULL DEFAULT 'panel'");

	install_add_column('shipping_tracking_numbers', 'format_warning', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");

	// Repeated pairs, the lowest id kept. Done before the key exists, and
	// safe to repeat: the second run finds nothing to join. The collation
	// decides what "the same number" is, so this removes exactly the rows the
	// unique key would refuse, no more.
	if (!install_index_exists('shipping_tracking_numbers', 'uk_parcel')) {

		db("DELETE repeated FROM shipping_tracking_numbers AS repeated
			INNER JOIN shipping_tracking_numbers AS kept
				ON (kept.ship_to_id = repeated.ship_to_id)
				AND (kept.number = repeated.number)
				AND (kept.id < repeated.id)");

	}

	install_add_index('shipping_tracking_numbers', 'uk_parcel', "UNIQUE KEY uk_parcel (ship_to_id, number)");

	// The backfill is written as WHERE shipped_at = 0, so a run that was cut
	// off continues where it stopped and a second run changes nothing.
	db("UPDATE shipping_tracking_numbers
		INNER JOIN ship_tos ON (ship_tos.id = shipping_tracking_numbers.ship_to_id)
		SET shipping_tracking_numbers.shipped_at = UNIX_TIMESTAMP(ship_tos.ship_date)
		WHERE (shipping_tracking_numbers.shipped_at = 0)
			AND (TRIM(shipping_tracking_numbers.number) != '')
			AND (ship_tos.ship_date != '0000-00-00')
			AND (ship_tos.ship_date <= CURDATE())");

	install_add_column('shipping_methods', 'carrier', "VARCHAR(20) NOT NULL DEFAULT ''");

	// The enum is widened from what is there, never narrowed: reading it first
	// keeps any value a later release may have added.
	$action = install_column_info('email_campaign_profiles', 'action');

	if (is_array($action) && (stripos((string) $action['Type'], "'order_delivered'") === false)) {

		preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", (string) $action['Type'], $matches);
		$values = array();

		foreach ($matches[1] as $value) {
			$values[] = "'" . $value . "'";
		}

		$values[] = "'order_delivered'";

		// The default is kept as it stands too, not rewritten from memory.
		$default = ((string) $action['Default'] !== '') ? (string) $action['Default'] : 'calendar_event_reserved';

		install_modify_column('email_campaign_profiles', 'action', "ENUM(" . implode(',', $values) . ") NOT NULL DEFAULT '" . $default . "'");

	}

	install_note('Shipping: each tracking number now keeps its carrier, status, dates and where it was recorded, a number is recorded once per address, a shipping method can name its carrier, and automatic e-mails gain the "order delivered" action.');

}


// Customer returns (2026.11.0, 11.0.11; includes/fn/returns.php).
//
// order_returns is one request to send back goods of a completed order: the
// lines of one delivery (ship_to_id), why (reason, a code of
// pg_return_reasons(), and the customer's own words), and where it stands.
// status is a VARCHAR checked in code rather than an ENUM, so a status added
// later needs no step and an older server never turns one into ''. Each status
// reached has its own time, because the deadlines are read from them: the
// refund is due 14 days after the goods reached the carrier (shipped_at), or
// after they arrived (received_at). decision_note is what staff wrote when
// approving or rejecting, for the customer; carrier and carrier_code say which
// carrier the goods go back with and the code the customer gives it, and
// tracking_number is the parcel's. source says who asked (customer, panel,
// api) and requested_by / decided_by which person.
//
// order_return_items lists how many of each order line go back; one row per
// line and return (uq_return_item). What a line still has to give is its
// quantity less what the open returns hold, worked out from these rows.
//
// Both InnoDB: a request is written in one transaction, so a return without
// its lines is never left behind.
function upgrade_2026_11_0_order_returns() {

	install_create_table('order_returns', "CREATE TABLE order_returns (
		id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
		order_id         INT UNSIGNED NOT NULL DEFAULT 0,
		ship_to_id       INT UNSIGNED NOT NULL DEFAULT 0,
		status           VARCHAR(16) NOT NULL DEFAULT 'requested',
		reason           VARCHAR(32) NOT NULL DEFAULT '',
		customer_note    TEXT NULL,
		decision_note    TEXT NULL,
		carrier          VARCHAR(32) NOT NULL DEFAULT '',
		carrier_code     VARCHAR(64) NOT NULL DEFAULT '',
		tracking_number  VARCHAR(100) NOT NULL DEFAULT '',
		source           VARCHAR(16) NOT NULL DEFAULT '',
		requested_by     INT UNSIGNED NOT NULL DEFAULT 0,
		decided_by       INT UNSIGNED NOT NULL DEFAULT 0,
		requested_at     INT UNSIGNED NOT NULL DEFAULT 0,
		decided_at       INT UNSIGNED NOT NULL DEFAULT 0,
		shipped_at       INT UNSIGNED NOT NULL DEFAULT 0,
		received_at      INT UNSIGNED NOT NULL DEFAULT 0,
		refunded_at      INT UNSIGNED NOT NULL DEFAULT 0,
		closed_at        INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_order (order_id),
		KEY idx_status (status, requested_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('order_return_items', "CREATE TABLE order_return_items (
		id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
		return_id      INT UNSIGNED NOT NULL DEFAULT 0,
		order_item_id  INT UNSIGNED NOT NULL DEFAULT 0,
		quantity       INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uq_return_item (return_id, order_item_id),
		KEY idx_order_item (order_item_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Returns: the tables that will keep customers\' return requests and the order lines they send back are added. Nothing changes on the site with this update.');

}

// When the refund reminders of a return were given (includes/fn/returns.php,
// pg_return_remind_refunds()): refund_due_notified_at for "due within
// PG_RETURN_REMIND_DAYS days", refund_overdue_notified_at for "overdue", unix
// time, 0 until that stage was rung. The daily job claims a stage with
// UPDATE ... WHERE <column> = 0 and rings the bell only when that claimed a
// row, so a reminder goes out once per return and stage however often staff
// clear the bell; until now the decision read the notification rows, which
// "Delete all notifications" removes for everyone, and the same reminder came
// back the next day. Same pattern as erp_invoices.overdue_notified_at.
function upgrade_2026_11_0_return_reminders() {

	install_add_column('order_returns', 'refund_due_notified_at', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('order_returns', 'refund_overdue_notified_at', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_note('Returns: each return request now records when its refund reminders were given, so a reminder cleared from the panel bell is not sent again the next day.');

}

// The refund of a return kept on the return itself (2026.11.0, 11.0.34;
// includes/fn/returns.php, pg_return_refund()): refund_amount in minor units
// (0 is a refund recorded as 0), refund_method driver or manual ('' while
// nothing is known), refund_reference (the payment service's refund id, or
// the typed reference of a refund made another way) and refund_unclear (1:
// the service gave no clear answer, the refund is booked unconfirmed). Until
// now they reached only the order.return.refunded event and the audit trail,
// so GET /orders/{id}/returns could not say how much went back.
//
// Returns refunded before the step are filled in from what the refund wrote
// at the time (pg_return_refund_backfill_values()): the return's refund
// audit line, else its order_refunds row, matched by its exact note. Only a
// return with nothing kept yet is read, so a second run finds nothing to do
// and a return neither source knows stays empty for the next one.
// order_returns holds one row per customer request (hundreds in a shop, not
// millions), and only the refunded ones are read, so one pass in chunks of
// 500 ids does it: no cursor and no pause.
function upgrade_2026_11_0_order_return_refund() {

	install_add_column('order_returns', 'refund_amount', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('order_returns', 'refund_method', "VARCHAR(10) NOT NULL DEFAULT ''");
	install_add_column('order_returns', 'refund_reference', "VARCHAR(255) NOT NULL DEFAULT ''");
	install_add_column('order_returns', 'refund_unclear', "TINYINT UNSIGNED NOT NULL DEFAULT 0");

	$returns = array();

	foreach ((array) db_items("SELECT id, order_id FROM order_returns WHERE refund_method = '' AND refunded_at > 0 ORDER BY id") as $row) {
		$returns[(int) $row['id']] = (int) $row['order_id'];
	}

	if (empty($returns)) {

		install_skipped('order_returns: no refunded return without its refund, nothing to fill in');

	} else {

		$audit_ready = install_table_exists('audit_log');
		$refunds_ready = install_column_exists('order_refunds', 'notes');
		$filled = 0;

		foreach (array_chunk(array_keys($returns), 500) as $ids) {

			// The latest refund line of each return; rows come oldest first.
			$audit = array();

			if ($audit_ready) {
				foreach ((array) db_items("SELECT entity_id, after_json FROM audit_log
					WHERE entity_type = 'order_return' AND action = 'update' AND entity_id IN (" . implode(',', $ids) . ")
					ORDER BY id") as $line) {
					$after = json_decode((string) $line['after_json'], true);

					if (is_array($after) && isset($after['refund_method'])) {
						$audit[(int) $line['entity_id']] = $after;
					}
				}
			}

			$refunds = array();

			if ($refunds_ready) {
				$orders = array_values(array_unique(array_intersect_key($returns, array_flip($ids))));

				foreach ((array) db_items("SELECT order_id, amount_cents, refund_type, transaction_id, notes FROM order_refunds
					WHERE refund_type IN ('return', 'return_manual') AND order_id IN (" . implode(',', array_map('intval', $orders)) . ")
					ORDER BY id") as $line) {
					$refunds[(int) $line['order_id']][] = $line;
				}
			}

			foreach ($ids as $id) {
				$values = pg_return_refund_backfill_values(
					isset($audit[$id]) ? $audit[$id] : null,
					isset($refunds[$returns[$id]]) ? $refunds[$returns[$id]] : array(),
					$id);

				if ($values === null) {
					continue;
				}

				db("UPDATE order_returns
					SET refund_amount = '" . (int) $values['amount'] . "',
						refund_method = '" . e($values['method']) . "',
						refund_reference = '" . e(mb_substr($values['reference'], 0, 255)) . "',
						refund_unclear = '" . (int) $values['unclear'] . "'
					WHERE (id = '" . (int) $id . "') AND (refund_method = '')");

				$filled += max(0, (int) mysqli_affected_rows(db::$con));
			}
		}

		$left = count($returns) - $filled;

		if ($filled > 0) {
			install_ran('order_returns: the refund of ' . $filled . ' refunded return(s) filled in from the audit trail and the order refunds');
		}

		if ($left > 0) {
			install_skipped('order_returns: ' . $left . ' refunded return(s) left without their refund: neither the audit trail nor the order refunds hold it');
		}
	}

	install_note('Returns: a refunded return now keeps its refund (amount, how it was made, the reference and whether the payment service answered clearly); returns refunded earlier are filled in from the audit trail and the order refunds.');

}

// Short links as redirects (router.php, includes/fn/short_link_redirects.php).
//
// redirect_code says how a link answers: 0 as before (a page, product group,
// product or file is shown at the short address; an address off the site is
// sent on with 301 when the link is permanent, 302 otherwise), 301 or 302 to
// send the visitor to the destination's own address with that status, the
// way an old address of a moved page should answer. is_pattern marks a name
// with a * in it ("blog/*"): such a link is tried only when no page answers
// the address, so it can never hide a live page, and its target may reuse
// what each * matched as $1, $2. idx_pattern serves that lookup.
//
// The name was a 100-character slug and the address 255 characters; an old
// address brought over from another site needs more of both. name stops at
// 191 so its index stays within 767 bytes in utf8mb4 (InnoDB without large
// prefixes, MyISAM's 1000-byte key). A column already that wide is left as it
// is, so a second run changes nothing.
function upgrade_2026_11_0_short_link_redirects() {

	install_add_column('short_links', 'redirect_code', "SMALLINT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('short_links', 'is_pattern', "TINYINT(1) NOT NULL DEFAULT 0");
	install_add_index('short_links', 'idx_pattern', "INDEX idx_pattern (is_pattern)");

	$widen = function ($column, $width, $definition) {

		$info = install_column_info('short_links', $column);

		if (is_array($info) && preg_match('/^varchar\((\d+)\)/i', (string) $info['Type'], $type) && ((int) $type[1] < $width)) {
			install_modify_column('short_links', $column, $definition);
		} else {
			install_skipped(lang(array('string' => '{var:1} already exists', 'vars' => 'short_links.' . $column . ' ' . $width)));
		}
	};

	$widen('name', 191, "VARCHAR(191) NOT NULL DEFAULT ''");
	$widen('url', 2048, "VARCHAR(2048) NOT NULL DEFAULT ''");

	install_note('Short links: a short link can now send visitors on to its destination with a permanent (301) or temporary (302) redirect, its name can be an old address with Turkish letters, and a * in a name stands for any part of an address that no page answers.');

}

// Page revisions (includes/fn/page_revisions.php).
//
// page_revisions keeps one row for every publish of a visual-designer page:
// the tree as saved, the SHA-1 of the tree and of the HTML drawn from it, the
// page's settings as JSON, who published, when and from where (publish,
// restore, import, ai). revision_no counts up per page; the unique key is what
// makes two saves of one page at the same moment take different numbers. The
// two config columns are the retention rules: the last N revisions of a page
// are kept, and so is every revision younger than D days.
function upgrade_2026_11_0_page_revisions() {

	install_create_table('page_revisions', "CREATE TABLE page_revisions (
		revision_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
		page_id        INT UNSIGNED NOT NULL DEFAULT 0,
		style_id       INT UNSIGNED NOT NULL DEFAULT 0,
		revision_no    INT UNSIGNED NOT NULL DEFAULT 0,
		page_tree_json LONGTEXT NOT NULL,
		tree_hash      CHAR(40) NOT NULL DEFAULT '',
		tree_code_hash CHAR(40) NOT NULL DEFAULT '',
		settings_json  TEXT NOT NULL,
		user_id        INT UNSIGNED NOT NULL DEFAULT 0,
		source         VARCHAR(16) NOT NULL DEFAULT 'publish',
		created_at     INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (revision_id),
		UNIQUE KEY idx_page_revision (page_id, revision_no),
		KEY idx_page_created (page_id, created_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_add_column('config', 'page_revisions_keep', "SMALLINT UNSIGNED NOT NULL DEFAULT 30");

	install_add_column('config', 'page_revisions_days', "SMALLINT UNSIGNED NOT NULL DEFAULT 90");

	install_note('Pages: every publish of a visual-designer page is kept as a revision, with who published it and when; the last 30 revisions of a page and every revision of the last 90 days are kept.');

}

// Scheduled publishing of visual-designer pages (includes/fn/page_schedule.php).
//
// page.publish_at is the moment a draft goes on the site and page.unpublish_at
// the moment a page on the site is taken back off it, both unix times, 0 for
// none. The general job (job.php) makes the move when the time has come and
// clears the column, so a time is a one-off event. Each column has an index:
// the job asks for the due rows on every tick, and nearly every row holds 0.
function upgrade_2026_11_0_page_schedule() {

	$columns = array(
		'publish_at'   => "INT UNSIGNED NOT NULL DEFAULT 0",
		'unpublish_at' => "INT UNSIGNED NOT NULL DEFAULT 0",
	);

	if (!install_add_columns_instant('page', $columns)) {

		foreach ($columns as $column => $definition) {

			install_add_column('page', $column, $definition);

		}

	}

	install_add_index('page', 'idx_publish_at', "INDEX idx_publish_at (publish_at)");

	install_add_index('page', 'idx_unpublish_at', "INDEX idx_unpublish_at (unpublish_at)");

	install_note('Pages: a visual-designer page can be given a time to go on the site and a time to come off it under Settings, Page; the general job makes the move. How many revisions of a page are kept is set under Site Settings, Features, Page Revisions.');

}

// What the site tells AI crawlers (2026.11.0, 11.0.21; includes/fn/ai_crawlers.php,
// the AI Crawlers card under Site Settings, SEO).
//
// robots_ai_rules writes the AI section of robots.txt: a Content-Signal line
// in the catch-all group and a "Disallow: /" group for each AI crawler that is
// not allowed. robots_ai_training is the one choice of its own there, whether
// pages may be used to train AI models; assistants and AI search follow the
// firewall's two switches. llms_txt publishes /llms.txt, built from the site's
// name, description and pages, with additional_llms_content added to it.
// Everything starts off, so robots.txt stays exactly as it was and /llms.txt
// answers as before (a file of that name, or a missing page) until the
// operator switches them on.
function upgrade_2026_11_0_ai_crawlers() {

	install_add_column('config', 'robots_ai_rules', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('config', 'robots_ai_training', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('config', 'llms_txt', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('config', 'additional_llms_content', "TEXT NULL");

	install_note('AI crawlers: robots.txt can now say how AI tools may use the site (a Content-Signal line, and a group for each AI crawler that is not allowed, in step with the firewall), and the site can publish llms.txt, a short guide to its pages for AI tools. Both are under Site Settings, SEO, AI Crawlers and stay off until switched on.');

}

// The consent log and the audit trail (2026.11.0, 11.0.16;
// includes/fn/privacy.php, includes/fn/audit.php).
//
// consent_log keeps one row for every consent a person gives or refuses: the
// cookie notice's categories, marketing by e-mail, SMS or phone call, and the
// documents accepted at checkout. Rows are only appended; the person's
// current answer is the newest row of each channel and purpose. person_token
// is a keyed hash of the e-mail address (pg_privacy_person_token()), so a row
// still proves the consent after the person's own records are erased.
//
// audit_log keeps who changed what in the panel, through the API or in a
// job: the action, the record, and the fields that changed with their values
// before and after (only those, secrets masked; pg_audit()). The free-text
// site log (log_activity()) stays as it is.
//
// channel, purpose, status, source and action are VARCHARs rather than
// ENUMs: a label added later must not turn into '' on servers that have not
// run its step. Both tables keep their rows in a backup (they are records of
// who did what, like log and erp_audit_log).
function upgrade_2026_11_0_privacy_logs() {

	install_create_table('consent_log', "CREATE TABLE consent_log (
		id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
		created_at    INT UNSIGNED NOT NULL DEFAULT 0,
		person_token  VARCHAR(64) NOT NULL DEFAULT '',
		user_id       INT UNSIGNED NOT NULL DEFAULT 0,
		contact_id    INT UNSIGNED NOT NULL DEFAULT 0,
		channel       VARCHAR(16) NOT NULL DEFAULT '',
		purpose       VARCHAR(32) NOT NULL DEFAULT '',
		status        VARCHAR(16) NOT NULL DEFAULT '',
		source        VARCHAR(32) NOT NULL DEFAULT '',
		ip            VARCHAR(45) NOT NULL DEFAULT '',
		user_agent    VARCHAR(255) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		KEY idx_person (person_token, created_at),
		KEY idx_user (user_id, created_at),
		KEY idx_contact (contact_id, created_at),
		KEY idx_created (created_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('audit_log', "CREATE TABLE audit_log (
		id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
		created_at    INT UNSIGNED NOT NULL DEFAULT 0,
		request_id    VARCHAR(32) NOT NULL DEFAULT '',
		source        VARCHAR(8) NOT NULL DEFAULT '',
		user_id       INT UNSIGNED NOT NULL DEFAULT 0,
		username      VARCHAR(100) NOT NULL DEFAULT '',
		ip            VARCHAR(45) NOT NULL DEFAULT '',
		action        VARCHAR(64) NOT NULL DEFAULT '',
		entity_type   VARCHAR(32) NOT NULL DEFAULT '',
		entity_id     INT UNSIGNED NOT NULL DEFAULT 0,
		label         VARCHAR(255) NOT NULL DEFAULT '',
		before_json   MEDIUMTEXT NULL,
		after_json    MEDIUMTEXT NULL,
		PRIMARY KEY (id),
		KEY idx_created (created_at),
		KEY idx_entity (entity_type, entity_id, created_at),
		KEY idx_user (user_id, created_at),
		KEY idx_request (request_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Privacy: a consent log and an audit trail are created. Both start empty; the screens that write and show them come with later updates.');

}

// A page's own canonical address and two more robots directives (2026.11.0,
// 11.0.22; includes/fn/seo.php, the page settings of edit_page.php and of the
// visual designer).
//
// page.canonical_url replaces the address the page names as canonical when it
// is drawn at its own address: a full http(s) address, or a path on this site
// starting with "/"; NULL or '' leaves the automatic one. page.noarchive adds
// noarchive to the robots tag. page.max_snippet adds max-snippet: NULL leaves
// the snippet to the search engine, -1 lifts the limit, 0 asks for no text
// snippet, a number caps it at that many characters.
//
// Added without copying the table where the server can do that, like the
// meta tags of 11.0.3.
function upgrade_2026_11_0_page_canonical_robots() {

	$columns = array(
		'canonical_url' => "TEXT NULL",
		'noarchive'     => "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0",
		'max_snippet'   => "INT NULL DEFAULT NULL",
	);

	if (!install_add_columns_instant('page', $columns)) {

		foreach ($columns as $column => $definition) {

			install_add_column('page', $column, $definition);

		}

	}

	install_note('Search engines: a page can now name its own canonical address and ask search engines not to keep a cached copy (noarchive) or to limit the text they show under its title (max-snippet). The fields are in the page settings and stay empty until filled in.');

}

// The texts written with AI (2026.11.0, 11.0.23; pg_ai_fields_*() in
// includes/fn/ai_write.php, the product screen and the page properties screen).
//
// products.ai_fields and page.ai_fields hold, as a JSON object, the columns of
// the row whose text came from an accepted "Write with AI" draft, each with a
// fingerprint of the text it was put on ({"title":"<16 hex digits>"}); NULL is
// none. A mark holds only while its column still has that text, so a text
// edited by hand, on the screen or through any other way in, has lost it. Both
// start NULL: nothing written before this version is marked.
//
// Added without copying the tables where the server can do that, as 11.0.2 and
// 11.0.3 do: the catalogue and the page trees make a copy slow on a big site.
function upgrade_2026_11_0_ai_fields() {

	$tables = array(
		'products' => array('ai_fields' => "TEXT NULL"),
		'page'     => array('ai_fields' => "TEXT NULL"),
	);

	foreach ($tables as $table => $columns) {

		if (!install_add_columns_instant($table, $columns)) {

			foreach ($columns as $column => $definition) {

				install_add_column($table, $column, $definition);

			}

		}

	}

	install_note('Write with AI: a text that came from an accepted AI draft on the product screen or the page properties screen is now marked beside its field. The mark goes when the text is edited by hand, when it is removed beside the field, or when the field is saved empty.');

}

// The two filters of the order invoice the ERP issues on its own
// (config.erp_auto_invoice_on, erp_auto_invoice_decision() in
// includes/erp/order_bridge.php): only buyers with a tax number, and the
// smallest order it is issued for. The amount is kept in minor units of the
// base currency, BIGINT like the ERP's other amounts. Both start off, so a
// store that already invoices its orders on its own goes on as before.
function upgrade_2026_11_0_erp_auto_invoice() {

	install_add_column('config', 'erp_auto_invoice_tax_number_only', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('config', 'erp_auto_invoice_min_total', "BIGINT NOT NULL DEFAULT 0");

	install_note('ERP: the invoice the ERP issues on its own for a paid or shipped web order can now be kept to buyers with a tax number and to orders from an amount up, under Site Settings, Ecommerce, ERP. Both are off until set there.');

}

// The two e-Fatura thresholds the ERP weighs the calendar year's gross sales
// against (includes/erp/efatura_threshold.php): sales over the internet and
// every sale. A seller that reaches one must join e-Fatura from the seventh
// month of the next year. Settings, not code, because the tax authority moves
// them from year to year. Minor units of the base currency, BIGINT like the
// ERP's other amounts; they start at the amounts in force for 2026 (500 Bin TL
// and 3 Milyon TL), and 0 switches a warning off.
function upgrade_2026_11_0_erp_efatura_threshold() {

	install_add_column('config', 'erp_efatura_web_threshold', "BIGINT UNSIGNED NOT NULL DEFAULT 50000000");
	install_add_column('config', 'erp_efatura_general_threshold', "BIGINT UNSIGNED NOT NULL DEFAULT 300000000");

	install_note('ERP: System Status warns when the year\'s sales come near the e-Fatura threshold (500 Bin TL over the internet, 3 Milyon TL in all) and names the month the move falls due. The amounts are under ERP Settings; 0 switches a warning off.');

}

// The storefront's conversion pack (2026.11.0, 11.0.14; includes/fn/reviews.php).
//
// Product reviews stay comments. comments.verified_order_id is the order that
// let a customer review the product (0: not a verified purchase - a comment
// written before this step, a member of staff's note, a page comment), and
// seller_reply / seller_reply_at are the store's answer shown under it.
// products.rating_avg and rating_count are the average stars and the number of
// a product's published reviews from verified purchases, kept on the row so a
// product list can show them without counting; they are worked out again
// whenever such a review changes, and once a day from the comments.
//
// wishlist_items keeps a product saved for later: a customer's by user_id, a
// visitor's by the token of their cookie until they sign in. stock_notify
// keeps a request to be told when a product is back in stock, by e-mail, user
// or phone; sent_at is stamped once the notice went out.
//
// order_review_request, the e-mail that asks a customer to review what they
// received, is added to the action enums of the campaign profiles and of the
// campaigns made from them. Each list is read first and only widened, so a
// value another step added stays; the default and the null rule are kept as
// they are.
function upgrade_2026_11_0_conversion_pack() {

	$comment_columns = array(
		'verified_order_id' => "INT UNSIGNED NOT NULL DEFAULT 0",
		'seller_reply'      => "TEXT NULL",
		'seller_reply_at'   => "INT UNSIGNED NOT NULL DEFAULT 0",
	);

	if (!install_add_columns_instant('comments', $comment_columns)) {

		foreach ($comment_columns as $column => $definition) {

			install_add_column('comments', $column, $definition);

		}

	}

	install_add_index('comments', 'idx_verified_order', "INDEX idx_verified_order (verified_order_id)");

	$product_columns = array(
		'rating_avg'   => "DECIMAL(3,2) NOT NULL DEFAULT 0.00",
		'rating_count' => "INT UNSIGNED NOT NULL DEFAULT 0",
	);

	if (!install_add_columns_instant('products', $product_columns)) {

		foreach ($product_columns as $column => $definition) {

			install_add_column('products', $column, $definition);

		}

	}

	install_create_table('wishlist_items', "CREATE TABLE wishlist_items (
		id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id       INT UNSIGNED NOT NULL DEFAULT 0,
		cookie_token  VARCHAR(64) NOT NULL DEFAULT '',
		product_id    INT UNSIGNED NOT NULL DEFAULT 0,
		added_at      INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uniq_owner_product (user_id, cookie_token, product_id),
		KEY idx_product (product_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('stock_notify', "CREATE TABLE stock_notify (
		id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
		product_id     INT UNSIGNED NOT NULL DEFAULT 0,
		email_address  VARCHAR(100) NOT NULL DEFAULT '',
		user_id        INT UNSIGNED NOT NULL DEFAULT 0,
		phone          VARCHAR(50) NOT NULL DEFAULT '',
		created_at     INT UNSIGNED NOT NULL DEFAULT 0,
		sent_at        INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_product_sent (product_id, sent_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	foreach (array('email_campaign_profiles', 'email_campaigns') as $table) {

		$action = install_column_info($table, 'action');

		if (!$action) {

			continue;

		}

		if (strpos((string) $action['Type'], "'order_review_request'") !== false) {

			install_skipped(lang(array('string' => '{var:1} already exists', 'vars' => $table . '.action order_review_request')));

			continue;

		}

		preg_match_all("/'((?:[^']|'')*)'/", (string) $action['Type'], $matches);

		$values = $matches[1];
		$values[] = 'order_review_request';

		$definition = "ENUM('" . implode("','", $values) . "')"
			. ((($action['Null'] ?? '') === 'YES') ? ' NULL' : ' NOT NULL')
			. (($action['Default'] !== null) ? " DEFAULT '" . e((string) $action['Default']) . "'" : '');

		install_modify_column($table, 'action', $definition);

	}

	install_note('Product reviews: a review of a product can now be written only by a customer who bought it, signed in with the account they ordered with, and it is marked as a verified purchase. Comments written before this version stay as they are; only verified reviews count towards the star rating given to search engines.');

}

// The VAT rate each order line was charged at (order_items.tax_rate, NULL for
// a line written before this step or by a path that does not set it). The
// checkout writes it with the line's tax (update_order_item_taxes()), the till
// with the sale (includes/local_sale.php). Until now the rate was worked back
// out of the line total and its tax (erp_line_tax_rate()), which only holds
// while every line's tax is its own rate x amount; the order invoice and the
// e-document need the rate itself. DECIMAL(6,3) like the ERP's rate columns.
// Added in place where the server can (the table grows with every order),
// copied otherwise.
function upgrade_2026_11_0_erp_order_item_tax_rate() {

	$columns = array(
		'tax_rate' => "DECIMAL(6,3) NULL DEFAULT NULL",
	);

	if (!install_add_columns_instant('order_items', $columns)) {

		foreach ($columns as $column => $definition) {

			install_add_column('order_items', $column, $definition);

		}

	}

	install_note('Orders: each order line now keeps the VAT rate it was charged at, so the invoice drawn from an order carries the rate itself rather than one worked back from the amounts. Lines of earlier orders keep working as before.');

}

// The Stripe payment driver on the PaymentIntents API (2026.11.0, 11.0.24;
// includes/payments/stripe.php).
//
// config.ecommerce_payment_gateway is an ENUM and the connection runs without
// strict mode, so a gateway value outside the list is stored as '': selecting
// the driver would quietly switch the card gateway off. 'Stripe PaymentIntents'
// is appended to the list the column already has, as 4.48 appended
// 'Pay With Iyzico' to orders.payment_method; the value 'Stripe' stays the
// classic Charges code. Nullability and default are copied from the column as
// it is. The driver keeps its keys in connect_apps, so no other column is
// needed.
function upgrade_2026_11_0_stripe_payment_intents() {

	$column = install_column_info('config', 'ecommerce_payment_gateway');

	if (!is_array($column)) {

		install_skipped(lang('config.ecommerce_payment_gateway does not exist, skipped'));

	} elseif (stripos((string) $column['Type'], "'Stripe PaymentIntents'") !== false) {

		install_skipped(lang('config.ecommerce_payment_gateway already knows about Stripe PaymentIntents'));

	} elseif (preg_match('/^enum\((.*)\)$/is', trim((string) $column['Type']), $enum_match)) {

		$nullable = (strtoupper((string) $column['Null']) === 'YES');
		$default  = ($column['Default'] === null) ? ($nullable ? 'NULL' : "''") : "'" . e((string) $column['Default']) . "'";

		install_modify_column('config', 'ecommerce_payment_gateway',
			"ENUM(" . $enum_match[1] . ",'Stripe PaymentIntents') " . ($nullable ? 'NULL' : 'NOT NULL') . " DEFAULT " . $default);

	} else {

		install_skipped(lang('config.ecommerce_payment_gateway is not an ENUM, skipped'));

	}

	install_note('Stripe can now take card payments on the PaymentIntents API, with the card, Apple Pay and Google Pay typed into Stripe\'s own fields on the checkout page and 3-D Secure. Choose "Stripe" as the Payment Gateway in the Ecommerce settings; "Stripe (classic)" keeps working as before.');
}

// Passkeys (2026.11.0, 11.0.13; includes/fn/passkeys.php, passkey.php).
//
// user_passkeys keeps one row per WebAuthn credential an account registered:
// what the verifier (includes/webauthn/) needs to check a later sign-in - the
// credential ID, its public key as PEM with its COSE algorithm, the signature
// counter, the backup eligibility fixed at registration and the user handle
// of the account - and what an account screen shows: a name, the AAGUID that
// says which authenticator made it, the transports the browser reported,
// whether it is backed up now, when it was added and last used.
//
// Binary values are kept as base64url text in ASCII columns, compared as
// bytes (ascii_bin): in base64url "a" and "A" are different values. A
// credential ID may be 1023 bytes, longer than an index of the older InnoDB
// row formats takes (767 bytes), so the unique key is the SHA-256 of the raw
// ID in hex.
// The user handle is 32 random bytes drawn at the account's first passkey and
// copied to the next ones; a handle derived from the site's keys would change
// with them and orphan every passkey made before. Rows go with their account.
function upgrade_2026_11_0_passkeys() {

	install_create_table('user_passkeys', "CREATE TABLE user_passkeys (
		id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id          INT UNSIGNED NOT NULL,
		credential_hash  CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		credential_id    VARCHAR(1364) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		public_key       TEXT CHARACTER SET ascii NOT NULL,
		alg              SMALLINT NOT NULL,
		sign_count       INT UNSIGNED NOT NULL DEFAULT 0,
		transports       VARCHAR(64) CHARACTER SET ascii NOT NULL DEFAULT '',
		aaguid           CHAR(36) CHARACTER SET ascii NOT NULL DEFAULT '',
		backup_eligible  TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
		backup_state     TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
		user_handle      VARCHAR(86) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		name             VARCHAR(100) NOT NULL DEFAULT '',
		created_at       INT UNSIGNED NOT NULL DEFAULT 0,
		last_used_at     INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uq_credential (credential_hash),
		KEY idx_user (user_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Passkeys: the table that will keep the passkeys of accounts is added. Nothing changes on the site with this update.');

}

// The 404 log (includes/fn/not_found_log.php). One row per address that
// answered 404 after the short links had their chance
// (pg_short_link_not_found()), in the form a short link name takes, so a row
// can become a redirect as it is: the path without the language prefix and the
// query string, at most 191 characters like short_links.name. A repeat raises
// the counts and adds no row. path_key is the SHA-1 of the path: a unique key
// on the path itself would fold "Old-Page" and "old-page" into one row under
// utf8mb4_unicode_ci. hit_count counts every recorded request, crawler_hits
// those of them from search engine crawlers the firewall verified; other bots
// are not recorded. referrer keeps the first referring address seen (scheme,
// host and path). The general job removes rows not seen for 90 days, and the
// writer keeps the table under its row cap.
function upgrade_2026_11_0_not_found_log() {

	install_create_table('not_found_log', "CREATE TABLE not_found_log (
		id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
		path_key      CHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		path          VARCHAR(191) NOT NULL DEFAULT '',
		referrer      VARCHAR(512) NOT NULL DEFAULT '',
		hit_count     INT UNSIGNED NOT NULL DEFAULT 0,
		crawler_hits  INT UNSIGNED NOT NULL DEFAULT 0,
		first_seen    INT UNSIGNED NOT NULL DEFAULT 0,
		last_seen     INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uq_path (path_key),
		KEY idx_last_seen (last_seen)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('404 log: addresses no page, file or short link answers are now counted with where visitors came from, and a redirect can be made from each of them. Addresses not asked for in 90 days are removed.');

}

// Repeating invoices of a month-end day (2026.11.0, 11.0.60;
// includes/erp/invoice_recurring.php, #1074).
//
// Before erp_invoice_recurring_day() a recurrence kept its first date's day.
// The invoice screen proposes the first date one month after the issue date,
// cut to the month end, so an invoice of the 31st started on 28 February and
// kept the 28th for good; 31 March gave 30 April and the 30th. The first
// date is not stored (next_date moves on with every run), so a row is moved
// back to the invoice's own day only when it can be shown to have started on
// that proposal P = issue_date + 1 month: P was cut (its day is the stored day
// and below the invoice's day) and next_date is exactly `occurrences` periods
// after P, which is where every written run leaves it. A first date the
// operator changed, a period skipped by the lock (next_date ahead of the
// count) or a deleted source invoice leaves the row as it is; stopping and
// starting the recurrence again stores the right day.
//
// The pending next_date moves to the right day within its own month (28.11
// becomes 30.11 for an invoice of the 31st), never into another month, so no
// period is written twice or left out; it stays where it is when the moved
// date would pass end_date. Both SET expressions read only the source invoice
// and the old next_date, never day_of_month, so the order MySQL assigns the
// columns of a multi-table UPDATE in does not matter. Re-runnable: a fixed
// row has day_of_month equal to the invoice's day and no longer matches.
function upgrade_2026_11_0_erp_invoice_recurring_day() {

	if (!install_column_exists('erp_invoice_recurrences', 'day_of_month')) {

		install_skipped('erp_invoice_recurrences: the table does not exist, skipped');

		return;

	}

	db("UPDATE erp_invoice_recurrences AS r
		INNER JOIN erp_invoices AS i ON i.id = r.source_invoice_id
		SET r.next_date = IF(
				r.end_date <> '0000-00-00'
				AND DATE_ADD(r.next_date, INTERVAL LEAST(DAY(i.issue_date), DAY(LAST_DAY(r.next_date))) - DAY(r.next_date) DAY) > r.end_date,
				r.next_date,
				DATE_ADD(r.next_date, INTERVAL LEAST(DAY(i.issue_date), DAY(LAST_DAY(r.next_date))) - DAY(r.next_date) DAY)
			),
			r.day_of_month = DAY(i.issue_date),
			r.updated_at = UNIX_TIMESTAMP()
		WHERE r.status = 'active'
			AND r.next_date > '0000-00-00'
			AND i.issue_date > '0000-00-00'
			AND r.day_of_month IN (28, 29, 30)
			AND DAY(i.issue_date) > r.day_of_month
			AND DAY(DATE_ADD(i.issue_date, INTERVAL 1 MONTH)) = r.day_of_month
			AND r.next_date = DATE_ADD(DATE_ADD(i.issue_date, INTERVAL 1 MONTH), INTERVAL r.occurrences * r.every_months MONTH)");

	$fixed = max(0, (int) mysqli_affected_rows(db::$con));

	if ($fixed > 0) {

		install_ran('erp_invoice_recurrences: ' . $fixed . ' repeating invoice(s) moved back to the day of the month of the invoice they repeat');

	} else {

		install_skipped('erp_invoice_recurrences: no repeating invoice kept a month-end day cut from the day of its invoice');

	}

	install_note('ERP: a repeating invoice started from an invoice dated the 29th, 30th or 31st no longer stays on the 28th, 29th or 30th the first short month cut it to; it goes on to the invoice\'s own day, or the last day of a shorter month. A recurrence whose first date was changed by hand keeps its day: stop it and start it again from the invoice to fix it.');

}

// Collections (includes/fn/collections.php): a designer form marked as a
// collection, and the kind of each of its fields with the form a reference
// field points into. Pickers, lists, feeds and the API read them without
// loading the form page's design. VARCHAR, not ENUM, so a new kind needs no
// migration. Every existing form and field stays as it was (0 and '').
function upgrade_2026_11_0_collections() {

	install_add_column('custom_form_pages', 'collection', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('form_fields', 'collection_kind', "VARCHAR(32) NOT NULL DEFAULT ''");
	install_add_column('form_fields', 'reference_page_id', "INT(10) UNSIGNED NOT NULL DEFAULT 0");

	install_note('Forms: a designed form can be marked as a collection, and its fields can be given a kind (number, image, reference to another form); existing forms are unchanged.');

}

// A switch on each text field of a custom form: switched off, the answers
// visitors give in that field (icon names, codes, JSON) are never listed for
// translation nor looked up when a page shows them. Existing fields stay
// included, the way they behaved before.
function upgrade_2026_11_0_form_field_translate() {

	install_add_column('form_fields', 'translate_values', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1");

	install_note('Forms: each text field can now be left out of translation; existing fields stay included.');

}

// Custom form submission limits (11.0.28): how many submissions one visitor
// address may make an hour, and one e-mail address a day and one e-mail
// domain an hour where the form mails, subscribes or registers that address
// (the domain limit off by default: one counter for every visitor at that
// domain, so a few submissions could refuse all of gmail.com for the hour);
// and the StopForumSpam switch, off by default because it sends the visitor's
// address to a third party. Read through waf_setting() with these same
// defaults, so the limits apply before the step runs too; the columns make
// them editable under Site Settings, Security.
function upgrade_2026_11_0_form_protection() {

	install_add_column('config', 'form_limit_ip_hour', "SMALLINT UNSIGNED NOT NULL DEFAULT 10");
	install_add_column('config', 'form_limit_recipient_day', "SMALLINT UNSIGNED NOT NULL DEFAULT 3");
	install_add_column('config', 'form_limit_domain_hour', "SMALLINT UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('config', 'form_spam_blacklist', "TINYINT(1) NOT NULL DEFAULT 0");

	install_note('Forms: custom form submissions are limited per visitor address (10 an hour) and per e-mail address (3 a day), and the limits can be changed under Site Settings, Security. A limit per e-mail domain and a check against StopForumSpam can be switched on there; both stay off.');

}

// A per-form switch for double opt-in: with it on, a custom form that adds its
// sender to a contact group mails a signed confirmation link instead, and the
// contact joins the group only when the button behind the link is pressed
// (includes/fn/form_subscription.php). Off on every existing form, so no form
// changes what it does until the switch is turned on.
function upgrade_2026_11_0_form_double_opt_in() {

	install_add_column('custom_form_pages', 'double_opt_in', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");

	install_note('Forms: a form that adds its sender to a contact group can now ask for confirmation by e-mail first (double opt-in). The switch is off on every form until it is turned on in the settings of the form.');

}

// The page cache's generation number (2026.11.0, 11.0.29;
// includes/fn/page_cache.php). Pages kept on disk live in the folder of one
// generation; a change that may alter what pages show raises the number, so
// no copy made before it is served again. The router reads it from the config
// row it reads anyway. The cache itself is off until switched on.
function upgrade_2026_11_0_page_cache_generation() {

	install_add_column('config', 'page_cache_generation', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_note('Page cache: the number that tells which pages kept on disk are current is added. Nothing changes on the site with this update.');

}

// The Themes panel's old combined light block in stored designs (2026.11.0,
// 11.0.32; no table changes; includes/fn/designer_theme_vars.php).
//
// Until the panel kept its light and shared blocks apart it wrote every
// light value under `:root, [data-bs-theme="light"]`. That `:root` also
// matches an <html> a theme switcher set to dark, and the design's User
// Styles load after bootstrap.css, so a light Background (#fdf6e3) kept a
// "Dark" page light with light grey text. The editor splits the block when a
// design is opened and before every panel write; a design nobody opens kept
// it. This step makes the same split, byte for byte, wherever the old block
// is stored and served without the editor:
//   - the design's asset list, style.style_custom_css (its User Styles entry,
//     meta 'user-styles');
//   - the per-page copy designs saved before 2026.4.4 still render from
//     while the design's own list is empty, page.page_custom_css;
//   - the asset list inside a design template made from a design,
//     design_template.template_json (assets.css), which a new design is
//     installed from;
//   - a theme file the old "Save these colours as a theme" wrote: a design
//     CSS file in exactly that shape, loaded after the User Styles. It is
//     written again as the button writes it now. A stylesheet written or
//     changed by hand is not in that shape and is left alone, and so are
//     the palette files (their unquoted `[data-bs-theme=light]` is not the
//     panel's block, and they set every colour again in their dark block).
// A row that is not a JSON list, has no User Styles entry or holds no old
// block is left as it is, so a second run changes nothing.
function upgrade_2026_11_0_theme_light_selector() {

	if (!function_exists('pg_theme_vars_upgrade_assets_json')) {

		install_skipped('Visual designer: the old light colours of the Themes panel were not split, because includes/fn/designer_theme_vars.php is missing. Upload the files of this version again and run the upgrade.');

		return;

	}

	$designs = upgrade_2026_11_0_theme_light_selector_rows('style', 'style_id', 'style_custom_css', 'pg_theme_vars_upgrade_assets_json');

	$pages = upgrade_2026_11_0_theme_light_selector_rows('page', 'page_id', 'page_custom_css', 'pg_theme_vars_upgrade_assets_json');

	$templates = upgrade_2026_11_0_theme_light_selector_rows('design_template', 'id', 'template_json', 'pg_theme_vars_upgrade_template_json');

	$files = upgrade_2026_11_0_theme_light_selector_files();

	@unlink(upgrade_2026_11_0_theme_light_selector_progress_file());

	if (($designs + $pages + $templates + $files) === 0) {

		install_skipped('Visual designer: no design keeps its Themes panel light colours in the old block that also applied to a dark page.');

		return;

	}

	install_ran('Visual designer: the Themes panel light colours that also applied to a page a theme switcher set to dark were split from the fonts and radii, as the editor does when a design is opened (designs: ' . $designs . ', pages: ' . $pages . ', design templates: ' . $templates . ', theme files: ' . $files . '). A dark page now takes the dark colours; light pages look as before.');

}

// How far the step got, per table and column: the last id read and how many
// rows were rewritten. The next pass goes on from there: in the same request
// from memory, in a new one (the upgrade screen runs a paused version again
// in the next request) from a file in data/temp written before every pause.
// The file only saves reading the rows done before again and keeps the
// counts of the final note; without it the step starts from the first row
// and finds nothing more to split in the rows it already rewrote. The file
// is deleted when the step has gone through every row; a file older than
// ten minutes belongs to a run that was given up on and is not read,
// because the rows may have been restored from a backup since.
function upgrade_2026_11_0_theme_light_selector_progress_file() {

	return dirname(__FILE__) . '/../../data/temp/upgrade_theme_light_selector.json';

}

function &upgrade_2026_11_0_theme_light_selector_progress() {

	static $progress = null;

	if ($progress === null) {

		$progress = array();

		$saved = @json_decode((string) @file_get_contents(upgrade_2026_11_0_theme_light_selector_progress_file()), true);

		if (is_array($saved) && isset($saved['time'], $saved['rows']) && is_array($saved['rows']) && ((time() - (int) $saved['time']) < 600)) {

			foreach ($saved['rows'] as $key => $row) {

				if (is_array($row) && isset($row['last'], $row['changed'])) {

					$progress[(string) $key] = array('last' => (int) $row['last'], 'changed' => (int) $row['changed']);

				}

			}

		}

	}

	return $progress;

}

function upgrade_2026_11_0_theme_light_selector_progress_save() {

	$file = upgrade_2026_11_0_theme_light_selector_progress_file();

	if (!is_dir(dirname($file))) {

		@mkdir(dirname($file), 0755, true);

	}

	@file_put_contents($file, json_encode(array('time' => time(), 'rows' => upgrade_2026_11_0_theme_light_selector_progress())), LOCK_EX);

}

// The rows of one table whose column holds a User Styles entry, one at a
// time in id order (a design's assets can be megabytes), each rewritten
// with $rewrite when it gives a new text. Returns how many were rewritten,
// in this pass and the ones before it. When the pass has used its time
// budget the version pauses and the next pass goes on after the last id
// it reached.
function upgrade_2026_11_0_theme_light_selector_rows($table, $id_column, $column, $rewrite) {

	$progress = &upgrade_2026_11_0_theme_light_selector_progress();

	if (!install_table_exists($table) || !install_column_exists($table, $column)) {

		return 0;

	}

	$key = $table . '.' . $column;

	if (!isset($progress[$key])) {

		$progress[$key] = array('last' => 0, 'changed' => 0);

	}

	while (true) {

		$row = db_item(
			"SELECT `$id_column` AS id, `$column` AS value FROM `$table`
			WHERE (`$id_column` > '" . (int) $progress[$key]['last'] . "') AND (`$column` LIKE '%user-styles%')
			ORDER BY `$id_column`
			LIMIT 1");

		if (!$row) {

			break;

		}

		$progress[$key]['last'] = (int) $row['id'];

		$value = (string) $row['value'];

		$new = $rewrite($value);

		if (is_string($new) && ($new !== $value)) {

			db("UPDATE `$table` SET `$column` = '" . e($new) . "' WHERE `$id_column` = '" . (int) $row['id'] . "'");

			$progress[$key]['changed']++;

		}

		if (install_pass_seconds() >= install_pause_budget()) {

			upgrade_2026_11_0_theme_light_selector_progress_save();

			install_pause('Visual designer: splitting the old Themes panel light colours, ' . $table . ' up to id ' . (int) $row['id'] . '.');

		}

	}

	return $progress[$key]['changed'];

}

// Theme files the old "Save these colours as a theme" wrote: design CSS
// files in the file manager, in exactly the shape that button wrote
// (pg_theme_vars_is_old_theme_file()), written again as it writes them now.
// Returns how many were rewritten.
function upgrade_2026_11_0_theme_light_selector_files() {

	if (!defined('FILE_DIRECTORY_PATH') || !install_column_exists('files', 'design')) {

		return 0;

	}

	$changed = 0;

	foreach ((array) db_items("SELECT id, name FROM files WHERE (design = '1') AND (type = 'css') ORDER BY id") as $row) {

		$name = (string) $row['name'];

		if (($name === '') || ($name !== basename($name)) || ($name[0] === '.')) {

			continue;

		}

		$path = FILE_DIRECTORY_PATH . '/' . $name;

		// The button refuses a stylesheet over 500 KB.
		if (!is_file($path) || (filesize($path) > 512000)) {

			continue;

		}

		$css = @file_get_contents($path);

		if (!is_string($css) || !pg_theme_vars_is_old_theme_file($css) || !preg_match('//u', $css)) {

			continue;

		}

		$new = pg_theme_vars_theme_css($css);

		if (($new === '') || ($new === pg_theme_vars_trim($css))) {

			continue;

		}

		if (@file_put_contents($path, $new) === false) {

			install_note('Visual designer: the theme file ' . $name . ' in data/files could not be written, so it still keeps its light colours on a page a theme switcher set to dark. Save the colours as a theme again from the Themes panel.');

			continue;

		}

		db("UPDATE files SET size = '" . (int) strlen($new) . "' WHERE id = '" . (int) $row['id'] . "'");

		$changed++;

	}

	return $changed;

}

// Replies to comments (2026.11.0, 11.0.31).
//
// comments.parent_id is the comment a comment answers, 0 for one that answers
// none. Every comment written before this step reads 0 and stays a top-level
// comment, so nothing is migrated; the classic comments block does not read
// the column. idx_parent finds the replies to a comment; idx_thread reads one
// thread (page, item type, item) in the order it was written, which the
// existing page_id key leaves to a sort of every comment on the page.
//
// The column is added in place where the server can, as 11.0.14 does for the
// same table. An index added to an InnoDB table is built in place; on a
// comments table left on MyISAM (2026.4.8 converts it unless it is over the
// size limits, upgrade_2026_4_8_innodb_limits()) the column and each index
// copy the table whole, and on the first run 11.0.14 has already copied it up
// to four times in the same pass. So none of the three is started once this
// pass has run a statement and its budget is spent (install_pass_spent()); it
// waits for the next pass, in the next request when the screen runs one
// version at a time.
function upgrade_2026_11_0_comment_replies() {

	$columns = array(
		'parent_id' => "INT UNSIGNED NOT NULL DEFAULT 0",
	);

	if (!install_add_columns_instant('comments', $columns)) {

		foreach ($columns as $column => $definition) {

			if (install_pass_spent()) {

				install_pause(lang(array('string' => 'comments: replies are being added; {var:1} follows in the next pass', 'vars' => $column)));

			}

			install_add_column('comments', $column, $definition);

		}

	}

	$indexes = array(
		'idx_parent' => "INDEX idx_parent (parent_id)",
		'idx_thread' => "INDEX idx_thread (page_id, item_type, item_id, created_timestamp)",
	);

	foreach ($indexes as $index => $definition) {

		if (install_pass_spent() && !install_index_exists('comments', $index)) {

			install_pause(lang(array('string' => 'comments: replies are being added; {var:1} follows in the next pass', 'vars' => $index)));

		}

		install_add_index('comments', $index, $definition);

	}

	install_note('Comments: a comment can now be stored as a reply to another one. Every comment written before this version stays a comment of its own, and comments are shown as before.');

}

// A Workspace channel's own icon (2026.11.0, 11.0.81;
// includes/workspace/channel_icons.php). The column holds a Bootstrap Icons
// class from the curated list ws_channel_icon_choices(), empty for none; a
// channel without one keeps the icon its kind gives, so every existing
// channel looks the same after the upgrade.
function upgrade_2026_11_0_workspace_channel_icon() {

	install_add_column('ws_channels', 'icon', "VARCHAR(40) NOT NULL DEFAULT ''");

	install_note('Workspace: a channel can be given an icon of its own, chosen in the channel form. Existing channels keep their icons.');

}

// A Workspace note's revision (2026.11.0, 11.0.84; includes/workspace/notes.php).
//
// Every write of a note's title, text or pin moves ws_notes.revision on. A
// sync client sends back the revision it read with a change, and a change
// made meanwhile is refused (409 changed_meanwhile): to the change rather
// than to the second, as base (updated_at) does, and also when the person
// changed it on another device. Every note starts at 1.
function upgrade_2026_11_0_workspace_note_revision() {

	install_add_column('ws_notes', 'revision', "INT UNSIGNED NOT NULL DEFAULT 1");

	install_note('Workspace: every note carries a revision, so an app that keeps notes in sync is told when a note changed after it was read.');

}

// Deleted Workspace notes (2026.11.0, 11.0.85; includes/workspace/notes.php).
//
// A note is deleted for good (ws_note_delete()). ws_note_deletions keeps for
// 30 days which note went and for whom: a row for its owner and for each
// person it was shared with (user_id), and one for each channel it was
// shared in (channel_id, user_id 0). A sync client asks
// GET /workspace/notes?updated_since= and drops the notes listed in deleted.
function upgrade_2026_11_0_workspace_note_deletions() {

	install_create_table('ws_note_deletions', "CREATE TABLE ws_note_deletions (
		id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		note_id INT UNSIGNED NOT NULL DEFAULT 0,
		user_id INT UNSIGNED NOT NULL DEFAULT 0,
		channel_id INT UNSIGNED NOT NULL DEFAULT 0,
		deleted_by INT UNSIGNED NOT NULL DEFAULT 0,
		deleted_at INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_user (user_id, deleted_at),
		KEY idx_channel (channel_id, deleted_at),
		KEY idx_deleted (deleted_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Workspace: a deleted note is remembered for 30 days, so an app that keeps notes in sync removes it as well.');

}

// Where an imported Workspace note came from (2026.11.0, 11.0.86;
// includes/workspace/notes_import.php).
//
// One row a note file a person brought in: the app it came from (provider:
// keep, enex, markdown, html), the id it is known by there (external_id:
// Keep's JSON entry name, Evernote's guid or title and created, a Markdown
// front matter id or path, a web page's address or file name), what the file
// held (hash) and the note it became. The same file brought in again shows
// as "same", or updates that note, instead of adding it once more. A deleted
// note drops its rows. external_id compares byte for byte: under the table's
// case- and accent-blind collation "Günlük.md" and "Gunluk.md" in one vault
// were one source, and the second file overwrote the first file's note.
function upgrade_2026_11_0_workspace_note_sources() {

	install_create_table('ws_note_sources', "CREATE TABLE ws_note_sources (
		id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id INT UNSIGNED NOT NULL DEFAULT 0,
		provider VARCHAR(16) NOT NULL DEFAULT '',
		external_id VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '',
		hash CHAR(40) NOT NULL DEFAULT '',
		note_id INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY idx_source (user_id, provider, external_id),
		KEY idx_note (note_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Workspace: notes brought in from other apps remember where they came from, so importing the same notes again updates them instead of adding them twice.');

}

// The / commands an application registers for itself (2026.11.0, 11.0.87;
// includes/workspace/app_commands.php).
//
// One row a command: the application that registered it, its name (one word
// of letters, Turkish lower case, without the slash), what it does and the
// arguments after the name as /help shows them. A name is the site's: UNIQUE
// lets the database refuse a second application the name the first one
// holds, even when both register at once. Deleting the application deletes
// its commands; renaming it keeps them. usage_text, since USAGE is a
// reserved word.
function upgrade_2026_11_0_workspace_app_commands() {

	install_create_table('ws_app_commands', "CREATE TABLE ws_app_commands (
		id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		app_id INT UNSIGNED NOT NULL DEFAULT 0,
		name VARCHAR(20) NOT NULL DEFAULT '',
		description VARCHAR(200) NOT NULL DEFAULT '',
		usage_text VARCHAR(200) NOT NULL DEFAULT '',
		created_at INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY idx_name (name),
		KEY idx_app (app_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Workspace: connected applications can add their own / commands to the channels.');

}

// Workspace flows (2026.11.0, 11.0.80; includes/workspace/flows.php).
//
// A flow is a graph of boxes and arrows drawn on the Workspace canvas: a
// trigger, the tasks it opens one after another, an end. ws_flows keeps one
// row per flow: its owner, an optional home channel whose readers may run it,
// the draft being edited (graph JSON) with draft_rev, which every save raises
// so that a save made against an older draft is refused, and the live
// version. ws_flow_versions keeps every published graph as it was published
// and is never changed afterwards; a run keeps the version it started on.
// ws_flow_runs and ws_flow_run_steps hold the runs and the state of each box
// in them (one row per box and pass, so two requests that finish parallel
// boxes never write over each other). The runner comes later; the tables are
// created together so that it needs no second schema step.
// ws_scheduled_actions.flow_id ties the hidden scheduled action that starts a
// flow on a time or an event to its flow.
//
// Statuses are VARCHAR rather than ENUM, for the reason the price history step
// gives: a status added later must not turn into '' on a server that has not
// run its step. Graphs and contexts are JSON read and written in PHP only; no
// JSON column type or function is used, so the step runs on MySQL 5.7 and
// MariaDB 10.2.
function upgrade_2026_11_0_workspace_flows() {

	install_create_table('ws_flows', "CREATE TABLE ws_flows (
		id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
		owner_id    INT UNSIGNED NOT NULL DEFAULT 0,
		channel_id  INT UNSIGNED NOT NULL DEFAULT 0,
		name        VARCHAR(160) NOT NULL DEFAULT '',
		status      VARCHAR(16) NOT NULL DEFAULT 'draft',
		draft       MEDIUMTEXT NULL,
		draft_rev   INT UNSIGNED NOT NULL DEFAULT 0,
		version_id  INT UNSIGNED NOT NULL DEFAULT 0,
		updated_by  INT UNSIGNED NOT NULL DEFAULT 0,
		created_at  INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at  INT UNSIGNED NOT NULL DEFAULT 0,
		binned_at   INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_owner (owner_id, status),
		KEY idx_channel (channel_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('ws_flow_versions', "CREATE TABLE ws_flow_versions (
		id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
		flow_id       INT UNSIGNED NOT NULL DEFAULT 0,
		n             INT UNSIGNED NOT NULL DEFAULT 0,
		graph         MEDIUMTEXT NULL,
		published_by  INT UNSIGNED NOT NULL DEFAULT 0,
		published_at  INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uq_flow_n (flow_id, n)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('ws_flow_runs', "CREATE TABLE ws_flow_runs (
		id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
		flow_id       INT UNSIGNED NOT NULL DEFAULT 0,
		version_id    INT UNSIGNED NOT NULL DEFAULT 0,
		status        VARCHAR(16) NOT NULL DEFAULT 'running',
		started_by    INT UNSIGNED NOT NULL DEFAULT 0,
		actor_id      INT UNSIGNED NOT NULL DEFAULT 0,
		start_key     VARCHAR(64) NOT NULL DEFAULT '',
		subject_type  VARCHAR(32) NOT NULL DEFAULT '',
		subject_id    INT UNSIGNED NOT NULL DEFAULT 0,
		context       MEDIUMTEXT NULL,
		depth         TINYINT UNSIGNED NOT NULL DEFAULT 0,
		boxes_run     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		started_at    INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at    INT UNSIGNED NOT NULL DEFAULT 0,
		finished_at   INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uq_start (flow_id, start_key),
		KEY idx_flow (flow_id, id),
		KEY idx_status (status, updated_at),
		KEY idx_subject (subject_type, subject_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('ws_flow_run_steps', "CREATE TABLE ws_flow_run_steps (
		id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
		run_id       INT UNSIGNED NOT NULL DEFAULT 0,
		node_id      VARCHAR(32) NOT NULL DEFAULT '',
		pass         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		status       VARCHAR(16) NOT NULL DEFAULT 'ready',
		outcome      VARCHAR(32) NOT NULL DEFAULT '',
		attempt      TINYINT UNSIGNED NOT NULL DEFAULT 0,
		ready_at     INT UNSIGNED NOT NULL DEFAULT 0,
		claimed_at   INT UNSIGNED NOT NULL DEFAULT 0,
		wait_event   VARCHAR(64) NOT NULL DEFAULT '',
		wait_key     VARCHAR(64) NOT NULL DEFAULT '',
		wake_at      INT UNSIGNED NOT NULL DEFAULT 0,
		effect       VARCHAR(64) NOT NULL DEFAULT '',
		result       TEXT NULL,
		finished_at  INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uq_node (run_id, node_id, pass),
		KEY idx_ready (status, ready_at),
		KEY idx_wait (status, wait_event, wait_key),
		KEY idx_wake (status, wake_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_add_column('ws_scheduled_actions', 'flow_id', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_index('ws_scheduled_actions', 'idx_flow', "INDEX idx_flow (flow_id)");

	install_note('Workspace: the tables that will keep the flows drawn on the Workspace canvas, their published versions and their runs are added. Nothing changes in the Workspace with this update.');

}

// Which scheduled actions are a flow's anchor (2026.11.0, 11.0.88;
// includes/workspace/flow_anchors.php).
//
// ws_scheduled_actions.anchor says what a row with a flow_id is: 'hidden'
// is the row a flow's start box writes (at a time, on an event, on a join),
// left out of every list, card, history and report; 'converted' is a
// scheduled action now carried out by a flow, which keeps its id, card,
// history and news; '' is a scheduled action of its own. Every row that
// starts a flow today is a hidden anchor, so the UPDATE marks them; it only
// touches rows still unmarked and runs again harmlessly. The column comes
// after the flows step that added flow_id. The actions themselves are
// converted into flows by the Workspace's scheduled-actions driver right
// after the upgrade (includes/workspace/scheduled_convert.php): the upgrade
// does not load the Workspace code the drawing needs. A VARCHAR, so a later
// value needs no MODIFY.
function upgrade_2026_11_0_workspace_scheduled_anchor() {

	install_add_column('ws_scheduled_actions', 'anchor', "VARCHAR(12) NOT NULL DEFAULT ''");

	db("UPDATE ws_scheduled_actions SET anchor = 'hidden' WHERE flow_id <> 0 AND anchor = ''");

	install_note('Workspace: every scheduled action of the staff becomes a flow in the first minutes after this update, keeping its card, its history and its state; it is then changed on the flow canvas. Scheduled messages stay as they are.');

}

// Workspace flows shared with the team (2026.11.0, 11.0.83;
// includes/workspace/flow_shares.php).
//
// ws_flow_shares keeps one row per flow and the person or channel it is
// shared with: target_type user (target_id a user id) or channel (a channel
// id), and level view, run or edit. A person shared at edit changes the flow
// only while they are staff in the team, and runs it otherwise; a channel is
// shared at view, for the people who read it. Sharing again with the same
// person or channel changes the level of its row: UNIQUE (flow_id,
// target_type, target_id). idx_target finds the flows shared with a person
// or a channel, flow_id in the index so that the list reads no row.
//
// target_type and level are VARCHAR rather than ENUM, for the reason the
// flows step gives: a value added later must not turn into '' on a server
// that has not run its step.
function upgrade_2026_11_0_workspace_flow_shares() {

	install_create_table('ws_flow_shares', "CREATE TABLE ws_flow_shares (
		id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
		flow_id      INT UNSIGNED NOT NULL DEFAULT 0,
		target_type  VARCHAR(16) NOT NULL DEFAULT '',
		target_id    INT UNSIGNED NOT NULL DEFAULT 0,
		level        VARCHAR(16) NOT NULL DEFAULT 'view',
		created_by   INT UNSIGNED NOT NULL DEFAULT 0,
		created_at   INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uq_target (flow_id, target_type, target_id),
		KEY idx_target (target_type, target_id, flow_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Workspace: the table that keeps whom a flow is shared with is added. A flow\'s owner may share it with colleagues, to look at, to run or to change, and with a channel whose readers may look at it; nothing is shared until they do.');

}

// Workflow tables (2026.11.0, 11.0.110; includes/workspace/wtables.php).
//
// ws_wtables is one table a person keeps: its owner, name and description,
// row_count (the 5000-row limit is checked without a COUNT(*)) and revision,
// bumped on every change of its columns so that a row written against an
// older set of columns is answered 409. idx_owner lists "my tables", the
// latest changed first, and counts them for the 500-table limit.
//
// ws_wtable_columns holds a table's typed columns in order: type is one of
// ws_wtable_column_types() and options a JSON object of what that type takes
// (decimals, currency, choices, kinds, expression).
//
// ws_wtable_rows holds the rows in order, their cells one JSON object by
// column id (money in integer minor units), so that a row is written with a
// single UPDATE checked against its own revision. Filtering and sorting are
// done in PHP: MariaDB and MySQL 5.7 read JSON differently.
//
// ws_wtable_shares repeats ws_flow_shares column for column: a person
// (view or edit) or a channel (view) a table is shared with, one row each.
//
// type, target_type and level are VARCHAR rather than ENUM, for the reason
// the flows step gives. There are no foreign keys, as elsewhere: a table's
// columns, rows and shares go with it in its delete path.
function upgrade_2026_11_0_workspace_wtables() {

	install_create_table('ws_wtables', "CREATE TABLE ws_wtables (
		id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id      INT UNSIGNED NOT NULL DEFAULT 0,
		name         VARCHAR(255) NOT NULL DEFAULT '',
		description  TEXT NULL,
		row_count    INT UNSIGNED NOT NULL DEFAULT 0,
		revision     INT UNSIGNED NOT NULL DEFAULT 0,
		created_at   INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at   INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_owner (user_id, updated_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('ws_wtable_columns', "CREATE TABLE ws_wtable_columns (
		id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
		table_id     INT UNSIGNED NOT NULL DEFAULT 0,
		name         VARCHAR(255) NOT NULL DEFAULT '',
		type         VARCHAR(16) NOT NULL DEFAULT 'text',
		options      TEXT NULL,
		position     INT UNSIGNED NOT NULL DEFAULT 0,
		created_at   INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_table (table_id, position)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('ws_wtable_rows', "CREATE TABLE ws_wtable_rows (
		id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
		table_id     INT UNSIGNED NOT NULL DEFAULT 0,
		position     INT UNSIGNED NOT NULL DEFAULT 0,
		cells        MEDIUMTEXT NULL,
		revision     INT UNSIGNED NOT NULL DEFAULT 0,
		created_by   INT UNSIGNED NOT NULL DEFAULT 0,
		created_at   INT UNSIGNED NOT NULL DEFAULT 0,
		updated_by   INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at   INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_table (table_id, position)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('ws_wtable_shares', "CREATE TABLE ws_wtable_shares (
		id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
		table_id     INT UNSIGNED NOT NULL DEFAULT 0,
		target_type  VARCHAR(16) NOT NULL DEFAULT '',
		target_id    INT UNSIGNED NOT NULL DEFAULT 0,
		level        VARCHAR(16) NOT NULL DEFAULT 'view',
		created_by   INT UNSIGNED NOT NULL DEFAULT 0,
		created_at   INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uq_target (table_id, target_type, target_id),
		KEY idx_target (target_type, target_id, table_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Workspace: the tables for workflow tables are added: a person\'s own tables of typed columns and rows, and whom each is shared with. They stay empty until somebody makes a table.');

}

// comments.idx_author: a member's own comments, newest first, without a scan
// of the table. The comments widget's recent mode lists "mine" through
// created_user_id ordered by created_timestamp; without the index an account
// with no comments in the chosen pages read every row (about 0.3 s on a large
// site). The ALTER asks the pass first, like 11.0.31 on the same table.
function upgrade_2026_11_0_comment_author_index() {

	if (install_pass_spent() && !install_index_exists('comments', 'idx_author')) {

		install_pause(lang('comments: the index for a member\'s own comments follows in the next pass'));

	}

	install_add_index('comments', 'idx_author', "INDEX idx_author (created_user_id, created_timestamp)");

	install_note('Comments: a member\'s own comments are found faster, for example in a list of their latest replies.');

}

// The comment counts of form records shown on pages built in the visual
// designer (2026.11.0, 11.0.33; data only, no table changes;
// includes/fn/comments.php).
//
// submitted_form_info keeps, per form record and page, how many published
// comments the record has there and which one is the newest; the form lists
// sort and print by it. A page built in the visual designer that shows
// records through a form_item_view widget has page type 'standard', and until
// moderation rebuilt that row from the comment's own item
// (pg_comment_refresh_record_info()), the comments screen and the API keyed
// the rebuild on page type 'form item view'. Approving, holding, scheduling
// or deleting a comment on such a page left the count at 0 or too high and
// the newest comment stale. This step rebuilds every such pair once, in two
// walks:
//   - every (page, record) pair with record comments on a page that is not a
//     classic form item view page;
//   - every counter row of such a page that still counts comments the record
//     no longer has there (its last comment there was deleted), which the
//     first walk never meets.
// Each rebuild reads the comments again, so the step can run any number of
// times. Once the pass has used its time budget it pauses and goes on where
// it stopped: in the same request from memory, in a new one from a file in
// data/temp written before every pause. The file also keeps a done marker,
// so when a later step of this version pauses and the version runs again,
// the recount is not started over. A file older than ten minutes belongs to
// a run that was given up on and is not read.

function upgrade_2026_11_0_comment_record_counts() {

	if (!function_exists('pg_comment_refresh_record_info')) {

		install_skipped('Comments: the comment counts of form records were not counted again, because includes/fn/comments.php is out of date. Upload the files of this version again and run the upgrade.');

		return;

	}

	$progress = &upgrade_2026_11_0_comment_record_counts_progress();

	if (empty($progress['done'])) {

		if ($progress['phase'] === 'comments') {

			upgrade_2026_11_0_comment_record_counts_commented();

			$progress['phase'] = 'stale';
			$progress['page_id'] = 0;
			$progress['item_id'] = 0;

		}

		upgrade_2026_11_0_comment_record_counts_stale();

		$progress['done'] = true;

		if ($progress['failed'] > 0) {

			install_log('2026.11.0 comment record counts: ' . $progress['failed'] . ' (page, record) pair(s) could not be counted again');

		}

	}

	// Kept, not deleted: a later step of the version that pauses runs the
	// version again from its first step, and the marker skips this one.
	upgrade_2026_11_0_comment_record_counts_progress_save();

	if (($progress['rebuilt'] + $progress['failed']) === 0) {

		install_note('Comments: no form record shown on a page built in the visual designer has comments to count again.');

		return;

	}

	install_note('Comments: the comments of ' . $progress['rebuilt'] . ' form record(s) shown on pages built in the visual designer were counted again for the form lists'
		. (($progress['failed'] > 0) ? ' (' . $progress['failed'] . ' could not be, and each is counted again with its next comment or moderation)' : '') . '.');

}

// Walk 1: the pairs with record comments on a page that is not a classic
// form item view page, page by page and record by record, from where the
// last pass stopped. The records of one page are read in batches through
// idx_thread's (page_id, item_type, item_id) prefix, so a big comments table
// is not read whole again for every batch.
function upgrade_2026_11_0_comment_record_counts_commented() {

	$progress = &upgrade_2026_11_0_comment_record_counts_progress();

	$pages = db_values(
		"SELECT DISTINCT comments.page_id
		FROM comments
		INNER JOIN page ON page.page_id = comments.page_id
		WHERE
			(comments.item_type = 'submitted_form')
			AND (comments.item_id > 0)
			AND (page.page_type <> 'form item view')
			AND (comments.page_id >= '" . (int) $progress['page_id'] . "')
		ORDER BY comments.page_id");

	foreach ($pages as $page) {

		$page = (int) $page;

		$after = ($page === (int) $progress['page_id']) ? (int) $progress['item_id'] : 0;

		do {

			$items = db_values(
				"SELECT DISTINCT item_id
				FROM comments
				WHERE
					(page_id = '" . $page . "')
					AND (item_type = 'submitted_form')
					AND (item_id > '" . $after . "')
				ORDER BY item_id
				LIMIT 500");

			foreach ($items as $item) {

				$after = (int) $item;

				upgrade_2026_11_0_comment_record_counts_one($page, $after);

			}

		} while (count($items) === 500);

	}

}

// Walk 2: the counter rows of such pages that still count comments the record
// no longer has there. Its last comment there was deleted, so walk 1 never
// meets the pair. In batches, each after the last pair the walk reached.
function upgrade_2026_11_0_comment_record_counts_stale() {

	$progress = &upgrade_2026_11_0_comment_record_counts_progress();

	do {

		$page = (int) $progress['page_id'];
		$item = (int) $progress['item_id'];

		$rows = db_items(
			"SELECT DISTINCT
				submitted_form_info.page_id,
				submitted_form_info.submitted_form_id AS item_id
			FROM submitted_form_info
			INNER JOIN page ON page.page_id = submitted_form_info.page_id
			WHERE
				((submitted_form_info.number_of_comments > 0) OR (submitted_form_info.newest_comment_id > 0))
				AND (submitted_form_info.submitted_form_id > 0)
				AND (page.page_type <> 'form item view')
				AND NOT EXISTS (
					SELECT 1
					FROM comments
					WHERE
						(comments.page_id = submitted_form_info.page_id)
						AND (comments.item_type = 'submitted_form')
						AND (comments.item_id = submitted_form_info.submitted_form_id)
				)
				AND (
					(submitted_form_info.page_id > '" . $page . "')
					OR ((submitted_form_info.page_id = '" . $page . "') AND (submitted_form_info.submitted_form_id > '" . $item . "'))
				)
			ORDER BY submitted_form_info.page_id, submitted_form_info.submitted_form_id
			LIMIT 500");

		foreach ($rows as $row) {

			upgrade_2026_11_0_comment_record_counts_one((int) $row['page_id'], (int) $row['item_id']);

		}

	} while (count($rows) === 500);

}

// Rebuilds one pair and moves the place on to it. The time is asked after
// the work, so every pass rebuilds at least one pair. The rebuild's queries
// do not raise the runner's statement count, so the clock is asked rather
// than install_pass_spent().
function upgrade_2026_11_0_comment_record_counts_one($page_id, $item_id) {

	$progress = &upgrade_2026_11_0_comment_record_counts_progress();

	if (pg_comment_refresh_record_info($page_id, 'submitted_form', $item_id)) {

		$progress['rebuilt']++;

	} else {

		$progress['failed']++;

	}

	$progress['page_id'] = (int) $page_id;
	$progress['item_id'] = (int) $item_id;

	if (install_pass_seconds() >= install_pause_budget()) {

		upgrade_2026_11_0_comment_record_counts_progress_save();

		install_pause('Comments: counting the comments of form records on designed pages again, up to page ' . (int) $page_id . ', record ' . (int) $item_id . '.');

	}

}

// Where the recount got to: the walk (phase), the last pair it reached, the
// counts of the final note and whether it is done. In the same request it
// lives in memory; across requests in the file below, written before every
// pause and at the end. The file is read only while it is under ten minutes
// old: an older one belongs to a run that was given up on, and the counters
// may have been restored from a backup since. Sites that share one software
// directory (a custom CONFIG_FILE_PATH) share its data/temp, so the file is
// named after the database, as the upgrade lock is (install_lock_name()):
// one site's place or done marker is never read by another's upgrade.
function upgrade_2026_11_0_comment_record_counts_progress_file() {

	static $file = null;

	if ($file === null) {

		$database = defined('DB_DATABASE') ? DB_DATABASE . '@' . (defined('DB_HOST') ? DB_HOST : '') : (string) db_value("SELECT DATABASE()");

		$file = PG_FUNCTIONS_DIR . '/data/temp/upgrade_comment_record_counts_' . md5($database) . '.json';

	}

	return $file;

}

function &upgrade_2026_11_0_comment_record_counts_progress() {

	static $progress = null;

	if ($progress === null) {

		$progress = array('done' => false, 'phase' => 'comments', 'page_id' => 0, 'item_id' => 0, 'rebuilt' => 0, 'failed' => 0);

		$saved = @json_decode((string) @file_get_contents(upgrade_2026_11_0_comment_record_counts_progress_file()), true);

		if (is_array($saved) && isset($saved['time'], $saved['progress']) && is_array($saved['progress']) && ((time() - (int) $saved['time']) < 600)) {

			$stored = $saved['progress'];

			$progress['done'] = !empty($stored['done']);
			$progress['phase'] = (isset($stored['phase']) && ($stored['phase'] === 'stale')) ? 'stale' : 'comments';

			foreach (array('page_id', 'item_id', 'rebuilt', 'failed') as $key) {

				$progress[$key] = (isset($stored[$key]) && is_scalar($stored[$key])) ? max(0, (int) $stored[$key]) : 0;

			}

		}

	}

	return $progress;

}

function upgrade_2026_11_0_comment_record_counts_progress_save() {

	$file = upgrade_2026_11_0_comment_record_counts_progress_file();

	if (!is_dir(dirname($file))) {

		@mkdir(dirname($file), 0755, true);

	}

	@file_put_contents($file, json_encode(array('time' => time(), 'progress' => upgrade_2026_11_0_comment_record_counts_progress())), LOCK_EX);

}

// Share links of a design's prototype (2026.11.0, 11.0.35;
// includes/fn/prototype_links.php, prototype.php?link=...).
//
// prototype_links keeps one row per link: the design (style_id) and the draft
// it opens on (start_page_id, 0 for the design's first draft); token_hash, the
// SHA-256 of the token in the link's address, which a request is looked up by
// (unique); token_secret, the token encrypted with the site's key so that its
// maker can be shown the address again ("<ciphertext>:<iv>", empty on a site
// without a key); token_hint, its first characters for lists and log lines.
// audience is 'anyone' or 'team', VARCHAR rather than ENUM for the reason the
// price history step gives. password_hash is NULL for a link without a
// password; password_failures counts wrong answers in a row and
// password_locked_until is the end of a lock, on the row and not in the
// sign-in throttle, whose address bucket would lock sign-in. expires_at is 0
// for a team link that runs for good; created_*, revoked_* (0: not revoked),
// uses and last_used_at are unix times and counters like the other token
// links. style_id has a key: the editor lists a design's links.
function upgrade_2026_11_0_prototype_links() {

	install_create_table('prototype_links', "CREATE TABLE prototype_links (
		id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
		style_id               INT UNSIGNED NOT NULL DEFAULT 0,
		start_page_id          INT UNSIGNED NOT NULL DEFAULT 0,
		token_hash             CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
		token_secret           TEXT NULL,
		token_hint             VARCHAR(8) NOT NULL DEFAULT '',
		audience               VARCHAR(16) NOT NULL DEFAULT 'team',
		password_hash          VARCHAR(255) NULL DEFAULT NULL,
		password_failures      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		password_locked_until  INT UNSIGNED NOT NULL DEFAULT 0,
		expires_at             INT UNSIGNED NOT NULL DEFAULT 0,
		created_by             INT UNSIGNED NOT NULL DEFAULT 0,
		created_at             INT UNSIGNED NOT NULL DEFAULT 0,
		revoked_at             INT UNSIGNED NOT NULL DEFAULT 0,
		revoked_by             INT UNSIGNED NOT NULL DEFAULT 0,
		uses                   INT UNSIGNED NOT NULL DEFAULT 0,
		last_used_at           INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uk_token (token_hash),
		KEY idx_style (style_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Designs: the draft pages of a design can be shared as a clickable prototype by a link, for the team or for anyone who has it, with an expiry, an optional password and a way to revoke it, from the Visual Page Editor.');

}

// Two marketplace columns (2026.11.0, 11.0.37; includes/api/outbound/).
//
// marketplace_product_map.commission is the commission rate in percent the
// marketplace reports for an approved listing (Trendyol answers it with the
// approved product), NULL until one is read: 0.00 would say the marketplace
// takes nothing. It is shown beside the listing and never enters a price.
//
// marketplace_order_map.invoice_sent is when the marketplace accepted the
// invoice link of an imported package, a unix time, 0 while it has not.
//
// Code that reads either column probes it first (mp_commission_ready(),
// mp_invoice_sent_ready()): the files land before this step runs.
function upgrade_2026_11_0_marketplace_columns() {

	install_add_column('marketplace_product_map', 'commission', "DECIMAL(6,2) NULL DEFAULT NULL");
	install_add_column('marketplace_order_map', 'invoice_sent', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_note('Marketplaces: a listing can keep the commission rate the marketplace reports for it, and an imported order can record when its invoice link was sent to the marketplace.');

}

// Feed tokens in clear in the audit trail (2026.11.0, 11.0.38;
// pg_audit_remask() in includes/fn/audit.php).
//
// Until JSON text was decoded before masking (#905), a save of the SEO
// settings stored config.product_feeds in its audit_log line as the text it
// was, whole or cut to its start, with the feed tokens inside it in clear.
// Those lines are masked again in place by today's rule; they are kept, so
// the record of who changed the SEO settings stays. A line already masked
// comes back unchanged and is not written, so the step can run again.
function upgrade_2026_11_0_audit_feed_tokens() {

	if (!install_table_exists('audit_log')) {
		install_skipped('audit_log: no table, no feed token to mask');

		return;
	}

	$masked = 0;

	foreach (db_items("SELECT id, before_json, after_json FROM audit_log WHERE (entity_type = 'settings') AND (label = 'seo')") as $row) {
		$before = pg_audit_remask($row['before_json']);
		$after = pg_audit_remask($row['after_json']);

		if (($before === $row['before_json']) && ($after === $row['after_json'])) {
			continue;
		}

		db("UPDATE audit_log SET
			before_json = " . (($before === null) ? 'NULL' : "'" . e($before) . "'") . ",
			after_json = " . (($after === null) ? 'NULL' : "'" . e($after) . "'") . "
			WHERE id = '" . (int) $row['id'] . "'");

		$masked++;
	}

	if ($masked > 0) {
		install_ran('audit_log: the feed tokens of ' . $masked . ' SEO settings line(s) masked');
	} else {
		install_skipped('audit_log: no SEO settings line keeps a feed token in clear');
	}

	install_note('Audit trail: the product feed addresses that early SEO settings lines kept in clear are masked; the lines stay.');

}

// The İYS e-mail status of a contact (2026.11.0, 11.0.39; includes/iys/).
//
// iys_email_status is what İYS (İleti Yönetim Sistemi) last said about the
// contact's address on the e-mail channel: '' not known, 'approved' or
// 'refused', written by the daily İYS job when it pushes a consent or pulls a
// refusal. A commercial campaign skips a refused address while the İYS e-mail
// switch is on. A VARCHAR, not an ENUM, so a state added later needs no step.
// Added in place where the server can; contacts grows with every form and
// order, so elsewhere the table is copied once (install_heavy_tables()).
function upgrade_2026_11_0_contact_iys_status() {

	$columns = array(
		'iys_email_status' => "VARCHAR(16) NOT NULL DEFAULT ''",
	);

	if (!install_add_columns_instant('contacts', $columns)) {

		foreach ($columns as $column => $definition) {

			install_add_column('contacts', $column, $definition);

		}

	}

	install_note('Contacts: a column keeps what İYS last said about each address on the e-mail channel. It stays empty until an İYS account is set up.');

}

// A marketplace's own catalogue, kept for matching (2026.11.0, 11.0.40;
// includes/api/outbound/remote_products.php).
//
// One row per product the store sells on the marketplace, read from the
// marketplace a page at a time when the operator asks. remote_code is what a
// marketplace_product_map row for it would carry (n11: the seller's stock
// code), so a match is that row written. The suggestion columns hold which
// shop product the row most likely is, with its score, reason and whether it
// is sure; dismissed says the operator answered that the shop does not sell
// it. fetched_timestamp is the read that last saw the row: a read that ends
// removes the rows it did not see, the products the store took off there.
//
// remote_code compares byte for byte. The seller types these codes, and under
// the table's case- and accent-blind collation "ABC-1" and "abc-1", or
// "ŞAPKA-01" and "SAPKA-01", were one row: the second product's title,
// barcode and price overwrote the first's under the first's code, and a match
// accepted on that row sent a shop product's stock to the wrong listing.
//
// The unique key covers the first 191 characters of the code, for the same
// reason marketplace_product_map.idx_code does: the whole column is past the
// index limit of the older row formats.
function upgrade_2026_11_0_marketplace_remote_products() {

	install_create_table('marketplace_remote_products', "CREATE TABLE marketplace_remote_products (
		id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
		account_id           INT UNSIGNED NOT NULL DEFAULT 0,
		remote_code          VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '',
		remote_id            VARCHAR(64) NOT NULL DEFAULT '',
		group_code           VARCHAR(255) NOT NULL DEFAULT '',
		barcode              VARCHAR(64) NOT NULL DEFAULT '',
		title                VARCHAR(500) NOT NULL DEFAULT '',
		image_url            TEXT NULL,
		price                INT NOT NULL DEFAULT 0,
		quantity             INT NOT NULL DEFAULT 0,
		remote_status        VARCHAR(60) NOT NULL DEFAULT '',
		suggested_product_id INT UNSIGNED NOT NULL DEFAULT 0,
		suggestion_score     TINYINT UNSIGNED NOT NULL DEFAULT 0,
		suggestion_reason    VARCHAR(20) NOT NULL DEFAULT '',
		suggestion_sure      TINYINT NOT NULL DEFAULT 0,
		dismissed            TINYINT NOT NULL DEFAULT 0,
		fetched_timestamp    INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY idx_code (account_id, remote_code(191)),
		KEY idx_suggested (account_id, suggested_product_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Marketplaces: the products a store sells on a marketplace can be read in and matched to this shop\'s products in bulk, with suggestions, on the new Marketplace products screen.');

}

// Sign-in with a passkey alone (2026.11.0, 11.0.41; includes/fn/passkeys.php).
//
// config.passkey_signin is the Security card's switch for the "Sign in with a
// passkey" button on the sign-in screens (PASSKEY_SIGNIN). Off by default: an
// upgraded site keeps its sign-in screen as it is until a manager turns the
// button on.
// A session opened with a passkey names it (auth_tokens.passkey_id, 0 for any
// other sign-in), so the account page can show it beside the session and
// removing the passkey ends the sessions it opened. No foreign key: a token
// row outlives nothing it points at, and a removed passkey takes its rows
// along itself (pg_passkey_remove()).
function upgrade_2026_11_0_session_passkey() {

	install_add_column('auth_tokens', 'passkey_id', "INT UNSIGNED NOT NULL DEFAULT 0");
	install_add_index('auth_tokens', 'idx_passkey', "INDEX idx_passkey (passkey_id)");

	install_note('My Account › Security names the passkey a session was opened with; removing a passkey signs out the sessions it opened.');

}

function upgrade_2026_11_0_passkey_signin() {

	install_add_column('config', 'passkey_signin', "TINYINT NOT NULL DEFAULT 0");

	install_note('Settings › Security: a switch offers sign-in with a passkey alone on the sign-in screens. It starts off.');

}

// A campaign made from an order_delivered profile copies the profile's action
// into email_campaigns.action. 11.0.20 widened only the profiles' enum, so
// such a campaign was stored with action ''. The list is read first and only
// widened, so a value another step added stays; the default and the null rule
// are kept as they are.
function upgrade_2026_11_0_delivered_campaigns() {

	$action = install_column_info('email_campaigns', 'action');

	if (!$action) {

		return;

	}

	if (strpos((string) $action['Type'], "'order_delivered'") !== false) {

		install_skipped(lang(array('string' => '{var:1} already exists', 'vars' => 'email_campaigns.action order_delivered')));

	} else {

		preg_match_all("/'((?:[^']|'')*)'/", (string) $action['Type'], $matches);

		$values = $matches[1];
		$values[] = 'order_delivered';

		$definition = "ENUM('" . implode("','", $values) . "')"
			. ((($action['Null'] ?? '') === 'YES') ? ' NULL' : ' NOT NULL')
			. (($action['Default'] !== null) ? " DEFAULT '" . e((string) $action['Default']) . "'" : '');

		install_modify_column('email_campaigns', 'action', $definition);

	}

	install_note('E-mail campaigns: a campaign started by an "order delivered" profile now keeps its action, and the order shipped and order delivered e-mails can show ^^tracking_numbers^^, ^^carrier_name^^ and ^^tracking_url^^.');

}

// marketplace_product_map.remote_code compares byte for byte (2026.11.0,
// 11.0.44; includes/api/outbound/orders.php, product_matches.php).
//
// Under the table's case- and accent-blind collation "ABC-1" and "abc-1" were
// one code: an order line for one was matched to the product mapped under the
// other, and stock went to the wrong listing. utf8mb4_bin, as
// marketplace_remote_products.remote_code already is. The order import still
// falls back to the case-blind match when exactly one mapping answers, and the
// shop view's search names the case-blind collation itself.
//
// The type and default stay as 2026.4.4 made them; idx_code (191 characters)
// is rebuilt by the MODIFY. A column already in utf8mb4_bin is left alone.
function upgrade_2026_11_0_marketplace_map_code_bin() {

	if (install_table_exists('marketplace_product_map')) {

		$column = db_item("SHOW FULL COLUMNS FROM marketplace_product_map WHERE Field = 'remote_code'");

		if (is_array($column) && isset($column['Collation']) && ($column['Collation'] === 'utf8mb4_bin')) {

			install_skipped(lang(array('string' => '{var:1} already exists', 'vars' => 'marketplace_product_map.remote_code (utf8mb4_bin)')));

		} else {

			install_modify_column('marketplace_product_map', 'remote_code',
				"VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT ''", false);

		}

	}

	install_note('Marketplaces: product codes that differ only in upper and lower case are now told apart, so an order is matched to the right product.');

}

// How long the audit trail keeps its lines (2026.11.0, 11.0.47;
// includes/fn/privacy_retention.php, the Security screen's Audit Trail card).
// Years, 2 by default; 0 keeps every line. The general job removes older
// lines once a day, and the consent log's history past three years with
// them (the newest answer of each person, channel and purpose stays).
function upgrade_2026_11_0_audit_retention() {

	install_add_column('config', 'audit_log_retention_years', "TINYINT UNSIGNED NOT NULL DEFAULT 2");

	install_note('Privacy: the audit trail now keeps its lines for 2 years and the consent log its history for 3 years; the general job removes older lines once a day. The consent answer in force for each person is always kept. The audit trail period can be changed under Settings › Security › Audit Trail.');

}

// Guest parcel lookup (2026.11.0, 11.0.45; track_shipment.php,
// includes/fn/shipment_lookup.php). No schema change: the step only tells the
// operator the page exists and how to put it in front of customers, since no
// menu or page links to it on its own. The "Kargo Yola Çıktı" e-mail of the
// Turkish starter site is left as it is.
function upgrade_2026_11_0_shipment_lookup() {

	install_note('Shipping: customers without an account can now look up their parcels at track_shipment.php in the software folder (for example /pinegrap/track_shipment.php) with their order number and billing e-mail. It shows carrier, tracking number and status only, allows 10 lookups an hour per address, and appears where you link to it, for example from a menu item, the footer or the shipping e-mail.');

}

// The KVKK notice at checkout (2026.11.0, 11.0.48; includes/fn/legal_documents.php).
// The page holding the store's KVKK notice (0: no notice link) on the config
// row, and the explicit consent sentence (no row: no consent box) in
// config_text, a name/value table for long settings texts
// (includes/fn/config_text.php). The config row has no room left for another
// TEXT column on MySQL 8.0 (tests/innodb_test.php keeps room for ten), and
// the notice itself is a page of the site for the same reason. The answer is
// kept in order_documents as type kvkk, which 11.0.1 created.
function upgrade_2026_11_0_kvkk_notice() {

	install_add_column('config', 'ecommerce_kvkk_page_id', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_create_table('config_text', "CREATE TABLE config_text (
		name                VARCHAR(64) NOT NULL,
		value               TEXT NULL,
		PRIMARY KEY (name)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Checkout legal documents: a link to your KVKK notice page and an optional explicit consent box can be shown at checkout under Site Settings, Ecommerce, Legal Documents. Nothing changes until a page or a consent sentence is chosen there.');

}

// The rule behind the "price before the discount" (2026.11.0, 11.0.49;
// includes/fn/price_history.php, Settings › Ecommerce › Reference Price).
// TEXT, as every new config column (no VARCHAR on config); NULL reads as the
// 30-day lowest, so the step writes no rows.
function upgrade_2026_11_0_reference_price_rule() {

	install_add_column('config', 'ecommerce_reference_price_rule', "TEXT DEFAULT NULL");

	install_note('Ecommerce: Settings › Ecommerce › Reference Price now picks which price counts as the price before a discount: the lowest price of the last 30 days (the default), of the last 10 days, or the compare-at price entered on each product.');

}

// Large image previews in search results (2026.11.0, 11.0.51;
// includes/fn/seo.php, Site Settings › SEO, Site Map and Crawling).
//
// max_image_preview_large adds max-image-preview:large to the robots meta tag
// of every indexed page (pg_page_robots_content()), which lets Google show a
// page's large picture in Discover and in results; a noindex page never gets
// it. It starts off, so a running site writes exactly what it wrote before
// until the operator switches it on; a new site gets it on from the
// installer, after this step.
function upgrade_2026_11_0_max_image_preview() {

	install_add_column('config', 'max_image_preview_large', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");

	install_note('SEO: pages can now let search engines show their pictures large (max-image-preview:large in the robots meta tag), under Site Settings, SEO, Site Map and Crawling. It stays off on this site until switched on.');

}

// The PayPal Checkout payment driver on the Orders v2 API (2026.11.0, 11.0.50;
// includes/payments/paypal.php).
//
// orders.payment_method is an ENUM and the connection runs without strict
// mode, so a method outside the list is stored as '': an order paid with the
// driver would lose its method, and with it its refund through the driver.
// 'PayPal Checkout' is appended to the list the column already has, as 4.48
// appended 'Pay With Iyzico'; 'PayPal Express Checkout' stays the classic
// code. Nullability and default are copied from the column as it is. The
// driver keeps its keys in connect_apps, so no other column is needed.
function upgrade_2026_11_0_paypal_checkout() {

	$column = install_column_info('orders', 'payment_method');

	if (!is_array($column)) {

		install_skipped(lang('orders.payment_method does not exist, skipped'));

	} elseif (stripos((string) $column['Type'], "'PayPal Checkout'") !== false) {

		install_skipped(lang('orders.payment_method already knows about PayPal Checkout'));

	} elseif (preg_match('/^enum\((.*)\)$/is', trim((string) $column['Type']), $enum_match)) {

		$nullable = (strtoupper((string) $column['Null']) === 'YES');
		$default  = ($column['Default'] === null) ? ($nullable ? 'NULL' : "''") : "'" . e((string) $column['Default']) . "'";

		install_modify_column('orders', 'payment_method',
			"ENUM(" . $enum_match[1] . ",'PayPal Checkout') " . ($nullable ? 'NULL' : 'NOT NULL') . " DEFAULT " . $default);

	} else {

		install_skipped(lang('orders.payment_method is not an ENUM, skipped'));

	}

	install_note('PayPal can now take payments on its Checkout (Orders v2) API: the visitor approves the payment on PayPal\'s page and the store takes it on the way back. Turn on "PayPal Checkout" in the Ecommerce settings with the REST app\'s client ID and secret; "PayPal Express Checkout" keeps working as before.');

}

// Redirect to the main host (2026.11.0, 11.0.52; pg_main_host_redirect_url()
// in includes/fn/seo.php, get_page.php, Site Settings › SEO).
//
// main_host_redirect answers a page request that arrived on another host name
// (www.example.com for example.com, or the other way round) with a 301 to the
// same path on config.hostname, so search engines see one copy of each page.
// Only GET and HEAD page renders, never an IP address or localhost; the panel,
// the API, health.php, cron, webhooks and file downloads are left alone. It
// starts off on every site, new ones included: a site reached by an
// unexpected name would otherwise send its visitors to a name that may not
// resolve yet.
function upgrade_2026_11_0_main_host_redirect() {

	install_add_column('config', 'main_host_redirect', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");

	install_note('SEO: pages opened on another host name of this site (for example with or without www) can now be sent to the site address with a permanent redirect, under Site Settings, SEO, Site Map and Crawling. It stays off until switched on.');

}
// The author of a record page's BlogPosting (2026.11.0, 11.0.53;
// includes/fn/seo.php, the custom form's settings). One word per form:
// contact (the record's linked contact, as before), organization (the site
// with its address) or field:<form_field_id> (a field of the form holds the
// name). The default keeps every form's output as it was.
function upgrade_2026_11_0_blogposting_author() {

	install_add_column('custom_form_pages', 'schema_author', "VARCHAR(64) NOT NULL DEFAULT 'contact'");

	install_note('Custom forms: the author named in the structured data of a record page is now chosen per form in the form settings, next to Enable Pretty URLs: the contact of the record as before, the site as an organization, or a field of the form. Nothing changes until a form picks one.');

}
// The schema type of a custom form's record pages (2026.11.0, 11.0.55;
// includes/fn/seo.php, the custom form's settings): BlogPosting, Article,
// NewsArticle, or none for no record block. The default keeps every form's
// output as it was.
function upgrade_2026_11_0_record_schema_type() {

	install_add_column('custom_form_pages', 'schema_type', "VARCHAR(32) NOT NULL DEFAULT 'BlogPosting'");

	install_note('Custom forms: the structured data type of a record page is now chosen per form in the form settings: BlogPosting as before, Article, NewsArticle, or None to leave the page\'s own structured data as its only description. Nothing changes until a form picks one.');

}
// Pairing codes for team devices (2026.11.0, 11.0.70; includes/api/pairing.php).
//
// A person signed in to the panel shows a one-time code as a QR; the team app
// scans it and exchanges it at POST /auth/pair for a device session. A code
// lives five minutes and works once. Only its HMAC is kept (code_hash), so a
// copy of the table signs nobody in. Rows are bound to the person and to the
// device application; device_id is the session the exchange made.
function upgrade_2026_11_0_api_device_pairings() {

	install_create_table('api_device_pairings', "CREATE TABLE api_device_pairings (
		id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		app_id INT UNSIGNED NOT NULL DEFAULT 0,
		user_id INT UNSIGNED NOT NULL DEFAULT 0,
		code_hash CHAR(64) NOT NULL DEFAULT '',
		created_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		expires_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		used_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		device_id INT UNSIGNED NOT NULL DEFAULT 0,
		created_ip VARCHAR(45) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		UNIQUE KEY idx_code_hash (code_hash),
		KEY idx_user (user_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Team devices can now be paired by scanning a code the person shows in the panel, without typing a password on the phone.');

}

// Incoming e-mail into Workspace channels (2026.11.0, 11.0.82; the parser is
// includes/fn/mail_parse.php, docs/memory/workspace/mail-tables.md).
//
// ws_mail_boxes is a mailbox the site reads (address, transport, its settings
// with the password sealed by pg_connect_seal(), the channel its mail lands
// in, where the IMAP read stopped, the last check). ws_mail_conversations is
// one conversation with one sender: its card message in the channel (the
// thread is that message's replies), the customer it matched, status,
// assignee and the response times. ws_mail_items is every mail in and out of
// a conversation; rfc_message_hash (SHA-256 of the Message-ID, or of the raw
// mail when it has none) is unique per box, so a mail fetched twice is kept
// once. ws_messages and ws_message_forwards learn the sender kind 'mail'
// (sender_id the ws_mail_items row). Nothing is read until a box is added.
function upgrade_2026_11_0_workspace_mail() {

	install_create_table('ws_mail_boxes', "CREATE TABLE ws_mail_boxes (
		id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
		name            VARCHAR(100) NOT NULL DEFAULT '',
		address         VARCHAR(255) NOT NULL DEFAULT '',
		transport       ENUM('imap','graph','gmail','push') NOT NULL DEFAULT 'imap',
		settings        TEXT NULL,
		folder          VARCHAR(255) NOT NULL DEFAULT 'INBOX',
		after_fetch     ENUM('seen','move') NOT NULL DEFAULT 'seen',
		channel_id      INT UNSIGNED NOT NULL DEFAULT 0,
		reply_via       ENUM('box','site','connection') NOT NULL DEFAULT 'box',
		auto_reply      TINYINT(1) NOT NULL DEFAULT 0,
		auto_reply_text TEXT NULL,
		enabled         TINYINT(1) NOT NULL DEFAULT 1,
		uidvalidity     INT UNSIGNED NOT NULL DEFAULT 0,
		last_uid        INT UNSIGNED NOT NULL DEFAULT 0,
		checked_at      INT UNSIGNED NOT NULL DEFAULT 0,
		ok_at           INT UNSIGNED NOT NULL DEFAULT 0,
		last_error      VARCHAR(255) NOT NULL DEFAULT '',
		created_by      INT UNSIGNED NOT NULL DEFAULT 0,
		created_at      INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at      INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_channel (channel_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('ws_mail_conversations', "CREATE TABLE ws_mail_conversations (
		id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
		box_id          INT UNSIGNED NOT NULL DEFAULT 0,
		channel_id      INT UNSIGNED NOT NULL DEFAULT 0,
		message_id      INT UNSIGNED NOT NULL DEFAULT 0,
		subject         VARCHAR(255) NOT NULL DEFAULT '',
		customer_email  VARCHAR(255) NOT NULL DEFAULT '',
		customer_name   VARCHAR(255) NOT NULL DEFAULT '',
		contact_id      INT UNSIGNED NOT NULL DEFAULT 0,
		user_id         INT UNSIGNED NOT NULL DEFAULT 0,
		status          ENUM('open','waiting','closed') NOT NULL DEFAULT 'open',
		assignee_id     INT UNSIGNED NOT NULL DEFAULT 0,
		first_in_at     INT UNSIGNED NOT NULL DEFAULT 0,
		first_reply_at  INT UNSIGNED NOT NULL DEFAULT 0,
		last_in_at      INT UNSIGNED NOT NULL DEFAULT 0,
		last_out_at     INT UNSIGNED NOT NULL DEFAULT 0,
		closed_at       INT UNSIGNED NOT NULL DEFAULT 0,
		created_at      INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at      INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_box (box_id, status, last_in_at),
		KEY idx_channel (channel_id),
		KEY idx_message (message_id),
		KEY idx_contact (contact_id),
		KEY idx_assignee (assignee_id, status)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('ws_mail_items', "CREATE TABLE ws_mail_items (
		id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
		box_id           INT UNSIGNED NOT NULL DEFAULT 0,
		conversation_id  INT UNSIGNED NOT NULL DEFAULT 0,
		message_id       INT UNSIGNED NOT NULL DEFAULT 0,
		direction        ENUM('in','out') NOT NULL DEFAULT 'in',
		rfc_message_id   VARCHAR(255) NOT NULL DEFAULT '',
		rfc_message_hash CHAR(64) NOT NULL DEFAULT '',
		in_reply_to      VARCHAR(255) NOT NULL DEFAULT '',
		reference_ids    TEXT NULL,
		from_email       VARCHAR(255) NOT NULL DEFAULT '',
		from_name        VARCHAR(255) NOT NULL DEFAULT '',
		to_list          TEXT NULL,
		cc_list          TEXT NULL,
		subject          VARCHAR(255) NOT NULL DEFAULT '',
		body_text        MEDIUMTEXT NULL,
		auth             ENUM('pass','fail','none') NOT NULL DEFAULT 'none',
		raw_path         VARCHAR(255) NOT NULL DEFAULT '',
		refused_files    TEXT NULL,
		user_id          INT UNSIGNED NOT NULL DEFAULT 0,
		sent_at          INT UNSIGNED NOT NULL DEFAULT 0,
		created_at       INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uq_box_message (box_id, rfc_message_hash),
		KEY idx_conversation (conversation_id, id),
		KEY idx_sender (box_id, from_email(191), created_at),
		KEY idx_created (created_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// An enum is widened only when it does not carry the value yet, so a
	// second run does not rebuild the tables for nothing.
	$widen = function ($table, $column, $value, $definition) {

		$info = install_column_info($table, $column);

		if (is_array($info) && (strpos((string) $info['Type'], "'" . $value . "'") === false)) {
			install_modify_column($table, $column, $definition);
		} else {
			install_skipped(lang(array('string' => '{var:1} already exists', 'vars' => $table . '.' . $column . ' ' . $value)));
		}
	};

	$widen('ws_messages', 'sender_kind', 'mail', "ENUM('user','app','system','guest','mail') NOT NULL DEFAULT 'user'");
	$widen('ws_message_forwards', 'source_sender_kind', 'mail', "ENUM('user','app','system','guest','mail') NOT NULL DEFAULT 'user'");

	install_note('Workspace: the tables for incoming e-mail are added (mailboxes, conversations and the mails in them). Nothing is read until a mailbox is added in the Workspace settings.');

}

// Product bundles (2026.11.0, 11.0.54; includes/fn/bundles.php, the product
// screen's contents card).
//
// A bundle is a products row with bundle = 1 whose contents are other
// products in fixed whole quantities (product_bundle_items). Its
// inventory_quantity is a cache of what its components allow, and with
// bundle_pricing = 'sum' its price is the components' sum less
// bundle_discount_bp basis points. order_item_components and
// erp_invoice_item_components keep the contents a sold line had, so a later
// edit of the bundle never changes an old order or invoice. One bundle line
// writes one stock move per component, so the ledger's unique key widens to
// (kind, line_id, product_id): the new key goes in before the old one goes,
// and every existing row stays unique because a line has one product.
function upgrade_2026_11_0_product_bundles() {

	$columns = array(
		'bundle'             => "TINYINT(1) NOT NULL DEFAULT 0",
		'bundle_pricing'     => "ENUM('fixed','sum') NOT NULL DEFAULT 'fixed'",
		'bundle_discount_bp' => "SMALLINT UNSIGNED NOT NULL DEFAULT 0",
	);

	if (!install_add_columns_instant('products', $columns)) {

		foreach ($columns as $column => $definition) {

			install_add_column('products', $column, $definition);

		}

	}

	install_create_table('product_bundle_items', "CREATE TABLE product_bundle_items (
		id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
		bundle_id   INT UNSIGNED NOT NULL DEFAULT 0,
		product_id  INT UNSIGNED NOT NULL DEFAULT 0,
		quantity    INT UNSIGNED NOT NULL DEFAULT 1,
		sort_order  INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uniq_bundle_product (bundle_id, product_id),
		KEY idx_product (product_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('order_item_components', "CREATE TABLE order_item_components (
		id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
		order_id       INT UNSIGNED NOT NULL DEFAULT 0,
		order_item_id  INT UNSIGNED NOT NULL DEFAULT 0,
		product_id     INT UNSIGNED NOT NULL DEFAULT 0,
		quantity       INT UNSIGNED NOT NULL DEFAULT 1,
		unit_cost      BIGINT NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_order_item (order_item_id),
		KEY idx_order (order_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('erp_invoice_item_components', "CREATE TABLE erp_invoice_item_components (
		id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
		invoice_id  INT UNSIGNED NOT NULL DEFAULT 0,
		line_id     INT UNSIGNED NOT NULL DEFAULT 0,
		product_id  INT UNSIGNED NOT NULL DEFAULT 0,
		quantity    INT UNSIGNED NOT NULL DEFAULT 1,
		PRIMARY KEY (id),
		KEY idx_line (line_id),
		KEY idx_invoice (invoice_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	if (install_table_exists('erp_stock_moves')) {

		install_add_index('erp_stock_moves', 'uniq_line_product', "UNIQUE KEY uniq_line_product (kind, line_id, product_id)");

		if (install_index_exists('erp_stock_moves', 'uniq_line_product')) {

			install_drop_index('erp_stock_moves', 'uniq_line');

		}

	}

	install_note('Products: a product can now be a bundle of other products, sold as one item. Its stock follows its contents and its price can be the sum of their prices less a discount.');

}

// Transcripts of Workspace voice messages (2026.11.0, 11.0.89;
// includes/workspace/voice.php).
//
// ws_voice holds one row per recording, by the file it travels in (a copy of
// the message in a discussion shares the file and reads the same row):
// message_id is the message it was posted with, duration_ms and peaks (the
// waveform levels, one character each) are what the player draws, status is
// '' (never asked), queued, running, done, failed or skipped, transcript the
// machine text kept apart from the person's own words, with the language,
// provider and model that wrote it. ws_channels.voice_access follows
// ai_access: 0 goes with the kind of channel (public yes, private no), 1 a
// manager allowed it, 2 not allowed. config holds whether the site's own
// connection to an OpenAI-compatible /audio/transcriptions endpoint is on and
// the time a failed call holds the queue back to; its texts (base address,
// model, the encrypted key, the last error) are config_text rows, as the
// config row has no room for more long columns.
function upgrade_2026_11_0_workspace_voice() {

	install_create_table('ws_voice', "CREATE TABLE ws_voice (
		id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		file_id INT UNSIGNED NOT NULL DEFAULT 0,
		message_id INT UNSIGNED NOT NULL DEFAULT 0,
		channel_id INT UNSIGNED NOT NULL DEFAULT 0,
		duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
		peaks VARCHAR(255) NOT NULL DEFAULT '',
		status VARCHAR(16) NOT NULL DEFAULT '',
		transcript MEDIUMTEXT NULL,
		language VARCHAR(16) NOT NULL DEFAULT '',
		provider VARCHAR(100) NOT NULL DEFAULT '',
		model VARCHAR(100) NOT NULL DEFAULT '',
		attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
		error VARCHAR(255) NOT NULL DEFAULT '',
		created_at INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at INT UNSIGNED NOT NULL DEFAULT 0,
		transcribed_at INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY idx_file (file_id),
		KEY idx_message (message_id),
		KEY idx_status (status, id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_add_column('ws_channels', 'voice_access', "TINYINT UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('config', 'ws_voice_enabled', "TINYINT(1) NOT NULL DEFAULT 0");
	install_add_column('config', 'ws_voice_hold_until', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_note('Workspace: voice messages can be written out as text by a transcription service with the site\'s own key, once an administrator switches it on in the Workspace settings; private channels only where their manager allows it.');

}

// The preamble an MCP agent may be sent (2026.11.0, 11.0.111;
// includes/workspace/mcp_agent.php).
//
// A server that is an agent itself (ws_mcp_servers.agent_tool) gets the
// message alone. ws_mcp_servers.agent_preamble, when an administrator turns
// it on for the server, puts a short preamble before it: the site, the time,
// who asked, the channel or task by name, the language of the answer and the
// assistant rules, with the message inside a data block. 0 for every server,
// so nothing an agent is sent changes until somebody turns it on.
function upgrade_2026_11_0_workspace_mcp_preamble() {

	install_add_column('ws_mcp_servers', 'agent_preamble', "TINYINT(1) NOT NULL DEFAULT 0 AFTER agent_tool");

	install_note('Workspace: an MCP server that is an agent itself can be told about the site before each message: the site, who asked, the channel or task by name, the language to answer in and the rules Pinegrap AI follows. It is off for every server until an administrator turns it on.');

}

// Card security codes are never stored (2026.11.0, 11.0.56). The checkout
// kept the code in clear on an order placed without a payment gateway, and a
// masked one on a gateway order; both are emptied here. Data only, no schema
// change; WHERE <> '' makes a second run change nothing. Card numbers of
// processed orders are masked by the general job (pg_order_card_data_sweep()),
// which decrypts each one.
function upgrade_2026_11_0_card_security_codes() {

	db("UPDATE orders SET card_verification_number = '' WHERE card_verification_number <> ''");

	$cleared = max(0, (int) mysqli_affected_rows(db::$con));

	install_note('Orders: card security codes are no longer stored; ' . $cleared . ' stored code(s) were removed. Card numbers of exported and cancelled orders are masked to their last four digits by the general job.');

}
