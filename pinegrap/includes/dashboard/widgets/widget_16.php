<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 16 - Current Site Exchange Rates.
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

function pg_dashboard_widget_16($request, $user)
{
    $output_rows = '';
    // if e-commerce is enabled and the user has access to manage e-commerce
    if ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {

        // get all of the currency information. Join user_id with username
        $query = "SELECT
            currencies.id,
            currencies.name,
            currencies.base,
            currencies.code,
            currencies.symbol,
            currencies.exchange_rate,
            currencies.created_user_id,
            currencies.created_timestamp,
            currencies.last_modified_user_id,
            currencies.last_modified_timestamp,
            last_modified_user.user_username as last_modified_username
        FROM currencies
        LEFT JOIN user as last_modified_user ON currencies.last_modified_user_id = last_modified_user.user_id
        ORDER BY base DESC,name DESC LIMIT 20";

        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        while ($row = mysqli_fetch_assoc($result)) {
            $currencies[] = $row;
        }
        if (!empty($currencies)) {
            // if there is at least one result to display  
            foreach ($currencies as $currency) {

                $output_link_url = 'edit_currency.php?id=' . $currency['id'] . '&amp;send_to=' . h(escape_javascript(urlencode(REQUEST_URL)));
                if ($currency['base'] != 1) {
                    $rate_display = ((float) $currency['exchange_rate'] > 0) ? pg_format_number((1 / $currency['exchange_rate']), 5) : '-';
                    $output_rows .= pg_widget_row(array(
                        'href'  => $output_link_url,
                        'badge' => $currency['symbol'],
                        'name'  => h($currency['code']) . ' &mdash; ' . h($currency['name']),
                        'aside' => h($currency['exchange_rate']),
                        'meta'  => '1 ' . $currency['symbol'] . ' = ' . $rate_display . ' ' . BASE_CURRENCY_SYMBOL,
                    ));
                }
            }
        } else {
            $output_rows = pg_widget_empty('bi-currency-exchange', lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Currency'))));
        }

        $output_data = '
            <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
                <div class="pg-list">' . $output_rows . '</div>
            </div>';

        //return success json output
        $response = array(
            'status' => 'success',
            'message' => 'Action Success',
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
