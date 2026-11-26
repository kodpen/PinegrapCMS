<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Google reviews: the rating and up to five reviews of the business's Google
 * place, read with a Places API key through Place Details (Places API (New)).
 * Read-only: nothing is written to Google and no account is connected.
 *
 * The key, the Place ID and the last answer per language are one sealed
 * service entry (pg_connect_service_put('google_reviews', …)), so the site
 * needs its encryption key and no column of its own. Google's terms let a
 * site keep the Place ID; the rest of the answer is kept only as a short
 * cache: fresh for PG_GREV_FRESH, shown after a failed read until
 * PG_GREV_KEEP, then dropped. The general job re-reads what is due; a page
 * reads itself only when nothing usable is kept.
 *
 * The widget that draws it: includes/fn/widgets_google_reviews.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Seconds an answer counts as fresh before the job reads it again.
define('PG_GREV_FRESH', 3600);
// Seconds a kept answer may still be shown when reading again fails.
define('PG_GREV_KEEP', 86400);
// Seconds between two tries after a failed read.
define('PG_GREV_RETRY', 600);

/**
 * The fields asked for: everything the widget draws and nothing more, as the
 * field mask decides what Google bills.
 *
 * @return string
 */
function pg_grev_field_mask()
{
    return 'id,displayName,rating,userRatingCount,reviews,googleMapsUri';
}

/**
 * The Place Details request.
 *
 * @param string $key      the Places API key
 * @param string $place_id
 * @param string $language a language code ('' lets Google choose)
 * @return array|null url, headers; null when the key or the Place ID cannot
 *                    be sent
 */
function pg_grev_request($key, $place_id, $language)
{
    $key = trim((string) $key);
    $place_id = trim((string) $place_id);

    if (($key === '') || !preg_match('/^[\x21-\x7e]{1,200}$/', $key) || !pg_grev_place_id_valid($place_id)) {
        return null;
    }

    $url = 'https://places.googleapis.com/v1/places/' . $place_id;

    if (preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', (string) $language)) {
        $url .= '?languageCode=' . $language;
    }

    return array(
        'url'     => $url,
        'headers' => array(
            'X-Goog-Api-Key: ' . $key,
            'X-Goog-FieldMask: ' . pg_grev_field_mask(),
            'Accept: application/json',
        ),
    );
}

/**
 * @param string $place_id
 * @return bool
 */
function pg_grev_place_id_valid($place_id)
{
    return (bool) preg_match('/^[A-Za-z0-9_-]{10,300}$/', (string) $place_id);
}

/**
 * An address Google gave for a link or a picture: https only.
 *
 * @param mixed $url
 * @return string
 */
function pg_grev_url($url)
{
    $url = is_string($url) ? trim($url) : '';

    return preg_match('#^https://[^\s"<>]+$#i', $url) ? $url : '';
}

/**
 * Reads a Place Details answer.
 *
 * @param string $body the answer's JSON
 * @return array|null name, rating (float), count, maps_url, reviews (at most
 *                    five: author, author_url, photo, rating, text, relative,
 *                    published, url); null for anything but a place
 */
function pg_grev_parse($body)
{
    $data = json_decode((string) $body, true);

    if (!is_array($data) || isset($data['error']) || !isset($data['displayName']['text'])) {
        return null;
    }

    $reviews = array();

    foreach ((isset($data['reviews']) && is_array($data['reviews'])) ? $data['reviews'] : array() as $review) {
        if (!is_array($review)) {
            continue;
        }

        $author = (isset($review['authorAttribution']) && is_array($review['authorAttribution'])) ? $review['authorAttribution'] : array();
        $text = isset($review['text']['text']) ? $review['text']['text'] : (isset($review['originalText']['text']) ? $review['originalText']['text'] : '');

        $reviews[] = array(
            'author'     => isset($author['displayName']) ? (string) $author['displayName'] : '',
            'author_url' => pg_grev_url(isset($author['uri']) ? $author['uri'] : ''),
            'photo'      => pg_grev_url(isset($author['photoUri']) ? $author['photoUri'] : ''),
            'rating'     => isset($review['rating']) ? (int) $review['rating'] : 0,
            'text'       => (string) $text,
            'relative'   => isset($review['relativePublishTimeDescription']) ? (string) $review['relativePublishTimeDescription'] : '',
            'published'  => isset($review['publishTime']) ? (string) $review['publishTime'] : '',
            'url'        => pg_grev_url(isset($review['googleMapsUri']) ? $review['googleMapsUri'] : ''),
        );

        // Google returns five; never more are drawn whatever it sends.
        if (count($reviews) === 5) {
            break;
        }
    }

    return array(
        'name'     => (string) $data['displayName']['text'],
        'rating'   => isset($data['rating']) ? round((float) $data['rating'], 1) : 0.0,
        'count'    => isset($data['userRatingCount']) ? (int) $data['userRatingCount'] : 0,
        'maps_url' => pg_grev_url(isset($data['googleMapsUri']) ? $data['googleMapsUri'] : ''),
        'reviews'  => $reviews,
    );
}

