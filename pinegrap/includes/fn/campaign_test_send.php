<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * "Send a test to me" on Edit Campaign: the campaign's stored e-mail sent to
 * the signed-in user and up to five typed addresses, its subject marked as
 * a test, merge fields filled from the user's own contact (or sample values),
 * through email() as a campaign mail, so the campaign mail server is the one
 * tried. A test is never counted in the campaign: no recipient row, no
 * reference code, no bounce reference. Each user may send a limited number
 * of tests an hour. A stored body that still holds a sample photo of a ready
 * design is pointed out before it is sent.
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
 * How many addresses a test may go to beside the user's own.
 *
 * @return int
 */
function pg_campaign_test_max_addresses()
{
    return 5;
}

/**
 * How many tests a user may send an hour (each press of the button is one,
 * whatever the number of addresses).
 *
 * @return int
 */
function pg_campaign_test_hourly_limit()
{
    return 10;
}

/**
 * The addresses a test goes to: the user's own first, then the typed ones
 * (split on commas, semicolons, spaces and line breaks), each once. Pure.
 *
 * @param string $typed what was typed in "Also send to"
 * @param string $own   the signed-in user's address ('' for none)
 * @return array addresses (list), error ('' when it may be sent)
 */
function pg_campaign_test_addresses($typed, $own)
{
    $out = array();
    $seen = array();
    $own = trim((string) $own);

    if ($own !== '' && validate_email_address($own)) {
        $out[] = $own;
        $seen[strtolower($own)] = true;
    }

    $typed_count = 0;

    foreach (preg_split('/[\s,;]+/', (string) $typed, -1, PREG_SPLIT_NO_EMPTY) as $address) {
        if (!validate_email_address($address)) {
            return array('addresses' => array(), 'error' => lang(array('string' => 'This is not an e-mail address: {var:1}', 'vars' => array($address))));
        }

        if (isset($seen[strtolower($address)])) {
            continue;
        }

        $seen[strtolower($address)] = true;
        $typed_count++;

        if ($typed_count > pg_campaign_test_max_addresses()) {
            return array('addresses' => array(), 'error' => lang(array('string' => 'A test goes to you and at most {var:1} more addresses.', 'vars' => array(pg_campaign_test_max_addresses()))));
        }

        $out[] = $address;
    }

    if (!$out) {
        return array('addresses' => array(), 'error' => lang('Your user has no e-mail address: type one to send the test to.'));
    }

    return array('addresses' => $out, 'error' => '');
}

/**
 * The subject of a test: the campaign's, marked.
 *
 * @param string $subject
 * @return string
 */
function pg_campaign_test_subject($subject)
{
    return lang(array('string' => '[Test] {var:1}', 'vars' => array((string) $subject)));
}

/**
 * How many pictures of a body are sample photos, the stand-ins the ready
 * designs and palette blocks carry (picsum.photos, placehold.co). Pure.
 *
 * @param string $html
 * @return int
 */
function pg_campaign_test_sample_photos($html)
{
    return (int) preg_match_all('#\ssrc\s*=\s*["\']?https?://(?:[a-z0-9-]+\.)?(?:picsum\.photos|placehold\.co)/#i', (string) $html);
}

/**
 * The merge fields of a test: the user's own contact's names, or the sample
 * values the previews show when the user has no contact.
 *
 * @param int $user_id
 * @return array fields for replace_variables(), and the name for the mail
 */
function pg_campaign_test_fields($user_id)
{
    $row = db_item(
        "SELECT contacts.first_name, contacts.last_name, contacts.nickname
        FROM user
        INNER JOIN contacts ON contacts.id = user.user_contact
        WHERE user.user_id = '" . (int) $user_id . "' LIMIT 1"
    );
    $first = is_array($row) ? trim((string) $row['first_name']) : '';
    $last = is_array($row) ? trim((string) $row['last_name']) : '';

    if ($first === '' && $last === '') {
        $first = lang('Jane');
        $last = lang('Doe');
    }

    $nickname = is_array($row) ? trim((string) $row['nickname']) : '';
    $name = ($nickname !== '') ? $nickname : trim($first . ' ' . $last);

    return array(
        'name'   => $name,
        'fields' => array(
            array('name' => 'name', 'data' => $name, 'type' => ''),
            array('name' => 'first_name', 'data' => $first, 'type' => ''),
            array('name' => 'last_name', 'data' => $last, 'type' => ''),
        ),
    );
}

/**
 * Sends the test. The campaign row is given without its id, so the mail
 * carries no bounce reference and nothing is counted against the campaign;
 * the web version and preferences links of the stored body lead nowhere in
 * a test, as no recipient was made for it.
 *
 * @param array $campaign  the campaign row: subject, body, format, purpose,
 *                         from_name, from_email_address, reply_email_address
 * @param array $addresses pg_campaign_test_addresses()['addresses']
 * @param int   $user_id
 * @return array sent (count), failed (list of addresses)
 */
function pg_campaign_test_send(array $campaign, array $addresses, $user_id)
{
    $merge = pg_campaign_test_fields($user_id);
    $format = ((string) ($campaign['format'] ?? '') === 'plain_text') ? 'plain_text' : 'html';
    $subject = pg_campaign_test_subject(replace_variables(array('content' => (string) $campaign['subject'], 'fields' => $merge['fields'], 'format' => 'plain_text')));
    $body = replace_variables(array('content' => (string) $campaign['body'], 'fields' => $merge['fields'], 'format' => $format));
    $body = str_replace(array('<reference_code></reference_code>', '<email_address_id></email_address_id>'), '', $body);
    unset($campaign['id'], $campaign['email_campaign_id'], $campaign['bcc_email_address']);
    $sent = 0;
    $failed = array();

    foreach ($addresses as $address) {
        if (email(pg_email_campaign_mail_properties($campaign, $address, $merge['name'], $subject, $body))) {
            $sent++;
        } else {
            $failed[] = $address;
        }
    }

    return array('sent' => $sent, 'failed' => $failed);
}
