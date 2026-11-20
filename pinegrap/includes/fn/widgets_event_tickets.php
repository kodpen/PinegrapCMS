<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The my account widget's tickets (site output, Bootstrap 5.3.8): the
 * signed-in member's orders that hold event tickets, each with its signed
 * link to the tickets page (tickets.php, event_tickets_page.php), as the
 * section "tickets", the flag has_tickets and the list account.tickets.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The most orders the my account tickets section lists. */
define('PG_EVENT_TICKETS_ACCOUNT_LIMIT', 50);

/**
 * The signed-in member's orders that hold tickets, newest first, with how
 * many each holds, how many are still valid and the events' names.
 *
 * @param int $user_id
 * @return array rows of id, order_number, order_date, tickets, valid, titles
 */
function pg_event_tickets_account_rows($user_id)
{
    if (((int) $user_id <= 0) || !pg_event_tickets_ready()) {
        return array();
    }

    return (array) db_items(
        "SELECT
            orders.id,
            orders.order_number,
            orders.order_date,
            COUNT(event_tickets.id) AS tickets,
            SUM(event_tickets.state = 'valid') AS valid,
            GROUP_CONCAT(DISTINCT COALESCE(NULLIF(calendar_events.name, ''), NULLIF(products.short_description, ''), products.name)
                ORDER BY event_tickets.order_item_id SEPARATOR ', ') AS titles
        FROM orders
        JOIN event_tickets ON event_tickets.order_id = orders.id
        LEFT JOIN calendar_events ON calendar_events.id = event_tickets.calendar_event_id
        LEFT JOIN products ON products.id = event_tickets.product_id
        WHERE orders.user_id = '" . (int) $user_id . "'
        GROUP BY orders.id, orders.order_number, orders.order_date
        ORDER BY orders.order_date DESC, orders.id DESC
        LIMIT " . PG_EVENT_TICKETS_ACCOUNT_LIMIT);
}

/**
 * The my account view of those orders: each with its signed tickets link,
 * its date written out and its words. Pure but for the link's base, which
 * it is given.
 *
 * @param array    $rows        pg_event_tickets_account_rows()
 * @param string   $key         the tickets key
 * @param string   $base        the tickets page's address, '…/tickets.php'
 * @param string   $date_format
 * @return array rows of order_id, order_number, date, count, valid, titles,
 *               url, count_label, state_label, tone
 */
function pg_event_tickets_account_view(array $rows, $key, $base, $date_format)
{
    $view = array();

    foreach ($rows as $row) {
        $token = pg_event_tickets_link_token((int) $row['id'], $key);

        if ($token === '') {
            continue;
        }

        $count = (int) $row['tickets'];
        $valid = (int) $row['valid'];

        $view[] = array(
            'order_id'     => (int) $row['id'],
            'order_number' => (string) $row['order_number'],
            'date'         => ((int) $row['order_date'] > 0) ? date($date_format, (int) $row['order_date']) : '',
            'count'        => $count,
            'valid'        => $valid,
            'titles'       => (string) $row['titles'],
            'url'          => $base . '?o=' . (int) $row['id'] . '&t=' . $token,
            'count_label'  => lang(array('string' => '{var:1} tickets', 'vars' => array($count))),
            'state_label'  => ($valid > 0) ? lang(array('string' => '{var:1} valid', 'vars' => array($valid))) : lang('None valid'),
            'tone'         => ($valid > 0) ? 'success' : 'secondary',
        );
    }

    return $view;
}

/**
 * The my account tickets section: one link per order to its tickets page
 * (Bootstrap 5.3.8, the site's). Pure; the designer canvas draws its twin
 * (_sdAccountTicketsHtml()).
 *
 * @param array $view pg_event_tickets_account_view()
 * @return string HTML, '' without tickets
 */
function pg_event_tickets_account_html(array $view)
{
    if (!$view) {
        return '';
    }

    $html = '<div class="list-group pg-sw-tickets">';

    foreach ($view as $row) {
        $html .= '<a href="' . h($row['url']) . '" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2">'
            . '<span><span class="fw-semibold">' . h($row['titles']) . '</span>'
            . '<span class="d-block small text-body-secondary">#' . h($row['order_number']) . ' · ' . h($row['date']) . ' · ' . h($row['count_label']) . '</span></span>'
            . '<span class="badge text-bg-' . h($row['tone']) . '">' . h($row['state_label']) . '</span>'
            . '</a>';
    }

    return $html . '</div>';
}

/**
 * The rows of the account.tickets list, as pg_sw_list_publish('account')
 * files them: array('account.tickets' => array($account_id => rows)).
 * Plain words, escaped once by the pass.
 *
 * @param int   $account_id
 * @param array $view pg_event_tickets_account_view()
 * @return array
 */
function pg_sw_list_account_ticket_rows($account_id, array $view)
{
    $rows = array();

    foreach ($view as $row) {
        $rows[] = array(
            '__tickets_order_no'    => (string) $row['order_number'],
            '__tickets_order_date'  => (string) $row['date'],
            '__tickets_events'      => (string) $row['titles'],
            '__tickets_count'       => (string) (int) $row['count'],
            '__tickets_count_label' => (string) $row['count_label'],
            '__tickets_valid'       => (string) (int) $row['valid'],
            '__tickets_state_label' => (string) $row['state_label'],
            '__tickets_tone'        => (string) $row['tone'],
            '__tickets_url'         => (string) $row['url'],
        );
    }

    return array('account.tickets' => array((int) $account_id => $rows));
}

/**
 * The my account widget's tickets: the view, the section and the flag,
 * empty without the tickets table or key.
 *
 * @param int $user_id
 * @return array view, section, has
 */
function pg_event_tickets_account($user_id)
{
    $key = pg_event_tickets_ready() ? pg_event_tickets_key() : '';

    if ($key === '') {
        return array('view' => array(), 'section' => '', 'has' => false);
    }

    $base = (defined('OUTPUT_PATH') ? OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY : '') . '/tickets.php';
    $view = pg_event_tickets_account_view(pg_event_tickets_account_rows((int) $user_id), $key, $base, pg_sw_default_date_format('date'));

    return array('view' => $view, 'section' => pg_event_tickets_account_html($view), 'has' => !empty($view));
}
