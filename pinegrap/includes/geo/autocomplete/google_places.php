<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Google Places address autocomplete driver (Places API, New).
 *
 * A suggestion list is one Autocomplete call (POST places:autocomplete) and a
 * pick is one Place Details call (GET places/<id>) asking for the address
 * components only. Both carry the same session token, so Google bills the
 * typing and the pick as one session. The key travels in the X-Goog-Api-Key
 * header and never reaches the browser.
 *
 * A suggestion has no address yet: only its place id, which the layer keeps
 * in the visitor's session so that only a place this site suggested can be
 * looked up.
 *
 * Built from Google's public API documentation:
 * https://developers.google.com/maps/documentation/places/web-service/place-autocomplete
 * https://developers.google.com/maps/documentation/places/web-service/place-details
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
 * Suggestions for what was typed.
 *
 * @param string $text    the typed address, pg_addr_text()
 * @param string $country ISO code, '' for every country
 * @param string $lang    language code
 * @param array  $app     pg_addr_app()
 * @param string $session the session token
 * @return array ok, error, refused, items (id, label, address null)
 */
function pg_addr_google_places_suggest($text, $country, $lang, $app, $session)
{
    $body = array(
        'input'                => (string) $text,
        'languageCode'         => (string) $lang,
        'sessionToken'         => (string) $session,
        'includedPrimaryTypes' => array('street_address', 'premise', 'subpremise', 'route'),
    );

    if ($country !== '') {
        $body['includedRegionCodes'] = array(strtolower($country));
    }

    $answer = pg_addr_http('POST', 'https://places.googleapis.com/v1/places:autocomplete', array(
        'headers' => array('Content-Type: application/json', 'X-Goog-Api-Key: ' . $app['key']),
        'body'    => json_encode($body),
    ));

    if (!$answer['ok']) {
        return $answer + array('items' => array());
    }

    return array('ok' => true, 'error' => '', 'refused' => false, 'items' => pg_addr_google_places_items($answer['data']));
}

/**
 * The suggestions of an Autocomplete answer.
 *
 * @param mixed $data the decoded answer
 * @return array list of id, label, address (null: Place Details fills it)
 */
function pg_addr_google_places_items($data)
{
    $items = array();

    if (!is_array($data) || !isset($data['suggestions']) || !is_array($data['suggestions'])) {
        return $items;
    }

    foreach ($data['suggestions'] as $suggestion) {
        $prediction = (is_array($suggestion) && isset($suggestion['placePrediction']) && is_array($suggestion['placePrediction'])) ? $suggestion['placePrediction'] : null;

        if ($prediction === null) {
            continue;
        }

        $id = isset($prediction['placeId']) ? (string) $prediction['placeId'] : '';
        $label = isset($prediction['text']['text']) ? trim((string) $prediction['text']['text']) : '';

        if (!pg_addr_google_places_id_valid($id) || ($label === '')) {
            continue;
        }

        $items[] = array('id' => $id, 'label' => $label, 'address' => null);
    }

    return $items;
}

/**
 * Whether a string can be a place id: it becomes part of the request path.
 *
 * @param string $id
 * @return bool
 */
function pg_addr_google_places_id_valid($id)
{
    return (bool) preg_match('/^[A-Za-z0-9_-]{10,400}$/D', (string) $id);
}

/**
 * The address of a suggested place.
 *
 * @param string $id      a place id the layer suggested
 * @param string $lang
 * @param array  $app
 * @param string $session the token the suggestions were asked with
 * @return array ok, error, refused, address (pg_addr_empty() shape)
 */
function pg_addr_google_places_fill($id, $lang, $app, $session)
{
    if (!pg_addr_google_places_id_valid($id)) {
        return array('ok' => false, 'error' => 'Unknown place.', 'refused' => false, 'address' => pg_addr_empty());
    }

    $url = 'https://places.googleapis.com/v1/places/' . rawurlencode($id)
        . '?' . http_build_query(array('languageCode' => (string) $lang, 'sessionToken' => (string) $session));

    $answer = pg_addr_http('GET', $url, array(
        'headers' => array('X-Goog-Api-Key: ' . $app['key'], 'X-Goog-FieldMask: addressComponents'),
    ));

    if (!$answer['ok']) {
        return $answer + array('address' => pg_addr_empty());
    }

    return array('ok' => true, 'error' => '', 'refused' => false, 'address' => pg_addr_google_places_address($answer['data']));
}

/**
 * A Place Details answer as one address.
 *
 * Turkey: administrative_area_level_2 is the district (İlçe) and
 * administrative_area_level_1 the province (İl); elsewhere the city is the
 * locality (postal_town in the United Kingdom) and the district the
 * sub-locality.
 *
 * @param mixed $data
 * @return array pg_addr_empty() shape
 */
function pg_addr_google_places_address($data)
{
    $parts = array();

    if (is_array($data) && isset($data['addressComponents']) && is_array($data['addressComponents'])) {
        foreach ($data['addressComponents'] as $component) {
            if (!is_array($component) || !isset($component['types']) || !is_array($component['types'])) {
                continue;
            }

            foreach ($component['types'] as $type) {
                if (is_string($type) && !isset($parts[$type])) {
                    $parts[$type] = array(
                        'long'  => isset($component['longText']) ? trim((string) $component['longText']) : '',
                        'short' => isset($component['shortText']) ? trim((string) $component['shortText']) : '',
                    );
                }
            }
        }
    }

    $long = function ($type) use ($parts) {
        return isset($parts[$type]) ? $parts[$type]['long'] : '';
    };

    $country = isset($parts['country']) ? strtoupper($parts['country']['short']) : '';

    $address = pg_addr_empty();
    $address['street'] = pg_addr_street_line($long('route'), $long('street_number'), $country);
    $address['street_2'] = $long('subpremise');
    $address['city'] = pg_addr_first(array($long('locality'), $long('postal_town'), $long('administrative_area_level_2')));
    $address['district'] = ($country === 'TR')
        ? $long('administrative_area_level_2')
        : pg_addr_first(array($long('sublocality_level_1'), $long('sublocality'), $long('neighborhood')));
    $address['state'] = $long('administrative_area_level_1');
    $address['state_code'] = isset($parts['administrative_area_level_1']) ? $parts['administrative_area_level_1']['short'] : '';
    $address['postcode'] = $long('postal_code');
    $address['country'] = $country;

    return $address;
}
