<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Incoming e-mail card: the unread count of the menu's Communication row
 * with a link to the tab, then the newest conversations of the tab's
 * Incoming view as the screen lists them (ws_mail_list() with the view's
 * defaults), so the card never shows a conversation the screen hides. A row
 * names the sender (bold while unread), the subject and the time, and opens
 * the conversation on the screen. Offered to whoever may open the tab
 * (pg_dashboard_v2_mail_gate()); reading the list marks nothing read.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

/**
 * How many conversations the card lists.
 */
if (!defined('PG_DASHBOARD_V2_MAIL_SHOWN')) {
    define('PG_DASHBOARD_V2_MAIL_SHOWN', 8);
}

/**
 * The card's rows from ws_mail_list() rows, in the list's order: who and
 * the subject as the screen's row names them (ws_mail_list_row_names()),
 * the kind of a WhatsApp or SMS conversation in the subject's place, the
 * time as the screen prints it, whether it is unread for the reader, and
 * the screen's address with the conversation open. Plain text: the
 * renderer writes it with textContent. Pure but for the clock of
 * ws_time_label().
 *
 * @param array[] $rows ws_mail_list() rows
 * @param string  $base the panel's address, ending in "/"
 * @return array[] id, who, subject, kind, time, unread, url
 */
function pg_dashboard_v2_mail_rows($rows, $base)
{
    $filters = ws_mail_list_filters(array());
    $kinds = ws_mail_transport_labels();
    $out = array();

    foreach (array_slice((array) $rows, 0, PG_DASHBOARD_V2_MAIL_SHOWN) as $row) {
        $names = ws_mail_list_row_names($row);

        $out[] = array(
            'id'      => (int) $row['id'],
            'who'     => $names['who'],
            'subject' => $names['subject'],
            'kind'    => ($names['transport'] !== 'email') ? (string) ($kinds[$names['transport']] ?? '') : '',
            'time'    => ws_time_label($names['last']),
            'unread'  => !empty($row['unread']),
            'url'     => $base . ws_mail_list_page_url($filters, (int) $row['id']),
        );
    }

    return $out;
}

function pg_dashboard_v2_card_mail($user, $period, $compare)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_area.php');

    $visible = pg_messages_mail_visible($user);

    if (!pg_dashboard_v2_mail_gate($visible, $visible && ws_mail_reads_ready())) {
        return array('status' => 'denied');
    }

    $viewer = ws_viewer($user);
    // The tab opens on Incoming, open conversations of every box and kind.
    $filters = ws_mail_list_filters(array());
    $list = ws_mail_list($viewer, $filters, time(), PG_DASHBOARD_V2_MAIL_SHOWN);
    $unread = pg_messages_mail_unread($user);
    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/';

    return array('status' => 'success', 'data' => array(
        'rows'   => pg_dashboard_v2_mail_rows($list['rows'], $base),
        'unread' => $unread,
        'count'  => ($unread > 0) ? lang(array('string' => '{var:1} unread', 'vars' => $unread)) : lang('Nothing unread'),
        'url'    => $base . 'messages_mail.php',
        'link'   => lang('Open Incoming e-mail'),
        'labels' => array(
            'unread' => lang('Unread'),
            'none'   => lang('No e-mail waiting'),
        ),
    ));
}
