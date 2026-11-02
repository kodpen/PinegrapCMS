<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Photon address autocomplete driver: a geocoder over OpenStreetMap
 * data, the public instance at photon.komoot.io by default or a host the
 * site runs itself. It needs no key.
 *
 * One call answers the suggestions with their whole address, so a pick needs
 * no second call. Photon has no country filter, so a few more results are
 * asked for and those of other countries dropped here. The public instance
 * speaks German, English and French; any other language asks for the local
 * names ("default"), which is what an address in that country is written in.
 *
 * Built from Photon's public documentation: https://github.com/komoot/photon
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
 * @param array  $app     pg_addr_app(): host
 * @param string $session unused
 * @return array ok, error, refused, items (id, label, address)
 */
function pg_addr_photon_suggest($text, $country, $lang, $app, $session)
{
    $host = pg_addr_photon_host(isset($app['host']) ? $app['host'] : '');

    $query = array(
        'q'     => (string) $text,
        'limit' => ($country !== '') ? 15 : PG_ADDR_SUGGESTIONS,
        'lang'  => in_array($lang, array('de', 'en', 'fr'), true) ? $lang : 'default',
    );

    $answer = pg_addr_http('GET', $host . '/api/?' . http_build_query($query), array());

    if (!$answer['ok']) {
        return $answer + array('items' => array());
    }

    return array('ok' => true, 'error' => '', 'refused' => false, 'items' => pg_addr_photon_items($answer['data'], $country));
}

/**
 * The host a stored value names: an https address without a trailing slash,
 * the public instance when it is empty or not one.
 *
 * @param string $host
 * @return string
 */
function pg_addr_photon_host($host)
{
    $host = rtrim(trim((string) $host), '/');

    if (!pg_addr_photon_host_valid($host)) {
        return PG_ADDR_PHOTON_HOST;
    }

    return $host;
}

/**
 * The suggestions of a Photon answer: the features of the country asked
 * for, with a street.
 *
 * @param mixed  $data
 * @param string $country ISO code, '' for every country
 * @return array list of id, label, address (at most PG_ADDR_SUGGESTIONS)
 */
function pg_addr_photon_items($data, $country)
{
    $items = array();
    $seen = array();

    if (!is_array($data) || !isset($data['features']) || !is_array($data['features'])) {
        return $items;
    }

    foreach ($data['features'] as $feature) {
        $properties = (is_array($feature) && isset($feature['properties']) && is_array($feature['properties'])) ? $feature['properties'] : null;

        if ($properties === null) {
            continue;
        }

        $address = pg_addr_photon_address($properties);

        if (($address['street'] === '') || (($country !== '') && ($address['country'] !== $country))) {
            continue;
        }

        $label = pg_addr_label($address);

        // OpenStreetMap often has a street once per way segment.
        if (isset($seen[$label])) {
            continue;
        }

        $seen[$label] = true;
        $items[] = array('id' => '', 'label' => $label, 'address' => $address);

        if (count($items) >= PG_ADDR_SUGGESTIONS) {
            break;
        }
    }

    return $items;
}

/**
 * One feature's properties as one address.
 *
 * Turkey: OpenStreetMap's county is the district (İlçe) and the state the
 * province (İl); a city that is not the province is a district as well.
 *
 * @param array $properties
 * @return array pg_addr_empty() shape
 */
function pg_addr_photon_address($properties)
{
    $value = function ($key) use ($properties) {
        return (isset($properties[$key]) && is_scalar($properties[$key])) ? trim((string) $properties[$key]) : '';
    };

    $country = strtoupper($value('countrycode'));
    $street = $value('street');

    if (($street === '') && ($value('type') === 'street')) {
        $street = $value('name');
    }

    $address = pg_addr_empty();
    $address['street'] = pg_addr_street_line($street, $value('housenumber'), $country);
    $address['state'] = $value('state');
    $address['postcode'] = $value('postcode');
    $address['country'] = $country;

    if ($country === 'TR') {
        $city = ($value('city') !== $address['state']) ? $value('city') : '';
        $address['district'] = pg_addr_first(array($value('county'), $city));
        $address['city'] = pg_addr_first(array($value('city'), $address['district']));
    } else {
        $address['city'] = pg_addr_first(array($value('city'), $value('county')));
        $address['district'] = $value('district');
    }

    return $address;
}
