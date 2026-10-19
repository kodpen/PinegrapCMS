<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Keys and signatures of the WebAuthn verifier (includes/webauthn/webauthn.php):
 * a credential public key in COSE_Key form (RFC 9052, RFC 9053) turned into
 * the PEM SubjectPublicKeyInfo that OpenSSL reads, signature checks for the
 * two algorithms a credential may use, and the attestation certificate
 * checks of the "packed" format.
 *
 * The algorithms:
 *
 *   -7    ES256  ECDSA with SHA-256 on P-256; the COSE key is EC2 (kty 2) with
 *                crv 1 and 32-byte x and y; the signature is the ASN.1 DER
 *                Ecdsa-Sig-Value WebAuthn prescribes.
 *   -257  RS256  RSASSA-PKCS1-v1_5 with SHA-256; the COSE key is RSA (kty 3)
 *                with n and e; the modulus is 2048 to 8192 bits.
 *
 * Nearly every authenticator offers ES256 and Windows Hello offers RS256, so
 * these two are what the creation options ask for. Any other algorithm,
 * curve or key type is refused with the code 'algorithm'; a key of the right
 * type that is malformed or weak, with 'public_key'.
 *
 * A key OpenSSL cannot load (a point that is not on the curve, a broken
 * modulus) never gets as far as a signature check: openssl_pkey_get_public()
 * refuses it and the error queue it leaves is emptied, so a later OpenSSL
 * call elsewhere in the request does not read it as its own.
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
 * The COSE algorithm identifiers a credential may use, in the order the
 * creation options offer them.
 *
 * @return int[]
 */
function pg_webauthn_algorithms()
{
    return array(-7, -257);
}

/**
 * A credential public key, as decoded from the authenticator data, in the
 * form a credential record keeps: its algorithm and its PEM public key.
 *
 * @param mixed $cose the decoded COSE_Key map
 * @return array alg, pem
 * @throws PgWebAuthnException 'algorithm' or 'public_key'
 */
function pg_webauthn_cose_key($cose)
{
    if (!is_array($cose) || !isset($cose[1], $cose[3]) || !is_int($cose[1]) || !is_int($cose[3])) {
        pg_webauthn_fail('public_key');
    }

    $kty = $cose[1];
    $alg = $cose[3];

    if (($kty === 2) && ($alg === -7)) {

        // P-256 only, uncompressed: x and y keep their leading zeros, so each
        // is exactly 32 bytes (RFC 9053 section 7.1.1).
        if (!isset($cose[-1]) || !is_int($cose[-1])) {
            pg_webauthn_fail('public_key');
        }

        if ($cose[-1] !== 1) {
            pg_webauthn_fail('algorithm');
        }

        if (!isset($cose[-2], $cose[-3])
            || !($cose[-2] instanceof PgWebAuthnBytes) || !($cose[-3] instanceof PgWebAuthnBytes)
            || (strlen($cose[-2]->value) !== 32) || (strlen($cose[-3]->value) !== 32)) {
            pg_webauthn_fail('public_key');
        }

        $der = pg_webauthn_der(0x30,
            pg_webauthn_der(0x30,
                pg_webauthn_der(0x06, "\x2a\x86\x48\xce\x3d\x02\x01")           // id-ecPublicKey
                . pg_webauthn_der(0x06, "\x2a\x86\x48\xce\x3d\x03\x01\x07"))    // prime256v1
            . pg_webauthn_der(0x03, "\x00\x04" . $cose[-2]->value . $cose[-3]->value));

    } elseif (($kty === 3) && ($alg === -257)) {

        if (!isset($cose[-1], $cose[-2])
            || !($cose[-1] instanceof PgWebAuthnBytes) || !($cose[-2] instanceof PgWebAuthnBytes)) {
            pg_webauthn_fail('public_key');
        }

        $modulus = ltrim($cose[-1]->value, "\x00");
        $exponent = ltrim($cose[-2]->value, "\x00");

        // An exponent of up to 8 bytes, odd and above 1; the modulus size is
        // checked on the loaded key, where OpenSSL counts its bits.
        if (($modulus === '') || ($exponent === '') || (strlen($exponent) > 8)
            || ((ord(substr($exponent, -1)) & 1) === 0) || ($exponent === "\x01")) {
            pg_webauthn_fail('public_key');
        }

        $der = pg_webauthn_der(0x30,
            pg_webauthn_der(0x30,
                pg_webauthn_der(0x06, "\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01")   // rsaEncryption
                . pg_webauthn_der(0x05, ''))
            . pg_webauthn_der(0x03, "\x00" . pg_webauthn_der(0x30,
                pg_webauthn_der_integer($modulus) . pg_webauthn_der_integer($exponent))));

    } else {

        pg_webauthn_fail('algorithm');

    }

    $pem = pg_webauthn_pem('PUBLIC KEY', $der);

    if (pg_webauthn_public_key($pem, $alg) === false) {
        pg_webauthn_fail('public_key');
    }

    return array('alg' => $alg, 'pem' => $pem);
}

