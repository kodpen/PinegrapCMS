<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema step for version 2026.2.7. Loaded by includes/migrations/runner.php when an
// installation is behind this version; never requested on its own. The function
// must be safe to run twice: every ADD / CREATE asks first and skips what exists.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

function upgrade_to_2026_2_7() {
    // ── IPv6 addresses were being truncated on ban ───────────────────────
    //
    // banned_ip_addresses.ip_address predates IPv6 in this codebase and was
    // sized for a dotted-quad — fifteen characters, exactly the width of
    // 255.255.255.255. An IPv6 address written into it lost everything past
    // the fifteenth character, which produced a compounding failure:
    //
    //   1. waf_auto_ban() looked for an existing row using the FULL address
    //      and found none, because what was stored was the truncated stub.
    //   2. It inserted, and MySQL truncated again.
    //   3. waf_ip_matches() compared the full address against the stub and
    //      returned false, so the ban was never actually enforced.
    //   4. The same client re-offended, and step 1 repeated.
    //
    // The visible symptom was dozens of identical ban rows for one address
    // while that address carried on browsing untouched. Both halves of the
    // bug — the duplicates and the ban doing nothing — are this one column.
    install_modify_column('banned_ip_addresses', 'ip_address', "VARCHAR(45) NOT NULL DEFAULT ''");

    // Automatic bans are disposable by design (an hour by default) and any
    // written before the widening may be truncated stubs that can never match
    // anything. Drop them all; a client that is still attacking earns a new,
    // correct ban within minutes. Operator entries are left alone.
    db("DELETE FROM banned_ip_addresses WHERE source = 'auto'");

    // Collapse any remaining duplicates so the unique key below can be added.
    // Keeps the highest id — the most recently written row.
    db("DELETE b FROM banned_ip_addresses b
        INNER JOIN (
            SELECT ip_address, list_type, source, MAX(id) AS keep_id
            FROM banned_ip_addresses
            GROUP BY ip_address, list_type, source
            HAVING COUNT(*) > 1
        ) d
        ON  b.ip_address = d.ip_address
        AND b.list_type  = d.list_type
        AND b.source     = d.source
        AND b.id        <> d.keep_id");

    // Make duplication structurally impossible rather than relying on the
    // application to check first. With this key in place waf_auto_ban() can
    // be a single atomic INSERT ... ON DUPLICATE KEY UPDATE, which is also
    // race-free: two simultaneous requests from one attacker cannot both find
    // "no existing row" and both insert.
    install_add_index('banned_ip_addresses', 'uniq_entry', "UNIQUE KEY uniq_entry (ip_address, list_type, source)");
}
