<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The link preview of a Workspace message: the first web address of its
 * text, read once by the general job, kept as one ws_link_previews row per
 * message (title, description, site name) for the card under the message.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The longest address kept; a longer one gets no preview. */
const WS_LINK_PREVIEW_URL_MAX = 2048;

/**
 * The first http or https address of a message's text, or ''. An address
 * inside code (a `span` or a ``` block) or inside a tag (<#order:5>,
 * <@app:2>) is not one. Closing punctuation of the sentence is left out.
 *
 * @param string $text
 * @return string
 */
function ws_link_preview_first_url($text)
{
    $text = (string) $text;

    // Code first: a fenced block, then an inline span; then the tags.
    $text = preg_replace('/```.*?(```|$)/s', ' ', $text);
    $text = preg_replace('/`[^`\n]*`/', ' ', (string) $text);
    $text = preg_replace('/<[#@][^>\s]*>/', ' ', (string) $text);

    if (!preg_match('#\bhttps?://[^\s<>"\'`]+#i', (string) $text, $match)) {
        return '';
    }

    $url = $match[0];

    // A sentence's end is not part of the address; a closing bracket is,
    // when the address opened one itself (a Wikipedia title).
    while ($url !== '') {
        $last = substr($url, -1);

        if (strpos('.,;:!?\'"*_', $last) !== false) {
            $url = substr($url, 0, -1);
        } elseif ((($last === ')') && (substr_count($url, '(') < substr_count($url, ')')))
            || (($last === ']') && (substr_count($url, '[') < substr_count($url, ']')))) {
            $url = substr($url, 0, -1);
        } else {
            break;
        }
    }

    if ((strlen($url) > WS_LINK_PREVIEW_URL_MAX) || !preg_match('#^https?://[^/?\#]+#i', $url)) {
        return '';
    }

    return $url;
}

/** Most redirects walked after the first request. */
const WS_LINK_PREVIEW_REDIRECTS = 3;

/** Most bytes of a page read; the head of a page is in its first part. */
const WS_LINK_PREVIEW_BODY_MAX = 262144;

/** Seconds a page may take, the whole read with its redirects. */
const WS_LINK_PREVIEW_TIMEOUT = 8;

/** The longest title, description and site name kept, in characters. */
const WS_LINK_PREVIEW_TITLE_MAX = 300;
const WS_LINK_PREVIEW_DESCRIPTION_MAX = 500;
const WS_LINK_PREVIEW_SITE_MAX = 100;

/**
 * The address with its host in ASCII form: a name written in its own letters
 * (çiçek.com.tr) becomes its xn-- form, which is what is checked, looked up
 * and called. Unchanged when the host is ASCII already or cannot be turned
 * (no intl extension, a name IDNA refuses).
 *
 * @param string $url
 * @return string
 */
function ws_link_preview_ascii_url($url)
{
    $url = (string) $url;

    if (!preg_match('#^(https?://(?:[^/?\#@]*@)?)([^/?\#:]+)(.*)$#is', $url, $match)
        || !preg_match('/[^\x00-\x7F]/', $match[2]) || !function_exists('idn_to_ascii')) {
        return $url;
    }

    $variant = defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0;
    $host = @idn_to_ascii($match[2], defined('IDNA_NONTRANSITIONAL_TO_ASCII') ? IDNA_NONTRANSITIONAL_TO_ASCII : 0, $variant);

    return (is_string($host) && ($host !== '')) ? $match[1] . $host . $match[3] : $url;
}

/**
 * Whether an address may be read, from the address alone: http or https, no
 * user name or password in it, the usual port, and a host that is no name or
 * address of the network itself. The resolved address is checked again where
 * it is called (api_http_check_url() in api_http_request()).
 *
 * @param string $url
 * @return bool
 */