/**
 * Loads a PEM public key (or the key of a PEM certificate) for one algorithm.
 * The key type has to be the algorithm's: an RSA key under -7, or an EC key
 * on another curve, is refused rather than checked with the wrong rules.
 *
 * @param string $pem
 * @param int    $alg
 * @return resource|object|false the key, or false
 */
function pg_webauthn_public_key($pem, $alg)
{
    if (!function_exists('openssl_pkey_get_public')) {
        return false;
    }

    $key = @openssl_pkey_get_public((string) $pem);

    if ($key === false) {
        pg_webauthn_openssl_clear();
        return false;
    }

    $details = @openssl_pkey_get_details($key);

    if (!is_array($details) || !isset($details['type'])) {
        pg_webauthn_openssl_clear();
        return false;
    }

    if ($alg === -7) {
        $usable = ($details['type'] === OPENSSL_KEYTYPE_EC)
            && isset($details['ec']['curve_name'])
            && ($details['ec']['curve_name'] === 'prime256v1');
    } elseif ($alg === -257) {
        $usable = ($details['type'] === OPENSSL_KEYTYPE_RSA)
            && isset($details['bits'])
            && ($details['bits'] >= 2048) && ($details['bits'] <= 8192);
    } else {
        $usable = false;
    }

    return $usable ? $key : false;
}

/**
 * Whether $signature is a valid signature of $data under $key with $alg.
 *
 * @param resource|object $key from pg_webauthn_public_key()
 * @param int    $alg
 * @param string $data
 * @param string $signature
 * @return bool
 */
function pg_webauthn_signature_ok($key, $alg, $data, $signature)
{
    $signature = (string) $signature;

    if (($alg === -7) && !pg_webauthn_ecdsa_der_ok($signature)) {
        return false;
    }

    if (($alg !== -7) && ($alg !== -257)) {
        return false;
    }

    if (($signature === '') || (strlen($signature) > 1024)) {
        return false;
    }

    $result = @openssl_verify((string) $data, $signature, $key, OPENSSL_ALGO_SHA256);

    if ($result !== 1) {
        pg_webauthn_openssl_clear();
        return false;
    }

    return true;
}

/**
 * The shape of a P-256 ECDSA signature in DER: SEQUENCE { INTEGER r,
 * INTEGER s }, each integer 1 to 33 bytes, nothing after it. OpenSSL checks
 * the encoding again; this keeps a malformed blob from reaching it at all.
 *
 * @param string $signature
 * @return bool
 */
function pg_webauthn_ecdsa_der_ok($signature)
{
    $length = strlen($signature);

    if (($length < 8) || ($length > 72) || (ord($signature[0]) !== 0x30) || (ord($signature[1]) !== ($length - 2))) {
        return false;
    }

    $offset = 2;

    for ($i = 0; $i < 2; $i++) {

        if ((($offset + 2) > $length) || (ord($signature[$offset]) !== 0x02)) {
            return false;
        }

        $integer_length = ord($signature[$offset + 1]);

        if (($integer_length < 1) || ($integer_length > 33) || (($offset + 2 + $integer_length) > $length)) {
            return false;
        }

        $offset += 2 + $integer_length;

    }

    return $offset === $length;
}

/**
 * Whether a "packed" attestation certificate meets the format's rules
 * (WebAuthn section 8.2.1): version 3; subject C (two letters), O, OU
 * "Authenticator Attestation" and CN; a basic constraints extension that does
 * not make it a CA; and, when it carries the id-fido-gen-ce-aaguid extension
 * (1.3.6.1.4.1.45724.1.1.4), that extension not critical and holding the
 * AAGUID of the authenticator data as a 16-byte OCTET STRING.
 *
 * Version and subject come from openssl_x509_parse(). The extensions are read
 * from the DER itself: that function names an extension by whatever the
 * OpenSSL build calls it and prints known ones as text, and neither may
 * decide whether a check runs.
 *
 * Whether the certificate is genuine is a separate question this does not
 * answer: no trust anchors are kept, so the attestation stays unverified.
 *
 * @param string $der    the certificate (x5c[0])
 * @param string $aaguid 16 bytes from the authenticator data
 * @return bool
 */
