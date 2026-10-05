<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.4.6. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

// 2026.4.6 - the release that follows 2026.4.5.
//
// One entry point, one step per subsystem, called in the order the work was
// done. The numbers in the comments are labels only and follow the ranges
// 2026.4.5 used (general work from 6.1, ERP 6.58-6.69 and from 6.90, API
// 6.70-6.79, the workspace 6.80-6.89 and 6.110-6.119); the order of the calls
// is what runs.
function upgrade_to_2026_4_6() {

	upgrade_2026_4_6_variant_price_rules();     // 6.1

	upgrade_2026_4_6_translations();            // 6.2

}

// The price rules a variant set was built with (2026.4.6, 6.1;
// pg_variant_price_compute() in includes/fn/ecommerce.php). The product screen
// prices every combination from per-option rules - an option that sets the
// price, a multiplier, an amount or a percentage - and writes the result into
// each variant's own products.price, which is still the only price anything
// reads. The rules are kept on the set as JSON so they are not lost once the
// variants exist: they cannot be worked back out of the prices. Nothing
// queries inside them. NULL is a set built without rules, which is every set
// that exists today; nothing is backfilled.
function upgrade_2026_4_6_variant_price_rules() {

	install_add_column('product_groups', 'variant_price_rules', "TEXT NULL");

	install_note('Variant sets: prices can be set per option (the option sets the price, multiplies it, or adds an amount or a percentage) and every variant is priced from them when the set is created.');

}

