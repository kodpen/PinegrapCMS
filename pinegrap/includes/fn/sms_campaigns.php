<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * SMS campaigns: a text sent to the contacts of chosen contact groups, now or
 * at a time, beside the e-mail campaigns. A campaign is a sms_campaigns row
 * with its groups in contact_groups_sms_campaigns_xref (2026.11.0, 11.0.137).
 * The general job queues a due campaign once: one `marketing` message per
 * number through pg_sms_enqueue(), which refuses a number without SMS
 * consent, one İYS refused and any message while the sender identity is
 * empty, and appends the identity and the type label. Nothing here sends or
 * writes the queue itself.
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
 * Whether the 2026.11.0 upgrade has created the campaign tables.
 *
 * @return bool
 */
function pg_sms_campaigns_ready()
{
    return pg_schema_has('sms_campaigns') && pg_schema_has('contact_groups_sms_campaigns_xref') && pg_sms_queue_ready();
}

/**
 * How long a queued campaign message may wait for its send: a promotion that
 * reaches a phone the next morning, after the queue stood still overnight,
 * is a message nobody asked for at that hour.
 *
 * @return int seconds
 */
function pg_sms_campaign_expiry()
{
    return 6 * 3600;
}

/**
 * The status names a campaign shows.
 *
 * @return array status => label
 */
function pg_sms_campaign_statuses()
{
    return array(
        'draft'     => lang('Draft'),
        'ready'     => lang('Scheduled'),
        'queued'    => lang('Queued for sending'),
        'cancelled' => lang('Cancelled'),
    );
}

/**
 * The queue reference of one campaign message: what makes a second run of
 * the same campaign find the message already queued.
 *
 * @param int $campaign_id
 * @param int $contact_id
 * @return string
 */
function pg_sms_campaign_reference($campaign_id, $contact_id)
{
    return 'smscampaign:' . (int) $campaign_id . ':' . (int) $contact_id;
}

/**
 * The longest campaign text, in characters: the layer's limit
 * (pg_sms_max_length()) less the line break, sender identity and type label
 * pg_sms_check() appends before it applies that limit.
 *
 * @param array $marketing pg_sms_marketing_normalize()
 * @return int
 */
function pg_sms_campaign_body_limit($marketing)
{
    return max(0, pg_sms_max_length() - mb_strlen("\n" . pg_sms_marketing_footer($marketing), 'UTF-8'));
}

/**
 * What the create and edit screen saves; no database.
 *
 * send is 'draft' (kept, never sent), 'now' (ready from now) or 'at'
 * (ready from $start_at, which must lie ahead). A draft may lack a text or a
 * group; a campaign that will be sent needs both. A text longer than
 * $limit (pg_sms_campaign_body_limit()) is refused, a draft's too: the layer
 * would refuse every message of it. A group the user may not pick is
 * dropped, as the e-mail campaign screen drops it.
 *
 * @param array    $post      name, body, send, contact_group_<id>
 * @param array    $groups    ids of the groups the user may pick
 * @param int      $now
 * @param int|null $start_at  the posted time as a timestamp, null when it
 *                            could not be read
 * @param int|null $limit     the longest text, null for no check
 * @return array errors (list of messages), values (name, body, status,
 *               start_at, included, excluded)
 */
function pg_sms_campaign_decide($post, $groups, $now, $start_at, $limit = null)
{
    $errors = array();

    $name = trim((string) ($post['name'] ?? ''));
    $body = trim(str_replace("\r\n", "\n", (string) ($post['body'] ?? '')));
    $send = (string) ($post['send'] ?? 'draft');

    if (function_exists('mb_substr')) {
        $name = mb_substr($name, 0, 100);
    }

    $included = array();
    $excluded = array();

    foreach ($groups as $group_id) {
        $choice = (string) ($post['contact_group_' . (int) $group_id] ?? '');

        if ($choice === 'included') {
            $included[] = (int) $group_id;
        } elseif ($choice === 'excluded') {
            $excluded[] = (int) $group_id;
        }
    }

    $status = 'draft';
    $start = 0;

    // Counted as the layer counts it when the message is checked.
    if (($limit !== null) && (mb_strlen(pg_sms_text($body), 'UTF-8') > (int) $limit)) {
        $errors[] = lang(array('string' => 'The text is longer than {var:1} characters, the room left beside the sender identity and the type label.', 'vars' => array((int) $limit)));
    }

    if ($send === 'now' || $send === 'at') {
        $status = 'ready';

        if ($body === '') {
            $errors[] = lang('Please enter the text of the message.');
        } elseif (!$included) {
            $errors[] = lang('Please include at least one contact group.');
        } elseif ($send === 'now') {
            $start = (int) $now;
        } elseif ($start_at === null) {
            $errors[] = lang('Please enter the date and time to send at.');
        } elseif ((int) $start_at < (int) $now) {
            $errors[] = lang('The date and time to send at has already passed.');
        } else {
            $start = (int) $start_at;
        }
    }

    return array(
        'errors' => $errors,
        'values' => array(
            'name'     => $name,
            'body'     => $body,
            'status'   => $status,
            'start_at' => $start,
            'included' => $included,
            'excluded' => $excluded,
        ),
    );
}

