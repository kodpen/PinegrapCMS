<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The products list (view_products.php), shared by the screen, its export,
 * its bulk actions (edit_products.php) and the list table driver's rows
 * action (list_table_rows, includes/panel/lists.php).
 *
 * A view is one of the filter menu's choices: its condition, the columns it
 * shows (in order) and the columns it sorts by. The screen draws the
 * headings from the same column list the rows are built from, so a header
 * row and a body row cannot disagree about which cells there are. A
 * product in the Recycle Bin is on no view.
 *
 * Loaded where it is needed (require_once), not by functions.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The views the filter menu offers, with their conditions. The SEO views are
 * offered only once the columns they read exist.
 *
 * @return array filter => array('label', 'description', 'where' => SQL or '')
 */
function pg_product_list_filters()
{
    $filters = array(
        'all_products' => array(
            'label' => lang('All Products'),
            'description' => lang('Merchandise, downloads, donations, recurring fees, memberships, and simple payments.'),
            'where' => ''),
        'all_product_actions' => array(
            'label' => lang('Actions After an Order'),
            'description' => lang('What each product does once it is ordered: e-mails, memberships, access and forms.'),
            'where' => ''),
        'shippable_products' => array(
            'label' => lang('Shippable Products'),
            'description' => lang('All products that can be shipped to a recipient.'),
            'where' => "products.shippable = '1'"),
        'recurring_products' => array(
            'label' => lang('Recurring Products'),
            'description' => lang('All products that require a recurring payment.'),
            'where' => "products.recurring = '1'"),
        'donation_products' => array(
            'label' => lang('Donation Products'),
            'description' => lang('All products that allow donors to enter their own amount and optionally set their donation schedule.'),
            'where' => "products.selection_type = 'donation'"),
        'grant_access_products' => array(
            'label' => lang('Grant Access Products'),
            'description' => lang('All products that grant access a private folder\'s pages and files.'),
            'where' => "products.grant_private_access = '1' AND (products.private_folder != '0' OR products.send_to_page != '0')"),
        'membership_products' => array(
            'label' => lang('Membership Products'),
            'description' => lang('All products that grant access to all member folders, and set or extend their membership days.'),
            'where' => "products.membership_renewal != '0'"),
        'out_of_stock_products' => array(
            'label' => lang('All Out of Stock Products'),
            'description' => lang('Products that are out of stock, including those sold out by customer orders.'),
            'where' => "products.out_of_stock = '1'"),
    );

    if (pg_seo_schema_ready()) {
        // Uncalculated rows are excluded: their stored 0 means unknown.
        $filters['seo_weak_products'] = array(
            'label' => lang('Products With a Weak SEO Score'),
            'description' => lang('Products scoring under 55 out of 100 on title, description, keywords and content.'),
            'where' => '(products.seo_score < 55) AND (products.seo_analysis_current = 1)');

        if (pg_seo_structure_schema_ready()) {
            // Bit 32768 marks at least one error-level structure finding.
            $filters['seo_struct_error_products'] = array(
                'label' => lang('Products With HTML Structure Errors'),
                'description' => lang('Products whose description markup has a serious fault, such as invalid nesting or images with no alt text.'),
                'where' => '((products.seo_flags & 32768) != 0)');
        }
    }

    return $filters;
}

/**
 * The price change methods of the Mass Change window (edit_products.php),
 * label => value. Each label shows a sample written the way the panel writes
 * an amount (pg_format_money()) and a percent ("{var:1}%"), so the sample
 * follows the site's separators and currency symbol.
 *
 * @param string $symbol The base currency symbol as stored (HTML-safe; an
 *                       entity such as &#8378; is printed as it is)
 * @return array label => '' (prompt), '2' increase, '1' decrease,
 *               '4' increase by percent, '3' decrease by percent
 */
function pg_product_mass_price_method_options($symbol)
{
    $amount = pg_format_money(30, (string) $symbol);
    $percent = lang(array('string' => '{var:1}%', 'vars' => array(pg_format_number(30, 0))));

    return array(
        lang(array('string' => 'Select a {var:1}', 'vars' => array(lang('Price Change Method')))) => '',
        lang(array('string' => 'Increase by an amount (e.g. {var:1})', 'vars' => array('+ ' . $amount))) => '2',
        lang(array('string' => 'Decrease by an amount (e.g. {var:1})', 'vars' => array('- ' . $amount))) => '1',
        lang(array('string' => 'Increase by a percentage (e.g. {var:1})', 'vars' => array('+ ' . $percent))) => '4',
        lang(array('string' => 'Decrease by a percentage (e.g. {var:1})', 'vars' => array('- ' . $percent))) => '3',
    );
}

/**
 * The view a request or the session names: all_products when the name is
 * not a view on this site (an SEO view before its upgrade, a stale value).
 *
 * @param mixed $filter
 * @return string
 */
function pg_product_list_filter_name($filter)
{
    if (!is_string($filter) || !array_key_exists($filter, pg_product_list_filters())) {
        return 'all_products';
    }

    return $filter;
}

/**
 * The columns a view shows after the check box, the action buttons and the
 * picture, in order.
 *
 * @param string $filter         pg_product_list_filter_name()'s answer
 * @param bool   $tax            whether the site charges tax (ECOMMERCE_TAX)
 * @param bool   $compare_at     whether the list draws the "was" price
 * @param array  $custom_labels  the custom product fields' labels, 1 to 4
 * @return array list of column codes
 */