// Front-end translation (2026.4.6, 6.2; includes/translate/). A page built in
// the visual editor is served in another language under a virtual language
// directory (/en/about): the same tree is drawn with its texts read from a
// translation table, so nothing of the design is copied. The unit of
// translation is the text, not the page - a text that appears on forty pages
// is one row - and the translation of a text is kept once per language.
//
//   site_languages        the target languages and their settings
//   translation_strings   every source text seen, keyed by a hash of it
//   translations          the text of a string in one language
//   translation_uses      where a string appears (page, shared component, menu)
//   translation_jobs      an "update translations" run and its items
//   translation_glossary  terms that are kept or always translated one way
//   page_translations     the drawn body of a page in one language (a cache)
//
// The config row carries the source language, the list of active prefixes the
// router reads with raw mysqli before anything else is loaded, the Google
// Cloud key (encrypted, cipher:iv) and the notes the engines are given.
function upgrade_2026_4_6_translations() {

	install_create_table('site_languages', "CREATE TABLE site_languages (
		code            VARCHAR(16) NOT NULL,
		label           VARCHAR(100) NOT NULL DEFAULT '',
		enabled         TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
		prefix          VARCHAR(16) NOT NULL DEFAULT '',
		engine          VARCHAR(32) NOT NULL DEFAULT 'manual',
		fallback_engine VARCHAR(32) NOT NULL DEFAULT '',
		index_policy    ENUM('reviewed','all','none') NOT NULL DEFAULT 'reviewed',
		locale_number   VARCHAR(16) NOT NULL DEFAULT '',
		locale_date     VARCHAR(32) NOT NULL DEFAULT '',
		og_locale       VARCHAR(16) NOT NULL DEFAULT '',
		sort_order      INT UNSIGNED NOT NULL DEFAULT 0,
		created_at      INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at      INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (code)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('translation_strings', "CREATE TABLE translation_strings (
		id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
		hash        CHAR(40) NOT NULL,
		source_text MEDIUMTEXT NOT NULL,
		format      ENUM('text','inline') NOT NULL DEFAULT 'text',
		kind        ENUM('content','seo','ui','option') NOT NULL DEFAULT 'content',
		chars       INT UNSIGNED NOT NULL DEFAULT 0,
		first_seen  INT UNSIGNED NOT NULL DEFAULT 0,
		last_seen   INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uniq_hash (hash)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('translations', "CREATE TABLE translations (
		id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
		string_id  INT UNSIGNED NOT NULL,
		language   VARCHAR(16) NOT NULL,
		text       MEDIUMTEXT NOT NULL,
		engine     VARCHAR(32) NOT NULL DEFAULT 'manual',
		status     ENUM('machine','reviewed') NOT NULL DEFAULT 'machine',
		suspicious TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
		updated_by INT UNSIGNED NOT NULL DEFAULT 0,
		created_at INT UNSIGNED NOT NULL DEFAULT 0,
		updated_at INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY uniq_string_language (string_id, language),
		KEY idx_language_status (language, status)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('translation_uses', "CREATE TABLE translation_uses (
		id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
		string_id      INT UNSIGNED NOT NULL,
		owner_type     VARCHAR(32) NOT NULL,
		owner_id       INT UNSIGNED NOT NULL DEFAULT 0,
		node_id        VARCHAR(64) NOT NULL DEFAULT '',
		field          VARCHAR(64) NOT NULL DEFAULT '',
		position       INT UNSIGNED NOT NULL DEFAULT 0,
		prev_string_id INT UNSIGNED NOT NULL DEFAULT 0,
		seen_at        INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_owner (owner_type, owner_id),
		KEY idx_string (string_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('translation_jobs', "CREATE TABLE translation_jobs (
		id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
		language     VARCHAR(16) NOT NULL,
		engine       VARCHAR(32) NOT NULL DEFAULT 'manual',
		scope        VARCHAR(64) NOT NULL DEFAULT 'all',
		status       ENUM('queued','sent','running','done','failed','cancelled') NOT NULL DEFAULT 'queued',
		total        INT UNSIGNED NOT NULL DEFAULT 0,
		done_count   INT UNSIGNED NOT NULL DEFAULT 0,
		failed_count INT UNSIGNED NOT NULL DEFAULT 0,
		created_by   INT UNSIGNED NOT NULL DEFAULT 0,
		created_at   INT UNSIGNED NOT NULL DEFAULT 0,
		claimed_at   INT UNSIGNED NOT NULL DEFAULT 0,
		finished_at  INT UNSIGNED NOT NULL DEFAULT 0,
		error        TEXT NULL,
		PRIMARY KEY (id),
		KEY idx_status (status, engine)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('translation_job_items', "CREATE TABLE translation_job_items (
		job_id    INT UNSIGNED NOT NULL,
		string_id INT UNSIGNED NOT NULL,
		status    ENUM('waiting','done','failed','skipped') NOT NULL DEFAULT 'waiting',
		error     VARCHAR(255) NOT NULL DEFAULT '',
		PRIMARY KEY (job_id, string_id),
		KEY idx_job_status (job_id, status)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	install_create_table('translation_glossary', "CREATE TABLE translation_glossary (
		id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
		language       VARCHAR(16) NOT NULL DEFAULT '',
		term           VARCHAR(255) NOT NULL,
		translation    VARCHAR(255) NOT NULL DEFAULT '',
		keep           TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
		note           VARCHAR(255) NOT NULL DEFAULT '',
		case_sensitive TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY idx_language (language)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// The drawn body is derived data: it is rebuilt whenever the source page's
	// own drawn body changes (the fingerprint is the hash of page_tree_code)
	// and dropped whenever a translation of the language is written.
	install_create_table('page_translations', "CREATE TABLE page_translations (
		page_id     INT UNSIGNED NOT NULL,
		language    VARCHAR(16) NOT NULL,
		tree_code   MEDIUMTEXT NOT NULL,
		fingerprint CHAR(40) NOT NULL DEFAULT '',
		coverage    TINYINT UNSIGNED NOT NULL DEFAULT 0,
		reviewed    TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
		mt_google   TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
		rendered_at INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (page_id, language)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	// An empty source language means "the panel language" (config.software_language).
	install_add_column('config', 'translation_source_language', "VARCHAR(16) NOT NULL DEFAULT ''");
	// prefix=code pairs, comma separated, of the enabled languages. Written by the
	// languages settings; read by router.php with raw mysqli.
	install_add_column('config', 'translation_prefixes', "TEXT NULL");
	install_add_column('config', 'translation_google_key', "TEXT NULL");
	install_add_column('config', 'translation_style_note', "TEXT NULL");
	install_add_column('config', 'translation_attribution', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1");

	// A language translated on its own when a page is saved: the saved page's
	// pending texts become a job for the language's engine (Pinegrap AI,
	// Claude, Google). Off by default.
	install_add_column('site_languages', 'auto_update', "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0");

	install_note('Front-end translation: pages of a visual design, the catalog and the forms can be served in other languages under a language directory (/en/...), translated by Claude, Pinegrap AI, Google Cloud Translation, the browser (Chrome), by hand or from a CSV file. Settings > Languages and Translation; Translations screen.');

}