function ws_link_preview_url_allowed($url)
{
    $url = ws_link_preview_ascii_url($url);

    if (($url === '') || (strlen($url) > WS_LINK_PREVIEW_URL_MAX) || preg_match('/[\s\x00-\x1F\x7F]/', $url)) {
        return false;
    }

    $parts = @parse_url($url);

    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
        return false;
    }

    $scheme = strtolower($parts['scheme']);

    if (($scheme !== 'http') && ($scheme !== 'https')) {
        return false;
    }

    if (isset($parts['user']) || isset($parts['pass'])) {
        return false;
    }

    if (isset($parts['port']) && !in_array((int) $parts['port'], array(80, 443), true)) {
        return false;
    }

    $host = strtolower(rtrim($parts['host'], '.'));

    // An IPv6 address comes in brackets.
    if ((substr($host, 0, 1) === '[') && (substr($host, -1) === ']')) {
        $host = substr($host, 1, -1);
    }

    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        if (!function_exists('api_http_address_is_allowed')) {
            require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');
        }

        return api_http_address_is_allowed($host);
    }

    // A name with no dot, or one of the names kept for a local network.
    if ((strpos($host, '.') === false) || !preg_match('/^[a-z0-9.-]+$/', $host)) {
        return false;
    }

    foreach (array('.localhost', '.local', '.internal', '.lan', '.home', '.intranet', '.corp', '.home.arpa') as $end) {
        if (substr($host, -strlen($end)) === $end) {
            return false;
        }
    }

    // A name whose last part is a number (0x7f.1, 2130706433.0) is an
    // address to some resolvers; no top-level domain is one.
    $labels = explode('.', $host);

    if (preg_match('/^(0x[0-9a-f]*|[0-9]+)$/', (string) end($labels))) {
        return false;
    }

    return true;
}

/**
 * The address a redirect leads to, made whole from the address it came
 * from, or '' when there is none.
 *
 * @param string $location the Location header as sent
 * @param string $from
 * @return string
 */
function ws_link_preview_redirect_target($location, $from)
{
    $location = trim((string) $location);

    if ($location === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $location)) {
        if (!function_exists('url_to_absolute')) {
            require_once(PG_FUNCTIONS_DIR . '/url_to_absolute.php');
        }

        $location = url_to_absolute((string) $from, $location);
    }

    return is_string($location) ? $location : '';
}

/**
 * Whether an answer's Content-Type is a web page.
 *
 * @param string $content_type
 * @return bool
 */
function ws_link_preview_is_html($content_type)
{
    $type = strtolower(trim(explode(';', (string) $content_type)[0]));

    return ($type === 'text/html') || ($type === 'application/xhtml+xml');
}

/**
 * A text kept plain: tags and entities out, one space for any run of white
 * space, no control characters, cut at $max characters with an ellipsis.
 * The text is escaped where it is drawn.
 *
 * @param string $text
 * @param int    $max
 * @return string
 */
function ws_link_preview_cut($text, $max)
{
    $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    if (!preg_match('//u', $text)) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }

    $text = preg_replace('/[\x{0000}-\x{001F}\x{007F}-\x{009F}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', ' ', $text);
    $text = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', (string) $text));

    if (mb_strlen($text, 'UTF-8') > $max) {
        $text = rtrim(mb_substr($text, 0, max(1, $max - 1), 'UTF-8')) . '…';
    }

    return $text;
}

/**
 * A page's charset as mbstring takes it, its aliases included (Shift_JIS,
 * gbk, big5, cp1252), 'UTF-8' for UTF-8, or '' for one mbstring does not
 * know (PHP 8 throws on such a name, PHP 7 warns and answers false).
 *
 * @param string $name as the page or its Content-Type header says it
 * @return string
 */
function ws_link_preview_charset($name)
{
    $name = trim((string) $name);

    if ($name === '') {
        return '';
    }

    if (in_array(strtolower($name), array('utf-8', 'utf8'), true)) {
        return 'UTF-8';
    }

    try {
        $known = (@mb_convert_encoding('a', 'UTF-8', $name) !== false);
    } catch (\Throwable $error) {
        $known = false;
    }

    return $known ? $name : '';
}

/**
 * The card's fields from what a page says about itself: title (og:title,
 * twitter:title, else <title>), description (og:description, description,
 * twitter:description) and site name (og:site_name, else the host without
 * www.), each plain and cut. No title: nothing to show.
 *
 * @param string $html         the start of the page, at least its head
 * @param string $url          the address read, for the host
 * @param string $content_type the answer's Content-Type, for its charset
 * @return array title, description, site_name
 */