/**
 * The numbers a campaign goes to; no database. One message per number: the
 * first contact of a number keeps it, so its reference stays the same on a
 * second run. A number that a contact of an excluded group has is left out;
 * a phone that is not a mobile number (pg_sms_number()) is skipped.
 *
 * @param array $included rows id, mobile_phone of the included groups
 * @param array $excluded rows id, mobile_phone of the excluded groups
 * @return array number => contact id
 */
function pg_sms_campaign_audience_pick($included, $excluded)
{
    $left_out = array();

    foreach ($excluded as $row) {
        $number = pg_sms_number($row['mobile_phone'] ?? '');

        if ($number !== '') {
            $left_out[$number] = true;
        }
    }

    $audience = array();

    foreach ($included as $row) {
        $number = pg_sms_number($row['mobile_phone'] ?? '');

        if (($number === '') || isset($left_out[$number]) || isset($audience[$number])) {
            continue;
        }

        $audience[$number] = (int) $row['id'];
    }

    return $audience;
}

/**
 * Whether the job should queue the campaign now.
 *
 * @param array $campaign status, start_at
 * @param int   $now
 * @return bool
 */
function pg_sms_campaign_due($campaign, $now)
{
    return (($campaign['status'] ?? '') === 'ready') && ((int) ($campaign['start_at'] ?? 0) <= (int) $now);
}

/**
 * Queues one message per number through the SMS layer's queue, as the
 * marketing kind. A refusal (no consent, refused by İYS, no sender identity,
 * SMS not set up) is counted and the number left, never sent another way.
 * A message already queued by an earlier run counts as queued.
 *
 * @param array    $campaign id, body
 * @param array    $audience number => contact id
 * @param int      $now
 * @param callable $enqueue  pg_sms_enqueue() or a stand-in with its signature
 * @return array queued, refused
 */
function pg_sms_campaign_queue($campaign, $audience, $now, $enqueue = 'pg_sms_enqueue')
{
    $counts = array('queued' => 0, 'refused' => 0);

    foreach ($audience as $number => $contact_id) {
        $result = call_user_func($enqueue, (string) $number, (string) $campaign['body'], 'marketing', array(
            'reference'  => pg_sms_campaign_reference($campaign['id'], $contact_id),
            'expires_at' => (int) $now + pg_sms_campaign_expiry(),
        ));

        if (!empty($result['ok'])) {
            $counts['queued']++;
        } else {
            $counts['refused']++;
        }
    }

    return $counts;
}

/**
 * The contacts of a campaign's groups, as the e-mail campaign picks them: a
 * contact who opted in, and in a subscription group did not opt out of
 * that group, with a mobile number.
 *
 * @param int $campaign_id
 * @return array number => contact id
 */
function pg_sms_campaign_audience($campaign_id)
{
    $rows = array('included' => array(), 'excluded' => array());

    $result = db_q(
        "SELECT contact_group_id, type FROM contact_groups_sms_campaigns_xref WHERE sms_campaign_id = ?",
        array((string) (int) $campaign_id));

    if ($result === false) {
        return array();
    }

    $groups = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $groups[] = $row;
    }

    foreach ($groups as $group) {
        if ($group['type'] === 'included') {
            $result = db_q(
                "SELECT contacts.id, contacts.mobile_phone
                FROM contacts_contact_groups_xref
                LEFT JOIN contact_groups ON contacts_contact_groups_xref.contact_group_id = contact_groups.id
                LEFT JOIN contacts ON contacts_contact_groups_xref.contact_id = contacts.id
                LEFT JOIN opt_in ON (contacts_contact_groups_xref.contact_id = opt_in.contact_id) AND (contacts_contact_groups_xref.contact_group_id = opt_in.contact_group_id)
                WHERE
                    (contacts_contact_groups_xref.contact_group_id = ?)
                    AND (contacts.opt_in = 1)
                    AND ((contact_groups.email_subscription != 1) OR (opt_in.opt_in = 1) OR (opt_in.opt_in IS NULL))
                    AND (contacts.mobile_phone != '')
                ORDER BY contacts.id",
                array((string) (int) $group['contact_group_id']));
            $type = 'included';
        } else {
            $result = db_q(
                "SELECT contacts.id, contacts.mobile_phone
                FROM contacts_contact_groups_xref
                LEFT JOIN contacts ON contacts_contact_groups_xref.contact_id = contacts.id
                WHERE
                    (contacts_contact_groups_xref.contact_group_id = ?)
                    AND (contacts.mobile_phone != '')",
                array((string) (int) $group['contact_group_id']));
            $type = 'excluded';
        }

        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $rows[$type][] = $row;
        }
    }

    return pg_sms_campaign_audience_pick($rows['included'], $rows['excluded']);
}