function pg_product_list_column_codes($filter, $tax, $compare_at, $custom_labels)
{
    $in = function ($filters) use ($filter) {
        return in_array($filter, $filters, true);
    };

    $codes = array('id', 'short_description', 'enabled', 'price');

    if ($compare_at) {
        $codes[] = 'compare_at_price';
    }

    if ($filter != 'out_of_stock_products') {
        $codes[] = 'inventory_quantity';
    }

    if ($tax && ($filter != 'all_product_actions')) {
        $codes[] = 'taxable';
    }

    if ($filter != 'all_product_actions') {
        $codes[] = 'product_form';
    }

    if ($in(array('all_products', 'out_of_stock_products', 'seo_weak_products', 'seo_struct_error_products'))) {
        $codes = array_merge($codes, array('selection_type', 'default_quantity', 'shippable'));

        // A custom field with no label has no column.
        foreach (array(1, 2, 3, 4) as $number) {
            if ((string) ($custom_labels[$number] ?? '') !== '') {
                $codes[] = 'custom_field_' . $number;
            }
        }
    }

    if ($filter == 'shippable_products') {
        $codes = array_merge($codes, array('weight', 'pwp', 'swp', 'dim', 'cont_req', 'prep', 'free_ship', 'extra_ship', 'allowed_zones', 'disallowed_zones'));
    }

    if ($in(array('all_products', 'donation_products', 'membership_products', 'seo_weak_products', 'seo_struct_error_products'))) {
        $codes[] = 'recurring_payment';
    }

    if ($in(array('recurring_products', 'donation_products'))) {
        $codes = array_merge($codes, array('start', 'number_of_payments', 'payment_period'));
    }

    if ($filter == 'donation_products') {
        $codes[] = 'allow_to_schedule';
    }

    if ($in(array('grant_access_products', 'membership_products', 'all_product_actions'))) {
        $codes[] = 'set_start_page';
    }

    if ($in(array('grant_access_products', 'all_product_actions'))) {
        $codes[] = 'grant_private_access';
    }

    if ($in(array('membership_products', 'all_product_actions'))) {
        $codes[] = 'membership_renewal';
    }

    if ($filter == 'all_product_actions') {
        $codes = array_merge($codes, array('order_receipt_message', 'order_receipt_bcc', 'email_page', 'email_page_bcc', 'contact_group'));
    }

    $codes[] = 'impact';
    $codes[] = 'last_modified';

    if ($filter == 'out_of_stock_products') {
        $codes[] = 'out_of_stock_date';
    }

    return $codes;
}

/**
 * The columns a view can be sorted by, as the sort menu and the headings'
 * links offer them.
 *
 * @param string $filter
 * @param bool   $compare_at whether the list draws the "was" price
 * @return array list of sort codes
 */
function pg_product_list_sort_codes($filter, $compare_at)
{
    switch ($filter) {
        case 'all_product_actions':
            $codes = array('id', 'short_description', 'enabled', 'price', 'set_start_page', 'grant_private_access', 'membership_renewal', 'order_receipt_message', 'order_receipt_bcc', 'email_page', 'email_page_bcc', 'contact_group', 'seo', 'impact', 'inventory_quantity', 'last_modified');
            break;

        case 'shippable_products':
            $codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'weight', 'pwp', 'swp', 'dim', 'cont_req', 'prep', 'free_ship', 'extra_ship', 'seo', 'impact', 'inventory_quantity', 'last_modified');
            break;

        case 'recurring_products':
            $codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'start', 'number_of_payments', 'payment_period', 'seo', 'impact', 'inventory_quantity', 'last_modified');
            break;

        case 'donation_products':
            $codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'recurring_payment', 'start', 'number_of_payments', 'payment_period', 'allow_to_schedule', 'seo', 'impact', 'inventory_quantity', 'last_modified');
            break;

        case 'grant_access_products':
            $codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'set_start_page', 'grant_private_access', 'seo', 'impact', 'inventory_quantity', 'last_modified');
            break;

        case 'membership_products':
            $codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'recurring_payment', 'set_start_page', 'membership_renewal', 'seo', 'impact', 'inventory_quantity', 'last_modified');
            break;

        case 'out_of_stock_products':
            $codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'selection_type', 'default_quantity', 'shippable', 'last_modified', 'custom_field_1', 'custom_field_2', 'custom_field_3', 'custom_field_4', 'out_of_stock_date');
            break;

        default:
            $codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'selection_type', 'default_quantity', 'shippable', 'recurring_payment', 'seo', 'impact', 'inventory_quantity', 'last_modified', 'custom_field_1', 'custom_field_2', 'custom_field_3', 'custom_field_4');
    }

    // The "was" price sorts like the price beside it, while the list draws it.
    if ($compare_at) {
        $codes[] = 'compare_at_price';
    }

    return $codes;
}

/**
 * The headings of the list's columns, keyed by column code.
 *
 * @param string $filter
 * @param array  $custom_labels the custom product fields' labels, 1 to 4
 * @return array code => heading
 */
