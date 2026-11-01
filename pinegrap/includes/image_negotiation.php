<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Which format a JPEG or PNG is answered in at its own address.
 *
 * A picture keeps its address and its uploaded file. When the operator has
 * switched it on, a browser whose Accept header names image/webp gets the
 * picture's WebP derivative at that same address, and every other reader
 * (mail programs, feeds, marketplaces fetching by URL) the original. The
 * answer then varies on Accept, so a cache that ignores Vary would hand its
 * first copy to everyone: a request that came through a CDN or proxy gets
 * the original until the operator confirms the edge keeps a copy per format.
 *
 * Pure functions: get_file.php, which never loads functions.php, decides
 * with them on the config row the router already read.
 *
 * CONTRACT - the functions in this file may only call each other and PHP
 * itself.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR') && !defined('PG_GET_FILE')) {
    exit;
}

/**
 * Whether an Accept header names this media type with a q-value above zero.
 *
 * Only the type itself counts: image/* and *\/* are sent by every client,
 * mail programs and feed readers included, and say nothing about what it can
 * draw. q=0 is a refusal. When the type is listed twice, the first entry
 * decides. A q-value that is not a number is not an acceptance.
 *
 * @param string $accept the Accept header
 * @param string $type   lower case, e.g. image/webp
 * @return bool
 */
function pg_image_accepts($accept, $type)
{
    if (!is_string($accept) || ($accept === '')) {
        return false;
    }

    $type = strtolower((string) $type);

    foreach (explode(',', $accept) as $entry) {

        $parameters = explode(';', $entry);

        if (strtolower(trim($parameters[0])) !== $type) {
            continue;
        }

        $q = 1.0;

        for ($i = 1; $i < count($parameters); $i++) {

            $pair = explode('=', $parameters[$i], 2);

            if ((count($pair) === 2) && (strtolower(trim($pair[0])) === 'q')) {

                $value = trim($pair[1]);

                if (!preg_match('/^[0-9]+(\.[0-9]*)?$/', $value)) {
                    return false;
                }

                $q = (float) $value;
            }
        }

        return ($q > 0);
    }

    return false;
}

/**
 * Whether a request came through a CDN or proxy that may keep the answer.
 *
 * The headers such a hop adds on its way to the origin: Cloudflare's CF-Ray
 * and CDN-Loop, the standard Via, CloudFront's X-Amz-Cf-Id.
 *
 * @param array $server $_SERVER
 * @return bool
 */
function pg_image_behind_proxy($server)
{
    if (!is_array($server)) {
        return false;
    }

    foreach (array('HTTP_CF_RAY', 'HTTP_CDN_LOOP', 'HTTP_VIA', 'HTTP_X_AMZ_CF_ID') as $key) {
        if (isset($server[$key]) && is_string($server[$key]) && (trim($server[$key]) !== '')) {
            return true;
        }
    }

    return false;
}

/**
 * The plain-address switches, from the config row.
 *
 * Bits of config.image_derivative_formats beside the formats (1 WebP,
 * 2 AVIF): 4 answers a JPEG or PNG's own address with WebP, and needs 1, as
 * a format switched off is never sent; 8 says the CDN in front keeps a copy
 * per format. A row without the column (before the upgrade) has both off.
 *
 * @param array|null $row the config row
 * @return array on, cdn => bool
 */
function pg_image_same_address_settings($row)
{
    $bits = (is_array($row) && isset($row['image_derivative_formats']) && is_numeric($row['image_derivative_formats']))
        ? (int) $row['image_derivative_formats']
        : 0;

    return array(
        'on'  => (($bits & 5) === 5),
        'cdn' => (($bits & 8) === 8));
}

/**
 * Whether the request asks for the uploaded bytes whatever else it names
 * (?f=original): a download, a mail.
 *
 * @param array $query $_GET
 * @return bool
 */
function pg_image_original_asked($query)
{
    return is_array($query)
        && isset($query['f'])
        && is_string($query['f'])
        && (strtolower(trim($query['f'])) === 'original');
}

/**
 * The format a picture asked for at its own address is answered in.
 *
 * Only a JPEG or PNG asked for without f= (plain or with w=) is negotiated:
 * f= names its own answer, and any other type is sent as it is. The caller
 * leaves out files that get no derivatives at all (ERP documents, workspace
 * files, attachments).
 *
 * vary says the answer depends on Accept (Vary: Accept goes out with it);
 * it is false whenever the switch is off, so a site that has not switched it
 * on sends exactly what it sent before.
 *
 * @param array|null $row       the config row
 * @param string     $extension the file's, lower case
 * @param array      $query     $_GET
 * @param array      $server    $_SERVER
 * @return array format ('webp', or '' for the original's own), vary (bool)
 */
function pg_image_negotiated_format($row, $extension, $query, $server)
{
    $none = array('format' => '', 'vary' => false);

    if (!in_array(strtolower((string) $extension), array('jpg', 'jpeg', 'jpe', 'png'), true)) {
        return $none;
    }

    if (is_array($query) && isset($query['f'])) {
        return $none;
    }

    $settings = pg_image_same_address_settings($row);

    if (!$settings['on']) {
        return $none;
    }

    if (pg_image_behind_proxy($server) && !$settings['cdn']) {
        return array('format' => '', 'vary' => true);
    }

    $accept = (is_array($server) && isset($server['HTTP_ACCEPT'])) ? $server['HTTP_ACCEPT'] : '';

    return array(
        'format' => pg_image_accepts($accept, 'image/webp') ? 'webp' : '',
        'vary'   => true);
}

/**
 * The name a browser saves a picture under when its own address was
 * answered in another format: photo.jpg sent as WebP is photo.webp.
 *
 * @param string $name      the original's file name
 * @param string $extension the format sent
 * @return string
 */
function pg_image_negotiated_download_name($name, $extension)
{
    $base = (string) $name;
    $dot = strrpos($base, '.');

    if (($dot !== false) && ($dot > 0)) {
        $base = substr($base, 0, $dot);
    }

    return $base . '.' . $extension;
}
