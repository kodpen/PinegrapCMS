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
	upgrade_2026_11_0_short_link_redirects();   // 11.0.12
	upgrade_2026_11_0_page_revisions();         // 11.0.15
	upgrade_2026_11_0_page_schedule();          // 11.0.17
	upgrade_2026_11_0_ai_crawlers();            // 11.0.21
	upgrade_2026_11_0_privacy_logs();           // 11.0.16
	upgrade_2026_11_0_page_canonical_robots();  // 11.0.22

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
