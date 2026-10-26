<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the external API's side of the record changes an application
 * proposes (proposals.php): one route in a channel and its twin on a task,
 * for every kind of record the Workspace changes.
 *
 * An AI app connected over MCP, or an integration with a key, proposes; the
 * person the connection or the key acts for applies the change on its card,
 * with their own rights. Applying and setting aside are no MCP tools
 * (workspace.changes.apply, workspace.changes.dismiss), and over REST a
 * change an application proposed is decided only from the person's own team
 * devices session (ws_api_proposal_decision_gate()), so an application never
 * applies what it proposed, nor what another application did.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The changes part of a body, as an assistant's answer and a proposal take
 * it: the kinds, actions and fields of ws_change_types(), which
 * ws_changes_input() checks them against. A new kind there (a sales quote, a
 * set of product variants, many new records at once) is described here once
 * and reaches every route that takes changes.
 *
 * @param string $applier who applies a change: the person who asked, or
 *                        the person the key or the connection acts for
 * @param bool   $required
 * @return array the parameter row
 */
function ws_api_changes_param($applier, $required = false)
{
    $param = array(
        'name'        => 'changes',
        'in'          => 'body',
        'type'        => 'list',
        'of'          => array('type' => 'string', 'action' => 'string', 'id' => 'integer', 'fields' => 'object', 'product_ids' => 'integer[]', 'bulk' => 'object', 'reason' => 'string'),
        'max_items'   => WS_CHANGES_PER_ANSWER,
        'description' => 'Proposed record changes, as objects of {type, action, id, fields, product_ids, bulk, reason}. action bulk changes many records of one kind at once (product, stock, product_group, page, file, contact, offer, calendar_event or erp_account) by the rule in bulk: {action (update or delete), scope (all, group with group_id for products, search with a word of the name, ids with a list), only_empty (a text field the records must have empty), field, operation (set with value; percent with value such as 50 or -10; add with value, money in minor units; generate with instruction, the text Pinegrap AI writes for each record, up to three text fields in field separated by commas; values with values, an object of id => new value), round (whole, for money)}; delete in bulk only where an administrator allowed it in the Workspace Settings. The card shows how many records it reaches; ' . $applier . ' applies it. type: product, stock, order, contact, erp_account, erp_quote, product_group, channel, user, page, form, file, offer, calendar_event, task, task_note or note. action: update (the default), create (no id; product, contact, erp_account, erp_quote, product_group, calendar_event, task_note, note), delete (no fields; product, contact, product_group, page, file, offer, calendar_event: a product, a group, a page or a file goes to the Recycle Bin), add or remove (product_group only: product_ids, a list of product ids put into the group or taken out of it), invoice (erp_quote only: id is the quote, no fields; an open, expired or accepted sales quote becomes an invoice draft dated today at the prices quoted when it is applied, an invoiced, rejected or cancelled one is refused). fields: an object of field => new value. product: name, title, short_description, full_description, details, meta_description, meta_keywords, keywords, brand, gtin, mpn, google_product_category, price (minor units, as GET /products gives it), enabled, taxable, tax_rate (a number such as 20, or "" to follow the tax zone), vat_exemption_code, shippable, free_shipping, extra_shipping_cost (minor units), weight, length, width, height (numbers), track_stock, backorder, out_of_stock_message, minimum_quantity, maximum_quantity, reward_points, order_receipt_message, custom_field_1 to custom_field_4, notes. stock: quantity (the new count). order: status (incomplete, complete, exported, cancelled), notes, cancellation_reason (with cancelled only), po_number, custom_field_1, custom_field_2, tracking_numbers (the whole list for an order that ships to one address; the customer is not e-mailed). contact: salutation, first_name, last_name, suffix, nickname, company, title, department, office_location, email, opt_in, phone (mobile), business_phone, business_fax, website, address_1, address_2, city, state, zip, country (the business address), home_address_1, home_address_2, home_city, home_state, home_zip, home_country, home_phone, home_fax, lead_source, tax_number, tax_office, description, member_id, expiration_date (YYYY-MM-DD). erp_account: title, email, phone, address, district, city, state, postcode, country_code (two letters), currency (three letters), tax_number, tax_office, payment_days, credit_limit (minor units), invoice_email, invoice_mail, overdue_notify_days, overdue_notify_customer, status (active, passive), notes. erp_quote (a sales quote, create, or invoice with no fields; never a cart quote): account_id (a list of one current account, active and not a supplier alone), issue_date (YYYY-MM-DD; today when left out), valid_until (YYYY-MM-DD; 30 days after issue_date when left out), currency (three letters; the store\'s when left out), notes, lines (a list of objects as POST /erp/quotes takes them: product_id, description, quantity, unit, unit_price in minor units, discount_rate, tax_rate, tax2_rate, withholding_code, withholding_rate; a line with a product_id takes the product\'s name and VAT rate where they are left out, any other line needs description and tax_rate); it needs account_id and lines, is numbered when applied and is written by the quote editor\'s own save. product_group: name, title, short_description, full_description, meta_description, meta_keywords, keywords, enabled. channel: summary; id is the channel the changes are proposed in. user: role (manager or user; never for an administrator, never for oneself), email; applied by an administrator only. page: title, meta_description, meta_keywords, search, search_keywords (a list or comma-separated words), sitemap, noindex, comments, comments_open, comments_publish, comments_login. form (a submitted form): complete, answers (an object of field name => new answer, for its text fields; only the ones given change). file: description, folder_id, content (the words of a text file only). offer: status (enabled, disabled), description, start_date, end_date (YYYY-MM-DD). calendar_event: name, short_description, location, start_time, end_time (YYYY-MM-DD HH:MM), all_day, published; a new one also takes calendar_ids (a list) and needs name, start_time and calendar_ids. task (update only, by whoever may change the task): title, description, status (todo, doing, waiting, done, cancelled), priority (low, normal, high, urgent), start_date, due_date (YYYY-MM-DD). task_note (create only): text, and task_id (a list of one task; proposed on a task, that task when left out); it is written in the name of the assistant or the application that proposed it. note (create only): title, body; a note of the Workspace, kept for the person who applies it. A new product also takes quantity and group_ids (a list), a new ERP account kind (customer, supplier, both) and is_person, a new product group parent_id (a group id; the top of the catalogue when left out) and product_ids. A new product needs name, a new product group name, a new ERP account title, a new contact one of first_name, last_name, company, email. A new product or product group is not on sale / published unless enabled is true. reason: one line on why.',
    );

    if ($required) {
        $param['required'] = true;
    }

    return $param;
}

