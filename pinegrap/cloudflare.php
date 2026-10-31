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
// Administrators only, like the settings menu entry that leads here.
validate_area_access($user, 'administrator');

include_once('liveform.class.php');

$liveform = new liveform('cloudflare');

// Use constants if they are defined; otherwise, show error and redirect
if (defined('CLOUDFLARE_API_TOKEN') && defined('CLOUDFLARE_ZONE_ID')) {
    $CF_API_TOKEN = CLOUDFLARE_API_TOKEN;
    $CF_ZONE_ID   = CLOUDFLARE_ZONE_ID;

    if (empty($CF_API_TOKEN) || empty($CF_ZONE_ID)) {
        $liveform->remove_form();
        $liveformsettings = new liveform('settings');

        $liveformsettings->mark_error('', lang('Cloudflare API Token or Zone ID is empty.'));
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/' . pg_settings_return_url());
        exit();
    }
} else {
    $liveform->remove_form();
    $liveformsettings = new liveform('settings');

    $liveformsettings->mark_error('', lang('Cloudflare API Token or Zone ID is not defined.'));
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/' . pg_settings_return_url());
    exit();
}



// ========= CONFIG HELPERS =========
$CF_API_BASE   = 'https://api.cloudflare.com/client/v4/';
// cf_request: REST helper. pg_curl_run() identifies this installation
// (sent with no User-Agent, a request looks like an anonymous client to the
// receiving server's firewall and gets rejected), verifies the certificate
// with pg_curl_tls() and goes through PROXY_ADDRESS when one is set.
function cf_request($method, $path, $body = null, $apiBase = '', $token = '') {
    $url = rtrim($apiBase, '/') . '/' . ltrim($path, '/');
    $answer = pg_curl_run($method, $url, [
        'body'    => ($body !== null) ? json_encode($body) : null,
        'headers' => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        'timeout' => 20,
        'connect_timeout' => 20
    ]);
    return [
        'status' => $answer['status'],
        'json'   => json_decode($answer['body'], true)
    ];
}

// cf_graphql: GraphQL helper, sent like cf_request().
function cf_graphql($body, $token) {
    $answer = pg_curl_run('POST', 'https://api.cloudflare.com/client/v4/graphql', [
        'body'    => json_encode($body),
        'headers' => [
            'Authorization: Bearer '.$token,
            'Content-Type: application/json'
        ],
        'timeout' => 20,
        'connect_timeout' => 20
    ]);
    return [
        'status' => $answer['status'],
        'json'   => json_decode($answer['body'], true)
    ];
}

