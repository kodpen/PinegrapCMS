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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 * 
 */

/**
 *
 * This page allows managers to create, list, and delete custom API applications.
 * Each application has an API key, secret key, access method, and permissions.
 * Permissions define what the application can do (Cloudflare token style).
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'manager');

license_check(array('output'=>'validate'));

include_once('liveform.class.php');
$liveform = new liveform('apps_settings');

/**
 * Access method options (GET/POST).
 */
$app_method_options = array('
    <option value="" disabled selected>-'.lang(array('string'=>'Select {var:1}','vars'=>lang('Access Method'))).'-</option>
    <option value="POST">'.lang('Only POST').'</option>
    <option value="GET">'.lang('POST or GET').'</option>
');

/**
 * Render the API Tester tab HTML.
 */
function apps_tester_html($api_base_url, $apps, $my_secret_key, $endpoints) {
    $apps_json   = json_encode($apps,        JSON_UNESCAPED_UNICODE);
    $secret_json = json_encode($my_secret_key, JSON_UNESCAPED_UNICODE);
    $base_json   = json_encode($api_base_url,  JSON_UNESCAPED_UNICODE);

    $apps_options = '<option value="">— ' . lang('Select Application') . ' —</option>';
    foreach ($apps as $app) {
        $apps_options .= '<option value="' . h($app['key']) . '">' . h($app['name']) . ' (' . h($app['method']) . ')</option>';
    }

    $sidebar = '';
    $panels  = '';
    foreach ($endpoints as $idx => $ep) {
        $active_btn   = $idx === 0 ? ' active' : '';
        $active_panel = $idx === 0 ? ' show active' : '';
        $panel_id     = 'tester-panel-' . h($ep['action']);

        $sidebar .= '
            <button type="button" class="list-group-item list-group-item-action py-2 tester-ep-btn' . $active_btn . '"
                    data-panel="' . $panel_id . '">
                <span class="small fw-medium">' . h($ep['label']) . '</span>
            </button>';

        $fields_html = '';
        foreach ($ep['fields'] as $f) {
            $req_star = !empty($f['req']) ? ' <span class="text-danger">*</span>' : '';
            $hint     = !empty($f['hint']) ? '<div class="form-text" style="font-size:.72em;">' . h($f['hint']) . '</div>' : '';

            if ($f['type'] === 'select') {
                $opts = '';
                foreach ($f['options'] as $val => $lbl) {
                    $opts .= '<option value="' . h($val) . '">' . h($lbl) . '</option>';
                }
                $input = '<select class="form-select form-select-sm tester-field" name="' . h($f['name']) . '">' . $opts . '</select>';
            } else {
                $input = '<input type="' . h($f['type']) . '" class="form-control form-control-sm tester-field" name="' . h($f['name']) . '" placeholder="' . h($f['name']) . '">';
            }
            $fields_html .= '
                <div class="col-12 col-sm-6 col-lg-4">
                    <label class="form-label mb-1 small">' . h($f['label']) . $req_star . '</label>
                    ' . $input . $hint . '
                </div>';
        }

        $panels .= '
            <div class="tester-panel' . ($idx !== 0 ? ' d-none' : '') . '" id="' . $panel_id . '">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center bg-white border-bottom py-2">
                        <code class="text-dark fw-bold">action=' . h($ep['action']) . '</code>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">' . $fields_html . '</div>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary btn-sm tester-run-btn" data-action="' . h($ep['action']) . '">
                                <i class="bi bi-play-fill me-1"></i>' . lang('Send') . '
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm tester-clear-btn">
                                <i class="bi bi-x-circle me-1"></i>' . lang('Clear') . '
                            </button>
                        </div>
                        <small class="text-muted tester-url-preview" style="font-size:.7em; word-break:break-all;"></small>
                    </div>
                </div>
                <div class="card border-0 shadow-sm tester-response-card d-none">
                    <div class="card-header d-flex justify-content-between align-items-center bg-white py-2">
                        <span class="small fw-semibold">' . lang('Response') . '</span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge tester-status-badge"></span>
                            <span class="small text-muted tester-timing"></span>
                            <button type="button" class="btn btn-outline-secondary btn-sm tester-copy-btn py-0 px-2" style="font-size:.75em;">
                                <i class="bi bi-clipboard me-1"></i>' . lang('Copy') . '
                            </button>
                        </div>
                    </div>
                    <pre class="tester-response-body m-0 p-3" style="background:#1e1e1e;color:#d4d4d4;border-radius:0 0 8px 8px;font-size:.8em;max-height:500px;overflow:auto;"></pre>
                </div>
            </div>';
    }

    $copied_text = lang('Copied');
    $sending_text = lang('Sending');
    $send_text = lang('Send');
    $error_text = lang('A system error occurred.');
    $creds_required = lang('API Key and Secret Key are required.');

    return '
        <div class="row g-3">
            <div class="col-12 col-md-3 col-xl-2">
                <div class="card border-0 shadow-sm sticky-top" style="top:16px;">
                    <div class="card-header bg-dark text-white py-2 small fw-bold">
                        <i class="bi bi-broadcast me-1"></i> ' . lang('Endpoints') . '
                    </div>
                    <div class="list-group list-group-flush">' . $sidebar . '</div>
                </div>
            </div>
            <div class="col-12 col-md-9 col-xl-10">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body py-2">
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-md-4">
                                <label class="form-label mb-1 small fw-semibold">' . lang('Application (API Key)') . '</label>
                                <select class="form-select form-select-sm" id="tester-app-selector">' . $apps_options . '</select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label mb-1 small fw-semibold">' . lang('API Key') . '</label>
                                <div class="input-group input-group-sm">
                                    <input type="password" class="form-control" id="tester-api-key" placeholder="API KEY" autocomplete="off">
                                    <button class="btn btn-outline-secondary show-password-btn bi bi-eye" type="button"></button>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label mb-1 small fw-semibold">' . lang('Secret Key') . '</label>
                                <div class="input-group input-group-sm">
                                    <input type="password" class="form-control" id="tester-secret-key" autocomplete="off">
                                    <button class="btn btn-outline-secondary show-password-btn bi bi-eye" type="button"></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="tester-panels">' . $panels . '</div>
            </div>
        </div>
        <script>
        (function(){
            const API_URL    = ' . $base_json . ';
            const APPS       = ' . $apps_json . ';
            const SECRET_KEY = ' . $secret_json . ';

            // Pre-fill secret key
            document.getElementById("tester-secret-key").value = SECRET_KEY;

            // App selector → fill api key
            document.getElementById("tester-app-selector").addEventListener("change", function(){
                document.getElementById("tester-api-key").value = this.value;
            });

            // Sidebar navigation
            document.querySelectorAll(".tester-ep-btn").forEach(function(btn){
                btn.addEventListener("click", function(){
                    document.querySelectorAll(".tester-ep-btn").forEach(b => b.classList.remove("active"));
                    this.classList.add("active");
                    const pid = this.dataset.panel;
                    document.querySelectorAll(".tester-panel").forEach(p => p.classList.toggle("d-none", p.id !== pid));
                });
            });

            // Clear buttons
            document.querySelectorAll(".tester-clear-btn").forEach(function(btn){
                btn.addEventListener("click", function(){
                    const panel = this.closest(".tester-panel");
                    panel.querySelectorAll(".tester-field").forEach(f => { if(f.tagName==="SELECT") f.selectedIndex=0; else f.value=""; });
                    panel.querySelector(".tester-response-card").classList.add("d-none");
                    panel.querySelector(".tester-url-preview").textContent = "";
                });
            });

            // Copy buttons
            document.querySelectorAll(".tester-copy-btn").forEach(function(btn){
                btn.addEventListener("click", function(){
                    const pre = this.closest(".card").querySelector(".tester-response-body");
                    navigator.clipboard.writeText(pre.textContent).then(() => {
                        const orig = this.innerHTML;
                        this.innerHTML = \'<i class="bi bi-check me-1"></i>' . $copied_text . '\';
                        setTimeout(() => this.innerHTML = orig, 1500);
                    });
                });
            });

            // JSON syntax highlight
            function highlight(json){
                return json.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")
                    .replace(/("(\\\\u[a-zA-Z0-9]{4}|\\\\[^u]|[^\\\\"])*"(\\s*:)?|\\b(true|false|null)\\b|-?\\d+(?:\\.\\d*)?(?:[eE][+\\-]?\\d+)?)/g,function(m){
                        let c="color:#ce9178";
                        if(/^"/.test(m)){ if(/:$/.test(m)) c="color:#9cdcfe"; }
                        else if(/true|false/.test(m)) c="color:#569cd6";
                        else if(/null/.test(m)) c="color:#808080";
                        else c="color:#b5cea8";
                        return \'<span style="\'+c+\'">\'+m+\'</span>\';
                    });
            }

            // Run buttons
            document.querySelectorAll(".tester-run-btn").forEach(function(btn){
                btn.addEventListener("click", function(){
                    const action     = this.dataset.action;
                    const panel      = this.closest(".tester-panel");
                    const api_key    = document.getElementById("tester-api-key").value.trim();
                    const secret_key = document.getElementById("tester-secret-key").value.trim();

                    if(!api_key || !secret_key){ alert("' . $creds_required . '"); return; }

                    const params = { action, api_key, secret_key };
                    panel.querySelectorAll(".tester-field").forEach(f => { if(f.value !== "") params[f.name] = f.value; });

                    // URL preview (mask credentials)
                    const preview = new URLSearchParams(Object.fromEntries(
                        Object.entries(params).map(([k,v]) => [k, (k==="api_key"||k==="secret_key") ? "***" : v])
                    ));
                    panel.querySelector(".tester-url-preview").textContent = "POST " + API_URL + " — " + preview.toString();

                    btn.disabled = true;
                    btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-1"></span>' . $sending_text . '...\';
                    const t0 = performance.now();

                    fetch(API_URL, {
                        method: "POST",
                        headers: {"Content-Type":"application/x-www-form-urlencoded"},
                        body: new URLSearchParams(params)
                    })
                    .then(r => r.text().then(text => ({status: r.status, text, ms: Math.round(performance.now()-t0)})))
                    .then(function(resp){
                        const card   = panel.querySelector(".tester-response-card");
                        const pre    = panel.querySelector(".tester-response-body");
                        const badge  = panel.querySelector(".tester-status-badge");
                        const timing = panel.querySelector(".tester-timing");
                        card.classList.remove("d-none");
                        timing.textContent = resp.ms + " ms";
                        let pretty = resp.text;
                        try {
                            const parsed = JSON.parse(resp.text);
                            pretty = JSON.stringify(parsed, null, 2);
                            const ok = parsed.status === "success";
                            badge.textContent = ok ? "200 OK" : ("Error: " + (parsed.message || resp.status));
                            badge.className = "badge tester-status-badge " + (ok ? "bg-success" : "bg-danger");
                        } catch(e) {
                            badge.textContent = resp.status;
                            badge.className = "badge tester-status-badge bg-secondary";
                        }
                        pre.innerHTML = highlight(pretty);
                    })
                    .catch(function(err){
                        const card = panel.querySelector(".tester-response-card");
                        panel.querySelector(".tester-response-body").textContent = "' . $error_text . '\\n" + err;
                        card.classList.remove("d-none");
                    })
                    .finally(function(){
                        btn.disabled = false;
                        btn.innerHTML = \'<i class="bi bi-play-fill me-1"></i>' . $send_text . '\';
                    });
                });
            });
        })();
        </script>';
}