function pg_webauthn_packed_certificate_ok($der, $aaguid)
{
    $extensions = pg_webauthn_certificate_extensions($der);

    if ($extensions === false) {
        return false;
    }

    if (!isset($extensions['2.5.29.19']) || (pg_webauthn_basic_constraints_ca($extensions['2.5.29.19']['value']) !== false)) {
        return false;
    }

    if (isset($extensions['1.3.6.1.4.1.45724.1.1.4'])) {

        $extension = $extensions['1.3.6.1.4.1.45724.1.1.4'];

        // The extension value is the DER of an OCTET STRING, itself carried
        // in the extension's own OCTET STRING: two wrappings around 16 bytes.
        if ($extension['critical'] || ($extension['value'] !== "\x04\x10" . (string) $aaguid)) {
            return false;
        }

    }

    if (!function_exists('openssl_x509_parse')) {
        return false;
    }

    $info = @openssl_x509_parse(pg_webauthn_pem('CERTIFICATE', $der));

    if (!is_array($info)) {
        pg_webauthn_openssl_clear();
        return false;
    }

    if (!isset($info['version']) || ((int) $info['version'] !== 2)) {
        return false;
    }

    $subject = (isset($info['subject']) && is_array($info['subject'])) ? $info['subject'] : array();

    foreach (array('C', 'O', 'OU', 'CN') as $field) {
        if (!isset($subject[$field]) || !is_string($subject[$field]) || ($subject[$field] === '')) {
            return false;
        }
    }

    return ($subject['OU'] === 'Authenticator Attestation') && (preg_match('/^[A-Za-z]{2}$/', $subject['C']) === 1);
}

/**
 * The extensions of a DER X.509 certificate, by dotted OID: critical (bool)
 * and value (the bytes inside extnValue). false when the certificate is not
 * one DER SEQUENCE, an extension is malformed, or an OID appears twice.
 *
 * @param string $der
 * @return array|false
 */
function pg_webauthn_certificate_extensions($der)
{
    $der = (string) $der;
    $offset = 0;
    $certificate = pg_webauthn_der_read($der, $offset);

    if (($certificate === false) || ($certificate[0] !== 0x30) || ($offset !== strlen($der))) {
        return false;
    }

    // Certificate ::= SEQUENCE { tbsCertificate, signatureAlgorithm, signature }
    $offset = 0;
    $tbs = pg_webauthn_der_read($certificate[1], $offset);

    if (($tbs === false) || ($tbs[0] !== 0x30)) {
        return false;
    }

    $extensions = array();
    $offset = 0;

    while ($offset < strlen($tbs[1])) {

        $field = pg_webauthn_der_read($tbs[1], $offset);

        if ($field === false) {
            return false;
        }

        // extensions [3] EXPLICIT SEQUENCE OF Extension; the other fields
        // (version, serial, names, validity, key) are passed over.
        if ($field[0] !== 0xa3) {
            continue;
        }

        $list_offset = 0;
        $list = pg_webauthn_der_read($field[1], $list_offset);

        if (($list === false) || ($list[0] !== 0x30) || ($list_offset !== strlen($field[1]))) {
            return false;
        }

        $item_offset = 0;

        while ($item_offset < strlen($list[1])) {

            // Extension ::= SEQUENCE { extnID OID, critical BOOLEAN DEFAULT
            // FALSE, extnValue OCTET STRING }
            $extension = pg_webauthn_der_read($list[1], $item_offset);

            if (($extension === false) || ($extension[0] !== 0x30)) {
                return false;
            }

            $part_offset = 0;
            $oid = pg_webauthn_der_read($extension[1], $part_offset);
            $next = pg_webauthn_der_read($extension[1], $part_offset);
            $critical = false;

            if (($next !== false) && ($next[0] === 0x01)) {

                if (($next[1] !== "\xff") && ($next[1] !== "\x00")) {
                    return false;
                }

                $critical = ($next[1] === "\xff");
                $next = pg_webauthn_der_read($extension[1], $part_offset);

            }

            if (($oid === false) || ($oid[0] !== 0x06) || ($next === false) || ($next[0] !== 0x04)
                || ($part_offset !== strlen($extension[1]))) {
                return false;
            }

            $name = pg_webauthn_der_oid($oid[1]);

            if (($name === '') || isset($extensions[$name])) {
                return false;
            }

            $extensions[$name] = array('critical' => $critical, 'value' => $next[1]);

        }

    }

    return $extensions;
}

/**
 * Whether a basicConstraints extension value makes the certificate a CA:
 * true, false, or null when the value is malformed. BasicConstraints ::=
 * SEQUENCE { cA BOOLEAN DEFAULT FALSE, pathLenConstraint INTEGER OPTIONAL }.
 *
 * @param string $value
 * @return bool|null
 */