// -------------------- ACTION HANDLERS --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Every action below changes DNS or zone settings, so reject forged requests first.
    validate_token_field();
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $type   = isset($_POST['type']) ? $_POST['type'] : '';

    // Build the record payload from the posted fields.
    $payload = [
        'type' => $type,
        'name' => isset($_POST['name']) ? $_POST['name'] : '',
        'ttl'  => isset($_POST['ttl']) ? (int)$_POST['ttl'] : 1,
    ];

    switch ($type) {
        case 'A':
        case 'AAAA':
        case 'CNAME':
            $payload['content'] = isset($_POST['content']) ? $_POST['content'] : '';
            $payload['proxied'] = isset($_POST['proxied']);
            break;

        case 'TXT':
            $payload['content'] = isset($_POST['content']) ? $_POST['content'] : '';
            break;

        case 'MX':
            $payload['content']  = isset($_POST['content']) ? $_POST['content'] : '';
            $payload['priority'] = isset($_POST['priority']) ? (int)$_POST['priority'] : 0;
            break;

        case 'NS':
            $payload['content'] = isset($_POST['content']) ? $_POST['content'] : '';
            break;

        case 'SRV':
            $payload['data'] = [
                'service'  => isset($_POST['service']) ? $_POST['service'] : '',
                'proto'    => isset($_POST['proto']) ? $_POST['proto'] : '',
                'name'     => isset($_POST['name']) ? $_POST['name'] : '',
                'priority' => isset($_POST['priority']) ? (int)$_POST['priority'] : 0,
                'weight'   => isset($_POST['weight']) ? (int)$_POST['weight'] : 0,
                'port'     => isset($_POST['port']) ? (int)$_POST['port'] : 0,
                'target'   => isset($_POST['target']) ? $_POST['target'] : ''
            ];
            break;

        case 'CAA':
            $payload['data'] = [
                'flags' => isset($_POST['flags']) ? (int)$_POST['flags'] : 0,
                'tag'   => isset($_POST['tag']) ? $_POST['tag'] : '',
                'value' => isset($_POST['value']) ? $_POST['value'] : ''
            ];
            break;
    }


    // Run the requested action.
    if ($action === 'add_record') {
        $resp = cf_request('POST', "zones/{$CF_ZONE_ID}/dns_records", $payload, $CF_API_BASE, $CF_API_TOKEN);
        if ($resp['status'] !== 200 || empty($resp['json']['success'])) {
            $msg = lang('Add record failed');
            if (!empty($resp['json']['errors'][0]['message'])) {
                $msg .= ': ' . h($resp['json']['errors'][0]['message']);
            }
            $liveform->mark_error('error', $msg);
        } else {
            $liveform->add_notice(lang('Record added successfully.'));
        }
    }

    // A DNS record id is a 32-character hex string. Anything else is refused
    // before it can become part of the API path.
    $record_id = isset($_POST['id']) ? (string)$_POST['id'] : '';
    if (($action === 'edit_record' || $action === 'delete_record') && (preg_match('/^[a-f0-9]{32}$/', $record_id) !== 1)) {
        $liveform->mark_error('error', lang('The DNS record ID is not valid.'));
        $action = '';
    }

    if ($action === 'edit_record') {
        $resp = cf_request('PUT', "zones/{$CF_ZONE_ID}/dns_records/" . $record_id, $payload, $CF_API_BASE, $CF_API_TOKEN);
        if ($resp['status'] !== 200 || empty($resp['json']['success'])) {
            $msg = lang('Edit record failed');
            if (!empty($resp['json']['errors'][0]['message'])) {
                $msg .= ': ' . h($resp['json']['errors'][0]['message']);
            }
            $liveform->mark_error('error', $msg);
        } else {
            $liveform->add_notice(lang('Record updated successfully.'));
        }
    }

    if ($action === 'delete_record') {
        $resp = cf_request('DELETE', "zones/{$CF_ZONE_ID}/dns_records/" . $record_id, null, $CF_API_BASE, $CF_API_TOKEN);
        if ($resp['status'] !== 200 || empty($resp['json']['success'])) {
            $liveform->mark_error('error', lang('Delete record failed'));
        } else {
            $liveform->add_notice(lang('Record deleted successfully.'));
        }
    }


    // Development Mode toggle
    if ($action === 'toggle_dev') {
        $value = isset($_POST['value']) ? $_POST['value'] : 'off';
        $resp = cf_request('PATCH', "zones/{$CF_ZONE_ID}/settings/development_mode", ['value' => $value], $CF_API_BASE, $CF_API_TOKEN);
        if ($resp['status'] !== 200 || empty($resp['json']['success'])) {
            $liveform->mark_error('error', lang('Development Mode toggle failed'));
        } else {
            $liveform->add_notice(lang(array('string' => 'Development Mode updated to {var:1}.', 'vars' => $value)));
        }
    }
    
    // Under Attack Mode toggle
    if ($action === 'toggle_uam') {
        $value = isset($_POST['value']) ? $_POST['value'] : 'medium';
        $resp = cf_request('PATCH', "zones/{$CF_ZONE_ID}/settings/security_level", ['value' => $value], $CF_API_BASE, $CF_API_TOKEN);
        if ($resp['status'] !== 200 || empty($resp['json']['success'])) {
            $liveform->mark_error('error', lang('Under Attack Mode toggle failed'));
        } else {
            $liveform->add_notice(lang(array('string' => 'Under Attack Mode updated to {var:1}.', 'vars' => $value)));
        }
    }
    
    // Always Use HTTPS toggle
    if ($action === 'toggle_always_https') {
        $value = isset($_POST['value']) ? $_POST['value'] : 'off';
        $resp = cf_request('PATCH', "zones/{$CF_ZONE_ID}/settings/always_use_https", ['value' => $value], $CF_API_BASE, $CF_API_TOKEN);
        if ($resp['status'] !== 200 || empty($resp['json']['success'])) {
            $liveform->mark_error('error', lang('Always Use HTTPS toggle failed'));
        } else {
            $liveform->add_notice(lang(array('string' => 'Always Use HTTPS updated to {var:1}.', 'vars' => $value)));
        }
    }
    
    // Brotli toggle
    if ($action === 'toggle_brotli') {
        $value = isset($_POST['value']) ? $_POST['value'] : 'off';
        $resp = cf_request('PATCH', "zones/{$CF_ZONE_ID}/settings/brotli", ['value' => $value], $CF_API_BASE, $CF_API_TOKEN);
        if ($resp['status'] !== 200 || empty($resp['json']['success'])) {
            $liveform->mark_error('error', lang('Brotli toggle failed'));
        } else {
            $liveform->add_notice(lang(array('string' => 'Brotli updated to {var:1}.', 'vars' => $value)));
        }
    }
    
    // Rocket Loader toggle
    if ($action === 'toggle_rocket') {
        $value = isset($_POST['value']) ? $_POST['value'] : 'off';
        $resp = cf_request('PATCH', "zones/{$CF_ZONE_ID}/settings/rocket_loader", ['value' => $value], $CF_API_BASE, $CF_API_TOKEN);
        if ($resp['status'] !== 200 || empty($resp['json']['success'])) {
            $msg = lang('Rocket Loader toggle failed (feature may be deprecated)');
            if (!empty($resp['json']['errors'][0]['message'])) {
                $msg .= ': ' . h($resp['json']['errors'][0]['message']);
            }
            $liveform->mark_error('error', $msg);
        } else {
            $liveform->add_notice(lang(array('string' => 'Rocket Loader updated to {var:1}.', 'vars' => $value)));
        }
    }
    
    // Clear Cache
    if ($action === 'clear_cache') {
        $resp = cf_request('POST', "zones/{$CF_ZONE_ID}/purge_cache", ['purge_everything' => true], $CF_API_BASE, $CF_API_TOKEN);
        if ($resp['status'] !== 200 || empty($resp['json']['success'])) {
            $liveform->mark_error('error', lang('Cache purge failed'));
        } else {
            $liveform->add_notice(lang('Cache successfully purged.'));
        }
    }

    // PRG pattern: redirect after POST so a refresh does not repeat the action.
    // A toggled switch gets focus back on the new page, and a custom period
    // keeps its dates.
    $redirect_query = [];
    $switch_ids = ['toggle_dev' => 'cf_dev', 'toggle_uam' => 'cf_uam', 'toggle_always_https' => 'cf_https', 'toggle_brotli' => 'cf_brotli', 'toggle_rocket' => 'cf_rocket'];
    if (isset($switch_ids[$action])) {
        $redirect_query['focus'] = $switch_ids[$action];
    }
    foreach (['start_date', 'end_date'] as $date_key) {
        if (isset($_POST[$date_key]) && is_string($_POST[$date_key]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST[$date_key])) {
            $redirect_query[$date_key] = $_POST[$date_key];
        }
    }
    header("Location: ".$_SERVER['PHP_SELF'].($redirect_query ? '?'.http_build_query($redirect_query) : ''));
    exit;
}

// -------------------- FETCH DATA --------------------

// DNS records (paginated, per_page=100)
$resp_dns = cf_request('GET', "zones/{$CF_ZONE_ID}/dns_records?per_page=100", null, $CF_API_BASE, $CF_API_TOKEN);

$permission_errors = array();

if (!($resp_dns['status'] === 200 && !empty($resp_dns['json']['success']))) {
    $err = isset($resp_dns['json']['errors'][0]['message']) ? $resp_dns['json']['errors'][0]['message'] : lang('Unknown error');
    if ($resp_dns['status'] === 403 && (stripos($err, 'Authentication') !== false || stripos($err, 'permission') !== false)) {
        $permission_errors[] = lang('This token does not have the required permission for DNS Management (Zone.DNS.Edit).');
    } else {
        $permission_errors[] = lang(array('string' => 'DNS records request failed: {var:1}', 'vars' => h($err)));
    }
}

// Without this a token lacking the DNS permission produced an empty record
// table and no explanation; the reasons are shown with the other form errors.
foreach ($permission_errors as $index => $permission_error) {
    $liveform->mark_error('permission_' . $index, $permission_error);
}

$records = ($resp_dns['status'] === 200 && !empty($resp_dns['json']['success']))
    ? (isset($resp_dns['json']['result']) ? $resp_dns['json']['result'] : array())
    : array();

// Settings
$resp_dev = cf_request('GET', "zones/{$CF_ZONE_ID}/settings/development_mode", null, $CF_API_BASE, $CF_API_TOKEN);
$dev_mode = ($resp_dev['status'] === 200 && !empty($resp_dev['json']['success']))
    ? (isset($resp_dev['json']['result']['value']) ? $resp_dev['json']['result']['value'] : 'off')
    : 'off';

$resp_uam = cf_request('GET', "zones/{$CF_ZONE_ID}/settings/security_level", null, $CF_API_BASE, $CF_API_TOKEN);
$uam_mode = ($resp_uam['status'] === 200 && !empty($resp_uam['json']['success']))
    ? (isset($resp_uam['json']['result']['value']) ? $resp_uam['json']['result']['value'] : 'medium')
    : 'medium';

$resp_always = cf_request('GET', "zones/{$CF_ZONE_ID}/settings/always_use_https", null, $CF_API_BASE, $CF_API_TOKEN);
$always_https = ($resp_always['status'] === 200 && !empty($resp_always['json']['success']))
    ? (isset($resp_always['json']['result']['value']) ? $resp_always['json']['result']['value'] : 'off')
    : 'off';

