<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Stores a visitor's answer to "Was this helpful?" on a collection record's
 * page: POST record=<forms.id>&answer=yes|no with the X-PG-Vote header, sent
 * by assets/js/pg_kb_vote.js. Answered in JSON: {"ok": true}, or
 * {"error": "..."} with 400, 404, 405, 409, 429 or 500.
 *
 * Only a complete record of a collection form takes a vote
 * (pg_kb_vote_record_form()). No address, IP or user is stored; the cookie
 * PG_KB_VOTE_COOKIE stops a second vote from the same browser (409 voted).
 * Public, so it takes no form token: the custom header keeps a plain
 * cross-site form from posting, and each address may vote PG_KB_VOTE_LIMIT
 * times in PG_KB_VOTE_WINDOW seconds (pg_kb_vote_throttled()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');

$method = isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : '';
$record_id = (isset($_POST['record']) && is_scalar($_POST['record'])) ? (int) $_POST['record'] : 0;
$answer = pg_kb_vote_answer(isset($_POST['answer']) ? $_POST['answer'] : null);
$status = 200;
$reply = array('ok' => true);
$form_id = 0;

if ($method !== 'POST') {
    header('Allow: POST');
    $status = 405;
    $reply = array('error' => 'method');
} elseif (empty($_SERVER['HTTP_X_PG_VOTE']) || ($record_id <= 0) || ($answer === null)) {
    $status = 400;
    $reply = array('error' => 'request');
} elseif (pg_kb_vote_given($record_id)) {
    $status = 409;
    $reply = array('error' => 'voted');
} elseif (pg_kb_vote_throttled()) {
    $status = 429;
    $reply = array('error' => 'rate');
} elseif (($form_id = pg_kb_vote_record_form($record_id)) === 0) {
    $status = 404;
    $reply = array('error' => 'record');
} elseif (!pg_kb_vote_store($form_id, $record_id, $answer)) {
    $status = 500;
    $reply = array('error' => 'store');
} else {
    pg_kb_vote_remember($record_id);
}

http_response_code($status);
echo json_encode($reply, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
