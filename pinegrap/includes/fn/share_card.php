<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The share card: a 1200 x 630 PNG drawn by the server from a page's or
 * item's title, the Organization Logo and the site's name, for a link
 * shared where the page has no share picture of its own.
 *
 * A page that prints a card's address keeps the title it printed in the
 * item's order file (cache/share/<kind>-<id>.json, one entry per language)
 * and signs the address over the kind, the id, that title, the logo's
 * stamp, the colours and the site's name. No text travels in the address:
 * get_share_card.php draws only a title the site itself printed, and an
 * address whose title, logo, colours or name has changed since names
 * nothing (404). The card is drawn once, under a lock and inside the image
 * engine's encode slots, and kept beside it; the general job removes the
 * files not asked for in 30 days.
 *
 * Loaded where it is used (get_share_card.php, job.php), not by
 * functions.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/image_draw.php');

/**
 * The card's measures. Pure.
 *
 * @return array width, height, pad (the margin), title (size, weight,
 *               lines, line height), name (size, weight), logo (box height
 *               and largest width), keep (days a card not asked for is
 *               kept), retry (seconds before a failed draw is tried again)
 */
function pg_share_card_limits()
{
    return array(
        'width'  => 1200,
        'height' => 630,
        'pad'    => 80,
        'title'  => array('size' => 64, 'weight' => 700, 'lines' => 3, 'lineHeight' => 1.2),
        'name'   => array('size' => 32, 'weight' => 400),
        'logo'   => array('height' => 96, 'width' => 480),
        'keep'   => 30,
        'retry'  => 3600,
    );
}

/**
 * The kinds of item a card is made for: a page, a record of a form and a
 * catalog product. Pure.
 *
 * @return string[]
 */
function pg_share_card_kinds()
{
    return array('page', 'record', 'product');
}

/**
 * The title cut into lines no wider than the width, measured letter by
 * letter as the server draws them: words go onto a line while they fit, a
 * word wider than a line is cut by letters and starts its own line, and a
 * title that needs more lines than allowed ends its last line with an
 * ellipsis. Pure but for the measure it is given.
 *
 * @param string   $title
 * @param callable $measure a text => its width in pixels
 * @param float    $width
 * @param int      $max     lines
 * @return string[]
 */
function pg_share_card_wrap($title, $measure, $width, $max = 3)
{
    $words = preg_split('/\s+/u', trim((string) $title), -1, PREG_SPLIT_NO_EMPTY);
    $lines = array();
    $line = '';
    $fits = function ($text) use ($measure, $width) {
        return (float) call_user_func($measure, $text) <= $width;
    };

    foreach ((array) $words as $word) {
        if (($line !== '') && $fits($line . ' ' . $word)) {
            $line .= ' ' . $word;
            continue;
        }

        if ($line !== '') {
            $lines[] = $line;
            $line = '';
        }

        if ($fits($word)) {
            $line = $word;
            continue;
        }

        // A word wider than a line: as many letters as fit, at least one.
        foreach ((array) preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) as $letter) {
            if (($line !== '') && !$fits($line . $letter)) {
                $lines[] = $line;
                $line = '';
            }

            $line .= $letter;
        }
    }

    if ($line !== '') {
        $lines[] = $line;
    }

    $max = max(1, (int) $max);

    if (count($lines) <= $max) {
        return $lines;
    }

    $lines = array_slice($lines, 0, $max);
    $last = $lines[$max - 1];

    while (($last !== '') && !$fits(rtrim($last) . '…')) {
        $last = mb_substr($last, 0, mb_strlen($last) - 1);
    }

    $lines[$max - 1] = rtrim($last) . '…';

    return $lines;
}

/**
 * The measure of a text in the bundled font at a size and weight: the sum
 * of its letters' advances, as the server places them. A letter the font
 * lacks counts as an em.
 *
 * @param int $size   pixels
 * @param int $weight 400 or 700
 * @return callable a text => its width in pixels
 */
