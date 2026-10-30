<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_recurring_commission_profiles');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/commission_list.php');

// The rows come one page at a time from the commission_profile_list_rows
// action (includes/panel/lists.php), searched and counted on the server.
$list_sort = pg_commission_profile_list_sort();
$sort_columns = $list_sort['sort_columns'];
$sort_default = $list_sort['sort_default'];
$sort_levels = $list_sort['sort_levels'];

print
pg_page_shell([
        'title'=> lang('All Commission Profiles'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Commission Profiles'),
        'heading_description' => lang('View all profiles for recurring commissions.'),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
           
            <div class="row mb-3  flex-wrap">
                <div class="col-12 text-center md:text-start">
                    
                    <nav id="button_bar" class="navigation " aria-label="' . h(lang('Button Bar')) . '">
                        <div class="pg-toolbar-grow"></div>
                        ' . pg_list_sort_menu('view_recurring_commission_profiles.php', $sort_columns, $sort_levels, $sort_default) . '
                    </nav>
                </div>
            </div>
            <div class="card my-7">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none" data-pg-list-action="commission_profile_list_rows">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                <th>' . get_column_heading(lang('Affiliate Name'), $sort_levels, '', '', 'affiliate_name') . '</th>
                                <th>' . get_column_heading(lang('Affiliate Code'), $sort_levels, '', '', 'affiliate_code') . '</th>
                                <th class="text-center">' . get_column_heading(lang('Enabled'), $sort_levels, '', '', 'enabled') . '</th>
                                <th class="text-end">' . get_column_heading(lang('Amount'), $sort_levels, '', '', 'amount') . '</th>
                                <th>' . get_column_heading(lang('Start Date'), $sort_levels, '', '', 'start_date') . '</th>
                                <th>' . get_column_heading(lang('Frequency'), $sort_levels, '', '', 'frequency') . '</th>
                                <th>' . get_column_heading(lang('Commissions'), $sort_levels, '', '', 'commissions') . '</th>
                                <th>' . get_column_heading(lang('Order'), $sort_levels, '', '', 'order_number') . '</th>
                                <th>' . get_column_heading(lang('Product'), $sort_levels, '', '', 'product') . '</th>
                                <th>' . get_column_heading(lang('Created'), $sort_levels, '', '', 'created') . '</th>
                                <th>' . get_column_heading(lang('Last Modified'), $sort_levels, '', '', 'last_modified') . '</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
?>