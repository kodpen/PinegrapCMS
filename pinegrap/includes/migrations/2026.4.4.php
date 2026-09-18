<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.4.4. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

// 2026.4.4 - the release that follows 2026.4.3.
//
// Eight work numbers (4.4 - 4.11) accumulated on the development machine after
// 2026.4.3 shipped, and none of them reached a customer. They go out as one
// version: one entry point, one step per subsystem, in the order the work was
// done, because the steps are not independent - the recycle bin table has to
// exist before its item_type enum is widened for the catalog, and the dashboard
// row has to exist before its columns are renamed.
//
// Every step is defensive - CREATE TABLE IF NOT EXISTS, SHOW COLUMNS, SHOW
// INDEX, SHOW TABLE STATUS - so an installation that already ran some or all of
// the work numbers (a development database at 2026.4.11, say, with its version
// set back to 2026.4.3) simply finds everything in place and moves on. The
// version is only written once the whole function returns.
function upgrade_to_2026_4_4() {

	upgrade_2026_4_4_image_limits();            // 4.4

	upgrade_2026_4_4_structured_data();         // 4.5

	upgrade_2026_4_4_ai_bot_ranges();           // 4.6

	upgrade_2026_4_4_recycle_bin();             // 4.7

	upgrade_2026_4_4_upload_folders();          // 4.8

	upgrade_2026_4_4_catalog_bin_and_sign_in(); // 4.9

	upgrade_2026_4_4_dashboard_appearance();    // 4.10

	upgrade_2026_4_4_catalog_address_index();   // 4.11

	upgrade_2026_4_4_password_storage();        // 4.12

	upgrade_2026_4_4_auth_and_google();         // 4.13

	upgrade_2026_4_4_device_limit();            // 4.14

	upgrade_2026_4_4_banned_emails();           // 4.15

	upgrade_2026_4_4_device_lock();             // 4.16

	upgrade_2026_4_4_pinned_sessions();         // 4.17

	upgrade_2026_4_4_short_link_bin();          // 4.18

	upgrade_2026_4_4_offer_conditions();        // 4.19

	upgrade_2026_4_4_offer_group_discount();    // 4.20

	upgrade_2026_4_4_update_channel();          // 4.21

	upgrade_2026_4_4_multi_page_design();       // 4.22

	upgrade_2026_4_4_designer_collab();         // 4.23

	upgrade_2026_4_4_password_changed_at();     // 4.24

	upgrade_2026_4_4_external_api();            // 4.25

	upgrade_2026_4_4_product_tax_rate();        // 4.26

	upgrade_2026_4_4_notification_reads();      // 4.27

	upgrade_2026_4_4_api_upload_folder();       // 4.28

	upgrade_2026_4_4_web_push();                // 4.29

	upgrade_2026_4_4_push_queue();              // 4.30

	upgrade_2026_4_4_app_icon();                // 4.31

	upgrade_2026_4_4_marketplaces();            // 4.32

	upgrade_2026_4_4_marketplace_orders();      // 4.33

	upgrade_2026_4_4_push_signout();            // 4.34

	upgrade_2026_4_4_marketplace_listings();    // 4.35

	upgrade_2026_4_4_api_rate_limit();          // 4.36

	upgrade_2026_4_4_files_engine();            // 4.37

	upgrade_2026_4_4_signature_field();         // 4.38

	upgrade_2026_4_4_signature_stamp();         // 4.39

	upgrade_2026_4_4_signature_tsa();           // 4.40

	upgrade_2026_4_4_security_headers();       // 4.41

	upgrade_2026_4_4_parasut_credentials();    // 4.42

	upgrade_2026_4_4_erp_core();               // 4.43

	upgrade_2026_4_4_order_tax_base();         // 4.44

	upgrade_2026_4_4_erp_settlements();        // 4.45

	upgrade_2026_4_4_erp_return_series();      // 4.46

	upgrade_2026_4_4_erp_invoice_document();   // 4.47

	upgrade_2026_4_4_offline_payment_awaiting(); // 4.48

	upgrade_2026_4_4_erp_foreign_currency();   // 4.49

	upgrade_2026_4_4_erp_return_line_link();   // 4.50

	upgrade_2026_4_4_erp_account_snapshot();   // 4.51
}


