<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Where the bounce record is shown: the delivered, suppressed, bounced and
 * complained counts of each campaign send, the Suppressed contact view and
 * the contacts export's status column, the badge on the contact card with
 * its "Allow again" button. The SQL builders are pure; the record itself and
 * the send gate are in mail_bounces.php.
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
 * The name of a bounce kind as the panel shows it.
 *
 * @param string $kind hard, soft, complaint, manual
 * @return string
 */
function pg_mail_bounce_kind_label($kind)
{
    switch ((string) $kind) {
        case 'hard':
            return lang('Hard bounce');
        case 'soft':
            return lang('Repeated soft bounces');
        case 'complaint':
            return lang('Spam complaint');
        case 'manual':
            return lang('Blocked by staff');
    }

    return '';
}

/**
 * A condition true when the address in this column is suppressed. The
 * column's collation compares without case, as mail_bounces keeps the
 * address lower-cased. Pure.
 *
 * @param string $column an SQL column, written by the caller
 * @return string
 */
function pg_mail_bounce_suppressed_sql($column)
{
    return '(EXISTS (SELECT 1 FROM mail_bounces WHERE (mail_bounces.address = ' . $column . ') AND (mail_bounces.suppressed = 1)))';
}

/**
 * A select expression holding the kind that suppresses the address in this
 * column, NULL when it is not suppressed. Pure.
 *
 * @param string $column an SQL column, written by the caller
 * @return string
 */
function pg_mail_bounce_status_sql($column)
{
    return '(SELECT mail_bounces.kind FROM mail_bounces WHERE (mail_bounces.address = ' . $column . ') AND (mail_bounces.suppressed = 1) LIMIT 1)';
}

/**
 * The campaign ids as a list for IN (), '' when none is a positive id. Pure.
 *
 * @param array $ids
 * @return string
 */
function pg_mail_bounce_campaign_id_list(array $ids)
{
    $clean = array();

    foreach ($ids as $id) {
        $id = (int) $id;

        if ($id > 0) {
            $clean[$id] = $id;
        }
    }

    return implode(', ', $clean);
}

/**
 * The recipients' counts of these campaigns in one query: delivered
 * (complete and, with the retry columns, not failed) and suppressed (skipped
 * by the send gate: failed with no attempt). '' when no id is left. Pure.
 *
 * @param array $ids         campaign ids
 * @param bool  $retry_ready pg_email_retry_ready(): failed and attempts exist
 * @return string
 */
function pg_mail_bounce_campaign_counts_sql(array $ids, $retry_ready)
{
    $list = pg_mail_bounce_campaign_id_list($ids);

    if ($list === '') {
        return '';
    }

    if ($retry_ready) {
        $delivered = "SUM((complete = '1') AND (failed = '0'))";
        $suppressed = "SUM((complete = '1') AND (failed = '1') AND (attempts = 0))";
    } else {
        $delivered = "SUM(complete = '1')";
        $suppressed = '0';
    }

    return 'SELECT email_campaign_id, ' . $delivered . ' AS delivered, ' . $suppressed . ' AS suppressed'
        . ' FROM email_recipients'
        . ' WHERE email_campaign_id IN (' . $list . ')'
        . ' GROUP BY email_campaign_id';
}

/**
 * The bounces and complaints that came back for these campaigns, from the
 * bounce record: each address counts once, under the campaign of the last
 * message it bounced. '' when no id is left. Pure.
 *
 * @param array $ids campaign ids
 * @return string
 */
function pg_mail_bounce_campaign_bounces_sql(array $ids)
{
    $list = pg_mail_bounce_campaign_id_list($ids);

    if ($list === '') {
        return '';
    }

    return "SELECT campaign_id, SUM(kind IN ('hard', 'soft')) AS bounced, SUM(kind = 'complaint') AS complained"
        . ' FROM mail_bounces'
        . ' WHERE campaign_id IN (' . $list . ')'
        . ' GROUP BY campaign_id';
}

/**
 * The four counts of each campaign, id => delivered, suppressed, bounced,
 * complained; zeros for a campaign without rows.
 *
 * @param array $ids
 * @return array
 */
