<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace: where a new e-mail conversation goes. A mailbox may send mail
 * written to one of its addresses (an alias delivered into the same box) or
 * to a "+tag" of its own address (support+billing@…) to a channel of its
 * own; anything else goes to the box's channel. A sender the box's server
 * verified and the site knows (a contact or a user account) also gets a
 * line in the channels about that customer, pointing at the conversation.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most routes one mailbox keeps. */
define('WS_MAIL_ROUTES_MAX', 10);

/** The most customer channels one new conversation writes a line into. */
define('WS_MAIL_CUSTOMER_LINES_MAX', 3);

/**
 * A route's address as typed, cleaned: "+tag" (letters, digits, dot,
 * dash, underscore) or a whole address, lower case.
 *
 * @param string $value
 * @return string '' when it is neither
 */
function ws_mail_route_match($value)
{
    $value = strtolower(trim((string) $value));

    if (strncmp($value, '+', 1) === 0) {
        return preg_match('/^\+[a-z0-9][a-z0-9._-]{0,63}$/', $value) ? $value : '';
    }

    return ((strlen($value) <= 255) && filter_var($value, FILTER_VALIDATE_EMAIL)) ? $value : '';
}

/**
 * A box's routes as kept in its settings, cleaned.
 *
 * @param array|null $box
 * @return array[] list of match, channel_id
 */
function ws_mail_routes($box)
{
    $settings = ws_mail_box_settings($box);
    $out = array();

    foreach ((array) ($settings['routes'] ?? array()) as $route) {
        $match = ws_mail_route_match(is_array($route) ? ($route['match'] ?? '') : '');
        $channel_id = is_array($route) ? (int) ($route['channel_id'] ?? 0) : 0;

        if (($match !== '') && ($channel_id > 0) && (count($out) < WS_MAIL_ROUTES_MAX)) {
            $out[] = array('match' => $match, 'channel_id' => $channel_id);
        }
    }

    return $out;
}

/**
 * Every address a mail was written to: To and Cc, and the envelope's
 * recipient where the receiving server wrote it (Delivered-To,
 * X-Original-To, Envelope-To), since a +tag or an alias often shows only
 * there when the mail came as a blind copy or through a forward.
 *
 * @param array $parsed pg_mail_parse()
 * @return string[] lower case, each once
 */
function ws_mail_recipients($parsed)
{
    $out = array();

    foreach (array_merge((array) ($parsed['to'] ?? array()), (array) ($parsed['cc'] ?? array())) as $address) {
        $out[] = strtolower(trim((string) ($address['email'] ?? '')));
    }

    foreach (array('delivered-to', 'x-original-to', 'envelope-to') as $name) {
        foreach ((array) ($parsed['headers'][$name] ?? array()) as $value) {
            foreach (pg_mail_address_list((string) $value) as $address) {
                $out[] = strtolower(trim((string) $address['email']));
            }
        }
    }

    return array_values(array_unique(array_filter($out, 'strlen')));
}

/**
 * The channel a new conversation goes to by the box's routes: the first
 * route, in the box's order, whose address the mail was written to. A
 * "+tag" route matches only the box's own address with that tag.
 *
 * @param array[]  $routes      ws_mail_routes()
 * @param string[] $recipients  ws_mail_recipients()
 * @param string   $box_address
 * @return int channel id, 0 for the box's own channel
 */
function ws_mail_route_pick($routes, $recipients, $box_address)
{
    $box_address = strtolower(trim((string) $box_address));
    $at = strrpos($box_address, '@');
    $local = ($at === false) ? '' : substr($box_address, 0, $at);
    $domain = ($at === false) ? '' : substr($box_address, $at + 1);

    foreach ((array) $routes as $route) {
        $match = (string) $route['match'];
        $wanted = (strncmp($match, '+', 1) === 0) ? (($local !== '') ? $local . $match . '@' . $domain : '') : $match;

        if (($wanted !== '') && in_array($wanted, (array) $recipients, true)) {
            return (int) $route['channel_id'];
        }
    }

    return 0;
}

