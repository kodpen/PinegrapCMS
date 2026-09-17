<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// No session, on purpose.
//
// The browser fetches a manifest and its icons without credentials, so these
// requests arrive with no session cookie. Starting a session for them would
// write a session file for every icon a launcher asks for, and hand back a
// Set-Cookie for a session nobody asked for - which is the kind of thing that
// ends up replacing the cookie of the operator who is signed in in the same
// browser. Nothing here needs to know who is asking: the answer is the same
// for every visitor. $_SESSION is still an array so the shared code that looks
// into it finds nothing rather than warning.
define('PG_NO_SESSION', true);

$_SESSION = array();

include('init.php');
header('Content-Type: application/json; charset=utf-8');

$software_url = URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY;

// The installed application carries the product name in front of the site's, so
// that an operator holding several Pinegrap sites can tell the icons apart and
// nobody mistakes the panel for the public site. The launcher shortens what
// does not fit rather than showing the wrong half.
define('PG_APP_NAME', 'PineGrap ' . TITLE);

// The panel installs as its own application.
//
// "standalone" is not decoration: Safari only treats a site added to the home
// screen as a web application when the manifest asks for standalone or
// fullscreen, and only an installed web application on iOS is allowed to hold a
// push subscription. Under the previous "minimal-ui" the panel opened as a
// browser tab on iOS and notifications were unavailable there whatever the
// server sent. display_override keeps the old value as the fallback for engines
// that do support it.
//
// The identifier is scoped to the software directory rather than to the host,
// because the site in front of the panel may ship a manifest of its own and two
// applications on one origin cannot share an id without replacing each other.
// What the launcher is given.
//
// An operator who chose an icon in Settings gets it rendered to the exact sizes
// by manifest_icon.php, and gets a maskable entry as well: that endpoint draws
// the margin Android needs, which is the thing a hand-made file usually lacks.
// With nothing chosen the shipped squares are used, and the maskable entry is
// only claimed when a purpose-built file has been put beside them - declaring
// an ordinary icon maskable would have its edges shaved off.
function pg_app_manifest_icons()
{
    $chosen = (defined('APP_ICON')) ? trim((string) APP_ICON) : '';

    if ($chosen != '') {

        return [
            [
                "src" => "manifest_icon.php?size=192",
                "sizes" => "192x192",
                "type" => "image/png",
                "purpose" => "any"
            ],
            [
                "src" => "manifest_icon.php?size=512",
                "sizes" => "512x512",
                "type" => "image/png",
                "purpose" => "any"
            ],
            [
                "src" => "manifest_icon.php?size=512&purpose=maskable",
                "sizes" => "512x512",
                "type" => "image/png",
                "purpose" => "maskable"
            ]
        ];
    }

    $icons = [
        [
            "src" => "assets/images/icon-192.png",
            "sizes" => "192x192",
            "type" => "image/png",
            "purpose" => "any"
        ],
        [
            "src" => "assets/images/icon-512.png",
            "sizes" => "512x512",
            "type" => "image/png",
            "purpose" => "any"
        ]
    ];

    if (file_exists(dirname(__FILE__) . '/assets/images/icon-maskable-512.png')) {
        $icons[] = [
            "src" => "assets/images/icon-maskable-512.png",
            "sizes" => "512x512",
            "type" => "image/png",
            "purpose" => "maskable"
        ];
    }

    return $icons;
}

$array = [
    "manifest_version"=> 3,
    "name" => PG_APP_NAME,
    "short_name" => PG_APP_NAME,
    "description"=> META_DESCRIPTION,
    "lang" => lang(['info' => true]),
    "start_url" => $software_url . "/welcome.php",
    "scope"=> $software_url . "/",
    "id"=> $software_url . "/",
    "display" => "standalone",
    "display_override" => ["standalone", "minimal-ui"],
    "background_color" => "#ffffff",
    "theme_color" => "#1a73e8",
    "orientation" => "portrait-primary",
    "categories" => ["productivity"],
    "prefer_related_applications" => false,

    // A notification click should raise the window that is already open rather
    // than start a second copy of the panel.
    "launch_handler" => [
        "client_mode" => ["navigate-existing", "auto"]
    ],

    "icons" => pg_app_manifest_icons(),
    "screenshots" => [
        [
            "src" => "assets/images/screenshot.png",
            "sizes" => "540x720",
            "type" => "image/png",
            "form_factor" => "narrow"
        ],
    ]
];

// Shortcuts are the long-press menu on the installed icon. They are gated on
// what the site has, not on what the person may do: a manifest is fetched
// without the session, so it cannot be told apart by account.
$shortcuts = [];

if (ECOMMERCE === true) {
    $shortcuts[] = [
        "name" => lang('Orders'),
        "short_name" => lang('Orders'),
        "url" => $software_url . "/view_orders.php"
    ];
    $shortcuts[] = [
        "name" => lang('Products'),
        "short_name" => lang('Products'),
        "url" => $software_url . "/view_products.php"
    ];
}

if (FORMS === true) {
    $shortcuts[] = [
        "name" => lang('Submitted Forms'),
        "short_name" => lang('Forms'),
        "url" => $software_url . "/view_submitted_forms.php"
    ];
}

if ($shortcuts) {
    $array['shortcuts'] = $shortcuts;
}

echo json_encode($array, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit();
