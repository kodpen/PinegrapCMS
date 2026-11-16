<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visitor notifications in the panel: the "Notifications" card on
 * Communication › Channels and the screen "Send a notification"
 * (messages_notifications.php) with its list of sent messages.
 *
 * The screen is for role <= 2, as the Channels tab is. It writes a message
 * through pg_site_push_message_send() (includes/fn/site_push.php): news to
 * every subscriber, or a test to this browser when the browser itself is
 * subscribed to the site. "Pick a page or record" reads the site search
 * index (search_items), which holds the public pages and records with their
 * address, title and description. The list shows news and tests; a stock or
 * order message goes to one device and is not listed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_push.php');

/**
 * The screen's address, from the panel.
 */
define('PG_SITE_PUSH_SCREEN', 'messages_notifications.php');

/**
 * The message kinds the screen lists: news and tests.
 *
 * @return int[]
 */
function pg_site_push_listed_kinds()
{
    return array(PG_SITE_PUSH_KIND_NEWS, PG_SITE_PUSH_KIND_TEST);
}

/**
 * What the card shows, read now.
 *
 * @return array  ready, on, subscribers, last_title, last_at
 */
function pg_site_push_card_facts()
{
    $facts = array('ready' => pg_site_push_ready(), 'on' => pg_site_push_switch_on(), 'subscribers' => 0, 'last_title' => '', 'last_at' => 0);

    if (!$facts['ready']) {
        return $facts;
    }

    $facts['subscribers'] = (int) db_value("SELECT COUNT(*) FROM site_push_subscription");
    $last = db_item("SELECT title, created_at FROM site_push_message WHERE kind = '" . PG_SITE_PUSH_KIND_NEWS . "' ORDER BY created_at DESC, id DESC LIMIT 1");

    if ($last) {
        $facts['last_title'] = (string) $last['title'];
        $facts['last_at'] = (int) $last['created_at'];
    }

    return $facts;
}

/**
 * The "Notifications" card of the Channels tab: on while the switch is on,
 * with the number of subscribers and the last news sent. Pure but for the
 * date's wording.
 *
 * @param array $facts  pg_site_push_card_facts()
 * @return array  a pg_messages_channel_cards() card
 */
function pg_site_push_channel_card(array $facts)
{
    $ready = !empty($facts['ready']);
    $on = $ready && !empty($facts['on']);
    $parts = array();

    if (!$ready) {
        $parts[] = lang('Visitor notifications are not available yet; run the upgrade.');
    } else {
        $parts[] = lang(array('string' => '{var:1} subscriber(s)', 'vars' => array(pg_format_number((int) $facts['subscribers'], 0))));
        $title = isset($facts['last_title']) ? (string) $facts['last_title'] : '';

        if ($title === '') {
            $parts[] = lang('No notification sent yet');
        } else {
            $when = (!empty($facts['last_at']) && function_exists('get_relative_time'))
                ? get_relative_time(array('timestamp' => (int) $facts['last_at'], 'format' => 'plain_text', 'mid_sentence' => true))
                : '';
            $parts[] = ($when !== '')
                ? lang(array('string' => 'Last: {var:1}, {var:2}', 'vars' => array($title, $when)))
                : lang(array('string' => 'Last: {var:1}', 'vars' => array($title)));
        }

        if (!$on) {
            $parts[] = lang('Off in Features');
        }
    }

    return array(
        'key'         => 'site_push',
        'title'       => lang('Notifications'),
        'icon'        => 'bi-bell',
        'tone'        => 'blue',
        'state'       => $on ? 'on' : 'off',
        'state_label' => $on ? lang('On') : lang('Off'),
        'line'        => implode(' · ', $parts),
        'url'         => PG_SITE_PUSH_SCREEN,
    );
}

/**
 * Search index rows as the picker's items: pages and records of this site,
 * never a file or an address elsewhere; the description cut to the text's
 * 200 characters. The index keeps an address relative to the site's root
 * ("spring", "shoes?r=7"), so the base path goes in front. Pure.
 *
 * @param array[]  $rows   url, title, description, page_id, page_name
 * @param string[] $hosts  the site's own host names
 * @param string   $base   the site's base path (OUTPUT_PATH), ending in /
 * @return array[]  title, body, url, kind (page or record)
 */
function pg_site_push_pick_items(array $rows, array $hosts, $base = '/')
{
    $items = array();

    foreach ($rows as $row) {
        $kind = function_exists('pg_search_item_type') ? pg_search_item_type($row) : ((int) $row['page_id'] > 0 ? 'page' : 'file');

        if (!in_array($kind, array('page', 'record'), true)) {
            continue;
        }

        $url = isset($row['url']) ? (string) $row['url'] : '';

        if (($url !== '') && !preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) && ($url[0] !== '/')) {
            $url = '/' . trim((string) $base, '/') . '/' . $url;
            $url = preg_replace('#^/{2,}#', '/', $url);
        }

        $url = pg_site_push_url_path($url, $hosts);

        if ($url === '') {
            continue;
        }

        $items[] = array(
            'title' => mb_substr(trim((string) $row['title']), 0, 60, 'UTF-8'),
            'body'  => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $row['description'])), 0, 200, 'UTF-8'),
            'url'   => $url,
            'kind'  => $kind,
        );
    }

    return $items;
}

