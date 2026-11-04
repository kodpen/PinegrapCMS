<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The recipient rule of Create Campaign (add_email_campaign.php): which
 * contacts of an included contact group a campaign takes, and the two counts
 * the contact group list shows. A commercial campaign takes contacts opted in
 * globally and, for a subscription group, to that group; a transactional
 * campaign takes every contact of the group that has an address, as the
 * automatic campaign profiles and the send path already treat it. Hard and
 * soft bounces are held back at sending for every purpose.
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
 * Whether a campaign of this purpose goes only to opted-in contacts. Only
 * the transactional purpose skips the opt-in; anything else is treated as
 * commercial, so an unknown value never widens the list.
 *
 * @param mixed $purpose
 * @return bool
 */
function pg_email_campaign_opt_in_required($purpose)
{
    return $purpose !== 'transactional';
}

/**
 * The opt-in conditions of the commercial rule, joined with AND.
 *
 * @param bool $subscription_group the group's email_subscription is on
 * @return string
 */
function pg_email_campaign_opt_in_condition($subscription_group)
{
    $condition = '(contacts.opt_in = 1)';

    if ($subscription_group) {
        $condition .= ' AND ((opt_in.opt_in = 1) OR (opt_in.opt_in IS NULL))';
    }

    return $condition;
}

/**
 * FROM and JOIN part shared by the recipient and the count queries.
 *
 * @param bool $join_opt_in join the per-group opt_in rows
 * @return string
 */
function pg_email_campaign_recipient_from($join_opt_in)
{
    $from = 'FROM contacts_contact_groups_xref
        LEFT JOIN contacts ON contacts_contact_groups_xref.contact_id = contacts.id';

    if ($join_opt_in) {
        $from .= '
        LEFT JOIN opt_in ON (contacts_contact_groups_xref.contact_id = opt_in.contact_id) AND (contacts_contact_groups_xref.contact_group_id = opt_in.contact_group_id)';
    }

    return $from;
}

/**
 * The query selecting id and address of the recipients of one included
 * group; its one placeholder is the contact group id.
 *
 * @param mixed $purpose            the campaign's purpose
 * @param bool  $subscription_group the group's email_subscription is on
 * @return string
 */
function pg_email_campaign_recipient_query($purpose, $subscription_group)
{
    $opt_in = pg_email_campaign_opt_in_required($purpose);
    $subscription_group = (bool) $subscription_group;

    $where = '(contacts_contact_groups_xref.contact_group_id = ?)';

    if ($opt_in) {
        $where .= ' AND ' . pg_email_campaign_opt_in_condition($subscription_group);
    }

    $where .= " AND (contacts.email_address != '')";

    return 'SELECT contacts.id, contacts.email_address
        ' . pg_email_campaign_recipient_from($opt_in && $subscription_group) . '
        WHERE ' . $where;
}

/**
 * The query counting one group's contacts with an address (total) and those
 * of them the commercial rule takes (opted_in); its one placeholder is the
 * contact group id.
 *
 * @param bool $subscription_group the group's email_subscription is on
 * @return string
 */
function pg_email_campaign_group_count_query($subscription_group)
{
    $subscription_group = (bool) $subscription_group;

    return 'SELECT
            COUNT(contacts.id) AS total,
            SUM(CASE WHEN ' . pg_email_campaign_opt_in_condition($subscription_group) . ' THEN 1 ELSE 0 END) AS opted_in
        ' . pg_email_campaign_recipient_from($subscription_group) . "
        WHERE (contacts_contact_groups_xref.contact_group_id = ?) AND (contacts.email_address != '')";
}

/**
 * A contact group's name with its counts, escaped for HTML: "Orders (23,
 * opted in 3)", or "News (5)" when every contact is opted in. Both counts
 * are printed so a change of purpose needs no reload.
 *
 * @param string $name
 * @param mixed  $total
 * @param mixed  $opted_in
 * @return string
 */
function pg_email_campaign_group_label($name, $total, $opted_in)
{
    $total = (int) $total;
    $opted_in = (int) $opted_in;

    if ($opted_in === $total) {
        $counts = pg_format_number($total, 0);
    } else {
        $counts = lang(array('string' => '{var:1}, opted in {var:2}', 'vars' => array(pg_format_number($total, 0), pg_format_number($opted_in, 0))));
    }

    return h($name) . ' (' . h($counts) . ')';
}
