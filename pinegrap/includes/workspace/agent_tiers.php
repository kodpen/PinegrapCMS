<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the risk tiers of what an agent does: 1 reading and messages,
 * 2 a reversible record change, 3 an irreversible one (a deletion, money, an
 * order's status, accounting, a user account). A channel's manager allows a
 * tier to an agent on its channel-agent row (agents.php, every tier above 1
 * off by default); a change above the allowed tier stays a proposal the
 * person applies. The tier of a change is read from the catalogue
 * (ws_change_types(), changes.php) and is pure here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

define('WS_TIER_READ', 1);
define('WS_TIER_REVERSIBLE', 2);
define('WS_TIER_IRREVERSIBLE', 3);

/**
 * The kinds every change of which is irreversible: accounting records and
 * user accounts (an e-mail or a role changed reaches the person at once).
 *
 * @return string[]
 */
function ws_change_tier3_types()
{
    return array('erp_account', 'erp_quote', 'user');
}

/**
 * The tier of one record change. A deletion, any money field, an order's
 * status (a cancellation, a completed order) and every change of the kinds of
 * ws_change_tier3_types() are tier 3; any other change of a known kind is
 * tier 2. An unknown kind or action counts as tier 3, so nothing new runs
 * unattended before it is classed. Pure but for the catalogue.
 *
 * @param string   $type   a key of ws_change_types()
 * @param string   $action update, create, delete, add, remove, invoice
 * @param string[] $fields the fields the change writes
 * @param array|null $types the catalogue, ws_change_types() when null
 * @return int 2 or 3
 */
function ws_change_tier($type, $action, $fields = array(), $types = null)
{
    $types = ($types === null) ? ws_change_types() : $types;
    $kind = $types[(string) $type] ?? null;

    if (!$kind || !in_array((string) $action, (array) ($kind['actions'] ?? array()), true)) {
        return WS_TIER_IRREVERSIBLE;
    }

    if (in_array((string) $type, ws_change_tier3_types(), true) || in_array((string) $action, array('delete', 'invoice'), true)) {
        return WS_TIER_IRREVERSIBLE;
    }

    foreach ((array) $fields as $field) {
        $spec = $kind['fields'][(string) $field] ?? null;

        if (!$spec || ($spec[0] === 'money')) {
            return WS_TIER_IRREVERSIBLE;
        }

        if (((string) $type === 'order') && in_array((string) $field, array('status', 'cancellation_reason'), true)) {
            return WS_TIER_IRREVERSIBLE;
        }
    }

    return WS_TIER_REVERSIBLE;
}

/**
 * The tier of a whole answer: its highest change. Pure but for the catalogue.
 *
 * @param array[]    $changes each type, action, fields (field => value, or a list of names)
 * @param array|null $types
 * @return int
 */
function ws_changes_tier($changes, $types = null)
{
    $tier = WS_TIER_READ;

    foreach ((array) $changes as $change) {
        $fields = (array) ($change['fields'] ?? array());
        $names = (array_values($fields) === $fields) ? $fields : array_keys($fields);
        $tier = max($tier, ws_change_tier((string) ($change['type'] ?? ''), (string) ($change['action'] ?? 'update'), $names, $types));
    }

    return $tier;
}

/**
 * Whether an agent allowed a tier in a channel may do what needs another.
 * A row's tier out of range counts as 1. Pure.
 *
 * @param int $allowed the channel-agent row's tier
 * @param int $needed
 * @return bool
 */
function ws_agent_tier_allows($allowed, $needed)
{
    $allowed = (int) $allowed;

    if (($allowed < WS_TIER_READ) || ($allowed > WS_TIER_IRREVERSIBLE)) {
        $allowed = WS_TIER_READ;
    }

    return (int) $needed <= $allowed;
}
