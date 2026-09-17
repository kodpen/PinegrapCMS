<?php

namespace Iyzipay;

class Curl
{
    /**
     * Execute a cURL request and return the response body.
     *
     * Originally this just returned `curl_exec()` without any error capture —
     * if the request failed (SSL verify error, DNS, timeout) the caller saw
     * an empty string indistinguishable from a successful empty response.
     * That made the "Ödeme başlatılamadı" / "status= code= message= raw="
     * symptom impossible to diagnose. We now log every cURL-level failure
     * via PHP's standard error_log so the operator can see the actual
     * cause in the PHP error log AND surface a synthetic "error" JSON
     * body so the SDK's response parser stops returning silent empties.
     */
    public function exec($url, $options)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        if ($body === false) {
            $errno = curl_errno($ch);
            $err   = curl_error($ch);
            curl_close($ch);
            // Log so the operator can spot SSL/network issues without
            // attaching a debugger.
            @error_log("Iyzipay\\Curl: cURL error #$errno: $err (URL: $url)");
            // Returning a synthetic JSON body lets the SDK's resource
            // parser populate an `errorMessage` field, which `submit_order.php`
            // already logs via `log_activity()`. Without this, the SDK
            // returns empty strings for status/code/message/raw.
            return json_encode(array(
                'status'       => 'failure',
                'errorCode'    => 'curl_' . $errno,
                'errorMessage' => 'cURL: ' . $err,
            ));
        }
        curl_close($ch);
        return $body;
    }
}