/**
 * Whether a language's kept answer should be read again.
 *
 * @param array|null $entry fetched_at, tried_at
 * @param int        $now
 * @return bool
 */
function pg_grev_due($entry, $now)
{
    if (!is_array($entry)) {
        return true;
    }

    $fetched = isset($entry['fetched_at']) ? (int) $entry['fetched_at'] : 0;
    $tried = isset($entry['tried_at']) ? (int) $entry['tried_at'] : 0;

    return ($now - $fetched > PG_GREV_FRESH) && ($now - $tried > PG_GREV_RETRY);
}

/**
 * Whether a kept answer may be shown.
 *
 * @param array|null $entry fetched_at, place
 * @param int        $now
 * @return bool
 */
function pg_grev_usable($entry, $now)
{
    return is_array($entry) && !empty($entry['place']) && is_array($entry['place'])
        && isset($entry['fetched_at']) && ($now - (int) $entry['fetched_at'] <= PG_GREV_KEEP);
}

/**
 * The stored settings and cache, read once per request.
 *
 * @param array|null $replace the state just written, kept as the request's copy
 * @return array key, place_id, cache (language => fetched_at, tried_at,
 *               logged, place)
 */
function pg_grev_state($replace = null)
{
    static $state = null;

    if (is_array($replace)) {
        $state = $replace;
    } elseif ($state === null) {
        $stored = function_exists('pg_connect_service_get') ? pg_connect_service_get('google_reviews') : array();
        $state = array(
            'key'      => isset($stored['key']) ? (string) $stored['key'] : '',
            'place_id' => isset($stored['place_id']) ? (string) $stored['place_id'] : '',
            'cache'    => (isset($stored['cache']) && is_array($stored['cache'])) ? $stored['cache'] : array(),
        );
    }

    return $state;
}

/**
 * @param array $state pg_grev_state()
 * @return bool
 */
function pg_grev_state_put($state)
{
    pg_grev_state($state);

    return pg_connect_service_put('google_reviews', 'Google reviews', $state);
}

/**
 * @return bool a key and a Place ID are kept
 */
function pg_grev_configured()
{
    $state = pg_grev_state();

    return ($state['key'] !== '') && ($state['place_id'] !== '');
}

/**
 * The language the reviews are asked in: the page's.
 *
 * @return string
 */
function pg_grev_language()
{
    if (defined('FRONTEND_LANGUAGE')) {
        return (string) FRONTEND_LANGUAGE;
    }

    return function_exists('pg_tr_source_language') ? pg_tr_source_language() : '';
}

/**
 * Asks Google.
 *
 * @param string $key
 * @param string $place_id
 * @param string $language
 * @return array ok, place, error
 */
function pg_grev_fetch($key, $place_id, $language)
{
    $request = pg_grev_request($key, $place_id, $language);

    if ($request === null) {
        return array('ok' => false, 'place' => null, 'error' => lang('The API key or the Place ID is missing or not valid.'));
    }

    $answer = pg_curl_run('GET', $request['url'], array(
        'headers'       => $request['headers'],
        'timeout'       => 10,
        'cap'           => 262144,
        'check_address' => true,
    ));
    $place = $answer['ok'] ? pg_grev_parse($answer['body']) : null;

    if ($place !== null) {
        return array('ok' => true, 'place' => $place, 'error' => '');
    }

    $data = json_decode((string) $answer['body'], true);
    $error = isset($data['error']['message']) ? (string) $data['error']['message']
        : (($answer['error'] !== '') ? $answer['error'] : 'HTTP ' . (int) $answer['status']);

    return array('ok' => false, 'place' => null, 'error' => $error);
}

/**
 * The kept languages after a read of $language: a language with a usable
 * answer stays, and so does one whose last try is within PG_GREV_KEEP, as
 * its try time holds back the next read (PG_GREV_RETRY) and its logged mark
 * the next log line. Dropping a failing language here would let each page
 * of it ask Google, and log the failure, again. Anything else, no page
 * having asked for it within the ceiling, is dropped.
 *
 * @param array  $cache    language => entry
 * @param string $language the language just read, always kept
 * @param int    $now
 * @return array
 */