if (!$_POST) {
    // Load current user info (for secret key display)
    $query = "SELECT user_id,user_username,secret_key,secret_key_iv
              FROM user
              WHERE user_username='".escape($_SESSION['sessionusername'])."'";
    $result = mysqli_query(db::$con,$query) or output_error('Query failed.');

    // Do NOT assign this to $user: that is the logged in user array which output_header() reads
    // from the global scope further down, and overwriting it dropped role/id/home from it.
    $current_user_row = mysqli_fetch_assoc($result);

    $user_id = $current_user_row['user_id'];
    $user_username = $current_user_row['user_username'];
    $secret_key = $current_user_row['secret_key'];
    $secret_key_iv = $current_user_row['secret_key_iv'];

    $generate_text = empty($secret_key) ? 'Generate' : 'Regenerate';
    $generate_icon = empty($secret_key) ? 'bi-plus-circle' : 'bi-arrow-clockwise';

    // Load all apps for listing and tester
    $query = "SELECT id as app_id,name,create_user_id,method,api_key,api_key_iv,permissions,timestamp,user.user_username as user
              FROM custom_apps
              LEFT JOIN user ON custom_apps.create_user_id=user.user_id";
    $result = mysqli_query(db::$con,$query) or output_error('Query failed.');
    $apps = mysqli_fetch_items($result);

    // Tester: decoded API keys + secret key for current user
    $my_secret_key = decode_ssl_keys($secret_key, $secret_key_iv);
    $api_base_url  = URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/apps.php';
    $apps_for_tester = array();
    foreach ($apps as $app) {
        $apps_for_tester[] = array(
            'name'   => $app['name'],
            'key'    => decode_ssl_keys($app['api_key'], $app['api_key_iv']),
            'method' => $app['method'],
        );
    }

    // Endpoint definitions for tester
    $endpoints_for_tester = array(
        array('action'=>'product',       'label'=>lang('Product'),         'method'=>'POST/GET',
              'fields'=>array(
                  array('name'=>'name',               'label'=>lang('Product Name'),    'type'=>'text',   'req'=>true,  'hint'=>lang('Exact product name')),
                  array('name'=>'price',              'label'=>lang('Price (cents)'),   'type'=>'number', 'req'=>false, 'hint'=>'e.g. 1999 = 19.99'),
                  array('name'=>'inventory_quantity', 'label'=>lang('Inventory Qty'),   'type'=>'number', 'req'=>false),
                  array('name'=>'increase_quantity',  'label'=>lang('Increase Qty'),    'type'=>'number', 'req'=>false),
                  array('name'=>'decrease_quantity',  'label'=>lang('Decrease Qty'),    'type'=>'number', 'req'=>false),
              )),
        array('action'=>'products',      'label'=>lang('Products (list)'), 'method'=>'POST/GET',
              'fields'=>array(
                  array('name'=>'id',      'label'=>'ID',                  'type'=>'number', 'req'=>false, 'hint'=>lang('Single product by id')),
                  array('name'=>'name',    'label'=>lang('Name filter'),   'type'=>'text',   'req'=>false),
                  array('name'=>'search',  'label'=>lang('Search'),        'type'=>'text',   'req'=>false),
                  array('name'=>'enabled', 'label'=>lang('Enabled'),       'type'=>'select', 'req'=>false,
                        'options'=>array(''=>lang('Any'), '1'=>lang('Enabled'), '0'=>lang('Disabled'))),
                  array('name'=>'limit',   'label'=>lang('Limit'),         'type'=>'number', 'req'=>false, 'hint'=>'1–500'),
              )),
        array('action'=>'orders',        'label'=>lang('Orders'),          'method'=>'POST/GET',
              'fields'=>array(
                  array('name'=>'id',           'label'=>'ID',                  'type'=>'number', 'req'=>false, 'hint'=>lang('Single order + items[]')),
                  array('name'=>'status',       'label'=>lang('Status'),        'type'=>'select', 'req'=>false,
                        'options'=>array(''=>lang('Any'), 'complete'=>'complete', 'incomplete'=>'incomplete', 'exported'=>'exported', 'complete_or_exported'=>'complete_or_exported')),
                  array('name'=>'date_from',    'label'=>lang('Date From'),     'type'=>'date',   'req'=>false),
                  array('name'=>'date_to',      'label'=>lang('Date To'),       'type'=>'date',   'req'=>false),
                  array('name'=>'order_number', 'label'=>lang('Order Number'),  'type'=>'text',   'req'=>false),
                  array('name'=>'email',        'label'=>lang('Billing Email'), 'type'=>'text',   'req'=>false),
                  array('name'=>'limit',        'label'=>lang('Limit'),         'type'=>'number', 'req'=>false, 'hint'=>'1–500'),
              )),
        array('action'=>'pages',         'label'=>lang('Pages'),           'method'=>'POST/GET',
              'fields'=>array(
                  array('name'=>'id',                    'label'=>'ID',                     'type'=>'number', 'req'=>false),
                  array('name'=>'name',                  'label'=>lang('Page Slug'),        'type'=>'text',   'req'=>false),
                  array('name'=>'page_title',            'label'=>lang('Page Title'),       'type'=>'text',   'req'=>false),
                  array('name'=>'page_meta_description', 'label'=>lang('Meta Description'),'type'=>'text',   'req'=>false),
                  array('name'=>'page_search_keywords',  'label'=>lang('Promote on Keyword'),'type'=>'text',  'req'=>false),
                  array('name'=>'page_name',             'label'=>lang('New Slug'),         'type'=>'text',   'req'=>false),
              )),
        array('action'=>'users',         'label'=>lang('Users'),           'method'=>'POST/GET',
              'fields'=>array(
                  array('name'=>'id',         'label'=>'ID',            'type'=>'number', 'req'=>false),
                  array('name'=>'username',   'label'=>lang('Username'),'type'=>'text',   'req'=>false),
                  array('name'=>'user_email', 'label'=>lang('Email'),   'type'=>'email',  'req'=>false),
              )),
        array('action'=>'visitors',      'label'=>lang('Visitors'),        'method'=>'GET',
              'fields'=>array(
                  array('name'=>'id',        'label'=>'ID',              'type'=>'number', 'req'=>false),
                  array('name'=>'date_from', 'label'=>lang('Date From'), 'type'=>'date',   'req'=>false),
                  array('name'=>'date_to',   'label'=>lang('Date To'),   'type'=>'date',   'req'=>false),
                  array('name'=>'page',      'label'=>lang('Page'),      'type'=>'text',   'req'=>false),
                  array('name'=>'limit',     'label'=>lang('Limit'),     'type'=>'number', 'req'=>false),
              )),
        array('action'=>'site_settings', 'label'=>lang('Site Settings'),  'method'=>'POST/GET',
              'fields'=>array(
                  array('name'=>'site_name',        'label'=>lang('Site Name'),        'type'=>'text',   'req'=>false),
                  array('name'=>'site_email',       'label'=>lang('Site Email'),       'type'=>'text',   'req'=>false),
                  array('name'=>'maintenance_mode', 'label'=>lang('Maintenance Mode'), 'type'=>'select', 'req'=>false,
                        'options'=>array(''=>lang('No change'), '1'=>lang('On'), '0'=>lang('Off'))),
              )),
    );

    // Build table rows
    $output_rows='';
    foreach($apps as $app){
        $permitions = '';
        $decoded_permissions = json_decode($app['permissions'], true);

        if (is_array($decoded_permissions)) {
            $grouped = [];
        
            // Group by action => [read/edit]
            foreach ($decoded_permissions as $perm) {
                $action = $perm['action'];
                $type   = strtolower($perm['type']);
                if (!isset($grouped[$action])) {
                    $grouped[$action] = [];
                }
                $grouped[$action][] = $type;
            }
        
            foreach ($grouped as $action => $types) {
                switch ($action) {
                    case 'product':
                        $label = lang('Product');
                        break;
                    case 'products':
                        $label = lang('Products (list)');
                        break;
                    case 'orders':
                        $label = lang('Orders');
                        break;
                    case 'site_settings':
                        $label = lang('Site Settings');
                        break;
                    case 'visitors':
                        $label = lang('Visitors');
                        break;
                    default:
                        $label = ucfirst(str_replace('_', ' ', $action));
                }
            
                $types = array_unique($types);
                $type_label = '';
                $output_type_class = '';
                if (in_array('read', $types) && in_array('edit', $types)) {
                    $type_label = lang('READ/EDIT');
                    $output_type_class="bg-success"; 
                } elseif (in_array('edit', $types)) {
                    $type_label = lang('EDIT');
                    $output_type_class="bg-primary"; 
                } elseif (in_array('read', $types)) {
                    $type_label = lang('READ');
                    $output_type_class="bg-primary"; 
                }
            
                $permitions .= '<div class="d-flex justify-content-between">' . h($label) . ' <span style="font-size:10px" class="badge ' . $output_type_class . '">' . h($type_label) . '</span></div><br/>';
            }
        }


        $output_app_method = ($app['method']==='GET')
            ? lang(array('string'=>'{var:1} or {var:2}','vars'=>array('<span class="badge bg-primary fw-light">Get</span>','<span class="badge bg-success fw-light">Post</span>')))
            : '<span class="badge bg-success fw-light">Post</span>';
        $decoded_api_key = decode_ssl_keys($app['api_key'],$app['api_key_iv']);
        $output_rows.='
        <tr id="'.h($app['app_id']).'">
          
            <td class="align-middle text-start action-buttons">
                <button type="submit" name="submit_delete_app" value="Delete" onclick="$(\'#application_to_delete\').val($(this).closest(\'tr\').attr(\'id\'));" class="m-1 btn-data-control btn btn-outline-danger border-2 "><i class="bi bi-trash" style=""></i></button>
                </td>
            <td>'.h($app['name']).'</td>
            <td>'.$output_app_method.'</td>
            <td>'.$permitions.'</td>
            <td>
                <div class="input-group input-group-sm">
                    <input readonly class="form-control" type="password" value="'.h($decoded_api_key).'"/>
                    <button type="button" class="btn btn-secondary show-password-btn bi bi-eye"></button>
                </div>
            </td>
            <td>'.get_relative_time(array('timestamp'=>$app['timestamp'])).' '.lang(array('string'=>'by {var:1}','vars'=>array(h($app['user'])))).'</td>
        </tr>';
    }

    // Output page
    $output = pg_page_shell([
        'title'=> lang('Custom Applications'),
        'extra classes'=>'setting',
        'icon'=>'setting',
        'heading'=>lang('Custom Applications'),
        'cancel'=>['enable'=>true,'title'=>lang('Return to Settings'),'onclick'=>"window.location.href='settings.php'"]
    ]).'
            '.$liveform->output_errors().$liveform->get_warnings().$liveform->output_notices().'
        <div class="row mb-2  flex-wrap">
            <div class="col-12 col-sm-12 text-center text-md-start">
                <h2 class="d-inline-block " data-bs-content="' . lang('Allows to view and update site data from outside the site') . '" title="' . lang('Custom Applications') . '">' . lang('Custom Applications') . '</h2>
                <div class="row">
                    <div class="col-12 col-md-auto">
                        <form name="form" action="apps_settings.php" method="post" class="disable_shortcut" style="margin: 0px" autocomplete="off">
                            ' . get_token_field() . '
                            <input type="hidden" name="user_id" value="' . $user_id . '" />
                            <label class="form-label" for="user_secret_key">' . lang('USER SECRET KEY') . '( <span>' . $user_username . '</span> )</label>
                            <div class="input-group input-group-sm">
                                <button type="submit" class="btn btn-primary" id="submit_generate_secret" name="submit_generate_secret"  value="' . $generate_text . '" ><i class="bi ' . $generate_icon . '"></i></button>
                                <input readonly class="form-control" type="password" id="user_secret_key" value="' . decode_ssl_keys($secret_key,$secret_key_iv) . '"/>
                                <button type="button" class="btn btn-secondary show-password-btn bi bi-eye"></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab navigation -->
        <ul class="nav nav-tabs mb-3" id="appsTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-apps-btn" data-bs-toggle="tab" data-bs-target="#tab-apps" type="button" role="tab">
                    <i class="bi bi-grid me-1"></i>' . lang('Applications') . '
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-tester-btn" data-bs-toggle="tab" data-bs-target="#tab-tester" type="button" role="tab">
                    <i class="bi bi-play-circle me-1"></i>' . lang('API Tester') . '
                </button>
            </li>
        </ul>

        <div class="tab-content" id="appsTabContent">

        <!-- TAB: Applications -->
        <div class="tab-pane fade show active" id="tab-apps" role="tabpanel">

        <!-- Apps table -->
        <form action="apps_settings.php" method="post" autocomplete="off" class="disable_shortcut">
            '.get_token_field().'
            <input type="hidden" name="application_to_delete" id="application_to_delete" value=""/>

            
            <div class="card my-4">
                <div class="card-header bg-reset border-0 chart-buttons justify-content-end d-flex flex-wrap"></div>
                <div class="card-body p-0 position-relative">
                    <table class="chart table  table-striped" style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th> 
                                <th>' . lang('App Name') . '</th>
                                <th>' . lang('Method') . '</th>
                                <th>' . lang('Permitions') . '</th>
                                <th>' . lang('API KEY') . '</th>
                                <th>' . lang('Create Date') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            
                <div class="card-footer bg-reset border-0 pb-4 flex-column d-flex">
                    <span>' . lang(array('string'=>'{var:1} to check out how to use it.','vars'=>' <a href="usage" class="link-primary" data-bs-toggle="modal" data-bs-target="#usage">' . lang('Click here') . '</a> ' )) . '</span>
                    <span>' . lang(array('string'=>'{var:1} to create new api.','vars'=>' <a href="create_new_app" class="link-primary" data-bs-toggle="modal" data-bs-target="#create_new_app">' . lang('Click here') . '</a> ' )) . '</span>
                </div>
            </div>
            
        </form>

        <div class="modal fade" id="usage" tabindex="-1" aria-labelledby="usage" aria-hidden="true">
          <div class="modal-dialog modal-xl">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">' . lang('Custom Application Usage') . '</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . lang('Close') . '"></button>
              </div>
              <div class="modal-body">
                <div class="row mb-4">
                  <div class="col-12">
                    <h5>' . lang('Available Actions') . '</h5>
                    <div class="table-responsive">
                      <table class="table table-bordered table-sm">
                        <thead class="table-light">
                          <tr>
                            <th>' . lang('Action') . '</th>
                            <th>' . lang('Description') . '</th>
                            <th>' . lang('Required Parameters') . '</th>
                            <th>' . lang('Optional Parameters') . '</th>
                            <th>' . lang('Permissions') . '</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td><code>product</code></td>
                            <td>' . lang('Read or update a product') . '</td>
                            <td><code>name</code></td>
                            <td><code>price</code>, <code>inventory_quantity</code>, <code>increase_quantity</code>, <code>decrease_quantity</code>, ' . lang('and more') . '</td>
                            <td><span class="badge bg-primary">products</span></td>
                          </tr>
                          <tr>
                            <td><code>pages</code></td>
                            <td>' . lang('List all pages or read/update a specific page') . '</td>
                            <td>' . lang('None (lists all) or') . ' <code>id</code> / <code>name</code></td>
                            <td><code>page_title</code>, <code>page_meta_description</code>, <code>page_search_keywords</code>, <code>page_name</code></td>
                            <td><span class="badge bg-primary">pages</span></td>
                          </tr>
                          <tr>
                            <td><code>users</code></td>
                            <td>' . lang('List all users or get a specific user (Manager+ only)') . '</td>
                            <td>' . lang('None (lists all) or') . ' <code>id</code> / <code>username</code></td>
                            <td><code>user_email</code></td>
                            <td><span class="badge bg-primary">users</span></td>
                          </tr>
                          <tr>
                            <td><code>visitors</code></td>
                            <td>' . lang('Get visitor stats summary or a single visitor by id') . '</td>
                            <td>' . lang('None (summary) or') . ' <code>id</code></td>
                            <td><code>date_from</code>, <code>date_to</code>, <code>page</code>, <code>limit</code></td>
                            <td><span class="badge bg-primary">visitors</span></td>
                          </tr>
                          <tr>
                            <td><code>products</code></td>
                            <td>
                              ' . lang('List products or get a single product by id') . '<br>
                              <small class="text-muted">
                                ' . lang('Fields') . ': <code>id</code>, <code>name</code>, <code>enabled</code>, <code>price</code>, <code>inventory_quantity</code>, <code>short_description</code>, <code>image_name</code>, <code>out_of_stock</code>, <code>taxable</code>, <code>timestamp</code>, <code>user</code>
                              </small>
                            </td>
                            <td>' . lang('None (lists all) or') . ' <code>id</code></td>
                            <td><code>name</code>, <code>search</code>, <code>enabled</code>, <code>limit</code></td>
                            <td><span class="badge bg-primary">products</span></td>
                          </tr>
                          <tr>
                            <td><code>orders</code></td>
                            <td>
                              ' . lang('List orders or get a single order with items by id') . '<br>
                              <small class="text-muted">
                                ' . lang('List fields') . ': <code>id</code>, <code>order_number</code>, <code>order_date</code>, <code>status</code>, <code>billing_first_name</code>, <code>billing_last_name</code>, <code>billing_email_address</code>, <code>subtotal</code>, <code>discount</code>, <code>tax</code>, <code>shipping</code>, <code>surcharge</code>, <code>total</code>, <code>payment_method</code>, <code>transaction_id</code><br>
                                ' . lang('Single (id) adds') . ': <code>billing_address_1/2</code>, <code>billing_city/state/zip/country/phone</code>, <code>notes</code>, <code>items[]</code>
                              </small>
                            </td>
                            <td>' . lang('None (all) or') . ' <code>id</code></td>
                            <td><code>status</code>, <code>date_from</code>, <code>date_to</code>, <code>order_number</code>, <code>email</code>, <code>limit</code></td>
                            <td><span class="badge bg-primary">orders</span></td>
                          </tr>
                          <tr>
                            <td><code>site_settings</code></td>
                            <td>' . lang('Read or update site settings (Admin only)') . '</td>
                            <td>—</td>
                            <td>' . lang('Any editable settings field') . '</td>
                            <td><span class="badge bg-primary">site_settings</span></td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <div class="alert alert-info mt-2 mb-0 small">
                      <i class="bi bi-info-circle me-1"></i>
                      ' . lang('All requests require') . ' <code>api_key</code> ' . lang('and') . ' <code>secret_key</code>.
                      ' . lang('POST method is recommended. If the app is set to POST-only, GET requests will be rejected.') . '
                    </div>
                  </div>
                </div>

                <div class="row g-4">
                  <div class="col-12">
                    <h5>' . lang('GET Method') . '</h5>
                    <textarea readonly class="form-control apps_code_field" style="height:150px;" id="example_get_url">
        ' . URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/apps.php?api_key=API_KEY_HERE&secret_key=SECRET_KEY_HERE&action=product&name=PRODUCT_NAME
                    </textarea>
                    ' . get_codemirror_javascript(['id' => 'example_get_url', 'code_type' => 'plain']) . '
                  </div>

                  <div class="col-12">
                    <h5>' . lang('HTML Form POST') . '</h5>
                    <textarea readonly class="form-control apps_code_field" style="height:300px;" id="example_html_post">
        <form action="' . URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/apps.php" method="post">
          <input name="api_key" value="API_KEY_HERE" required />
          <input name="secret_key" value="SECRET_KEY_HERE" required />
          <input name="action" value="product" required />
          <input name="name" value="PRODUCT_NAME" required />
          <input name="increase_quantity" value="5" />
          <input type="submit" value="Send" />
        </form>
                    </textarea>
                    ' . get_codemirror_javascript(['id' => 'example_html_post', 'code_type' => 'html']) . '
                  </div>

                  <div class="col-12">
                    <h5>' . lang('PHP cURL') . '</h5>
                    <textarea readonly class="form-control apps_code_field" style="height:300px;" id="example_php_curl">
        <?php
        $post = [
          "api_key" => "API_KEY_HERE",
          "secret_key" => "SECRET_KEY_HERE",
          "action" => "product",
          "name" => "PRODUCT_NAME",
          "increase_quantity" => "5"
        ];
        $ch = curl_init("' . URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/apps.php");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);
        echo $response["status"];
        ?>
                    </textarea>
                    ' . get_codemirror_javascript(['id' => 'example_php_curl', 'code_type' => 'php']) . '
                  </div>

                  <div class="col-12">
                    <h5>' . lang('JavaScript AJAX') . '</h5>
                    <textarea readonly class="form-control apps_code_field" style="height:300px;" id="example_js_ajax">
        fetch("' . URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/apps.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: new URLSearchParams({
            api_key: "API_KEY_HERE",
            secret_key: "SECRET_KEY_HERE",
            action: "product",
            name: "PRODUCT_NAME",
            increase_quantity: "5"
          })
        })
        .then(res => res.json())
        .then(data => console.log(data));
                    </textarea>
                    ' . get_codemirror_javascript(['id' => 'example_js_ajax', 'code_type' => 'javascript']) . '
                  </div>

                  <div class="col-12">
                    <h5>' . lang('JSON Payload') . '</h5>
                    <textarea readonly class="form-control apps_code_field" style="height:300px;" id="example_json_payload">
        {
          "api_key": "API_KEY_HERE",
          "secret_key": "SECRET_KEY_HERE",
          "action": "product",
          "name": "PRODUCT_NAME",
          "increase_quantity": 5
        }
                    </textarea>
                    ' . get_codemirror_javascript(['id' => 'example_json_payload', 'code_type' => 'json']) . '
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Create new app form -->
        <div class="modal fade" id="create_new_app" tabindex="-1" aria-labelledby="create_new_app" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">' . lang('Create New API') . '</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . lang('Close') . '"></button>
                    </div>
                    <div class="modal-body">
                    
                        <ul class="application_informations mb-3">
                            <li>' . lang('You can have unlimited applications.') . '</li>
                            <li class="text-danger">' . lang('Dont share API keys with people you dont know, if youre sharing for a short time, delete the application you shared when youre done!') . '</li>
                            <li class="text-danger">' . lang('If the server does not have ssl, think twice before using it.') . '</li>
                            <li class="text-success">' . lang('Post method is always safer') . '</li>
                        </ul>
                        <form action="apps_settings.php" method="post" autocomplete="off">
                            '.get_token_field().'
                            <div class="row">
                                <!-- App name -->
                                <div class="col-12 col-lg-4 mb-3">
                                    <label for="app_name" title="'.lang('Name for your custom application').'">
                                        '.lang('App Name').'
                                    </label>
                                    '.$liveform->output_field(array(
                                        'type'=>'text',
                                        'name'=>'app_name',
                                        'id'=>'app_name',
                                        'class'=>'form-control',
                                        'maxlength'=>'256'
                                    )).'
                                    <div class="form-text">'.lang('Choose a descriptive name for your app.').'</div>
                                </div>
                                    
                                <!-- Access method -->
                                <div class="col-12 col-lg-4 mb-3">
                                    <label for="app_access_method" title="'.lang('How the API can be accessed').'">
                                        '.lang('Access Method').'
                                    </label>
                                    '.$liveform->output_field(array(
                                        'type'=>'select',
                                        'name'=>'app_access_method',
                                        'id'=>'app_access_method',
                                        'class'=>'form-select',
                                        'options'=>$app_method_options
                                    )).'
                                    <div class="form-text">'.lang('POST is recommended for security.').'</div>
                                </div>
                                    
                                <!-- Permissions -->
                                <div class="col-12 mt-3">
                                    <label title="'.lang('Define what this app is allowed to do').'">
                                        '.lang('Permissions').'
                                    </label>
                                    <div id="permissions">
                                        <div class="permission-row d-flex my-1">
                                            <select name="permission_action[]" class="form-select me-2 permission-action w-auto">
                                                <option value="" disabled selected>'.lang('- Select Permission Action -').'</option>
                                                <optgroup label="'.lang('Content').'">
                                                    <option value="pages">'.lang('Pages').'</option>
                                                    <option value="product">'.lang('Product (single)').'</option>
                                                    <option value="products">'.lang('Products (list)').'</option>
                                                    <option value="orders">'.lang('Orders').'</option>
                                                </optgroup>
                                                <optgroup label="'.lang('Data').'">
                                                    <option value="visitors">'.lang('Visitors').'</option>
                                                    <option value="users">'.lang('Users').'</option>
                                                </optgroup>
                                                <optgroup label="'.lang('System').'">
                                                    <option value="site_settings">'.lang('Site Settings').'</option>
                                                </optgroup>
                                            </select>
                                            <select name="permission_type[]" class="form-select me-2 w-auto permission-type">
                                                <option value="read">'.lang('Read').'</option>
                                                <option value="edit">'.lang('Read') . ' + ' . lang('Edit').'</option>
                                            </select>
                                            <button type="button" class="btn btn-outline-danger remove-permission bi bi-x-lg" style="display:none;" title="'.lang('Remove this permission').'"></button>
                                        </div>
                                    </div>
                                    <button type="button" id="add-permission" class="btn btn-outline-primary mt-2 bi bi-plus-lg" title="'.lang('Add another permission').'"></button>
                                    
                                </div>
                            </div>
                                    
                            <!-- Submit button -->
                            <div class="text-center mt-4">
                                <button type="submit" name="create_new_app" value="Create" class="btn btn-success">
                                    <i class="bi bi-plus-lg me-1"></i> '.lang('Create').'
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        </div><!-- /TAB: Applications -->

        <!-- TAB: API Tester -->
        <div class="tab-pane fade" id="tab-tester" role="tabpanel">
