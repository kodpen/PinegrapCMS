<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - ready-made MCP connectors, and a server added by its address
 * alone.
 *
 * The catalogue is a list bundled with the code: the remote MCP servers of
 * well-known services that one click can add, each with its address, the way
 * it signs in, the alias it is offered under and an icon of the icon font.
 * Nothing is fetched from a registry at run time. Three ways of signing in
 * are enough for one click: none (the server answers anybody), oauth (the
 * service registers the site on its own, by a dynamic registration or a
 * client ID metadata document, and the administrator signs in once), and
 * bearer (a token the administrator makes on the service's page and pastes).
 *
 * Adding an entry: a row in ws_mcp_catalogue() with an address that answers
 * an initialize (200 without a credential, or 401 with an OAuth challenge
 * the site can register at), an icon class that exists in
 * assets/fonts/bootstrap-icons, an alias that is free of
 * ws_mcp_reserved_aliases(), and the description in tr.json.
 * tests/workspace_mcp_test.php checks the rows.
 *
 * A server outside the catalogue is added from its address: the probe asks it
 * for an initialize without a credential, reads the OAuth challenge of a 401
 * (pg_mcp_client_discover_auth()) and chooses the sign-in from the answer, so
 * the administrator types the address, then at most a token.
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
 * The ready-made connectors, by key: name, description, url, auth (none,
 * oauth or bearer), token_url (where a token is made, bearer only), icon (a
 * Bootstrap Icons class), alias (offered when it is free) and tools (what the
 * first read turns on: read_only, the tools the server marks as reading
 * only).
 *
 * @return array key => entry
 */
function ws_mcp_catalogue()
{
    return array(
        'deepwiki' => array(
            'name'        => 'DeepWiki',
            'description' => lang('Answers questions about public GitHub repositories from their code and documentation.'),
            'url'         => 'https://mcp.deepwiki.com/mcp',
            'auth'        => 'none',
            'token_url'   => '',
            'icon'        => 'bi-journal-code',
            'alias'       => 'deepwiki',
            'tools'       => 'read_only',
        ),
        'context7' => array(
            'name'        => 'Context7',
            'description' => lang('Up-to-date documentation and code examples of programming libraries.'),
            'url'         => 'https://mcp.context7.com/mcp',
            'auth'        => 'none',
            'token_url'   => '',
            'icon'        => 'bi-braces',
            'alias'       => 'context7',
            'tools'       => 'read_only',
        ),
        'mslearn' => array(
            'name'        => 'Microsoft Learn',
            'description' => lang('Searches and reads Microsoft\'s documentation and code samples.'),
            'url'         => 'https://learn.microsoft.com/api/mcp',
            'auth'        => 'none',
            'token_url'   => '',
            'icon'        => 'bi-microsoft',
            'alias'       => 'mslearn',
            'tools'       => 'read_only',
        ),
        'cloudflare_docs' => array(
            'name'        => 'Cloudflare Docs',
            'description' => lang('Searches Cloudflare\'s developer documentation.'),
            'url'         => 'https://docs.mcp.cloudflare.com/mcp',
            'auth'        => 'none',
            'token_url'   => '',
            'icon'        => 'bi-cloud',
            'alias'       => 'cloudflare',
            'tools'       => 'read_only',
        ),
        'huggingface' => array(
            'name'        => 'Hugging Face',
            'description' => lang('Searches models, datasets, Spaces and papers on Hugging Face.'),
            'url'         => 'https://huggingface.co/mcp',
            'auth'        => 'none',
            'token_url'   => '',
            'icon'        => 'bi-emoji-smile',
            'alias'       => 'huggingface',
            'tools'       => 'read_only',
        ),
        'linear' => array(
            'name'        => 'Linear',
            'description' => lang('Finds and updates issues, projects and comments in Linear.'),
            'url'         => 'https://mcp.linear.app/mcp',
            'auth'        => 'oauth',
            'token_url'   => '',
            'icon'        => 'bi-kanban',
            'alias'       => 'linear',
            'tools'       => 'read_only',
        ),
        'notion' => array(
            'name'        => 'Notion',
            'description' => lang('Searches and edits the pages and databases of a Notion workspace.'),
            'url'         => 'https://mcp.notion.com/mcp',
            'auth'        => 'oauth',
            'token_url'   => '',
            'icon'        => 'bi-journal-text',
            'alias'       => 'notion',
            'tools'       => 'read_only',
        ),
        'sentry' => array(
            'name'        => 'Sentry',
            'description' => lang('Looks into errors, issues and releases in Sentry.'),
            'url'         => 'https://mcp.sentry.dev/mcp',
            'auth'        => 'oauth',
            'token_url'   => '',
            'icon'        => 'bi-bug',
            'alias'       => 'sentry',
            'tools'       => 'read_only',
        ),
        'atlassian' => array(
            'name'        => 'Atlassian',
            'description' => lang('Jira issues and Confluence pages of an Atlassian site.'),
            'url'         => 'https://mcp.atlassian.com/v1/mcp',
            'auth'        => 'oauth',
            'token_url'   => '',
            'icon'        => 'bi-list-check',
            'alias'       => 'atlassian',
            'tools'       => 'read_only',
        ),
        'vercel' => array(
            'name'        => 'Vercel',
            'description' => lang('Projects, deployments and their logs on Vercel.'),
            'url'         => 'https://mcp.vercel.com',
            'auth'        => 'oauth',
            'token_url'   => '',
            'icon'        => 'bi-triangle-fill',
            'alias'       => 'vercel',
            'tools'       => 'read_only',
        ),
        'canva' => array(
            'name'        => 'Canva',
            'description' => lang('Finds, creates and edits designs in Canva.'),
            'url'         => 'https://mcp.canva.com/mcp',
            'auth'        => 'oauth',
            'token_url'   => '',
            'icon'        => 'bi-palette',
            'alias'       => 'canva',
            'tools'       => 'read_only',
        ),
        // GitHub's sign-in server registers no client on its own, so the
        // shared connection is a personal access token sent as Bearer.
        'github' => array(
            'name'        => 'GitHub',
            'description' => lang('Repositories, issues and pull requests on GitHub, with a personal access token.'),
            'url'         => 'https://api.githubcopilot.com/mcp/',
            'auth'        => 'bearer',
            'token_url'   => 'https://github.com/settings/personal-access-tokens/new',
            'icon'        => 'bi-github',
            'alias'       => 'github',
            'tools'       => 'read_only',
        ),
    );
}

/**
 * One ready-made connector.
 *
 * @param mixed $key
 * @return array|null
 */
function ws_mcp_catalogue_entry($key)
{
    if (!is_string($key) || ($key === '')) {
        return null;
    }

    $catalogue = ws_mcp_catalogue();

    return $catalogue[$key] ?? null;
}

/**
 * What a connector asks for, as its tile's badge says it.
 *
 * @return array auth => label
 */
function ws_mcp_catalogue_auth_labels()
{
    return array(
        'none'   => lang('No account needed'),
        'oauth'  => lang('Sign-in needed'),
        'bearer' => lang('Token needed'),
    );
}

/**
 * An address as two servers are compared by: scheme and host in lower case,
 * no default port, no trailing slash; the query counts.
 *
 * @param string $url
 * @return string
 */
function ws_mcp_url_key($url)
{
    $url = trim((string) $url);
    $parts = @parse_url($url);

    if (!is_array($parts) || !isset($parts['host']) || ($parts['host'] === '')) {
        return strtolower(rtrim($url, '/'));
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
    $port = isset($parts['port']) ? (int) $parts['port'] : 0;

    if ((($scheme === 'https') && ($port === 443)) || (($scheme === 'http') && ($port === 80))) {
        $port = 0;
    }

    return $scheme . '://' . strtolower($parts['host']) . (($port > 0) ? ':' . $port : '')
        . rtrim((string) ($parts['path'] ?? ''), '/')
        . (isset($parts['query']) ? '?' . $parts['query'] : '');
}

/**
 * The connectors a site already has, whatever alias they were added under.
 *
 * @param array $servers rows with id, alias and url
 * @return array key => the server row
 */
function ws_mcp_catalogue_added($servers)
{
    $by_url = array();

    foreach ((array) $servers as $server) {
        $by_url[ws_mcp_url_key($server['url'] ?? '')] = $server;
    }

    $added = array();

    foreach (ws_mcp_catalogue() as $key => $entry) {
        $url = ws_mcp_url_key($entry['url']);

        if (isset($by_url[$url])) {
            $added[$key] = $by_url[$url];
        }
    }

    return $added;
}

/**
 * The alias a host suggests: the name it is registered under (linear for
 * mcp.linear.app, example for shop.example.com.tr), shaped as an alias may
 * be. A reserved or too short name gets -mcp; a host with no name, server.
 *
 * @param string $host
 * @return string
 */
function ws_mcp_alias_from_host($host)
{
    $host = strtolower(trim((string) $host, " \t\n\r\0\x0B."));

    if (($host === '') || (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false)) {
        return 'server';
    }

    $labels = explode('.', $host);

    if (count($labels) > 1) {
        $top = array_pop($labels);

        // A country's second level (co.uk, com.tr) is not the name.
        if ((strlen($top) === 2) && (count($labels) > 1)
            && in_array(end($labels), array('co', 'com', 'net', 'org', 'gov', 'edu', 'ac', 'gen', 'web', 'biz', 'info', 'name', 'ltd', 'plc', 'tv', 'me'), true)) {
            array_pop($labels);
        }
    }

    $alias = preg_replace('/[^a-z0-9_-]/', '', (string) end($labels));
    $alias = rtrim(substr(ltrim((string) $alias, '0123456789_-'), 0, 32), '_-');

    if ($alias === '') {
        return 'server';
    }

    if (ws_mcp_alias_shape_problem($alias) !== '') {
        $alias = substr($alias, 0, 28) . '-mcp';
    }

    return (ws_mcp_alias_shape_problem($alias) === '') ? $alias : 'server';
}

/**
 * An alias nobody has yet: the one given, else the same with 2, 3, ... after
 * it. When none is free the one given comes back, and the save says why.
 *
 * @param string $alias
 * @return string
 */
function ws_mcp_alias_free($alias)
{
    $alias = ws_mcp_alias_clean($alias);

    if (ws_mcp_alias_problem($alias) === '') {
        return $alias;
    }

    for ($n = 2; $n <= 9; $n++) {
        $candidate = substr($alias, 0, 31) . $n;

        if (ws_mcp_alias_problem($candidate) === '') {
            return $candidate;
        }
    }

    return $alias;
}

/**
 * The sign-in an address's answer calls for: none when it answered without a
 * credential; oauth when it refused with 401 and the service registers the
 * site on its own (ws_mcp_oauth_registration()); bearer for any other
 * refusal, a token being what such a service gives; '' when no MCP server
 * answered.
 *
 * @param bool       $answered the handshake and the tool list worked
 * @param int        $status   the HTTP status of the refusal
 * @param array|null $found    pg_mcp_client_discover_auth(), on a 401
 * @param bool       $https    the site's own address is https
 * @return string none | oauth | bearer | ''
 */
function ws_mcp_probe_kind($answered, $status, $found, $https)
{
    if ($answered) {
        return 'none';
    }

    if (((int) $status !== 401) && ((int) $status !== 403)) {
        return '';
    }

    if (is_array($found) && !empty($found['ok']) && (ws_mcp_oauth_registration($found, '', (bool) $https) !== '')) {
        return 'oauth';
    }

    return 'bearer';
}

/**
 * Asks an address how it signs in, without a credential: an initialize and
 * the tool list, and on a 401 the OAuth challenge and the service's sign-in
 * metadata. Nothing is registered or saved.
 *
 * @param string        $url
 * @param callable|null $fetch a stand-in for the network, (method, url, body,
 *                             headers) answering like api_http_request(); the
 *                             tests pass one
 * @param bool|null     $https the site's own address is https (null: as
 *                             URL_SCHEME says)
 * @return array ok, kind (none, oauth, bearer or ''), tools, reads (tools
 *               marked as reading only), name, alias (from the host), host,
 *               error, oauth_manual (a sign-in that needs an application of
 *               its own was found)
 */
function ws_mcp_probe($url, $fetch = null, $https = null)
{
    $url = trim((string) $url);
    $host = ws_mcp_host($url);
    $alias = ws_mcp_alias_from_host((string) parse_url($url, PHP_URL_HOST));
    $out = array('ok' => false, 'kind' => '', 'tools' => 0, 'reads' => 0, 'name' => ucfirst($alias), 'alias' => $alias, 'host' => $host, 'error' => '', 'oauth_manual' => false);
    $https = ($https === null) ? (defined('URL_SCHEME') && (URL_SCHEME === 'https://')) : (bool) $https;

    if (!pg_mcp_client_url_is_valid($url)) {
        $out['error'] = lang('The address must be an https address, without a user name, a password or a # part.');
        return $out;
    }

    if (!is_callable($fetch) && !function_exists('curl_init')) {
        $out['error'] = lang('This server cannot make outgoing HTTPS requests (cURL is missing).');
        return $out;
    }

    $conn = pg_mcp_client_connection($url, array(), '', '', WS_MCP_SCREEN_TIMEOUT);

    if (is_callable($fetch)) {
        $conn['transport'] = function ($to, $body, $headers, $seconds, $max_body) use ($fetch) {
            return call_user_func($fetch, 'POST', $to, $body, $headers);
        };
    }

    $open = pg_mcp_client_negotiate($conn);
    $status = (int) $open['status'];
    $error = (string) $open['error'];
    $answered = false;

    if ($open['ok']) {
        $list = pg_mcp_client_list_tools($conn);
        $status = (int) $list['status'];
        $error = (string) $list['error'];

        if ($list['ok']) {
            $answered = true;
            $out['tools'] = count($list['tools']);

            foreach ($list['tools'] as $tool) {
                if (ws_mcp_tool_can_run($tool) === 'read') {
                    $out['reads']++;
                }
            }

            $named = trim((string) ($open['server']['title'] ?? ''));
            $named = ($named !== '') ? $named : trim((string) ($open['server']['name'] ?? ''));

            if ($named !== '') {
                $out['name'] = mb_substr(preg_replace('/\s+/u', ' ', $named), 0, 100);
            }
        }
    }

    $found = null;

    if (!$answered && ($status === 401)) {
        $found = pg_mcp_client_discover_auth($url, $fetch, WS_MCP_SCREEN_TIMEOUT);
    }

    $out['kind'] = ws_mcp_probe_kind($answered, $status, $found, $https);
    $out['ok'] = ($out['kind'] !== '');
    $out['oauth_manual'] = ($out['kind'] === 'bearer') && is_array($found) && !empty($found['ok']);
    $out['error'] = $out['ok'] ? '' : (($error !== '') ? $error : pg_mcp_client_status_text($status));

    return $out;
}

/**
 * What the probe found, in a sentence for the administrator.
 *
 * @param array $probe ws_mcp_probe()
 * @return string
 */
function ws_mcp_probe_text($probe)
{
    $host = (string) ($probe['host'] ?? '');

    switch ((string) ($probe['kind'] ?? '')) {
        case 'none':
            if ((int) $probe['tools'] === 0) {
                return lang(array('string' => '{var:1} answers without an account, but lists no tools.', 'vars' => $host));
            }

            if ((int) $probe['reads'] > 0) {
                return lang(array('string' => '{var:1} answers without an account and has {var:2} tools; the {var:3} it marks as reading only are turned on when it is added.', 'vars' => array($host, (int) $probe['tools'], (int) $probe['reads'])));
            }

            return lang(array('string' => '{var:1} answers without an account and has {var:2} tools. It does not say which of them only read, so turn on the ones you trust after adding it.', 'vars' => array($host, (int) $probe['tools'])));

        case 'oauth':
            return lang(array('string' => '{var:1} asks for a sign-in and registers the site on its own: add it, then sign in to the service on its own page.', 'vars' => $host));

        case 'bearer':
            return !empty($probe['oauth_manual'])
                ? lang(array('string' => '{var:1} asks for a credential. Paste an access token the service gives you; or sign in with an application of your own, under More settings.', 'vars' => $host))
                : lang(array('string' => '{var:1} asks for a credential: paste an access token (API key) the service gives you.', 'vars' => $host));
    }

    return lang(array('string' => 'No MCP server answered at that address: {var:1}', 'vars' => (string) ($probe['error'] ?? '')));
}

/**
 * The colour of the probe's sentence: success, info or danger.
 *
 * @param array $probe ws_mcp_probe()
 * @return string
 */
function ws_mcp_probe_state($probe)
{
    if ((string) ($probe['kind'] ?? '') === 'none') {
        return 'success';
    }

    return in_array((string) ($probe['kind'] ?? ''), array('oauth', 'bearer'), true) ? 'info' : 'danger';
}

/**
 * The server a site already has at an address.
 *
 * @param string $url
 * @return array|null
 */
function ws_mcp_server_at($url)
{
    $key = ws_mcp_url_key($url);

    foreach (ws_mcp_servers() as $server) {
        if (ws_mcp_url_key($server['url']) === $key) {
            return $server;
        }
    }

    return null;
}

/* ---------------------------------------------------------------------------
   The settings screen
   --------------------------------------------------------------------------- */

/**
 * A ready-made connector added (mcp_catalogue_add), or an address asked how
 * it signs in (mcp_probe). Called by ws_mcp_settings_post() for an
 * administrator.
 *
 * A connector without an account is saved and its tools read at once; one
 * people sign in to is saved and the administrator sent to the service's
 * sign-in; one that takes a token opens the add form filled, at the token.
 *
 * @param array    $viewer
 * @param string   $action mcp_catalogue_add | mcp_probe
 * @param liveform $liveform
 * @param string   $username
 * @return string the anchor to go back to
 */
function ws_mcp_catalogue_post($viewer, $action, $liveform, $username)
{
    if ($action === 'mcp_probe') {
        $url = trim(mb_substr(is_scalar($_POST['mcp_url'] ?? '') ? (string) $_POST['mcp_url'] : '', 0, 500));
        $existing = ($url !== '') ? ws_mcp_server_at($url) : null;

        if ($existing) {
            $probe = array('ok' => false, 'kind' => '', 'alias' => (string) $existing['alias'], 'name' => (string) $existing['name']);
            $state = 'info';
            $text = lang(array('string' => 'This address is already added as @{var:1}.', 'vars' => $existing['alias']));
        } else {
            $probe = ws_mcp_probe($url);
            $state = ws_mcp_probe_state($probe);
            $text = ws_mcp_probe_text($probe);
        }

        $liveform->assign_field_value('mcp_kept_for', 'probe');
        $liveform->assign_field_value('mcp_url', $url);
        $liveform->assign_field_value('mcp_auth', (string) $probe['kind']);
        $liveform->assign_field_value('mcp_alias', ($probe['kind'] !== '') ? ws_mcp_alias_free($probe['alias']) : '');
        $liveform->assign_field_value('mcp_name', ($probe['kind'] !== '') ? (string) $probe['name'] : '');
        $liveform->assign_field_value('mcp_probe_state', $state);
        $liveform->assign_field_value('mcp_probe_text', $text);

        return '#ws-mcp-custom';
    }

    $key = is_scalar($_POST['mcp_catalogue'] ?? '') ? (string) $_POST['mcp_catalogue'] : '';
    $entry = ws_mcp_catalogue_entry($key);

    if (!$entry) {
        $liveform->add_error(lang('That connector could not be found.'));
        return '#ws-mcp-catalogue';
    }

    $added = ws_mcp_catalogue_added(ws_mcp_servers());

    if (isset($added[$key])) {
        $liveform->add_notice(lang(array('string' => '{var:1} is already added as @{var:2}.', 'vars' => array(h($entry['name']), h($added[$key]['alias'])))));
        return '#ws-mcp-' . (int) $added[$key]['id'];
    }

    $input = array(
        'mcp_alias'     => ws_mcp_alias_free($entry['alias']),
        'mcp_name'      => $entry['name'],
        'mcp_url'       => $entry['url'],
        'mcp_auth'      => $entry['auth'],
        'mcp_catalogue' => $key,
    );

    // A token is the administrator's to make: the add form opens filled,
    // with the link to the page where it is made.
    if ($entry['auth'] === 'bearer') {
        ws_mcp_settings_keep($liveform, null, '', $input);
        return '#ws-mcp-new';
    }

    return ws_mcp_settings_save($viewer, null, $liveform, $username, $input);
}

/**
 * The ready-made connectors as tiles, each with its Add button, or Already
 * added with a link to the server that is it.
 *
 * @param string $self_url
 * @param array  $servers
 * @return string
 */
function ws_mcp_settings_catalogue($self_url, $servers)
{
    $added = ws_mcp_catalogue_added($servers);
    $labels = ws_mcp_catalogue_auth_labels();
    $badges = array(
        'none'   => 'text-bg-success-subtle text-success-emphasis',
        'oauth'  => 'text-bg-primary-subtle text-primary-emphasis',
        'bearer' => 'text-bg-warning-subtle text-warning-emphasis',
    );
    $hints = array(
        'none'   => lang('Adds it and reads its tools at once.'),
        'oauth'  => lang('Adds it and opens the service\'s page to sign in.'),
        'bearer' => lang('Opens the form to paste a token.'),
    );
    $tiles = '';

    foreach (ws_mcp_catalogue() as $key => $entry) {
        $server = $added[$key] ?? null;

        if ($server) {
            $action = '<a class="btn btn-sm btn-outline-success rounded-pill px-3 text-nowrap" href="#ws-mcp-' . (int) $server['id'] . '"><i class="bi bi-check2 me-1" aria-hidden="true"></i>' . h(lang('Already added')) . '</a>';
            $alias = (string) $server['alias'];
        } else {
            $action = '
                <form method="post" action="' . h($self_url) . '" class="m-0">
                    ' . get_token_field() . '
                    <input type="hidden" name="ws_action" value="mcp_catalogue_add">
                    <input type="hidden" name="mcp_catalogue" value="' . h($key) . '">
                    <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-3 text-nowrap" title="' . h($hints[$entry['auth']]) . '" aria-label="' . h(lang('Add') . ' ' . $entry['name']) . '"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . h(lang('Add')) . '</button>
                </form>';
            $alias = $entry['alias'];
        }

        $tiles .= '
            <div class="col-sm-6 col-xl-4">
                <div class="border rounded-3 p-2 h-100" id="ws-mcp-catalogue-' . h($key) . '">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi ' . h($entry['icon']) . ' fs-5 lh-1" aria-hidden="true"></i>
                        <div class="fw-semibold ws-grow text-truncate">' . h($entry['name']) . '</div>
                        ' . $action . '
                    </div>
                    <div class="small mt-1"><span class="badge ' . $badges[$entry['auth']] . '">' . h($labels[$entry['auth']]) . '</span> <span class="text-body-secondary">@' . h($alias) . '</span></div>
                    <div class="small text-body-secondary mt-1">' . h($entry['description']) . '</div>
                </div>
            </div>';
    }

    return '
        <section class="mb-4" id="ws-mcp-catalogue" aria-labelledby="ws-mcp-catalogue-title">
            <h3 class="h6" id="ws-mcp-catalogue-title">' . h(lang('Ready-made connectors')) . '</h3>
            <p class="small text-body-secondary mb-2">' . h(lang('Add one with a click. A connector without an account works at once; one you sign in to opens the service\'s page first; a token is pasted into the form.')) . '</p>
            <div class="row g-2">' . $tiles . '</div>
        </section>';
}

