<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Cart quotes: the product lists staff send a customer as a link
 * (includes/fn/cart_quotes.php), newest first, with where each stands. An
 * open quote can be cancelled or given a new link; the link is shown only
 * when it is made, since only its hash is kept. ?contact_id= lists one
 * contact's quotes; ?id= one quote, with its Workspace button: the address
 * its Workspace tag opens.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

$user = validate_user();
validate_ecommerce_access($user);

if (!defined('ECOMMERCE') || (ECOMMERCE !== true)) {
    output_error(lang('E-commerce is not enabled on this site.'));
}

$liveform = new liveform('view_cart_quotes');
$contact_id = is_scalar($_GET['contact_id'] ?? null) ? max(0, (int) $_GET['contact_id']) : 0;
$quote_id = is_scalar($_GET['id'] ?? null) ? max(0, (int) $_GET['id']) : 0;
$filter = array_filter(array('id' => $quote_id, 'contact_id' => $contact_id));
$self = 'view_cart_quotes.php' . ($filter ? '?' . http_build_query($filter) : '');

// Cancel, or a new link, for one open quote.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();

    // Each button posts its action with the quote's id as its value.
    $action = isset($_POST['cancel']) ? 'cancel' : (isset($_POST['link']) ? 'link' : '');
    $quote_id = is_scalar($_POST[$action] ?? null) ? (int) $_POST[$action] : 0;

    if ($action === 'cancel') {
        if (pg_cart_quote_cancel($quote_id)) {
            log_activity(lang(array('string' => 'cart quote {var:1} was cancelled', 'vars' => '#' . $quote_id)));
            $liveform->add_notice(h(lang('The cart quote was cancelled. Its link opens nothing any more.')));
        } else {
            $liveform->mark_error('_error', h(lang('Only an open cart quote can be cancelled.')));
        }
    } elseif ($action === 'link') {
        $token = pg_cart_quote_new_link($quote_id);

        if ($token !== '') {
            log_activity(lang(array('string' => 'cart quote {var:1} was given a new link', 'vars' => '#' . $quote_id)));
            $liveform->add_notice(h(lang('The cart quote has a new link; the old one no longer works. Copy it now: it is not shown again.'))
                . '<div class="mt-5"><input type="text" class="form-control" readonly value="' . h(pg_cart_quote_url($token)) . '" aria-label="' . h(lang('Cart quote link')) . '"></div>');
        } else {
            $liveform->mark_error('_error', h(lang('Only an open cart quote can be given a new link.')));
        }
    } else {
        $liveform->mark_error('_error', h(lang('Invalid request.')));
    }

    go(PATH . SOFTWARE_DIRECTORY . '/' . $self);
}

$ready = pg_cart_quotes_ready();
$rows = $ready ? pg_cart_quote_panel_rows($contact_id, PG_CART_QUOTE_PANEL_LIMIT, $quote_id) : array();
$now = time();
$badges = array('open' => 'badge theme-success', 'ordered' => 'badge theme-primary', 'expired' => 'badge theme-secondary', 'cancelled' => 'badge theme-danger');
$list_rows = array();

// The list asks only for the ecommerce right; a contact name links only when
// edit_contact.php admits the viewer (contacts right and contact group).
$contacts_access = (validate_contacts_access($user, true) == true);