function pg_product_list_labels($filter, $custom_labels)
{
    return array(
        'id'                    => lang('ID'),
        'short_description'     => lang('Short Description'),
        'enabled'               => lang('Enabled'),
        'price'                 => ($filter == 'donation_products') ? lang('Default Amount (Price)') : lang('Price'),
        'compare_at_price'      => lang('Compare-at Price'),
        'taxable'               => lang('Taxable'),
        'product_form'          => lang('Product Form'),
        'selection_type'        => lang('Selection Type'),
        'default_quantity'      => lang('Default Quantity'),
        'shippable'             => lang('Shippable'),
        'weight'                => lang('Weight'),
        'pwp'                   => lang('PWP'),
        'swp'                   => lang('SWP'),
        'dim'                   => lang('Dim'),
        'cont_req'              => lang('Cont Req'),
        'prep'                  => lang('Prep'),
        'free_ship'             => lang('Free Ship'),
        'extra_ship'            => lang('Extra Ship'),
        'allowed_zones'         => lang('Allowed Zones'),
        'disallowed_zones'      => lang('Disallowed Zones'),
        'recurring_payment'     => lang('Recurring Payment'),
        'seo'                   => lang('SEO'),
        'impact'                => lang('Impact'),
        'allow_to_schedule'     => lang('Allow to Schedule'),
        'order_receipt_message' => lang('Order Receipt Message'),
        'order_receipt_bcc'     => lang('Order Receipt BCC'),
        'email_page'            => lang('E-mail Page'),
        'email_page_bcc'        => lang('E-mail Page BCC'),
        'contact_group'         => lang('Contact Group'),
        'start'                 => lang('Start'),
        'number_of_payments'    => lang('Number of Payments'),
        'payment_period'        => lang('Payment Period'),
        'set_start_page'        => lang('Set Start Page'),
        'grant_private_access'  => lang('Grant Private Access'),
        'membership_renewal'    => lang('Membership Renewal'),
        'custom_field_1'        => (string) ($custom_labels[1] ?? ''),
        'custom_field_2'        => (string) ($custom_labels[2] ?? ''),
        'custom_field_3'        => (string) ($custom_labels[3] ?? ''),
        'custom_field_4'        => (string) ($custom_labels[4] ?? ''),
        'inventory_quantity'    => lang('Inventory Quantity'),
        'last_modified'         => lang('Last Modified'),
        'out_of_stock_date'     => lang('Out of Stock Date'),
    );
}

/**
 * What each sort code orders by. Only codes pg_list_state() let through
 * reach pg_list_order_by(), and it writes nothing but these. The export
 * reads products without the list's joins, so a name shown from another
 * table is a correlated subquery, never a join or SELECT alias. Stock sorts
 * as the cell shows it: an untracked product (a dash) has none.
 *
 * @return array code => SQL
 */
function pg_product_list_sort_sql()
{
    return array(
        'id'                    => 'products.name',
        'short_description'     => 'products.short_description',
        'enabled'               => 'products.enabled',
        'price'                 => 'products.price',
        'compare_at_price'      => 'products.compare_at_price',
        'taxable'               => 'products.taxable',
        'product_form'          => 'products.form_name',
        'selection_type'        => pg_list_label_order('products.selection_type', pg_product_list_selection_types()),
        'default_quantity'      => 'products.default_quantity',
        'shippable'             => 'products.shippable',
        'weight'                => 'products.weight',
        'pwp'                   => 'products.primary_weight_points',
        'swp'                   => 'products.secondary_weight_points',
        'dim'                   => 'products.length',
        'cont_req'              => 'products.container_required',
        'prep'                  => 'products.preparation_time',
        'free_ship'             => 'products.free_shipping',
        'extra_ship'            => 'products.extra_shipping_cost',
        'recurring_payment'     => 'products.recurring',
        'seo'                   => 'products.seo_score',
        'impact'                => 'seo_impact',
        'allow_to_schedule'     => 'products.recurring_schedule_editable_by_customer',
        'order_receipt_message' => 'products.order_receipt_message',
        'order_receipt_bcc'     => 'products.order_receipt_bcc_email_address',
        'email_page'            => '(SELECT page_name FROM page WHERE page.page_id = products.email_page)',
        'email_page_bcc'        => 'products.email_bcc',
        'contact_group'         => '(SELECT name FROM contact_groups WHERE contact_groups.id = products.contact_group_id)',
        'start'                 => 'products.start',
        'number_of_payments'    => 'products.number_of_payments',
        'payment_period'        => 'products.payment_period',
        'set_start_page'        => '(SELECT page_name FROM page WHERE page.page_id = products.send_to_page)',
        'grant_private_access'  => '(SELECT folder_name FROM folder WHERE folder.folder_id = products.private_folder)',
        'membership_renewal'    => 'products.membership_renewal',
        'custom_field_1'        => 'products.custom_field_1',
        'custom_field_2'        => 'products.custom_field_2',
        'custom_field_3'        => 'products.custom_field_3',
        'custom_field_4'        => 'products.custom_field_4',
        'inventory_quantity'    => "IF(products.inventory = '1', products.inventory_quantity, NULL)",
        'last_modified'         => 'products.timestamp',
        'out_of_stock_date'     => 'products.out_of_stock_timestamp',
    );
}

/**
 * Everything the screen, the export and the action need to know about a
 * view. The sort comes from the session (pg_list_state()), where the screen
 * keeps what its headings and sort menu asked for.
 *
 * @param string $filter        pg_product_list_filter_name()'s answer
 * @param bool   $tax           whether the site charges tax
 * @param bool   $compare_at    whether the list draws the "was" price
 * @param bool   $show_image    whether the list draws the pictures
 * @param array  $custom_labels the custom product fields' labels, 1 to 4
 * @param bool   $catalog_bin   whether products can be in the Recycle Bin
 * @return array
 */
