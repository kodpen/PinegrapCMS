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
 * first copy to everyone: through a CDN or proxy nobody has confirmed keeps a
 * copy per format, both answers go out private, so the edge keeps neither;
 * when the self-test found an edge that keeps private answers too, the
 * original goes until the next test.
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
 * How a picture is answered through a CDN or proxy nobody has confirmed,
 * from the self-test's stored state (includes/fn/image_vary_check.php,
 * same_address.json beside the derivatives): private (negotiated, kept by
 * no shared cache) until a test found an edge that keeps private answers
 * too, then original until the next test.
 *
 * @param array|null $stored the decoded state
 * @return string private, original
 */
function pg_image_same_address_mode($stored)
{
    return (is_array($stored) && isset($stored['mode']) && ($stored['mode'] === 'original'))
        ? 'original'
        : 'private';
}

/**
 * What the System Status line says about WebP at a picture's own address.
 *
 * off: the switch is off (an upgraded site until the operator switches it
 * on). no_webp: on, but this server cannot write WebP, so every reader gets
 * the original. private: on, the panel was reached through a CDN or proxy
 * nobody has confirmed keeps a copy per format, so pictures are negotiated
 * and the edge keeps no copy of them. original: the same, but the self-test
 * found the edge keeps private answers too, so requests through it get the
 * original. on: pictures go out as WebP where the browser reads it, and a
 * CDN in front (confirmed) keeps a copy per format.
 *
 * @param array  $same_address on, cdn => bool (pg_image_delivery_settings())
 * @param bool   $webp_written the server writes WebP (pg_image_derivative_formats())
 * @param bool   $behind_proxy pg_image_behind_proxy() of the panel's request
 * @param string $mode         pg_image_same_address_mode()
 * @return string off, no_webp, private, original, on
 */
