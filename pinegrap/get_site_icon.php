<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The site icon's addresses under the site's root (includes/fn/site_icon.php):
 * /site.webmanifest, /apple-touch-icon.png and /site-icon-<size>.png.
 * router.php sends them here only while a site icon is set, with the asked
 * name in PG_SITE_ICON_ITEM. A PNG size not drawn yet, or drawn from an older
 * picture, is drawn first.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// No session: a browser asks for icons and the manifest without
// credentials, and the answer is the same for every visitor
// (manifest_icon.php does the same).
define('PG_NO_SESSION', true);

$_SESSION = array();

include(dirname(__FILE__) . '/init.php');

$site_icon_size = defined('PG_SITE_ICON_ITEM') ? pg_site_icon_item_size(PG_SITE_ICON_ITEM) : -1;
$site_icon_name = pg_site_icon_name();
$site_icon_kind = pg_site_icon_kind($site_icon_name);
$site_icon_source = ($site_icon_kind !== '') ? pg_site_icon_source_path($site_icon_name) : '';

if (($site_icon_size < 0) || ($site_icon_source === '') || (($site_icon_size > 0) && ($site_icon_kind !== 'png'))) {
    header('HTTP/1.1 404 Not Found');
    header('X-Robots-Tag: noindex');
    exit();
}

$site_icon_stamp = pg_site_icon_stamp($site_icon_name, $site_icon_source);

if ($site_icon_size === 0) {
    $site_icon_body = json_encode(
        pg_site_icon_manifest($site_icon_kind, defined('TITLE') ? TITLE : '', PATH, PATH . encode_url_path(pg_basename($site_icon_name)), $site_icon_stamp),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $site_icon_type = 'application/manifest+json';
} else {
    if (!pg_site_icon_current($site_icon_stamp) && !pg_site_icon_generate($site_icon_name)['ok']) {
        header('HTTP/1.1 404 Not Found');
        exit();
    }

    $site_icon_body = (string) @file_get_contents(pg_site_icon_directory() . '/icon-' . $site_icon_size . '.png');
    $site_icon_type = 'image/png';
}

// The addresses carry no version of their own (a browser asks for
// /apple-touch-icon.png by name), so a day's cache and a validator.
$site_icon_tag = '"' . substr($site_icon_stamp, 0, 16) . '-' . $site_icon_size . '"';

header('Content-Type: ' . $site_icon_type);
header('Cache-Control: public, max-age=86400');
header('ETag: ' . $site_icon_tag);
header('X-Content-Type-Options: nosniff');

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && (trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) === $site_icon_tag)) {
    header('HTTP/1.1 304 Not Modified');
    exit();
}

header('Content-Length: ' . strlen($site_icon_body));
echo $site_icon_body;
exit();