$resp_brotli = cf_request('GET', "zones/{$CF_ZONE_ID}/settings/brotli", null, $CF_API_BASE, $CF_API_TOKEN);
$brotli = ($resp_brotli['status'] === 200 && !empty($resp_brotli['json']['success']))
    ? (isset($resp_brotli['json']['result']['value']) ? $resp_brotli['json']['result']['value'] : 'off')
    : 'off';

$resp_rocket = cf_request('GET', "zones/{$CF_ZONE_ID}/settings/rocket_loader", null, $CF_API_BASE, $CF_API_TOKEN);
$rocket = ($resp_rocket['status'] === 200 && !empty($resp_rocket['json']['success']))
    ? (isset($resp_rocket['json']['result']['value']) ? $resp_rocket['json']['result']['value'] : 'off')
    : 'off';

// -------------------- AUDIT LOGS FETCH --------------------
$audit_logs = array();
$audit_error = '';

// Read the account ID from config, or fall back to the zone lookup below.
if (defined('CLOUDFLARE_ACCOUNT_ID') && !empty(CLOUDFLARE_ACCOUNT_ID)) {
    $CF_ACCOUNT_ID = CLOUDFLARE_ACCOUNT_ID;
} else {
    // Not in config: fetch the account ID from the zone via the API.
    $resp_zone = cf_request('GET', "zones/{$CF_ZONE_ID}", null, $CF_API_BASE, $CF_API_TOKEN);
    if ($resp_zone['status'] === 200 && !empty($resp_zone['json']['success'])) {
        $CF_ACCOUNT_ID = isset($resp_zone['json']['result']['account']['id']) 
            ? $resp_zone['json']['result']['account']['id'] 
            : '';
    }
    if (empty($CF_ACCOUNT_ID)) {
        $audit_error = lang('CLOUDFLARE_ACCOUNT_ID could not be determined. Define it in your config or ensure token has Zone:Read permission.');
    }
}

if (!empty($CF_ACCOUNT_ID)) {
    $audit_since  = date('Y-m-d', strtotime('-7 days'));
    $audit_before = date('Y-m-d');

    if (isset($_REQUEST['log_start']) && isset($_REQUEST['log_end'])) {
        $log_start = trim($_REQUEST['log_start']);
        $log_end   = trim($_REQUEST['log_end']);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $log_start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $log_end)) {
            $audit_since  = $log_start;
            $audit_before = $log_end;
        }
    }

    $audit_url = "accounts/{$CF_ACCOUNT_ID}/logs/audit?since={$audit_since}&before={$audit_before}&per_page=50";
    $resp_audit = cf_request('GET', $audit_url, null, $CF_API_BASE, $CF_API_TOKEN);

    if ($resp_audit['status'] === 200 && !empty($resp_audit['json']['success'])) {
        $audit_logs = isset($resp_audit['json']['result']) ? $resp_audit['json']['result'] : array();
    } else {
        $audit_error = lang(array('string' => 'Audit Logs API error (HTTP {var:1})', 'vars' => $resp_audit['status']));
        if (!empty($resp_audit['json']['errors'][0]['message'])) {
            $audit_error .= ': ' . $resp_audit['json']['errors'][0]['message'];
        }
    }
}

// -------------------- FIREWALL EVENTS FETCH (GraphQL) --------------------
$fw_start = date('Y-m-d', strtotime('-7 days'));
$fw_end   = date('Y-m-d');

if (isset($_REQUEST['log_start']) && isset($_REQUEST['log_end'])) {
    $log_start = trim($_REQUEST['log_start']);
    $log_end   = trim($_REQUEST['log_end']);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $log_start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $log_end)) {
        $fw_start = $log_start;
        $fw_end   = $log_end;
    }
}

$fw_query = [
    'query' => 'query {
        viewer {
            zones(filter: { zoneTag: "'.$CF_ZONE_ID.'" }) {
                firewallEventsAdaptiveGroups(
                    limit: 50,
                    filter: { date_geq: "'.$fw_start.'", date_leq: "'.$fw_end.'" }
                ) {
                    dimensions {
                        action
                        clientIP
                        rayName
                    }
                    sum {
                        requests
                    }
                    max {
                        occurred_at
                    }
                }
            }
        }
    }'
];

$resp_fw = cf_graphql($fw_query, $CF_API_TOKEN);
$firewall_events = array();
if ($resp_fw['status'] === 200 && !empty($resp_fw['json']['data']['viewer']['zones'][0]['firewallEventsAdaptiveGroups'])) {
    $firewall_events = $resp_fw['json']['data']['viewer']['zones'][0]['firewallEventsAdaptiveGroups'];
}

// -------------------- BUILD LOG TABLES --------------------
// Audit Logs table
$audit_rows = '';
if (!empty($audit_logs)) {
    foreach ($audit_logs as $log) {
        // Action: V2 = object, V1 = string
        if (is_array($log['action'])) {
            $action_type   = isset($log['action']['type']) ? (string)$log['action']['type'] : '-';
            $action_result = isset($log['action']['result']) ? (string)$log['action']['result'] : '';
            $action = $action_type . ($action_result ? ' (' . $action_result . ')' : '');
        } else {
            $action = isset($log['action']) ? (string)$log['action'] : '-';
        }

        // Actor
        $actor = '-';
        if (isset($log['actor']['email'])) {
            $actor = (string)$log['actor']['email'];
        } elseif (isset($log['actor']['ip'])) {
            $actor = (string)$log['actor']['ip'];
        } elseif (isset($log['actor']['id'])) {
            $actor = (string)$log['actor']['id'];
        }

        // Resource (V2: resource.product)
        $resource = '-';
        if (isset($log['resource']['product']) && is_string($log['resource']['product'])) {
            $resource = (string)$log['resource']['product'];
        } elseif (isset($log['resource']['type']) && is_string($log['resource']['type'])) {
            $resource = (string)$log['resource']['type'];
        }

        // Zone (V2: zone.name)
        $zone_name = '-';
        if (isset($log['zone']['name']) && is_string($log['zone']['name'])) {
            $zone_name = (string)$log['zone']['name'];
        }

        $timestamp = isset($log['timestamp']) ? (string)$log['timestamp'] : '-';

        $badge_class = 'bg-secondary';
        if (stripos($action, 'delete') !== false) $badge_class = 'bg-danger';
        elseif (stripos($action, 'create') !== false) $badge_class = 'bg-success';
        elseif (stripos($action, 'update') !== false || stripos($action, 'change') !== false) $badge_class = 'bg-warning fg-black';
        elseif (stripos($action, 'login') !== false) $badge_class = 'bg-info';

        $audit_rows .= '<tr>
            <td><small>'.htmlspecialchars($timestamp, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401).'</small></td>
            <td><span class="badge '.$badge_class.'">'.htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401).'</span></td>
            <td>'.htmlspecialchars($actor, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401).'</td>
            <td><small>'.htmlspecialchars($resource, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401).'</small></td>
            <td><small>'.htmlspecialchars($zone_name, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401).'</small></td>
        </tr>';
    }
} else {
    $audit_error_msg = !empty($audit_error) 
        ? $audit_error 
        : lang('No audit logs found for this period.');
    $audit_rows = '<tr><td colspan="5" class="text-center fg-danger">'.$audit_error_msg.'</td></tr>';
}