function pg_product_list_view($filter, $tax, $compare_at, $show_image, $custom_labels, $catalog_bin)
{
    $filters = pg_product_list_filters();
    $conditions = array();

    if ($filters[$filter]['where'] !== '') {
        $conditions[] = $filters[$filter]['where'];
    }

    if ($catalog_bin) {
        $conditions[] = "(products.recycled = '0')";
    }

    $labels = pg_product_list_labels($filter, $custom_labels);
    $sort_columns = array();

    foreach (pg_product_list_sort_codes($filter, $compare_at) as $code) {
        if ($labels[$code] !== '') {
            $sort_columns[$code] = $labels[$code];
        }
    }

    $sort_default = array('sort' => ($filter == 'out_of_stock_products') ? 'out_of_stock_date' : 'last_modified', 'order' => 'desc');
    $state = pg_list_state('view_products', $sort_columns, $sort_default);

    return array(
        'filter' => $filter,
        'heading' => $filters[$filter]['label'],
        'subheading' => $filters[$filter]['description'],
        'where' => $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '',
        'condition' => $conditions ? implode(' AND ', $conditions) : '',
        'columns' => pg_product_list_column_codes($filter, $tax, $compare_at, $custom_labels),
        'labels' => $labels,
        'sort_columns' => $sort_columns,
        'sort_default' => $sort_default,
        'sort_levels' => $state['sorts'],
        'order' => $state['order'],
        // The id last keeps the pages from overlapping.
        'order_by' => pg_list_order_by($state['sorts'], pg_product_list_sort_sql()) . ', products.id asc',
        'compare_at' => (bool) $compare_at,
        'show_image' => (bool) $show_image,
    );
}

/**
 * A view of the running site, with its settings.
 *
 * @param string $filter pg_product_list_filter_name()'s answer
 * @return array pg_product_list_view()'s answer
 */
function pg_product_list_site_view($filter)
{
    return pg_product_list_view(
        $filter,
        (ECOMMERCE_TAX == true),
        pg_pb_list_shows_compare_at_price(),
        (bool) ECOMMERCE_SHOW_PRODUCT_IMAGES,
        array(
            1 => ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL,
            2 => ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL,
            3 => ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL,
            4 => ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL,
        ),
        pg_catalog_bin_ready());
}

/**
 * The list's columns for the list table driver, in the order the table
 * shows them: the name (the editor link), the picture, the SEO score bar,
 * the view's columns and the row's buttons. A column sorts when
 * the view sorts by its code, so a sort kept in the session still names
 * its column. A column whose key is a quick edit field changes in place.
 *
 * @param array $view       pg_product_list_view()'s answer
 * @param array $quick_edit pg_pb_quick_edit_columns(), or an empty array
 *                          when nothing may change in place
 * @return array key => spec, as pg_list_table_columns() takes them
 */
function pg_product_list_columns($view, $quick_edit)
{
    $sql = pg_product_list_sort_sql();
    $sql['impact'] = pg_seo_impact_sql('products.seo_score', 'products.seo_checked_at');
    $sql['seo'] = 'products.seo_score';

    $kinds = array(
        'enabled' => 'check', 'price' => 'money', 'compare_at_price' => 'money', 'inventory_quantity' => 'number',
        'taxable' => 'check', 'default_quantity' => 'number', 'shippable' => 'check', 'pwp' => 'number', 'swp' => 'number',
        'cont_req' => 'check', 'prep' => 'number', 'free_ship' => 'check', 'extra_ship' => 'money',
        'recurring_payment' => 'check', 'number_of_payments' => 'number', 'allow_to_schedule' => 'check',
        'order_receipt_message' => 'check', 'seo' => 'badge', 'impact' => 'number', 'last_modified' => 'date',
        'out_of_stock_date' => 'date',
    );
    $widths = array(
        'id' => '12rem', 'short_description' => '16rem', 'price' => '8rem', 'compare_at_price' => '8rem',
        'inventory_quantity' => '7rem', 'allowed_zones' => '12rem', 'disallowed_zones' => '12rem',
        'last_modified' => '10rem', 'out_of_stock_date' => '10rem',
    );
    // The input each quick edit field type opens as.
    $inputs = array('text' => 'text', 'select' => 'select', 'money' => 'money', 'integer' => 'number', 'decimal' => 'number');

    $spec = function ($code, $heading) use ($view, $sql, $kinds, $widths, $quick_edit, $inputs) {
        $column = array('heading' => $heading, 'kind' => $kinds[$code] ?? 'text');

        if (isset($widths[$code])) {
            $column['width'] = $widths[$code];
        }

        if (isset($view['sort_columns'][$code], $sql[$code])) {
            $column['sort'] = $sql[$code];
        }

        // The ID column changes the product code (pg_pb_quick_edit_field_of()).
        $field = $quick_edit ? pg_pb_quick_edit_field_of($code) : $code;

        if (isset($quick_edit[$field]['type'], $inputs[$quick_edit[$field]['type']])) {
            $column['editable'] = $inputs[$quick_edit[$field]['type']];
        }

        return $column;
    };

    $columns = array('id' => $spec('id', $view['labels']['id']));
    $columns['id']['kind'] = 'link';

    if ($view['show_image']) {
        $columns['image'] = array('heading' => lang('Image'), 'width' => '6rem', 'kind' => 'image');
    }

    // The score bar in a narrow column of its own beside the name; it sorts
    // where the view sorts by the score.
    $columns['seo'] = pg_list_table_seo_column(isset($view['sort_columns']['seo']) ? $sql['seo'] : '');

    foreach ($view['columns'] as $code) {
        if ($code === 'id') {
            continue;
        }

        $columns[$code] = $spec($code, $view['labels'][$code]);
    }

    $columns['actions'] = array('heading' => lang('Action'), 'width' => '7rem', 'kind' => 'actions');

    return $columns;
}

/**
 * The selection types as the list shows them.
 *
 * @return array stored value => label
 */
function pg_product_list_selection_types()
{
    return array(
        'checkbox' => lang('Checkbox'),
        'quantity' => lang('Quantity'),
        'donation' => lang('Donation'),
        'autoselect' => lang('Auto-Select'),
    );
}

/**
 * A search word as the digits of a stored amount: an amount is shown with
 * two decimals and stored in minor units, so the shown digits are the stored
 * ones. '' for a word that is not an amount.
 *
 * @param string $term
 * @return string
 */
