<?php

namespace Iyzipay;

class DefaultHttpClient implements HttpClient
{
    private $curl;

    public function __construct($curl = null)
    {
        if (!$curl) {
            $curl = new Curl();
        }
        $this->curl = $curl;
    }

    public static function create($curl = null)
    {
        return new DefaultHttpClient($curl);
    }

    /**
     * Returns cURL options shared by every request. On Windows builds of
     * PHP, cURL ships WITHOUT a bundled CA store, so HTTPS requests to
     * https://sandbox-api.iyzipay.com fail silently with cURL errno 60
     * ("unable to get local issuer certificate"). The SDK's `Curl` wrapper
     * doesn't check curl_errno — `curl_exec()` returns FALSE and the
     * resulting empty body flows through `ThreedsInitialize::create()` as
     * `status='', errorCode='', errorMessage=''` (the exact "Ödeme
     * başlatılamadı" symptom).
     *
     * We bundle Mozilla's cacert.pem alongside the SDK and point CURLOPT_CAINFO
     * at it whenever it exists; on Linux installs that already have a system
     * CA store wired up via curl.cainfo, this is a no-op (CURLOPT_CAINFO
     * is only set when the bundled file is present). Also raises sane
     * timeouts so a stalled network doesn't take down PHP-FPM workers.
     */
    private function commonOptions()
    {
        $opts = array(
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
        );
        $bundled_ca = __DIR__ . '/../../cacert.pem';
        if (file_exists($bundled_ca)) {
            $opts[CURLOPT_CAINFO]         = $bundled_ca;
            $opts[CURLOPT_SSL_VERIFYPEER] = true;
            $opts[CURLOPT_SSL_VERIFYHOST] = 2;
        }
        return $opts;
    }

    public function get($url)
    {
        return $this->curl->exec($url, $this->commonOptions() + array(
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => false,
            CURLOPT_HEADER => false
        ));
    }

    public function getV2($url, $header)
    {
        return $this->curl->exec($url, $this->commonOptions() + array(
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => false,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $header
        ));
    }

    public function post($url, $header, $content)
    {
        return $this->curl->exec($url, $this->commonOptions() + array(
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => false,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $header
        ));
    }

    public function put($url, $header, $content)
    {
        return $this->curl->exec($url, $this->commonOptions() + array(
            CURLOPT_CUSTOMREQUEST => "PUT",
            CURLOPT_POSTFIELDS => $content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => false,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $header
        ));
    }

    public function patch($url, $header, $content)
    {
        return $this->curl->exec($url, $this->commonOptions() + array(
            CURLOPT_CUSTOMREQUEST => "PATCH",
            CURLOPT_POSTFIELDS => $content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => false,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $header
        ));
    }

    public function delete($url, $header, $content = null)
    {
        return $this->curl->exec($url, $this->commonOptions() + array(
            CURLOPT_CUSTOMREQUEST => "DELETE",
            CURLOPT_POSTFIELDS => $content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => false,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $header
        ));
    }
}