/**
 * The routes of a box form as posted, checked. Pure: the channels a person
 * may choose are given; a route keeps a channel its editor cannot read, as
 * the box's own channel does, when it was already there.
 *
 * @param array $matches     route_match[] as posted
 * @param array $channel_ids route_channel[] as posted
 * @param int[] $allowed     channel ids the editor may choose
 * @param int[] $kept        channel ids the box's routes already name
 * @return array ok, error, routes (list of match, channel_id)
 */
function ws_mail_routes_input($matches, $channel_ids, $allowed, $kept)
{
    $routes = array();
    $seen = array();
    $channel_ids = array_values((array) $channel_ids);

    foreach (array_values((array) $matches) as $index => $value) {
        $value = is_scalar($value) ? trim((string) $value) : '';
        $channel_id = is_scalar($channel_ids[$index] ?? 0) ? (int) ($channel_ids[$index] ?? 0) : 0;

        // An empty row is no route.
        if (($value === '') && ($channel_id <= 0)) {
            continue;
        }

        $match = ws_mail_route_match($value);

        if ($match === '') {
            return array('ok' => false, 'error' => lang('A route is an address such as billing@example.com or a tag of the mailbox\'s address such as +billing.'), 'routes' => array());
        }

        if (($channel_id <= 0) || (!in_array($channel_id, (array) $allowed, true) && !in_array($channel_id, (array) $kept, true))) {
            return array('ok' => false, 'error' => lang('Choose the channel for each route.'), 'routes' => array());
        }

        if (isset($seen[$match])) {
            return array('ok' => false, 'error' => lang(array('string' => '{var:1} has two routes.', 'vars' => $match)), 'routes' => array());
        }

        if (count($routes) >= WS_MAIL_ROUTES_MAX) {
            return array('ok' => false, 'error' => lang(array('string' => 'A mailbox has at most {var:1} routes.', 'vars' => WS_MAIL_ROUTES_MAX)), 'routes' => array());
        }

        $seen[$match] = true;
        $routes[] = array('match' => $match, 'channel_id' => $channel_id);
    }

    return array('ok' => true, 'error' => '', 'routes' => $routes);
}

/**
 * The routing half of a box's form: the kept routes, then empty rows up to
 * three more (never past WS_MAIL_ROUTES_MAX).
 *
 * @param string     $prefix
 * @param array|null $box
 * @param array[]    $channels ws_mail_box_channels()
 * @return string HTML
 */
function ws_mail_route_fields($prefix, $box, $channels)
{
    $routes = ws_mail_routes($box);
    $rows = $routes;

    for ($i = 0; ($i < 3) && (count($rows) < WS_MAIL_ROUTES_MAX); $i++) {
        $rows[] = array('match' => '', 'channel_id' => 0);
    }

    $html = '
                <div class="md:col-12"><h3 class="h6 mt-3 mb-0">' . h(lang('Routing')) . '</h3>
                    <div class="form-text">' . h(lang('A new conversation written to one of these addresses goes to its channel instead; the first route that fits wins. A tag such as +billing stands for the mailbox\'s address with that tag (support+billing@…). Answers stay in their conversation\'s channel.')) . '</div>
                </div>';

    foreach ($rows as $index => $route) {
        $channel_id = (int) $route['channel_id'];
        $options = '<option value="">' . h(lang('Choose')) . '</option>';

        if (($channel_id > 0) && !isset($channels[$channel_id])) {
            $options .= '<option value="' . $channel_id . '" selected>' . h(lang('A channel you do not see')) . '</option>';
        }

        foreach ($channels as $channel) {
            $options .= '<option value="' . (int) $channel['id'] . '"' . (((int) $channel['id'] === $channel_id) ? ' selected' : '') . '>'
                . h('#' . $channel['name']) . '</option>';
        }

        $id = $prefix . 'route_' . $index;
        $html .= '
                <div class="md:col-6">
                    <label class="form-label visually-hidden" for="' . $id . '">' . h(lang('Address or tag')) . '</label>
                    <input class="form-control form-control-sm font-monospace" type="text" name="route_match[]" id="' . $id . '" maxlength="255" value="' . h((string) $route['match']) . '" placeholder="' . h(lang('billing@example.com or +billing')) . '" autocomplete="off">
                </div>
                <div class="md:col-6">
                    <label class="form-label visually-hidden" for="' . $id . '_channel">' . h(lang('Channel')) . '</label>
                    <select class="form-control form-control-sm" name="route_channel[]" id="' . $id . '_channel">' . $options . '</select>
                </div>';
    }

    return $html;
}