/**
 * The picker's items for a search term: at most 10 live index rows whose
 * title or address holds it.
 *
 * @param string $term
 * @return array[]  pg_site_push_pick_items()
 */
function pg_site_push_pick($term)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_search.php');

    $term = trim((string) $term);

    if ((mb_strlen($term, 'UTF-8') < 2) || !pg_schema_has('search_items', 'url')) {
        return array();
    }

    $like = '%' . escape_like($term) . '%';
    $binned = function_exists('pg_recycle_bin_folder_ids') ? pg_recycle_bin_folder_ids() : array();
    $result = db_q("SELECT search_items.url, search_items.title, search_items.description, search_items.page_id, sp.page_name
        FROM search_items LEFT JOIN page AS sp ON sp.page_id = search_items.page_id
        WHERE (search_items.title LIKE ? OR search_items.url LIKE ?)" . pg_search_items_live_sql($binned) . "
        ORDER BY search_items.title
        LIMIT 30", array($like, $like));
    $rows = array();

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $rows[] = $row;
    }

    return array_slice(pg_site_push_pick_items($rows, pg_site_push_hosts(), defined('OUTPUT_PATH') ? OUTPUT_PATH : '/'), 0, 10);
}

/**
 * The subscription of this browser, by the endpoint its own push
 * subscription reports, or 0.
 *
 * @param mixed $endpoint
 * @return int
 */
function pg_site_push_subscription_by_endpoint($endpoint)
{
    if (!is_string($endpoint) || (pg_site_push_endpoint_service($endpoint) === '') || !pg_site_push_ready()) {
        return 0;
    }

    return (int) db_value("SELECT id FROM site_push_subscription WHERE endpoint_hash = '" . e(hash('sha256', trim($endpoint))) . "'");
}

/**
 * The sentence for an error code of pg_site_push_message_send() or the
 * screen. Pure.
 *
 * @param string $code
 * @return string
 */
function pg_site_push_send_error($code)
{
    $texts = array(
        'off'          => lang('Visitor notifications are off. Turn them on in Site Settings, Features.'),
        'kind'         => lang('The notification was not sent.'),
        'subscription' => lang('This browser is no longer subscribed to the site\'s notifications.'),
        'device'       => lang('This browser is not subscribed to the site\'s notifications. Turn them on with Notify me on the site, then try again.'),
        'save'         => lang('The notification could not be saved. Try again.'),
        'title'        => lang('Write a title of at most 60 characters.'),
        'body'         => lang('The text can be at most 200 characters.'),
        'url'          => lang('The link must be an address of this site.'),
    );

    return isset($texts[$code]) ? $texts[$code] : lang('The notification was not sent.');
}

/**
 * The list's columns.
 *
 * @return array
 */
function pg_site_push_list_columns()
{
    return array(
        'message' => array('heading' => lang('Message')),
        'kind'    => array('heading' => lang('Type'), 'kind' => 'badge'),
        'sent_at' => array('heading' => lang('Sent'), 'kind' => 'date'),
        'queued'  => array('heading' => lang('Subscribers')),
        'sent'    => array('heading' => lang('Delivered')),
        'failed'  => array('heading' => lang('Failed')),
        'removed' => array('heading' => lang('Removed')),
    );
}

/**
 * The sent news and tests, newest first, as the list table driver's printed
 * source.
 *
 * @param array $levels  the list state's sorts
 * @return array  pg_list_table_printed()
 */
