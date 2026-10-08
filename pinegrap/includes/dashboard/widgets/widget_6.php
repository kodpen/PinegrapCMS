<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 6 - Trending Content: the most visited content of the last seven days.
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

function pg_dashboard_widget_6($request, $user)
{
    $output_rows = '';
    if (($user['role'] < 3) || ($user['manage_visitors'] == true)) {
        $timestamp_7_days_ago = strtotime('-7 days');
        $timestamp_1_day_ago = strtotime('-1 day');

        $output_rows = '';

        // Read from the hourly rollup rather than counting raw
        // visitor rows. Beyond the cost, this is what makes the
        // list useful: entries now name the article or product
        // that was read, where before every blog post in the site
        // collapsed into a single row called 'blog-gorunum'.
        //
        // Grouping by page_id and item also removes the need for
        // pg_home_page_group_expression() here — the home page's
        // several recorded spellings share one page_id, so they no
        // longer split their own traffic between two rows.

        // --- Get Top Pages (last 7 days)
        $top_pages = [];
        foreach (pg_visitor_top_content(date('Y-m-d', $timestamp_7_days_ago), date('Y-m-d'), 5) as $row) {
            $top_pages[] = ['name' => $row['label'], 'url' => $row['url'], 'visits' => (int) $row['views']];
        }

        // --- Get Trend Page (last 1 day)
        $trend_page = null;
        $trend_rows = pg_visitor_top_content(date('Y-m-d', $timestamp_1_day_ago), date('Y-m-d'), 1);
        if (!empty($trend_rows)) {
            $trend_page = [
                'name'   => $trend_rows[0]['label'],
                'url'    => $trend_rows[0]['url'],
                'visits' => (int) $trend_rows[0]['views'],
            ];
        }

        // --- Mark/merge trend page
        // Purpose: Mark if trend is in Top 5; else add as 6th row
        if ($trend_page) {
            $found = false;
            foreach ($top_pages as &$pg) {
                if ($pg['name'] === $trend_page['name']) {
                    $pg['trend'] = true;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $trend_page['trend'] = true;
                $top_pages[] = $trend_page;
            }
        }

        // --- Get Top 5 Products (all time)
        $top_products = [];
        if ((ECOMMERCE === true) and (($user['role'] < 3) or USER_MANAGE_ECOMMERCE or USER_MANAGE_ECOMMERCE_REPORTS)) {

            $query = "
                SELECT
                    p.id,
                    MAX(p.short_description) AS product_name,
                    MAX(p.image_name) AS image_name,
                    SUM(oi.quantity) AS total_qty
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN products p ON p.id = oi.product_id
                WHERE o.status = 'complete'
                GROUP BY p.id
                ORDER BY total_qty DESC
                LIMIT 5
            ";

            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            while ($row = mysqli_fetch_assoc($result)) {
                $top_products[] = [
                    'id' => (int) $row['id'],
                    'product_name' => h($row['product_name']),
                    'image_name' => $row['image_name'],
                    'qty' => (int) $row['total_qty'],
                ];
            }
        }



        // --- Render Pages
        $output_rows .= pg_widget_row_heading(lang('Top Pages'), lang('Total Visits'));
        foreach ($top_pages as $pg) {
            $trend_badge = !empty($pg['trend'])
                ? ' <i class="bi bi-fire text-danger ms-1" title="' . lang('Trending Page') . '"></i>'
                : '';
            $output_rows .= pg_widget_row(array(
                'small'  => true,
                'href'   => h($pg['url']),
                'target' => '_blank',
                'badge'  => '<i class="bi bi-window"></i>',
                'name'   => h($pg['name']) . $trend_badge,
                'aside'  => pg_format_number($pg['visits'], 0),
            ));
        }

        // --- Render Products
        if (!empty($top_products)) {
            $output_rows .= pg_widget_row_heading(lang('Top Products'), lang('Total Sales'));

            foreach ($top_products as $pr) {
                $output_rows .= pg_widget_row(array(
                    'small' => true,
                    'href'  => 'edit_product.php?id=' . $pr['id'],
                    'badge' => $pr['image_name']
                        ? '<img src="' . PATH . h($pr['image_name']) . '" alt="">'
                        : '<i class="bi bi-box-seam"></i>',
                    'name'  => $pr['product_name'],
                    'aside' => pg_format_number($pr['qty'], 0),
                ));
            }
        }

        // Neither list had anything in it. Without this the card
        // drew an empty .pg-list and read as a card that had failed
        // to load rather than as a site with no traffic yet.
        if ($output_rows === '') {
            $output_rows = pg_widget_empty(
                'bi-fire',
                lang('There is not enough data yet.'));
        }

        // --- Final HTML for widget body
        $output_data = '
        <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
            <div class="pg-list">' . $output_rows . '</div>
        </div>';

        $response = [
            'status' => 'success',
            'message' => lang('Data Received successfully.'),
            'data' => $output_data
        ];
        return $response;
    } else {
        $response = array(
            'status' => 'error',
            'message' => 'Access denied'
        );
        return $response;
    }
}
