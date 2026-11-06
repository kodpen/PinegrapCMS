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

/**
 * The contacts a storefront door's join put into the group, as
 * pg_announce_contact_group_members() takes them: the contact when the door
 * wrote a new membership row, none when the group is filled by rules or
 * gone, the contact was already in it, or the insert failed.
 *
 * @param int  $contact_id
 * @param int  $group_id
 * @param bool $hand_group    the group exists and a hand door may write to it
 * @param bool $member        the contact was already in the group
 * @param int  $inserted_rows mysqli_affected_rows() of the insert
 * @return int[]
 */
function pg_audience_door_joined($contact_id, $group_id, $hand_group, $member, $inserted_rows)
{
    $contact_id = (int) $contact_id;

    if (($contact_id <= 0) || ((int) $group_id <= 0) || !$hand_group || $member || ((int) $inserted_rows !== 1)) {
        return array();
    }

    return array($contact_id);
}

/**
 * Puts a contact into a group from a storefront door (the registration or
 * membership group of a new account) and announces the join through
 * pg_announce_contact_group_members(), as every other door does, so "joined
 * a group" campaign profiles, flows and webhooks hear a sign-up too. A group
 * filled by rules or gone is skipped in silence; a contact already in it is
 * left as it is and announces nothing.
 *
 * @param int $contact_id
 * @param int $group_id
 * @return bool whether a membership row was written
 */
function pg_audience_door_join($contact_id, $group_id)
{
    $contact_id = (int) $contact_id;
    $group_id = (int) $group_id;

    if (($contact_id <= 0) || ($group_id <= 0)) {
        return false;
    }

    $name = db_value("SELECT name FROM contact_groups WHERE (id = '" . $group_id . "') AND " . pg_audience_hand_sql() . " LIMIT 1");
    $hand_group = ($name !== null) && ($name !== false);

    $member = $hand_group && (bool) db_value(
        "SELECT contact_id FROM contacts_contact_groups_xref
        WHERE (contact_id = '" . $contact_id . "') AND (contact_group_id = '" . $group_id . "') LIMIT 1");

    $inserted = 0;

    if ($hand_group && !$member) {
        db("INSERT INTO contacts_contact_groups_xref (contact_id, contact_group_id)
            VALUES ('" . $contact_id . "', '" . $group_id . "')");
        $inserted = (int) mysqli_affected_rows(db::$con);
    }

    $joined = pg_audience_door_joined($contact_id, $group_id, $hand_group, $member, $inserted);

    if ($joined && function_exists('pg_announce_contact_group_members')) {
        pg_announce_contact_group_members($group_id, $joined, array(), (string) $name);
    }

    return (bool) $joined;
}
