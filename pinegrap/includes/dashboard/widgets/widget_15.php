<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 15 - Offers: active and upcoming offers by end date.
 *
 * Loaded and called by includes/dashboard/widgets.php; see that file for the
 * contract every widget follows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_WIDGETS')) {
    exit;
}

function pg_dashboard_widget_15($request, $user)
{
    $output_rows = '';
    if ((ECOMMERCE === true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {

        $today = date('Y-m-d');
        $deadline = date('Y-m-d', strtotime('+7 days'));

        require_once(PG_FUNCTIONS_DIR . '/edit_offer_f.php');

        // Current and upcoming offers by end date, then the latest expired
        // ones apart: in one list ordered by end date, sixty expired offers
        // used to push every open-ended (2099-12-31) offer out of it.
        $current_offers = db_items(
            "SELECT offers.id, offers.code, offers.description, offers.status,
                    offers.start_date, offers.end_date
             FROM offers
             WHERE offers.end_date >= '" . e($today) . "'
                AND offers.status = 'enabled'
                AND offers.start_date <= '" . e($today) . "'
             ORDER BY offers.end_date ASC, offers.id ASC
             LIMIT 40"
        );

        $expired = db_items(
            "SELECT offers.id, offers.code, offers.description, offers.status,
                    offers.start_date, offers.end_date
             FROM offers
             WHERE offers.end_date < '" . e($today) . "'
             ORDER BY offers.end_date DESC, offers.id ASC
             LIMIT 20"
        );

        $expiring = array();
        $active = array();

        foreach ($current_offers as $offer) {
            if ($offer['end_date'] <= $deadline) {
                if (count($expiring) < 20)
                    $expiring[] = $offer;
            } elseif (count($active) < 20) {
                $active[] = $offer;
            }
        }

        // "Automatic - cart is 100.00 or more -> %100 off shipping":
        // the same sentence the offer list and the editor show, so
        // an offer reads the same wherever it appears. Built for
        // every row at once - a load per offer would be four
        // queries times sixty.
        $offer_sentences = _pg_offer_sentences(array_merge(
            array_column($expiring, 'id'),
            array_column($active, 'id'),
            array_column($expired, 'id')));

        $output_rows = '';

        // ── Section: Expiring Soon (only shown when non-empty) ────
        if (!empty($expiring)) {
            $output_rows .= pg_widget_row_heading('<i class="bi bi-alarm-fill fg-danger"></i> <span class="fg-danger">' . lang('Expiring Soon') . '</span>', lang('Days Left'));
            foreach ($expiring as $offer) {
                $days_left = (int) _pg_offer_days_left($offer['end_date'], $today);
                // Three days or fewer is the point at which an offer
                // needs attention today rather than this week, so the
                // tile turns red instead of amber.
                $badge_color = $days_left <= 3 ? '#ef4444' : '#f59e0b';
                $output_rows .= pg_widget_row(array(
                    'href'  => 'edit_offer.php?id=' . $offer['id'],
                    'color' => $badge_color,
                    'badge' => '<i class="bi bi-alarm"></i>',
                    'name'  => h($offer['code'] !== '' ? $offer['code'] : lang('(no code)')),
                    'aside' => $days_left,
                    'meta'  => h(isset($offer_sentences[(int) $offer['id']]) ? $offer_sentences[(int) $offer['id']] : '—'),
                ));
            }
        }

        // ── Section: Active Offers (only shown when non-empty) ────
        if (!empty($active)) {
            $output_rows .= pg_widget_row_heading('<i class="bi bi-tag-fill fg-success"></i> <span class="fg-success">' . lang('Active Offers') . '</span>', lang('Days Left'));
            foreach ($active as $offer) {
                $days_left = _pg_offer_days_left($offer['end_date'], $today);
                $output_rows .= pg_widget_row(array(
                    'href'  => 'edit_offer.php?id=' . $offer['id'],
                    'color' => '#10b981',
                    'badge' => '<i class="bi bi-tag-fill"></i>',
                    'name'  => h($offer['code'] !== '' ? $offer['code'] : lang('(no code)')),
                    'aside' => ($days_left === null) ? h(lang('Open-ended')) : $days_left,
                    'meta'  => h(isset($offer_sentences[(int) $offer['id']]) ? $offer_sentences[(int) $offer['id']] : '—'),
                ));
            }
        }

        // ── Section: Expired (only shown when non-empty) ──────────
        if (!empty($expired)) {
            $output_rows .= pg_widget_row_heading(lang('Expired'), lang('End Date'));
            foreach ($expired as $offer) {
                $output_rows .= pg_widget_row(array(
                    'href'  => 'edit_offer.php?id=' . $offer['id'],
                    'color' => '#94a3b8',
                    'badge' => '<i class="bi bi-tag"></i>',
                    'name'  => h($offer['code'] !== '' ? $offer['code'] : lang('(no code)')),
                    'aside' => h(prepare_form_data_for_output($offer['end_date'], 'date', false)),
                    'meta'  => h(isset($offer_sentences[(int) $offer['id']]) ? $offer_sentences[(int) $offer['id']] : '—'),
                ));
            }
        }

        // ── Global empty state ────────────────────────────────────
        if ($output_rows === '') {
            $output_rows = pg_widget_empty('bi-tag', lang('No offers right now.'));
        }

        $output_data = '
        <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
            <div class="pg-list">' . $output_rows . '</div>
        </div>';

        $response = array(
            'status' => 'success',
            'message' => lang('Data Received successfully.'),
            'data' => $output_data,
        );
        return $response;

    } else {
        $response = array(
            'status' => 'error',
            'message' => 'Access denied'
        );
        return $response;
    }
}