function pg_share_card_measure($size, $weight)
{
    $file = pg_image_draw_font_file('g:Roboto', $weight, false);
    $font = ($file !== '') ? pg_image_draw_font_read($file) : null;
    $em = $font ? $size / $font['upem'] : 0;

    return function ($text) use ($font, $em, $size) {
        $width = 0.0;

        foreach ((array) preg_split('//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY) as $letter) {
            $advance = $font ? pg_image_draw_font_advance($font, $letter) : null;
            $width += ($advance === null) ? $size : $advance * $em;
        }

        return $width;
    };
}

/**
 * Why this host cannot draw a card of the title and name: 'server' when it
 * draws no text (no Imagick, its check failed or the drawing is paused),
 * 'letters' when a letter needs shaping or the bundled font lacks it; ''
 * when it can. The default share picture then stays.
 *
 * @param array  $can   pg_image_draw_can()
 * @param string $title
 * @param string $name  the site's name
 * @return string
 */
function pg_share_card_unable($can, $title, $name)
{
    $limits = pg_share_card_limits();

    if (empty($can['shapes']) || empty($can['text'])) {
        return 'server';
    }

    $bold = pg_image_draw_font_file('g:Roboto', $limits['title']['weight'], false);
    $regular = pg_image_draw_font_file('g:Roboto', $limits['name']['weight'], false);

    if (($bold === '') || ($regular === '')) {
        return 'server';
    }

    // The ellipsis is drawn whenever a text is cut.
    $title = trim((string) $title) . '…';
    $name = trim((string) $name) . '…';

    if (!pg_image_draw_text_plain($title) || !pg_image_draw_text_plain($name)
        || !pg_image_draw_font_fits($bold, $limits['title']['weight'], false, $title)
        || !pg_image_draw_font_fits($regular, $limits['name']['weight'], false, $name)) {
        return 'letters';
    }

    return '';
}

/**
 * The colours of the card from the settings texts share_card_background and
 * share_card_color (#rgb or #rrggbb); a dark slate with white letters when
 * a value is missing or no colour.
 *
 * @return array background, color
 */
function pg_share_card_colors()
{
    $background = strtolower(trim(pg_config_text('share_card_background')));
    $color = strtolower(trim(pg_config_text('share_card_color')));

    return array(
        'background' => pg_image_draw_color($background) ? $background : '#1f2937',
        'color'      => pg_image_draw_color($color) ? $color : '#ffffff',
    );
}

/**
 * The Organization Logo as the card draws it: a file of the site's file
 * folder in a format this host reads. An address of another site is never
 * fetched.
 *
 * @param array $can pg_image_draw_can()
 * @return array|null path, width, height, stamp (name, time and size, so a
 *                    replaced picture is a new card)
 */
function pg_share_card_logo($can)
{
    $name = defined('ORGANIZATION_LOGO') ? trim((string) ORGANIZATION_LOGO) : '';

    if (($name === '') || !defined('FILE_DIRECTORY_PATH') || preg_match('#[/\\\\]|^\.|^https?:#i', $name)) {
        return null;
    }

    $path = FILE_DIRECTORY_PATH . '/' . $name;
    $size = is_file($path) ? @getimagesize($path) : false;
    $formats = array(IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpeg', IMAGETYPE_WEBP => 'webp');

    if (!$size || !isset($formats[$size[2]]) || !in_array($formats[$size[2]], (array) ($can['pictures'] ?? array()), true)
        || ($size[0] < 1) || ($size[1] < 1) || ($size[0] * $size[1] > pg_image_draw_limits()['picture'])) {
        return null;
    }

    return array('path' => $path, 'width' => (int) $size[0], 'height' => (int) $size[1], 'stamp' => $name . '|' . (int) @filemtime($path) . '|' . (int) @filesize($path));
}

/**
 * What every card of the site is signed against now.
 *
 * @param array|null $logo pg_share_card_logo()
 * @return array logo (its stamp, '' for none), background, color, name
 */
function pg_share_card_state($logo)
{
    return array('logo' => $logo ? $logo['stamp'] : '', 'name' => defined('TITLE') ? trim((string) TITLE) : '') + pg_share_card_colors();
}

/**
 * The signature of a card: the kind, the id, the title and the site's
 * state under the site's key, so any change of what the card shows is a new
 * address. '' without a key, a known kind, an id or a title. Pure.
 *
 * @param string $kind  pg_share_card_kinds()
 * @param int    $id
 * @param string $title
 * @param array  $state pg_share_card_state()
 * @param string $key   ENCRYPTION_KEY
 * @return string 32 hex letters
 */
function pg_share_card_signature($kind, $id, $title, $state, $key)
{
    $id = (int) $id;
    $title = trim((string) $title);

    if (((string) $key === '') || !in_array($kind, pg_share_card_kinds(), true) || ($id <= 0) || ($title === '')) {
        return '';
    }

    $signed = implode("\n", array('share-card:1', $kind, $id, $title, (string) ($state['logo'] ?? ''),
        (string) ($state['background'] ?? ''), (string) ($state['color'] ?? ''), (string) ($state['name'] ?? '')));

    return substr(hash_hmac('sha256', $signed, (string) $key), 0, 32);
}

/**
 * A card's address. Pure.
 *
 * @param string $root the site's address with its path, ending in /
 * @param string $kind
 * @param int    $id
 * @param string $signature
 * @return string
 */
function pg_share_card_href($root, $kind, $id, $signature)
{
    return $root . 'get_share_card.php?k=' . rawurlencode($kind) . '&i=' . (int) $id . '&s=' . rawurlencode($signature);
}

/**
 * A card request read from the query: a known kind, an id and a signature
 * in their own shapes, else null. Pure.
 *
 * @param array $query $_GET
 * @return array|null kind, id, signature
 */
function pg_share_card_request($query)
{
    $kind = $query['k'] ?? '';
    $id = $query['i'] ?? '';
    $signature = $query['s'] ?? '';

    if (!is_string($kind) || !is_string($id) || !is_string($signature) || !in_array($kind, pg_share_card_kinds(), true)
        || !preg_match('/^[1-9][0-9]{0,9}$/', $id) || !preg_match('/^[0-9a-f]{32}$/', $signature)) {
        return null;
    }

    return array('kind' => $kind, 'id' => (int) $id, 'signature' => $signature);
}

/**
 * The folder the cards and their orders are kept in: beside the file
 * folder, like the image derivatives, so two sites sharing one software
 * folder never read each other's ids.
 *
 * @return string '' without a file folder
 */
function pg_share_card_directory()
{
    return defined('FILE_DIRECTORY_PATH') ? dirname(FILE_DIRECTORY_PATH) . '/cache/share' : '';
}

/**
 * A card's file. Pure.
 *
 * @return string '' without a folder
 */
function pg_share_card_path($directory, $kind, $id, $signature)
{
    return ($directory === '') ? '' : $directory . '/' . $kind . '-' . (int) $id . '-' . $signature . '.png';
}

/**
 * An item's order file: the titles its pages printed. Pure.
 *
 * @return string '' without a folder
 */
function pg_share_card_order_path($directory, $kind, $id)
{
    return ($directory === '') ? '' : $directory . '/' . $kind . '-' . (int) $id . '.json';
}

/**
 * An order with the title a page printed in a language. The same title,
 * signature and page within a day give the order back unchanged, so a
 * page drawn again writes nothing. Pure.
 *
 * @param array  $order     language => t (title), s (signature), p (the
 *                          page that printed it), at (time)
 * @param string $language
 * @param string $title
 * @param string $signature
 * @param int    $page_id
 * @param int    $now
 * @return array
 */
function pg_share_card_order_put($order, $language, $title, $signature, $page_id, $now)
{
    $language = (string) $language;
    $entry = $order[$language] ?? null;

    if (is_array($entry) && (($entry['t'] ?? '') === $title) && (($entry['s'] ?? '') === $signature)
        && ((int) ($entry['p'] ?? 0) === (int) $page_id) && ((int) $now - (int) ($entry['at'] ?? 0) < 86400)) {
        return $order;
    }

    $order[$language] = array('t' => (string) $title, 's' => (string) $signature, 'p' => (int) $page_id, 'at' => (int) $now);

    return $order;
}

/**
 * The entry of an order a request names, when its title signs to the
 * request's signature against the site as it is now and the item is one
 * every visitor may open; null for anything else (404). Pure but for a
 * callable $public.
 *
 * @param array         $request pg_share_card_request()
 * @param array         $order   pg_share_card_order_put()
 * @param bool|callable $public  whether the item is public, or an entry
 *                               (title, page, language) => that answer
 * @param array         $state   pg_share_card_state()
 * @param string        $key
 * @return array|null title, page, language
 */
function pg_share_card_find($request, $order, $public, $state, $key)
{
    foreach ((array) $order as $language => $entry) {
        if (!is_array($entry) || !is_string($entry['s'] ?? null) || !hash_equals($entry['s'], (string) $request['signature'])) {
            continue;
        }

        $signature = pg_share_card_signature($request['kind'], $request['id'], (string) ($entry['t'] ?? ''), $state, $key);

        if (($signature === '') || !hash_equals($signature, (string) $request['signature'])) {
            continue;
        }

        $found = array('title' => (string) $entry['t'], 'page' => (int) ($entry['p'] ?? 0), 'language' => (string) $language);

        return (is_callable($public) ? call_user_func($public, $found) : $public) ? $found : null;
    }

    return null;
}

/**
 * The query of a page's folder and whether that folder is archived, as
 * get_page.php reads them. Pure.
 *
 * @param int $page_id
 * @return string
 */
function pg_share_card_page_sql($page_id)
{
    return "SELECT page.page_folder, folder.folder_archived FROM page LEFT JOIN folder ON page.page_folder = folder.folder_id WHERE page.page_id = '" . (int) $page_id . "'";
}

/**
 * The query that finds a record (a row of forms) or an enabled product;
 * '' for a page, which is checked by its folder alone. Pure.
 *
 * @param string $kind
 * @param int    $id
 * @return string
 */
function pg_share_card_item_sql($kind, $id)
{
    if ($kind === 'record') {
        return "SELECT id FROM forms WHERE id = '" . (int) $id . "' AND page_id <> '0'";
    }

    return ($kind === 'product') ? "SELECT id FROM products WHERE id = '" . (int) $id . "' AND enabled = '1'" : '';
}

/**
 * Whether every visitor opens a page: it exists, its folder is public, not
 * archived (get_page.php answers 410 there) and not in the Recycle Bin. A
 * draft sits in the private Drafts folder. Pure.
 *
 * @param array|null $row    pg_share_card_page_sql()
 * @param string     $access get_access_control_type() of its folder
 * @param bool       $binned pg_folder_binned() of its folder
 * @return bool
 */
function pg_share_card_page_open($row, $access, $binned)
{
    return is_array($row) && ((string) ($row['page_folder'] ?? '') !== '') && ((string) ($row['folder_archived'] ?? '') !== '1')
        && ($access === 'public') && !$binned;
}

/**
 * Whether every visitor may open the item and the page that printed its
 * card: a page pg_share_card_page_open() lets through, a record whose
 * printing page is open, an enabled product whose printing page is open.
 *
 * @param string $kind
 * @param int    $id
 * @param int    $page_id the page that printed the card
 * @return bool
 */
function pg_share_card_public($kind, $id, $page_id)
{
    $page_public = function ($page_id) {
        $row = db_item(pg_share_card_page_sql($page_id));
        $folder = is_array($row) ? (string) ($row['page_folder'] ?? '') : '';

        return ($folder !== '') && pg_share_card_page_open($row, get_access_control_type($folder), pg_folder_binned($folder));
    };

    if ($kind === 'page') {
        return $page_public($id);
    }

    $sql = pg_share_card_item_sql($kind, $id);

    return ($sql !== '') && $page_public($page_id) && (bool) db_value($sql);
}

/**
 * The address of an item's card for a page to print, with the title the
 * page prints; '' when no card can be made (no key or folder, a host that
 * cannot draw it, letters the font lacks), and the page then keeps the
 * default picture. Keeps the title in the item's order file.
 *
 * @param string $kind
 * @param int    $id
 * @param string $title    as og:title prints it
 * @param string $language the language the page is served in
 * @param int    $page_id  the page printing it
 * @return string
 */
function pg_share_card_address($kind, $id, $title, $language, $page_id)
{
    $directory = pg_share_card_directory();
    $title = trim((string) preg_replace('/\s+/u', ' ', (string) $title));
    $can = pg_image_draw_can();
    $state = pg_share_card_state(pg_share_card_logo($can));
    $signature = pg_share_card_signature($kind, $id, $title, $state, defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '');

    if (($directory === '') || ($signature === '') || (pg_share_card_unable($can, $title, $state['name']) !== '')) {
        return '';
    }

    $path = pg_share_card_order_path($directory, $kind, $id);
    $order = json_decode((string) @file_get_contents($path), true);
    $order = is_array($order) ? $order : array();

    if (pg_share_card_order_put($order, $language, $title, $signature, $page_id, time()) !== $order) {
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        // Read again under the item's lock, so two languages printed at the
        // same moment both keep their entry.
        $lock = @fopen($path . '.lock', 'c');

        if (!$lock || !@flock($lock, LOCK_EX)) {
            if ($lock) {
                @fclose($lock);
            }

            return '';
        }

        $order = json_decode((string) @file_get_contents($path), true);
        $order = is_array($order) ? $order : array();
        $written = pg_share_card_write($path, (string) json_encode(pg_share_card_order_put($order, $language, $title, $signature, $page_id, time())));
        @flock($lock, LOCK_UN);
        @fclose($lock);

        if (!$written) {
            return '';
        }
    }

    return pg_share_card_href(URL_SCHEME . HOSTNAME_SETTING . PATH, $kind, $id, $signature);
}

/**
 * Writes a file whole: a temporary file renamed over it, so a reader never
 * sees half of it.
 *
 * @param string $path
 * @param string $bytes
 * @return bool
 */
function pg_share_card_write($path, $bytes)
{
    $temporary = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

    if (@file_put_contents($temporary, $bytes) !== strlen($bytes)) {
        @unlink($temporary);

        return false;
    }

    if (!@rename($temporary, $path)) {
        @unlink($temporary);

        return false;
    }

    return true;
}

/**
 * The picture layer's address the logo is drawn from. Pure.
 *
 * @return string
 */
function pg_share_card_logo_src()
{
    return 'share-card-logo';
}

/**
 * The card's canvas document: the background colour, the logo top left
 * when there is one, the title's lines below it and the site's name at the
 * foot, in the bundled Roboto. Pure.
 *
 * @param string[]   $lines  pg_share_card_wrap()
 * @param string     $name   the site's name, cut to one line
 * @param array      $colors background, color
 * @param array|null $logo   width, height
 * @return array
 */
function pg_share_card_document($lines, $name, $colors, $logo)
{
    $limits = pg_share_card_limits();
    $pad = $limits['pad'];
    $layers = array();
    $top = $pad + 40;

    if ($logo && ($logo['width'] > 0) && ($logo['height'] > 0)) {
        $height = (float) $limits['logo']['height'];
        $width = $height * $logo['width'] / $logo['height'];

        if ($width > $limits['logo']['width']) {
            $width = (float) $limits['logo']['width'];
            $height = $width * $logo['height'] / $logo['width'];
        }

        $layers[] = array('type' => 'image', 'src' => pg_share_card_logo_src(), 'x' => $pad, 'y' => 72, 'w' => round($width, 2), 'h' => round($height, 2));
        $top = 72 + $limits['logo']['height'] + 48;
    }

    $title = $limits['title'];
    $layers[] = array('type' => 'text', 'text' => implode("\n", $lines), 'font' => 'g:Roboto', 'weight' => $title['weight'], 'size' => $title['size'],
        'lineHeight' => $title['lineHeight'], 'color' => $colors['color'], 'x' => $pad, 'y' => $top,
        'w' => $limits['width'] - 2 * $pad, 'h' => round(max(1, count($lines)) * $title['size'] * $title['lineHeight'], 2));

    $foot = $limits['name'];
    $layers[] = array('type' => 'text', 'text' => (string) $name, 'font' => 'g:Roboto', 'weight' => $foot['weight'], 'size' => $foot['size'],
        'lineHeight' => 1.25, 'color' => $colors['color'], 'opacity' => 0.8, 'x' => $pad, 'y' => $limits['height'] - 72 - $foot['size'] * 1.25,
        'w' => $limits['width'] - 2 * $pad, 'h' => $foot['size'] * 1.25);

    return array('artboard' => array('width' => $limits['width'], 'height' => $limits['height'], 'background' => $colors['background']), 'layers' => $layers);
}

/**
 * Draws a card into its file: under the card's own lock (a second request
 * for it waits outside), inside one of the image engine's encode slots and
 * the drawing's time budget. A drawing that fails leaves a mark beside the
 * card, so the address is not drawn again for an hour.
 *
 * @param string     $path  pg_share_card_path()
 * @param string     $title
 * @param array      $state pg_share_card_state()
 * @param array|null $logo  pg_share_card_logo()
 * @return string drawn | busy (the lock or every slot is taken, or the
 *                folder takes no file) | failed
 */
function pg_share_card_draw($path, $title, $state, $logo)
{
    $limits = pg_share_card_limits();
    $directory = dirname($path);

    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }

    if (!pg_dir_writable($directory)) {
        return 'busy';
    }

    $lock = @fopen($path . '.lock', 'c');

    if (!$lock || !@flock($lock, LOCK_EX | LOCK_NB)) {
        if ($lock) {
            @fclose($lock);
        }

        return 'busy';
    }

    $result = 'drawn';

    if (!is_file($path)) {
        $slots = pg_image_derivative_directory();

        if (($slots !== '') && !is_dir($slots)) {
            @mkdir($slots, 0755, true);
        }

        $slot = pg_image_encode_slot_take($slots);

        if ($slot === null) {
            $result = 'busy';
        } else {
            $width = $limits['width'] - 2 * $limits['pad'];
            $lines = pg_share_card_wrap($title, pg_share_card_measure($limits['title']['size'], $limits['title']['weight']), $width, $limits['title']['lines']);
            $name = pg_share_card_wrap($state['name'], pg_share_card_measure($limits['name']['size'], $limits['name']['weight']), $width, 1);
            $doc = pg_share_card_document($lines, (string) ($name[0] ?? ''), $state, $logo);
            $png = pg_image_draw_document($doc, function ($src) use ($logo) {
                return ($logo && ($src === pg_share_card_logo_src())) ? $logo['path'] : '';
            });

            pg_image_encode_slot_release($slot);

            if (($png === '') || !pg_share_card_write($path, $png)) {
                @file_put_contents(substr($path, 0, -4) . '.fail', (string) time());
                $result = 'failed';
            }
        }
    }

    @flock($lock, LOCK_UN);
    @fclose($lock);
    @unlink($path . '.lock');

    return $result;
}

/**
 * The answer to a card request: the file, drawn first when it is not there
 * yet.
 *
 * @param array $request pg_share_card_request()
 * @return array status (200, 404 or 503), path (with 200)
 */
function pg_share_card_answer($request)
{
    $limits = pg_share_card_limits();
    $directory = pg_share_card_directory();
    $none = array('status' => 404, 'path' => '');

    if (($directory === '') || !$request) {
        return $none;
    }

    $order = json_decode((string) @file_get_contents(pg_share_card_order_path($directory, $request['kind'], $request['id'])), true);

    if (!is_array($order)) {
        return $none;
    }

    $can = pg_image_draw_can();
    $logo = pg_share_card_logo($can);
    $state = pg_share_card_state($logo);
    $found = pg_share_card_find($request, $order, function ($entry) use ($request) {
        return pg_share_card_public($request['kind'], $request['id'], $entry['page']);
    }, $state, defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '');

    if (!$found) {
        return $none;
    }

    $path = pg_share_card_path($directory, $request['kind'], $request['id'], $request['signature']);

    if (!is_file($path)) {
        $failed = (int) @filemtime(substr($path, 0, -4) . '.fail');

        // A failed drawing is not tried again within the hour, and a host
        // that cannot draw it never starts.
        if ((($failed > 0) && ($failed > time() - $limits['retry'])) || (pg_share_card_unable($can, $found['title'], $state['name']) !== '')) {
            return $none;
        }

        $drawn = pg_share_card_draw($path, $found['title'], $state, $logo);

        if ($drawn !== 'drawn') {
            return array('status' => ($drawn === 'busy') ? 503 : 404, 'path' => '');
        }
    } elseif ((int) @filemtime($path) < time() - 86400) {
        // Asked for: the sweep keeps it another 30 days.
        @touch($path);
    }

    return array('status' => 200, 'path' => $path);
}

/**
 * Removes the cards and orders not asked for or printed in the days kept,
 * failure marks past their hour and locks or temporary files left by a
 * request that died. Other files of the folder are left alone.
 *
 * @param string $directory pg_share_card_directory()
 * @param int    $now
 * @return int files removed
 */
function pg_share_card_sweep($directory, $now)
{
    $limits = pg_share_card_limits();
    $removed = 0;

    if (($directory === '') || !is_dir($directory)) {
        return 0;
    }

    $kinds = implode('|', pg_share_card_kinds());

    foreach ((array) glob($directory . '/*') as $path) {
        $name = basename((string) $path);
        $age = (int) $now - (int) @filemtime($path);

        if (preg_match('/^(?:' . $kinds . ')-[0-9]+(?:-[0-9a-f]{32}\.png|\.json|\.json\.lock)$/', $name)) {
            $old = $age > $limits['keep'] * 86400;
        } elseif (preg_match('/^(?:' . $kinds . ')-[0-9]+(?:-[0-9a-f]{32}\.(?:fail|png\.lock)|.*\.tmp)$/', $name)) {
            $old = $age > $limits['retry'];
        } else {
            continue;
        }

        if ($old && @unlink($path)) {
            $removed++;
        }
    }

    return $removed;
}

/**
 * Whether the day's sweep is due: the folder is there and its mark is a
 * day old or missing.
 *
 * @param string $directory
 * @param int    $now
 * @return bool
 */
function pg_share_card_sweep_due($directory, $now)
{
    if (($directory === '') || !is_dir($directory)) {
        return false;
    }

    return (int) @filemtime($directory . '/sweep.txt') < (int) $now - 86400;
}

/**
 * The day's sweep, its mark written first so a sweep that dies is not run
 * again on every tick.
 *
 * @return int files removed
 */
function pg_share_card_sweep_daily()
{
    $directory = pg_share_card_directory();
    @file_put_contents($directory . '/sweep.txt', (string) time());

    return pg_share_card_sweep($directory, time());
}