' . apps_tester_html($api_base_url, $apps_for_tester, $my_secret_key, $endpoints_for_tester) . '
        </div><!-- /TAB: API Tester -->

        </div><!-- /tab-content -->

    </main>

    <!-- JavaScript -->
    <script>
    (function(){
        function updatePermissionOptions(){
            var selected=[];
            document.querySelectorAll("#permissions .permission-action").forEach(function(sel){
                if(sel.value){selected.push(sel.value);}
            });
            document.querySelectorAll("#permissions .permission-action").forEach(function(sel){
                for(var i=0;i<sel.options.length;i++){
                    if(selected.indexOf(sel.options[i].value)!==-1 && sel.value!==sel.options[i].value){
                        sel.options[i].disabled=true;
                    } else {
                        sel.options[i].disabled=false;
                    }
                }
            });
        }
        document.getElementById("add-permission").addEventListener("click", function(){
            var container=document.getElementById("permissions");
            var first=container.firstElementChild;
            if(!first) return;
            var row=first.cloneNode(true);
            row.querySelectorAll("select").forEach(function(sel){sel.selectedIndex=0;});
            var removeBtn=row.querySelector(".remove-permission");
            if(removeBtn) removeBtn.style.display="inline-block";
            container.appendChild(row);
            updatePermissionOptions();
        });
        document.addEventListener("click", function(e){
            if(e.target.classList.contains("remove-permission")){
                e.target.parentElement.remove();
                updatePermissionOptions();
            }
        });
        document.addEventListener("change", function(e){
            if(e.target.classList.contains("permission-action")){
                updatePermissionOptions();
            }
        });
        updatePermissionOptions();
    })();
    </script>
    '.output_footer();

    print $output;
    $liveform->remove_form('apps_settings');

} else {
    // Handle form submission
    $liveform->add_fields_to_session();
    validate_token_field();

    // Generate random API key (32 chars, uppercase letters + numbers)
    $random_key = get_random_string(array(
        'type' => 'uppercase_letters_and_numbers',
        'length' => 32
    ));

    // Encrypt API key with IV + compute deterministic hash for fast DB lookup
    list($encrypted_random_key, $iv_base64) = encrypt_string_with_iv($random_key);
    $api_key_hash = hash_hmac('sha256', $random_key, ENCRYPTION_KEY);

    // Secret key generation/regeneration
    if (
        (isset($_POST['submit_generate_secret']) && ($_POST['submit_generate_secret'] ?? '') === 'Generate') ||
        (isset($_POST['submit_generate_secret']) && ($_POST['submit_generate_secret'] ?? '') === 'Regenerate')
    ) {
        // Generate a fresh secret key (different from the API key)
        $secret_random_key = get_random_string(array(
            'type' => 'uppercase_letters_and_numbers',
            'length' => 32
        ));

        // Compute deterministic HMAC hash for fast DB lookup (used in apps.php WHERE clause)
        $secret_key_hash = hash_hmac('sha256', $secret_random_key, ENCRYPTION_KEY);

        // Ensure uniqueness via hash (hash is deterministic — this check actually works)
        $query = "SELECT user_id FROM user WHERE secret_key_hash = '" . escape($secret_key_hash) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);

        if (empty($row)) {
            // Encrypt plaintext for secure storage (IV-based — different ciphertext each time)
            list($encrypted_secret, $secret_iv_base64) = encrypt_string_with_iv($secret_random_key);

            // Update user with new secret key + hash
            $query = "UPDATE user
                      SET secret_key      = '" . escape($encrypted_secret) . "',
                          secret_key_iv   = '" . escape($secret_iv_base64) . "',
                          secret_key_hash = '" . escape($secret_key_hash) . "'
                      WHERE user_id = '" . escape($_POST['user_id'] ?? '') . "'";
            mysqli_query(db::$con, $query) or output_error('Query failed.');
            $liveform->add_notice(lang(array('string'=>'Success to generate secret key for {var:1}.','vars'=>$_SESSION['sessionusername'])));
            log_activity(lang(array('string'=>'Success to generate secret key for {var:1}.','vars'=>$_SESSION['sessionusername'])), $_SESSION['sessionusername']);
        } else {
            $liveform->add_notice(lang('Try again.'));
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/apps_settings.php');
            exit();
        }

    } else {
        // Delete app
        if (isset($_POST['submit_delete_app']) && ($_POST['submit_delete_app'] ?? '') === 'Delete') {
            $selected_app_id = $_POST['application_to_delete'];
            $query = "DELETE FROM custom_apps WHERE id = '" . escape($selected_app_id) . "'";
            mysqli_query(db::$con, $query) or output_error('Query failed.');
            $liveform->add_notice(lang(array('string'=>'Success to Delete Application, id: {var:1}.','vars'=>$selected_app_id)));

        } else {
            // Validate required fields
            $liveform->validate_required_field('app_name', lang('Application Name is a required field!'));
            $liveform->validate_required_field('app_access_method', lang('Application Access Method is a required field!'));

            // Build permissions JSON
            $permissions_json = '[]';
            $permission_actions = isset($_POST['permission_action']) ? $_POST['permission_action'] : array();
            $permission_types = isset($_POST['permission_type']) ? $_POST['permission_type'] : array();

            $permissions = array();
            $count = min(count($permission_actions), count($permission_types));
            for ($i=0; $i<$count; $i++) {
                $action = trim($permission_actions[$i]);
                $type = trim($permission_types[$i]);
                if ($action === '' || $type === '') continue;

                // Avoid duplicates
                $dup = false;
                foreach ($permissions as $p) {
                    if ($p['action'] === $action && $p['type'] === $type) {
                        $dup = true; break;
                    }
                }
                if (!$dup) {
                    // Special rule: if type=edit, it implies read as well
                    if ($type === 'edit') {
                        $permissions[] = array('action'=>$action,'type'=>'read');
                    }
                    $permissions[] = array('action'=>$action,'type'=>$type);
                }
            }
            if (!empty($permissions)) {
                $permissions_json = json_encode($permissions);
            }

            // Insert new app if no errors
            if ($liveform->check_form_errors() == false) {
                $query = "INSERT INTO custom_apps (
                            create_user_id,
                            name,
                            method,
                            api_key,
                            api_key_iv,
                            api_key_hash,
                            permissions,
                            timestamp
                          )
                          VALUES (
                            '" . escape($user['id']) . "',
                            '" . escape($liveform->get_field_value('app_name')) . "',
                            '" . escape($liveform->get_field_value('app_access_method')) . "',
                            '" . escape($encrypted_random_key) . "',
                            '" . escape($iv_base64) . "',
                            '" . escape($api_key_hash) . "',
                            '" . escape($permissions_json) . "',
                            UNIX_TIMESTAMP()
                          )";
                mysqli_query(db::$con, $query) or output_error('Query failed.');
                $liveform->add_notice(lang('Success to Create new Application'));
            }
        }
    }

    // Redirect back to settings page
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/apps_settings.php');
}
?>