function ws_link_preview_fields($html, $url, $content_type = '')
{
    $html = (string) $html;
    $charset = '';

    if (preg_match('/charset\s*=\s*["\']?([a-z0-9_.:-]+)/i', (string) $content_type, $match)) {
        $charset = $match[1];
    } elseif (preg_match('/<meta[^>]+charset\s*=\s*["\']?([a-z0-9_.:-]+)/i', substr($html, 0, 4096), $match)) {
        $charset = $match[1];
    }

    $charset = ws_link_preview_charset($charset);

    if (($charset !== '') && ($charset !== 'UTF-8')) {
        $html = (string) @mb_convert_encoding($html, 'UTF-8', $charset);
        // The page's own declaration would make the reader decode it again.
        $html = (string) preg_replace('/(<meta[^>]+charset\s*=\s*["\']?)[a-z0-9_.:-]+/i', '${1}utf-8', $html);
    }

    $meta = array();
    $title = '';

    if (trim($html) !== '') {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // The XML declaration makes DOMDocument read the text as UTF-8.
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach ($document->getElementsByTagName('meta') as $node) {
            foreach (array('property', 'name') as $attribute) {
                $key = strtolower(trim($node->getAttribute($attribute)));

                if (($key !== '') && !isset($meta[$key])) {
                    $meta[$key] = $node->getAttribute('content');
                }
            }
        }

        $nodes = $document->getElementsByTagName('title');
        $title = ($nodes->length > 0) ? (string) $nodes->item(0)->textContent : '';
    }

    $pick = function ($keys, $else = '') use ($meta) {
        foreach ($keys as $key) {
            if (isset($meta[$key]) && (trim($meta[$key]) !== '')) {
                return $meta[$key];
            }
        }

        return $else;
    };

    $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));

    if (substr($host, 0, 4) === 'www.') {
        $host = substr($host, 4);
    }

    return array(
        'title'       => ws_link_preview_cut($pick(array('og:title', 'twitter:title'), $title), WS_LINK_PREVIEW_TITLE_MAX),
        'description' => ws_link_preview_cut($pick(array('og:description', 'description', 'twitter:description')), WS_LINK_PREVIEW_DESCRIPTION_MAX),
        'site_name'   => ws_link_preview_cut($pick(array('og:site_name'), $host), WS_LINK_PREVIEW_SITE_MAX),
    );
}

/** Most pages read in an hour, for the whole site. */
const WS_LINK_PREVIEW_HOURLY = 60;

/** Most rows one pass of the general job takes. */
const WS_LINK_PREVIEW_BATCH = 5;

/** A row still waiting after this many seconds is closed with no card. */
const WS_LINK_PREVIEW_WAIT_MAX = 86400;

/**
 * The page of this site a path names: its page_name, '' for the home page,
 * null when the address is not this site's or names no page (a file, an
 * address under the software's folder). Pure: the site's host and path are
 * given.
 *
 * @param string $url
 * @param string $host HOSTNAME_SETTING
 * @param string $path PATH, with its slashes
 * @return string|null
 */
function ws_link_preview_own_page($url, $host, $path)
{
    $parts = @parse_url((string) $url);
    $host = strtolower(trim((string) $host));

    if (!is_array($parts) || !isset($parts['host']) || ($host === '')) {
        return null;
    }

    $strip = function ($name) {
        $name = strtolower(rtrim((string) $name, '.'));

        return (substr($name, 0, 4) === 'www.') ? substr($name, 4) : $name;
    };

    if ($strip($parts['host']) !== $strip(preg_replace('/:\d+$/', '', $host))) {
        return null;
    }

    $base = '/' . trim((string) $path, '/');
    $base = ($base === '/') ? '/' : $base . '/';
    $request = isset($parts['path']) ? (string) $parts['path'] : '/';

    if ($request . '/' === $base) {
        $request = $base;
    }

    if (strpos($request, $base) !== 0) {
        return null;
    }

    $name = rawurldecode(trim(substr($request, strlen($base)), '/'));

    if ($name === '') {
        return '';
    }

    // A page name is one part of the path; a file or a folder is no page.
    if ((strpos($name, '/') !== false) || preg_match('/\.[a-z0-9]{2,5}$/i', $name)) {
        return null;
    }

    return $name;
}

/**
 * The page of this site an address names (ws_link_preview_own_page() with
 * the site's host and path), null for any other address.
 *
 * @param string $url
 * @return string|null
 */
function ws_link_preview_site_page($url)
{
    if (!defined('HOSTNAME_SETTING')) {
        return null;
    }

    return ws_link_preview_own_page($url, HOSTNAME_SETTING, defined('PATH') ? PATH : '/');
}

