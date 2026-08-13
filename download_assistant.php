<?php
/**
 *
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Kodpen
 * @link        https://kodpen.com
 * @copyright   2016-2025 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 *
 */

session_start();

// -----------------------------
// Variables
// -----------------------------
$is_installed   = false;
$is_existing    = false;
$output_install_button = '';
$output_update_button  = '';
$output_repair_button  = '';
$output_update_disable_class = '';
$output_repair_disable_class = '';
$output_install_disable_class = '';
$version = '';
$message = '';

/**
 * User agent for the requests this assistant makes to the software server.
 *
 * This file has to work on a server where Pinegrap is not installed yet, so it
 * cannot rely on pinegrap_user_agent() from waf.php — it uses that when the
 * installation is already there, and builds the same shape by hand when it is
 * not.
 *
 * Sending nothing is what breaks: a request with no User-Agent looks like an
 * anonymous client to the software server's own firewall and is rejected, so
 * install, repair and update all fail with an error that points nowhere near
 * the real cause.
 */
function da_user_agent()
{
    if (function_exists('pinegrap_user_agent')) {
        return pinegrap_user_agent();
    }

    $version = defined('VERSION') ? VERSION : 'assistant';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

    return 'Pinegrap/' . $version . ($host !== '' ? ' (+' . $host . ')' : '');
}

/**
 * Fetch a file over HTTP into $destination.
 *
 * Replaces file_put_contents($dest, fopen($url, 'r')), which had two faults:
 * the stream wrapper sends no User-Agent, and a failed fopen() returns false,
 * which file_put_contents() happily writes as an empty file. The caller then
 * found a zero-byte archive and reported "something went wrong while
 * extracting zip file" — an error message pointing at the wrong subsystem
 * entirely, for what was really a rejected download.
 *
 * Returns true on success.
 */
function da_download($url, $destination)
{
    $bytes = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, da_user_agent());
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        if (defined('PROXY_ADDRESS') && PROXY_ADDRESS != '') {
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
        }

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body !== false && $status === 200) {
            $bytes = $body;
        }
    }

    // Stream wrapper fallback, with the User-Agent set explicitly — PHP sends
    // none by default here.
    if ($bytes === false) {
        $context = stream_context_create(array(
            'http' => array(
                'method'  => 'GET',
                'header'  => 'User-Agent: ' . da_user_agent() . "\r\n",
                'timeout' => 300,
            ),
            'ssl' => array('verify_peer' => false, 'verify_peer_name' => false),
        ));

        $bytes = @file_get_contents($url, false, $context);
    }

    if ($bytes === false || $bytes === '') {
        return false;
    }

    // What came back has to actually be an archive. A firewall's 403 page or a
    // proxy error is a perfectly valid HTTP body, and writing one under a .zip
    // name turns a rejected download into a baffling extraction error.
    if (substr($bytes, 0, 2) !== 'PK') {
        return false;
    }

    $written = @file_put_contents($destination, $bytes);

    // A short write means a full disk; the truncated archive would then
    // extract partially.
    return ($written !== false && $written === strlen($bytes));
}

// -----------------------------
// Check if Pinegrap exists
// -----------------------------
if (is_dir('pinegrap')) {
    $is_existing = true;

    if (is_file('pinegrap/data/config.php')) {
        include('pinegrap/data/config.php');
    }
    
    if (is_file('pinegrap/init.php') && defined('DB_DATABASE')) {
        
        include('pinegrap/init.php');
        $user = validate_user();
        validate_area_access($user, 'administrator');
        $version = 'Version: ' . VERSION;
        $is_installed = true;

        $output_install_disable_class = ' disabled';
        $output_install_button = '<button class="col-12 w-100 btn btn-primary my-2 disabled rounded-pill">Install</button>';

    } else {
        $output_install_button = '<a href="pinegrap/install/index.php" class="col-12 w-100 btn btn-primary my-2 rounded-pill">Complete Install</a>';
        $output_update_disable_class = ' disabled';
        $output_repair_disable_class = ' disabled';
    }

} else {
    $output_install_button = '<button type="submit" name="action" value="install" class="col-12 w-100 btn btn-primary my-2 rounded-pill" onclick="return confirm(\'Are you sure to download and install the Pinegrap Software?\')">Install</button>';
    $output_update_disable_class = ' disabled';
    $output_repair_disable_class = ' disabled';

    unset($_SESSION['software']['download_assistant']['update']['avaliable']);
    unset($_SESSION['software']['download_assistant']['update']['version']);
}

// -----------------------------
// HTML header & footer
// -----------------------------
$output_header = '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
  <title>Pinegrap Download Assistant</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">';

