<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The coordinates of one address from Photon, the geocoder over
 * OpenStreetMap data the address autocomplete already offers
 * (includes/geo/autocomplete/photon.php): the public instance or the host
 * the site runs itself. Photon's results may be kept and drawn on any map
 * with the OpenStreetMap credit; Google's and Mapbox's may not, so no
 * other provider has a call here.
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
 * Asks Photon for the best match of one address.
 *
 * @param string $text the address, one line
 * @param array  $app  pg_addr_app('photon'): host
 * @return array pg_addr_http(): ok, error, refused, status, data
 */
function pg_geocode_photon($text, $app)
{
    $host = pg_addr_photon_host(isset($app['host']) ? $app['host'] : '');

    return pg_addr_http('GET', $host . '/api/?' . http_build_query(array('q' => (string) $text, 'limit' => 1)), array());
}

/**
 * The point of the first feature of a Photon answer. GeoJSON writes the
 * longitude first.
 *
 * @param mixed $data
 * @return array|null array(lat, lng) as floats, null when there is none
 */
function pg_geocode_photon_point($data)
{
    if (!is_array($data) || !isset($data['features'][0]['geometry']['coordinates']) || !is_array($data['features'][0]['geometry']['coordinates'])) {
        return null;
    }

    $coordinates = array_values($data['features'][0]['geometry']['coordinates']);

    if ((count($coordinates) < 2) || !is_numeric($coordinates[0]) || !is_numeric($coordinates[1])) {
        return null;
    }

    $lng = (float) $coordinates[0];
    $lat = (float) $coordinates[1];

    if (($lat < -90) || ($lat > 90) || ($lng < -180) || ($lng > 180)) {
        return null;
    }

    return array($lat, $lng);
}
