<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The automation contract: the triggers, actions and searches an app on an
 * automation platform (Zapier, Make, n8n, Pabbly Connect) is written from,
 * as data. Nothing here runs at request time; tests/automation_contract_test.php
 * holds it against the route table and the event catalogue, and the scopes
 * it names are the ones a new application for such a platform is offered.
 *
 * A row is never renamed or removed once an app relies on it: an app is
 * published on the platform and outlives every release of the site.
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
 * The contract's rows. Every row has:
 *   key     a stable name the apps use for it
 *   kind    trigger, action or search
 *   module  '' for the core, or the module whose routes and events it uses
 *           (erp, workspace); an app offers it only while the module is on
 *   scope   the scope of every route the row names
 *
 * A trigger: events (the catalogue names that start it), record (the route
 * that reads the record), record_ids (each {placeholder} of the record
 * route's path => the field of the event's data that fills it, a dot for a
 * nested field), list (the route whose newest records are the trigger's
 * samples), list_ids (the same for the list route's path) and newest (the
 * query parameters that list the newest first; array() when the list does
 * by itself; null when it cannot yet).
 *
 * An action: route, a POST route. A search: route, a GET list, and by, the
 * query parameters it searches with.
 *
 * No route here moves money, and no scope here holds a route that does.
 *
 * @return array
 */
function pg_automation_contract()
{
    $trigger = function ($key, $module, $scope, $events, $record, $record_ids, $list, $list_ids, $newest) {
        return array(
            'key'        => $key,
            'kind'       => 'trigger',
            'module'     => $module,
            'scope'      => $scope,
            'events'     => $events,
            'record'     => $record,
            'record_ids' => $record_ids,
            'list'       => $list,
            'list_ids'   => $list_ids,
            'newest'     => $newest,
        );
    };

    $order = array('sort' => 'placed_desc');
    $id = array('id' => 'id');

    $rows = array(
        // Orders: every order.* event carries the order's id.
        $trigger('order_created', '', 'orders:read', array('order.created'), 'orders.get', $id, 'orders.list', array(), $order),
        $trigger('order_status_changed', '', 'orders:read', array('order.status_changed'), 'orders.get', $id, 'orders.list', array(), $order),
        $trigger('order_shipped', '', 'orders:read', array('order.shipped'), 'orders.get', $id, 'orders.list', array(), $order),
        $trigger('order_cancelled', '', 'orders:read', array('order.cancelled'), 'orders.get', $id, 'orders.list', array(), $order),
        // A return's id is return_id: id is the order's, as in every order.* event.
        $trigger('order_return_requested', '', 'orders:read', array('order.return.requested'), 'returns.get', array('id' => 'return_id'), 'returns.list', array(), array()),
        $trigger('customer_created', '', 'customers:read', array('customer.created'), 'customers.get', $id, 'customers.list', array(), null),
        // id is the submission's, form_id the form's.
        $trigger('form_submitted', '', 'forms:read', array('form.submitted'), 'forms.submission_get', array('id' => 'form_id', 'submission_id' => 'id'), 'forms.submissions', array('id' => 'form_id'), null),
        $trigger('product_created', '', 'products:read', array('product.created'), 'products.get', $id, 'products.list', array(), null),
        $trigger('stock_low', '', 'products:read', array('stock.low'), 'products.get', array('id' => 'product_id'), 'products.list', array(), null),
        $trigger('subscription_renewed', '', 'customers:read', array('subscription.renewed'), 'subscriptions.get', $id, 'subscriptions.list', array(), null),
        $trigger('subscription_failed', '', 'customers:read', array('subscription.failed'), 'subscriptions.get', $id, 'subscriptions.list', array(), null),
        $trigger('subscription_cancelled', '', 'customers:read', array('subscription.cancelled'), 'subscriptions.get', $id, 'subscriptions.list', array(), null),

        $trigger('workspace_task_created', 'workspace', 'tasks:read', array('workspace.task.created'), 'workspace.tasks.get', $id, 'workspace.tasks.list', array(), null),
        $trigger('workspace_task_completed', 'workspace', 'tasks:read', array('workspace.task.completed'), 'workspace.tasks.get', $id, 'workspace.tasks.list', array(), null),
        // Rows of a table shared in a public channel only, as the event is.
        $trigger('workspace_table_row_created', 'workspace', 'workspace:read', array('workspace.wtable.row.created'), 'workspace.table_rows.get', array('id' => 'table.id', 'row_id' => 'row.id'), 'workspace.table_rows.list', array('id' => 'table.id'), null),

        $trigger('erp_invoice_created', 'erp', 'erp:read', array('erp.invoice.created'), 'erp.invoices.get', $id, 'erp.invoices.list', array(), null),
        $trigger('erp_invoice_paid', 'erp', 'erp:read', array('erp.invoice.paid'), 'erp.invoices.get', $id, 'erp.invoices.list', array(), null),
        $trigger('erp_quote_created', 'erp', 'erp:read', array('erp.quote.created'), 'erp.quotes.get', $id, 'erp.quotes.list', array(), null),
        $trigger('erp_quote_accepted', 'erp', 'erp:read', array('erp.quote.accepted'), 'erp.quotes.get', $id, 'erp.quotes.list', array(), null),
    );

    $actions = array(
        array('create_product', '', 'products:write', 'products.create'),
        array('update_product', '', 'products:write', 'products.update'),
        array('set_stock', '', 'inventory:write', 'inventory.write'),
        array('create_form_submission', '', 'forms:write', 'forms.submission_create'),
        array('create_workspace_task', 'workspace', 'tasks:write', 'workspace.tasks.create'),
        array('create_workspace_message', 'workspace', 'workspace:write', 'workspace.messages.create'),
        array('create_workspace_table_row', 'workspace', 'workspace:write', 'workspace.table_rows.create'),
    );

    foreach ($actions as $action) {
        $rows[] = array(
            'key'    => $action[0],
            'kind'   => 'action',
            'module' => $action[1],
            'scope'  => $action[2],
            'route'  => $action[3],
        );
    }

    $searches = array(
        array('find_order', '', 'orders:read', 'orders.list', array('order_number', 'email')),
        array('find_customer', '', 'customers:read', 'customers.list', array('email', 'search')),
        array('find_product', '', 'products:read', 'products.list', array('sku', 'barcode', 'search')),
        array('find_form_submission', '', 'forms:read', 'forms.submissions', array('reference_code')),
        array('find_erp_invoice', 'erp', 'erp:read', 'erp.invoices.list', array('number')),
    );

    foreach ($searches as $search) {
        $rows[] = array(
            'key'    => $search[0],
            'kind'   => 'search',
            'module' => $search[1],
            'scope'  => $search[2],
            'route'  => $search[3],
            'by'     => $search[4],
        );
    }

    return $rows;
}

/**
 * The scopes an application for an automation platform needs: the site's
 * name (meta:read), its own subscriptions (webhooks:manage) and every scope
 * the contract's rows use, each once.
 *
 * @return array
 */
function pg_automation_contract_scopes()
{
    $scopes = array('meta:read', 'webhooks:manage');

    foreach (pg_automation_contract() as $row) {
        if (!in_array($row['scope'], $scopes, true)) {
            $scopes[] = $row['scope'];
        }
    }

    return $scopes;
}