foreach ($rows as $row) {
    $id = (int) $row['id'];
    $state = pg_cart_quote_state($row, $now);
    $quantity = 0;

    foreach ($row['lines'] as $line) {
        $quantity += $line['quantity'];
    }

    $names = pg_cart_quote_lines_summary($row['lines']);

    $actions = '';

    if ($state === 'open') {
        $actions = '<button type="submit" name="link" value="' . $id . '" class="btn-data-control m-1 btn btn-outline theme-primary border-2" data-confirm-content="' . h(lang('The link the customer already has stops working. Make a new link?')) . '" title="' . h(lang('New link')) . '" aria-label="' . h(lang('New link')) . '"><i class="bi bi-link-45deg" aria-hidden="true"></i></button>'
            . '<button type="submit" name="cancel" value="' . $id . '" class="btn-data-control m-1 btn btn-outline theme-danger border-2" data-confirm-content="' . h(lang('The link of this cart quote stops working. This cannot be undone.')) . '" title="' . h(lang('Cancel the cart quote')) . '" aria-label="' . h(lang('Cancel the cart quote')) . '"><i class="bi bi-x-lg" aria-hidden="true"></i></button>';
    }

    $contact_label = h(($row['contact_name'] !== '') ? $row['contact_name'] : '#' . (int) $row['contact_id']);

    if ((int) $row['contact_id'] < 1) {
        $output_contact = '<span class="fg-secondary">' . h(lang('Nobody named')) . '</span>';
    } elseif (!$row['contact_found']) {
        $output_contact = '<span class="fg-secondary">' . h(lang('Deleted contact')) . '</span>';
    } elseif (pg_can_open_contact($user, $row['contact_id'], $contacts_access)) {
        $output_contact = '<a href="edit_contact.php?id=' . (int) $row['contact_id'] . '">' . $contact_label . '</a>';
    } else {
        $output_contact = $contact_label;
    }

    $output_order = ((int) $row['order_id'] > 0) && ($state === 'ordered')
        ? '<div class="small"><a href="view_order.php?id=' . (int) $row['order_id'] . '">' . h(lang('Open the order')) . '</a></div>'
        : '';

    // The quote's number opens it; Cancel and New link post the page's form.
    $list_rows[] = array(
        'id' => $id,
        'cells' => array(
            'quote' => pg_list_cell('<a href="view_cart_quotes.php?id=' . $id . '" data-pg-open aria-label="' . h(lang('Cart quote') . ' #' . $id) . '">#' . $id . '</a><div class="small fg-secondary">' . h((string) $row['token_hint']) . '…</div>', 'align-middle', array('data-pg-sort-value' => $id)),
            'customer' => pg_list_cell($output_contact),
            'items' => pg_list_cell(h(lang(array('string' => '{var:1} product(s)', 'vars' => array($quantity)))) . (($names !== '') ? '<div class="small fg-secondary text-wrap">' . h($names) . '</div>' : ''), 'align-middle', array('data-pg-sort-value' => $quantity)),
            'status' => pg_list_cell('<span class="' . h($badges[$state] ?? 'badge theme-secondary') . '">' . h(pg_cart_quote_state_label($state)) . '</span>' . $output_order),
            'valid_until' => pg_list_cell(h(((int) $row['valid_until'] > 0) ? date('d.m.Y', (int) $row['valid_until']) : lang('No end')), 'align-middle', array('data-pg-sort-value' => (int) $row['valid_until'])),
            'created' => pg_list_cell(h(date('d.m.Y H:i', (int) $row['created_at'])) . '<div class="small fg-secondary">' . h((string) $row['creator']) . '</div>', 'align-middle', array('data-pg-sort-value' => (int) $row['created_at'])),
            'actions' => pg_list_cell($actions, 'align-middle text-end'),
        ),
    );
}

// Every quote shown is printed: the list table driver sorts and searches
// them in the browser (browser mode) and keeps a heading's sort.
$columns = array(
    'quote' => array('heading' => lang('Cart quote')),
    'customer' => array('heading' => lang('Customer')),
    'items' => array('heading' => lang('Items')),
    'status' => array('heading' => lang('Status'), 'kind' => 'badge'),
    'valid_until' => array('heading' => lang('Valid until'), 'kind' => 'date'),
    'created' => array('heading' => lang('Created'), 'kind' => 'date'),
    'actions' => array('heading' => lang('Action'), 'kind' => 'actions'),
);
$sort_columns = array();

foreach ($columns as $key => $spec) {
    $sort_columns[$key] = $spec['heading'];
}

