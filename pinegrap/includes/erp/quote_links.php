<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - guest links of sales quotes (erp_quote_links, 2026.11.0 step
 * 11.0.64).
 *
 * A customer without an account opens a quote by link (erp_quote.php), reads
 * it and accepts it with a drawn signature. Only the SHA-256 of the link's
 * token is kept, with its first six characters as a hint; the token itself
 * is shown once, on the quote screen or in the e-mail it goes out in.
 *
 * One live link per quote: issuing a new one revokes the earlier ones. A
 * link runs to the end of the quote's valid_until day at issue, and every
 * open of the page asks the quote again (erp_quote_state(),
 * erp_quote_status_refusal()), so an expired, invoiced, cancelled or
 * rejected quote is never accepted through an old link. Acceptance keeps
 * the signature evidence in erp_signatures and moves the status with the
 * panel's writer (erp_quote_set_status()); never a bare status flip. Rows
 * are never deleted: who issued which link, and when, stays.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

/**
 * Whether the 11.0.64 table is there.
 *
 * @return bool
 */
function erp_quote_links_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('erp_quote_links', 'revoked_at')
            && erp_quotes_ready();
    }

    return $ready;
}

/**
 * Whether a string has the shape of a link token (pg_short_link_new_token():
 * 43 URL-safe base64 characters). Pure.
 *
 * @param mixed $token
 * @return bool
 */
function erp_quote_link_token_shape($token)
{
    return is_string($token) && (preg_match('/^[A-Za-z0-9_-]{43}$/', $token) === 1);
}

/**
 * What is kept of a token: its SHA-256 and its first six characters. Pure.
 *
 * @param string $token
 * @return array hash, hint
 */
function erp_quote_link_token_parts($token)
{
    return array('hash' => hash('sha256', (string) $token), 'hint' => substr((string) $token, 0, 6));
}

/**
 * The last second of the day a quote's valid_until names, in the site's
 * zone; 0 when the quote has no date. Pure but for the zone.
 *
 * @param string $valid_until Y-m-d
 * @return int
 */
function erp_quote_link_expires_at($valid_until)
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $valid_until, $parts) || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
        return 0;
    }

    return (int) mktime(23, 59, 59, (int) $parts[2], (int) $parts[3], (int) $parts[1]);
}

/**
 * Where a link stands now, against the quote as it is now.
 *
 *   revoked   a newer link replaced it, or staff took it back
 *   used      the customer accepted through it
 *   expired   past expires_at
 *   accepted  the quote was accepted another way
 *   closed    the quote is expired, invoiced, cancelled or rejected
 *   open      the customer may accept
 *
 * Pure but for erp_quote_state()'s today.
 *
 * @param array $link   erp_quote_links row
 * @param array $quote  From erp_quote()
 * @param int   $now
 * @return string
 */
function erp_quote_link_state(array $link, array $quote, $now)
{
    if ((int) ($link['revoked_at'] ?? 0) > 0) {
        return 'revoked';
    }

    if ((int) ($link['used_at'] ?? 0) > 0) {
        return 'used';
    }

    $expires = (int) ($link['expires_at'] ?? 0);

    if (($expires > 0) && ((int) $now > $expires)) {
        return 'expired';
    }

    $state = erp_quote_state($quote);

    if ($state === 'accepted') {
        return 'accepted';
    }

    if (($state !== 'open') || (erp_quote_status_refusal($quote, 'accepted') !== '')) {
        return 'closed';
    }

    return 'open';
}

/**
 * The address a token opens.
 *
 * @param string $token
 * @return string
 */
function erp_quote_link_url($token)
{
    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/erp_quote.php?t=' . $token;
}

/**
 * The link row a token names, or null: a wrong shape, an unknown hash or a
 * stored hash that differs all read the same.
 *
 * @param mixed $token
 * @return array|null
 */
function erp_quote_link_for_token($token)
{
    if (!erp_quote_links_ready() || !erp_quote_link_token_shape($token)) {
        return null;
    }

    $parts = erp_quote_link_token_parts($token);
    $row = db_item("SELECT * FROM erp_quote_links WHERE token_hash = '" . escape($parts['hash']) . "' LIMIT 1");

    if (!is_array($row) || !hash_equals((string) $row['token_hash'], $parts['hash'])) {
        return null;
    }

    return $row;
}

/**
 * The quote's live link (neither revoked nor used), newest first, or null.
 *
 * @param int $quote_id
 * @return array|null
 */
