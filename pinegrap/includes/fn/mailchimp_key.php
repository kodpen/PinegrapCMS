<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Mailchimp API key of the Communication settings card. Mailchimp's API
 * host is the key's data center ("us6" in "…-us6"), so only a plain host
 * label is taken from the key: anything else after the dash ("-evil.example/x?")
 * would send the key and the store's customers to another host. The key is
 * never printed back into the settings form: an empty box keeps the stored
 * key and a tick removes it. A refused request's log line and error mail
 * list the request with the key replaced by its data center, and the key is
 * masked in whatever else the message carries.
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

/**
 * A request as a refused request's log line and error mail list it: the key
 * a settings save passes is replaced by its data center ("****-us6").
 *
 * @param array $request the mailchimp_request() argument
 * @return array
 */
function pg_mailchimp_request_for_log($request)
{
    if (!is_array($request) || !array_key_exists('key', $request)) {
        return $request;
    }

    $dc = pg_mailchimp_dc($request['key']);
    $request['key'] = ($dc === '') ? '****' : '****-' . $dc;

    return $request;
}

/**
 * The text with the key masked in every form it could take there: plain
 * (a response echoing it), URL-encoded, Base64 and the Basic auth value
 * ("key:<key>") cURL sends.
 *
 * @param string $text
 * @param mixed  $key
 * @return string
 */
function pg_mailchimp_mask_key($text, $key)
{
    $text = (string) $text;
    $key = is_string($key) ? $key : '';

    if ($key === '') {
        return $text;
    }

    $forms = array();

    foreach (array($key, trim($key)) as $value) {
        if ($value === '') {
            continue;
        }
        $forms[] = base64_encode('key:' . $value);
        $forms[] = base64_encode($value);
        $forms[] = rawurlencode($value);
        $forms[] = urlencode($value);
        $forms[] = $value;
    }

    // Longest first, so a shorter form never cuts a longer one in half.
    $forms = array_unique($forms);
    usort($forms, function ($a, $b) {
        return strlen($b) - strlen($a);
    });

    return str_replace($forms, '****', $text);
}

/**
 * The message a refused Mailchimp request logs and mails: the heading (what
 * Mailchimp refused, or the cURL error), the response when there is one and
 * the request, never the key.
 *
 * @param string     $heading
 * @param array|null $response the decoded answer, null when none came
 * @param array      $request  the mailchimp_request() argument
 * @param mixed      $key      the key the request was sent with
 * @return string
 */
function pg_mailchimp_error_message($heading, $response, $request, $key)
{
    $message = $heading . "\n";

    if ($response !== null) {
        $message .= lang('Response') . ': ' . print_r($response, true) . "\n";
    }

    $message .= lang('Request') . ': ' . print_r(pg_mailchimp_request_for_log($request), true);

    return pg_mailchimp_mask_key($message, $key);
}
