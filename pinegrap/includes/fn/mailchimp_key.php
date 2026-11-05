<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Mailchimp API key of the Communication settings card. Mailchimp's API
 * host is the key's data center ("us6" in "…-us6"), so only a plain host
 * label is taken from the key: anything else after the dash ("-evil.example/x?")
 * would send the key and the store's customers to another host. The key is
 * never printed back into the settings form: an empty box keeps the stored
 * key and a tick removes it.
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
 * The data center of a Mailchimp API key: the lower-case letters and digits
 * after its last dash, or '' when the key does not end in one.
 *
 * @param mixed $key
 * @return string
 */
function pg_mailchimp_dc($key)
{
    if (!is_string($key)) {
        return '';
    }

    return (preg_match('/^[^\s-][^\s]*-([a-z]{1,10}[0-9]{1,4})$/D', trim($key), $match) === 1) ? $match[1] : '';
}

/**
 * The key a settings save writes: a typed key replaces the stored one, an
 * empty box keeps it, the remove tick clears it.
 *
 * @param mixed  $posted the box as posted
 * @param string $stored config.mailchimp_key
 * @param bool   $clear  the "Remove the stored key" tick
 * @return string
 */
function pg_mailchimp_key_for_save($posted, $stored, $clear)
{
    $posted = is_string($posted) ? trim($posted) : '';

    if ($posted !== '') {
        return $posted;
    }

    return $clear ? '' : (string) $stored;
}
