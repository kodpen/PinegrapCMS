<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The site's own icon on the front end: the favicon a browser tab and a
 * search result show, the apple-touch-icon and the web manifest.
 *
 * The operator names one square picture of the Files screen (Site Settings,
 * SEO, Site Icon), kept as the settings text 'site_icon' (config_text: the
 * config row has no room for another column). A PNG is drawn by the image
 * engine (pg_process_image_file()) at the sizes pg_site_icon_sizes() lists,
 * into data/site_icon/ beside the file directory, with a stamp of the source
 * so a picture replaced in the Files screen is drawn again on the next
 * request. An SVG is linked at its own address and draws itself at any size.
 * router.php hands /site.webmanifest, /apple-touch-icon.png and
 * /site-icon-<size>.png to get_site_icon.php while the setting is filled;
 * with it empty nothing is linked or answered, as before.
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
 * The square sizes drawn from a PNG source, in pixels: 48 for the favicon
 * (search results ask for 48 or a multiple), 180 for the apple-touch-icon,
 * 192 and 512 for the manifest.
 *
 * @return array
 */
function pg_site_icon_sizes()
{
    return array(48, 180, 192, 512);
}

/**
 * The Files screen name of the site icon, '' when none is set (or before the
 * config_text table exists).
 *
 * @return string
 */
function pg_site_icon_name()
{
    return trim((string) pg_config_text('site_icon'));
}

/**
 * What kind of source a file name is: 'png', 'svg' or '' for anything else
 * (a picture the engine cannot draw squares from as PNG is not offered).
 *
 * @param string $name
 * @return string
 */
function pg_site_icon_kind($name)
{
    $name = strtolower(trim((string) $name));

    if (preg_match('/\.png$/', $name)) {
        return 'png';
    }

    if (preg_match('/\.svg$/', $name)) {
        return 'svg';
    }

    return '';
}

/**
 * The public address of a size drawn from a PNG source, under the site's
 * root: the apple-touch-icon at the name browsers ask for on their own.
 *
 * @param int    $size
 * @param string $root  PATH
 * @return string
 */
function pg_site_icon_href($size, $root)
{
    return $root . (((int) $size === 180) ? 'apple-touch-icon.png' : 'site-icon-' . (int) $size . '.png');
}

/**
 * The size an address under the site's root asks for (router.php,
 * get_site_icon.php): 0 for the manifest, the size for an icon, -1 for
 * anything else.
 *
 * @param string $item  The address without PATH, lower case
 * @return int
 */
function pg_site_icon_item_size($item)
{
    $item = (string) $item;

    if ($item === 'site.webmanifest') {
        return 0;
    }

    if ($item === 'apple-touch-icon.png') {
        return 180;
    }

    // One address per size: 180 is only the apple-touch-icon.
    if (preg_match('/^site-icon-(\d{2,3})\.png$/', $item, $match) && in_array((int) $match[1], pg_site_icon_sizes(), true)
        && ((int) $match[1] !== 180)
    ) {
        return (int) $match[1];
    }

    return -1;
}

/**
 * The head links of the site icon: the favicon sizes, the apple-touch-icon
 * and the manifest for a PNG; the picture itself and the manifest for an
 * SVG. $version follows the source (pg_site_icon_stamp()), so a browser
 * that keeps icons for a long time asks again when the picture changes.
 * $skip names what the page's own head already declares ('icon',
 * 'apple-touch-icon', 'manifest'): the design's own links win.
 *
 * @param string $kind       pg_site_icon_kind()
 * @param string $root       PATH
 * @param string $file_href  The source's own address (for an SVG)
 * @param string $version
 * @param array  $skip
 * @return string
 */
function pg_site_icon_head_html($kind, $root, $file_href, $version, $skip = array())
{
    $query = ($version !== '') ? '?v=' . rawurlencode(substr((string) $version, 0, 8)) : '';
    $links = array();

    if ($kind === 'png') {
        if (!in_array('icon', $skip, true)) {
            $links[] = '<link rel="icon" type="image/png" sizes="48x48" href="' . h(pg_site_icon_href(48, $root) . $query) . '">';
            $links[] = '<link rel="icon" type="image/png" sizes="192x192" href="' . h(pg_site_icon_href(192, $root) . $query) . '">';
        }

        if (!in_array('apple-touch-icon', $skip, true)) {
            $links[] = '<link rel="apple-touch-icon" sizes="180x180" href="' . h(pg_site_icon_href(180, $root) . $query) . '">';
        }
    } elseif ($kind === 'svg') {
        if (!in_array('icon', $skip, true)) {
            $links[] = '<link rel="icon" type="image/svg+xml" href="' . h($file_href) . '">';
        }
    } else {
        return '';
    }

    if (!in_array('manifest', $skip, true)) {
        $links[] = '<link rel="manifest" href="' . h($root . 'site.webmanifest' . $query) . '">';
    }

    return implode("\n        ", $links);
}