// Firewall Events table
$fw_rows = '';
if (!empty($firewall_events)) {
    foreach ($firewall_events as $event) {
        $fw_action = isset($event['dimensions']['action']) ? htmlspecialchars((string)$event['dimensions']['action'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401) : '-';
        $fw_ip     = isset($event['dimensions']['clientIP']) ? htmlspecialchars((string)$event['dimensions']['clientIP'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401) : '-';
        $fw_ray    = isset($event['dimensions']['rayName']) ? htmlspecialchars((string)$event['dimensions']['rayName'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401) : '-';
        $fw_count  = isset($event['sum']['requests']) ? (int)$event['sum']['requests'] : 0;
        $fw_time   = isset($event['max']['occurred_at']) ? htmlspecialchars((string)$event['max']['occurred_at'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401) : '-';

        $fw_badge = 'bg-secondary';
        if ($fw_action === 'block') $fw_badge = 'bg-danger';
        elseif ($fw_action === 'challenge') $fw_badge = 'bg-warning fg-black';
        elseif ($fw_action === 'jschallenge') $fw_badge = 'bg-warning fg-black';
        elseif ($fw_action === 'managed_challenge') $fw_badge = 'bg-info';
        elseif ($fw_action === 'allow') $fw_badge = 'bg-success';
        elseif ($fw_action === 'log') $fw_badge = 'bg-secondary';

        $fw_rows .= '<tr>
            <td><small>'.$fw_time.'</small></td>
            <td><span class="badge '.$fw_badge.'">'.$fw_action.'</span></td>
            <td>'.$fw_ip.'</td>
            <td>'.$fw_count.'</td>
            <td><small>'.$fw_ray.'</small></td>
        </tr>';
    }
} else {
    $fw_rows = '<tr><td colspan="5" class="text-center fg-secondary">' . lang('No firewall events found for this period.') . '</td></tr>';
}


// Log date range values for form
$log_start_val = isset($_REQUEST['log_start']) ? htmlspecialchars($_REQUEST['log_start'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401) : date('Y-m-d', strtotime('-7 days'));
$log_end_val   = isset($_REQUEST['log_end'])   ? htmlspecialchars($_REQUEST['log_end'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401)   : date('Y-m-d');


// -------------------- ANALYTICS --------------------
function getCloudflareAnalytics($CF_API_TOKEN, $CF_ZONE_ID, $days, $area, $start_date = null, $end_date = null) {
    global $permission_errors;

    $analytics = [
        'requests'        => 0,
        'bandwidth'       => 0,
        'uniques'         => 0,
        'cached_requests' => 0,
        'cached_bytes'    => 0
    ];

    if ($start_date && $end_date) {
        $start = $start_date;
        $end   = $end_date;
    } else {
        $start = date('Y-m-d', strtotime('-'.$days.' days'));
        $end   = date('Y-m-d');
    }

    // ---- Requests, Bandwidth, Cached ----
    $graphql_query = [
      'query' => 'query {
        viewer {
          zones(filter: { zoneTag: "'.$CF_ZONE_ID.'" }) {
            httpRequests1dGroups(limit: '.$days.', filter: {
              date_geq: "'.$start.'",
              date_leq: "'.$end.'"
            }) {
              sum {
                requests
                bytes
                cachedRequests
                cachedBytes
              }
            }
          }
        }
      }'
    ];

    $resp = cf_graphql($graphql_query, $CF_API_TOKEN);
    if ($resp['status'] === 200 && !empty($resp['json']['data']['viewer']['zones'][0]['httpRequests1dGroups'])) {
        foreach ($resp['json']['data']['viewer']['zones'][0]['httpRequests1dGroups'] as $day) {
            $analytics['requests']        += isset($day['sum']['requests']) ? $day['sum']['requests'] : 0;
            $analytics['bandwidth']       += isset($day['sum']['bytes']) ? $day['sum']['bytes'] : 0;
            $analytics['cached_requests'] += isset($day['sum']['cachedRequests']) ? $day['sum']['cachedRequests'] : 0;
            $analytics['cached_bytes']    += isset($day['sum']['cachedBytes']) ? $day['sum']['cachedBytes'] : 0;
        }
    }

    // ---- Unique Visitors ----
    $graphql_uniques = [
      'query' => 'query {
        viewer {
          zones(filter: { zoneTag: "'.$CF_ZONE_ID.'" }) {
            httpRequestsAdaptiveGroups(
              limit: 1000,
              filter: { date_geq: "'.$start.'", date_leq: "'.$end.'" }
            ) {
              sum { visits }
            }
          }
        }
      }'
    ];

    $resp_uniques = cf_graphql($graphql_uniques, $CF_API_TOKEN);
    if ($resp_uniques['status'] === 200 && !empty($resp_uniques['json']['data']['viewer']['zones'][0]['httpRequestsAdaptiveGroups'])) {
        foreach ($resp_uniques['json']['data']['viewer']['zones'][0]['httpRequestsAdaptiveGroups'] as $row) {
            $analytics['uniques'] += isset($row['sum']['visits']) ? $row['sum']['visits'] : 0;
        }
    }

    if ($analytics['uniques'] === 0) {
        $graphql_uniques2 = [
            'query' => 'query {
                viewer {
                  zones(filter: { zoneTag: "'.$CF_ZONE_ID.'" }) {
                    httpRequests1dGroups(
                      limit: '.$days.',
                      filter: { date_geq: "'.$start.'", date_leq: "'.$end.'" }
                    ) {
                      uniq { uniques }
                    }
                  }
                }
            }'
        ];
        $resp_uniques2 = cf_graphql($graphql_uniques2, $CF_API_TOKEN);
        if ($resp_uniques2['status'] === 200 && !empty($resp_uniques2['json']['data']['viewer']['zones'][0]['httpRequests1dGroups'])) {
            foreach ($resp_uniques2['json']['data']['viewer']['zones'][0]['httpRequests1dGroups'] as $row) {
                $analytics['uniques'] += isset($row['uniq']['uniques']) ? $row['uniq']['uniques'] : 0;
            }
        }
    }

    // ---- Format output ----
    switch ($area) {
        case 'requests':
            return pg_format_number($analytics['requests'], 0);
        case 'uniques':
            return pg_format_number($analytics['uniques'], 0);
        case 'bandwidth':
            return pg_format_number((isset($analytics['bandwidth']) ? $analytics['bandwidth'] : 0) / 1000 / 1000, 2) . ' MB';
        case 'percent_cached':
            return ($analytics['bandwidth'] > 0)
                ? pg_format_number(($analytics['cached_bytes'] / $analytics['bandwidth']) * 100, 2) . ' %'
                : '0 %';
        case 'cached_bytes':
            return pg_format_number((isset($analytics['cached_bytes']) ? $analytics['cached_bytes'] : 0) / 1024 / 1024, 2) . ' MB';
        default:
            return '';
    }
}


// -------------------- PERIOD + CUSTOM RANGE + REFRESH --------------------
if (!isset($_SESSION['software']['cloudflare']['analytics']['period'])) {
    $_SESSION['software']['cloudflare']['analytics']['period'] = '30d';
}
if (isset($_REQUEST['period'])) {
    $_SESSION['software']['cloudflare']['analytics']['period'] = $_REQUEST['period'];
}
$period = ($_SESSION['software']['cloudflare']['analytics']['period'] ?? '');

$start_date = null;
$end_date   = null;

switch ($period) {
    case '24h':   $days = 1;   break;
    case '7d':    $days = 7;   break;
    case '30d':   $days = 30;  break;
    case 'all':   $days = 500; break;
    case 'custom':
        if (isset($_REQUEST['start_date'], $_REQUEST['end_date']) &&
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $_REQUEST['start_date']) &&
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $_REQUEST['end_date'])) {
            $start_date = $_REQUEST['start_date'];
            $end_date   = $_REQUEST['end_date'];
            $days = max(1, (int) ((strtotime($end_date) - strtotime($start_date)) / 86400) + 1);
        } else {
            $days = 30;
        }
        break;
    default:      $days = 30;  break;
}

// The switches and Clear Cache post their own form: a custom period sends its
// dates along so the redirect keeps them, and the switch toggled last gets
// focus back after the redirect.
$period_fields = ($start_date !== null)
    ? '<input type="hidden" name="start_date" value="' . h($start_date) . '"><input type="hidden" name="end_date" value="' . h($end_date) . '">'
    : '';
$focus_switch = (isset($_GET['focus']) && is_string($_GET['focus'])) ? $_GET['focus'] : '';
$switch_focus = function ($id) use ($focus_switch) {
    return ($id === $focus_switch) ? ' autofocus' : '';
};


// -------------------- DNS TABLE --------------------
$table_rows = '';
foreach ($records as $r) {
    $data_content = isset($r['content']) ? $r['content'] : (isset($r['data']['target']) ? $r['data']['target'] : '');
        $attrs = 'data-id="'.htmlspecialchars((string)$r['id'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401).'" 
              data-type="'.htmlspecialchars((string)$r['type'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401).'" 
              data-name="'.htmlspecialchars((string)$r['name'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401).'" 
              data-content="'.htmlspecialchars((string)$data_content, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401).'" 
              data-ttl="'.(int)$r['ttl'].'" 
              data-proxied="'.(!empty($r['proxied']) ? '1' : '0').'"';


    // Cloudflare returns an MX priority at the top level of the record; an
    // SRV one sits in data, with the top-level value as the fallback.
    if ($r['type'] === 'MX' && isset($r['priority'])) {
        $attrs .= ' data-priority="'.(int)$r['priority'].'"';
    }

    if ($r['type'] === 'SRV' && !empty($r['data'])) {
        foreach ($r['data'] as $k => $v) {
            $v = is_array($v) ? json_encode($v) : (string)$v;
            $attrs .= ' data-'.htmlspecialchars((string)$k, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401).'="'.htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401).'"';

        }
        if (!isset($r['data']['priority']) && isset($r['priority'])) {
            $attrs .= ' data-priority="'.(int)$r['priority'].'"';
        }
    }

    if ($r['type'] === 'CAA' && !empty($r['data'])) {
        foreach ($r['data'] as $k => $v) {
            $v = is_array($v) ? json_encode($v) : (string)$v;
            $attrs .= ' data-' . htmlspecialchars((string)$k, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401) . '="' . htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401) . '"';

        }
    }


    $table_rows .= '<tr>
        <td>
            <button class="btn btn-sm btn-outline theme-warning" data-bs-toggle="dialog" data-bs-target="#editModal" aria-label="' . h(lang('Edit')) . '" '.$attrs.'>
                <i class="bi bi-pencil-square"></i>
            </button>
            <form method="post" style="display:inline" onsubmit="event.preventDefault(); var f=this; pgConfirm({title:\'' . h(escape_javascript(lang('Delete'))) . '\', message:\'' . h(escape_javascript(lang('Are you sure you want to delete this record?'))) . '\', confirmText:\'' . h(escape_javascript(lang('Delete'))) . '\', cancelText:\'' . h(escape_javascript(lang('Cancel'))) . '\', variant:\'danger\'}).then(function(ok){if(ok) f.submit();}); return false;">
                '.get_token_field().'
                <input type="hidden" name="action" value="delete_record">
                <input type="hidden" name="id" value="' . htmlspecialchars((string)$r['id'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401) . '">
                <button type="submit" class="btn btn-sm btn-outline theme-danger" aria-label="' . h(lang('Delete')) . '"><i class="bi bi-trash"></i></button>
            </form>
        </td>
        <td><span class="badge theme-info">' . htmlspecialchars((string)$r['type'], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401) . '</span></td>
        <td>'.truncate($r['name'],50).'</td>
        <td>'.truncate($data_content,50).'</td>
        <td>'.(($r['ttl'] == 1) ? lang('Auto') : (int)$r['ttl']).'</td>
        <td>'.(!empty($r['proxied']) ? '<span class="badge theme-success">' . lang('Yes') . '</span>' : '<span class="badge theme-secondary">' . lang('No') . '</span>').'</td>
    </tr>';
}

