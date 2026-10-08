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

        // Single query: fetch all relevant offers ordered by end_date
        $all_offers = db_items(
            "SELECT offers.id, offers.code, offers.description, offers.status,
                    offers.start_date, offers.end_date
             FROM offers
             ORDER BY offers.end_date ASC
             LIMIT 60"
        );

        $expiring = array();
        $active = array();
        $expired = array();

        foreach ($all_offers as $offer) {
            if ($offer['end_date'] < $today) {
                if (count($expired) < 20)
                    $expired[] = $offer;
            } elseif (
                $offer['status'] === 'enabled'
                && $offer['start_date'] <= $today
                && $offer['end_date'] <= $deadline
            ) {
                if (count($expiring) < 20)
                    $expiring[] = $offer;
            } elseif (
                $offer['status'] === 'enabled'
                && $offer['start_date'] <= $today
            ) {
                if (count($active) < 20)
                    $active[] = $offer;
            }
        }

        // Sort expired by end_date DESC (most recently expired first)
        usort($expired, function ($a, $b) {
            return strcmp($b['end_date'], $a['end_date']);
        });

        // "Automatic - cart is 100.00 or more -> %100 off shipping":
        // the same sentence the offer list and the editor show, so
        // an offer reads the same wherever it appears. Built for
        // every row at once - a load per offer would be four
        // queries times sixty.
        require_once(PG_FUNCTIONS_DIR . '/edit_offer_f.php');
        $offer_sentences = _pg_offer_sentences(array_merge(
            array_column($expiring, 'id'),
            array_column($active, 'id'),
            array_column($expired, 'id')));

        $output_rows = '';

        // ── Section: Expiring Soon (only shown when non-empty) ────
        if (!empty($expiring)) {
            $output_rows .= pg_widget_row_heading('<i class="bi bi-alarm-fill text-danger"></i> <span class="text-danger">' . lang('Expiring Soon') . '</span>', lang('Days Left'));
            foreach ($expiring as $offer) {
                $days_left = max(0, (int) ceil((strtotime($offer['end_date']) - strtotime($today)) / 86400));
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
            $output_rows .= pg_widget_row_heading('<i class="bi bi-tag-fill text-success"></i> <span class="text-success">' . lang('Active Offers') . '</span>', lang('Days Left'));
            foreach ($active as $offer) {
                $days_left = max(0, (int) ceil((strtotime($offer['end_date']) - strtotime($today)) / 86400));
                $output_rows .= pg_widget_row(array(
                    'href'  => 'edit_offer.php?id=' . $offer['id'],
                    'color' => '#10b981',
                    'badge' => '<i class="bi bi-tag-fill"></i>',
                    'name'  => h($offer['code'] !== '' ? $offer['code'] : lang('(no code)')),
                    'aside' => $days_left,
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
                    'aside' => h($offer['end_date']),
                    'meta'  => h(isset($offer_sentences[(int) $offer['id']]) ? $offer_sentences[(int) $offer['id']] : '—'),
                ));
            }
        }

        // ── Global empty state ────────────────────────────────────
        if ($output_rows === '') {
            $output_rows = pg_widget_empty(
                'bi-tag',
                lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Offer'))));
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