function pg_product_list_amount_term($term)
{
    if (!preg_match('/^[0-9][0-9.,]*$/', (string) $term)) {
        return '';
    }

    return preg_replace('/[^0-9]/', '', (string) $term);
}

/**
 * The search box's columns: the text the view's row shows.
 *
 * @param array $view pg_product_list_view()'s answer
 * @return array array('columns', 'labels') for pg_list_search_where()
 */
function pg_product_list_search_columns($view)
{
    $amount = 'pg_product_list_amount_term';
    $columns = array('products.name', 'products.short_description', 'user.user_username', array('expr' => 'products.price', 'term' => $amount));
    $labels = array();

    foreach ($view['columns'] as $code) {
        switch ($code) {
            case 'compare_at_price':
                $columns[] = array('expr' => 'products.compare_at_price', 'term' => $amount);
                break;

            case 'inventory_quantity':
                $columns[] = "IF(products.inventory = '1', products.inventory_quantity, NULL)";
                break;

            case 'product_form':
                $columns[] = 'products.form_name';
                break;

            case 'selection_type':
                $labels[] = array('expr' => 'products.selection_type', 'labels' => pg_product_list_selection_types());
                break;

            case 'default_quantity':
                $columns[] = 'products.default_quantity';
                break;

            case 'custom_field_1':
            case 'custom_field_2':
            case 'custom_field_3':
            case 'custom_field_4':
                $columns[] = 'products.' . $code;
                break;

            case 'allowed_zones':
                $columns[] = '(SELECT GROUP_CONCAT(search_zone.name) FROM products_zones_xref AS search_xref'
                    . ' LEFT JOIN zones AS search_zone ON search_zone.id = search_xref.zone_id'
                    . ' WHERE search_xref.product_id = products.id)';
                break;

            case 'payment_period':
                $columns[] = 'products.payment_period';
                break;

            case 'set_start_page':
                $columns[] = "IF(products.grant_private_access = '1', page.page_name, NULL)";
                break;

            case 'grant_private_access':
                $columns[] = "IF(products.grant_private_access = '1', folder.folder_name, NULL)";
                break;

            case 'order_receipt_bcc':
                $columns[] = 'products.order_receipt_bcc_email_address';
                break;

            case 'email_page':
                $columns[] = 'email_page.page_name';
                break;

            case 'email_page_bcc':
                $columns[] = 'products.email_bcc';
                break;

            case 'contact_group':
                $columns[] = 'contact_groups.name';
                break;
        }
    }

    return array('columns' => $columns, 'labels' => $labels);
}

/**
 * The FROM of the list (without the word): the product with the names its cells show. Each
 * join finds one row at most, so a count over it counts products.
 *
 * @return string
 */
function pg_product_list_from()
{
    return 'products
        LEFT JOIN user ON products.user = user.user_id
        LEFT JOIN contact_groups ON contact_groups.id = products.contact_group_id
        LEFT JOIN page AS email_page ON email_page.page_id = products.email_page
        LEFT JOIN page ON page.page_id = products.send_to_page
        LEFT JOIN folder ON folder.folder_id = products.private_folder';
}

/**
 * The SELECT list of a list row.
 *
 * @param array $view pg_product_list_view()'s answer
 * @return string
 */
function pg_product_list_select($view)
{
    $barcodes = (defined('BARCODE_ENABLED') && BARCODE_ENABLED)
        ? '(SELECT COUNT(*) FROM product_barcodes WHERE product_barcodes.product_id = products.id)'
        : '0';

    return 'products.id, products.name, products.enabled, products.image_name, products.inventory, products.inventory_quantity,
        products.short_description, products.price, ' . ($view['compare_at'] ? 'products.compare_at_price, ' : '') . '
        products.taxable, products.form_name, products.seo_score,
        ' . (pg_seo_schema_ready() ? 'products.seo_flags, products.seo_checked_at,' : "'0' AS seo_flags, '0' AS seo_checked_at,") . '
        products.seo_analysis_current,
        ' . pg_seo_impact_select('products.seo_score', 'products.seo_checked_at') . ',
        products.shippable, products.selection_type, products.default_quantity,
        products.custom_field_1, products.custom_field_2, products.custom_field_3, products.custom_field_4,
        products.weight, products.primary_weight_points, products.secondary_weight_points,
        products.length, products.width, products.height, products.container_required,
        products.preparation_time, products.free_shipping, products.extra_shipping_cost,
        products.recurring, products.start AS recurring_start, products.number_of_payments, products.payment_period,
        products.recurring_schedule_editable_by_customer, products.grant_private_access, products.membership_renewal,
        products.order_receipt_message, products.order_receipt_bcc_email_address, products.email_bcc,
        email_page.page_name AS email_page, contact_groups.name AS contact_group_name,
        page.page_name AS start_page, folder.folder_name AS private_folder,
        ' . $barcodes . ' AS barcode_count,
        user.user_username AS user,
        products.out_of_stock, products.out_of_stock_timestamp, products.timestamp';
}

/**
 * The zones a product may and may not be shipped to, for the products of a
 * page: one query for the page's zones and one for all of them.
 *
 * @param array $product_ids
 * @return array product id => array('allowed' => names, 'disallowed' => names)
 */