/**
 * Which of the site icon's links a page's head already declares itself
 * ('icon', 'apple-touch-icon', 'manifest'), in the part before </head>.
 *
 * @param string $html
 * @return array
 */
function pg_site_icon_declared($html)
{
    $html = (string) $html;
    $end = stripos($html, '</head>');
    $head = ($end === false) ? $html : substr($html, 0, $end);
    $declared = array();

    if (preg_match_all('/<link\b[^>]*\brel\s*=\s*["\']?([^"\'>]+)/i', $head, $matches)) {
        foreach ($matches[1] as $rel) {
            $words = preg_split('/\s+/', strtolower(trim($rel)));

            if (in_array('icon', $words, true)) {
                $declared['icon'] = 'icon';
            }

            if (in_array('apple-touch-icon', $words, true) || in_array('apple-touch-icon-precomposed', $words, true)) {
                $declared['apple-touch-icon'] = 'apple-touch-icon';
            }

            if (in_array('manifest', $words, true)) {
                $declared['manifest'] = 'manifest';
            }
        }
    }

    return array_values($declared);
}

/**
 * The web manifest of the site: its name, its home address and the icons.
 * No display mode beyond the browser's own: the site is not promised to
 * work as an installed application.
 *
 * @param string $kind       pg_site_icon_kind()
 * @param string $name       The site's title
 * @param string $root       PATH
 * @param string $file_href  The source's own address (for an SVG)
 * @param string $version
 * @return array
 */
function pg_site_icon_manifest($kind, $name, $root, $file_href, $version)
{
    $query = ($version !== '') ? '?v=' . rawurlencode(substr((string) $version, 0, 8)) : '';
    $icons = array();

    if ($kind === 'png') {
        foreach (array(192, 512) as $size) {
            $icons[] = array('src' => pg_site_icon_href($size, $root) . $query, 'sizes' => $size . 'x' . $size, 'type' => 'image/png');
        }
    } elseif ($kind === 'svg') {
        $icons[] = array('src' => $file_href, 'sizes' => 'any', 'type' => 'image/svg+xml');
    }

    $name = trim((string) $name);
    $manifest = array('name' => $name, 'short_name' => $name, 'start_url' => $root, 'display' => 'browser', 'icons' => $icons);

    if ($name === '') {
        unset($manifest['name'], $manifest['short_name']);
    }

    return $manifest;
}

/**
 * The folder the drawn sizes are kept in: data/site_icon/, beside the file
 * directory (data/ is refused over HTTP; get_site_icon.php sends them).
 *
 * @return string
 */
function pg_site_icon_directory()
{
    return dirname(FILE_DIRECTORY_PATH) . '/site_icon';
}

/**
 * The source file of a site icon name on disk, '' when the Files screen has
 * no such file. pg_basename() keeps a stored value from walking out of the
 * file directory.
 *
 * @param string $name
 * @return string
 */
function pg_site_icon_source_path($name)
{
    $name = pg_basename(trim((string) $name));

    if ($name === '') {
        return '';
    }

    $path = FILE_DIRECTORY_PATH . '/' . $name;

    return is_file($path) ? $path : '';
}

/**
 * What the drawn sizes were made from: the name, the source's modification
 * time and length. A replaced picture gives another stamp.
 *
 * @param string $name
 * @param string $path  pg_site_icon_source_path()
 * @return string
 */
function pg_site_icon_stamp($name, $path)
{
    if ($path === '' || !is_file($path)) {
        return '';
    }

    return sha1(pg_basename((string) $name) . '|' . (int) @filemtime($path) . '|' . (int) @filesize($path));
}

/**
 * Whether the drawn sizes in pg_site_icon_directory() were made from this
 * source as it is now.
 *
 * @param string $stamp  pg_site_icon_stamp()
 * @return bool
 */
function pg_site_icon_current($stamp)
{
    if ($stamp === '') {
        return false;
    }

    $kept = @file_get_contents(pg_site_icon_directory() . '/stamp.txt');

    if (trim((string) $kept) !== $stamp) {
        return false;
    }

    foreach (pg_site_icon_sizes() as $size) {
        if (!is_file(pg_site_icon_directory() . '/icon-' . $size . '.png')) {
            return false;
        }
    }

    return true;
}

