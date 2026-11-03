<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Mapbox address autocomplete driver (Geocoding API v6, forward search
 * with autocomplete).
 *
 * One call answers the suggestions with their whole address (the feature's
 * context), so a pick needs no second call: the layer keeps the addresses in
 * the visitor's session and fills from there. The access token travels in
 * the query string of a server-side request and never reaches the browser.
 *
 * Built from Mapbox's public API documentation:
 * https://docs.mapbox.com/api/search/geocoding/
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
 * @param string $text
 * @param string $country ISO code, '' for every country
 * @param string $lang
 * @param array  $app     pg_addr_app()
 * @param string $session unused: Mapbox's forward search has no session
 * @return array ok, error, refused, items (id, label, address)
 */
function pg_addr_mapbox_suggest($text, $country, $lang, $app, $session)
{
    $query = array(
        'q'            => (string) $text,
        'autocomplete' => 'true',
        'types'        => 'address,street',
        'limit'        => PG_ADDR_SUGGESTIONS,
        'language'     => (string) $lang,
        'access_token' => (string) $app['key'],
    );

    if ($country !== '') {
        $query['country'] = strtolower($country);
    }

    $answer = pg_addr_http('GET', 'https://api.mapbox.com/search/geocode/v6/forward?' . http_build_query($query), array());

    if (!$answer['ok']) {
        return $answer + array('items' => array());
    }

    return array('ok' => true, 'error' => '', 'refused' => false, 'items' => pg_addr_mapbox_items($answer['data']));
}

/**
 * The suggestions of a forward search answer.
 *
 * @param mixed $data
 * @return array list of id, label, address
 */
function pg_addr_mapbox_items($data)
{
    $items = array();

    if (!is_array($data) || !isset($data['features']) || !is_array($data['features'])) {
        return $items;
    }

    foreach ($data['features'] as $feature) {
        $properties = (is_array($feature) && isset($feature['properties']) && is_array($feature['properties'])) ? $feature['properties'] : null;

        if ($properties === null) {
            continue;
        }

        $address = pg_addr_mapbox_address($properties);
        $label = isset($properties['full_address']) ? trim((string) $properties['full_address']) : '';

        if ($label === '') {
            $label = pg_addr_label($address);
        }

        if (($address['street'] === '') || ($label === '')) {
            continue;
        }

        $items[] = array('id' => '', 'label' => $label, 'address' => $address);
    }

    return $items;
}

/**
 * One feature's properties as one address.
 *
 * Turkey: the place is the district (İlçe) and the region the province (İl).
 * Elsewhere the place is the city and the locality or neighbourhood the
 * district.
 *
 * @param array $properties
 * @return array pg_addr_empty() shape
 */
function pg_addr_mapbox_address($properties)
{
    $context = (isset($properties['context']) && is_array($properties['context'])) ? $properties['context'] : array();

    $name = function ($level, $key = 'name') use ($context) {
        return (isset($context[$level][$key]) && is_scalar($context[$level][$key])) ? trim((string) $context[$level][$key]) : '';
    };

    $country = strtoupper($name('country', 'country_code'));

    $street = pg_addr_first(array($name('address', 'street_name'), $name('street')));

    // A street feature has no address context: its own name is the street.
    if (($street === '') && isset($properties['feature_type']) && ($properties['feature_type'] === 'street') && isset($properties['name'])) {
        $street = trim((string) $properties['name']);
    }

    $address = pg_addr_empty();
    $address['street'] = pg_addr_street_line($street, $name('address', 'address_number'), $country);
    $address['city'] = $name('place');
    $address['district'] = ($country === 'TR')
        ? $name('place')
        : pg_addr_first(array($name('locality'), $name('neighborhood')));
    $address['state'] = $name('region');
    $address['state_code'] = $name('region', 'region_code');
    $address['postcode'] = $name('postcode');
    $address['country'] = $country;

    return $address;
}