function pg_product_list_zones($product_ids)
{
    $product_ids = array_values(array_unique(array_filter(array_map('intval', $product_ids))));

    if (!$product_ids) {
        return array();
    }

    $zones = array();

    foreach (db_items('SELECT id, name FROM zones ORDER BY name ASC') as $zone) {
        $zones[(int) $zone['id']] = (string) $zone['name'];
    }

    $allowed = array();
    $result = db_q('SELECT product_id, zone_id FROM products_zones_xref WHERE product_id IN (' . implode(', ', array_fill(0, count($product_ids), '?')) . ')', $product_ids);

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $allowed[(int) $row['product_id']][(int) $row['zone_id']] = true;
    }

    $answer = array();

    foreach ($product_ids as $product_id) {
        $answer[$product_id] = array('allowed' => array(), 'disallowed' => array());

        // By name, as the zones were read.
        foreach ($zones as $zone_id => $name) {
            $answer[$product_id][isset($allowed[$product_id][$zone_id]) ? 'allowed' : 'disallowed'][] = $name;
        }
    }

    return $answer;
}

/**
 * The rows of a page: the pictures behind each cover and the zones of all
 * of them in one query each.
 *
 * @param array $view     pg_product_list_view()'s answer
 * @param array $products rows of pg_product_list_select()
 * @return array rows for the list table driver (pg_product_list_row())
 */
function pg_product_list_rows($view, $products)
{
    $ids = array_map('intval', array_column($products, 'id'));
    $context = array(
        'quick_edit' => pg_pb_quick_edit_columns(),
        'more_images' => ($view['show_image'] && $ids) ? pg_product_more_images($ids) : array(),
        'zones' => in_array('allowed_zones', $view['columns'], true) ? pg_product_list_zones($ids) : array(),
    );

    $rows = array();

    foreach ($products as $product) {
        $rows[] = pg_product_list_row($product, $view, $context);
    }

    return $rows;
}

/**
 * A day count as the list shows it: '1 day', '30 days' (in Turkish '30 gün':
 * the count and the word are one key, so a language puts the noun the way it
 * follows a number). No count (NULL, empty or 0) shows nothing.
 *
 * @param mixed $days
 * @return string not escaped
 */
function pg_product_list_days($days)
{
    $days = trim((string) $days);

    if (($days === '') || ($days === '0')) {
        return '';
    }

    return lang(array('string' => ($days === '1') ? '{var:1} day' : '{var:1} days', 'vars' => array($days)));
}

/**
 * One row of the products list.
 *
 * @param array $product a row of pg_product_list_select()
 * @param array $view    pg_product_list_view()'s answer
 * @param array $context array('quick_edit' => pg_pb_quick_edit_columns(),
 *                       'more_images' => pg_product_more_images(),
 *                       'zones' => pg_product_list_zones())
 * @return array array('id', 'class', 'cells' => column key => pg_list_cell())
 */