function pg_grev_cache_prune(array $cache, $language, $now)
{
    foreach ($cache as $code => $kept) {
        if ((string) $code === (string) $language) {
            continue;
        }

        $tried = (is_array($kept) && isset($kept['tried_at'])) ? (int) $kept['tried_at'] : 0;

        if (!pg_grev_usable($kept, $now) && ($now - $tried > PG_GREV_KEEP)) {
            unset($cache[$code]);
        }
    }

    return $cache;
}

/**
 * Reads a language again and keeps the answer. A failure keeps the last good
 * copy and is logged once until a read works again.
 *
 * @param string $language
 * @return bool
 */
function pg_grev_refresh($language)
{
    $state = pg_grev_state();

    if (!pg_grev_configured()) {
        return false;
    }

    $now = time();
    $entry = isset($state['cache'][$language]) && is_array($state['cache'][$language]) ? $state['cache'][$language] : array();
    // Marked first, so that requests arriving meanwhile do not ask as well.
    $entry['tried_at'] = $now;
    $state['cache'][$language] = $entry;
    pg_grev_state_put($state);

    $result = pg_grev_fetch($state['key'], $state['place_id'], $language);

    if ($result['ok']) {
        $entry = array('fetched_at' => $now, 'tried_at' => $now, 'logged' => false, 'place' => $result['place']);
    } else {
        if (empty($entry['logged']) && function_exists('log_activity')) {
            log_activity(lang(array('string' => 'Google reviews could not be read: {var:1}', 'vars' => array($result['error']))), 'SYSTEM');
        }

        $entry['logged'] = true;

        if (!pg_grev_usable($entry, $now)) {
            unset($entry['place'], $entry['fetched_at']);
        }
    }

    $state['cache'][$language] = $entry;
    $state['cache'] = pg_grev_cache_prune($state['cache'], $language, $now);

    pg_grev_state_put($state);

    return $result['ok'];
}

/**
 * The place to draw for this page's language, read now when nothing usable
 * is kept.
 *
 * @return array|null pg_grev_parse()
 */
function pg_grev_place()
{
    if (!pg_grev_configured()) {
        return null;
    }

    $language = pg_grev_language();
    $state = pg_grev_state();
    $entry = isset($state['cache'][$language]) ? $state['cache'][$language] : null;
    $now = time();

    if (!pg_grev_usable($entry, $now) && pg_grev_due($entry, $now)) {
        pg_grev_refresh($language);
        $state = pg_grev_state();
        $entry = isset($state['cache'][$language]) ? $state['cache'][$language] : null;
    }

    return pg_grev_usable($entry, $now) ? $entry['place'] : null;
}

/**
 * The general job's pass: every kept language that is due.
 */
function pg_grev_job_run()
{
    if (!pg_grev_configured()) {
        return;
    }

    $state = pg_grev_state();
    $now = time();

    foreach ($state['cache'] as $language => $entry) {
        if (pg_grev_due($entry, $now)) {
            pg_grev_refresh((string) $language);
        }
    }
}

/**
 * The address of Google reviews' page on Connections, where the key and the
 * Place ID are typed, returning to the Google Reviews card.
 *
 * @return string
 */
function pg_grev_connections_url()
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');

    return (defined('PATH') ? PATH : '') . (defined('SOFTWARE_DIRECTORY') ? SOFTWARE_DIRECTORY : '')
        . '/' . pg_connections_lead_url('google_reviews', 'api', 'pgset-google-reviews');
}

/**
 * The Google Reviews card of Site Settings › Integrations: no key, only the
 * connection's state and the way to its page on Connections. Pure.
 *
 * @param bool $connected pg_grev_configured()
 * @return string HTML
 */
