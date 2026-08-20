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
 */

include('init.php');
header('Content-Type: application/json; charset=utf-8');
$array = [
    "manifest_version"=> 3,
    "name" => TITLE,
    "short_name" => TITLE,
    "description"=> META_DESCRIPTION,
    "start_url" => URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . "/welcome.php",
    "scope"=> URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY,
    "id"=> URL_SCHEME . HOSTNAME_SETTING,
    "display" => "minimal-ui",
    "background_color" => "#ffffff",
    "theme_color" => "#1a73e8",
    "orientation" => "portrait-primary",
    "categories" => ["productivity"],

    "icons" => [
        [
            "src" => "assets/images/icon-192.png",
            "sizes" => "192x192",
            "type" => "image/png"
        ],
        [
            "src" => "assets/images/icon-512.png",
            "sizes" => "512x512",
            "type" => "image/png"
        ]
    ],
    "screenshots" => [
        [
            "src" => "assets/images/screenshot.png",
            "sizes" => "540x720",
            "type" => "image/png"
        ],
    ]
];
echo json_encode($array, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
exit();
?>