function pg_site_push_list($levels)
{
    $messages = pg_site_push_ready()
        ? db_items("SELECT id, title, body, url, kind, created_at, queued_count, sent_count, failed_count, removed_count
            FROM site_push_message
            WHERE kind IN (" . implode(', ', pg_site_push_listed_kinds()) . ")
            ORDER BY created_at DESC, id DESC
            LIMIT 500")
        : array();
    $rows = array();
    $number = function ($value) {
        return pg_list_cell(h(pg_format_number((int) $value, 0)), 'align-middle text-end', array('data-pg-sort-value' => (int) $value));
    };

    foreach ($messages as $message) {
        $test = ((int) $message['kind'] === PG_SITE_PUSH_KIND_TEST);

        $rows[] = array(
            'id' => (int) $message['id'],
            'cells' => array(
                'message' => pg_list_cell('<div class="pg-list-row-title">' . h((string) $message['title']) . '</div>'
                    . '<div class="pg-text-meta text-break">' . h(implode(' · ', array_filter(array((string) $message['body'], (string) $message['url']), 'strlen'))) . '</div>'),
                'kind' => pg_list_cell(pg_messages_sends_pill($test ? lang('Test') : lang('News'), $test ? '' : 'theme-info'), 'align-middle', array('data-pg-sort-value' => (int) $message['kind'])),
                'sent_at' => pg_list_table_when_cell($message['created_at'], ''),
                'queued' => $number($message['queued_count']),
                'sent' => $number($message['sent_count']),
                'failed' => $number($message['failed_count']),
                'removed' => $number($message['removed_count']),
            ),
        );
    }

    return pg_list_table_printed(array(
        'list' => 'site_push_messages',
        'columns' => pg_site_push_list_columns(),
        'levels' => $levels,
        'rows' => $rows,
    ));
}

/**
 * The send form's card: title, text, link with "Pick a page or record", the
 * send button naming the subscribers, and "Send a test to this device",
 * which the script turns on only when this browser is subscribed.
 *
 * @param array $options  token, subscribers, enabled, values (title, body, url)
 * @return string
 */
function pg_site_push_form_html(array $options)
{
    $values = isset($options['values']) && is_array($options['values']) ? $options['values'] : array();
    $value = function ($key) use ($values) {
        return isset($values[$key]) ? h((string) $values[$key]) : '';
    };
    $disabled = empty($options['enabled']) ? ' disabled' : '';
    $count = (int) (isset($options['subscribers']) ? $options['subscribers'] : 0);

    return '<section class="pg-card pg-messages-card mb-5"><div class="pg-messages-card-head"><div class="pg-messages-card-heading">'
        . '<h2 class="pg-title-section">' . h(lang('Send a notification')) . '</h2>'
        . '<p class="pg-text-meta mb-0">' . h(lang('It reaches every browser that pressed Notify me on the site, even when the site is closed.')) . '</p></div></div>'
        . '<form method="post" class="pg-site-push-form" data-pg-site-push data-scope="' . h(defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . '" data-pick="' . h(PG_SITE_PUSH_SCREEN) . '?pick=">'
        . (string) $options['token']
        . '<input type="hidden" name="endpoint" value="">'
        . '<div class="mb-4"><label class="form-label" for="site-push-pick">' . h(lang('Pick a page or record')) . '</label>'
        . '<input class="form-control" type="search" id="site-push-pick" autocomplete="off" placeholder="' . h(lang('Search the site\'s pages and records')) . '"' . $disabled . '>'
        . '<div class="list-group mt-2" data-pg-site-push-results aria-live="polite"></div></div>'
        . '<div class="mb-4"><label class="form-label" for="site-push-title">' . h(lang('Title')) . '</label>'
        . '<input class="form-control" type="text" id="site-push-title" name="title" maxlength="60" required value="' . $value('title') . '"' . $disabled . '>'
        . '<div class="form-text">' . h(lang('At most 60 characters.')) . '</div></div>'
        . '<div class="mb-4"><label class="form-label" for="site-push-body">' . h(lang('Text')) . '</label>'
        . '<textarea class="form-control" id="site-push-body" name="body" rows="3" maxlength="200"' . $disabled . '>' . $value('body') . '</textarea>'
        . '<div class="form-text">' . h(lang('At most 200 characters.')) . '</div></div>'
        . '<div class="mb-4"><label class="form-label" for="site-push-url">' . h(lang('Link')) . '</label>'
        . '<input class="form-control" type="text" id="site-push-url" name="url" maxlength="500" placeholder="/" value="' . $value('url') . '"' . $disabled . '>'
        . '<div class="form-text">' . h(lang('An address of this site; empty opens the home page.')) . '</div></div>'
        . '<div class="d-flex flex-wrap gap-3 align-items-center">'
        . '<button type="submit" class="btn btn-solid theme-primary rounded-pill px-4" name="send" value="news"' . (($count < 1) ? ' disabled' : $disabled) . '><i class="bi bi-send me-2" aria-hidden="true"></i>'
        . h(lang(array('string' => 'Send to {var:1} subscriber(s)', 'vars' => array(pg_format_number($count, 0))))) . '</button>'
        . '<button type="submit" class="btn btn-outline theme-secondary rounded-pill px-4" name="send" value="test" data-pg-site-push-test disabled><i class="bi bi-phone me-2" aria-hidden="true"></i>' . h(lang('Send a test to this device')) . '</button>'
        . '<span class="pg-text-meta" data-pg-site-push-device data-on="' . h(lang('This browser is subscribed; a test reaches it.')) . '">' . h(lang('This browser is not subscribed to the site\'s notifications.')) . '</span>'
        . '</div></form></section>';
}