/**
 * The route rows. One proposal route for every kind of record: a kind added
 * to ws_change_types() later (erp_quote, variant_set, a bulk create) is
 * proposed here too, with no route of its own.
 *
 * @return array
 */
function ws_proposal_api_routes()
{
    $what = 'Nothing is written to a record here: this only proposes, the person applies, and the application never applies or sets aside a change itself (that is done on the card, or from the person\'s own team devices session: POST /workspace/changes/{id}/apply and /dismiss answer 403 to an application key and to every OAuth connection for a change an application proposed). Each change is checked as its card will be: the person must be allowed to change that kind of record and that record, the fields are laid against what the record holds now (the card shows from and to), and a change that alters nothing or cannot be made refuses the call with 422 naming changes[i] and why, so the call can be sent again with that change fixed or left out; nothing is kept from a refused call. The application must also hold the scope the kind of record is read with elsewhere in the API (orders:read for an order, products:read for a product or its stock, customers:read for a contact...), or the answer is 403 insufficient_scope. A user account (type user) is read by no API scope, so no application proposes a change to one: 403 forbidden naming changes[i]. Every kind of record the Workspace changes is taken here; kinds added later (sales quotes, product variants, many new records at once) join this same endpoint. At most ' . WS_CHANGES_PER_ANSWER . ' changes in a call and ' . WS_PROPOSALS_PER_HOUR . ' proposals an hour for one person from one application (429 rate_limited; Retry-After and the message say in how many seconds a place frees). Proposed with an application key or through an OAuth connection (agent app:<application id> or oauth:<client id>, as GET /workspace/changes lists them); a team devices session changes the record itself and is refused with 403, as are the applications Claude and Pinegrap AI work through, which propose in their answers. Each change answers with status pending until the person applies it (applied), sets it aside (dismissed), or the record changes first (stale).';

    return array(

        array(
            'id'          => 'workspace.changes.propose',
            'method'      => 'POST',
            'path'        => '/workspace/channels/{id}/changes',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_changes_propose',
            'dry_run'     => true,
            'returns'     => 'WorkspaceProposal',
            'status'      => 201,
            'summary'     => 'Propose record changes in a channel',
            'description' => 'How an AI app or an integration proposes a change to a site record (a product, its stock, an order, a customer, a page, a task...) instead of writing it: it writes text into a channel the owner may post in, as the application, with the person the key or the connection acts for mentioned (they are told), and each change waits under it on a card for that person to apply or set aside, with their own rights. ' . $what . ' id may also be a discussion\'s the owner is in.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'text', 'in' => 'body', 'type' => 'string', 'max_length' => 3900, 'required' => true, 'description' => 'What is proposed and why, for the person who reads the cards. Tags such as <#order:1045> or <@user:12> are kept as tags.'),
                ws_api_changes_param('the person the key or the connection acts for', true),
            ),
        ),

        array(
            'id'          => 'workspace.tasks.changes.propose',
            'method'      => 'POST',
            'path'        => '/workspace/tasks/{id}/changes',
            'scope'       => 'workspace:write',
            'handler'     => 'ws_api_task_changes_propose',
            'dry_run'     => true,
            'returns'     => 'WorkspaceProposal',
            'status'      => 201,
            'summary'     => 'Propose record changes on a task',
            'description' => 'The task twin of POST /workspace/channels/{id}/changes: the text and the cards wait in the task\'s drawer, under the assistants\' runs on it, for the person the key or the connection acts for, who is told on the bell. A task_note with no task_id goes on this task; many records at once (bulk) and a channel\'s summary are proposed in a channel, not on a task. While an assistant works on the task the answer is 409 assistant_busy (Retry-After): propose when its run has ended, or in a channel. Needs tasks:read as well, to see the task. ' . $what,
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'text', 'in' => 'body', 'type' => 'string', 'max_length' => 3900, 'required' => true, 'description' => 'What is proposed and why, for the person who reads the cards. Tags such as <#order:1045> or <@user:12> are kept as tags.'),
                ws_api_changes_param('the person the key or the connection acts for', true),
            ),
        ),

    );
}

