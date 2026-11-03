<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The doors that put a contact into a contact group by hand: a staff tick,
 * a bulk organize, an import, a form's or a product's group, a confirmed
 * subscription, the external API and MCP. A group of kind 'dynamic' is
 * filled only by its rules (includes/fn/audiences.php recomputes it), so
 * every door asks here first and leaves such a group alone: a staff door
 * shows pg_audience_door_message(), the API answers 409
 * contact_group_by_rule, a storefront door skips the group in silence.
 *
 * Readers (campaigns, SMS campaigns, the account's groups, exports) read
 * the xref rows as they always did; erasure deletes a contact's rows and
 * recomputes nothing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The SQL test that keeps a contact_groups row a hand door may write to:
 * the kind column once the upgrade has added it, always true before.
 *
 * @param bool   $ready  pg_audience_ready()'s answer
 * @param string $column the kind column, qualified when the query joins
 * @return string
 */
function pg_audience_hand_where($ready, $column = 'kind')
{
    if (!$ready || !preg_match('/^[a-z_]+(\.[a-z_]+)?$/', (string) $column)) {
        return '(1 = 1)';
    }

    return '(' . $column . " <> 'dynamic')";
}

/**
 * The SQL test for the current site (pg_audience_hand_where()).
 *
 * @param string $column
 * @return string
 */
function pg_audience_hand_sql($column = 'kind')
{
    return pg_audience_hand_where(pg_audience_ready(), $column);
}

/**
 * Splits group ids into the ones a hand door may write to and the ones
 * filled by rules. Ids are made whole; zero, negative and repeated ids are
 * dropped; the order sent is kept.
 *
 * @param array $group_ids
 * @param array $dynamic_ids the ids of groups of kind 'dynamic'
 * @return array array('hand' => int[], 'rule' => int[])
 */
function pg_audience_hand_ids(array $group_ids, array $dynamic_ids)
{
    $dynamic = array();

    foreach ($dynamic_ids as $id) {
        $dynamic[(int) $id] = true;
    }

    $out = array('hand' => array(), 'rule' => array());
    $seen = array();

    foreach ($group_ids as $id) {
        if (!is_scalar($id) || !preg_match('/^\s*\d+\s*$/', (string) $id)) {
            continue;
        }

        $id = (int) $id;

        if (($id <= 0) || isset($seen[$id])) {
            continue;
        }

        $seen[$id] = true;
        $out[isset($dynamic[$id]) ? 'rule' : 'hand'][] = $id;
    }

    return $out;
}

/**
 * The ids of every group filled by rules, read once a request; empty
 * before the upgrade or on a failed query.
 *
 * @param bool $fresh read again (after a group's kind changed)
 * @return int[]
 */
function pg_audience_dynamic_group_ids($fresh = false)
{
    static $ids = null;

    if (($ids !== null) && !$fresh) {
        return $ids;
    }

    $ids = array();

    if (!pg_audience_ready()) {
        return $ids;
    }

    $result = mysqli_query(db::$con, "SELECT id FROM contact_groups WHERE kind = 'dynamic'");

    if ($result) {
        while ($row = mysqli_fetch_row($result)) {
            $ids[] = (int) $row[0];
        }
    }

    return $ids;
}

/**
 * Whether a group is filled by rules, so no hand door may write to it.
 *
 * @param int $group_id
 * @return bool
 */
function pg_audience_group_is_dynamic($group_id)
{
    return in_array((int) $group_id, pg_audience_dynamic_group_ids(), true);
}

/**
 * The one message a staff door shows when it leaves a group filled by
 * rules alone.
 *
 * @return string
 */
function pg_audience_door_message()
{
    return lang('A contact group filled by rules cannot be changed by hand; its rules decide who is in it.');
}