$output_footer = '
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';

// -----------------------------
// Main Logic
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    if (isset($_SESSION['software']['download_assistant']['update']['avaliable'])
        && $_SESSION['software']['download_assistant']['update']['avaliable'] === 'yes') {
        $output_update_btn_value = 'update';
        $output_update_btn_label = 'Update Now (' . $version . ' → ' . $_SESSION['software']['download_assistant']['update']['version'] . ')';
    } else {
        $output_update_btn_value = 'check';
        $output_update_btn_label = 'Check Updates';
    }

    $output_update_button = '<button type="submit" name="action" value="' . $output_update_btn_value . '" class="col-12 w-100 btn btn-primary my-2  rounded-pill ' . $output_update_disable_class . '">' . $output_update_btn_label . '</button>';

    $output_repair_button = '<button type="submit" name="action" value="repair" class="col-12 w-100 btn btn-primary my-2  rounded-pill ' . $output_repair_disable_class . '" onclick="return confirm(\'Do you accept the repair process? The software will be updated with the latest released software file and any custom changes will be deleted.\')">Repair</button>';

    if ($is_existing) {
        $message = 'The Pinegrap Software already exists';
        $message .= $is_installed ? ' and installed.' : ' but not installed.';
    }

    echo output_download_assistant_html_content(
        $output_header,
        $output_footer,
        $output_install_button,
        $output_repair_button,
        $output_update_button,
        $message,
        $version
    );

} else {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['install', 'repair', 'update'])) {

        $software_file = ($action === 'install') ? 'pinegrap_software.zip' : 'pinegrap_software_update.zip';

        if (is_file($software_file)) {
            unlink($software_file);
        }

        // A rejected or failed download used to fall through to the ZIP
        // handling below and surface as an extraction error, which sends
        // whoever is debugging it to entirely the wrong place.
        if (!da_download("https://kodpen.com/" . $software_file, $software_file)) {
            $_SESSION['software']['download_assistant']['message'] =
                'Could not download ' . $software_file . ' from the software server. '
                . 'Check that this server can reach kodpen.com, and that a firewall in '
                . 'front of it is not rejecting the request.';

            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        $path = pathinfo(realpath($software_file), PATHINFO_DIRNAME);
        $zip = new ZipArchive;

        // CHECKCONS rejects a damaged archive before a single file is touched,
        // rather than half-applying it. Falls back to a plain open so an
        // archive that only fails the strict check still gets a real error.
        $opened = $zip->open($software_file, ZipArchive::CHECKCONS);

        if ($opened !== TRUE) {
            $opened = $zip->open($software_file);
        }

        if ($opened === TRUE) {
            // Record what the archive claims to hold, so it can be confirmed
            // afterwards. extractTo() stops at the first entry it cannot write
            // and everything after it is silently never created — the reason
            // an update can leave files missing while reporting success.
            $expected_entries = array();

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);

                if ($stat && isset($stat['name']) && substr($stat['name'], -1) !== '/') {
                    $expected_entries[] = $stat['name'];
                }
            }

            $extracted = $zip->extractTo($path);
            $zip->close();

            $missing = array();

            if ($extracted) {
                $base = rtrim($path, '/\\') . '/';

                foreach ($expected_entries as $entry) {
                    if (!file_exists($base . $entry)) {
                        $missing[] = $entry;

                        if (count($missing) >= 25) {
                            break;
                        }
                    }
                }
            }

            if (!$extracted || $missing) {
                $_SESSION['software']['download_assistant']['message'] = !$extracted
                    ? 'Extraction failed. Check that the web server can write to this directory and that the disk is not full.'
                    : count($missing) . '+ file(s) were not written (for example: '
                        . implode(', ', array_slice($missing, 0, 3))
                        . '). The installation is partially updated — run repair again.';

                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            }

            if (is_file($software_file)) {
                unlink($software_file);
            }

            if ($action === 'install') {
                header('Location: pinegrap/install/index.php');
                exit;
            } else {
                clear_update_session();

                include_once('pinegrap/liveform.class.php');
                $liveform_welcome = new liveform('welcome');

                if ($action === 'repair') {
                    log_activity(lang('Software Repair Successful'), $_SESSION['sessionusername']);
                    $liveform_welcome->add_notice(lang('Software Repair Successful'));
                }

                if ($action === 'update') {
                    log_activity(lang('Software Updated Successfully'), $_SESSION['sessionusername']);
                    $liveform_welcome->add_notice(lang('Software Updated Successfully'));
                }

                db("UPDATE config SET software_update_available = 0");

                header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/install/index.php?automated_upgrade=true');
                exit;
            }

        } else {
            if (is_file($software_file)) {
                unlink($software_file);
            }
            $message = 'Something went wrong while extracting zip file.';
            echo output_download_assistant_html_content(
                $output_header,
                $output_footer,
                $output_install_button,
                $output_repair_button,
                $output_update_button,
                $message,
                $version
            );
        }

    } elseif ($action === 'check') {
        if (!function_exists('curl_init')) {
            $_SESSION['software']['download_assistant']['message'] = 'cURL not available on this server.';
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        // Request data
        $request = [
            'hostname'      => defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : $_SERVER['HTTP_HOST'],
            'url'           => (defined('URL_SCHEME') ? URL_SCHEME : 'http://') . $_SERVER['HTTP_HOST'] . ($_SERVER['REQUEST_URI'] ?? ''),
            'version'       => defined('VERSION') ? VERSION : '0.0.0',
            'edition'       => defined('EDITION') ? EDITION : '',
            'uname'         => php_uname(),
            'os'            => PHP_OS,
            'web_server'    => $_SERVER['SERVER_SOFTWARE'] ?? '',
            'php_version'   => phpversion(),
            'mysql_version' => function_exists('db') ? db("SELECT VERSION()") : '',
            'installer'     => defined('INSTALLER') ? INSTALLER : '',
            'private_label' => defined('PRIVATE_LABEL') ? PRIVATE_LABEL : ''
        ];

        $API     = '59593DS72233483322T669223344';
        $REQUEST = 'latest_version';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.kodpen.com/api2?API=' . $API . '&REQUEST=' . $REQUEST);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, da_user_agent());
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, (function_exists('encode_json') ? encode_json($request) : json_encode($request)));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen((function_exists('encode_json') ? encode_json($request) : json_encode($request)))
        ]);

        if (defined('PROXY_ADDRESS') && PROXY_ADDRESS != '') {
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
        }

        $response = curl_exec($ch);
        $curl_errno = curl_errno($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $_SESSION['software']['download_assistant']['message'] =
                'Could not contact update server. Error ' . $curl_errno . ': ' . $curl_error;
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        $response = decode_json($response);

        if (!isset($response['version'])) {
            $_SESSION['software']['download_assistant']['message'] = 'Invalid response from update server.';
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        $new_version = trim($response['version']);
        $old_version = defined('VERSION') ? VERSION : '0.0.0';

        if (is_newer_version($old_version, $new_version)) {
            $_SESSION['software']['download_assistant']['update']['avaliable'] = 'yes';
            $_SESSION['software']['download_assistant']['update']['version']   = $new_version;
            $_SESSION['software']['download_assistant']['message'] = 'New update available: ' . $new_version;
        } else {
            clear_update_session();
            $_SESSION['software']['download_assistant']['message'] = 'You are already on the latest version.';
        }

        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;

    }
}