$list_state = pg_list_state('view_cart_quotes', $sort_columns, array('sort' => 'created', 'order' => 'desc'));
$list = pg_list_table_printed(array('list' => 'view_cart_quotes', 'columns' => $columns, 'levels' => $list_state['sorts'], 'rows' => $list_rows));

$output_not_ready = $ready ? '' : '
                <div class="alert theme-warning" role="status">' . h(lang('Cart quotes are not installed yet: the database has to be updated first.')) . '</div>';

// One quote asked for by its id and not found: say so instead of an empty
// list.
$output_not_found = ($ready && ($quote_id > 0) && !$rows) ? '
                <div class="alert theme-warning" role="status">' . h(lang('The cart quote could not be found.')) . '</div>' : '';

$output_limit = (count($rows) >= PG_CART_QUOTE_PANEL_LIMIT) ? '
                <p class="small fg-secondary">' . h(lang(array('string' => 'The newest {var:1} cart quotes are shown.', 'vars' => array(PG_CART_QUOTE_PANEL_LIMIT)))) . '</p>' : '';

// One quote shown: where it was talked about in the workspace, and the tasks
// about it.
$output_workspace_button = '';

if (($quote_id > 0) && $rows && defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED) {
    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
    $output_workspace_button = ws_record_button($user, 'cart_quote', $quote_id, lang('Cart quote') . ' #' . $quote_id);
}

// A filtered list names whose quotes, or which quote, it shows.
$heading = lang('Cart quotes');

if ($quote_id > 0) {
    $heading = lang('Cart quote') . ' #' . $quote_id;
} elseif ($contact_id > 0) {
    $contact = db_item("SELECT first_name, last_name, company FROM contacts WHERE id = '" . $contact_id . "'");
    $person = is_array($contact) ? trim(trim((string) $contact['first_name']) . ' ' . trim((string) $contact['last_name'])) : '';
    $name = ($person !== '') ? $person : (is_array($contact) ? trim((string) $contact['company']) : '');
    $heading = lang('Cart quotes') . ': ' . (($name !== '') ? $name : '#' . $contact_id);
}

$breadcrumb = array(array('label' => lang('All Orders'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_orders.php'));

if ($filter) {
    $breadcrumb[] = array('label' => lang('Cart quotes'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_cart_quotes.php');
}

$breadcrumb[] = array('label' => $heading);

echo
    pg_page_shell(array(
        'title'               => $heading,
        'extra classes'       => 'store pg-measure-list',
        'icon'                => 'store',
        // The heading is printed as markup; a contact's name is escaped here.
        'heading'             => h($heading),
        'heading_description' => lang('Product lists sent to a customer as a link that fills their cart at the store\'s current prices.'),
        'cancel'              => array('enable' => 'true', 'url' => 'view_orders.php', 'title' => lang('All Orders')),
        'breadcrumb'          => $breadcrumb,
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            ' . $output_not_ready . '
            ' . $output_not_found . '
            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . h(lang('Button Bar')) . '">
                ' . ($ready ? '<a class="btn btn-sm btn-solid theme-primary rounded-pill px-12" href="add_cart_quote.php' . (($contact_id > 0) ? '?contact_id=' . $contact_id : '') . '"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang('New cart quote')) . '</a>' : '') . '
                ' . ($filter ? '<a class="btn btn-sm btn-text theme-secondary" href="view_cart_quotes.php"><i class="bi bi-list-ul me-1" aria-hidden="true"></i>' . h(lang('All cart quotes')) . '</a>' : '') . '
                ' . $output_workspace_button . '
                <div class="pg-toolbar-grow"></div>
                ' . pg_list_table_search_box('view_cart_quotes') . '
            </nav>
            ' . $output_limit . '
            <form name="form" action="' . h($self) . '" method="post" class="disable_shortcut">
                ' . get_token_field() . '
                ' . pg_list_table_html($list['source'], $list['page'], lang('Cart quotes')) . '
            </form>
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . output_footer();

$liveform->remove_form();
