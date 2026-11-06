<?php
declare(strict_types=1);

/*
 * JSON API (same-origin only, cookie session).
 *   GET  /api.php               -> the signed-in user's notes
 *   POST /api.php  {id,title,body}  -> update one of the signed-in user's notes
 *        requires Content-Type: application/json and X-CSRF-Token header
 */

define('APP_CONTEXT', 'api');
require __DIR__ . '/../src/bootstrap.php';

const MAX_BODY_BYTES = 65536;

send_api_headers();

// 1. Cross-origin protection: reject untrusted origins before touching the session.
if (!Csrf::originIsTrusted()) {
    json_response(403, ['error' => 'Origin not allowed']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'POST') {
    header('Allow: GET, POST');
    json_response(405, ['error' => 'Method not supported']);
}

// 2. Authentication.
Session::start();
$db = Database::connection();
$auth = new Auth(new UserRepository($db));
$user = $auth->currentUser();
if ($user === null) {
    json_response(401, ['error' => 'Authentication required']);
}
$uid = (int) $user['id'];
$notes = new NoteRepository($db);

// 3a. Read: only the caller's own notes. The owner is never taken from the client.
if ($method === 'GET') {
    if (isset($_GET['user_id']) && Validator::id($_GET['user_id']) !== $uid) {
        json_response(403, ['error' => 'Forbidden']);
    }
    json_response(200, ['notes' => $notes->listForOwner($uid)]);
}

// 3b. Update: CSRF token, strict content type, size limit, strict JSON parsing, schema check.
if (!Csrf::isValid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    json_response(403, ['error' => 'Missing or invalid CSRF token']);
}

$contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
if ($contentType !== 'application/json') {
    json_response(415, ['error' => 'Content-Type must be application/json']);
}

$raw = file_get_contents('php://input', false, null, 0, MAX_BODY_BYTES + 1);
if ($raw === false || strlen($raw) > MAX_BODY_BYTES) {
    json_response(413, ['error' => 'Request body too large']);
}

try {
    $data = json_decode($raw, true, 4, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    json_response(400, ['error' => 'Malformed JSON']);
}

if (!is_array($data) || array_is_list($data)) {
    json_response(400, ['error' => 'Expected a JSON object']);
}

$allowed = ['id', 'title', 'body'];
$unknown = array_diff(array_keys($data), $allowed);
$missing = array_diff($allowed, array_keys($data));
if ($unknown !== [] || $missing !== []) {
    json_response(422, ['error' => 'Body must contain exactly: id, title, body']);
}
if (!is_int($data['id']) || $data['id'] < 1) {
    json_response(422, ['error' => 'id must be a positive integer']);
}
[$note, $err] = Validator::note($data['title'], $data['body']);
if ($err !== null) {
    json_response(422, ['error' => $err]);
}

// Per-record authorization: another user's note looks exactly like a missing one.
if (!$notes->update($data['id'], $uid, $note['title'], $note['body'])) {
    json_response(404, ['error' => 'Note not found']);
}

json_response(200, ['updated' => 1, 'note' => ['id' => $data['id']] + $note]);