// -------------------- TABS ACTIVE --------------------
$active_24h = ($period === '24h') ? 'active" aria-current="page' : '';
$active_7d  = ($period === '7d')  ? 'active" aria-current="page' : '';
$active_30d = ($period === '30d') ? 'active" aria-current="page' : '';
$active_all = ($period === 'all') ? 'active" aria-current="page' : '';
$active_cus = ($period === 'custom') ? 'active" aria-current="page' : '';


// -------------------- PRINT --------------------
print pg_page_shell([
    'title'   => lang('Cloudflare Tools'),
    'extra classes' => 'setting',
    'icon'    => 'setting',
    'heading' => lang('Cloudflare Tools'),
    'cancel'  => ['enable'=>true,'title'=>lang('Cancel')]
]) . '
<main id="content" class="container-fluid">

  <div class="row"><div class="col-12">

    '.$liveform->output_errors().$liveform->get_warnings().$liveform->output_notices().'

    <!-- Period Tabs -->
    <ul class="nav nav-tabs mb-5">
      <li class="nav-item">
        <a class="nav-link '.$active_24h.'" href="?period=24h">' . lang('24 Hours') . '</a>
      </li>
      <li class="nav-item">
        <a class="nav-link '.$active_7d.'" href="?period=7d">' . lang('7 Days') . '</a>
      </li>
      <li class="nav-item">
        <a class="nav-link '.$active_30d.'" href="?period=30d">' . lang('30 Days') . '</a>
      </li>
      <li class="nav-item">
        <a class="nav-link '.$active_all.'" href="?period=all">' . lang('All Time') . '</a>
      </li>
      <li class="nav-item">
        <a class="nav-link '.$active_cus.'" href="?period=custom">' . lang('Custom') . '</a>
      </li>
    </ul>

    '.(($period === 'custom') ? '
    <!-- Custom Date Range -->
    <form method="get" class="mb-5">
      <input type="hidden" name="period" value="custom">
      <div class="row g-3">
        <div class="md:col-4">
          <input type="date" data-pg-date name="start_date" value="' . htmlspecialchars((string)isset($_REQUEST['start_date']) ? $_REQUEST['start_date'] : '', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401) . '" class="form-control" required>
        </div>
        <div class="md:col-4">
          <input type="date" data-pg-date name="end_date" value="' . htmlspecialchars((string)isset($_REQUEST['end_date']) ? $_REQUEST['end_date'] : '', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401) . '" class="form-control" required>
        </div>
        <div class="md:col-4">
          <button type="submit" class="btn btn-solid theme-primary">' . lang('Apply') . '</button>
        </div>
      </div>
    </form>
    ' : '').'

    <!-- Analytics -->
    <div class="row mb-7">
      <div class="md:col-2"><div class="card text-center"><div class="card-body">
        <h6>' . lang('Requests') . '</h6>
        <p>'.getCloudflareAnalytics($CF_API_TOKEN,$CF_ZONE_ID,$days,'requests',$start_date,$end_date).'</p>
      </div></div></div>
      <div class="md:col-2"><div class="card text-center"><div class="card-body">
        <h6>' . lang('Unique Visitors') . '</h6>
        <p>'.getCloudflareAnalytics($CF_API_TOKEN,$CF_ZONE_ID,$days,'uniques',$start_date,$end_date).'</p>
      </div></div></div>
      <div class="md:col-2"><div class="card text-center"><div class="card-body">
        <h6>' . lang('Bandwidth') . '</h6>
        <p>'.getCloudflareAnalytics($CF_API_TOKEN,$CF_ZONE_ID,$days,'bandwidth',$start_date,$end_date).'</p>
      </div></div></div>
      <div class="md:col-2"><div class="card text-center"><div class="card-body">
        <h6>' . lang('Percent Cached') . '</h6>
        <p>'.getCloudflareAnalytics($CF_API_TOKEN,$CF_ZONE_ID,$days,'percent_cached',$start_date,$end_date).'</p>
      </div></div></div>
      <div class="md:col-2"><div class="card text-center"><div class="card-body">
        <h6>' . lang('Cached Data') . '</h6>
        <p>'.getCloudflareAnalytics($CF_API_TOKEN,$CF_ZONE_ID,$days,'cached_bytes',$start_date,$end_date).'</p>
      </div></div></div>
    </div>

    <!-- Settings -->
    <div class="card my-7">
      <div class="card-header bg-transparent border-0 fw-bold">
        <i class="bi bi-sliders"></i> ' . lang('Settings') . '
      </div>
      <div class="card-body">
        <!-- Development Mode -->
        <form method="post" class="disable_shortcut mx-5 my-12">'.get_token_field().$period_fields.'
          <input type="hidden" name="action" value="toggle_dev">
          <input type="hidden" name="value" value="'.($dev_mode==="on"?"off":"on").'">
          <div class="form-field">
            <div class="switch"><input class="" type="checkbox" id="cf_dev" onchange="this.form.submit()"'.$switch_focus('cf_dev').' '.($dev_mode==="on"?"checked":"").'></div>
            <label class="" for="cf_dev">' . lang('Development Mode') . '</label>
          </div>
        </form>
        <!-- Under Attack Mode -->
        <form method="post" class="disable_shortcut mx-5 my-12">'.get_token_field().$period_fields.'
          <input type="hidden" name="action" value="toggle_uam">
          <input type="hidden" name="value" value="'.($uam_mode==="under_attack"?"medium":"under_attack").'">
          <div class="form-field">
            <div class="switch"><input class="" type="checkbox" id="cf_uam" onchange="this.form.submit()"'.$switch_focus('cf_uam').' '.($uam_mode==="under_attack"?"checked":"").'></div>
            <label class="" for="cf_uam">' . lang('Under Attack Mode') . '</label>
          </div>
        </form>
        <!-- Always Use HTTPS -->
        <form method="post" class="disable_shortcut mx-5 my-12">'.get_token_field().$period_fields.'
          <input type="hidden" name="action" value="toggle_always_https">
          <input type="hidden" name="value" value="'.($always_https==="on"?"off":"on").'">
          <div class="form-field">
            <div class="switch"><input class="" type="checkbox" id="cf_https" onchange="this.form.submit()"'.$switch_focus('cf_https').' '.($always_https==="on"?"checked":"").'></div>
            <label class="" for="cf_https">' . lang('Always Use HTTPS') . '</label>
          </div>
        </form>
        <!-- Brotli -->
        <form method="post" class="disable_shortcut mx-5 my-12">'.get_token_field().$period_fields.'
          <input type="hidden" name="action" value="toggle_brotli">
          <input type="hidden" name="value" value="'.($brotli==="on"?"off":"on").'">
          <div class="form-field">
            <div class="switch"><input class="" type="checkbox" id="cf_brotli" onchange="this.form.submit()"'.$switch_focus('cf_brotli').' '.($brotli==="on"?"checked":"").'></div>
            <label class="" for="cf_brotli">' . lang('Brotli') . '</label>
          </div>
        </form>
        <!-- Rocket Loader -->
        <form method="post" class="disable_shortcut mx-5 my-12">'.get_token_field().$period_fields.'
          <input type="hidden" name="action" value="toggle_rocket">
          <input type="hidden" name="value" value="'.($rocket==="on"?"off":"on").'">
          <div class="form-field">
            <div class="switch"><input class="" type="checkbox" id="cf_rocket" onchange="this.form.submit()"'.$switch_focus('cf_rocket').' '.($rocket==="on"?"checked":"").'></div>
            <label class="" for="cf_rocket">' . lang('Rocket Loader') . '</label>
          </div>
        </form>
        <!-- Clear Cache -->
        <form method="post" class="disable_shortcut mx-5 my-12">'.get_token_field().$period_fields.'
          <input type="hidden" name="action" value="clear_cache">
          <button type="submit" class="btn btn-solid theme-danger"><i class="bi bi-trash"></i> ' . lang('Clear Cache') . '</button>
        </form>
      </div>
    </div>

    <!-- DNS Records -->
    <div class="card my-7">
      <div class="card-header d-flex justify-content-between align-items-center">
        <label class="form-label mb-0 fw-bold"><i class="bi bi-hdd-network"></i> ' . lang('DNS Records') . '</label>
        <button class="btn btn-solid theme-primary btn-sm" data-bs-toggle="dialog" data-bs-target="#addModal"><i class="bi bi-plus-circle"></i> ' . lang('Add Record') . '</button>
      </div>
      <div class="card-body">
        <table class="table chart table-striped w-100">
          <thead ><tr><th>' . lang('Actions') . '</th><th>' . lang('Type') . '</th><th>' . lang('Name') . '</th><th>' . lang('Content') . '</th><th>' . lang('TTL') . '</th><th>' . lang('Proxied') . '</th></tr></thead>
          <tbody>'.$table_rows.'</tbody>
        </table>
      </div>
    </div>

    <!-- Zone Logs -->
    <div class="card my-7">
      <div class="card-header d-flex justify-content-between align-items-center">
        <label class="form-label mb-0 fw-bold"><i class="bi bi-list-columns-reverse"></i> ' . lang('Zone Logs') . '</label>
      </div>
      <div class="card-body">

        <!-- Log Date Range Filter -->
        <form method="get" class="mb-7">
          <div class="row g-3 align-items-end">
            <div class="md:col-3">
              <label class="form-label small">' . lang('Start Date') . '</label>
              <input type="date" data-pg-date name="log_start" value="'.$log_start_val.'" class="form-control" required>
            </div>
            <div class="md:col-3">
              <label class="form-label small">' . lang('End Date') . '</label>
              <input type="date" data-pg-date name="log_end" value="'.$log_end_val.'" class="form-control" required>
            </div>
            <div class="md:col-2">
              <button type="submit" class="btn btn-solid theme-primary btn-sm"><i class="bi bi-arrow-clockwise"></i> ' . lang('Refresh Logs') . '</button>
            </div>
          </div>
        </form>

        <!-- Audit Logs -->
        <h6 class="mb-5"><i class="bi bi-shield-check"></i> ' . lang('Audit Logs') . ' <small class="fg-secondary">(' . lang('Account Activity') . ')</small></h6>
        <div class="table-responsive mb-7">
          <table class="table table-sm table-striped w-100">
            <thead><tr><th>' . lang('Timestamp') . '</th><th>' . lang('Action') . '</th><th>' . lang('Actor') . '</th><th>' . lang('Resource') . '</th><th>' . lang('Zone') . '</th></tr></thead>



            <tbody>'.$audit_rows.'</tbody>
          </table>
        </div>

        <!-- Firewall Events -->
        <h6 class="mb-5"><i class="bi bi-shield-exclamation"></i> ' . lang('Firewall Events') . ' <small class="fg-secondary">(' . lang('Security Events') . ')</small></h6>
        <div class="table-responsive">
          <table class="table table-sm table-striped w-100">
            <thead><tr><th>' . lang('Time') . '</th><th>' . lang('Action') . '</th><th>' . lang('Client IP') . '</th><th>' . lang('Requests') . '</th><th>' . lang('Ray ID') . '</th></tr></thead>
            <tbody>'.$fw_rows.'</tbody>
          </table>
        </div>

      </div>
    </div>

  </div></div>