function pg_product_list_row($product, $view, $context)
{
    $id = (int) $product['id'];
    $quick_edit = $context['quick_edit'];
    $check = '<span class="material-icons">task_alt</span>';
    $barcodes = defined('BARCODE_ENABLED') && BARCODE_ENABLED && !empty($product['barcode_count']);

    // The cells a quick edit can change, drawn by the same function that
    // draws them again in the answer to an edit.
    $list_cells = pg_pb_list_cells($product);

    $edit = function ($field, $html, $class) use ($quick_edit, $id, $product) {
        return pg_list_cell($html, $class, pg_list_edit_cell_attributes($quick_edit, $field, $id, pg_pb_quick_edit_value($field, $product)));
    };

    $buttons = '<button type="button" class="m-1 btn-data-control btn btn-outline theme-primary border-2 " title="' . lang('Edit') . '" onclick="window.location.href=\'edit_product.php?id=' . $id . '\'"><i class="bi bi-pencil"></i></button>';

    if ($barcodes) {
        // What the label prints beside the barcode (pgPrintProductBarcode()).
        $label = array(
            'sku' => (string) $product['name'],
            'short_description' => (string) $product['short_description'],
            'price' => number_format($product['price'] / 100, 2, '.', ''),
            'productImageSrc' => $product['image_name'] ? OUTPUT_PATH . $product['image_name'] : '',
        );

        $buttons .= '<button type="button" class="m-1 btn-data-control btn btn-outline theme-secondary border-2" title="' . lang('Print Barcode') . '" data-pg-barcode-product="' . h(encode_json($label)) . '" onclick="pgPrintProductBarcode(' . $id . ', this)"><i class="bi bi-printer"></i></button>';
    }

    // The name opens the product, from a click or a double-click or Enter
    // on the row (data-pg-open); the SEO column's bar opens the analysis.
    $cells = array(
        'id' => pg_list_cell(
            '<a class="pg-link-body-emphasis text-decoration-none" data-pg-open href="edit_product.php?id=' . $id . '">' . h($product['name']) . '</a>',
            $list_cells['name']['class'],
            array('data-pg-cell' => 'name')),
    );

    if ($view['show_image']) {
        $cells['image'] = pg_list_cell(pg_product_image_cell_content('product-' . $id, $product['image_name'], $product['name'], $product['short_description'], $context['more_images'][$id] ?? array()), 'align-middle');
    }

    $cells['seo'] = pg_list_table_seo_cell('product', $id, $product);

    foreach ($view['columns'] as $code) {
        switch ($code) {
            case 'short_description':
            case 'enabled':
            case 'price':
            case 'compare_at_price':
                $cells[$code] = $edit($code, $list_cells[$code]['html'], $list_cells[$code]['class']);
                break;

            case 'inventory_quantity':
                // Only a product that tracks stock has a count to change here.
                $stock = $list_cells['inventory_quantity'];
                $cells[$code] = ((string) $product['inventory'] === '1')
                    ? $edit('inventory_quantity', $stock['html'], $stock['class'])
                    : pg_list_cell($stock['html'], $stock['class'], array('title' => $stock['title']));
                break;

            case 'taxable':
                $cells[$code] = pg_list_cell(($product['taxable'] == 1) ? $check : '', 'align-middle text-center');
                break;

            case 'product_form':
                $cells[$code] = pg_list_cell(h($product['form_name']));
                break;

            case 'selection_type':
                $cells[$code] = pg_list_cell(pg_product_list_selection_types()[$product['selection_type']] ?? '');
                break;

            case 'default_quantity':
                $cells[$code] = pg_list_cell(h($product['default_quantity']), 'align-middle');
                break;

            case 'shippable':
                $cells[$code] = pg_list_cell(($product['shippable'] == 1) ? $check : '', 'align-middle text-center');
                break;

            case 'custom_field_1':
            case 'custom_field_2':
            case 'custom_field_3':
            case 'custom_field_4':
                $cells[$code] = pg_list_cell(h($product[$code]));
                break;

            case 'weight':
                $cells[$code] = pg_list_cell(($product['weight'] > 0) ? h(($product['weight'] + 0) . ' ' . lang('lb')) : '', 'align-middle text-center');
                break;

            case 'pwp':
                $cells[$code] = pg_list_cell(h($product['primary_weight_points']), 'align-middle');
                break;

            case 'swp':
                $cells[$code] = pg_list_cell(h($product['secondary_weight_points']), 'align-middle');
                break;

            case 'dim':
                $dimensions = '';

                if (($product['length'] > 0) || ($product['width'] > 0) || ($product['height'] > 0)) {
                    $dimensions = ($product['length'] + 0) . '&Prime; x ' . ($product['width'] + 0) . '&Prime; x ' . ($product['height'] + 0) . '&Prime;';
                }

                $cells[$code] = pg_list_cell($dimensions);
                break;

            case 'cont_req':
                $cells[$code] = pg_list_cell($product['container_required'] ? $check : '', 'align-middle text-center');
                break;

            case 'prep':
                $cells[$code] = pg_list_cell(h($product['preparation_time']), 'align-middle');
                break;

            case 'free_ship':
                $cells[$code] = pg_list_cell(($product['free_shipping'] == 1) ? $check : '', 'align-middle text-center');
                break;

            case 'extra_ship':
                $cells[$code] = pg_list_cell(pg_format_money($product['extra_shipping_cost'] / 100, BASE_CURRENCY_SYMBOL), 'align-middle text-end');
                break;

            case 'allowed_zones':
            case 'disallowed_zones':
                $names = $context['zones'][$id][($code == 'allowed_zones') ? 'allowed' : 'disallowed'] ?? array();
                $cells[$code] = pg_list_cell(implode(',<br />', array_map('h', $names)));
                break;

            case 'recurring_payment':
                $cells[$code] = pg_list_cell(($product['recurring'] == 1) ? $check : '', 'align-middle text-center');
                break;

            case 'start':
                $cells[$code] = pg_list_cell(h(((string) $product['recurring_start'] !== '0') ? pg_product_list_days($product['recurring_start']) : lang('Immediately')));
                break;

            case 'number_of_payments':
                $cells[$code] = pg_list_cell(h(((string) $product['number_of_payments'] !== '0') ? $product['number_of_payments'] : lang('Unlimited')), 'align-middle');
                break;

            case 'payment_period':
                $cells[$code] = pg_list_cell(h($product['payment_period']));
                break;

            case 'allow_to_schedule':
                $cells[$code] = pg_list_cell(($product['recurring_schedule_editable_by_customer'] == 1) ? $check : '', 'align-middle text-center');
                break;

            case 'set_start_page':
                $cells[$code] = pg_list_cell(((string) $product['grant_private_access'] === '1') ? h($product['start_page']) : '');
                break;

            case 'grant_private_access':
                $cells[$code] = pg_list_cell(((string) $product['grant_private_access'] === '1') ? h($product['private_folder']) : '', 'align-middle ');
                break;

            case 'membership_renewal':
                $cells[$code] = pg_list_cell(h(pg_product_list_days($product['membership_renewal'])));
                break;

            case 'order_receipt_message':
                $cells[$code] = pg_list_cell(((string) $product['order_receipt_message'] !== '') ? $check : '', 'align-middle text-center');
                break;

            case 'order_receipt_bcc':
                $cells[$code] = pg_list_cell(h($product['order_receipt_bcc_email_address']));
                break;

            case 'email_page':
                $cells[$code] = pg_list_cell(h($product['email_page']));
                break;

            case 'email_page_bcc':
                $cells[$code] = pg_list_cell(h($product['email_bcc']));
                break;

            case 'contact_group':
                $cells[$code] = pg_list_cell(h($product['contact_group_name']));
                break;

            case 'impact':
                $cells[$code] = pg_list_cell(pg_seo_render_impact($product), 'align-middle');
                break;

            case 'last_modified':
                $cells[$code] = pg_list_cell(pg_list_when($product['timestamp'], $product['user'] ?? ''));
                break;

            case 'out_of_stock_date':
                $cells[$code] = pg_list_cell(get_relative_time(array('timestamp' => $product['out_of_stock_timestamp'])));
                break;
        }
    }

    // Every other value the product screen changes opens as a field in
    // quick edit (pg_pb_quick_edit_more_fields()).
    $more = pg_pb_quick_edit_more_fields();

    foreach (array_keys($cells) as $code) {
        $field = pg_pb_quick_edit_field_of($code);

        if (isset($more[$field], $quick_edit[$field])) {
            $attributes = pg_list_edit_cell_attributes($quick_edit, $field, $id, pg_pb_quick_edit_value($field, $product));

            if ($attributes) {
                $cells[$code]['attributes'] = array_merge($cells[$code]['attributes'] ?? array(), array_map('strval', $attributes));
            }
        }
    }

    $cells['actions'] = pg_list_cell($buttons, 'align-middle action-buttons');

    return array('id' => $id, 'class' => '', 'cells' => $cells);
}

