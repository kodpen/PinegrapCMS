<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Who is told about the visitor journey's scores: customer.hot once a
 * contact's interest score reaches the site's threshold (again only after it
 * fell below and rose), customer.returned when a hot contact starts a new
 * visit (at most once a day a contact), and the staff's "Hot contacts"
 * operations alert once a day. Called from the scoring pass
 * (pg_lead_score_contact(), pg_lead_score_run()), never in a visitor's
 * request. An event carries the contact, its scores, reason codes and
 * interests, never the pages seen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** customer.returned is announced at most once in this many seconds a contact. */
const PG_LEAD_RETURNED_EVERY = 86400;

/** The alert names at most this many hot contacts. */
const PG_LEAD_ALERT_NAMED = 3;

/**
 * What a new score tells, against the contact's row before it: hot when the
 * contact reaches the threshold and was not hot (or had no row); returned
 * when a contact hot before and now starts a visit newer than the last one
 * seen, begun within a day, and was not told so in the last day. Pure.
 *
 * @param array|null $old       the row before: hot, last_visit_at, returned_at; null for none
 * @param int        $hot       1 when the new interest score reaches the threshold
 * @param int        $last_visit the newest visit's start now
 * @param int        $now
 * @return array{hot: bool, returned: bool}
 */
function pg_lead_tell_moves($old, $hot, $last_visit, $now)
{
    $was_hot = is_array($old) && ((int) ($old['hot'] ?? 0) === 1);
    $returned = $was_hot && (int) $hot === 1
        && ((int) $last_visit > (int) ($old['last_visit_at'] ?? 0))
        && ((int) $last_visit >= (int) $now - PG_LEAD_RETURNED_EVERY)
        && ((int) $now - (int) ($old['returned_at'] ?? 0) >= PG_LEAD_RETURNED_EVERY);

    return array('hot' => ((int) $hot === 1) && !$was_hot, 'returned' => $returned);
}

/**
 * The data of customer.hot and customer.returned: the contact, its interest
 * and fit scores, the site's threshold, the interest's reason codes, its
 * interests by name (the first as topic) and the newest visit's start (UTC).
 * Pure.
 *
 * @param array $row       contact_id, interest, fit, reasons (the interest's
 *                         list of {kind}), topics (list of {name}), last_visit_at
 * @param int   $threshold
 * @return array
 */
function pg_lead_event_payload(array $row, $threshold)
{
    $reasons = array();

    foreach ((array) ($row['reasons'] ?? array()) as $reason) {
        if (isset($reason['kind']) && ((string) $reason['kind'] !== '')) {
            $reasons[] = (string) $reason['kind'];
        }
    }

    $topics = array();

    foreach ((array) ($row['topics'] ?? array()) as $topic) {
        if (isset($topic['name']) && ((string) $topic['name'] !== '')) {
            $topics[] = (string) $topic['name'];
        }
    }

    $last = (int) ($row['last_visit_at'] ?? 0);

    return array(
        'id' => (int) ($row['contact_id'] ?? 0),
        'score' => (int) ($row['interest'] ?? 0),
        'fit' => (int) ($row['fit'] ?? 0),
        'threshold' => (int) $threshold,
        'reasons' => $reasons,
        'topic' => $topics ? $topics[0] : '',
        'topics' => $topics,
        'last_visit_at' => $last ? gmdate('Y-m-d\TH:i:s\Z', $last) : null,
    );
}

/**
 * Announces what a contact's new score told (pg_lead_tell_moves()) and
 * keeps when it was told returned. Silent without the events module.
 *
 * @param array $moves     pg_lead_tell_moves()
 * @param array $row       as pg_lead_event_payload() takes it
 * @param int   $threshold
 * @param int   $now
 */
function pg_lead_tell(array $moves, array $row, $threshold, $now)
{
    if (!empty($moves['returned'])) {
        db("UPDATE contact_scores SET returned_at = '" . (int) $now . "' WHERE contact_id = '" . (int) $row['contact_id'] . "'");
    }

    if (!function_exists('pg_announce')) {
        return;
    }

    if (!empty($moves['hot'])) {
        pg_announce('customer.hot', pg_lead_event_payload($row, $threshold));
    }

    if (!empty($moves['returned'])) {
        pg_announce('customer.returned', pg_lead_event_payload($row, $threshold));
    }
}

/**
 * The "Hot contacts" alert's sentence: how many turned hot in the last day
 * and the first names. Pure but for lang().
 *
 * @param int      $count
 * @param string[] $names
 * @return string
 */
function pg_lead_alert_detail($count, array $names)
{
    $count = (int) $count;
    $names = array_slice(array_values(array_filter(array_map('strval', $names), 'strlen')), 0, PG_LEAD_ALERT_NAMED);
    $sentence = lang(array('string' => '{var:1} contact(s) became hot in the last day', 'vars' => array($count)));

    if ($names) {
        $sentence .= ': ' . implode(', ', $names);
    }

    $sentence .= '.';

    if ($names && ($count > count($names))) {
        $sentence .= ' ' . lang(array('string' => 'And {var:1} more.', 'vars' => array($count - count($names))));
    }

    return $sentence;
}

/**
 * Where the day's alert starts counting: the last alert, so a contact that
 * turned hot after it but more than a day before the next pass that may send
 * again is not left out, and one it counted is not counted again; the last
 * day when none was sent, two days at most. Pure.
 *
 * @param int $last_sent unix time of the last contacts_hot alert, 0 for never
 * @param int $now
 * @return int contacts with hot_at after this are counted
 */
function pg_lead_alert_since($last_sent, $now)
{
    $last_sent = (int) $last_sent;
    $now = (int) $now;

    if (($last_sent <= 0) || ($last_sent > $now)) {
        return $now - 86400;
    }

    return max($last_sent, $now - 172800);
}

/**
 * Tells the staff once a day of the contacts that became hot since the last
 * alert (pg_ops_alert(), code contacts_hot; pg_lead_alert_since()); nothing
 * when none did or the day's alert is already out.
 *
 * @param int $now
 */
function pg_lead_alert_send($now)
{
    if (!function_exists('pg_ops_alert') || !pg_ops_alerts_enabled()) {
        return;
    }

    $last_sent = pg_ops_alert_last_sent('contacts_hot');

    if (!pg_ops_alert_due($last_sent, (int) $now)) {
        return;
    }

    $since = pg_lead_alert_since($last_sent, (int) $now);
    $count = (int) db_value("SELECT COUNT(*) FROM contact_scores WHERE hot = 1 AND hot_at > '" . $since . "'");

    if ($count === 0) {
        return;
    }

    $names = array();

    foreach ((array) db_items("SELECT c.first_name, c.last_name, c.email_address FROM contact_scores s
        INNER JOIN contacts c ON c.id = s.contact_id
        WHERE s.hot = 1 AND s.hot_at > '" . $since . "'
        ORDER BY s.interest DESC, s.contact_id LIMIT " . PG_LEAD_ALERT_NAMED) as $contact) {
        $name = trim($contact['first_name'] . ' ' . $contact['last_name']);
        $names[] = ($name !== '') ? $name : (string) $contact['email_address'];
    }

    pg_ops_alert('contacts_hot', pg_lead_alert_detail($count, $names));
}
