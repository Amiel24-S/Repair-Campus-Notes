<?php
declare(strict_types=1);

define('APP_CONTEXT', 'web');
require __DIR__ . '/../src/bootstrap.php';

send_html_security_headers();
Session::start();

$db = Database::connection();
$users = new UserRepository($db);
$notes = new NoteRepository($db);
$auth = new Auth($users);
$user = $auth->currentUser();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ---------------------------------------------------------------------------
// State-changing actions: POST only, trusted origin, valid CSRF token.
// ---------------------------------------------------------------------------
if ($method === 'POST') {
    if (!Csrf::originIsTrusted() || !Csrf::isValid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        render('error', ['message' => 'Request rejected: the security token is missing or invalid. Go back, reload the page and try again.']);
        exit;
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

    switch ($action) {
        case 'register':
            if ($user !== null) {
                redirect('/');
            }
            [$email, $err] = Validator::email($_POST['email'] ?? null);
            [$password, $err2] = Validator::password($_POST['password'] ?? null);
            if ($err !== null || $err2 !== null) {
                Session::flash('error', $err ?? $err2);
                redirect('/');
            }
            // Role is not read from the form: every self-registered account is a student.
            if ($users->create($email, password_hash($password, PASSWORD_DEFAULT))) {
                Session::flash('ok', 'Account created. You can now sign in.');
            } else {
                Session::flash('error', 'Registration failed. Try a different email address.');
            }
            redirect('/');

        case 'login':
            if ($user !== null) {
                redirect('/');
            }
            [$email] = Validator::email($_POST['email'] ?? null);
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            $found = ($email !== null && strlen($password) <= 1024) ? $auth->attempt($email, $password) : null;
            if ($found === null) {
                Session::flash('error', 'Invalid email or password.');
                redirect('/');
            }
            Session::login((int) $found['id']);
            redirect('/');

        case 'logout':
            Session::destroy();
            redirect('/');
    }

    // Everything below requires a signed-in user.
    if ($user === null) {
        Session::flash('error', 'Please sign in first.');
        redirect('/');
    }
    $uid = (int) $user['id'];

    switch ($action) {
        case 'create':
            // Owner always comes from the session, never from the request.
            [$note, $err] = Validator::note($_POST['title'] ?? null, $_POST['body'] ?? null);
            if ($err !== null) {
                Session::flash('error', $err);
                redirect('/');
            }
            $notes->create($uid, $note['title'], $note['body']);
            Session::flash('ok', 'Note saved.');
            redirect('/');

        case 'update':
            $id = Validator::id($_POST['id'] ?? null);
            [$note, $err] = Validator::note($_POST['title'] ?? null, $_POST['body'] ?? null);
            if ($id === null) {
                Session::flash('error', 'Note not found.');
                redirect('/');
            }
            if ($err !== null) {
                Session::flash('error', $err);
                redirect('/?edit=' . $id);
            }
            Session::flash(...($notes->update($id, $uid, $note['title'], $note['body'])
                ? ['ok', 'Note updated.']
                : ['error', 'Note not found.']));
            redirect('/');

        case 'delete':
            $id = Validator::id($_POST['id'] ?? null);
            Session::flash(...(($id !== null && $notes->delete($id, $uid))
                ? ['ok', 'Note deleted.']
                : ['error', 'Note not found.']));
            redirect('/');
    }

    http_response_code(400);
    render('error', ['message' => 'Unknown action.']);
    exit;
}

if ($method !== 'GET' && $method !== 'HEAD') {
    header('Allow: GET, HEAD, POST');
    http_response_code(405);
    render('error', ['message' => 'Method not allowed.']);
    exit;
}

// ---------------------------------------------------------------------------
// Read-only page rendering.
// ---------------------------------------------------------------------------
$flash = Session::takeFlash();
$editing = null;
$list = [];
if ($user !== null) {
    $uid = (int) $user['id'];
    if (isset($_GET['edit'])) {
        $id = Validator::id($_GET['edit']);
        $editing = $id !== null ? $notes->findForOwner($id, $uid) : null;
        if ($editing === null) {
            $flash = ['type' => 'error', 'text' => 'Note not found.'];
        }
    }
    $list = $notes->listForOwner($uid);
}

render('home', [
    'user' => $user,
    'notes' => $list,
    'editing' => $editing,
    'flash' => $flash,
    'csrf' => Csrf::token(),
]);