function pg_webauthn_basic_constraints_ca($value)
{
    $value = (string) $value;
    $offset = 0;
    $sequence = pg_webauthn_der_read($value, $offset);

    if (($sequence === false) || ($sequence[0] !== 0x30) || ($offset !== strlen($value))) {
        return null;
    }

    if ($sequence[1] === '') {
        return false;
    }

    $offset = 0;
    $first = pg_webauthn_der_read($sequence[1], $offset);

    if ($first === false) {
        return null;
    }

    if ($first[0] === 0x01) {

        if ($first[1] === "\xff") {
            return true;
        }

        return ($first[1] === "\x00") ? false : null;

    }

    // No cA: only a path length, which does not make a CA on its own.
    return ($first[0] === 0x02) ? false : null;
}

/**
 * One DER element at $offset: its tag and contents, moving $offset past it.
 * Single-byte tags and definite lengths of up to four bytes, which is all an
 * X.509 certificate uses; false when the element does not fit what is left.
 *
 * @param string $der
 * @param int    $offset
 * @return array|false array(tag, contents)
 */
function pg_webauthn_der_read($der, &$offset)
{
    $length = strlen($der);

    if (($offset < 0) || (($offset + 2) > $length)) {
        return false;
    }

    $tag = ord($der[$offset]);
    $size = ord($der[$offset + 1]);
    $offset += 2;

    if (($tag & 0x1f) === 0x1f) {
        return false;
    }

    if (($size & 0x80) !== 0) {

        $count = $size & 0x7f;

        if (($count < 1) || ($count > 4) || (($offset + $count) > $length)) {
            return false;
        }

        $size = 0;

        for ($i = 0; $i < $count; $i++) {
            $size = ($size * 256) + ord($der[$offset + $i]);
        }

        $offset += $count;

    }

    if ($size > ($length - $offset)) {
        return false;
    }

    $contents = (string) substr($der, $offset, $size);
    $offset += $size;

    return array($tag, $contents);
}

/**
 * A DER OBJECT IDENTIFIER's contents as dotted text, '' when malformed
 * (empty, an arc cut short or padded with a leading 0x80, or an arc over
 * 28 bits, which none of the identifiers read here have).
 *
 * @param string $bytes
 * @return string
 */
function pg_webauthn_der_oid($bytes)
{
    $bytes = (string) $bytes;
    $length = strlen($bytes);

    if ($length === 0) {
        return '';
    }

    $arcs = array();
    $value = 0;
    $byte = 0;

    for ($i = 0; $i < $length; $i++) {

        $byte = ord($bytes[$i]);

        if ((($value === 0) && ($byte === 0x80)) || ($value > 0x1fffff)) {
            return '';
        }

        $value = ($value << 7) | ($byte & 0x7f);

        if (($byte & 0x80) === 0) {
            $arcs[] = $value;
            $value = 0;
        }

    }

    if (($byte & 0x80) !== 0) {
        return '';
    }

    // The first byte carries the first two arcs (40 * first + second).
    $first = $arcs[0];
    $head = ($first < 40) ? array(0, $first) : (($first < 80) ? array(1, $first - 40) : array(2, $first - 80));

    return implode('.', array_merge($head, array_slice($arcs, 1)));
}

/**
 * One DER element: tag, definite length, content.
 *
 * @param int    $tag
 * @param string $content
 * @return string
 */
function pg_webauthn_der($tag, $content)
{
    $length = strlen($content);

    if ($length < 0x80) {
        return chr($tag) . chr($length) . $content;
    }

    $length_bytes = ltrim(pack('N', $length), "\x00");

    return chr($tag) . chr(0x80 | strlen($length_bytes)) . $length_bytes . $content;
}

/**
 * A DER INTEGER holding an unsigned big-endian number: leading zeros dropped,
 * one put back when the top bit would make it negative.
 *
 * @param string $bytes
 * @return string
 */
function pg_webauthn_der_integer($bytes)
{
    $bytes = ltrim((string) $bytes, "\x00");

    if (($bytes === '') || ((ord($bytes[0]) & 0x80) !== 0)) {
        $bytes = "\x00" . $bytes;
    }

    return pg_webauthn_der(0x02, $bytes);
}

/**
 * DER bytes as PEM text with the given label.
 *
 * @param string $label 'PUBLIC KEY' or 'CERTIFICATE'
 * @param string $der
 * @return string
 */
function pg_webauthn_pem($label, $der)
{
    return '-----BEGIN ' . $label . "-----\n"
        . chunk_split(base64_encode((string) $der), 64, "\n")
        . '-----END ' . $label . "-----\n";
}

/**
 * Empties OpenSSL's error queue after a refused key, certificate or
 * signature, so the next OpenSSL call in the request starts clean.
 */
function pg_webauthn_openssl_clear()
{
    if (!function_exists('openssl_error_string')) {
        return;
    }

    // The queue is short; the bound only guards against a library that
    // never reports it empty.
    for ($i = 0; ($i < 64) && (openssl_error_string() !== false); $i++) {
    }
}