function erp_quote_link_live($quote_id)
{
    if (!erp_quote_links_ready()) {
        return null;
    }

    $row = db_item("SELECT l.*, u.user_username AS created_by_name
        FROM erp_quote_links l
        LEFT JOIN user u ON u.user_id = l.created_by
        WHERE l.quote_id = '" . (int) $quote_id . "' AND l.revoked_at = 0 AND l.used_at = 0
        ORDER BY l.id DESC LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * Takes back every live link of a quote. The rows stay.
 *
 * @param int $quote_id
 * @return bool
 */
function erp_quote_link_revoke($quote_id)
{
    if (!erp_quote_links_ready()) {
        return false;
    }

    return db("UPDATE erp_quote_links SET revoked_at = UNIX_TIMESTAMP()
        WHERE quote_id = '" . (int) $quote_id . "' AND revoked_at = 0 AND used_at = 0") !== false;
}

/**
 * Issues a new link for an open quote; the earlier live links are revoked
 * first. The token is returned once and never kept.
 *
 * @param int $quote_id
 * @param int $user_id
 * @return array ['success' => bool, 'error' => string, 'token' => string]
 */
function erp_quote_link_issue($quote_id, $user_id)
{
    $fail = function ($message) {
        return array('success' => false, 'error' => $message, 'token' => '');
    };

    if (!erp_quote_links_ready()) {
        return $fail(lang('Guest links of quotes come with the software update; run the update to use them.'));
    }

    $quote = erp_quote($quote_id);

    if ($quote === null) {
        return $fail(lang('The quote could not be found.'));
    }

    if ((erp_quote_state($quote) !== 'open') || (erp_quote_status_refusal($quote, 'accepted') !== '')) {
        return $fail(lang('Only an open quote can be sent as a guest link.'));
    }

    $token = pg_short_link_new_token();
    $parts = erp_quote_link_token_parts($token);

    erp_quote_link_revoke((int) $quote['id']);

    $written = db("INSERT INTO erp_quote_links SET
            quote_id = '" . (int) $quote['id'] . "',
            token_hash = '" . escape($parts['hash']) . "',
            token_hint = '" . escape($parts['hint']) . "',
            expires_at = '" . erp_quote_link_expires_at((string) $quote['valid_until']) . "',
            created_at = UNIX_TIMESTAMP(),
            created_by = '" . (int) $user_id . "'");

    if ($written === false) {
        return $fail(lang('The guest link could not be made.'));
    }

    log_activity(lang(array('string' => 'erp quote ({var:1}) got a new guest link', 'vars' => (string) $quote['full_number'])), (string) ($_SESSION['sessionusername'] ?? 'ERP'));

    return array('success' => true, 'error' => '', 'token' => $token);
}

/**
 * The customer accepts through a link: the link is claimed (used_at) so a
 * second submit does nothing, the drawing is kept with its evidence, and the
 * quote is accepted by the panel's writer. A refused step releases the
 * claim.
 *
 * @param string $token
 * @param string $png          From pg_signature_png_from_data_url()
 * @param string $signer_name
 * @return array ['success' => bool, 'error' => string]
 */
function erp_quote_link_accept($token, $png, $signer_name)
{
    $fail = function ($message) {
        return array('success' => false, 'error' => $message);
    };

    $link = erp_quote_link_for_token($token);
    $quote = is_array($link) ? erp_quote((int) $link['quote_id']) : null;

    if (($link === null) || ($quote === null) || (erp_quote_link_state($link, $quote, time()) !== 'open')) {
        return $fail(lang('This quote can no longer be accepted through this link.'));
    }

    $refusal = erp_quote_status_refusal($quote, 'accepted');

    if ($refusal !== '') {
        return $fail($refusal);
    }

    if (!is_string($png) || ($png === '')) {
        return $fail(lang('The signature could not be read. Sign again.'));
    }

    $claimed = db("UPDATE erp_quote_links SET used_at = UNIX_TIMESTAMP()
        WHERE id = '" . (int) $link['id'] . "' AND used_at = 0 AND revoked_at = 0");

    if (($claimed === false) || (mysqli_affected_rows(db::$con) !== 1)) {
        return $fail(lang('This quote can no longer be accepted through this link.'));
    }

    $stored = erp_signature_store('quote', (int) $quote['id'], $png, array(
        'signer_name' => $signer_name,
        'document_hash' => erp_signature_document_hash_quote($quote),
        'label' => (string) $quote['full_number'],
        'user_id' => 0,
    ));

    if (!$stored['success']) {
        db("UPDATE erp_quote_links SET used_at = 0 WHERE id = '" . (int) $link['id'] . "'");
        return $fail($stored['error']);
    }

    erp_quote_set_status((int) $quote['id'], 'accepted', 0);
    erp_event_quote((int) $quote['id'], 'erp.quote.signed');

    log_activity(lang(array(
        'string' => 'erp quote ({var:1}) was signed by {var:2} through its guest link',
        'vars' => array((string) $quote['full_number'], (string) $stored['record']['signer_name']),
    )), 'ERP');

    return array('success' => true, 'error' => '');
}