/**
 * Queues every due campaign; the general job calls it before it sends the
 * SMS queue. A campaign is claimed by moving it from ready to queued first,
 * so two runs at once never queue it twice; a run that dies half way leaves
 * it queued with the messages it reached (each reference is unique).
 *
 * @param int $now
 * @return int campaigns queued
 */
function pg_sms_campaigns_run_due($now = null)
{
    if (!pg_sms_campaigns_ready()) {
        return 0;
    }

    $now = ($now === null) ? time() : (int) $now;

    $result = db_q(
        "SELECT id, body, status, start_at FROM sms_campaigns WHERE status = 'ready' AND start_at <= ? ORDER BY start_at, id LIMIT 5",
        array((string) $now));

    if ($result === false) {
        return 0;
    }

    $campaigns = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $campaigns[] = $row;
    }

    $done = 0;

    foreach ($campaigns as $campaign) {
        if (!pg_sms_campaign_due($campaign, $now)) {
            continue;
        }

        $claim = db_q(
            "UPDATE sms_campaigns SET status = 'queued', queued_at = ? WHERE id = ? AND status = 'ready'",
            array((string) $now, (string) (int) $campaign['id']));

        if (($claim === false) || (mysqli_affected_rows(db::$con) !== 1)) {
            continue;
        }

        $counts = pg_sms_campaign_queue($campaign, pg_sms_campaign_audience($campaign['id']), $now);

        db_q(
            "UPDATE sms_campaigns SET queued_count = ?, refused_count = ? WHERE id = ?",
            array((string) $counts['queued'], (string) $counts['refused'], (string) (int) $campaign['id']));

        log_activity(lang(array(
            'string' => 'SMS campaign queued: {var:1} messages, {var:2} numbers refused by the SMS checks',
            'vars'   => array($counts['queued'], $counts['refused']),
        )), 'UNKNOWN');

        $done++;
    }

    return $done;
}

/**
 * What became of a campaign's queued messages while the queue keeps them
 * (sent rows are swept after 7 days, failed ones after 30).
 *
 * @param int $campaign_id
 * @return array status => count
 */
function pg_sms_campaign_outbox_counts($campaign_id)
{
    $counts = array();

    $result = db_q(
        "SELECT status, COUNT(*) AS number FROM sms_outbox WHERE reference LIKE ? GROUP BY status",
        array('smscampaign:' . (int) $campaign_id . ':%'));

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $counts[$row['status']] = (int) $row['number'];
    }

    return $counts;
}

/**
 * The contact groups a user may pick for a campaign.
 *
 * @param array $user
 * @return array id => name
 */
function pg_sms_campaign_groups($user)
{
    $groups = array();

    $result = db_q("SELECT id, name FROM contact_groups ORDER BY name", array());

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        if (validate_contact_group_access($user, $row['id'])) {
            $groups[(int) $row['id']] = $row['name'];
        }
    }

    return $groups;
}

/**
 * Writes a campaign and its groups.
 *
 * @param int   $id     0 for a new campaign
 * @param array $values pg_sms_campaign_decide() values
 * @param int   $user_id
 * @return int the campaign's id, 0 when it could not be written
 */
function pg_sms_campaign_save($id, $values, $user_id)
{
    $now = (string) time();
    $user_id = (string) (int) $user_id;

    if ($id) {
        $result = db_q(
            "UPDATE sms_campaigns SET name = ?, body = ?, status = ?, start_at = ?, updated_at = ?, updated_by = ?
            WHERE id = ? AND status IN ('draft', 'ready')",
            array($values['name'], $values['body'], $values['status'], (string) $values['start_at'], $now, $user_id, (string) (int) $id));
    } else {
        $result = db_q(
            "INSERT INTO sms_campaigns (name, body, status, start_at, created_at, created_by, updated_at, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            array($values['name'], $values['body'], $values['status'], (string) $values['start_at'], $now, $user_id, $now, $user_id));

        $id = ($result === false) ? 0 : (int) mysqli_insert_id(db::$con);
    }

    if (($result === false) || !$id) {
        return 0;
    }

    db_q("DELETE FROM contact_groups_sms_campaigns_xref WHERE sms_campaign_id = ?", array((string) (int) $id));

    foreach (array('included', 'excluded') as $type) {
        foreach ($values[$type] as $group_id) {
            db_q(
                "INSERT INTO contact_groups_sms_campaigns_xref (contact_group_id, sms_campaign_id, type) VALUES (?, ?, ?)",
                array((string) (int) $group_id, (string) (int) $id, $type));
        }
    }

    return (int) $id;
}