function pg_image_same_address_state($same_address, $webp_written, $behind_proxy, $mode = 'private')
{
    if (!is_array($same_address) || empty($same_address['on'])) {
        return 'off';
    }

    if (!$webp_written) {
        return 'no_webp';
    }

    if ($behind_proxy && empty($same_address['cdn'])) {
        return ($mode === 'original') ? 'original' : 'private';
    }

    return 'on';
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
 * Whether the request is the self-test's (includes/fn/image_vary_check.php):
 * ?pg_vary_check= and 16 hex characters, fresh for every test. It is
 * negotiated through a CDN before anyone confirmed the CDN, whatever mode
 * the last test left, since the test is what confirms it. Its own query
 * makes it its own cache entry, so a CDN that ignores Vary hands that copy
 * to no other reader of the picture.
 *
 * @param array $query $_GET
 * @return bool
 */
function pg_image_vary_check_asked($query)
{
    return is_array($query)
        && isset($query['pg_vary_check'])
        && is_string($query['pg_vary_check'])
        && (preg_match('/^[a-f0-9]{16}$/', $query['pg_vary_check']) === 1);
}

/**
 * Whether the self-test asks for the private answer (pg_vary_cache=private
 * beside its token): it shows whether the edge keeps an answer marked
 * private, which the public test address cannot.
 *
 * @param array $query $_GET
 * @return bool
 */
function pg_image_vary_check_private($query)
{
    return pg_image_vary_check_asked($query)
        && isset($query['pg_vary_cache'])
        && ($query['pg_vary_cache'] === 'private');
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
 * on sends exactly what it sent before. private says no shared cache may keep
 * the answer: it came through a CDN or proxy nobody has confirmed keeps a
 * copy per format (bit 8), so whatever the edge does with Vary, it hands
 * nobody a wrong copy. In the original mode (the self-test found the edge
 * keeps private answers too) such a request gets the original, public, as a
 * single copy for everyone.
 *
 * @param array|null $row       the config row
 * @param string     $extension the file's, lower case
 * @param array      $query     $_GET
 * @param array      $server    $_SERVER
 * @param string     $mode      pg_image_same_address_mode()
 * @return array format ('webp', or '' for the original's own), vary (bool),
 *               private (bool)
 */
function pg_image_negotiated_format($row, $extension, $query, $server, $mode = 'private')
{
    $none = array('format' => '', 'vary' => false, 'private' => false);

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

    $private = false;

    if (pg_image_behind_proxy($server) && !$settings['cdn']) {

        if (pg_image_vary_check_asked($query)) {
            $private = pg_image_vary_check_private($query);
        } elseif ($mode === 'original') {
            return array('format' => '', 'vary' => true, 'private' => false);
        } else {
            $private = true;
        }
    }

    $accept = (is_array($server) && isset($server['HTTP_ACCEPT'])) ? $server['HTTP_ACCEPT'] : '';

    return array(
        'format'  => pg_image_accepts($accept, 'image/webp') ? 'webp' : '',
        'vary'    => true,
        'private' => $private);
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

/**
 * One picture address of a mail body asking for the uploaded bytes: a JPEG
 * or PNG of this site gets f=original, everything else is left as it is.
 *
 * Mail programs and the proxies that fetch a mail's pictures may name
 * image/webp and then show the copy where WebP is not drawn, or keep it for
 * every reader. The address is attribute text, so a new query parameter is
 * joined with &amp;.
 *
 * @param string $url  as written in the attribute
 * @param string $host this site's host name (HOSTNAME_SETTING)
 * @return string
 */
function pg_image_mail_original_url($url, $host)
{
    $url = (string) $url;

    // 1 scheme and host as written, 2 the host, 3 the path, 4 the query,
    // 5 the fragment.
    if (!preg_match('~^((?:https?:)?//([^/?#]+))?([^?#]*)(\?[^#]*)?(#.*)?$~i', $url, $parts)) {
        return $url;
    }

    $parts = array_pad($parts, 6, '');

    if ($parts[1] !== '') {
        $own = preg_replace('~^www\.~i', '', strtolower((string) $host));
        $their = preg_replace('~^www\.~i', '', strtolower(preg_replace('~:\d+$~', '', $parts[2])));

        if (($own === '') || ($their !== $own)) {
            return $url;
        }
    } elseif (preg_match('~^[a-z][a-z0-9+.-]*:~i', $parts[3])) {
        // data:, cid:, mailto: and the like.
        return $url;
    }

    if (!preg_match('~\.(?:jpe?g|jpe|png)$~i', $parts[3])) {
        return $url;
    }

    $query = $parts[4];

    if (preg_match('~(?:^\?|&(?:amp;)?)f=~i', $query)) {
        return $url;
    }

    if (($query === '') || ($query === '?')) {
        $query = '?f=original';
    } else {
        $query .= '&amp;f=original';
    }

    return $parts[1] . $parts[3] . $query . $parts[5];
}

/**
 * A mail body with its JPEG and PNG addresses asking for the uploaded bytes
 * (pg_image_mail_original_url()): src and background attributes and url()
 * in style attributes, the places a mail's pictures are written.
 *
 * @param string $html
 * @param string $host this site's host name (HOSTNAME_SETTING)
 * @return string
 */
function pg_image_mail_original_urls($html, $host)
{
    $html = (string) $html;

    if (!preg_match('~\.(?:jpe?g|jpe|png)~i', $html)) {
        return $html;
    }

    $address = function ($url) use ($host) {
        return pg_image_mail_original_url($url, $host);
    };

    return preg_replace_callback('~<[a-z][a-z0-9]*\b[^>]*>~i', function ($tag) use ($address) {

        $tag = preg_replace_callback('~(\s(?:src|background)\s*=\s*)(["\'])(.*?)\2~is', function ($match) use ($address) {
            return $match[1] . $match[2] . $address($match[3]) . $match[2];
        }, $tag[0]);

        return preg_replace_callback('~(\sstyle\s*=\s*)(["\'])(.*?)\2~is', function ($match) use ($address) {
            $style = preg_replace_callback('~(url\(\s*)(&quot;|&#0?39;|["\']?)(.*?)\2(\s*\))~i', function ($url) use ($address) {
                return $url[1] . $url[2] . $address($url[3]) . $url[2] . $url[4];
            }, $match[3]);

            return $match[1] . $match[2] . $style . $match[2];
        }, $tag);

    }, $html);
}
