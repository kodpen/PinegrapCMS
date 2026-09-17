<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.2.5. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_2_5() {
    // ── Record the visitor's user agent ──────────────────────────────────
    //
    // The visitors table stored the address, referrer and landing page but
    // never the user agent, which made it impossible to answer the one
    // question that matters when the counter disagrees with Google
    // Analytics: which client produced these visits?
    //
    // The prefix index is what makes "group the last day's visits by client"
    // affordable on a table that can take six figures of rows per day.
    //
    // Both statements copy the whole table on MyISAM, so on a big site this
    // is the slow step of the chain; the helpers make sure a second run does
    // not pay for it twice.
    install_add_column('visitors', 'user_agent', "VARCHAR(255) NOT NULL DEFAULT ''");
    install_add_index('visitors', 'idx_user_agent', "INDEX idx_user_agent (user_agent(64))");
}