function pg_mail_bounce_campaign_counts(array $ids)
{
    $counts = array();

    foreach ($ids as $id) {
        $counts[(int) $id] = array('delivered' => 0, 'suppressed' => 0, 'bounced' => 0, 'complained' => 0);
    }

    $query = pg_mail_bounce_campaign_counts_sql($ids, pg_email_retry_ready());

    if ($query !== '') {
        foreach (db_items($query) as $row) {
            $id = (int) $row['email_campaign_id'];
            $counts[$id]['delivered'] = (int) $row['delivered'];
            $counts[$id]['suppressed'] = (int) $row['suppressed'];
        }
    }

    $query = pg_mail_bounces_ready() ? pg_mail_bounce_campaign_bounces_sql($ids) : '';

    if ($query !== '') {
        foreach (db_items($query) as $row) {
            $id = (int) $row['campaign_id'];

            if (isset($counts[$id])) {
                $counts[$id]['bounced'] = (int) $row['bounced'];
                $counts[$id]['complained'] = (int) $row['complained'];
            }
        }
    }

    return $counts;
}

/**
 * The delivery cell of a campaign row: the delivered count, then a badge for
 * each of suppressed, bounced and complained that is not zero.
 *
 * @param array $counts one entry of pg_mail_bounce_campaign_counts()
 * @return string HTML
 */
function pg_mail_bounce_campaign_cell(array $counts)
{
    $cell = h(lang(array('string' => '{var:1} delivered', 'vars' => array(pg_format_number((int) ($counts['delivered'] ?? 0), 0)))));

    $badges = array(
        'suppressed' => 'theme-secondary',
        'bounced'    => 'theme-warning',
        'complained' => 'theme-danger',
    );

    foreach ($badges as $key => $theme) {
        $count = (int) ($counts[$key] ?? 0);

        if ($count <= 0) {
            continue;
        }

        $vars = array(pg_format_number($count, 0));

        if ($key === 'suppressed') {
            $text = lang(array('string' => '{var:1} suppressed', 'vars' => $vars));
        } elseif ($key === 'bounced') {
            $text = lang(array('string' => '{var:1} bounced back', 'vars' => $vars));
        } else {
            $text = lang(array('string' => '{var:1} complained', 'vars' => $vars));
        }

        $cell .= ' <span class="badge ' . $theme . '">' . h($text) . '</span>';
    }

    return $cell;
}

/**
 * The badge the contact card shows while its address is suppressed, with
 * the "Allow again" button that posts the card's form; '' when the address
 * is not suppressed.
 *
 * @param array|null $row pg_mail_bounce_row()'s answer
 * @return string HTML
 */
function pg_mail_bounce_contact_badge($row)
{
    if (!pg_mail_bounce_blocks($row)) {
        return '';
    }

    $text = lang(array('string' => 'No e-mail is sent to this address: {var:1}', 'vars' => array(pg_mail_bounce_kind_label($row['kind'] ?? ''))));
    $last = (int) ($row['last_at'] ?? 0);

    return '
                                        <div class="col-12 my-1 d-flex flex-wrap align-items-center gap-3">
                                            <span class="badge theme-danger">' . h(lang('Suppressed')) . '</span>
                                            <span class="fg-secondary">' . h($text) . (($last > 0) ? ' · ' . get_relative_time(array('timestamp' => $last)) : '') . '</span>
                                            <button type="submit" name="submit_allow_again" value="1" formnovalidate class="btn btn-sm btn-outline theme-primary">' . h(lang('Allow again')) . '</button>
                                        </div>';
}

/**
 * Lift the block on a contact's stored address from the contact card. The
 * address is read from the contact, never posted; the clear writes the
 * activity line.
 *
 * @param int $contact_id
 * @return bool whether a block was lifted
 */
function pg_mail_bounce_allow_contact($contact_id)
{
    $address = (string) db_value("SELECT email_address FROM contacts WHERE id = '" . (int) $contact_id . "'");

    return ($address !== '') && pg_mail_bounce_clear($address);
}