// -----------------------------
// Helper Functions
// -----------------------------
function clear_update_session()
{
    unset($_SESSION['software']['download_assistant']['update']['avaliable']);
    unset($_SESSION['software']['download_assistant']['update']['version']);
}

function output_download_assistant_html_content(
    string $header,
    string $footer,
    string $install_btn,
    string $repair_btn,
    string $update_btn,
    string $message,
    string $version
) {
    $extra_message = '';
    if (isset($_SESSION['software']['download_assistant']['message'])) {
        $extra_message = '<div class="alert alert-primary mt-3">' 
            . htmlspecialchars($_SESSION['software']['download_assistant']['message']) 
            . '</div>';
        unset($_SESSION['software']['download_assistant']['message']);
    }

    // Daha havalı arkaplan (gradient + pattern overlay)
    $pattern_css = '
        <style>
            body {
                background: linear-gradient(118deg, #fff172 15%, #884cff);
                background-size: 120% 120%;
                background-attachment: fixed;
                min-height: 100vh;
                color: #fff;
                /* Animasyon */
                animation: gradientShift 30s ease infinite;
            }

            body::before {
                content: "";
                position: absolute;
                top: 0; left: 0; right: 0; bottom: 0;
                background-image: radial-gradient(rgba(255,255,255,0.1) 1px, transparent 1px);
                background-size: 25px 25px;
                z-index: 0;
            }
            main, .card {
                position: relative;
                z-index: 1;
            }
            .card {    
                background: transparent;
                backdrop-filter: saturate(1.6) brightness(1.3) blur(30px);
                color: #212529;
                overflow: hidden;
            }
            .reposition{
                min-height: 60vh;
                align-items: center;
            }

            
            /* Gradient animasyonu */
            @keyframes gradientShift {
                0%   { background-position: 0% 50%; }
                50%  { background-position: 100% 50%; }
                100% { background-position: 0% 50%; }
            }

        </style>
    ';

    return $header . $pattern_css . '
    <main class="container py-5 ">
        <div class="row justify-content-center reposition">
            <div class="col-md-6 col-lg-4 ">

                <!-- Logo -->
                <div class="text-center mb-3">
                    
                    <svg style="width: 100%;height: auto;aspect-ratio: 20/3;fill:#ffffff5c" class="my-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 921.38 191.64">
		                <path d="m273.62,8.02h26.52v74.47l33.87-33.66h33.87l-38.76,39.17,45.29,62.84h-29.58l-33.66-46.11-11.02,11.02v35.09h-26.52V8.02Z"></path>
		                <path d="m446.32,54.23c8.09,4.69,14.49,11.09,19.18,19.18,4.69,8.09,7.04,16.9,7.04,26.42s-2.35,18.33-7.04,26.42c-4.69,8.09-11.09,14.49-19.18,19.18-8.09,4.69-16.9,7.04-26.42,7.04s-18.33-2.35-26.42-7.04c-8.09-4.69-14.49-11.08-19.18-19.18-4.69-8.09-7.04-16.9-7.04-26.42s2.35-18.33,7.04-26.42c4.69-8.09,11.08-14.49,19.18-19.18,8.09-4.69,16.9-7.04,26.42-7.04s18.33,2.35,26.42,7.04Zm-44.99,26.52c-5.03,5.1-7.55,11.46-7.55,19.08s2.52,13.98,7.55,19.08c5.03,5.1,11.29,7.65,18.77,7.65s13.67-2.55,18.57-7.65,7.34-11.46,7.34-19.08-2.45-13.98-7.34-19.08c-4.9-5.1-11.09-7.65-18.57-7.65s-13.74,2.55-18.77,7.65Z"></path>
		                <path d="m580.77,126.25c-4.69,8.09-11.05,14.49-19.08,19.18-8.03,4.69-16.8,7.04-26.32,7.04s-18.29-2.35-26.32-7.04c-8.03-4.69-14.42-11.08-19.18-19.18-4.76-8.09-7.14-16.9-7.14-26.42s2.38-18.33,7.14-26.42c4.76-8.09,11.15-14.49,19.18-19.18,8.02-4.69,16.8-7.04,26.32-7.04s18.5,2.59,25.71,7.75V8.02h26.73v91.81c0,9.52-2.35,18.33-7.04,26.42Zm-64.17-45.5c-4.9,5.1-7.34,11.46-7.34,19.08s2.45,13.98,7.34,19.08,11.08,7.65,18.57,7.65,13.74-2.55,18.77-7.65c5.03-5.1,7.55-11.46,7.55-19.08s-2.52-13.98-7.55-19.08c-5.03-5.1-11.29-7.65-18.77-7.65s-13.67,2.55-18.57,7.65Z"></path>
		                <path d="m607.09,73.41c4.69-8.09,11.05-14.49,19.08-19.18,8.02-4.69,16.8-7.04,26.32-7.04s18.29,2.35,26.32,7.04c8.02,4.69,14.42,11.09,19.18,19.18,4.76,8.09,7.14,16.9,7.14,26.42s-2.38,18.33-7.14,26.42c-4.76,8.09-11.15,14.49-19.18,19.18-8.03,4.69-16.8,7.04-26.32,7.04s-18.5-2.58-25.71-7.75v46.92h-26.73v-91.81c0-9.52,2.35-18.33,7.04-26.42Zm64.17,45.5c4.9-5.1,7.34-11.46,7.34-19.08s-2.45-13.98-7.34-19.08c-4.9-5.1-11.09-7.65-18.57-7.65s-13.74,2.55-18.77,7.65c-5.03,5.1-7.55,11.46-7.55,19.08s2.52,13.98,7.55,19.08c5.03,5.1,11.29,7.65,18.77,7.65s13.67-2.55,18.57-7.65Z"></path>
		                <path d="m722.16,73.82c4.55-8.09,10.81-14.55,18.77-19.38,7.96-4.83,16.7-7.24,26.22-7.24,8.7,0,17.07,2.01,25.09,6.02,8.02,4.01,14.65,9.83,19.89,17.44,5.24,7.62,7.99,16.6,8.26,26.93.13,4.35-.14,8.57-.82,12.65h-75.69c1.22,5.03,3.91,9.01,8.06,11.94,4.15,2.93,9.21,4.39,15.2,4.39,4.21,0,7.65-.54,10.3-1.63,2.65-1.09,5.07-2.52,7.24-4.28l31.62.2c-4.22,9.39-10.68,17-19.38,22.85-8.71,5.85-18.36,8.77-28.97,8.77-9.66,0-18.5-2.35-26.52-7.04-8.03-4.69-14.38-11.05-19.08-19.08-4.69-8.02-7.04-16.8-7.04-26.32s2.28-18.12,6.83-26.22Zm69.88,15.2c-2.04-4.76-5.27-8.6-9.69-11.53-4.42-2.92-9.22-4.39-14.38-4.39-5.71,0-10.61,1.43-14.69,4.28-4.08,2.86-7.21,6.73-9.39,11.63h48.15Z"></path>
		                <path d="m921.38,150.84h-26.52v-53.66c0-8.29-1.46-14.38-4.39-18.26-2.93-3.88-7.58-5.81-13.98-5.81s-11.05,1.94-13.97,5.81c-2.93,3.88-4.39,9.97-4.39,18.26v53.66h-26.52v-53.66c0-16.59,4.08-29.07,12.24-37.44,8.16-8.36,19.04-12.55,32.64-12.55s24.48,4.18,32.64,12.55c8.16,8.37,12.24,20.85,12.24,37.44v53.66Z"></path>
		                <path d="m84.22,5.25h-.05s-.06,0-.08,0c-4.3.03-7.76,3.56-7.76,7.86v146.21c0,2.31.94,4.41,2.45,5.92,1.52,1.52,3.61,2.45,5.93,2.45.15,0,.3,0,.44,0,4.32-.03,7.8-3.54,7.8-7.86V13.98c0-4.82-3.91-8.73-8.73-8.73Z"></path>
		                <path d="m60.23,63.12l-39.51-20.8c8.82-12.45,21.97-21.62,37.26-25.29,3.9-.94,6.68-4.38,6.68-8.4v-.03c0-5.59-5.23-9.67-10.66-8.37C33.8,5.08,16.53,17.46,5.34,34.24h0c-.74,1.11-1.48,2.29-2.23,3.56-.74,1.26-1.49,2.6-2.23,4.01-2,3.85-.51,8.59,3.33,10.61l48.27,25.41c5.53,2.91,12.18-1.1,12.18-7.35h0c0-3.08-1.71-5.92-4.44-7.35Z"></path>
		                <path d="m109.77,105.93l39.51,20.8c-8.82,12.45-21.97,21.62-37.26,25.29-3.9.94-6.68,4.38-6.68,8.4v.03c0,5.59,5.23,9.67,10.66,8.37,20.2-4.83,37.47-17.22,48.66-34h0c.74-1.11,1.48-2.29,2.23-3.56.74-1.26,1.49-2.6,2.23-4.01,2-3.85.51-8.59-3.33-10.61l-48.27-25.41c-5.53-2.91-12.18,1.1-12.18,7.35h0c0,3.08,1.71,5.92,4.44,7.35Z"></path>
		            <   
                    <div class="fw-bold mt-2 fs-2" style="color:#393939;">PineGrap™</div>
                </div>

                <!-- Card -->
                <div class="card shadow-sm" style="--bs-card-border-radius: var(--bs-border-radius-xxl);">
                    <div class="card-body text-center">
                        ' . $extra_message . '
                        ' . ($message ? '<p>' . htmlspecialchars($message) . '</p>' : '') . '
                        ' . ($version ? '<p class="text-muted">' . htmlspecialchars($version) . '</p>' : '') . '

                        <form method="post">
                            <div class="d-inline-block">
                                ' . $install_btn . $update_btn . $repair_btn . '
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center small text-muted border-0 d-flex">
                        <span class="badge me-auto bg-body text-dark border  rounded-pill" title="downloader version">Installer v2.0b</span>
                        © ' . date('Y') . ' Kodpen
                    </div>
                </div>

            </div>
        </div>
    </main>' . $footer;
}


/**
 * Compare two version strings (e.g. 8.2.1 vs 8.1.9)
 */
function is_newer_version(string $old, string $new): bool
{
    $old_parts = explode('.', $old);
    $new_parts = explode('.', $new);

    for ($i = 0; $i < max(count($old_parts), count($new_parts)); $i++) {
        $old_val = (int)($old_parts[$i] ?? 0);
        $new_val = (int)($new_parts[$i] ?? 0);

        if ($new_val > $old_val) {
            return true;
        } elseif ($new_val < $old_val) {
            return false;
        }
    }
    return false;
}