/**
 * The agent the caller proposes under, or a 403: only an application key or
 * an OAuth connection proposes. A person on their own device changes the
 * record themselves; an assistant's application proposes in its answer to
 * the request it works on, where the person who asked applies it.
 *
 * @return string ws_proposal_agent_key()
 */
function ws_api_proposal_agent()
{
    if (ws_api_is_assistant()) {
        api_fail(403, 'forbidden', lang('An assistant proposes changes in its answer to the request it works on.'));
    }

    $agent = ws_proposal_caller_key(function_exists('api_current_device') ? api_current_device() : null, ws_api_app_id());

    if ($agent === '') {
        api_fail(403, 'forbidden', lang('Changes are proposed by an application, with its key or through an OAuth connection; signed in on your own device, change the record yourself.'));
    }

    return $agent;
}

/**
 * The kinds of record the changes name, checked before any record is looked
 * up, so a refusal says nothing about the record: a kind no application
 * proposes (ws_proposal_closed_change()), then the read scope of each.
 *
 * @param mixed $changes as sent
 */
function ws_api_proposal_scopes($changes)
{
    $types = ws_change_types();
    $closed = ws_proposal_closed_change($changes, $types);

    if ($closed >= 0) {
        api_fail(403, 'forbidden', 'changes[' . $closed . ']: ' . lang('No API scope reads user accounts, so an application cannot propose a change to one.'));
    }

    foreach (ws_proposal_scopes($changes, $types) as $scope) {
        api_require_scope($scope);
    }
}

