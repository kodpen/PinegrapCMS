<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.1.21. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_1_21() {
    // Visual Pinegrap Editor — tree storage + new layout type value.
    // style_tree_json holds the JSON tree edited in Visual Pinegrap Editor; style_code is always
    // the sole render source (generated from the tree on save).
    install_add_column('style', 'style_tree_json', "LONGTEXT DEFAULT NULL");
    install_modify_column('style', 'style_layout', "ENUM('','one_column','one_column_email','one_column_mobile','two_column_sidebar_left','two_column_sidebar_right','three_column_sidebar_left','visual_designer') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''");

    // Shared Component Library — reusable tree nodes referenced across visual-designer styles.
    // A shared_ref node in any style's tree_json points to a row here by id (never by name).
    // name is UNIQUE; duplicate names are silently suffixed ([1], [2]...) at insert time.
    //
    // New-install only: the feature ships with this version, so there is no legacy data to
    // migrate. The placeholder-only invariant (shared_ref.children === []) is enforced at
    // every write site — JS save-path sanitize, server-side save_system_style sanitize, and
    // the marker-emitting _render_tree_node — so inline-expanded shared content can never
    // land in the DB.
    //
    // Note: TEXT/LONGTEXT columns cannot have a DEFAULT value in MySQL strict mode.
    // Only VARCHAR, INT, etc. can have DEFAULT ''. Omit DEFAULT for TEXT/LONGTEXT.
    install_create_table('shared_components', "CREATE TABLE IF NOT EXISTS shared_components (
        id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(255) NOT NULL DEFAULT '',
        description TEXT NOT NULL,
        tree_json   LONGTEXT NOT NULL,
        tree_hash   VARCHAR(64) NOT NULL DEFAULT '',
        category    VARCHAR(100) NOT NULL DEFAULT '',
        created_by  INT UNSIGNED NOT NULL DEFAULT 0,
        created_at  INT UNSIGNED NOT NULL DEFAULT 0,
        updated_at  INT UNSIGNED NOT NULL DEFAULT 0,
        UNIQUE KEY uk_name (name),
        INDEX idx_updated (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
