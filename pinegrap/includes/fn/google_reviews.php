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
            log_activity('Google reviews could not be read: ' . $result['error'], 'SYSTEM');
        }

        $entry['logged'] = true;

        if (!pg_grev_usable($entry, $now)) {
            unset($entry['place'], $entry['fetched_at']);
        }
    }

    $state['cache'][$language] = $entry;

    // A language no page asked for within the ceiling is dropped.
    foreach ($state['cache'] as $code => $kept) {
        if (($code !== $language) && !pg_grev_usable($kept, $now)) {
            unset($state['cache'][$code]);
        }
    }

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
 * The Google Reviews card of Site Settings › Communication: the key (never
 * drawn back), the Place ID and the test button.
 *
 * @return string
 */
function pg_grev_settings_fields()
{
    $ready = function_exists('pg_connect_schema_ready') && pg_connect_schema_ready() && pg_connect_can_seal();
    $state = $ready ? pg_grev_state() : array('key' => '', 'place_id' => '');
    $html = '';

    if (!$ready) {
        $html .= '
                                        <div class="col-12"><div class="alert theme-warning mb-0">' . h(lang('Google reviews need the site\'s encryption key to keep the API key.')) . '</div></div>';
    }

    $kept = ($state['key'] !== '')
        ? lang(array('string' => 'A key ending in {var:1} is saved. Leave the box empty to keep it.', 'vars' => array(h(substr($state['key'], -4)))))
        : lang('The key of a Google Cloud project with billing and the Places API (New) on. Restrict the key to this server.');

    $html .= '
                                        <div class="col-12 form-text">' . h(lang('Shows the rating and up to five reviews of the business from Google in the Google Reviews widget of the Visual Page Editor.')) . '</div>
                                        <div class="pg-f-md">
                                            <label for="google_reviews_key" class="form-label">' . lang('Places API Key') . '</label>
                                            <input type="password" name="google_reviews_key" id="google_reviews_key" class="form-control" value="" maxlength="200" autocomplete="new-password"/>
                                            <div class="form-text">' . $kept . '</div>
                                        </div>
                                        <div class="pg-f-md">
                                            <label for="google_reviews_place_id" class="form-label">' . lang('Place ID') . '</label>
                                            <input type="text" name="google_reviews_place_id" id="google_reviews_place_id" class="form-control" value="' . h($state['place_id']) . '" maxlength="300" autocomplete="off" spellcheck="false"/>
                                            <div class="form-text">' . lang(array(
                                                'string' => 'Find your business with Google\'s {var:1} and copy its Place ID. Empty: the widget shows nothing.',
                                                'vars'   => array('<a href="https://developers.google.com/maps/documentation/places/web-service/place-id" target="_blank" rel="noopener">' . h(lang('Place ID Finder')) . '</a>'))) . '</div>
                                        </div>';

    if ($state['key'] !== '') {
        $html .= '
                                        <div class="col-12">
                                            <div class="form-field mb-0">
                                                <input value="1" class="check" type="checkbox" id="google_reviews_forget" name="google_reviews_forget"/>
                                                <label class="fg-danger" for="google_reviews_forget">' . lang('Remove the saved key') . '</label>
                                            </div>
                                        </div>';
    }

    $html .= '
                                        <div class="col-12">
                                            <button type="submit" name="google_reviews_test" value="1" class="btn btn-sm btn-outline theme-secondary"' . ($ready ? '' : ' disabled') . '><i class="bi bi-google me-1" aria-hidden="true"></i>' . lang('Save and test') . '</button>
                                        </div>';

    return $html;
}

/**
 * Checks the card's fields before anything is written.
 *
 * @param array    $post
 * @param liveform $liveform
 * @return array key (null: keep the saved one), place_id, forget, test
 */
function pg_grev_settings_check($post, $liveform)
{
    $key = isset($post['google_reviews_key']) && is_string($post['google_reviews_key']) ? trim($post['google_reviews_key']) : '';
    $place_id = isset($post['google_reviews_place_id']) && is_string($post['google_reviews_place_id']) ? trim($post['google_reviews_place_id']) : '';

    if (($key !== '') && !preg_match('/^[\x21-\x7e]{1,200}$/', $key)) {
        $liveform->mark_error('google_reviews_key', lang('This is not an API key.'));
    }

    if (($place_id !== '') && !pg_grev_place_id_valid($place_id)) {
        $liveform->mark_error('google_reviews_place_id', lang('This is not a Place ID.'));
    }

    return array(
        'key'      => ($key === '') ? null : $key,
        'place_id' => $place_id,
        'forget'   => !empty($post['google_reviews_forget']),
        'test'     => !empty($post['google_reviews_test']),
    );
}

/**
 * Writes the card and runs its test when asked.
 *
 * @param array    $decision pg_grev_settings_check()
 * @param liveform $liveform
 */
function pg_grev_settings_save($decision, $liveform)
{
    if (!function_exists('pg_connect_schema_ready') || !pg_connect_schema_ready() || !pg_connect_can_seal()) {
        return;
    }

    $state = pg_grev_state();
    $key = $decision['forget'] ? '' : (($decision['key'] !== null) ? $decision['key'] : $state['key']);
    $changed = ($key !== $state['key']) || ($decision['place_id'] !== $state['place_id']);

    if (($key === '') && ($decision['place_id'] === '')) {
        if ($state['key'] !== '' || $state['place_id'] !== '') {
            pg_connect_service_delete('google_reviews');
            pg_grev_state(array('key' => '', 'place_id' => '', 'cache' => array()));
        }
    } elseif ($changed) {
        // Another key or place: nothing read with the old one is shown.
        pg_grev_state_put(array('key' => $key, 'place_id' => $decision['place_id'], 'cache' => array()));
    }

    if (!$decision['test']) {
        return;
    }

    if (!pg_grev_configured()) {
        $liveform->add_notice(lang('Enter the Places API key and the Place ID to test them.'));
        return;
    }

    $state = pg_grev_state();
    $result = pg_grev_fetch($state['key'], $state['place_id'], pg_grev_language());

    if ($result['ok']) {
        $liveform->add_notice(lang(array(
            'string' => 'Google found {var:1}: rated {var:2} from {var:3} ratings, {var:4} reviews to show.',
            'vars'   => array(
                h($result['place']['name']),
                number_format((float) $result['place']['rating'], 1),
                (int) $result['place']['count'],
                count($result['place']['reviews'])))));
    } else {
        $liveform->add_notice(lang(array(
            'string' => 'Google did not answer with the place: {var:1}',
            'vars'   => array(h($result['error'])))));
    }
}
