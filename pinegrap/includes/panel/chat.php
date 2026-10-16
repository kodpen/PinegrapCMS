<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel actions - live chat, in the panel and on the site.
 *
 *   pg_panel_chat       chat_* - the panel's live chat (chat.php)
 *   pg_panel_site_chat  site_chat_* - the site visitor's live chat
 *                       (chat.php)
 *
 * Called by pg_panel_dispatch() (includes/panel/actions.php); see that file
 * for the contract.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_PANEL_ACTIONS')) {
    exit;
}

function pg_panel_chat($request, $action)
{
    // Live chat (backend)
    // Single gate: session + CSRF token here; application rules (pairing
    // rule, side/ownership, rate limit, delete authority) live in chat.php.
    // When the schema has not been upgraded yet,
    // pg_chat_handle_backend_action returns a polite error — no page
    // breaks (pg_chat_ready probe).
    if (!USER_LOGGED_IN) {
        respond(array(
            'status' => 'error',
            'message' => 'Invalid login.'
        ));
    }

    validate_token();

    require_once(PG_FUNCTIONS_DIR . '/chat.php');

    respond(pg_chat_handle_backend_action($action, $request));
}

function pg_panel_site_chat($request, $action)
{
    // Live chat (site)
    // Visitor/member endpoints. bootstrap hands the token to the client;
    // every other action validates it inside chat.php
    // (pg_chat_site_token_ok). Ownership is session-based (same pattern as
    // the cart's order_id); captcha and rate limiting live in the module.
    require_once(PG_FUNCTIONS_DIR . '/chat.php');

    respond(pg_chat_handle_site_action($action, $request));
}
