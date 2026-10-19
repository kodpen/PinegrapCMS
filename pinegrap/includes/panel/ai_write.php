<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel actions - "Write with AI".
 *
 *   ai_write   a draft for one field of a product, a page or a custom
 *              form's record, written by Pinegrap AI from the form's current
 *              values; nothing is saved
 *
 * Request: target (product_description | product_short | seo_title |
 * seo_description | record_field), record (product | page | form),
 * record_id (the product or page, 0 for one not saved yet; for a form record
 * the custom form's page), field_id (the form field, for record_field),
 * context (field => value as the form holds it now), tone (neutral |
 * friendly | formal | persuasive), hint (an optional request of the person's
 * own). Answer: text, html (the paragraphs as HTML for a rich text field),
 * format and remaining (drafts the site may still ask for today).
 *
 * With engine "browser" the person's browser writes the draft with its own
 * model, in two steps: step "prompt" (the same request, and language: the
 * code the model writes in) answers the rules and the record's data, format
 * and max; step "finish" (target, record, record_id, field_id and text: the
 * model's answer) answers like the Pinegrap AI request, remaining null. The
 * rules are in includes/fn/ai_write.php.
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

function pg_panel_ai_write($request, $action)
{
    // Kept global: helpers read the session user through `global $user`;
    // see includes/panel/actions.php.
    global $user;

    validate_token();

    $user = validate_user();

    $context = (isset($request['context']) && is_array($request['context'])) ? $request['context'] : array();

    $args = array(
        'target'    => isset($request['target']) ? (string) $request['target'] : '',
        'record'    => isset($request['record']) ? (string) $request['record'] : '',
        'record_id' => isset($request['record_id']) ? (int) $request['record_id'] : 0,
        'field_id'  => isset($request['field_id']) ? (int) $request['field_id'] : 0,
        'context'   => $context,
        'tone'      => (isset($request['tone']) && is_string($request['tone'])) ? $request['tone'] : '',
        'hint'      => (isset($request['hint']) && is_string($request['hint'])) ? $request['hint'] : '',
    );

    if (($request['engine'] ?? '') === 'browser') {
        if (($request['step'] ?? '') === 'finish') {
            $args['text'] = (isset($request['text']) && is_string($request['text'])) ? $request['text'] : '';
            $result = pg_ai_write_browser_finish($user, $args);
        } else {
            $args['language'] = (isset($request['language']) && is_string($request['language'])) ? $request['language'] : '';
            $result = pg_ai_write_browser_prompt($user, $args);

            if (!$result['ok']) {
                respond(array('status' => 'error', 'message' => $result['error']));
            }

            respond(array(
                'status' => 'success',
                'rules'  => $result['rules'],
                'data'   => $result['data'],
                'format' => $result['format'],
                'max'    => $result['max'],
            ));
        }
    } else {
        $result = pg_ai_write($user, $args);
    }

    if (!$result['ok']) {
        respond(array('status' => 'error', 'message' => $result['error']));
    }

    respond(array(
        'status'    => 'success',
        'text'      => $result['text'],
        'html'      => $result['html'],
        'format'    => $result['format'],
        'remaining' => $result['remaining'],
    ));
}