<!-- Add Record Modal -->
<dialog class="dialog" id="addModal" aria-labelledby="addModal_title">
    <form method="post" class="d-contents">
      '.get_token_field().'
      <input type="hidden" name="action" value="add_record">
      <div class="dialog-header"><h5 class="dialog-title" id="addModal_title">' . lang('Add DNS Record') . '</h5><button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button></div>
      <div class="dialog-body">
        <div class="mb-5"><label class="form-label" for="add_type">' . lang('Type') . '</label>
          <select name="type" id="add_type" class="form-control" required>
            <option value="A">A</option><option value="AAAA">AAAA</option><option value="CNAME">CNAME</option>
            <option value="TXT">TXT</option><option value="MX">MX</option><option value="NS">NS</option>
            <option value="SRV">SRV</option><option value="CAA">CAA</option>
          </select>
        </div>
        <div class="mb-5"><label class="form-label" for="add_name">' . lang('Name') . '</label><input type="text" name="name" id="add_name" class="form-control" required></div>
        <div class="mb-5 common-content"><label class="form-label" for="add_content">' . lang('Content') . '</label><input type="text" name="content" id="add_content" class="form-control"></div>
        <div class="mb-5"><label class="form-label" for="add_ttl">' . lang('TTL') . '</label>
          <select name="ttl" id="add_ttl" class="form-control"><option value="1">' . lang('Auto') . '</option><option value="60">' . lang('1 min') . '</option><option value="300">' . lang('5 min') . '</option><option value="3600" selected>' . lang('1 hour') . '</option><option value="86400">' . lang('1 day') . '</option></select>
        </div>
        <div class="form-field proxied-only"><input class="check" type="checkbox" name="proxied" id="add_proxied"><label class="" for="add_proxied">' . lang('Proxied') . '</label></div>
        <!-- MX extra -->
        <div class="mb-5 type-extra type-mx d-none"><label class="form-label" for="add_priority">' . lang('Priority') . '</label><input type="number" name="priority" id="add_priority" class="form-control" min="0" max="65535"></div>
        <!-- SRV extra -->
        <div class="type-extra type-srv d-none">
          <div class="mb-5"><label for="add_service">' . lang('Service') . '</label><input type="text" name="service" id="add_service" class="form-control"></div>
          <div class="mb-5"><label for="add_proto">' . lang('Protocol') . '</label><input type="text" name="proto" id="add_proto" class="form-control"></div>
          <div class="mb-5"><label for="add_priority2">' . lang('Priority') . '</label><input type="number" name="priority" id="add_priority2" class="form-control" min="0" max="65535"></div>
          <div class="mb-5"><label for="add_weight">' . lang('Weight') . '</label><input type="number" name="weight" id="add_weight" class="form-control" min="0" max="65535"></div>
          <div class="mb-5"><label for="add_port">' . lang('Port') . '</label><input type="number" name="port" id="add_port" class="form-control" min="0" max="65535"></div>
          <div class="mb-5"><label for="add_target">' . lang('Target') . '</label><input type="text" name="target" id="add_target" class="form-control"></div>
        </div>
        <!-- CAA extra -->
        <div class="type-extra type-caa d-none">
          <div class="mb-5"><label for="add_flags">' . lang('Flags') . '</label><input type="number" name="flags" id="add_flags" class="form-control" min="0" max="255"></div>
          <div class="mb-5"><label for="add_tag">' . lang('Tag') . '</label>
            <select name="tag" id="add_tag" class="form-control">
              <option value="issue">issue</option>
              <option value="issuewild">issuewild</option>
              <option value="iodef">iodef</option>
            </select>
          </div>
          <div class="mb-5"><label for="add_value">' . lang('Value') . '</label><input type="text" name="value" id="add_value" class="form-control"></div>
        </div>
      </div>
      <div class="dialog-footer"><button type="submit" class="btn btn-solid theme-primary">' . lang('Add') . '</button></div>
    </form>