/**
 * The 429 for an application that made all the proposals it may for this
 * person in the last hour.
 *
 * @param string $agent
 * @param array  $viewer
 */
function ws_api_proposal_limit($agent, $viewer)
{
    if (ws_proposal_limited($agent, (int) $viewer['id'])) {
        $wait = ws_proposal_retry_after($agent, (int) $viewer['id']);

        ws_api_proposal_limit_fail($wait, ws_proposal_limit_text($wait));
    }
}

/**
 * The 429 of the hourly limit, also when it was reached by calls sent at the
 * same moment or another proposal for the person was being written
 * (ws_proposal_store()). Retry-After is when a place frees, not the whole
 * hour.
 *
 * @param int    $retry_after seconds
 * @param string $message
 */
function ws_api_proposal_limit_fail($retry_after, $message)
{
    api_extra_headers('Retry-After', (string) max(1, (int) $retry_after));
    api_fail(429, 'rate_limited', $message);
}

/**
 * A proposal that could not be written, as the API answers it.
 *
 * @param array $result ws_proposal_store()
 */
function ws_api_proposal_store_failed($result)
{
    if (!empty($result['limited'])) {
        ws_api_proposal_limit_fail((int) ($result['retry_after'] ?? 3600), (string) $result['error']);
    }

    api_fail_validation($result['error'], 'text');
}

/**
 * The checked changes, or a 422 naming the one that cannot be proposed.
 *
 * @param mixed $changes    as sent
 * @param array $viewer     the person who applies them
 * @param int   $channel_id
 * @param int   $task_id
 * @return array[] ws_changes_input() rows, at least one
 */
function ws_api_proposal_rows($changes, $viewer, $channel_id, $task_id)
{
    $checked = ws_changes_input($changes, (int) $viewer['id'], (int) $channel_id, (int) $task_id, true);

    if (!$checked['ok']) {
        api_fail_validation($checked['error'], 'changes');
    }

    if (empty($checked['rows'])) {
        api_fail_validation(lang('changes names at least one change.'), 'changes');
    }

    return $checked['rows'];
}

function ws_api_changes_propose($params)
{
    $viewer = ws_api_viewer();
    $agent = ws_api_proposal_agent();
    $channel = ws_api_channel_or_404($viewer, $params['id']);

    if (!ws_can_post_channel($viewer, $channel)) {
        api_fail(403, 'forbidden', lang('You cannot post in that channel.'));
    }

    if (!ws_proposals_ready()) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    ws_api_proposal_scopes($params['changes'] ?? array());

    // The person who applies the cards is told, whatever the text says.
    $text = trim((string) $params['text']);
    $mention = '<@user:' . (int) $viewer['id'] . '>';

    if (strpos($text, $mention) === false) {
        $text = $mention . ' ' . $text;
    }

    $app_id = ws_api_writer_app_id();

    // The checks ws_message_send() makes, made first so that a dry run meets
    // the same refusals.
    $checked = ws_message_send_check($viewer, $channel, $text, array('app_id' => $app_id));

    if (!$checked['ok']) {
        api_fail_validation($checked['error'], 'text');
    }

    $rows = ws_api_proposal_rows($params['changes'] ?? array(), $viewer, $channel['id'], 0);

    ws_api_proposal_limit($agent, $viewer);

    api_dry_run_stop('proposed', 'workspace_proposal', array(
        'channel_id' => (int) $channel['id'],
        'agent'      => $agent,
        'changes'    => count($rows),
    ));

    $result = ws_proposal_store($viewer, $agent, array('channel' => $channel), $text, $rows, $app_id);

    if (!$result['ok']) {
        ws_api_proposal_store_failed($result);
    }

    ws_api_log(lang(array('string' => '{var:1} change(s) proposed in #{var:2}', 'vars' => array(count($rows), $channel['name']))));

    api_ok(ws_api_proposal_present($viewer, ws_api_proposal_or_404($result['request_id'])), 201);
}

