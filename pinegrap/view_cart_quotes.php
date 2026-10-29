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
            $liveform->add_notice(h(lang('The cart quote was cancelled. Its link opens nothing any more.')));
        } else {
            $liveform->mark_error('_error', h(lang('Only an open cart quote can be cancelled.')));
        }
    } elseif ($action === 'link') {
        $token = pg_cart_quote_new_link($quote_id);

        if ($token !== '') {
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
$output_rows = '';

foreach ($rows as $row) {
    $id = (int) $row['id'];
    $state = pg_cart_quote_state($row, $now);
    $names = array();
    $quantity = 0;

    foreach ($row['lines'] as $line) {
        $quantity += $line['quantity'];
        $names[] = $line['name'] . ' × ' . $line['quantity'];
    }

    $actions = '';

    if ($state === 'open') {
        $actions = '<button type="submit" name="link" value="' . $id . '" class="btn-data-control m-1 btn btn-outline theme-primary border-2" title="' . h(lang('New link')) . '" aria-label="' . h(lang('New link')) . '"><i class="bi bi-link-45deg" aria-hidden="true"></i></button>'
            . '<button type="submit" name="cancel" value="' . $id . '" class="btn-data-control m-1 btn btn-outline theme-danger border-2" data-confirm-content="' . h(lang('The link of this cart quote stops working. This cannot be undone.')) . '" title="' . h(lang('Cancel the cart quote')) . '" aria-label="' . h(lang('Cancel the cart quote')) . '"><i class="bi bi-x-lg" aria-hidden="true"></i></button>';
    }

    $output_contact = ((int) $row['contact_id'] > 0)
        ? '<a href="edit_contact.php?id=' . (int) $row['contact_id'] . '">' . h(($row['contact_name'] !== '') ? $row['contact_name'] : '#' . (int) $row['contact_id']) . '</a>'
        : '<span class="fg-secondary">' . h(lang('Nobody named')) . '</span>';

    $output_order = ((int) $row['order_id'] > 0) && ($state === 'ordered')
        ? '<div class="small"><a href="view_order.php?id=' . (int) $row['order_id'] . '">' . h(lang('Open the order')) . '</a></div>'
        : '';

    $output_rows .= '
            <tr id="' . $id . '">
                <td class="align-middle text-start col-reorder-none action-buttons">' . $actions . '</td>
                <td class="align-middle" data-order="' . $id . '"><a href="view_cart_quotes.php?id=' . $id . '" aria-label="' . h(lang('Cart quote') . ' #' . $id) . '">#' . $id . '</a><div class="small fg-secondary">' . h((string) $row['token_hint']) . '…</div></td>
                <td class="align-middle">' . $output_contact . '</td>
                <td class="align-middle">' . h(lang(array('string' => '{var:1} item(s)', 'vars' => array($quantity)))) . (($names !== array()) ? '<div class="small fg-secondary">' . h(implode(', ', $names)) . '</div>' : '') . '</td>
                <td class="align-middle"><span class="' . h($badges[$state] ?? 'badge theme-secondary') . '">' . h(pg_cart_quote_state_label($state)) . '</span>' . $output_order . '</td>
                <td class="align-middle" data-order="' . (int) $row['valid_until'] . '">' . h(((int) $row['valid_until'] > 0) ? date('d.m.Y', (int) $row['valid_until']) : lang('No end')) . '</td>
                <td class="align-middle" data-order="' . (int) $row['created_at'] . '">' . h(date('d.m.Y H:i', (int) $row['created_at'])) . '<div class="small fg-secondary">' . h((string) $row['creator']) . '</div></td>
            </tr>';
}

$output_not_ready = $ready ? '' : '
                <div class="alert theme-warning" role="status">' . h(lang('Cart quotes are not installed yet: the database has to be updated first.')) . '</div>';

$output_limit = (count($rows) >= PG_CART_QUOTE_PANEL_LIMIT) ? '
                <p class="small fg-secondary">' . h(lang(array('string' => 'The newest {var:1} cart quotes are shown.', 'vars' => array(PG_CART_QUOTE_PANEL_LIMIT)))) . '</p>' : '';

// One quote shown: where it was talked about in the workspace, and the tasks
// about it.
$output_workspace_button = '';

if (($quote_id > 0) && $rows && defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED) {
    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
    $output_workspace_button = ws_record_button($user, 'cart_quote', $quote_id, lang('Cart quote') . ' #' . $quote_id);
}

echo
    pg_page_shell(array(
        'title'               => lang('Cart quotes'),
        'extra classes'       => 'store',
        'icon'                => 'store',
        'heading'             => lang('Cart quotes'),
        'heading_description' => lang('Product lists sent to a customer as a link that fills their cart at the store\'s current prices.'),
        'cancel'              => false,
        'breadcrumb'          => array(
            array('label' => lang('All Orders'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_orders.php'),
            array('label' => lang('Cart quotes')),
        ),
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            ' . $output_not_ready . '
            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . h(lang('Button Bar')) . '">
                ' . ($ready ? '<a class="btn btn-sm btn-solid theme-primary rounded-pill px-12" href="add_cart_quote.php' . (($contact_id > 0) ? '?contact_id=' . $contact_id : '') . '"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang('New cart quote')) . '</a>' : '') . '
                ' . ($filter ? '<a class="btn btn-sm btn-text theme-secondary" href="view_cart_quotes.php"><i class="bi bi-list-ul me-1" aria-hidden="true"></i>' . h(lang('All cart quotes')) . '</a>' : '') . '
                <a class="btn btn-sm btn-text theme-secondary" href="view_orders.php"><i class="bi bi-cart4 me-1" aria-hidden="true"></i>' . h(lang('All Orders')) . '</a>
                ' . $output_workspace_button . '
            </nav>
            ' . $output_limit . '
            <div class="card my-7">
                <div class="card-body p-0 position-relative">
                    <form name="form" action="' . h($self) . '" method="post">
                        ' . get_token_field() . '
                        <table class="chart table-hover table" style="width:100%;display:none;">
                            <thead>
                                <tr>
                                    <th class="noVis">' . h(lang('Action')) . '</th>
                                    <th>' . h(lang('Cart quote')) . '</th>
                                    <th>' . h(lang('Customer')) . '</th>
                                    <th>' . h(lang('Items')) . '</th>
                                    <th>' . h(lang('Status')) . '</th>
                                    <th>' . h(lang('Valid until')) . '</th>
                                    <th>' . h(lang('Created')) . '</th>
                                </tr>
                            </thead>
                            <tbody>
                                ' . $output_rows . '
                            </tbody>
                        </table>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>' . output_footer();

$liveform->remove_form();