</dialog>

<!-- Edit Record Modal -->
<dialog class="dialog" id="editModal" aria-labelledby="editModal_title">
    <form method="post" class="d-contents">
      '.get_token_field().'
      <input type="hidden" name="action" value="edit_record">
      <input type="hidden" name="id" id="edit_id">
      <div class="dialog-header"><h5 class="dialog-title" id="editModal_title">' . lang('Edit DNS Record') . '</h5><button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button></div>
      <div class="dialog-body">
        <div class="mb-5"><label for="edit_type">' . lang('Type') . '</label>
          <select name="type" id="edit_type" class="form-control" required>
            <option value="A">A</option><option value="AAAA">AAAA</option><option value="CNAME">CNAME</option>
            <option value="TXT">TXT</option><option value="MX">MX</option><option value="NS">NS</option>
            <option value="SRV">SRV</option><option value="CAA">CAA</option>
          </select>
        </div>
        <div class="mb-5"><label for="edit_name">' . lang('Name') . '</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
        <div class="mb-5 common-content"><label for="edit_content">' . lang('Content') . '</label><input type="text" name="content" id="edit_content" class="form-control"></div>
        <div class="mb-5"><label for="edit_ttl">' . lang('TTL') . '</label>
          <select name="ttl" id="edit_ttl" class="form-control">
            <option value="1">' . lang('Auto') . '</option><option value="60">' . lang('1 min') . '</option><option value="300">' . lang('5 min') . '</option>
            <option value="3600">' . lang('1 hour') . '</option><option value="86400">' . lang('1 day') . '</option>
          </select>
        </div>
        <div class="form-field proxied-only"><input class="check" type="checkbox" name="proxied" id="edit_proxied"><label class="" for="edit_proxied">' . lang('Proxied') . '</label></div>
        <!-- MX extra -->
        <div class="mb-5 type-extra type-mx d-none"><label for="edit_priority">' . lang('Priority') . '</label><input type="number" name="priority" id="edit_priority" class="form-control" min="0" max="65535"></div>
        <!-- SRV extra -->
        <div class="type-extra type-srv d-none">
          <div class="mb-5"><label for="edit_service">' . lang('Service') . '</label><input type="text" name="service" id="edit_service" class="form-control"></div>
          <div class="mb-5"><label for="edit_proto">' . lang('Protocol') . '</label><input type="text" name="proto" id="edit_proto" class="form-control"></div>
          <div class="mb-5"><label for="edit_priority2">' . lang('Priority') . '</label><input type="number" name="priority" id="edit_priority2" class="form-control" min="0" max="65535"></div>
          <div class="mb-5"><label for="edit_weight">' . lang('Weight') . '</label><input type="number" name="weight" id="edit_weight" class="form-control" min="0" max="65535"></div>
          <div class="mb-5"><label for="edit_port">' . lang('Port') . '</label><input type="number" name="port" id="edit_port" class="form-control" min="0" max="65535"></div>
          <div class="mb-5"><label for="edit_target">' . lang('Target') . '</label><input type="text" name="target" id="edit_target" class="form-control"></div>
        </div>
        <!-- CAA extra -->
        <div class="type-extra type-caa d-none">
          <div class="mb-5"><label for="edit_flags">' . lang('Flags') . '</label><input type="number" name="flags" id="edit_flags" class="form-control" min="0" max="255"></div>
          <div class="mb-5"><label for="edit_tag">' . lang('Tag') . '</label>
            <select name="tag" id="edit_tag" class="form-control">
              <option value="issue">issue</option>
              <option value="issuewild">issuewild</option>
              <option value="iodef">iodef</option>
            </select>
          </div>
          <div class="mb-5"><label for="edit_value">' . lang('Value') . '</label><input type="text" name="value" id="edit_value" class="form-control"></div>
        </div>
      </div>
      <div class="dialog-footer"><button type="submit" class="btn btn-solid theme-warning">' . lang('Save') . '</button></div>
    </form>
