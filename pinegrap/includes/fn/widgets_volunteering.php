<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The my_account widget's "volunteering" section: the signed-in member's
 * volunteer programmes (includes/fn/volunteer_page_view.php) in the site's
 * look, Bootstrap 5.3.8. Loaded by _pg_my_account_extras() when the member
 * has a volunteer profile.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_page_view.php');

/**
 * The classes of the site's look (the my account section). Pure.
 *
 * @return array
 */
function pg_volunteer_page_site_classes()
{
    return array(
        'box'    => 'card mb-4 pg-sw-volunteering',
        'body'   => 'card-body',
        'list'   => 'list-group mb-3',
        'item'   => 'list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2',
        'meta'   => 'small text-body-secondary',
        'pill'   => 'badge text-bg-',
        'tones'  => array('ok' => 'success', 'wait' => 'warning', 'info' => 'info', 'off' => 'secondary'),
        'quiet'  => 'btn btn-sm btn-outline-secondary',
        'fill'   => 'btn btn-sm btn-primary',
        'danger' => 'btn btn-sm btn-outline-danger',
        'check'  => 'form-check form-switch mb-0',
        'switch' => 'form-check-input',
        'label'  => 'form-check-label',
        'select' => 'form-select form-select-sm w-auto',
    );
}

/**
 * The my account "volunteering" section (the site's look): the member's
 * programmes, each button posting to volunteer.php, which sends the member
 * back to $back.
 *
 * @param array  $ids  pg_volunteer_account_ids()
 * @param string $back the page's own path
 * @return string HTML, '' without a programme
 */
function pg_volunteer_account_html(array $ids, $back)
{
    $now = date('Y-m-d H:i:s');
    $views = array();

    foreach ($ids as $id) {
        $view = pg_volunteer_self_view($id, $now);

        if ($view !== null) {
            $views[] = $view;
        }
    }

    return pg_volunteer_page_html($views, array(
        'action' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/volunteer.php',
        'hidden' => get_token_field() . '<input type="hidden" name="send_to" value="' . h($back) . '">',
    ), pg_volunteer_page_site_classes());
}