function ws_api_task_changes_propose($params)
{
    $viewer = ws_api_viewer();
    $agent = ws_api_proposal_agent();

    // The route asks workspace:write; seeing the task is the tasks scope's.
    api_require_scope('tasks:read');

    $task = ws_api_task_or_404($viewer, $params['id']);

    if (!ws_proposals_ready(true)) {
        api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));
    }

    ws_api_proposal_scopes($params['changes'] ?? array());

    // The checks a note on the task meets (ws_task_note_add()): who may
    // write on it, and the words.
    $checked = ws_task_note_add_check($viewer, $task, (string) $params['text']);

    if (!$checked['ok']) {
        api_fail_validation($checked['error'], 'text');
    }

    $rows = ws_api_proposal_rows($params['changes'] ?? array(), $viewer, 0, $task['id']);

    // A run under way on the task ends after this row would be written, and
    // the bell at its end (task_assistant in notify.php) names the task's
    // newest answered row: it would name the proposal instead of the run.
    // The proposal waits until the run ends, as a second run does
    // (ws_task_delegation_run_check()).
    if (ws_task_delegation_open_run($task['id'])) {
        api_extra_headers('Retry-After', '60');
        api_fail(409, 'assistant_busy', lang('An assistant is working on this task. Propose the changes when its run has ended, or in a channel.'));
    }

    ws_api_proposal_limit($agent, $viewer);

    api_dry_run_stop('proposed', 'workspace_proposal', array(
        'task_id' => (int) $task['id'],
        'agent'   => $agent,
        'changes' => count($rows),
    ));

    $result = ws_proposal_store($viewer, $agent, array('task' => $task), $checked['body'], $rows, ws_api_writer_app_id());

    if (!$result['ok']) {
        ws_api_proposal_store_failed($result);
    }

    ws_api_log(lang(array('string' => '{var:1} change(s) proposed on task {var:2}', 'vars' => array(count($rows), ws_task_number($task['id'])))));

    api_ok(ws_api_proposal_present($viewer, ws_api_proposal_or_404($result['request_id'])), 201);
}

/**
 * A proposal's request row, or a 404.
 *
 * @param int $id
 * @return array
 */
function ws_api_proposal_or_404($id)
{
    $row = db_item("SELECT * FROM ws_ai_requests WHERE id = '" . (int) $id . "' AND " . ws_proposal_where());

    if (!is_array($row)) {
        api_fail_not_found(lang('Proposal'));
    }

    return $row;
}

/**
 * A proposal as the API shows it: where it was made, who proposed it, who
 * applies it, and its changes as GET /workspace/changes lists them, in the
 * order they were sent.
 *
 * @param array $viewer
 * @param array $row ws_ai_requests
 * @return array
 */
function ws_api_proposal_present($viewer, $row)
{
    $task_id = ((int) $row['channel_id'] === 0) ? (int) ($row['task_id'] ?? 0) : 0;
    $changes = array_reverse(ws_api_changes_rows(array("c.request_id = '" . (int) $row['id'] . "'"), WS_CHANGES_PER_ANSWER));

    return array(
        'request_id'   => (int) $row['id'],
        'agent'        => (string) $row['agent'],
        'proposed_by'  => ws_proposal_name((string) $row['agent']),
        'channel_id'   => ((int) $row['channel_id'] > 0) ? (int) $row['channel_id'] : null,
        'task_id'      => ($task_id > 0) ? $task_id : null,
        'message_id'   => ((int) $row['reply_message_id'] > 0) ? (int) $row['reply_message_id'] : null,
        'requested_by' => ws_api_person_ref($row['requested_by']),
        'changes'      => ws_api_changes_present($viewer, $changes),
        'created_at'   => api_time($row['created_at']),
    );
}

// What ws_api_proposal_present() returns.
function ws_api_proposal_schema()
{
    return array(
        'request_id'   => 'integer',
        'agent'        => 'string',
        'proposed_by'  => 'string',
        'channel_id'   => 'integer?',
        'task_id'      => 'integer?',
        'message_id'   => 'integer?',
        'requested_by' => 'WorkspacePerson?',
        'changes'      => 'WorkspaceChange[]',
        'created_at'   => 'string?',
    );
}