</dialog>

<script>
function toggleExtraFields(selectEl, modalPrefix) {
  const modal = document.getElementById(modalPrefix+"Modal");
  const type = selectEl.value.toLowerCase();
  const hideProxied = ["txt","mx","ns"].includes(type);
  const hideContent = ["srv","caa"].includes(type);

  // A hidden group is disabled as well, so its fields are not posted: the
  // MX and SRV priority boxes share one name and the last one would win.
  const show = function (el, visible) {
    el.classList.toggle("d-none", !visible);
    el.querySelectorAll("input, select").forEach(field => { field.disabled = !visible; });
  };
  modal.querySelectorAll(".type-extra").forEach(el => show(el, el.classList.contains("type-"+type)));
  modal.querySelectorAll(".common-content").forEach(el => show(el, !hideContent));
  modal.querySelectorAll(".proxied-only").forEach(el => show(el, !hideProxied));

  // Every type that shows Content (A, AAAA, CNAME, TXT, MX, NS) needs it.
  modal.querySelectorAll(".common-content input").forEach(field => { field.required = !hideContent; });
}

document.addEventListener("DOMContentLoaded", function() {
  const addType = document.querySelector("#add_type");
  if (addType) {
    addType.addEventListener("change", () => toggleExtraFields(addType, "add"));
    toggleExtraFields(addType, "add");
  }
  const editType = document.querySelector("#edit_type");
  if (editType) {
    editType.addEventListener("change", () => toggleExtraFields(editType, "edit"));
    toggleExtraFields(editType, "edit");
  }

  var editModal = document.getElementById("editModal");
  pgUI.dialog.on(editModal, "show", function (event) {
    var button = event.relatedTarget;
    document.getElementById("edit_id").value      = button.getAttribute("data-id");
    document.getElementById("edit_type").value    = button.getAttribute("data-type");
    document.getElementById("edit_name").value    = button.getAttribute("data-name");
    document.getElementById("edit_content").value = button.getAttribute("data-content") || "";
    document.getElementById("edit_ttl").value     = button.getAttribute("data-ttl");
    document.getElementById("edit_proxied").checked = (button.getAttribute("data-proxied") === "1");

    if (button.getAttribute("data-type")==="MX") {
      document.getElementById("edit_priority").value = button.getAttribute("data-priority") || "";
    }

    // SRV populate
    if (button.getAttribute("data-type")==="SRV") {
      document.getElementById("edit_service").value   = button.getAttribute("data-service") || "";
      document.getElementById("edit_proto").value     = button.getAttribute("data-proto") || "";
      document.getElementById("edit_priority2").value = button.getAttribute("data-priority") || "";
      document.getElementById("edit_weight").value    = button.getAttribute("data-weight") || "";
      document.getElementById("edit_port").value      = button.getAttribute("data-port") || "";
      document.getElementById("edit_target").value    = button.getAttribute("data-target") || "";
    }

    // CAA populate
    if (button.getAttribute("data-type")==="CAA") {
      document.getElementById("edit_flags").value = button.getAttribute("data-flags") || "";
      document.getElementById("edit_tag").value   = button.getAttribute("data-tag") || "";
      document.getElementById("edit_value").value = button.getAttribute("data-value") || "";
    }

    // show the fields that match the selected type
    toggleExtraFields(document.getElementById("edit_type"), "edit");
  });
});
</script>

</main>
' . get_date_picker_format() . pg_fields_script() . output_footer();

$liveform->remove_form();
?>