/**
 * "Add a custom connector": the address alone, checked first; then what the
 * check found and the short form that adds it. The whole form stays behind
 * More settings.
 *
 * @param string $self_url
 * @param array  $kept      ws_mcp_settings_kept()
 * @param string $full_form the whole add form (ws_mcp_settings_form())
 * @return string
 */
function ws_mcp_settings_custom($self_url, $kept, $full_form)
{
    $probed = ($kept['for'] === 'probe');
    $values = $probed ? $kept['values'] : array();
    $kind = (string) ($values['mcp_auth'] ?? '');
    $open = ($kept['for'] === 'new');
    $result = '';

    if ($probed) {
        $state = in_array($kept['state'], array('success', 'info', 'danger'), true) ? $kept['state'] : 'info';
        $result = '<div class="alert alert-' . $state . ' small py-2 mt-2 mb-2" role="status">' . h($kept['text']) . '</div>';

        if (in_array($kind, array('none', 'oauth', 'bearer'), true)) {
            $result .= '
                <form method="post" action="' . h($self_url) . '" class="d-flex flex-wrap align-items-end gap-2" data-ws-mcp-form>
                    ' . get_token_field() . '
                    <input type="hidden" name="ws_action" value="mcp_save">
                    <input type="hidden" name="mcp_id" value="0">
                    <input type="hidden" name="mcp_url" value="' . h($values['mcp_url'] ?? '') . '">
                    <input type="hidden" name="mcp_name" value="' . h($values['mcp_name'] ?? '') . '">
                    <input type="hidden" name="mcp_auth" value="' . h($kind) . '">
                    <div>
                        <label class="form-label small mb-1" for="ws_mcp_probe_alias">' . h(lang('Alias')) . '</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">@</span>
                            <input class="form-control" type="text" name="mcp_alias" id="ws_mcp_probe_alias" value="' . h($values['mcp_alias'] ?? '') . '" maxlength="32" pattern="[A-Za-z][A-Za-z0-9_\-]{1,31}" required autocomplete="off">
                        </div>
                    </div>
                    ' . (($kind === 'bearer') ? '
                    <div class="ws-grow">
                        <label class="form-label small mb-1" for="ws_mcp_probe_token">' . h(lang('Access token')) . '</label>
                        <input class="form-control form-control-sm" type="password" name="mcp_token" id="ws_mcp_probe_token" required autocomplete="new-password" autofocus>
                    </div>' : '') . '
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3"><i class="bi bi-check2 me-1" aria-hidden="true"></i>' . h(($kind === 'oauth') ? lang('Add and sign in') : lang('Add')) . '</button>
                </form>';
        }
    }

    return '
        <section class="mb-4" id="ws-mcp-custom" aria-labelledby="ws-mcp-custom-title">
            <h3 class="h6" id="ws-mcp-custom-title">' . h(lang('Add a custom connector')) . '</h3>
            <form method="post" action="' . h($self_url) . '" class="d-flex flex-wrap align-items-center gap-2">
                ' . get_token_field() . '
                <input type="hidden" name="ws_action" value="mcp_probe">
                <label class="visually-hidden" for="ws_mcp_probe_url">' . h(lang('Server address')) . '</label>
                <input class="form-control form-control-sm w-auto flex-grow-1" type="url" name="mcp_url" id="ws_mcp_probe_url" value="' . h($probed ? ($values['mcp_url'] ?? '') : '') . '" maxlength="500" placeholder="https://" required autocomplete="off">
                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 text-nowrap"><i class="bi bi-search me-1" aria-hidden="true"></i>' . h(lang('Check the address')) . '</button>
                <button type="button" class="btn btn-sm btn-ghost text-nowrap" data-bs-toggle="collapse" data-bs-target="#ws-mcp-new" aria-controls="ws-mcp-new" aria-expanded="' . ($open ? 'true' : 'false') . '"><i class="bi bi-sliders me-1" aria-hidden="true"></i>' . h(lang('More settings')) . '</button>
            </form>
            <div class="form-text">' . h(lang('Only the address is needed: it is checked first, and how the site signs in is chosen from the answer. Servers that run on a computer (npx, stdio) cannot be reached from a website.')) . '</div>
            ' . $result . '
            <div class="collapse mt-2' . ($open ? ' show' : '') . '" id="ws-mcp-new">' . $full_form . '</div>
        </section>';
}
