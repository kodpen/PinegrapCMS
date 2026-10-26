<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - how an application's tag (<@app:N>) is drawn.
 *
 * The application linked as Claude reads "@Claude" and Pinegrap AI's reads
 * "@ai"; any other application reads as a handle made from its own name
 * (api_apps.name), with that name beside it. A handle has the shape of an MCP
 * server's alias (ws_mcp_alias_shape_problem()) and never takes one of the
 * Workspace's own words or an alias a server already holds, so "@" means one
 * thing; where the name gives none, the handle is app-<id>. No history of
 * the links is kept, so an application Claude used before reads as itself.
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
 * The handle an application's name gives, without the @.
 *
 * @param string   $name   the application's name
 * @param int      $app_id its id, for the fallback app-<id>
 * @param string[] $taken  handles held already (MCP servers' aliases)
 * @return string
 */
function ws_app_handle($name, $app_id, $taken = array())
{
    // Accented Latin letters are folded so a Turkish name keeps its words;
    // anything else outside a-z, 0-9, _ and - becomes one dash.
    $fold = array(
        'ı' => 'i', 'İ' => 'i', 'ş' => 's', 'Ş' => 's', 'ğ' => 'g', 'Ğ' => 'g',
        'ü' => 'u', 'Ü' => 'u', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c',
        'â' => 'a', 'Â' => 'a', 'î' => 'i', 'Î' => 'i', 'û' => 'u', 'Û' => 'u',
        'ä' => 'a', 'Ä' => 'a', 'é' => 'e', 'É' => 'e', 'è' => 'e', 'È' => 'e',
        'ß' => 'ss',
    );

    $handle = strtolower(strtr(trim((string) $name), $fold));
    $handle = preg_replace('/[^a-z0-9_-]+/', '-', $handle);
    $handle = preg_replace('/-{2,}/', '-', $handle);
    $handle = preg_replace('/^[^a-z]+/', '', $handle);
    $handle = rtrim(substr($handle, 0, 32), '-_');

    $taken = array_map('ws_mcp_alias_clean', (array) $taken);

    if (!preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $handle)
        || in_array($handle, ws_mcp_reserved_aliases(), true)
        || in_array($handle, $taken, true)) {
        return 'app-' . (int) $app_id;
    }

    return $handle;
}

/**
 * How one application's tag is drawn.
 *
 * @param int        $app_id        the tag's id
 * @param array|null $app           its api_apps row (id, name), null when it is gone
 * @param int        $claude_app_id the application linked as Claude (0 for none)
 * @param int        $ai_app_id     Pinegrap AI's application (0 for none)
 * @param string[]   $taken         handles held already (MCP servers' aliases)
 * @return array label, meta, icon, known, gone
 */
function ws_app_tag($app_id, $app, $claude_app_id, $ai_app_id, $taken = array())
{
    $app_id = (int) $app_id;

    // The linked assistants keep their names while the link points at them,
    // also when the link is switched off or its application was deleted.
    if (($app_id > 0) && ($app_id === (int) $ai_app_id)) {
        return array('label' => '@ai', 'meta' => lang('Pinegrap AI'), 'icon' => 'bi-cpu', 'known' => true, 'gone' => false);
    }

    if (($app_id > 0) && ($app_id === (int) $claude_app_id)) {
        return array('label' => '@Claude', 'meta' => lang('AI assistant'), 'icon' => 'bi-stars', 'known' => true, 'gone' => false);
    }

    if (!is_array($app)) {
        return array('label' => '@app-' . $app_id, 'meta' => lang('Deleted record'), 'icon' => 'bi-x-circle', 'known' => false, 'gone' => true);
    }

    $name = trim((string) ($app['name'] ?? ''));

    return array(
        'label' => '@' . ws_app_handle($name, $app_id, $taken),
        'meta'  => ($name !== '') ? $name : lang('Application'),
        'icon'  => 'bi-plug',
        'known' => true,
        'gone'  => false,
    );
}
