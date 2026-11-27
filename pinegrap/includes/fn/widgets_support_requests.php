<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The my account widget's support requests (site output, Bootstrap 5.3.8):
 * the signed-in member's requests, each with its signed link to the
 * request's page (support_request.php, support_requests.php), as the
 * section "requests" and the flag has_requests.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/support_requests.php');

/**
 * The My Account requests section: one link per request to its page
 * (Bootstrap 5.3.8, the site's). Pure; the designer canvas draws its twin
 * (_sdAccountRequestsHtml()).
 *
 * @param array $view pg_support_request_account_view()
 * @return string HTML, '' without requests
 */
function pg_support_request_account_html(array $view)
{
    if (!$view) {
        return '';
    }

    $html = '<div class="list-group pg-sw-requests">';

    foreach ($view as $row) {
        $answer = ($row['last_answer'] !== '') ? lang(array('string' => 'Last answer {var:1}', 'vars' => array($row['last_answer']))) : lang('Not answered yet');
        $html .= '<a href="' . h($row['url']) . '" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2">'
            . '<span><span class="fw-semibold">' . h($row['subject']) . '</span>'
            . '<span class="d-block small text-body-secondary">' . h($row['number']) . ' · ' . h($answer) . '</span></span>'
            . '<span class="badge text-bg-' . h($row['tone']) . '">' . h($row['label']) . '</span>'
            . '</a>';
    }

    return $html . '</div>';
}

/**
 * The my account widget's requests: the view, the section and the flag.
 *
 * @param int $user_id
 * @return array view, section, has
 */
function pg_support_request_account($user_id)
{
    $key = pg_support_request_key();
    $view = ($key === '') ? array() : pg_support_request_account_view(pg_support_request_account_rows((int) $user_id), $key,
        pg_support_request_base(false), pg_sw_default_date_format('date'));

    return array('view' => $view, 'section' => pg_support_request_account_html($view), 'has' => !empty($view));
}