/**
 * Draw the sizes of a PNG site icon (the Files screen name $name) into
 * pg_site_icon_directory() and stamp them (pg_site_icon_draw()).
 *
 * @param string $name
 * @return array  'ok' => bool, 'reason' => '' | not_png | missing | folder | engine
 */
function pg_site_icon_generate($name)
{
    if (pg_site_icon_kind($name) !== 'png') {
        return array('ok' => false, 'reason' => 'not_png');
    }

    $source = pg_site_icon_source_path($name);

    if ($source === '') {
        return array('ok' => false, 'reason' => 'missing');
    }

    return pg_site_icon_draw($source, pg_site_icon_directory(), pg_site_icon_stamp($name, $source));
}

/**
 * Draw the sizes of a PNG through the image engine: each size is a copy of
 * the source scaled down to that long edge (a smaller source is kept as it
 * is: the engine never enlarges), moved over the previous one only when it
 * is complete. The stamp goes last, so a draw cut short is drawn again on
 * the next request.
 *
 * @param string $source     The PNG on disk
 * @param string $directory  Where icon-<size>.png and stamp.txt go
 * @param string $stamp      pg_site_icon_stamp()
 * @return array  'ok' => bool, 'reason' => '' | not_png | folder | engine
 */
function pg_site_icon_draw($source, $directory, $stamp)
{
    $info = @getimagesize($source);

    if (!is_array($info) || (($info['mime'] ?? '') !== 'image/png')) {
        return array('ok' => false, 'reason' => 'not_png');
    }

    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }

    if (!is_dir($directory) || !pg_dir_writable($directory)) {
        return array('ok' => false, 'reason' => 'folder');
    }

    @set_time_limit(120);

    foreach (pg_site_icon_sizes() as $size) {
        $temporary = $directory . '/icon-' . $size . '.draw.png';

        if (!@copy($source, $temporary)) {
            return array('ok' => false, 'reason' => 'folder');
        }

        $report = pg_process_image_file($temporary, array('max_dimension' => $size, 'quality' => 90));
        clearstatcache(true, $temporary);
        $drawn = @getimagesize($temporary);

        // 'skipped' keeps the copy: a source no larger than the size, or a
        // result the engine found heavier. Only a picture still larger than
        // the size is refused.
        if ((($report['status'] ?? '') === 'error') || !is_array($drawn) || (max((int) $drawn[0], (int) $drawn[1]) > $size)) {
            @unlink($temporary);
            return array('ok' => false, 'reason' => 'engine');
        }

        if (!@rename($temporary, $directory . '/icon-' . $size . '.png')) {
            @unlink($temporary);
            return array('ok' => false, 'reason' => 'folder');
        }
    }

    @file_put_contents($directory . '/stamp.txt', (string) $stamp);

    return array('ok' => true, 'reason' => '');
}

/**
 * The sentence the settings screen shows for a site icon that could not be
 * drawn (pg_site_icon_generate()).
 *
 * @param string $reason
 * @return string
 */
function pg_site_icon_error_text($reason)
{
    switch ((string) $reason) {
        case 'not_png':
            return lang('The site icon must be a PNG or SVG picture.');
        case 'missing':
            return lang('The site icon file was not found in Files.');
        case 'folder':
            return lang('The site icon sizes could not be written to the data folder.');
        default:
            return lang('The site icon sizes could not be drawn from this picture.');
    }
}

/**
 * The head links for the page being drawn, '' while no site icon is set.
 * A PNG whose sizes are not drawn yet (or are out of date) is drawn here
 * first; one that cannot be drawn is not linked.
 *
 * @param string $content  The page drawn so far, for the links it declares
 * @return string
 */
function pg_site_icon_page_head($content)
{
    $name = pg_site_icon_name();
    $kind = pg_site_icon_kind($name);

    if ($kind === '') {
        return '';
    }

    $source = pg_site_icon_source_path($name);

    if ($source === '') {
        return '';
    }

    $stamp = pg_site_icon_stamp($name, $source);

    if (($kind === 'png') && !pg_site_icon_current($stamp)) {
        $drawn = pg_site_icon_generate($name);

        if (!$drawn['ok']) {
            return '';
        }
    }

    return pg_site_icon_head_html($kind, PATH, PATH . encode_url_path(pg_basename($name)), $stamp, pg_site_icon_declared($content));
}
