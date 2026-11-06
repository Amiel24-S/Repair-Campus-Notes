<?php
/**
 * @var ?array  $user
 * @var array   $notes
 * @var ?array  $editing
 * @var ?array  $flash
 * @var string  $csrf
 * Every dynamic value is passed through h().
 */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= h($csrf) ?>">
<title>Campus Notes</title>
<link rel="stylesheet" href="/style.css">
</head>
<body>
<h1>Campus Notes</h1>

<?php if ($flash !== null): ?>
<p class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>" role="status"><?= h($flash['text']) ?></p>
<?php endif; ?>

<?php if ($user === null): ?>
<h2>Register or sign in</h2>
<form method="post" action="/">
  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
  <label>Email <input name="email" type="email" maxlength="254" autocomplete="username" required></label>
  <label>Password <input name="password" type="password" minlength="<?= Validator::PASSWORD_MIN ?>" maxlength="<?= Validator::PASSWORD_MAX ?>" autocomplete="current-password" required></label>
  <button name="action" value="login">Sign in</button>
  <button name="action" value="register">Register</button>
</form>
<?php else: ?>
<div class="bar">
  <span>Signed in as <?= h($user['email']) ?> | Role: <?= h($user['role']) ?></span>
  <form method="post" action="/" class="inline">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <button name="action" value="logout">Log out</button>
  </form>
</div>

<?php if ($editing !== null): ?>
<h2>Edit note</h2>
<form method="post" action="/">
  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
  <input type="hidden" name="id" value="<?= h($editing['id']) ?>">
  <label>Title <input name="title" value="<?= h($editing['title']) ?>" maxlength="<?= Validator::TITLE_MAX ?>" required></label>
  <label>Body <textarea name="body" maxlength="<?= Validator::BODY_MAX ?>" required><?= h($editing['body']) ?></textarea></label>
  <button name="action" value="update">Update</button>
  <a href="/">Cancel</a>
</form>
<?php endif; ?>

<h2>Create a note</h2>
<form method="post" action="/">
  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
  <label>Title <input name="title" maxlength="<?= Validator::TITLE_MAX ?>" required></label>
  <label>Body <textarea name="body" maxlength="<?= Validator::BODY_MAX ?>" required></textarea></label>
  <button name="action" value="create">Save</button>
</form>

<h2>My notes</h2>
<?php if ($notes === []): ?>
<p>No notes yet.</p>
<?php endif; ?>
<?php foreach ($notes as $note): ?>
<article>
  <h3><?= h($note['title']) ?></h3>
  <p><?= nl2br(h($note['body'])) ?></p>
  <a href="/?edit=<?= h($note['id']) ?>">Edit</a>
  <form method="post" action="/" class="inline">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <input type="hidden" name="id" value="<?= h($note['id']) ?>">
    <button name="action" value="delete">Delete</button>
  </form>
</article>
<?php endforeach; ?>
<?php endif; ?>
</body>
</html>