function pg_grev_settings_connection_html($connected)
{
    return '
                                        <div class="col-12 form-text">' . h(lang('Shows the rating and up to five reviews of the business from Google in the Google Reviews widget of the Page Design Studio.')) . '</div>
                                        <div class="col-12" id="pgsub-google-reviews-connection">
                                            <div class="d-flex flex-column align-items-start gap-2">
                                                <div class="d-flex flex-wrap align-items-center gap-3"><span class="fw-semibold">' . h(lang('Google reviews connection')) . '</span>' . ($connected
                                                    ? '<span class="pg-pill pg-tone-teal">' . h(lang('Connected')) . '</span>'
                                                    : '<span class="pg-pill pg-tone-gray">' . h(lang('Not connected')) . '</span>') . '</div>
                                                <div class="form-text mt-0">' . h($connected
                                                    ? lang('The Places API key and the Place ID are kept on the Google reviews page of Connections.')
                                                    : lang('Type the Places API key and the Place ID on the Google reviews page of Connections; until then the widget shows nothing.')) . '</div>
                                                <a class="pg-button pg-button-quiet pg-button-sm" href="' . h(pg_grev_connections_url()) . '">' . h($connected ? lang('Manage in Connections') : lang('Connect in Connections')) . '</a>
                                            </div>
                                        </div>';
}

/**
 * What a save on Google reviews' page of Connections does: the key (an
 * empty box keeps the stored one) and the Place ID are both needed, as a
 * connection without either reads nothing. Pure.
 *
 * @param array $post   google_reviews_key, google_reviews_place_id, submit_try
 * @param array $stored key, place_id (pg_grev_state())
 * @return array marks (field => message), key, place_id (what to keep),
 *               changed (bool: the cache is emptied), try (bool)
 */
function pg_grev_page_decide($post, $stored)
{
    $key = (isset($post['google_reviews_key']) && is_string($post['google_reviews_key'])) ? trim($post['google_reviews_key']) : '';
    $place_id = (isset($post['google_reviews_place_id']) && is_string($post['google_reviews_place_id'])) ? trim($post['google_reviews_place_id']) : '';
    $stored_key = isset($stored['key']) ? (string) $stored['key'] : '';
    $stored_place = isset($stored['place_id']) ? (string) $stored['place_id'] : '';
    $marks = array();

    if (($key === '') && ($stored_key === '')) {
        $marks['google_reviews_key'] = lang('Type the Places API key.');
    } elseif (($key !== '') && !preg_match('/^[\x21-\x7e]{1,200}$/', $key)) {
        $marks['google_reviews_key'] = lang('This is not an API key.');
    }

    if ($place_id === '') {
        $marks['google_reviews_place_id'] = lang('Type the Place ID.');
    } elseif (!pg_grev_place_id_valid($place_id)) {
        $marks['google_reviews_place_id'] = lang('This is not a Place ID.');
    }

    $kept_key = ($key !== '') ? $key : $stored_key;

    return array(
        'marks'    => $marks,
        'key'      => $kept_key,
        'place_id' => $place_id,
        'changed'  => ($kept_key !== $stored_key) || ($place_id !== $stored_place),
        'try'      => !empty($post['submit_try']),
    );
}

/**
 * Writes what pg_grev_page_decide() kept and runs its try when asked.
 *
 * @param array    $decision pg_grev_page_decide() without marks
 * @param liveform $liveform
 * @return bool written (or nothing to write)
 */
function pg_grev_page_save($decision, $liveform)
{
    if (!function_exists('pg_connect_schema_ready') || !pg_connect_schema_ready() || !pg_connect_can_seal()) {
        $liveform->add_error(lang('Google reviews need the site\'s encryption key to keep the API key.'));
        return false;
    }

    // Another key or place: nothing read with the old one is shown.
    if ($decision['changed'] && !pg_grev_state_put(array('key' => $decision['key'], 'place_id' => $decision['place_id'], 'cache' => array()))) {
        $liveform->add_error(lang('The connection could not be saved.'));
        return false;
    }

    if ($decision['try']) {
        $result = pg_grev_fetch($decision['key'], $decision['place_id'], pg_grev_language());

        if ($result['ok']) {
            $liveform->add_notice(lang(array(
                'string' => 'Google found {var:1}: rated {var:2} from {var:3} ratings, {var:4} reviews to show.',
                'vars'   => array(
                    h($result['place']['name']),
                    number_format((float) $result['place']['rating'], 1),
                    (int) $result['place']['count'],
                    count($result['place']['reviews'])))));
        } else {
            $liveform->add_error(lang(array(
                'string' => 'Google did not answer with the place: {var:1}',
                'vars'   => array(h($result['error'])))));
        }
    }

    return true;
}

/**
 * Disconnect on the page: the key, the Place ID and the cache are deleted.
 */
function pg_grev_page_remove()
{
    if (function_exists('pg_connect_service_delete')) {
        pg_connect_service_delete('google_reviews');
    }

    pg_grev_state(array('key' => '', 'place_id' => '', 'cache' => array()));
}