// Outbound marketplace synchronisation (2026.4.4).
//
// Three tables and no new column on anything that already exists. That is the
// whole design decision, and it is what the accounting integration got wrong:
// Parasut put its credentials in `config` and its identifiers on the record
// itself, so a second provider means a second set of columns, and one shop
// cannot hold two accounts with the same provider.
//
// marketplace_accounts
//   One row per connection, not per provider. A seller with two n11 stores has
//   two rows. Credentials are an encrypted JSON blob rather than named columns,
//   because every provider asks for something different - n11 wants an app key
//   and secret, Amazon wants a refresh token and a role to assume - and a
//   provider added later must not need a schema change.
//
// marketplace_product_map
//   What this product is called over there. Kept out of `products` for the same
//   reason: one product goes to several marketplaces, and to several accounts
//   on the same marketplace. The remote code is a string because it is theirs,
//   not ours - n11 calls it stockCode, and it is only a number by coincidence.
//
// marketplace_sync_queue
//   Nothing is sent inside a web request. A price change writes a row here and
//   returns; api_sync_job.php batches the rows and talks to the network. This
//   is the correction to the mistake that is still visible in the accounting
//   integration, where saving an order waits on somebody else's server.
//
//   remote_task_id and its status exist because the large marketplaces are
//   asynchronous in the same shape: the push is accepted, an identifier comes
//   back, and the real answer - which SKU failed and why - arrives on a later
//   poll. A queue that only recorded "sent" would report success for a batch
//   that was rejected line by line.
function upgrade_2026_4_4_marketplaces() {

	install_create_table('marketplace_accounts', "CREATE TABLE marketplace_accounts (
		id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
		provider          VARCHAR(30) NOT NULL DEFAULT '',
		name              VARCHAR(100) NOT NULL DEFAULT '',
		credentials       TEXT,
		status            ENUM('active','paused','error') NOT NULL DEFAULT 'paused',
		push_stock        TINYINT NOT NULL DEFAULT 1,
		push_price        TINYINT NOT NULL DEFAULT 1,
		last_success      INT UNSIGNED NOT NULL DEFAULT 0,
		last_failure      INT UNSIGNED NOT NULL DEFAULT 0,
		last_error        VARCHAR(500) NOT NULL DEFAULT '',
		created_user_id   INT UNSIGNED NOT NULL DEFAULT 0,
		created_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		updated_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_provider (provider, status)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// One product may be mapped once per account, which is what the unique key
	// says. Without it a double click on the mapping screen sends every price
	// change twice for ever.
	install_create_table('marketplace_product_map', "CREATE TABLE marketplace_product_map (
		id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
		account_id        INT UNSIGNED NOT NULL DEFAULT 0,
		product_id        INT UNSIGNED NOT NULL DEFAULT 0,
		remote_code       VARCHAR(255) NOT NULL DEFAULT '',
		-- idx_code below indexes the first 191 characters of this column, not
		-- all 255: at utf8mb4 the whole column is 1020 bytes, past the 767-byte
		-- per-column index limit of the older InnoDB row formats (MySQL 5.6,
		-- MariaDB <= 10.1) and past MyISAM's 1000-byte whole-key limit. There
		-- the CREATE would fail with MySQL 1071, which the runner does not
		-- tolerate, so the version would abort and abort again on every retry.
		-- 191 x 4 = 764 bytes fits everywhere; a marketplace code long enough
		-- to collide in its first 191 characters does not exist.
		remote_id         VARCHAR(64) NOT NULL DEFAULT '',
		status            ENUM('active','paused','error') NOT NULL DEFAULT 'active',
		last_pushed       INT UNSIGNED NOT NULL DEFAULT 0,
		last_error        VARCHAR(500) NOT NULL DEFAULT '',
		created_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY idx_pair (account_id, product_id),
		KEY idx_product (product_id),
		KEY idx_code (account_id, remote_code(191))
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// The dispatcher asks one question - what is due now - so run_after leads
	// the index and status follows it, keeping finished rows out of the same
	// range scan rather than filtering them afterwards. Same shape as
	// api_webhook_queue.idx_due, for the same reason.
	install_create_table('marketplace_sync_queue', "CREATE TABLE marketplace_sync_queue (
		id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
		account_id        INT UNSIGNED NOT NULL DEFAULT 0,
		product_id        INT UNSIGNED NOT NULL DEFAULT 0,
		operation         VARCHAR(30) NOT NULL DEFAULT 'stock_price',
		priority          TINYINT UNSIGNED NOT NULL DEFAULT 5,
		status            ENUM('waiting','sent','done','failed') NOT NULL DEFAULT 'waiting',
		attempts          TINYINT UNSIGNED NOT NULL DEFAULT 0,
		run_after         INT UNSIGNED NOT NULL DEFAULT 0,
		remote_task_id    VARCHAR(64) NOT NULL DEFAULT '',
		last_error        VARCHAR(500) NOT NULL DEFAULT '',
		created_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		updated_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_due (run_after, status, priority),
		KEY idx_account (account_id, status),
		KEY idx_task (remote_task_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_add_column('config', 'marketplace_sync_last_run', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_note('Products can now be kept in step with a marketplace. Stock and price changes are queued and sent by a scheduled job, so saving a product never waits on somebody else\'s server.');
}


// Orders coming back from a marketplace (2026.4.4).
//
// The unit is the package, not the order. n11 - and the others work the same
// way - splits one customer order into shipment packages, and it is the package
// that has a status, a courier and a tracking number. Keying the map on the
// order number would make a second package of the same order look like a
// duplicate and it would never be imported.
//
// The map row is written BEFORE the order it describes. There are no
// transactions here (MyISAM), so a crash halfway through writing an order has
// to leave something behind that says "this package was being imported"; the
// unique key is what makes the retry find it instead of writing the order
// twice. An order that failed to finish is visible as a map row with no
// order_id.
//
// remote_status is kept beside our own because they are not the same alphabet
// and the translation is lossy: n11 has Picking, Unpacked and UnSupplied, this
// shop has four statuses. Keeping theirs verbatim means the screen can say what
// the marketplace actually thinks without inventing a status here.
function upgrade_2026_4_4_marketplace_orders() {

	install_create_table('marketplace_order_map', "CREATE TABLE marketplace_order_map (
		id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
		account_id         INT UNSIGNED NOT NULL DEFAULT 0,
		order_id           INT UNSIGNED NOT NULL DEFAULT 0,
		remote_order_id    VARCHAR(64) NOT NULL DEFAULT '',
		remote_package_id  VARCHAR(64) NOT NULL DEFAULT '',
		remote_status      VARCHAR(40) NOT NULL DEFAULT '',
		status             ENUM('importing','imported','failed') NOT NULL DEFAULT 'importing',
		approved           TINYINT NOT NULL DEFAULT 0,
		lines_json         TEXT,
		last_error         VARCHAR(500) NOT NULL DEFAULT '',
		remote_timestamp   INT UNSIGNED NOT NULL DEFAULT 0,
		imported_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		updated_timestamp  INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY idx_package (account_id, remote_package_id),
		KEY idx_order (order_id),
		KEY idx_status (account_id, status),
		KEY idx_remote_order (account_id, remote_order_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// Per account, because a shop may want stock going out to a marketplace
	// without its orders coming back - a seller who fulfils there through
	// somebody else's system still wants the quantities kept straight.
	install_add_column('marketplace_accounts', 'pull_orders', "TINYINT NOT NULL DEFAULT 1");

	// How far the order feed has been read. Not a page number: these feeds are
	// queried by a time window, and the window has to move forward only when a
	// pull actually succeeded, or a failed poll would skip everything it did
	// not manage to read.
	install_add_column('marketplace_accounts', 'last_order_pull', "INT UNSIGNED NOT NULL DEFAULT 0");

	// Whether an imported order is accepted on the marketplace automatically.
	// Accepting is a commitment to ship, so it is not something to do on the
	// operator's behalf without being asked.
	install_add_column('marketplace_accounts', 'auto_approve', "TINYINT NOT NULL DEFAULT 0");

	// A marketplace order is a sale to a customer, the same as a web order, and
	// the order list must not hide it. The list defaults to type = 'online'
	// (view_orders.php), so a new type of its own would be invisible until
	// somebody changed the filter - which is how an operator misses an order.
	// The filter therefore reads 'online' as "came from outside" and this type
	// sits inside it, with its own entry for looking at marketplace sales alone.
	install_note('Orders placed on a marketplace can now be brought into the shop. They appear in the order list beside web orders, and can be filtered on their own.');
}


// Listing a product on a marketplace (2026.4.4).
//
// Sending stock and price needs nothing but a code. Opening a listing needs the
// marketplace's own taxonomy: which of their categories the article belongs to,
// and then the attributes that category demands - and those are theirs, they
// change, and there are tens of thousands of them.
//
// So the tree is cached rather than fetched per use. It belongs to the provider
// and not to the account: two shops selling on the same marketplace see the
// same categories, and fetching it twice would be two very large downloads for
// one answer.
//
// Attributes are cached per category rather than all at once, because they are
// only ever needed for a category somebody actually picked. Fetching every
// category's attributes would be tens of thousands of calls against a limit
// counted per minute.
//
// The mapping is per ACCOUNT and per product group, not per product. A group is
// the article - the shirt - and its products are the sizes; the category and
// the mandatory attributes belong to the article, and only the variant
// attributes differ between the rows. Two accounts on the same marketplace may
// still file the same article differently, which is why the account is in the
// key.
function upgrade_2026_4_4_marketplace_listings() {

	install_create_table('marketplace_categories', "CREATE TABLE marketplace_categories (
		id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
		provider          VARCHAR(30) NOT NULL DEFAULT '',
		remote_id         VARCHAR(64) NOT NULL DEFAULT '',
		parent_id         VARCHAR(64) NOT NULL DEFAULT '',
		name              VARCHAR(255) NOT NULL DEFAULT '',
		path              VARCHAR(500) NOT NULL DEFAULT '',
		is_leaf           TINYINT NOT NULL DEFAULT 0,
		updated_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY idx_remote (provider, remote_id),
		KEY idx_leaf (provider, is_leaf),
		KEY idx_parent (provider, parent_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('marketplace_category_attributes', "CREATE TABLE marketplace_category_attributes (
		id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
		provider          VARCHAR(30) NOT NULL DEFAULT '',
		category_id       VARCHAR(64) NOT NULL DEFAULT '',
		attribute_id      VARCHAR(64) NOT NULL DEFAULT '',
		name              VARCHAR(255) NOT NULL DEFAULT '',
		mandatory         TINYINT NOT NULL DEFAULT 0,
		is_variant        TINYINT NOT NULL DEFAULT 0,
		custom_allowed    TINYINT NOT NULL DEFAULT 0,
		sort_order        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		values_json       MEDIUMTEXT,
		updated_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY idx_attribute (provider, category_id, attribute_id),
		KEY idx_category (provider, category_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('marketplace_category_map', "CREATE TABLE marketplace_category_map (
		id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
		account_id         INT UNSIGNED NOT NULL DEFAULT 0,
		group_id           INT UNSIGNED NOT NULL DEFAULT 0,
		remote_category_id VARCHAR(64) NOT NULL DEFAULT '',
		attributes_json    TEXT,
		last_error         VARCHAR(500) NOT NULL DEFAULT '',
		updated_timestamp  INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY idx_pair (account_id, group_id),
		KEY idx_group (group_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// Two things every listing on this marketplace needs and no product here
	// carries: which cargo agreement the parcel goes out under, and how long the
	// shop takes to hand it over. Both are properties of the shop's contract
	// rather than of any one article, so they sit on the account.
	install_add_column('marketplace_accounts', 'shipment_template', "VARCHAR(100) NOT NULL DEFAULT ''");

	install_add_column('marketplace_accounts', 'preparing_day', "TINYINT UNSIGNED NOT NULL DEFAULT 3");

	install_add_column('marketplace_accounts', 'categories_fetched', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_note('Products can now be listed on a marketplace from here: pick the marketplace\'s category for an article once, fill in what that category requires, and the variants are sent as one listing.');
}

// Two people in the Visual Page Editor (2026.4.4).
//
// The editor writes a whole page in one POST. Two operators on the same page
// therefore do not merge - the second save silently replaces the first, and
// the first person finds their work gone with nothing on screen having warned
// them. Three tables make that impossible and make the room visible instead.
//
// `designer_presence` is a heartbeat: one row per open editor tab, refreshed
// every twenty seconds, read to draw the other people's avatars. Rows are not
// deleted on a crash - staleness (a minute without a beat) is what retires
// them, because a browser that is killed never gets to say goodbye.
//
// `designer_page_lock` is the authority: one row per page, `page_id` as the
// primary key so the database itself refuses a second holder. Claiming is a
// single INSERT ... ON DUPLICATE KEY UPDATE that only hands the lock over when
// the current holder has gone stale; a SELECT-then-INSERT would let two tabs
// that ask at the same moment both believe they won (the same race
// waf_auto_ban() had before 2026.2.7).
//
// What the locked-out person CAN do is leave a NOTE — the editor's existing
// per-node note, no second mechanism for the same job. Notes live in the page
// tree, which a view-mode session cannot save, so the endpoint that writes one
// is the single bounded exception to the lock.
function upgrade_2026_4_4_designer_collab() {

	install_create_table('designer_presence', "CREATE TABLE designer_presence (
		id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
		session_key  VARCHAR(64) NOT NULL,
		style_id     INT UNSIGNED NOT NULL DEFAULT 0,
		page_id      INT UNSIGNED NOT NULL DEFAULT 0,
		user_id      INT UNSIGNED NOT NULL DEFAULT 0,
		started_at   INT UNSIGNED NOT NULL DEFAULT 0,
		last_seen    INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uniq_session (session_key),
		KEY idx_style (style_id, last_seen),
		KEY idx_page (page_id, last_seen)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('designer_page_lock', "CREATE TABLE designer_page_lock (
		page_id     INT UNSIGNED NOT NULL,
		session_key VARCHAR(64) NOT NULL,
		user_id     INT UNSIGNED NOT NULL DEFAULT 0,
		style_id    INT UNSIGNED NOT NULL DEFAULT 0,
		acquired_at INT UNSIGNED NOT NULL DEFAULT 0,
		last_seen   INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (page_id),
		KEY idx_session (session_key)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// When this page last had a note written to it.
	//
	// The heartbeat has to be able to answer "has anybody left a note since I
	// looked" without reading a single page tree: the trees are LONGTEXT, one
	// per open tab, and the honest answer is almost always "no". One integer on
	// the page turns that into a lookup by primary key.
	install_add_column('page', 'page_notes_at', "INT(10) UNSIGNED NOT NULL DEFAULT 0");

	install_note('Visual designer: presence, per-page edit lock and note stamp.');

	// Field-level pattern validation.
	//
	// `required` was the only rule a form field could carry, so "must look
	// like a phone number" had to be a note in the label and a hope. The
	// pattern is stored next to the field because it has to be enforced in
	// BOTH places: the browser reads it as the input's `pattern` attribute and
	// says so before the round trip, and custom_form.php checks it again on
	// submit — a client-side rule alone is a suggestion, not a rule.
	//
	// The message travels with it: a bare "invalid" tells the visitor nothing, and
	// the person who wrote the pattern is the only one who knows what shape
	// they wanted.
	install_add_column('form_fields', 'validation_regex',   "VARCHAR(255) NOT NULL DEFAULT ''");
	install_add_column('form_fields', 'validation_message', "VARCHAR(255) NOT NULL DEFAULT ''");
}

// Image limits (2026.4.4).
//
// Compression alone cannot fix an 8 MB camera photo: a picture that heavy is
// heavy because of its pixel count, and stripping metadata and recompressing
// it takes it to about 6 MB. Cutting the longest edge is the only thing that
// changes the order of magnitude, so the software now knows what a sensible
// edge is and can act on it.
//
// Two ceilings rather than one. A product photo answers to a catalogue and to
// Google Merchant Center, which announced a 500 px floor for 2027 — 2400 px is
// far above anything a product page displays and still leaves room to zoom.
// A file in the library can be a design asset, a full width banner or a print
// original, so its shrink is opt-in per file, only offered above 2560 px, and
// lands on 1920 px.
//
// image_resize_quality applies only when the software actually resamples. The
// plain optimize button keeps its old dimension-based quality ladder, so a
// file optimized before and after this upgrade comes out the same.
//
// Every default here is the current behaviour plus the new ceiling, so no
// backfill: existing files are untouched until somebody presses a button.
function upgrade_2026_4_4_image_limits() {

	$config_columns = array(
		'image_product_optimize'      => "ALTER TABLE config ADD image_product_optimize TINYINT(1) NOT NULL DEFAULT 1",
		'image_product_max_dimension' => "ALTER TABLE config ADD image_product_max_dimension SMALLINT UNSIGNED NOT NULL DEFAULT 2400",
		'image_product_min_dimension' => "ALTER TABLE config ADD image_product_min_dimension SMALLINT UNSIGNED NOT NULL DEFAULT 500",
		'image_file_resize_trigger'   => "ALTER TABLE config ADD image_file_resize_trigger SMALLINT UNSIGNED NOT NULL DEFAULT 2560",
		'image_file_max_dimension'    => "ALTER TABLE config ADD image_file_max_dimension SMALLINT UNSIGNED NOT NULL DEFAULT 1920",
		'image_resize_quality'        => "ALTER TABLE config ADD image_resize_quality TINYINT UNSIGNED NOT NULL DEFAULT 82"
	);

	foreach ($config_columns as $column => $sql) {
		if (!db_item("SHOW COLUMNS FROM config LIKE '" . $column . "'")) {
			db($sql);
		}
	}
}

// Open Graph and structured data settings (2026.4.4, work number 4.5).
//
// Everything here is a site-wide setting the render path reads through init.php
// constants; none of it changes behaviour until the operator fills it in.
//
//   og_default_image / organization_logo - a file name from the Files screen or
//     a full URL. The default image is the social share picture for pages that
//     have none of their own; the logo feeds the Organization block and the
//     BlogPosting publisher.
//   merchant_* - the site-wide shipping and return facts Google's merchant
//     listing wants on every Product (shippingDetails / hasMerchantReturnPolicy).
//     They are site-wide because the software's shipping engine prices per
//     order, not per product - a single representative rate is what the markup
//     format expects. -1 means "not configured, do not emit".
//   custom_jsonld (config and page) - operator-authored JSON-LD, validated as
//     JSON on save and re-encoded on output so it can never break out of its
//     <script> tag. The page column is LONGTEXT NULL because a default value
//     is not allowed on LONGTEXT and every existing row should read as empty.
function upgrade_2026_4_4_structured_data() {

	$config_columns = array(
		'og_default_image'          => "ALTER TABLE config ADD og_default_image VARCHAR(255) NOT NULL DEFAULT ''",
		'organization_logo'         => "ALTER TABLE config ADD organization_logo VARCHAR(255) NOT NULL DEFAULT ''",
		'merchant_country'          => "ALTER TABLE config ADD merchant_country VARCHAR(2) NOT NULL DEFAULT ''",
		'merchant_shipping_rate'    => "ALTER TABLE config ADD merchant_shipping_rate INT NOT NULL DEFAULT -1",
		'merchant_transit_days_min' => "ALTER TABLE config ADD merchant_transit_days_min TINYINT UNSIGNED NOT NULL DEFAULT 1",
		'merchant_transit_days_max' => "ALTER TABLE config ADD merchant_transit_days_max TINYINT UNSIGNED NOT NULL DEFAULT 3",
		'merchant_return_days'      => "ALTER TABLE config ADD merchant_return_days SMALLINT NOT NULL DEFAULT -1",
		'merchant_return_fees'      => "ALTER TABLE config ADD merchant_return_fees TINYINT UNSIGNED NOT NULL DEFAULT 0",
		'custom_jsonld'             => "ALTER TABLE config ADD custom_jsonld LONGTEXT NULL"
	);

	foreach ($config_columns as $column => $sql) {
		if (!db_item("SHOW COLUMNS FROM config LIKE '" . $column . "'")) {
			db($sql);
		}
	}

	if (!db_item("SHOW COLUMNS FROM page LIKE 'custom_jsonld'")) {
		db("ALTER TABLE page ADD custom_jsonld LONGTEXT NULL");
	}
}

function upgrade_2026_4_4_ai_bot_ranges() {

	// AI bots verified by their operators' published IP range lists.
	//
	// The firewall knows two kinds of AI bot. Training crawlers (GPTBot,
	// ClaudeBot, CCBot, PerplexityBot) fetch in bulk and stay in the bad-bot
	// list. User fetchers (ChatGPT-User, Claude-User, Perplexity-User) and AI
	// search indexers (OAI-SearchBot, Claude-SearchBot) fetch because a person
	// asked the assistant about the site; blocking them keeps the site out of
	// AI answers. Until now they matched no list at all and fell through to
	// "unknown bot".
	//
	// Trusting their user agent text is not an option - a scraper claiming
	// "ChatGPT-User" from an IBM address block is the incident that shaped
	// this. These operators offer no rDNS, but each publishes a JSON list of
	// every address its bots egress from. The table below stores those lists;
	// waf.php verifies the claim against them on the visitor path.
	db("CREATE TABLE IF NOT EXISTS waf_bot_ranges (
		provider   VARCHAR(40) NOT NULL,
		fetched_at INT UNSIGNED NOT NULL DEFAULT 0,
		prefixes   MEDIUMTEXT,
		PRIMARY KEY (provider)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// Both switches default to on: the traffic is person-triggered, tiny, and
	// IP-verified, and visibility in AI answers is the point of allowing it.
	// The operator can turn either off in the firewall settings.
	$config_columns = array(
		'waf_allow_ai_fetchers' => "ALTER TABLE config ADD waf_allow_ai_fetchers TINYINT(1) NOT NULL DEFAULT 1",
		'waf_allow_ai_search'   => "ALTER TABLE config ADD waf_allow_ai_search TINYINT(1) NOT NULL DEFAULT 1",
		'waf_ai_ranges_checked' => "ALTER TABLE config ADD waf_ai_ranges_checked INT UNSIGNED NOT NULL DEFAULT 0"
	);

	foreach ($config_columns as $column => $sql) {
		if (!db_item("SHOW COLUMNS FROM config LIKE '" . $column . "'")) {
			db($sql);
		}
	}

	// No outbound fetch here, deliberately. The upgrade must not wait on
	// three remote servers, and a request killed by a proxy timeout would
	// restart the whole version. The lists arrive on their own moments later:
	// the firewall dashboard widget and the settings screen both call the
	// throttled refresher, and the daily cron job keeps them current. Until
	// the first successful fetch the new entries verify as 'unknown', which
	// is exactly the pre-upgrade behaviour.
}

function upgrade_2026_4_4_recycle_bin() {

	// Recycle bin for the combined folder/page/file explorer.
	//
	// Deleting from that screen moves items into a private, archived
	// "Recycle Bin" folder instead of destroying them, so a slip of the
	// finger stops being permanent. Rows stay in their own tables on
	// purpose: clean_up.php removes disk files that have no files row, so a
	// binned file must keep its row to keep its bytes. This table only
	// remembers where each top-level item came from and when it was binned;
	// entries older than the retention window are purged permanently by the
	// explorer's daily sweep.
	db("CREATE TABLE IF NOT EXISTS recycle_bin (
		id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
		item_type          ENUM('folder','page','file') NOT NULL,
		item_id            INT UNSIGNED NOT NULL,
		original_parent_id INT UNSIGNED NOT NULL DEFAULT 0,
		deleted_at         INT UNSIGNED NOT NULL DEFAULT 0,
		deleted_by         INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_item (item_type, item_id),
		KEY idx_deleted_at (deleted_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// The bin folder itself is created lazily on first delete; config only
	// carries its id, the retention window and the last purge run.
	$config_columns = array(
		'recycle_folder_id'      => "ALTER TABLE config ADD recycle_folder_id INT UNSIGNED NOT NULL DEFAULT 0",
		'recycle_retention_days' => "ALTER TABLE config ADD recycle_retention_days INT UNSIGNED NOT NULL DEFAULT 30",
		'recycle_last_purge'     => "ALTER TABLE config ADD recycle_last_purge INT UNSIGNED NOT NULL DEFAULT 0"
	);

	foreach ($config_columns as $column => $sql) {
		if (!db_item("SHOW COLUMNS FROM config LIKE '" . $column . "'")) {
			db($sql);
		}
	}
}

function upgrade_2026_4_4_upload_folders() {

	// Where the screens that have no folder picker file what they receive.
	//
	// A chat attachment used to be written with folder zero, and a file row
	// with folder zero is in no folder at all: the file manager and the files
	// screen both list one folder at a time, so the file sat on disk and in
	// the table but was nowhere to be seen. The product screens do have a
	// picker, but it starts at the top folder and people upload without
	// touching it, so the top folder fills up with product photos.
	//
	// Both are settings now. Zero keeps meaning "not chosen", and the code
	// falls back to the top folder in that case, which is where people look
	// for these files anyway.
	$config_columns = array(
		'chat_upload_folder_id'    => "ALTER TABLE config ADD chat_upload_folder_id INT UNSIGNED NOT NULL DEFAULT 0",
		'product_upload_folder_id' => "ALTER TABLE config ADD product_upload_folder_id INT UNSIGNED NOT NULL DEFAULT 0"
	);

	foreach ($config_columns as $column => $sql) {
		if (!db_item("SHOW COLUMNS FROM config LIKE '" . $column . "'")) {
			db($sql);
		}
	}
}

function upgrade_2026_4_4_api_upload_folder() {

	// Where an application's uploads are filed.
	//
	// The external API can write files, and a key that could choose the folder
	// could write into the one the site's own design files live in. One folder,
	// set by the operator, is the whole answer. Zero keeps meaning "not chosen"
	// and the code falls back to the top folder, the same way the chat and
	// product upload settings do.
	install_add_column('config', 'api_upload_folder_id', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_note('The external API files its uploads in one folder chosen by the operator.');
}

function upgrade_2026_4_4_catalog_bin_and_sign_in() {

	// The recycle bin reaches the catalog.
	//
	// It could not before, for two reasons.  Its item_type is an enum that only
	// knew the three things the file manager deletes, and the way it hides them
	// is to move them into a hidden folder -- which a product cannot follow,
	// because a product has no parent: it belongs to several groups at once
	// through products_groups_xref.
	//
	// So the catalog is binned with a flag instead.  The flag on its own would
	// not be enough: every storefront query that reads these two tables would
	// have to learn to filter it, and one missed query means a product you
	// deleted is still on sale.  Binning therefore also switches the row off,
	// and 'enabled' is the gate the whole storefront already respects.  The
	// previous value is kept so that restoring puts the row back as it was,
	// rather than publishing something that was switched off before deletion.
	$catalog_columns = array(
		'product_groups' => array(
			'recycled'         => "ALTER TABLE product_groups ADD recycled TINYINT UNSIGNED NOT NULL DEFAULT 0, ADD INDEX recycled (recycled)",
			'recycled_enabled' => "ALTER TABLE product_groups ADD recycled_enabled TINYINT UNSIGNED NOT NULL DEFAULT 0"),
		'products' => array(
			'recycled'         => "ALTER TABLE products ADD recycled TINYINT UNSIGNED NOT NULL DEFAULT 0, ADD INDEX recycled (recycled)",
			'recycled_enabled' => "ALTER TABLE products ADD recycled_enabled TINYINT UNSIGNED NOT NULL DEFAULT 0"));

	foreach ($catalog_columns as $table => $columns) {

		foreach ($columns as $column => $sql) {

			if (!db_item("SHOW COLUMNS FROM " . $table . " LIKE '" . $column . "'")) {
				db($sql);
			}
		}
	}

	// The enum is widened rather than replaced, so rows already in the bin keep
	// meaning what they meant.
	$item_type = db_item("SHOW COLUMNS FROM recycle_bin LIKE 'item_type'");

	if (($item_type) && (strpos((string) $item_type['Type'], 'product_group') === false)) {
		db("ALTER TABLE recycle_bin MODIFY item_type ENUM('folder','page','file','product_group','product') NOT NULL");
	}

	// -- Sign-in throttle -------------------------------------------------
	//
	// Nothing counted failed sign-ins before this. The firewall's sensitive
	// limit caps requests to the sign-in screen at thirty a minute, which is
	// still forty thousand password guesses a day, and it does nothing at all
	// in a fresh install because the firewall ships in Monitor mode. A
	// guessing run against a known user name was unopposed.
	//
	// The switch is deliberately separate from the firewall's: an operator who
	// turns the firewall off to chase a false positive must not open the
	// password door in the same movement, which is why
	// check_banned_ip_addresses() sits outside the firewall too. Counting
	// still reuses the firewall's waf_rate buckets, so there remains one
	// implementation of the counter.
	$login_throttle_columns = array(
		'login_throttle'          => "ALTER TABLE config ADD login_throttle TINYINT(1) NOT NULL DEFAULT 1",
		'login_throttle_attempts' => "ALTER TABLE config ADD login_throttle_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 10",
		'login_throttle_minutes'  => "ALTER TABLE config ADD login_throttle_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15",
		'login_throttle_lockout'  => "ALTER TABLE config ADD login_throttle_lockout SMALLINT UNSIGNED NOT NULL DEFAULT 60");

	foreach ($login_throttle_columns as $column => $sql) {

		if (!db_item("SHOW COLUMNS FROM config LIKE '" . $column . "'")) {
			db($sql);
		}
	}

	// -- Guided tours ------------------------------------------------------
	//
	// Which tours a person has already watched, as a comma separated list of
	// keys rather than a column per tour.  validate_user() reads the whole user
	// row on every request already, so a tour costs no query at all, and adding
	// the next tour is then a new string in a screen rather than another
	// migration.  The number after the dot in a key ('explorer_files.1') is the
	// tour's own version: raising it in the screen makes everyone see that tour
	// once more, which is exactly what has to happen when the screen it
	// describes has changed underneath it.
	if (!db_item("SHOW COLUMNS FROM user LIKE 'tours_seen'")) {
		db("ALTER TABLE user ADD tours_seen TEXT");
	}

	// -- user: MyISAM to InnoDB -------------------------------------------
	//
	// The same reasoning as `visitors` in 2026.3.6, on a much smaller table
	// with a much worse failure mode.
	//
	// who_is_online() runs an UPDATE on this table for every signed-in visitor
	// whose stamp is over fifty seconds old, and a MyISAM UPDATE takes an
	// exclusive table lock. initialize_user() reads the same table on every
	// request a signed-in visitor makes, so on a membership site those reads
	// queue behind the presence writes.
	//
	// Recovery is the stronger reason. An unclean shutdown leaves a MyISAM
	// table needing REPAIR TABLE, and when the damaged table is `user` nobody
	// can sign in to run the repair, the operator included.
	//
	// Checked rather than assumed because ALTER TABLE ... ENGINE cannot be
	// resumed and is expensive to start over; a request killed part-way
	// through simply repeats the ALTER next time.
	//
	// The index on user_password stays. The sign-in query still filters on
	// that column, so it goes when the password hashing does, not before.
	$user_status = db_item("SHOW TABLE STATUS LIKE 'user'");

	if (is_array($user_status) && isset($user_status['Engine']) && strtolower($user_status['Engine']) !== 'innodb') {
		db("ALTER TABLE user ENGINE=InnoDB");
	}

	// -- Dashboard widget order -------------------------------------------
	//
	// dashboard.order_widgets holds one widget id per position, comma
	// separated. It now carries every id the dashboard knows, hidden cards
	// included: a card switched off by a setting has to keep its place while
	// the setting is off, or turning the setting back on drops it at the end of
	// the screen and the arrangement is gone.
	//
	// The table has held its settings in a single row since 2021 and nothing
	// has ever inserted one. Where the row is missing the dashboard reads as
	// unset, which is survivable -- welcome.php falls back to its factory
	// order -- but the save is an UPDATE, so an arrangement built on such an
	// installation matched no row and was thrown away without a word.
	if (!db_item("SELECT * FROM dashboard")) {
		db("INSERT INTO dashboard () VALUES ()");
	}

	// VARCHAR(256) was declared in 2021 for nineteen ids, about sixty
	// characters, and it still holds roughly eighty. Nothing truncates today.
	// It is widened anyway because of what truncation does here: MySQL cuts the
	// string at the limit and every id past the cut is simply gone, which on
	// screen is widgets that vanished and cannot be brought back except by
	// pressing Reset Widgets. That is the exact fault this release repairs, and
	// leaving the ceiling where it is would bring it back, silently, on the day
	// the dashboard grows past it.
	//
	// The default changes with it. Fresh rows used to arrive holding the 2021
	// list, '1,2,...,19', which names a retired widget and knows nothing of the
	// six added since -- an installation nobody had touched started with a
	// stale arrangement. 'default' is what Reset Widgets writes and what
	// welcome.php reads as "factory order", so a new row now starts the same
	// way a reset one does.
	$order_widgets = db_item("SHOW COLUMNS FROM dashboard LIKE 'order_widgets'");

	if (($order_widgets) && (stripos((string) $order_widgets['Type'], 'varchar(1024)') === false)) {
		db("ALTER TABLE dashboard MODIFY order_widgets VARCHAR(1024) NOT NULL DEFAULT 'default'");
	}

}

// The dashboard's appearance settings, made out of two dead columns (2026.4.4, work number 4.10).
//
// dashboard.widget_themes and dashboard.bg_image were added in 2021 for a widget
// theme picker and a dashboard wallpaper. Neither was ever read: outside this
// file no query in the software mentions either name, and the enum still offers
// blur_one, blur_two and blur_three, which name nothing that has ever existed.
// For three years both have held a value nobody chose and nothing honoured.
//
// They are renamed to what they now hold rather than dropped and replaced by two
// columns of the same shape. The data in them is not migrated, because an unread
// column has no meaning to preserve: every row lands on the default, and the
// default is the dashboard the operator is already looking at.
//
// VARCHAR rather than ENUM for both, though one of them was an enum. The two
// lists are the kind that grow, and adding a card style should be a CSS rule and
// a line in a PHP array -- not an ALTER on every installation in the field. The
// whitelists in welcome.php and api.php are the real gate either way, and an
// ENUM whose values no longer match its column is exactly the state these two
// were rescued from.
function upgrade_2026_4_4_dashboard_appearance() {

	// Each column moves in two guarded phases, and the second one keys on the
	// NEW name. A request cut off between them - which is the normal way an
	// upgrade dies on a host with a hard FastCGI timeout - would otherwise leave
	// a renamed but still nullable column that no later run would look at again,
	// because the first guard reads the old name and the old name is gone.

	// -- Card style ---------------------------------------------------------
	//
	// 'flat' is the block of per-widget background tints the dashboard has worn
	// all along, which is why it is the default rather than one of the three new
	// treatments: an installation that never opens the new menu sees no change.
	if (db_item("SHOW COLUMNS FROM dashboard LIKE 'widget_themes'")) {

		// Nullable and untyped for the length of the rename. Going straight to
		// NOT NULL fails on a row holding NULL, and going straight to a new ENUM
		// fails on a row holding 'classic'; both are errors in strict mode, and
		// both are what the existing rows actually contain.
		db("ALTER TABLE dashboard CHANGE widget_themes widget_theme VARCHAR(16) NULL DEFAULT NULL");
	}

	if (db_item("SHOW COLUMNS FROM dashboard LIKE 'widget_theme'")) {

		db("UPDATE dashboard
			SET widget_theme = 'flat'
			WHERE (widget_theme IS NULL)
			OR (widget_theme NOT IN ('flat', 'neon', 'glass', 'aurora'))");

		db("ALTER TABLE dashboard MODIFY widget_theme VARCHAR(16) NOT NULL DEFAULT 'flat'");
	}

	// -- Panel backdrop -----------------------------------------------------
	//
	// 'auto' means "whatever the card style needs", resolved in welcome.php, and
	// for the flat style that resolves to nothing - so a dashboard nobody has
	// restyled keeps the plain background it has now.
	//
	// The values it replaces, bg_metapolis and bg_purple_and_blue, were the
	// names of wallpaper images that were never served.
	if (db_item("SHOW COLUMNS FROM dashboard LIKE 'bg_image'")) {

		db("ALTER TABLE dashboard CHANGE bg_image panel_backdrop VARCHAR(16) NULL DEFAULT NULL");
	}

	if (db_item("SHOW COLUMNS FROM dashboard LIKE 'panel_backdrop'")) {

		db("UPDATE dashboard
			SET panel_backdrop = 'auto'
			WHERE (panel_backdrop IS NULL)
			OR (panel_backdrop NOT IN ('auto', 'none', 'mesh', 'dusk', 'ember'))");

		db("ALTER TABLE dashboard MODIFY panel_backdrop VARCHAR(16) NOT NULL DEFAULT 'auto'");
	}
}

function upgrade_2026_4_4_catalog_address_index() {

	// -- The address a catalog page is answered by --------------------------
	//
	// products.address_name and product_groups.address_name hold the path
	// segment a visitor arrives on, and neither was indexed. Every lookup by
	// address read the whole table.
	//
	// It showed up first in the product importer, which calls
	// prepare_catalog_item_address_name() once per product and pays two full
	// scans for it -- four thousand products scanned four thousand times is
	// sixteen million rows read to import three thousand. An import large
	// enough to be worth doing ran out of time.
	//
	// It was never only the importer, though. get_catalog_item_from_url()
	// resolves a product by the same column on every catalog detail page a
	// visitor opens, so every product page on every site was paying for the
	// missing index too.
	//
	// A prefix rather than the whole column: 255 utf8mb4 characters is 1020
	// bytes and does not fit a MyISAM key. 191 is under every limit any
	// version of MySQL has had, and an address name that long does not exist
	// -- the forms table carries the same index at 250, which fits MyISAM
	// exactly and nothing else.
	//
	// Not UNIQUE. The software writes a duplicate on purpose and then renames
	// it: prepare_catalog_item_address_name() looks for a clash and appends
	// "[1]", so a unique key would refuse the very row it is resolving.

	install_add_index('products', 'address_name', "INDEX address_name (address_name(191))");

	install_add_index('product_groups', 'address_name', "INDEX address_name (address_name(191))");
}


// Password storage, step one of two: schema only (2026.4.12).
//
// The sign-in system stored passwords as bare, unsalted MD5 and, worse, used
// that stored hash as the session credential itself - a cookie holding the hash
// logs the account in, so anyone who can read the user table signs in as anyone
// without cracking anything. The fix moves passwords to password_hash() and
// moves the session onto a random token (auth_tokens, next step). This first
// step only lays the columns down; nothing reads them yet, so the site behaves
// exactly as before after it runs.
//
// The wrapping of existing MD5 rows into password_hash(md5) is deliberately NOT
// done here. Wrapping a row while the verifier still checks WHERE user_password
// = md5(input) would lock that account out the instant it is wrapped, because
// the column would no longer equal the MD5 it is compared against. Wrapping
// therefore waits until the verifier is live (the code release that reads
// user_password_algo), and runs in batches from the general job then.
function upgrade_2026_4_4_password_storage() {

	// Widen BEFORE any hash is written. A 60 character bcrypt value written into
	// varchar(32) is silently truncated when the server is not in strict mode,
	// and a truncated hash can never be verified again - it would destroy every
	// password it touched. Only modify when the column is not already wide
	// enough, so a second run reports "already", not "changed".
	$info = install_column_info('user', 'user_password');

	if (is_array($info) && (stripos((string) $info['Type'], 'varchar(255)') === false)) {

		// ASCII on purpose, like user_google_id below. user_password carries an
		// index (KEY user_password, and step 4.9 keeps it), so its width is a key
		// width: utf8mb4 x 255 = 1020 bytes, past MyISAM's 1000 and past the 767
		// of the older InnoDB row formats - and MySQL 1071 is not a tolerated
		// error, so the ALTER would abort this version on those servers and every
		// retry would abort at the same statement. Every hash this column can
		// hold - 32 hex MD5, 60 character bcrypt, an argon2 string - is ASCII.
		install_modify_column('user', 'user_password',
			"VARCHAR(255) CHARACTER SET ascii DEFAULT NULL");
	}

	// Which of four shapes the column holds, because length cannot tell a
	// wrapped value from a modern one (both are 60 character bcrypt):
	//   0 = legacy   bare MD5, 32 hex        (only ever arrives by import)
	//   1 = wrapped  password_hash(md5)      (safe at rest, upgraded on sign-in)
	//   2 = modern   password_hash(password) (the destination)
	//   3 = external no password at all      (Google and the like)
	install_add_column('user', 'user_password_algo', "TINYINT UNSIGNED NOT NULL DEFAULT 0");

	// A row that never had a password is external, not a legacy MD5. Re-runnable:
	// it only touches rows still at the default 0 whose password is empty.
	db("UPDATE user SET user_password_algo = 3
	    WHERE user_password_algo = 0 AND (user_password IS NULL OR user_password = '')");

	// Chunked wrapping progress, the same shape the visitor and submitted-form
	// rollups use (config.*_cursor / *_done). Written here, read by the job once
	// the verifier ships.
	install_add_column('config', 'password_wrap_cursor', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('config', 'password_wrap_done', "TINYINT(1) NOT NULL DEFAULT 0");
}

// Session tokens and external sign-in, schema only (2026.4.13).
//
// auth_tokens is the "remember me" store done correctly: the cookie carries a
// random selector:validator, the table stores only sha256(validator), so
// reading the table yields nothing that can be pasted into a cookie to sign in,
// and each row can be revoked without touching the password. It also lets a
// passwordless (Google) account hold a session at all, which the old
// hash-in-cookie scheme could not represent. Nothing reads this table until the
// session refactor ships; creating it early keeps that release to code only.
function upgrade_2026_4_4_auth_and_google() {

	install_create_table('auth_tokens', "CREATE TABLE auth_tokens (
		selector       CHAR(24)     NOT NULL,
		validator_hash CHAR(64)     NOT NULL,
		user_id        INT UNSIGNED NOT NULL,
		expires_at     INT UNSIGNED NOT NULL,
		created_at     INT UNSIGNED NOT NULL,
		last_used_at   INT UNSIGNED NOT NULL DEFAULT 0,
		user_agent     VARCHAR(255) NOT NULL DEFAULT '',
		ip_address     VARCHAR(45)  NOT NULL DEFAULT '',
		PRIMARY KEY (selector),
		INDEX idx_user (user_id),
		INDEX idx_expires (expires_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// ASCII on purpose. Provider ids are always ASCII, and a utf8mb4
	// VARCHAR(255) unique index is 1020 bytes - past the 767 byte limit of the
	// older InnoDB row formats, where the ALTER would fail outright on a
	// customer's server. ASCII keeps the key at 255 bytes.
	install_add_column('user', 'user_google_id', "VARCHAR(255) CHARACTER SET ascii DEFAULT NULL");

	// UNIQUE, and MySQL allows any number of NULLs in a unique index, so the
	// thousands of users who never link Google do not collide.
	install_add_index('user', 'uniq_user_google_id',
		"UNIQUE KEY uniq_user_google_id (user_google_id)");

	// Google credentials live per install: every site has its own redirect URI,
	// so one shared client id cannot work and a shipped client secret would be a
	// leaked secret. The secret is stored encrypted the same way user.secret_key
	// is (ENCRYPTION_KEY), with its IV beside it.
	install_add_column('config', 'oauth_google_enabled', "TINYINT(1) NOT NULL DEFAULT 0");

	install_add_column('config', 'oauth_google_client_id', "VARCHAR(255) NOT NULL DEFAULT ''");

	install_add_column('config', 'oauth_google_client_secret', "VARCHAR(255) NOT NULL DEFAULT ''");

	install_add_column('config', 'oauth_google_secret_iv', "VARBINARY(32) DEFAULT ''");
}

function upgrade_2026_4_4_pinned_sessions() {

	// A session an operator has nailed down: it cannot be evicted by the device
	// limit, and the member cannot sign it out from their own account screen.
	// With a limit of one that ties an account to the device it is pinned on -
	// the reason it exists. A deliberate sign-out on that device still releases
	// it, because the device is only known by its cookie: once that is gone
	// nothing can recognise the machine again, and holding the slot would lock
	// the member out of every device instead of tying them to one.
	install_add_column('auth_tokens', 'pinned', "TINYINT(1) NOT NULL DEFAULT 0");
}

function upgrade_2026_4_4_device_lock() {

	// Strict device limit: the account's devices are locked, so a visitor who
	// reaches the limit is refused instead of being offered "sign out the
	// others". The offer is what lets two people share one account - they take
	// turns kicking each other - and this switch closes that door. Off by
	// default, meaningless while the device limit itself is off.
	install_add_column('config', 'remember_me_device_limit_strict', "TINYINT(1) NOT NULL DEFAULT 0");
}

function upgrade_2026_4_4_banned_emails() {

	// Email block list, alongside the firewall's IP lists. Removing a Google
	// connection does not keep the same person from connecting it again, and
	// deleting an account does not keep the same address from registering
	// again; this is the list that does. Manual entries only - one text column
	// rather than a table, because nothing writes to it automatically.
	install_add_column('config', 'banned_email_addresses', "TEXT");
}

function upgrade_2026_4_4_device_limit() {

	// Optional cap on how many "remember me" devices one account may keep
	// signed in at once. 0 means unlimited, so existing sites are unchanged
	// until an operator sets a value in Settings. Enforced at sign-in via
	// device_limit.php; the column feeds REMEMBER_ME_DEVICE_LIMIT.
	// An explicit on/off, separate from the number, so the feature is off by
	// default and an operator turns it on in the Security settings.
	install_add_column('config', 'remember_me_device_limit_enabled', "TINYINT(1) NOT NULL DEFAULT 0");

	install_add_column('config', 'remember_me_device_limit', "INT NOT NULL DEFAULT 0");
}


// Short links in the Recycle Bin (2026.4.18).
//
// Deleting a short link deleted it: the row was gone and the address it
// answered stopped answering, with nothing to undo and no record of what it
// used to point at. Everything else on that screen goes to the bin first.
//
// Short links belong to no folder, so they cannot be moved into the bin folder
// the way a page or a file is. They take the catalog's route instead -- a flag
// on the row, and a restore record in recycle_bin -- which is the same shape
// products and product groups have used since 4.9.
//
// Runs after the recycle bin table exists (4.7) and after the catalog widened
// its item_type (4.9), because it widens the same enum again.
function upgrade_2026_4_4_short_link_bin() {

	install_add_column('short_links', 'recycled', "TINYINT UNSIGNED NOT NULL DEFAULT 0");

	// Every listing of short links filters on it, so it carries an index for
	// the same reason product_groups.recycled does.
	install_add_index('short_links', 'recycled', "INDEX recycled (recycled)");

	// Widened rather than replaced, so rows already in the bin keep meaning
	// what they meant. Asked for first: MODIFY on an enum rewrites the table,
	// and this step has to be free to run twice.
	$short_link_item_type = install_column_info('recycle_bin', 'item_type');

	if (($short_link_item_type) && (strpos((string) $short_link_item_type['Type'], 'short_link') === false)) {

		install_modify_column('recycle_bin', 'item_type',
			"ENUM('folder','page','file','product_group','product','short_link') NOT NULL");
	}
}


// Conditions an offer sets on the customer, not on the cart (2026.4.4, work
// number 4.19).
//
// offer_rules answers "what is in the basket": a subtotal, a product, a
// quantity. "Only for new customers" is not about the basket at all, and the
// rules table is shared between offers besides, so a condition of this kind
// gets a row of its own that belongs to one offer.
//
// The table is deliberately plain - a type and two values - because the next
// conditions of this family (a customer group, a first order of the season)
// are the same shape, and a column per idea would mean a schema step per idea.
// The type is a string rather than an enum for the same reason.
//
// user.user_created is what "registered in the last N days" reads. Existing
// accounts have no such record anywhere, so they stay at 0, which reads as
// unknown and never satisfies the condition: an offer for new customers must
// not be handed to everyone who signed up before the software could tell.
function upgrade_2026_4_4_offer_conditions() {

	install_create_table('offer_conditions', "CREATE TABLE offer_conditions (
		id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
		offer_id   INT UNSIGNED NOT NULL DEFAULT 0,
		type       VARCHAR(50) NOT NULL DEFAULT '',
		int_value  INT NOT NULL DEFAULT 0,
		text_value VARCHAR(255) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		KEY offer_id (offer_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_add_column('user', 'user_created', "INT UNSIGNED NOT NULL DEFAULT 0");
}


// A product discount that targets a product group (2026.4.4, work number
// 4.20).
//
// "20% off every office chair" had to be entered as one action per chair, and
// a chair added to the catalogue next week was not in the campaign until
// somebody remembered to edit the offer.
//
// The action keeps its type. 'discount product' already means "take this much
// off an item in the cart"; whether the item is named directly or by the group
// it belongs to is a question about the target, not about the action. Adding a
// column instead of an enum value keeps every switch on the type - the order
// screens, the reports, the receipt - working untouched, and an installation
// that has not run this step reads 0 and behaves exactly as before.
function upgrade_2026_4_4_offer_group_discount() {

	install_add_column('offer_actions', 'discount_product_group_id', "INT UNSIGNED NOT NULL DEFAULT 0");

	// What the product discount is aimed at. Empty means the product named in
	// discount_product_product_id, which is what every row written before this
	// step meant, so the default needs no back-fill. 'group' reads the group
	// column; 'cheapest' names no product at all - it picks the least expensive
	// line in the cart, which is only known once there is a cart.
	install_add_column('offer_actions', 'discount_product_target', "VARCHAR(20) NOT NULL DEFAULT ''");
}


// The update channel (2026.4.4, work number 4.21).
//
// Until now every installation asked the update server the same question and
// got the same answer, so a release could only be tried by the people who
// build it. A site that opts in to `beta` asks for `latest_beta_version` and
// downloads the beta package instead; everything else about the update - the
// version comparison, the notification, the upgrade itself - is unchanged.
//
// An enum rather than a flag: a channel is a name, and a third one (an "lts"
// or a customer's own) is then a value, not another column. The default is
// `stable`, so an installation that never touches the setting keeps behaving
// exactly as it does today, and a database that has not run this step yet
// falls back to `stable` in code as well.
function upgrade_2026_4_4_update_channel() {

	install_add_column('config', 'software_update_channel', "ENUM('stable','beta') NOT NULL DEFAULT 'stable'");
}

// Multi-page visual design (2026.4.4).
//
// Until now a visual-designer style and its page were a pair held together
// by name: one `style` row carried the whole layout tree AND the generated
// HTML, while the page row carried the CSS / JS / font assets. That layout is
// the wrong way round for what the designer is becoming — several pages
// edited side by side in tabs, all sharing one set of stylesheets, scripts,
// fonts and theme, each with its own layout.
//
// So the two halves swap places:
//   • the tree and the HTML generated from it move to the PAGE
//     (`page_tree_json`, `page_tree_code`) — every page has its own layout;
//   • the assets move to the STYLE (`style_custom_css/js/fonts`) — every page
//     attached to the style via the existing `page_style` foreign key shares
//     them.
// Theme, collection, body classes and `style_head` already lived on the
// style and stay put.
//
// Backfill walks every visual-designer style, finds the page it was paired
// with (the same rule the editor used: page_style = style_id AND page_name =
// style_name), and copies each half across. Re-runnable: every UPDATE is
// gated on the destination still being empty, so a second run — or a run on
// a database where the editor has already written the new columns — changes
// nothing. The old columns are left in place; the render path reads the new
// ones first and falls back, so a site that takes the files before running
// this step keeps working.
function upgrade_2026_4_4_multi_page_design() {

	install_add_column('page', 'page_tree_json', "LONGTEXT NULL");
	install_add_column('page', 'page_tree_code', "LONGTEXT NULL");

	install_add_column('style', 'style_custom_css',   "LONGTEXT NULL");
	install_add_column('style', 'style_custom_js',    "LONGTEXT NULL");
	install_add_column('style', 'style_custom_fonts', "TEXT NULL");

	// Tree + generated code: style → its paired page. Only rows the editor
	// created (style_layout = 'visual_designer') carry a tree worth moving.
	db("UPDATE page p
	    INNER JOIN style s
	        ON s.style_id = p.page_style
	       AND s.style_name = p.page_name
	    SET p.page_tree_json = s.style_tree_json,
	        p.page_tree_code = s.style_code
	    WHERE s.style_layout = 'visual_designer'
	      AND s.style_tree_json IS NOT NULL
	      AND s.style_tree_json <> ''
	      AND (p.page_tree_json IS NULL OR p.page_tree_json = '')");

	// Assets: paired page → style. Same pairing rule; a style with several
	// attached pages takes the assets of the one whose name matches, which is
	// the only page the old editor ever wrote to.
	db("UPDATE style s
	    INNER JOIN page p
	        ON p.page_style = s.style_id
	       AND p.page_name = s.style_name
	    SET s.style_custom_css   = p.page_custom_css,
	        s.style_custom_js    = p.page_custom_js,
	        s.style_custom_fonts = p.page_custom_fonts
	    WHERE s.style_layout = 'visual_designer'
	      AND (s.style_custom_css IS NULL OR s.style_custom_css = '')
	      AND (s.style_custom_js IS NULL OR s.style_custom_js = '')
	      AND (s.style_custom_fonts IS NULL OR s.style_custom_fonts = '')");

	install_note('Visual designer: layout tree moved to the page, shared assets moved to the style.');
}

function upgrade_2026_4_4_password_changed_at() {

	// When this account's password was last written. There was no such record:
	// the user row carried the hash and nothing else, so the only trace of a
	// change was a free-text line in the log table, which is capped at 100 rows
	// per user and worded differently by each of the four paths that set a
	// password. Unix timestamp, 0 meaning "not known" - existing rows keep 0
	// rather than being backfilled from the log, because a wrong date on a
	// security screen is worse than an honest blank.
	install_add_column('user', 'user_password_changed_at', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_note('User accounts now record when the password was last changed.');
}

function upgrade_2026_4_4_external_api() {

	// The rebuilt external API.
	//
	// The old surface authenticated with two keys that belonged to two
	// different things: custom_apps.api_key identified an application, but
	// user.secret_key identified a PERSON. Any person's secret therefore
	// opened any application's key, and regenerating one person's secret broke
	// every integration that person had ever set up. Nothing could be rotated
	// and nothing revoked short of deleting the application.
	//
	// api_apps puts both halves on the application. api_key is the public
	// identifier and is stored in the clear so it can be listed, indexed and
	// searched; the secret is only ever kept as an HMAC and is shown to the
	// operator once, at the moment it is made. api_secret_prev_hash is what
	// makes rotation survivable: a new secret is issued while the old one
	// keeps working until api_secret_prev_expires, so a live marketplace sync
	// changes credentials without a window of failing calls.
	install_create_table('api_apps', "CREATE TABLE api_apps (
		id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
		name                    VARCHAR(190) NOT NULL DEFAULT '',
		description             VARCHAR(500) NOT NULL DEFAULT '',
		api_key                 VARCHAR(64) NOT NULL,
		api_secret_hash         VARCHAR(64) NOT NULL DEFAULT '',
		api_secret_hint         VARCHAR(8) NOT NULL DEFAULT '',
		api_secret_prev_hash    VARCHAR(64) NOT NULL DEFAULT '',
		api_secret_prev_expires INT UNSIGNED NOT NULL DEFAULT 0,
		owner_user_id           INT UNSIGNED NOT NULL DEFAULT 0,
		scopes                  TEXT,
		ip_allowlist            VARCHAR(500) NOT NULL DEFAULT '',
		require_signature       TINYINT NOT NULL DEFAULT 0,
		status                  ENUM('active','disabled','revoked') NOT NULL DEFAULT 'disabled',
		expires_at              INT UNSIGNED NOT NULL DEFAULT 0,
		rate_limit_per_min      SMALLINT UNSIGNED NOT NULL DEFAULT 120,
		last_used_timestamp     INT UNSIGNED NOT NULL DEFAULT 0,
		last_used_ip            VARCHAR(45) NOT NULL DEFAULT '',
		created_user_id         INT UNSIGNED NOT NULL DEFAULT 0,
		created_timestamp       INT UNSIGNED NOT NULL DEFAULT 0,
		updated_timestamp       INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uq_api_key (api_key),
		KEY idx_owner (owner_user_id),
		KEY idx_status (status)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// One row per request. The old surface wrote nothing at all unless a write
	// succeeded, so "which key is calling us, from where, how often, and what
	// is it getting back" had no answer - which is the first question asked
	// when an integration misbehaves. request_id is echoed to the caller in a
	// header so a support message can name one exact request.
	install_create_table('api_request_log', "CREATE TABLE api_request_log (
		id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
		app_id       INT UNSIGNED NOT NULL DEFAULT 0,
		request_id   CHAR(32) NOT NULL DEFAULT '',
		method       VARCHAR(10) NOT NULL DEFAULT '',
		path         VARCHAR(255) NOT NULL DEFAULT '',
		status_code  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		error_code   VARCHAR(50) NOT NULL DEFAULT '',
		ip           VARCHAR(45) NOT NULL DEFAULT '',
		duration_ms  INT UNSIGNED NOT NULL DEFAULT 0,
		timestamp    INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_app_time (app_id, timestamp),
		KEY idx_timestamp (timestamp),
		KEY idx_request_id (request_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// Rate limiting without a lock. The primary key is the application and the
	// minute it is in, so counting a request is one INSERT ... ON DUPLICATE KEY
	// UPDATE - two simultaneous requests cannot both read 5 and both write 6
	// the way a SELECT-then-UPDATE pair would. Old windows are swept with the
	// log.
	install_create_table('api_rate_bucket', "CREATE TABLE api_rate_bucket (
		app_id       INT UNSIGNED NOT NULL,
		window_start INT UNSIGNED NOT NULL,
		hits         INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (app_id, window_start)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// Replayed writes. A marketplace whose connection drops mid-call retries
	// the same stock adjustment, and an adjustment applied twice is wrong
	// stock. The caller sends an Idempotency-Key; the first request stores its
	// response here and the retry is answered from the store instead of being
	// applied again. request_hash catches the other mistake - the same key
	// reused for a different body - which is answered with a conflict rather
	// than the wrong cached response.
	install_create_table('api_idempotency', "CREATE TABLE api_idempotency (
		id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
		app_id            INT UNSIGNED NOT NULL DEFAULT 0,
		idem_key          VARCHAR(64) NOT NULL DEFAULT '',
		request_hash      CHAR(64) NOT NULL DEFAULT '',
		status_code       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		response          MEDIUMTEXT,
		created_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uq_app_key (app_id, idem_key),
		KEY idx_created (created_timestamp)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// Outgoing event notifications. The tables are created here, with the rest
	// of the API schema, so there is one migration for the subsystem; the queue
	// stays empty until the dispatcher is switched on.
	install_create_table('api_webhooks', "CREATE TABLE api_webhooks (
		id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
		app_id            INT UNSIGNED NOT NULL DEFAULT 0,
		url               VARCHAR(500) NOT NULL DEFAULT '',
		events            TEXT,
		secret            VARCHAR(64) NOT NULL DEFAULT '',
		status            ENUM('active','failing','disabled') NOT NULL DEFAULT 'active',
		last_success      INT UNSIGNED NOT NULL DEFAULT 0,
		last_failure      INT UNSIGNED NOT NULL DEFAULT 0,
		created_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_app (app_id),
		KEY idx_status (status)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// next_attempt_at leads the index because the dispatcher's only question is
	// "what is due now"; attempts follows it so exhausted rows drop out of that
	// same range scan instead of being filtered afterwards.
	install_create_table('api_webhook_queue', "CREATE TABLE api_webhook_queue (
		id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
		webhook_id        INT UNSIGNED NOT NULL DEFAULT 0,
		event             VARCHAR(50) NOT NULL DEFAULT '',
		payload           MEDIUMTEXT,
		attempts          TINYINT UNSIGNED NOT NULL DEFAULT 0,
		next_attempt_at   INT UNSIGNED NOT NULL DEFAULT 0,
		last_status_code  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		last_error        VARCHAR(500) NOT NULL DEFAULT '',
		created_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_due (next_attempt_at, attempts),
		KEY idx_webhook (webhook_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// Incremental sync asks "what changed since T" on every poll, every few
	// minutes, forever. Without these two indexes that question is a full table
	// scan of the catalogue and of every order ever placed.
	install_add_index('products', 'idx_timestamp', "INDEX idx_timestamp (timestamp)");

	install_add_index('orders', 'idx_order_date', "INDEX idx_order_date (order_date)");

	// API-wide settings. api_enabled is the master switch, kept separate from
	// the per-application status so an operator can close the whole surface in
	// one move. api_openapi_public decides whether the machine-readable
	// description is served to anyone or only inside an authenticated panel
	// session - it names every endpoint and parameter, which is a map worth
	// handing out deliberately rather than by default.
	install_add_column('config', 'api_enabled', "TINYINT NOT NULL DEFAULT 1");

	install_add_column('config', 'api_require_https', "TINYINT NOT NULL DEFAULT 1");

	install_add_column('config', 'api_openapi_public', "TINYINT NOT NULL DEFAULT 0");

	install_add_column('config', 'api_log_retention_days', "SMALLINT UNSIGNED NOT NULL DEFAULT 30");

	install_add_column('config', 'api_log_last_purge', "INT UNSIGNED NOT NULL DEFAULT 0");

	// The endpoint this replaces is removed rather than migrated. Nothing was
	// ever built against it - no site had a working integration on it - so
	// there are no credentials to preserve and no client to keep answering.
	// Its table goes with it; the columns it left on `user` (secret_key and
	// friends) are not dropped here, because dropping columns off the account
	// table for a feature removal is a wider blast radius than it is worth.
	//
	// The screens go the same way, on the other half of the release: apps.php
	// and apps_settings.php are in clean_up.php's removal list, and nothing in
	// the tree reads this table any more. Schema and files are retired
	// together, so the drop leaves nothing pointing at a table that is gone.
	install_drop_table('custom_apps');

	install_note('External API: applications now carry their own key and secret, with scopes, rate limiting and a request log. The previous app endpoint and its table are removed.');
}


// A tax rate on the product itself (2026.4.4).
//
// Tax has been a property of the destination here: tax_zones carries one rate
// per zone, the buyer's address picks the zone, and products only say whether
// they are taxable at all. That is how a US sales tax works, and it is why the
// column did not exist.
//
// A value-added tax is the other shape. The rate belongs to the article - in
// Turkey 20, 10 or 1 per cent depending on what is being sold - and a shop with
// mixed rates cannot express itself with one number per zone. The same is true
// of every marketplace listing: Trendyol, n11 and Hepsiburada all want the rate
// with the product, not with the customer.
//
// NULL is not zero. NULL means the product has no rate of its own and the
// zone's rate stands, which is what every existing row means and why the column
// must be nullable rather than defaulting to 0.000 - a default of zero would
// read as "zero-rated" and quietly stop charging tax on every product on every
// site that upgrades.
//
// The zone still decides WHETHER tax is charged. A buyer outside every zone
// pays none, exactly as before, whatever rate the product carries; that keeps
// exports zero-rated without a second mechanism.
function upgrade_2026_4_4_product_tax_rate() {

	// No UNSIGNED: it is deprecated on DECIMAL since MySQL 8.0.17 and removed
	// in MySQL 9, where the ALTER would fail outright and abort the version.
	// Nothing writes a negative rate, so the constraint bought nothing.
	install_add_column('products', 'tax_rate', "DECIMAL(6,3) NULL DEFAULT NULL");

	install_note('Products can now carry their own tax rate. Left empty, a product is taxed at the rate of the buyer\'s tax zone, which is how every product behaves today.');
}

// Per-user read state for panel notifications (2026.4.4).
//
// `notifications.readed` is a single flag on the row, so the bell was shared:
// the first operator to open the dropdown marked every visible notification
// read and every other operator's badge went out with it. On a site with more
// than one person the counter did not describe anybody - it described whoever
// looked last.
//
// Read state belongs to the pair (notification, person), which is what this
// table is. A row means that person has seen it; no row means unread, so
// marking something back to unread is a DELETE and needs no third state. The
// composite primary key makes the write idempotent - two tabs opening the
// dropdown in the same second cannot produce two rows.
//
// `readed` is left on the table. Files land before the schema does, so a panel
// running this version against the previous schema still has to answer, and
// that column is what it falls back to.
function upgrade_2026_4_4_notification_reads() {

	install_create_table('notification_reads', "CREATE TABLE notification_reads (
		notification_id INT UNSIGNED NOT NULL,
		user_id         INT UNSIGNED NOT NULL,
		timestamp       INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (notification_id, user_id),
		KEY idx_user (user_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// What one person had already read becomes read for everybody who exists
	// today. Starting everyone at zero instead would light the bell up with
	// months of history on the first login after the upgrade. Re-runnable: the
	// primary key drops the rows that are already there.
	//
	// The JOIN is deliberately narrowed to the accounts that can see a
	// notification at all. `user` is not the staff table - every member of
	// every membership site is a row in it - and an unrestricted join here is
	// (read notifications x every account), which on a shop with a long
	// history and a few thousand members is tens of millions of rows written
	// inside one request. It would not finish, and because the version number
	// is written only after the whole function returns, a statement that never
	// finishes is a site that can never be upgraded.
	//
	// Who is left out: a member with no panel rights, for whom the only
	// visible kind is the rare hand-written 'custom' notice. They see those as
	// unread once. That is the trade against a join nobody can bound.
	db("INSERT IGNORE INTO notification_reads (notification_id, user_id, timestamp)
		SELECT notifications.id, user.user_id, " . time() . "
		FROM notifications
		JOIN user
			ON ((user.user_role < 3)
				OR (user.user_manage_ecommerce = 'yes')
				OR (user.user_manage_forms = 'yes'))
		WHERE notifications.readed = 1");

	install_note('Notifications now remember who has read what. One operator opening the bell no longer clears everybody else\'s badge.');
}

// Web push subscriptions (2026.4.4).
//
// The panel learns about a new order by asking api.php every twenty seconds,
// and the chat by asking every five to sixty. Both stop the moment the tab is
// hidden, so a closed panel is a silent one. A push subscription is the same
// news arriving without anyone asking, and it works on a phone with the screen
// off, which is what makes the panel usable as an installed application.
//
// A subscription belongs to a browser, not to a person: the same operator on a
// laptop and a phone is two rows, and one browser signed into two accounts is
// two rows as well. The endpoint is the browser's address at its push service
// and is the natural key, but it runs past what an index can carry at utf8mb4,
// so the unique key is over its SHA-256 and the endpoint itself is stored
// beside it.
//
// VAPID is the pair of keys that identifies this site to the push services.
// They are generated once, on the first subscription, and kept in config
// because they are per-site and must survive an update - a new public key
// invalidates every subscription ever handed out.
function upgrade_2026_4_4_web_push() {

	install_create_table('push_subscriptions', "CREATE TABLE push_subscriptions (
		id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id             INT UNSIGNED NOT NULL DEFAULT 0,
		endpoint            VARCHAR(500) NOT NULL DEFAULT '',
		endpoint_hash       CHAR(64) NOT NULL,
		p256dh              VARCHAR(255) NOT NULL DEFAULT '',
		auth                VARCHAR(64) NOT NULL DEFAULT '',
		user_agent          VARCHAR(255) NOT NULL DEFAULT '',
		created_timestamp   INT UNSIGNED NOT NULL DEFAULT 0,
		last_seen_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		last_ok_timestamp   INT UNSIGNED NOT NULL DEFAULT 0,
		fail_count          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uq_endpoint (endpoint_hash),
		KEY idx_user (user_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_add_column('config', 'push_vapid_public', "VARCHAR(255) NOT NULL DEFAULT ''");
	install_add_column('config', 'push_vapid_private', "TEXT");

	install_note('The panel can now be installed as an application and deliver notifications to a device that is not looking at it.');
}

// The queue that carries a device notification (2026.4.4).
//
// Sending happens on a schedule rather than inside the request that caused it.
// A customer placing an order would otherwise wait for one HTTPS round trip per
// operator device before their order was confirmed, and a push service having a
// slow minute would become a slow checkout.
//
// A row is one person who should be woken about one thing. The unique key over
// (person, source, reference) is what makes the writers careless in the right
// way: a notification that fires twice, or a chat message saved twice by a
// retried request, cannot queue the same wake-up twice.
//
// `send_after` carries the delay. A panel notification is due immediately; a
// chat message waits, because somebody reading the message on the screen in
// front of them should not also get a banner on their phone - by the time the
// row comes due the read mark has usually moved past it and the row is dropped
// unsent.
function upgrade_2026_4_4_push_queue() {

	install_create_table('push_queue', "CREATE TABLE push_queue (
		id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id           INT UNSIGNED NOT NULL,
		source            VARCHAR(20) NOT NULL DEFAULT '',
		reference_id      INT UNSIGNED NOT NULL DEFAULT 0,
		send_after        INT UNSIGNED NOT NULL DEFAULT 0,
		attempts          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		created_timestamp INT UNSIGNED NOT NULL DEFAULT 0,
		last_error        VARCHAR(255) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		UNIQUE KEY uq_item (user_id, source, reference_id),
		KEY idx_send_after (send_after)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('New orders, form submissions, comments and unanswered chat messages can now reach a device that is not looking at the panel.');
}

// The icon the installed panel wears (2026.4.4).
//
// It was a file shipped with the software, the same on every installation, so
// three Pinegrap sites on one phone were three identical squares. The setting
// holds a file name from the Files screen, the same shape the share image and
// the organisation logo use, so one picker behaves like the others and a value
// that no longer matches anything is still readable.
//
// Nothing is stored resized. The launcher wants exact sizes and the source is
// whatever the operator had to hand, so manifest_icon.php renders each size on
// demand and keeps the result in data/temp.
function upgrade_2026_4_4_app_icon() {

	install_add_column('config', 'app_icon', "VARCHAR(255) NOT NULL DEFAULT ''");

	install_note('The panel installed as an application can now wear the site\'s own icon, chosen in Settings.');
}

// Signing out takes the device notifications with it (2026.4.4).
//
// A push subscription belongs to a browser, not to an account, so signing out
// did not end it: the browser kept being woken about a site nobody on it was
// signed in to. No detail leaked - the worker has no session to read the text
// with, so all it could draw was the generic wording - but "something happened"
// is still more than a signed-out browser should be told, and on a shared
// computer it is somebody else's news.
//
// The remember-me selector identifies the browser. It is already revoked in one
// place for every way a session ends deliberately - signing out, an operator
// ending a session from the list, the device limit retiring the oldest one - so
// recording it here lets that same place take the subscription away.
// The endpoint the site's own pages talk to gets its own allowance (2026.4.4).
//
// api.php was counted against waf_rate_limit_sensitive, the number sized for a
// sign-in screen or a checkout form - things a visitor submits a handful of
// times an hour. That is the wrong shape for an endpoint a page talks to while
// the visitor sits still: an open chat window asks it something every two
// seconds, which is the whole of that allowance by itself, and a product page
// or the express order screen adds its own calls on top. The visitor was
// rate limited and then banned for using the site normally.
function upgrade_2026_4_4_api_rate_limit() {

	install_add_column('config', 'waf_rate_limit_api', "SMALLINT UNSIGNED NOT NULL DEFAULT 180");

	install_note('The firewall now counts the site\'s own background requests on their own allowance. Live chat and the store pages ask the server for things continuously; they were being counted against the sign-in and checkout limit, which could rate limit a visitor for using the site normally.');
}

// The one table every page's images depend on, moved to the engine that can
// serve them under load.
//
// get_file.php resolves every image, script and download with
// SELECT ... FROM files WHERE name = ?, once per request, and a screen of
// thumbnails is hundreds of those at once. On MyISAM that lookup was a full
// table scan -- name carried no index -- so every picture read the whole
// table, and every upload or optimise takes an exclusive table lock, so those
// scans queued behind one another and behind each write. A connection held for
// the length of a scan is a connection not returned to the pool, which is the
// shortage this release chased down on the file manager.
//
// InnoDB answers both halves: row-level locking lets the reads run alongside
// the writes, and an indexed equality lookup returns without touching the rest
// of the table. It also takes the MyISAM recovery trap -- a table needing
// REPAIR after an unclean shutdown -- off the table the whole site's images
// pass through. The same move 2026.4.4 already makes for `user`, here on the
// hottest read path there is.
//
// The engine change is done first so the index is built under InnoDB's wider
// key limit, and both are checked rather than assumed: an interrupted ALTER
// simply repeats on the next run.
function upgrade_2026_4_4_files_engine() {

	// install_set_engine() checks the current engine itself and skips a table
	// that is missing or already InnoDB, so this is safe to run twice.
	install_set_engine('files', 'InnoDB');

	// Keep the whole column in the index when it fits the oldest key limit that
	// might still apply (name is VARCHAR(100) = 400 bytes under utf8mb4, well
	// inside 767); fall back to a prefix only if an installation has widened the
	// column past what a full key can hold. An equality lookup on a name of a
	// hundred characters is served entirely by either shape.
	$name_column = install_column_info('files', 'name');
	$name_length = 0;

	if (is_array($name_column) && isset($name_column['Type']) && preg_match('/\((\d+)\)/', (string) $name_column['Type'], $matches)) {
		$name_length = (int) $matches[1];
	}

	$name_index = (($name_length > 0) && (($name_length * 4) <= 767))
		? 'INDEX name (name)'
		: 'INDEX name (name(191))';

	install_add_index('files', 'name', $name_index);

	install_note('The files table moved to InnoDB and its name column is now indexed. Every image, script and download is looked up by name once per request, and a folder of pictures is hundreds of those at once; on the old engine each was a full table scan that also queued behind uploads, which is what exhausted the database connections on a busy site.');
}

function upgrade_2026_4_4_push_signout() {

	install_add_column('push_subscriptions', 'auth_selector', "VARCHAR(64) NOT NULL DEFAULT ''");
	install_add_index('push_subscriptions', 'idx_auth_selector', "KEY idx_auth_selector (auth_selector)");

	// The sessions screen lists the devices that are subscribed, and a device
	// that has stopped accepting deliveries has to be able to say so. A counter
	// alone tells an operator that something is wrong without telling them what.
	install_add_column('push_subscriptions', 'last_error', "VARCHAR(255) NOT NULL DEFAULT ''");

	install_note('Signing out of a device now stops that device receiving notifications.');
}


// A signature field, and the record that makes one worth keeping (2026.4.4).
//
// form_fields.type gets a value of its own. The palette in the visual designer
// never needs one - a type it does not recognise falls back to a text box, and
// that is deliberate - but the field screens read this column to decide what to
// render, what to validate and how to store the answer, and there is nothing
// text-box-like for a signature to fall back to: it is drawn, sealed, and then
// locked against further editing.
//
// The definition is widened from whatever the column already says rather than
// written out in full. This enum has collected values over the years and a
// literal list would silently drop anything added after this line was written.
//
// form_signatures holds what turns a drawing into evidence: the hash of the
// document as it was shown at that moment, the strokes (a pasted image has no
// timing), server time, who and from where, and a seal over all of it. Without
// the seal a signature is only as trustworthy as write access to the database.
//
// UNIQUE (form_id, form_field_id) is the lock itself. A second signature for the
// same field of the same submission cannot be written, so "already signed" is a
// property of the schema and not a check someone has to remember to write.
function upgrade_2026_4_4_signature_field() {

	$column = install_column_info('form_fields', 'type');

	if (is_array($column) && isset($column['Type']) && (stripos($column['Type'], "'signature'") === false)) {

		$definition = preg_replace("/\)\s*$/", ",'signature')", $column['Type'], 1, $replaced);

		if ($replaced === 1) {

			$collation = (isset($column['Collation']) && ($column['Collation'] !== null) && ($column['Collation'] !== '')) ? (' COLLATE ' . $column['Collation']) : '';
			$nullable = (isset($column['Null']) && ($column['Null'] === 'YES')) ? ' NULL' : ' NOT NULL';
			$default = (isset($column['Default']) && ($column['Default'] !== null)) ? (" DEFAULT '" . e($column['Default']) . "'") : '';

			install_modify_column('form_fields', 'type', $definition . $collation . $nullable . $default);

		} else {

			install_note('form_fields.type was left alone: the column does not read as an enum.');

		}

	}

	install_create_table('form_signatures', "CREATE TABLE form_signatures (
		id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
		form_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
		form_field_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
		file_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
		page_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
		document_hash CHAR(64) NOT NULL DEFAULT '',
		consent_text TEXT NOT NULL,
		strokes LONGTEXT NOT NULL,
		signed_at INT(10) UNSIGNED NOT NULL DEFAULT 0,
		ip_address VARCHAR(45) NOT NULL DEFAULT '',
		user_agent VARCHAR(255) NOT NULL DEFAULT '',
		user_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
		seal CHAR(64) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		UNIQUE KEY uniq_signature (form_id, form_field_id),
		KEY form_field_id (form_field_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

}


// A signature carries the bytes it was drawn as, and a time somebody else
// vouches for (2026.4.4).
//
// image_hash closes a gap the first version left: document_hash covers the
// fields and the answers, and the seal covers the file's id - so replacing the
// file on disk changed the signature without changing anything that was
// checked. The digest of the bytes is now part of the record and of the seal.
//
// The stamp columns hold an RFC 3161 token. signed_at is this server's clock
// and therefore this site's own word; a token is a third party's word about the
// same moment, over a digest anyone holding the receipt can recompute. The
// token is stored base64 so it survives a dump and restore as text, like every
// other column here.
function upgrade_2026_4_4_signature_stamp() {

	if (!install_table_exists('form_signatures')) {

		return;

	}

	install_add_column('form_signatures', 'image_hash', "CHAR(64) NOT NULL DEFAULT '' AFTER file_id");
	install_add_column('form_signatures', 'stamp_digest', "CHAR(64) NOT NULL DEFAULT ''");
	install_add_column('form_signatures', 'stamp_time', "INT(10) UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('form_signatures', 'stamp_authority', "VARCHAR(255) NOT NULL DEFAULT ''");
	install_add_column('form_signatures', 'stamp_token', "LONGTEXT NOT NULL");

}


// The time stamp authority moves from data/config.php into the config table
// (2026.4.4).
//
// It arrived as a define because it was written before the setting had a
// screen. That was the wrong home: data/config.php is for what an operator
// edits on the server with an editor - paths, keys, last-resort switches -
// while this is an ordinary setting somebody picks from the panel, next to
// every other setting they pick. The password sits beside the payment gateway
// passwords that have always lived in this table, in the same shape.
function upgrade_2026_4_4_signature_tsa() {

	install_add_column('config', 'signature_tsa_url', "VARCHAR(255) NOT NULL DEFAULT ''");
	install_add_column('config', 'signature_tsa_auth', "VARCHAR(20) NOT NULL DEFAULT 'none'");
	install_add_column('config', 'signature_tsa_username', "VARCHAR(100) NOT NULL DEFAULT ''");
	install_add_column('config', 'signature_tsa_password', "VARCHAR(100) NOT NULL DEFAULT ''");

}


// Security response headers, the plain-text block log, two firewall ceilings
// and the sign-in question (4.41). Nine columns on config, no new table.
//
// security_csp_mode is a VARCHAR rather than an ENUM so a fourth mode can be
// added by code alone; the reader treats anything it does not know as
// 'report'. security_csp_policy empty means the built-in policy, which is
// deliberately not copied into the row: the built-in one can improve with a
// release, a copied one would be frozen at whatever it was on upgrade day.
//
// Defaults are chosen so that an upgraded site changes as little as
// possible while gaining the safe part: headers on, framing refused, HSTS
// OFF (it is a one-year commitment the operator has to make knowingly), the
// policy in Report-Only, the text log on, three requests open at once, a
// seven-day ban ceiling, and the question after three failures.
function upgrade_2026_4_4_security_headers() {

	install_add_column('config', 'security_headers',             "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1");
	install_add_column('config', 'security_frame_protection',    "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1");
	install_add_column('config', 'security_hsts',                "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");
	install_add_column('config', 'security_csp_mode',            "VARCHAR(8) NOT NULL DEFAULT 'report'");
	install_add_column('config', 'security_csp_policy',          "TEXT NULL");
	install_add_column('config', 'waf_text_log',                 "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1");
	install_add_column('config', 'waf_inflight_limit',           "TINYINT UNSIGNED NOT NULL DEFAULT 3");
	install_add_column('config', 'waf_auto_ban_max_minutes',     "INT UNSIGNED NOT NULL DEFAULT 10080");
	install_add_column('config', 'login_throttle_captcha_after', "TINYINT UNSIGNED NOT NULL DEFAULT 3");

	install_note('Every response now carries security headers, and a Content Security Policy runs in Report-Only mode: nothing is refused, but the Firewall Log screen lists the third-party sources a policy would refuse. The firewall also writes refusals to a plain-text log for fail2ban, limits how many sign-in and checkout requests one address may have open at once, lengthens repeat bans, and asks a simple arithmetic question after three failed sign-ins. All of it is under Site Settings, Firewall and Security.');
}


// 4.42 - Parasut credentials off the config row in clear text.
//
// parasut_client_secret and parasut_password were VARCHAR columns holding the
// values as typed, and the settings screen wrote them back into the form, so
// anyone who could view the page source could read them. They move into one
// encrypted blob, in the "<ciphertext>:<iv>" shape encrypt_string_with_iv()
// answers, so a provider that later needs a third field costs no schema change.
//
// The old columns stay in place, emptied. Dropping them would take the only copy
// of a credential away from a site that has to roll back to the previous files.
function upgrade_2026_4_4_parasut_credentials() {

	// TEXT and not VARCHAR to match marketplace_accounts.credentials, which holds
	// the same shape. No DEFAULT clause: MySQL rejects a default on a TEXT column.
	install_add_column('config', 'parasut_credentials_enc', "TEXT NULL");

	$row = db_item("SELECT parasut_client_secret, parasut_password, parasut_credentials_enc FROM config LIMIT 1");

	if (!$row) {

		return;

	}

	$client_secret = (string) ($row['parasut_client_secret'] ?? '');
	$password      = (string) ($row['parasut_password'] ?? '');

	// Nothing to move, or a previous run already moved it.
	if (($client_secret === '') && ($password === '')) {

		install_note('Parasut credentials: nothing stored in clear text.');

		return;

	}

	// The upgrade runs with functions.php loaded, so the helper and the key are
	// normally both there. Guarded anyway: leaving the values where they are and
	// saying so beats writing a blob that cannot be read back, and the step is
	// safe to run again once whatever is missing is in place.
	if (!function_exists('encrypt_string_with_iv') || !defined('ENCRYPTION_KEY') || (ENCRYPTION_KEY === '')) {

		install_note('Parasut credentials: encryption is unavailable, the values were left as they are.');

		return;

	}

	$payload = json_encode(array(

		'client_secret' => $client_secret,

		'password'      => $password,

	));

	list($cipher, $iv) = encrypt_string_with_iv($payload);

	db("UPDATE config SET parasut_credentials_enc = '" . escape($cipher . ':' . $iv) . "',"
		. " parasut_client_secret = '', parasut_password = ''");

	install_note('Parasut credentials moved out of clear text into parasut_credentials_enc.');

	upgrade_2026_4_4_parasut_sandbox_off();

}

// Turn off the Parasut sandbox switch and leave it off.
//
// It pointed at api.heroku-staging.parasut.com - Parasut's own staging host, not
// a sandbox offered to merchants. The OAuth call went through the same base URL,
// so a site with the switch on could not get a token at all. The screen no longer
// offers it and nothing reads it; the column stays so that rolling back to the
// previous files finds what it expects.
function upgrade_2026_4_4_parasut_sandbox_off() {

	if (!install_column_exists('config', 'parasut_use_sandbox')) {

		return;

	}

	db("UPDATE config SET parasut_use_sandbox = 0 WHERE parasut_use_sandbox <> 0");

	install_note('Parasut sandbox switch turned off and retired.');

}

// 4.43 - ERP module skeleton.
//
// Eleven tables, created whether or not the module is switched on. Tying schema
// to a feature switch puts a site that enables it later on a schema the version
// number says it already has, and the idempotent steps then skip past the gap
// and fix it in place. Empty tables cost almost nothing; a broken schema on the
// next upgrade costs a site.
//
// Money is stored the way the rest of the codebase stores it: whole kurus in an
// integer column. Rates keep DECIMAL because they are rates, and quantity keeps
// DECIMAL because goods are sold by weight and length as well as by the piece.
//
// InnoDB is not optional here: a receipt writes one cash line and one account
// line, and a half-written pair is a balance that disagrees with its own ledger.
//
// DATE columns default to the zero date, matching every other DATE column in the
// schema. That is the value the codebase compares against to mean unset, in
// seventeen places; a second convention here would leave those comparisons
// quietly missing every ERP row.
function upgrade_2026_4_4_erp_core() {

	upgrade_2026_4_4_erp_config();

	upgrade_2026_4_4_erp_accounts();

	upgrade_2026_4_4_erp_cash();

	upgrade_2026_4_4_erp_invoices();

	upgrade_2026_4_4_erp_waybills();

	upgrade_2026_4_4_erp_support();

	upgrade_2026_4_4_erp_existing_tables();

	upgrade_2026_4_4_erp_permissions();

	install_note('ERP module tables are in place.');

}

function upgrade_2026_4_4_erp_config() {

	install_add_column('config', 'erp_enabled', "TINYINT(1) NOT NULL DEFAULT 0");

	install_add_column('config', 'erp_parasut_enabled', "TINYINT(1) NOT NULL DEFAULT 0");

	install_add_column('config', 'erp_default_series', "VARCHAR(10) NOT NULL DEFAULT 'PGF'");

	// off: never; order_paid: when payment lands; order_shipped: when it leaves.
	install_add_column('config', 'erp_auto_invoice_on', "ENUM('off','order_paid','order_shipped') NOT NULL DEFAULT 'off'");

	install_add_column('config', 'erp_default_cash_account_id', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('config', 'erp_einvoice_scenario', "ENUM('basic','commercial') NOT NULL DEFAULT 'basic'");

	// Printed on e-archive invoices for internet sales, which have to name the
	// address the sale was made at.
	install_add_column('config', 'erp_web_address', "VARCHAR(255) NOT NULL DEFAULT ''");

}

function upgrade_2026_4_4_erp_accounts() {

	// balance and balance_fc are caches of the ledger below, refreshed inside the
	// same transaction that writes the movement. balance_fc holds the figure in
	// the account's own currency so a foreign-currency account can be settled in
	// that currency and still be reported in lira.
	//
	// There is no opening_balance column on purpose. The opening figure is a
	// movement like any other; holding it in both places counts it twice, which
	// is exactly what the first draft of this schema did.
	//
	// parasut_contact_id is not here either - contacts.parasut_contact_id has
	// held it since 2026.1.17 and two homes for one identifier is one too many.
	install_create_table('erp_accounts', "CREATE TABLE erp_accounts (
		id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
		kind                ENUM('customer','supplier','both') NOT NULL DEFAULT 'customer',
		title               VARCHAR(255) NOT NULL DEFAULT '',
		is_person           TINYINT(1) NOT NULL DEFAULT 1,
		tax_number          VARCHAR(11) NOT NULL DEFAULT '',
		tax_office          VARCHAR(100) NOT NULL DEFAULT '',
		email               VARCHAR(255) NOT NULL DEFAULT '',
		phone               VARCHAR(50) NOT NULL DEFAULT '',
		address             VARCHAR(255) NOT NULL DEFAULT '',
		district            VARCHAR(100) NOT NULL DEFAULT '',
		city                VARCHAR(100) NOT NULL DEFAULT '',
		country_code        CHAR(2) NOT NULL DEFAULT 'TR',
		postcode            VARCHAR(20) NOT NULL DEFAULT '',
		currency            CHAR(3) NOT NULL DEFAULT 'TRY',
		balance             BIGINT NOT NULL DEFAULT 0,
		balance_fc          BIGINT NOT NULL DEFAULT 0,
		balance_updated_at  INT UNSIGNED NOT NULL DEFAULT 0,
		contact_id          INT UNSIGNED NOT NULL DEFAULT 0,
		einvoice_user       TINYINT(1) NOT NULL DEFAULT 0,
		einvoice_alias      VARCHAR(100) NOT NULL DEFAULT '',
		einvoice_aliases    TEXT,
		einvoice_checked_at INT UNSIGNED NOT NULL DEFAULT 0,
		status              ENUM('active','passive') NOT NULL DEFAULT 'active',
		notes               TEXT,
		created_by          INT UNSIGNED NOT NULL DEFAULT 0,
		created_at          INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at          INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_kind_title (kind, title),
		KEY idx_tax_number (tax_number),
		KEY idx_contact (contact_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// Append only. A correction is a second, opposite movement, never an edit:
	// a ledger that can be rewritten cannot be reconciled against anything.
	// amount is always positive; direction says which way it goes.
	install_create_table('erp_account_transactions', "CREATE TABLE erp_account_transactions (
		id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		account_id           INT UNSIGNED NOT NULL DEFAULT 0,
		doc_date             DATE NOT NULL DEFAULT '0000-00-00',
		kind                 ENUM('opening','invoice','return','collection','payment','adjustment','writeoff','fx_diff') NOT NULL DEFAULT 'adjustment',
		direction            ENUM('debit','credit') NOT NULL DEFAULT 'debit',
		amount               BIGINT NOT NULL DEFAULT 0,
		currency             CHAR(3) NOT NULL DEFAULT 'TRY',
		exchange_rate        DECIMAL(15,6) NOT NULL DEFAULT 1.000000,
		exchange_rate_date   DATE NOT NULL DEFAULT '0000-00-00',
		exchange_rate_source VARCHAR(20) NOT NULL DEFAULT '',
		amount_try           BIGINT NOT NULL DEFAULT 0,
		doc_type             VARCHAR(20) NOT NULL DEFAULT '',
		doc_id               INT UNSIGNED NOT NULL DEFAULT 0,
		description          VARCHAR(255) NOT NULL DEFAULT '',
		created_by           INT UNSIGNED NOT NULL DEFAULT 0,
		created_at           INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_statement (account_id, doc_date, id),
		KEY idx_document (doc_type, doc_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

}

function upgrade_2026_4_4_erp_cash() {

	install_create_table('erp_cash_accounts', "CREATE TABLE erp_cash_accounts (
		id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
		name            VARCHAR(100) NOT NULL DEFAULT '',
		kind            ENUM('cash','bank','pos','credit_card') NOT NULL DEFAULT 'cash',
		currency        CHAR(3) NOT NULL DEFAULT 'TRY',
		iban            VARCHAR(34) NOT NULL DEFAULT '',
		bank_name       VARCHAR(100) NOT NULL DEFAULT '',
		opening_balance BIGINT NOT NULL DEFAULT 0,
		balance         BIGINT NOT NULL DEFAULT 0,
		is_active       TINYINT(1) NOT NULL DEFAULT 1,
		sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		created_at      INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at      INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_active (is_active, sort_order)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// transfer_pair_id links the two halves of a transfer between own accounts,
	// so the pair can be shown as one move and undone as one.
	install_create_table('erp_cash_transactions', "CREATE TABLE erp_cash_transactions (
		id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		cash_account_id    INT UNSIGNED NOT NULL DEFAULT 0,
		doc_date           DATE NOT NULL DEFAULT '0000-00-00',
		direction          ENUM('in','out') NOT NULL DEFAULT 'in',
		amount             BIGINT NOT NULL DEFAULT 0,
		currency           CHAR(3) NOT NULL DEFAULT 'TRY',
		exchange_rate      DECIMAL(15,6) NOT NULL DEFAULT 1.000000,
		exchange_rate_date DATE NOT NULL DEFAULT '0000-00-00',
		amount_try         BIGINT NOT NULL DEFAULT 0,
		account_id         INT UNSIGNED NOT NULL DEFAULT 0,
		doc_type           VARCHAR(20) NOT NULL DEFAULT '',
		doc_id             INT UNSIGNED NOT NULL DEFAULT 0,
		payment_method     ENUM('cash','transfer','card','other') NOT NULL DEFAULT 'cash',
		transfer_pair_id   BIGINT UNSIGNED NOT NULL DEFAULT 0,
		description        VARCHAR(255) NOT NULL DEFAULT '',
		created_by         INT UNSIGNED NOT NULL DEFAULT 0,
		created_at         INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_book (cash_account_id, doc_date, id),
		KEY idx_document (doc_type, doc_id),
		KEY idx_account (account_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

}

function upgrade_2026_4_4_erp_invoices() {

	// order_id is indexed but not unique. Three items shipped, one returned, then
	// another: that is two return documents against one order, and a unique key
	// refuses the second. One sales invoice per order is enforced in code, since
	// MySQL has no partial index to say "unique only where doc_type = invoice".
	//
	// Sales and purchase numbering are separate series, hence direction in the
	// unique key: a purchase bill carries the supplier's number, not ours.
	install_create_table('erp_invoices', "CREATE TABLE erp_invoices (
		id                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
		direction                ENUM('sales','purchase') NOT NULL DEFAULT 'sales',
		doc_type                 ENUM('invoice','return','proforma') NOT NULL DEFAULT 'invoice',
		invoice_type             ENUM('SATIS','ISTISNA','TEVKIFAT','IADE','OZELMATRAH','IHRACAT') NOT NULL DEFAULT 'SATIS',
		series                   VARCHAR(10) NOT NULL DEFAULT '',
		number                   INT UNSIGNED NOT NULL DEFAULT 0,
		issue_year               SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		full_number              VARCHAR(32) NOT NULL DEFAULT '',
		supplier_invoice_no      VARCHAR(32) NOT NULL DEFAULT '',
		supplier_invoice_date    DATE NOT NULL DEFAULT '0000-00-00',
		account_id               INT UNSIGNED NOT NULL DEFAULT 0,
		order_id                 INT UNSIGNED NOT NULL DEFAULT 0,
		parent_invoice_id        INT UNSIGNED NOT NULL DEFAULT 0,
		issue_date               DATE NOT NULL DEFAULT '0000-00-00',
		due_date                 DATE NOT NULL DEFAULT '0000-00-00',
		currency                 CHAR(3) NOT NULL DEFAULT 'TRY',
		exchange_rate            DECIMAL(15,6) NOT NULL DEFAULT 1.000000,
		exchange_rate_date       DATE NOT NULL DEFAULT '0000-00-00',
		subtotal                 BIGINT NOT NULL DEFAULT 0,
		discount_total           BIGINT NOT NULL DEFAULT 0,
		shipping_total           BIGINT NOT NULL DEFAULT 0,
		surcharge_total          BIGINT NOT NULL DEFAULT 0,
		gift_card_total          BIGINT NOT NULL DEFAULT 0,
		tax_total                BIGINT NOT NULL DEFAULT 0,
		withholding_total        BIGINT NOT NULL DEFAULT 0,
		grand_total              BIGINT NOT NULL DEFAULT 0,
		grand_total_try          BIGINT NOT NULL DEFAULT 0,
		paid_total               BIGINT NOT NULL DEFAULT 0,
		status                   ENUM('draft','issued','partially_paid','paid','cancelled') NOT NULL DEFAULT 'draft',
		is_internet_sale         TINYINT(1) NOT NULL DEFAULT 0,
		payment_method           VARCHAR(30) NOT NULL DEFAULT '',
		payment_date             DATE NOT NULL DEFAULT '0000-00-00',
		shipment_date            DATE NOT NULL DEFAULT '0000-00-00',
		carrier_title            VARCHAR(255) NOT NULL DEFAULT '',
		carrier_vkn              VARCHAR(11) NOT NULL DEFAULT '',
		web_address              VARCHAR(255) NOT NULL DEFAULT '',
		edoc_kind                ENUM('none','einvoice','earchive') NOT NULL DEFAULT 'none',
		edoc_scenario            ENUM('basic','commercial') NOT NULL DEFAULT 'basic',
		edoc_status              ENUM('none','queued','sending','sent','accepted','rejected','error') NOT NULL DEFAULT 'none',
		earchive_cancel_deadline DATE NOT NULL DEFAULT '0000-00-00',
		parasut_invoice_id       VARCHAR(32) NOT NULL DEFAULT '',
		parasut_edoc_id          VARCHAR(32) NOT NULL DEFAULT '',
		parasut_job_id           VARCHAR(64) NOT NULL DEFAULT '',
		parasut_job_expires_at   INT UNSIGNED NOT NULL DEFAULT 0,
		gib_uuid                 CHAR(36) NOT NULL DEFAULT '',
		gib_number               VARCHAR(20) NOT NULL DEFAULT '',
		edoc_error               TEXT,
		edoc_sent_at             INT UNSIGNED NOT NULL DEFAULT 0,
		notes                    TEXT,
		created_by               INT UNSIGNED NOT NULL DEFAULT 0,
		created_at               INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at               INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uniq_number (direction, series, number, issue_year),
		KEY idx_account (account_id, issue_date),
		KEY idx_order (order_id),
		KEY idx_edoc_status (edoc_status),
		KEY idx_status (direction, status, issue_date)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// The column is tax_total and not tax on purpose. order_items.tax is a UNIT
	// amount and the difference has cost this codebase three bugs; a line on an
	// invoice carries the tax for the whole line, and a name that does not match
	// stops the reader instead of letting them assume.
	//
	// vat_exemption_code and withholding_code are UBL-TR code lists. A zero-rated
	// line has to say why it is zero-rated or the document comes back.
	install_create_table('erp_invoice_items', "CREATE TABLE erp_invoice_items (
		id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
		invoice_id         INT UNSIGNED NOT NULL DEFAULT 0,
		line_no            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		product_id         INT UNSIGNED NOT NULL DEFAULT 0,
		description        VARCHAR(255) NOT NULL DEFAULT '',
		quantity           DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
		unit_code          VARCHAR(10) NOT NULL DEFAULT 'C62',
		unit_price         BIGINT NOT NULL DEFAULT 0,
		discount_rate      DECIMAL(6,3) NOT NULL DEFAULT 0.000,
		discount_amount    BIGINT NOT NULL DEFAULT 0,
		tax_rate           DECIMAL(6,3) NOT NULL DEFAULT 0.000,
		tax_total          BIGINT NOT NULL DEFAULT 0,
		vat_exemption_code VARCHAR(10) NOT NULL DEFAULT '',
		withholding_rate   DECIMAL(6,3) NOT NULL DEFAULT 0.000,
		withholding_code   VARCHAR(10) NOT NULL DEFAULT '',
		line_total         BIGINT NOT NULL DEFAULT 0,
		gtip               VARCHAR(20) NOT NULL DEFAULT '',
		returned_qty       DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
		PRIMARY KEY (id),
		KEY idx_invoice (invoice_id, line_no),
		KEY idx_product (product_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

}

function upgrade_2026_4_4_erp_waybills() {

	// No edoc_* columns. Parasut's API exposes shipment_documents for create and
	// read but nothing that turns one into an e-irsaliye, and columns for a
	// feature that may not be reachable are columns nobody can explain later.
	// They go in when the endpoint is confirmed.
	install_create_table('erp_waybills', "CREATE TABLE erp_waybills (
		id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
		series              VARCHAR(10) NOT NULL DEFAULT '',
		number              INT UNSIGNED NOT NULL DEFAULT 0,
		issue_year          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		full_number         VARCHAR(32) NOT NULL DEFAULT '',
		account_id          INT UNSIGNED NOT NULL DEFAULT 0,
		order_id            INT UNSIGNED NOT NULL DEFAULT 0,
		invoice_id          INT UNSIGNED NOT NULL DEFAULT 0,
		issue_date          DATE NOT NULL DEFAULT '0000-00-00',
		ship_date           DATE NOT NULL DEFAULT '0000-00-00',
		ship_time           TIME NOT NULL DEFAULT '00:00:00',
		carrier_title       VARCHAR(255) NOT NULL DEFAULT '',
		carrier_vkn         VARCHAR(11) NOT NULL DEFAULT '',
		plate               VARCHAR(20) NOT NULL DEFAULT '',
		driver_name         VARCHAR(100) NOT NULL DEFAULT '',
		driver_tckn         VARCHAR(11) NOT NULL DEFAULT '',
		ship_to_title       VARCHAR(255) NOT NULL DEFAULT '',
		ship_to_address     VARCHAR(255) NOT NULL DEFAULT '',
		ship_to_district    VARCHAR(100) NOT NULL DEFAULT '',
		ship_to_city        VARCHAR(100) NOT NULL DEFAULT '',
		ship_to_country     CHAR(2) NOT NULL DEFAULT 'TR',
		status              ENUM('draft','issued','cancelled') NOT NULL DEFAULT 'draft',
		parasut_shipment_id VARCHAR(32) NOT NULL DEFAULT '',
		notes               TEXT,
		created_by          INT UNSIGNED NOT NULL DEFAULT 0,
		created_at          INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at          INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uniq_number (series, number, issue_year),
		KEY idx_account (account_id, issue_date),
		KEY idx_order (order_id),
		KEY idx_invoice (invoice_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('erp_waybill_items', "CREATE TABLE erp_waybill_items (
		id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
		waybill_id  INT UNSIGNED NOT NULL DEFAULT 0,
		line_no     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		product_id  INT UNSIGNED NOT NULL DEFAULT 0,
		description VARCHAR(255) NOT NULL DEFAULT '',
		quantity    DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
		unit_code   VARCHAR(10) NOT NULL DEFAULT 'C62',
		PRIMARY KEY (id),
		KEY idx_waybill (waybill_id, line_no)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

}

function upgrade_2026_4_4_erp_support() {

	// A number is taken under a row lock but only spent once the document is
	// saved. Spending it on a draft leaves a hole in the series every time
	// someone opens a form and walks away, and a series with holes is the one
	// thing the tax authority does ask about.
	install_create_table('erp_document_series', "CREATE TABLE erp_document_series (
		id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
		series      VARCHAR(10) NOT NULL DEFAULT '',
		doc_kind    ENUM('sales_invoice','sales_return','purchase_invoice','proforma','waybill','collection','payment') NOT NULL DEFAULT 'sales_invoice',
		issue_year  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		last_number INT UNSIGNED NOT NULL DEFAULT 0,
		prefix      VARCHAR(10) NOT NULL DEFAULT '',
		padding     TINYINT UNSIGNED NOT NULL DEFAULT 9,
		PRIMARY KEY (id),
		UNIQUE KEY uniq_series (series, doc_kind, issue_year)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// Two ladders, not one. poll asks the trackable job, which is only valid for
	// fifteen minutes; reconcile reads the document itself and is what makes the
	// answer certain on a site whose cron runs every few minutes.
	install_create_table('erp_edoc_queue', "CREATE TABLE erp_edoc_queue (
		id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
		doc_type        ENUM('invoice','waybill') NOT NULL DEFAULT 'invoice',
		doc_id          INT UNSIGNED NOT NULL DEFAULT 0,
		action          ENUM('create','convert','poll','reconcile','pay','cancel') NOT NULL DEFAULT 'create',
		status          ENUM('pending','sending','done','failed','abandoned') NOT NULL DEFAULT 'pending',
		attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
		next_attempt_at INT UNSIGNED NOT NULL DEFAULT 0,
		parasut_job_id  VARCHAR(64) NOT NULL DEFAULT '',
		last_error      TEXT,
		created_at      INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at      INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_due (status, next_attempt_at),
		KEY idx_document (doc_type, doc_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// Excerpts, not bodies, and masked before they are written: these payloads
	// carry names, addresses and tax numbers, and a log is not a place to keep
	// them. Cleared after thirty days.
	install_create_table('erp_parasut_log', "CREATE TABLE erp_parasut_log (
		id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		doc_type         VARCHAR(20) NOT NULL DEFAULT '',
		doc_id           INT UNSIGNED NOT NULL DEFAULT 0,
		method           VARCHAR(10) NOT NULL DEFAULT '',
		path             VARCHAR(255) NOT NULL DEFAULT '',
		http_code        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		duration_ms      INT UNSIGNED NOT NULL DEFAULT 0,
		request_excerpt  VARCHAR(500) NOT NULL DEFAULT '',
		response_excerpt VARCHAR(500) NOT NULL DEFAULT '',
		created_at       INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_document (doc_type, doc_id),
		KEY idx_created (created_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

}

function upgrade_2026_4_4_erp_permissions() {

	// Three switches, not one per screen. The gate decides whether the module is
	// visible at all; the other two carve off the parts that are not everyone's
	// business - what is in the till, and the settings that decide how documents
	// are numbered and where they are sent.
	//
	// Prefix-less names and TINYINT, which is how permission columns have been
	// added since manage_ecommerce_reports. The older user_manage_* columns are
	// ENUM('no','yes') and are not a pattern to copy.
	install_add_column('user', 'manage_erp', "TINYINT UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('user', 'manage_erp_cash', "TINYINT UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('user', 'manage_erp_settings', "TINYINT UNSIGNED NOT NULL DEFAULT 0");

}

function upgrade_2026_4_4_erp_existing_tables() {

	// The ERP invoice, as opposed to orders.parasut_invoice_id, which holds
	// Parasut's own identifier and stays where it is.
	install_add_column('orders', 'erp_invoice_id', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('orders', 'erp_account_id', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_add_index('orders', 'idx_erp_invoice', "KEY idx_erp_invoice (erp_invoice_id)");

	// When the money arrived, which is not when the order was placed. An e-archive
	// invoice for an internet sale has to state it, and there was nowhere to read
	// it from: order_date is the order, and nothing recorded the payment.
	install_add_column('orders', 'paid_at', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_add_column('contacts', 'erp_account_id', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_add_index('contacts', 'idx_erp_account', "KEY idx_erp_account (erp_account_id)");

	// The carrier's registered name and tax number, required on an e-archive
	// invoice for goods sold over the internet. Neither was held anywhere:
	// shipping_methods had the name of the service, not of the company behind it.
	install_add_column('shipping_methods', 'carrier_title', "VARCHAR(255) NOT NULL DEFAULT ''");

	install_add_column('shipping_methods', 'carrier_vkn', "VARCHAR(11) NOT NULL DEFAULT ''");

	// Why a product is zero-rated, which the document has to say. products.tax_rate
	// can record 0.000 but not the reason for it.
	install_add_column('products', 'vat_exemption_code', "VARCHAR(10) NOT NULL DEFAULT ''");

}


// 4.44 - order line tax moves from a unit amount to a line amount.
//
// order_items.tax held the tax on ONE unit, and every total multiplied it by the
// quantity. Three other places disagreed: the cart summary works the tax out on
// the line total (widgets_cart), and so do Parasut and the e-document format the
// tax authority accepts. round(rate x unit) x qty and round(rate x unit x qty)
// are equal at quantity one and one to three kurus apart above it, so an invoice
// could not tie to what was charged.
//
// The line amount wins because three of the four places already used it, and
// because it is the base the document has to be filed on.
//
// A new column rather than a redefinition of the old one: a reader left behind
// would have carried on multiplying by the quantity and overcharged by that
// factor, silently. tax is no longer written, so a missed reader now shows zero
// - wrong, but loudly wrong.
//
// The backfill derives from a column this step never writes, and skips rows that
// already carry a figure, so running it again changes nothing.
function upgrade_2026_4_4_order_tax_base() {

	install_add_column('order_items', 'tax_total', "INT NOT NULL DEFAULT 0");

	db("UPDATE order_items SET tax_total = tax * quantity WHERE tax <> 0 AND tax_total = 0");

	install_note('Order line tax carried over to order_items.tax_total as a line amount.');

}

// 4.45 - which receipt closed which invoice.
//
// The money movement is already in the ledger: a receipt credits the account and
// the balance falls. What was missing is the allocation - THAT receipt paid THIS
// invoice. It is a separate layer on purpose, because posting a second ledger
// entry for it would count the same money twice.
//
// One row per (invoice, movement) pair, enforced by a unique key: allocating the
// same receipt to the same invoice again corrects the amount rather than
// stacking a second helping of it. A receipt may be split over several invoices
// and an invoice may be closed by several receipts, which is why this is a table
// and not a column.
//
// erp_invoices.paid_total is derived from these rows rather than nudged, for the
// same reason the balances are: a counter that is only ever incremented drifts,
// and there is nothing left to check it against.
function upgrade_2026_4_4_erp_settlements() {

	install_create_table('erp_settlements', "CREATE TABLE erp_settlements (
		id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		invoice_id     INT UNSIGNED NOT NULL DEFAULT 0,
		account_txn_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		account_id     INT UNSIGNED NOT NULL DEFAULT 0,
		doc_date       DATE NOT NULL DEFAULT '0000-00-00',
		amount         BIGINT NOT NULL DEFAULT 0,
		amount_try     BIGINT NOT NULL DEFAULT 0,
		created_by     INT UNSIGNED NOT NULL DEFAULT 0,
		created_at     INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uniq_pair (invoice_id, account_txn_id),
		KEY idx_invoice (invoice_id),
		KEY idx_txn (account_txn_id),
		KEY idx_account (account_id, doc_date)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_note('Invoice settlements table added (which receipt closed which invoice).');

}

// 4.46 - returns count on a series of their own.
//
// erp_document_series.doc_kind was an ENUM that only knew about the documents
// the original ERP schema had planned for, so asking it for a return number
// silently produced no row at all: MySQL will not store a value the column has
// never heard of, and the read-back then found nothing. Widening the column is
// the fix; the read-back now also says so out loud rather than returning an
// empty error.
//
// A return gets its own counter so a missing number in the invoice run never has
// to be explained as "that one was a return".
//
// Databases created after this step already carry the wider list, so the check
// below skips them rather than running an ALTER on every upgrade.
function upgrade_2026_4_4_erp_return_series() {

	$column = install_column_info('erp_document_series', 'doc_kind');

	if (is_array($column) && (strpos((string) $column['Type'], "'sales_return'") !== false)) {

		return install_skipped(lang('erp_document_series.doc_kind already knows about returns'));

	}

	install_modify_column('erp_document_series', 'doc_kind',
		"ENUM('sales_invoice','sales_return','purchase_invoice','proforma','waybill','collection','payment') NOT NULL DEFAULT 'sales_invoice'");

	install_note('Returns and purchase invoices can now take their own document numbers.');

}

// 4.47 - what a printed invoice needs that the config row did not yet hold.
//
// A PDF invoice has to name the seller the way the tax office knows it: by tax
// number and tax office. Neither had a home on the config row because the
// Parasut integration carried them on the Parasut side; a locally rendered
// document cannot reach across for them, so they live here.
//
// The template is admin-editable HTML and can run to tens of kilobytes, which
// is why it is MEDIUMTEXT and not a constant read on every page. MySQL refuses
// a DEFAULT on a TEXT column, so it is nullable: NULL or empty means "use the
// built-in template".
function upgrade_2026_4_4_erp_invoice_document() {

	// Tax number (VKN, 10 digits) or ID number (TCKN, 11 digits) of the seller.
	install_add_column('config', 'erp_seller_vkn', "VARCHAR(11) NOT NULL DEFAULT ''");

	install_add_column('config', 'erp_seller_tax_office', "VARCHAR(100) NOT NULL DEFAULT ''");

	// The HTML template the PDF invoice is rendered from; empty means built-in.
	install_add_column('config', 'erp_invoice_template', "MEDIUMTEXT NULL");

	install_note('Invoices can now be printed as PDF with the seller\'s tax number and tax office; the template can be edited in the panel.');

}

// 4.48 - a bank transfer order is not paid until the money is seen.
//
// orders.payment_method is an ENUM and the connection runs without strict
// mode, so a value outside the list is stored as ''. 'Pay With Iyzico' was
// never in the list, which blanked the method on every order paid that way.
// The column is only rewritten when the value is missing, and the new value is
// appended to the list the column already has: a MODIFY that dropped a value in
// use would coerce those rows to '', the very damage this step repairs.
//
// The cancel-days setting drives the periodic job that cancels transfer orders
// nobody paid for; 0 leaves them open until an operator acts. Whether an order
// is awaiting payment is derived from payment_method and paid_at, so the status
// column gains no value and every report keyed on it keeps working.
function upgrade_2026_4_4_offline_payment_awaiting() {

	$column = install_column_info('orders', 'payment_method');

	if (!is_array($column)) {

		install_skipped(lang('orders.payment_method does not exist, skipped'));

	} elseif (stripos((string) $column['Type'], "'Pay With Iyzico'") !== false) {

		install_skipped(lang('orders.payment_method already knows about Pay With Iyzico'));

	} elseif (preg_match('/^enum\((.*)\)$/is', trim((string) $column['Type']), $enum_match)) {

		// Nullability and default are copied from the column as it is, so the
		// only change is the extra value at the end of the list.
		$nullable = (strtoupper((string) $column['Null']) === 'YES');
		$default  = ($column['Default'] === null) ? ($nullable ? 'NULL' : "''") : "'" . e((string) $column['Default']) . "'";

		install_modify_column('orders', 'payment_method',
			"ENUM(" . $enum_match[1] . ",'Pay With Iyzico') " . ($nullable ? 'NULL' : 'NOT NULL') . " DEFAULT " . $default);

	} else {

		// Somebody already widened the column to a free-text type; nothing to add.
		install_skipped(lang('orders.payment_method is not an ENUM, skipped'));

	}

	// 0 = never cancel automatically; the periodic job reads this once per run.
	install_add_column('config', 'ecommerce_offline_payment_cancel_days', "TINYINT UNSIGNED NOT NULL DEFAULT 0");

	install_note('Bank transfer orders show as awaiting payment until the payment is recorded, and unpaid ones can be cancelled automatically after a set number of days.');

}


// 4.49 - the ERP counts in the store's base currency, and can hold documents
// in another one.
//
// The first ERP schema named its converted columns amount_try / grand_total_try:
// the module was drafted for one country. Pinegrap is installed anywhere, and
// its home currency is whatever edit_currency.php marks as base, so the
// columns are renamed to *_base while 2026.4.4 is still unreleased - nobody
// has run the earlier shape. Each rename copies the column's type, nullability
// and default from the column as it stands, and is skipped once the new name
// exists.
//
// currency_rates is the dated history behind update_exchange_rates.php. The
// store's own currencies.exchange_rate is one figure overwritten in place and
// points the other way (units of the foreign currency per base unit); an
// invoice raised in March and paid in May needs both days' rates months later,
// in the direction the ledger multiplies (base units per unit of the document
// currency). One row per (day, base, currency) so a second run of the job on
// the same day corrects rather than duplicates.
//
// Foreign-currency support is an opt-in setting (erp_fx_enabled); with it off
// every document, account and till is in the base currency and the screens do
// not ask.
function upgrade_2026_4_4_erp_foreign_currency() {

	$renames = array(
		array('erp_account_transactions', 'amount_try', 'amount_base'),
		array('erp_cash_transactions', 'amount_try', 'amount_base'),
		array('erp_invoices', 'grand_total_try', 'grand_total_base'),
		array('erp_settlements', 'amount_try', 'amount_base'),
	);

	foreach ($renames as $rename) {

		list($table, $old, $new) = $rename;

		if (install_column_exists($table, $new)) {

			install_skipped(lang(array('string' => '{var:1} was already renamed to {var:2}', 'vars' => array($table . '.' . $old, $new))));

			continue;

		}

		$column = install_column_info($table, $old);

		if (!is_array($column)) {

			install_skipped(lang(array('string' => '{var:1} does not exist, skipped', 'vars' => $table . '.' . $old)));

			continue;

		}

		$nullable = (strtoupper((string) $column['Null']) === 'YES');
		$default  = ($column['Default'] === null) ? ($nullable ? ' DEFAULT NULL' : '') : " DEFAULT '" . e((string) $column['Default']) . "'";

		install_rename_column($table, $old, $new, (string) $column['Type'] . ($nullable ? ' NULL' : ' NOT NULL') . $default);

	}

	// rate = base units per 1 unit of currency_code, the direction the ledger
	// multiplies in. source names the feed the figure came from.
	install_create_table('currency_rates', "CREATE TABLE currency_rates (
		id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
		rate_date     DATE NOT NULL DEFAULT '0000-00-00',
		base_code     CHAR(3) NOT NULL DEFAULT '',
		currency_code CHAR(3) NOT NULL DEFAULT '',
		rate          DECIMAL(18,8) NOT NULL DEFAULT 0,
		source        VARCHAR(32) NOT NULL DEFAULT '',
		fetched_at    INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uniq_day (rate_date, base_code, currency_code)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// Off by default: a shop that never sees a foreign invoice keeps today's
	// single-currency screens.
	install_add_column('config', 'erp_fx_enabled', "TINYINT(1) NOT NULL DEFAULT 0");

	// Comma-separated ISO codes offered on documents besides the base currency.
	install_add_column('config', 'erp_fx_currencies', "VARCHAR(64) NOT NULL DEFAULT 'USD,EUR,GBP'");

	// Post the base-currency gap between an invoice and the receipts that closed it.
	install_add_column('config', 'erp_fx_auto_diff', "TINYINT(1) NOT NULL DEFAULT 1");

	// Where a document's rate came from; the ledger rows already record it.
	install_add_column('erp_invoices', 'exchange_rate_source', "VARCHAR(32) NOT NULL DEFAULT ''");

	install_add_column('erp_cash_transactions', 'exchange_rate_source', "VARCHAR(32) NOT NULL DEFAULT ''");

	install_note('The ERP counts in the store\'s base currency and, when switched on in the ERP settings, can raise invoices and keep accounts and tills in another currency; daily exchange rates are kept as a dated history.');

}

// 4.50 - a return line points at the invoice line it was taken from.
//
// erp_invoice_items had no link from a return line back to the parent line, so
// cancelling a return handed the quantity back by product_id ... LIMIT 1. Two
// lines of the same product on one invoice - a different price, a different
// discount - cannot be told apart that way, and the wrong line could end up
// returnable again while the right one stayed used up. parent_line_id records
// the link and both the tax cap on a further return and the cancel read it.
//
// Rows written before this step are filled in where the parent invoice has
// exactly one line for the product, which is the only case the old match was
// exact in. Where it has several the link stays 0 and those rows keep the
// product match, so the step can run again without touching them twice.
function upgrade_2026_4_4_erp_return_line_link() {

	install_add_column('erp_invoice_items', 'parent_line_id', "INT UNSIGNED NOT NULL DEFAULT 0");

	install_add_index('erp_invoice_items', 'idx_parent_line', "KEY idx_parent_line (parent_line_id)");

	db("UPDATE erp_invoice_items r
		INNER JOIN erp_invoices d ON r.invoice_id = d.id AND d.doc_type = 'return'
		INNER JOIN (
			SELECT invoice_id, product_id, MIN(id) AS line_id, COUNT(*) AS line_count
			FROM erp_invoice_items
			GROUP BY invoice_id, product_id
		) p ON p.invoice_id = d.parent_invoice_id AND p.product_id = r.product_id AND p.line_count = 1
		SET r.parent_line_id = p.line_id
		WHERE r.parent_line_id = 0");

	install_note('Return lines now record the invoice line they were taken from, so cancelling a return restores the right line.');

}


// ERP: the account as it read when the invoice was issued (2026.4.4, 4.51).
//
// An invoice names its counterparty; the account card is edited afterwards -
// a company renames itself, moves, changes its tax office - and a document
// that reads the card live starts saying something it never said. The
// snapshot is taken when the invoice is issued and never touched again; the
// document reads the snapshot and falls back to the live card only for
// invoices written before this step.
function upgrade_2026_4_4_erp_account_snapshot() {

	install_add_column('erp_invoices', 'account_title', "VARCHAR(255) NOT NULL DEFAULT ''");
	install_add_column('erp_invoices', 'account_tax_number', "VARCHAR(32) NOT NULL DEFAULT ''");
	install_add_column('erp_invoices', 'account_tax_office', "VARCHAR(100) NOT NULL DEFAULT ''");
	install_add_column('erp_invoices', 'account_address', "VARCHAR(255) NOT NULL DEFAULT ''");
	install_add_column('erp_invoices', 'account_city', "VARCHAR(100) NOT NULL DEFAULT ''");
	install_add_column('erp_invoices', 'account_country_code', "CHAR(2) NOT NULL DEFAULT ''");
	install_add_column('erp_invoices', 'account_email', "VARCHAR(255) NOT NULL DEFAULT ''");

	// Issued documents written before the snapshot existed take the card as
	// it reads today: the best record there is of what they said. Drafts are
	// left alone; they copy the card when they are issued. Re-runnable: only
	// rows with an empty snapshot are touched.
	db("UPDATE erp_invoices i
		INNER JOIN erp_accounts a ON i.account_id = a.id
		SET i.account_title = a.title,
			i.account_tax_number = a.tax_number,
			i.account_tax_office = a.tax_office,
			i.account_address = TRIM(CONCAT(a.address,
				CASE WHEN TRIM(CONCAT(a.postcode, ' ', a.district)) <> '' THEN CONCAT(', ', TRIM(CONCAT(a.postcode, ' ', a.district))) ELSE '' END)),
			i.account_city = a.city,
			i.account_country_code = a.country_code,
			i.account_email = a.email
		WHERE i.account_title = '' AND i.status <> 'draft'");

	install_note('Invoices now keep a copy of the account title, tax details and address as they were when the document was issued.');

}