/**
 * The products list as the list table driver's source
 * (includes/fn/list_table.php), for the screen, the rows action and the
 * bulk actions (edit_products.php) alike. It is the screen's gate too: a
 * manager or above, or a user with the e-commerce right
 * (validate_ecommerce_access()); the same users change cells in place, on
 * the screen's own pg_list_edit_request() endpoint.
 *
 * @param array $user   the signed-in user
 * @param array $params the list's parameters: 'filter' (the view)
 * @return array|null pg_list_table_source(), or null when the user may not
 *                    open the list
 */
function pg_product_list_source($user, $params)
{
    if (!is_array($user) || (((int) ($user['role'] ?? 9) > 2) && empty($user['manage_ecommerce']))) {
        return null;
    }

    // The cells a quick edit changes are the product screen's (pg_pb_list_cells()).
    require_once(PG_FUNCTIONS_DIR . '/seo.php');
    require_once(PG_FUNCTIONS_DIR . '/product_builder.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

    $view = pg_product_list_site_view(pg_product_list_filter_name((is_array($params) && isset($params['filter'])) ? $params['filter'] : ''));
    $quick_edit = pg_pb_quick_edit_columns();

    // The traffic behind the impact figure joins only the list's read.
    return pg_list_table_source(array(
        'list' => 'view_products',
        'columns' => pg_product_list_columns($view, $quick_edit),
        'from' => pg_product_list_from() . ' ' . pg_seo_traffic_join('product', 'products.id'),
        'where' => $view['condition'],
        'id' => 'products.id',
        'id_order' => 'asc',
        'select' => pg_product_list_select($view),
        'search' => pg_product_list_search_columns($view),
        'levels' => $view['sort_levels'],
        'rows' => function ($records) use ($view) {
            return pg_product_list_rows($view, $records);
        },
        'params_out' => array('filter' => $view['filter']),
        'edit' => array('url' => 'view_products.php', 'columns' => $quick_edit),
    ));
}

/**
 * The products a bulk action of the list acts on (Modify Selected, Assign
 * Barcodes, Delete Selected): the selection the list table driver posted
 * (JSON), read with the list's own query and rights. A selection of ids is
 * read in the view the list showed, sent beside it; a condition carries
 * its view itself. A product in the Recycle Bin is on no view, so it is
 * never among them.
 *
 * @param array $user        the signed-in user
 * @param mixed $selection   the posted selection, JSON
 *                           (pg_list_table_selection_parse())
 * @param mixed $list_params the posted list parameters, JSON ('filter')
 * @return array|false|null product ids (empty when nothing or nothing
 *                          reachable was selected), false when the query
 *                          failed, null when the selection holds more than
 *                          10,000 products
 */
function pg_product_list_selected_ids($user, $selection, $list_params)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

    $selection = pg_list_table_selection_parse(is_string($selection) ? json_decode($selection, true) : null, time());

    if ($selection === null) {
        return array();
    }

    $params = ($selection['mode'] === 'condition') ? $selection['params'] : json_decode(is_string($list_params) ? $list_params : '', true);
    $source = pg_product_list_source($user, is_array($params) ? $params : array());

    if ($source === null) {
        return array();
    }

    $selected = pg_list_table_selected_ids($source, $selection);

    if ($selected === false) {
        return false;
    }

    return $selected['more'] ? null : $selected['ids'];
}

/**
 * The Modify Selected dialog of the products list and the product group
 * screen (includes/templates/edit_products.php). Its fields belong to the
 * page's form by their form attribute, so Save posts them with that form's
 * selection to edit_products.php; the form carries the token, the action and
 * the selection fields. An empty pick list leaves its property alone.
 *
 * @param string $form_id the id of the page's form that posts the change
 * @param string $warning a warning shown above the fields (plain text), or ''
 * @return string HTML
 */
function pg_product_modify_dialog($form_id, $warning = '')
{
    $enabled_options = array(
        lang(array('string'=>'Select to change {var:1}','vars'=>array(lang('Status')) )) => '',
        lang('Enable') => '1',
        lang('Disable') => '0'
    );
    $price_change_method_options = pg_product_mass_price_method_options(BASE_CURRENCY_SYMBOL);
    $inventory_options = array(
        lang(array('string'=>'Select a {var:1}','vars'=>array(lang('Option for Track Inventory')) ))=>'',
        lang('Enable Track Inventory')=>'1',
        lang('Disable Track Inventory')=>'0',
    );
    $inventory_quantity_process_options = array(
        lang(array('string'=>'Select a {var:1}','vars'=>array(lang('Process')) ))=>'',
        lang('Enter an Inventory Quantity instead of the Current Inventory Quantity.')=>'value',
        lang('Increase Current Inventory Quantity (+)')=>'increase',
        lang('Decrease Current Inventory Quantity (-)')=>'decrease',
    );
    $tax_rate_method_options = array(
        lang(array('string'=>'Select a {var:1}','vars'=>array(lang('Process')) ))=>'',
        lang('Set the tax rate to the value below')=>'set',
        lang('Follow the tax zone (clear the product rate)')=>'zone',
    );

    // The zone lists only on a site that ships.
    $zones = ECOMMERCE_SHIPPING ? db_items("SELECT id, name FROM zones ORDER BY name") : null;

    // The locations a quantity change can go to, default first; none on a
    // site without stock locations.
    $stock_locations = pg_stock_screen_locations();

    ob_start();
    require(PG_FUNCTIONS_DIR . '/includes/templates/edit_products.php');

    return (string) ob_get_clean();
}