/**
 * Whether an address gets a row: this site's page (read from its data, so
 * its host may be a local one), or an address that may be read.
 *
 * @param string $url
 * @return bool
 */
function ws_link_preview_wanted($url)
{
    return ($url !== '') && ((ws_link_preview_site_page($url) !== null) || ws_link_preview_url_allowed($url));
}

/**
 * Whether the table is there (2026.11.0, step 11.0.220).
 *
 * @return bool
 */
function ws_link_preview_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('ws_link_previews');
    }

    return $ready;
}

/**
 * Whether link previews are read and drawn: the switch in Workspace Settings
 * (on unless set off), never on a demonstration site.
 *
 * @return bool
 */
function ws_link_preview_on()
{
    return !pg_demo() && (pg_config_text('ws_link_preview_off') !== '1') && ws_link_preview_ready();
}

/**
 * A message was sent: its first address waits for the general job. No call
 * is made here.
 *
 * @param int    $message_id
 * @param int    $channel_id
 * @param string $body
 * @return void
 */
function ws_link_preview_message_sent($message_id, $channel_id, $body)
{
    if (!ws_link_preview_on()) {
        return;
    }

    $url = ws_link_preview_first_url($body);

    if (!ws_link_preview_wanted($url)) {
        return;
    }

    db("INSERT INTO ws_link_previews (message_id, channel_id, url, state, created_at)
        VALUES ('" . (int) $message_id . "', '" . (int) $channel_id . "', '" . e($url) . "', 'waiting', '" . time() . "')
        ON DUPLICATE KEY UPDATE channel_id = VALUES(channel_id), url = VALUES(url), title = '', description = '',
            site_name = '', state = 'waiting', created_at = VALUES(created_at), read_at = 0");
}

/**
 * A message was edited: the same first address keeps its row (and a card
 * the writer removed stays removed); another one is read again; none takes
 * the row away.
 *
 * @param int    $message_id
 * @param int    $channel_id
 * @param string $body the new text
 * @return void
 */
function ws_link_preview_message_edited($message_id, $channel_id, $body)
{
    if (!ws_link_preview_ready()) {
        return;
    }

    $url = ws_link_preview_first_url($body);

    if (!ws_link_preview_wanted($url)) {
        ws_link_preview_message_deleted($message_id);

        return;
    }

    $kept = db_value("SELECT url FROM ws_link_previews WHERE message_id = '" . (int) $message_id . "'");

    if (($kept !== null) && ($kept !== false) && ((string) $kept === $url)) {
        return;
    }

    ws_link_preview_message_deleted($message_id);
    ws_link_preview_message_sent($message_id, $channel_id, $body);
}

/**
 * A message was deleted: its row goes.
 *
 * @param int $message_id
 * @return void
 */
function ws_link_preview_message_deleted($message_id)
{
    if (ws_link_preview_ready()) {
        db("DELETE FROM ws_link_previews WHERE message_id = '" . (int) $message_id . "'");
    }
}

/**
 * A channel's messages were removed for good (a discussion purged): its rows
 * go in one query.
 *
 * @param int $channel_id
 * @return void
 */
function ws_link_preview_channel_purged($channel_id)
{
    if (ws_link_preview_ready()) {
        db("DELETE FROM ws_link_previews WHERE channel_id = '" . (int) $channel_id . "'");
    }
}

/**
 * Whether the general job has a row to read: one small read.
 *
 * @return bool
 */
function ws_link_preview_due()
{
    return ws_link_preview_on()
        && ((int) db_value("SELECT COUNT(*) FROM ws_link_previews WHERE state = 'waiting' LIMIT 1") > 0);
}

/**
 * The query that closes the rows waiting over a day with no card. It reads
 * nothing, so it stamps no read_at: the hourly cap counts read_at of the last
 * hour, and a closed backlog would otherwise hold every read for an hour.
 * Pure.
 *
 * @param int $now
 * @return string
 */
function ws_link_preview_expire_sql($now)
{
    return "UPDATE ws_link_previews SET state = 'none'
        WHERE state = 'waiting' AND created_at < '" . ((int) $now - WS_LINK_PREVIEW_WAIT_MAX) . "'";
}

/**
 * The query that claims a row for one read: still waiting, not claimed, and
 * still for the address the pass read. A message edited to another address
 * in between has a new waiting row, which is left to the next pass. Pure.
 *
 * @param int    $message_id
 * @param string $url the address the pass read with the row
 * @param int    $now
 * @return string
 */
function ws_link_preview_claim_sql($message_id, $url, $now)
{
    return "UPDATE ws_link_previews SET read_at = '" . (int) $now . "'
        WHERE message_id = '" . (int) $message_id . "' AND state = 'waiting' AND read_at = 0 AND url = '" . e((string) $url) . "'";
}

/**
 * One pass of the general job: rows waiting over a day are closed, then a
 * few of the oldest are read, under the hourly cap. Each row is claimed
 * first (read_at set while still waiting), so two overlapping runs never
 * read one address twice.
 *
 * @param int $limit
 * @return int rows read
 */
function ws_link_preview_run($limit = WS_LINK_PREVIEW_BATCH)
{
    if (!ws_link_preview_on()) {
        return 0;
    }

    $now = time();

    db(ws_link_preview_expire_sql($now));

    $room = WS_LINK_PREVIEW_HOURLY - (int) db_value("SELECT COUNT(*) FROM ws_link_previews WHERE read_at > '" . ($now - 3600) . "'");
    $limit = min((int) $limit, $room);

    if ($limit <= 0) {
        return 0;
    }

    $done = 0;
    $started = microtime(true);

    foreach ((array) db_items("SELECT message_id, url FROM ws_link_previews
        WHERE state = 'waiting' AND read_at = 0 ORDER BY created_at, message_id LIMIT " . (int) $limit) as $row) {
        // A slow site must not hold the job's other passes.
        if ((microtime(true) - $started) > 20) {
            break;
        }

        $id = (int) $row['message_id'];

        db(ws_link_preview_claim_sql($id, (string) $row['url'], time()));

        if ((int) mysqli_affected_rows(db::$con) !== 1) {
            continue;
        }

        $message = db_item("SELECT body, deleted_at FROM ws_messages WHERE id = '" . $id . "'");

        // Deleted, or edited to another address since: the edit wrote its own row.
        if (!$message || ((int) $message['deleted_at'] > 0) || (ws_link_preview_first_url($message['body']) !== (string) $row['url'])) {
            if (!$message || ((int) $message['deleted_at'] > 0)) {
                ws_link_preview_message_deleted($id);
            }

            continue;
        }

        $fields = ws_link_preview_read((string) $row['url']);
        $shown = ($fields !== null) && ($fields['title'] !== '');

        db("UPDATE ws_link_previews SET
            title = '" . e($shown ? $fields['title'] : '') . "',
            description = '" . e($shown ? $fields['description'] : '') . "',
            site_name = '" . e($shown ? $fields['site_name'] : '') . "',
            state = '" . ($shown ? 'shown' : 'none') . "',
            read_at = '" . time() . "'
            WHERE message_id = '" . $id . "' AND state = 'waiting' AND url = '" . e($row['url']) . "'");

        // The open screens of the channel redraw the message with its card.
        if ($shown && function_exists('ws_message_touch')) {
            ws_message_touch($id);
        }

        $done++;
    }

    return $done;
}

/**
 * The card's fields of an address: from the site's own data with no call
 * when it is this site's page, else from the page read once. null when there
 * is nothing to show.
 *
 * @param string $url
 * @return array|null title, description, site_name
 */
function ws_link_preview_read($url)
{
    $page = ws_link_preview_site_page($url);

    if ($page !== null) {
        return ws_link_preview_own_fields($page);
    }

    return ws_link_preview_fetch($url);
}

/**
 * The card of this site's page, from the page table: its title (else its
 * name) and meta description. The home page takes the site's title. A page
 * in the recycle bin, or no page of that name, shows nothing; nor does a page
 * a signed-out visitor cannot open (ws_link_preview_own_card()).
 *
 * @param string $page_name '' for the home page
 * @return array|null
 */
function ws_link_preview_own_fields($page_name)
{
    $site = trim((string) (defined('ORGANIZATION_NAME') ? ORGANIZATION_NAME : ''));

    if ($site === '') {
        $site = trim((string) (defined('TITLE') ? TITLE : ''));
    }

    if ($site === '') {
        $site = defined('HOSTNAME_SETTING') ? (string) HOSTNAME_SETTING : '';
    }

    if ($page_name === '') {
        $title = trim((string) (defined('TITLE') ? TITLE : '')) ?: $site;

        return array(
            'title'       => ws_link_preview_cut($title, WS_LINK_PREVIEW_TITLE_MAX),
            'description' => '',
            'site_name'   => ws_link_preview_cut($site, WS_LINK_PREVIEW_SITE_MAX),
        );
    }

    $binned = function_exists('pg_designer_not_binned_sql') ? pg_designer_not_binned_sql('page.page_folder') : '';
    $row = db_item("SELECT page.page_name, page.page_title, page.page_meta_description, page.page_folder, folder.folder_archived
        FROM page
        LEFT JOIN folder ON folder.folder_id = page.page_folder
        WHERE page.page_name = '" . e($page_name) . "'" . $binned . " LIMIT 1");

    return ws_link_preview_own_card($row, is_array($row) ? (string) get_access_control_type($row['page_folder']) : '', $site);
}

/**
 * The card fields of one page row of this site, or null when the page is not
 * one every visitor opens. The card is stored once per message and shown to
 * every reader of the channel, a guest's channel included, so a private,
 * member, registration or guest page, or one in an archived folder, gives no
 * title or description: the page itself would answer such a reader with a
 * sign-in or an error. Pure.
 *
 * @param array|null $row    page_name, page_title, page_meta_description, folder_archived
 * @param string     $access get_access_control_type() of the page's folder
 * @param string     $site   the site's name for the card
 * @return array|null title, description, site_name
 */
function ws_link_preview_own_card($row, $access, $site)
{
    if (!is_array($row) || ((string) $access !== 'public') || ((string) ($row['folder_archived'] ?? '') === '1')) {
        return null;
    }

    $title = trim((string) ($row['page_title'] ?? ''));

    return array(
        'title'       => ws_link_preview_cut(($title !== '') ? $title : (string) ($row['page_name'] ?? ''), WS_LINK_PREVIEW_TITLE_MAX),
        'description' => ws_link_preview_cut((string) ($row['page_meta_description'] ?? ''), WS_LINK_PREVIEW_DESCRIPTION_MAX),
        'site_name'   => ws_link_preview_cut((string) $site, WS_LINK_PREVIEW_SITE_MAX),
    );
}

/**
 * Whole seconds left before the read's deadline, for the next hop's request;
 * 0 once it is spent. Pure.
 *
 * @param float $deadline
 * @param float $now
 * @return int
 */
function ws_link_preview_time_left($deadline, $now)
{
    $left = (float) $deadline - (float) $now;

    return ($left > 0) ? (int) min(WS_LINK_PREVIEW_TIMEOUT, ceil($left)) : 0;
}

/**
 * Read a page once: every hop checked and pinned by api_http_request()
 * (an address inside the network is refused, the resolved address is the
 * one called), redirects walked here at most WS_LINK_PREVIEW_REDIRECTS, only
 * an HTML answer, the body read up to the end of its head or the limit, the
 * whole wait, redirects included, bounded by WS_LINK_PREVIEW_TIMEOUT. null when it gives nothing.
 *
 * @param string $url
 * @return array|null
 */
function ws_link_preview_fetch($url)
{
    if (!function_exists('api_http_request')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');
    }

    if (!function_exists('curl_init')) {
        return null;
    }

    $complete = function ($body) {
        return (strlen($body) >= WS_LINK_PREVIEW_BODY_MAX) || (stripos($body, '</head>') !== false);
    };

    $deadline = microtime(true) + WS_LINK_PREVIEW_TIMEOUT;

    for ($hop = 0; $hop <= WS_LINK_PREVIEW_REDIRECTS; $hop++) {
        $url = ws_link_preview_ascii_url($url);
        $left = ws_link_preview_time_left($deadline, microtime(true));

        if ($left <= 0) {
            return null;
        }

        if (!ws_link_preview_url_allowed($url)) {
            return null;
        }

        $answer = api_http_request('GET', $url, array(
            'allow_http'       => true,
            'decode'           => true,
            'timeout'          => $left,
            'max_body'         => WS_LINK_PREVIEW_BODY_MAX,
            'response_headers' => true,
            'complete'         => $complete,
            'agent'            => 'link preview',
            'headers'          => array('Accept: text/html,application/xhtml+xml;q=0.9'),
        ));

        $status = (int) $answer['status'];
        $headers = isset($answer['headers']) ? (array) $answer['headers'] : array();

        if (($status >= 300) && ($status < 400)) {
            $url = ws_link_preview_redirect_target(isset($headers['location']) ? $headers['location'] : '', $url);

            if ($url === '') {
                return null;
            }

            continue;
        }

        if (($status < 200) || ($status >= 300)) {
            return null;
        }

        $type = isset($headers['content-type']) ? (string) $headers['content-type'] : '';

        if (!ws_link_preview_is_html($type)) {
            return null;
        }

        $body = (string) $answer['body'];

        // The limit reached before the page's head ended: too big to read.
        if ((strlen($body) >= WS_LINK_PREVIEW_BODY_MAX) && (stripos($body, '</head>') === false)) {
            return null;
        }

        return ws_link_preview_fields($body, $url, $type);
    }

    return null;
}

/**
 * Whether the person may switch link previews on or off: a Workspace member
 * with the Workspace settings right.
 *
 * @param array $viewer ws_viewer()
 * @return bool
 */
function ws_link_preview_settings_allowed($viewer)
{
    return !empty($viewer['member']) && !empty($viewer['settings']) && ws_link_preview_ready();
}

/**
 * The Link previews card of Site Settings (Channels): one
 * switch, posted with the pane's Save as ws_link_preview_on beside the
 * marker ws_link_preview_card (an unticked box posts nothing).
 *
 * @param array $viewer ws_viewer()
 * @return string HTML, '' when the person may not change it
 */
function ws_link_preview_settings_card($viewer)
{
    if (!ws_link_preview_settings_allowed($viewer)) {
        return '';
    }

    $on = (pg_config_text('ws_link_preview_off') !== '1');
    $state = ($on && !pg_demo())
        ? '<span class="pg-pill pg-pill-success">' . h(lang('On')) . '</span>'
        : '<span class="pg-pill">' . h(lang('Off')) . '</span>';

    return '
    <div id="pgset-ws-link-preview" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 d-flex flex-wrap align-items-center gap-3">
                <span class="pg-title-section ws-grow">' . h(lang('Link previews')) . '</span>
                ' . $state . '
            </div>
            <div class="card-body">
                <input type="hidden" name="ws_link_preview_card" value="1">
                <div class="row">
                    <div class="col-12">
                        <div class="form-field">
                            <div class="switch"><input type="checkbox" name="ws_link_preview_on" value="1" id="ws_link_preview_on"' . ($on ? ' checked' : '') . '></div>
                            <label for="ws_link_preview_on">' . h(lang('Show a card under a message\'s web address')) . '</label>
                            <div class="form-text">' . h(lang('The server reads the first web address of a new message once and keeps its title, description and site name for the card. Addresses inside the site\'s own network are never read. Off, nothing is read and no card is shown.'))
                            . (pg_demo() ? ' ' . h(lang('A demonstration site reads no web addresses.')) : '') . '</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

/**
 * The switch's save, from the posted fields: nothing when the card was not
 * in the form or the person may not change it.
 *
 * @param array $viewer ws_viewer()
 * @param array $post
 * @return bool whether it was written
 */
function ws_link_preview_settings_save($viewer, $post)
{
    if (empty($post['ws_link_preview_card']) || !ws_link_preview_settings_allowed($viewer)) {
        return false;
    }

    $off = empty($post['ws_link_preview_on']) ? '1' : '';

    if (pg_config_text('ws_link_preview_off') === $off) {
        return false;
    }

    return (bool) pg_config_text_save('ws_link_preview_off', $off);
}

/**
 * The fields of a message's card, from its ws_link_previews row: the plain
 * texts as stored, trimmed. null for a row not read into a card (waiting,
 * none, refused, removed), with no title or with an address that is not a
 * web address. The screen's card and the API both read these. Pure.
 *
 * @param array|null $row url, title, description, site_name, state
 * @return array|null url, title, description, site_name
 */
function ws_link_preview_card_fields($row)
{
    if (!is_array($row) || (($row['state'] ?? '') !== 'shown')) {
        return null;
    }

    $url = (string) ($row['url'] ?? '');
    $title = trim((string) ($row['title'] ?? ''));

    if (($title === '') || !preg_match('#^https?://#i', $url)) {
        return null;
    }

    return array(
        'url'         => $url,
        'title'       => $title,
        'description' => trim((string) ($row['description'] ?? '')),
        'site_name'   => trim((string) ($row['site_name'] ?? '')),
    );
}

/**
 * The card under a message: the site name, the title and the description
 * (cut at two lines by the stylesheet), the whole card a link opened in a
 * new tab. '' when ws_link_preview_card_fields() gives none. Every text is
 * escaped here.
 *
 * @param array|null $row url, title, description, site_name, state
 * @return string HTML
 */
function ws_link_preview_card_html($row)
{
    $card = ws_link_preview_card_fields($row);

    if ($card === null) {
        return '';
    }

    return '<a class="ws-link-card" href="' . h($card['url']) . '" target="_blank" rel="noopener noreferrer">'
        . (($card['site_name'] !== '') ? '<span class="ws-link-card-site">' . h($card['site_name']) . '</span>' : '')
        . '<span class="ws-link-card-title">' . h($card['title']) . '</span>'
        . (($card['description'] !== '') ? '<span class="ws-link-card-text">' . h($card['description']) . '</span>' : '')
        . '</a>';
}

/**
 * The card fields of these messages, one query for the page: message id =>
 * ws_link_preview_card_fields(). Empty when previews are switched off (or on
 * a demonstration site): no card anywhere then, whatever rows there are.
 *
 * @param int[] $ids
 * @return array
 */
function ws_link_preview_card_rows_map($ids)
{
    $ids = array_values(array_filter(array_map('intval', (array) $ids)));

    if (empty($ids) || !ws_link_preview_on()) {
        return array();
    }

    $cards = array();

    foreach ((array) db_items("SELECT message_id, url, title, description, site_name, state FROM ws_link_previews
        WHERE state = 'shown' AND message_id IN (" . implode(',', $ids) . ")") as $row) {
        $card = ws_link_preview_card_fields($row);

        if ($card !== null) {
            $cards[(int) $row['message_id']] = $card;
        }
    }

    return $cards;
}

/**
 * The cards of these messages as the screen draws them: message id => HTML
 * (ws_link_preview_card_rows_map()).
 *
 * @param int[] $ids
 * @return array
 */
function ws_link_preview_cards_map($ids)
{
    $cards = array();

    foreach (ws_link_preview_card_rows_map($ids) as $id => $card) {
        $cards[$id] = ws_link_preview_card_html($card + array('state' => 'shown'));
    }

    return $cards;
}

/**
 * Whether the person may take a message's card away: only the person who
 * wrote it, while it stands and its card is drawn. Pure.
 *
 * @param array $viewer  ws_viewer()
 * @param array $message the ws_messages row
 * @param bool  $card    whether the message has a card now
 * @return bool
 */
function ws_link_preview_can_remove($viewer, $message, $card)
{
    return $card
        && is_array($message)
        && (($message['sender_kind'] ?? '') === 'user')
        && ((int) ($message['sender_id'] ?? 0) > 0)
        && ((int) ($message['sender_id'] ?? 0) === (int) ($viewer['id'] ?? 0))
        && ((int) ($message['deleted_at'] ?? 0) === 0);
}

/**
 * "Remove preview": the writer takes the card away for everyone. The row is
 * kept as removed, so the same address is not read again; an edit to another
 * address reads that one. The message is touched, so open screens redraw it.
 *
 * @param array $viewer
 * @param array $message
 * @return array ok, error
 */
function ws_link_preview_remove($viewer, $message)
{
    $cards = is_array($message) ? ws_link_preview_cards_map(array((int) $message['id'])) : array();

    if (!ws_link_preview_can_remove($viewer, $message, !empty($cards))) {
        return array('ok' => false, 'error' => lang('Only the person who wrote the message can remove its preview.'));
    }

    db("UPDATE ws_link_previews SET state = 'removed', title = '', description = '', site_name = ''
        WHERE message_id = '" . (int) $message['id'] . "' AND state = 'shown'");

    if (function_exists('ws_message_touch')) {
        ws_message_touch((int) $message['id']);
    }

    return array('ok' => true, 'error' => '');
}

/**
 * The texts of the card's menu entry.
 *
 * @return array key => text
 */
function ws_link_preview_js_strings()
{
    return array(
        'link_preview_remove' => lang('Remove preview'),
    );
}