/**
 * The channel a new conversation of this box goes to: a route's channel
 * while it is there and not archived, else the box's own.
 *
 * @param array $box
 * @param array $parsed pg_mail_parse()
 * @return array|null channel row
 */
function ws_mail_route_channel($box, $parsed)
{
    $picked = ws_mail_route_pick(ws_mail_routes($box), ws_mail_recipients($parsed), (string) ($box['address'] ?? ''));
    $channel = ($picked > 0) ? ws_channel($picked) : null;

    if ($channel && ((int) $channel['archived_at'] === 0)) {
        return $channel;
    }

    return ws_channel((int) ($box['channel_id'] ?? 0));
}

/**
 * The text of the line a known customer's channel gets: who wrote, where
 * the conversation is (a channel tag, drawn for each reader by what they
 * may see), and the subject with any tag a sender could type taken apart.
 *
 * @param string $name       the sender's name or address
 * @param int    $channel_id the conversation's channel
 * @param string $subject
 * @return string
 */
function ws_mail_customer_line_text($name, $channel_id, $subject)
{
    $plain = function ($text) {
        return trim(preg_replace('/<([@#][a-z_]+:[0-9]+)>/i', '$1', preg_replace('/\s+/u', ' ', (string) $text)));
    };
    $subject = mb_substr($plain($subject), 0, 200);

    return lang(array('string' => 'An e-mail from {var:1} came into {var:2}: {var:3}', 'vars' => array(
        mb_substr($plain($name), 0, 200),
        '<#channel:' . (int) $channel_id . '>',
        ($subject !== '') ? $subject : lang('(no subject)'),
    )));
}

/**
 * Writes the line about a new conversation into the channels about its
 * customer (ws_customer_channels()): open or private channels only, never
 * a guest room or a discussion, never the conversation's own channel, at
 * most WS_MAIL_CUSTOMER_LINES_MAX, the most recently used first.
 *
 * @param array  $conversation ws_mail_conversations row (contact_id or user_id set)
 * @param string $name         the sender's name or address
 * @return int lines written
 */
function ws_mail_customer_lines($conversation, $name)
{
    if (!function_exists('ws_customer_channels')) {
        return 0;
    }

    if ((int) $conversation['contact_id'] > 0) {
        $channels = ws_customer_channels('contact', (int) $conversation['contact_id']);
    } elseif ((int) $conversation['user_id'] > 0) {
        $channels = ws_customer_channels('user_account', (int) $conversation['user_id']);
    } else {
        return 0;
    }

    $text = ws_mail_customer_line_text($name, (int) $conversation['channel_id'], (string) $conversation['subject']);
    $written = 0;

    foreach ($channels as $channel) {
        if (($written >= WS_MAIL_CUSTOMER_LINES_MAX) || ((int) $channel['id'] === (int) $conversation['channel_id'])
            || !in_array((string) $channel['kind'], array('public', 'private'), true)) {
            continue;
        }

        if (ws_message_system((int) $channel['id'], $text) > 0) {
            $written++;
        }
    }

    return $written;
}
