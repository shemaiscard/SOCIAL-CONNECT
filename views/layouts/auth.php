<?php
$pageTitle = (isset($title) ? $title . ' · ' : '') . 'Social Connect';
$people = [
    [['id' => 2, 'first_name' => 'Amara', 'last_name' => 'Osei'], 'liked your photo from the lake', 'heart'],
    [['id' => 5, 'first_name' => 'Lukas', 'last_name' => 'Brandt'], 'sent you a message', 'message-circle'],
    [['id' => 3, 'first_name' => 'Priya', 'last_name' => 'Raman'], 'accepted your friend request', 'user-check'],
];
?>
<!doctype html>
<html lang="en" data-base="<?= e(base_path()) ?>" data-icons="<?= e(asset('icons.svg')) ?>">
<head>
<?= partial('head', ['pageTitle' => $pageTitle]) ?>
</head>
<body>
<div class="auth">
  <aside class="auth-side">
    <a class="brand" href="<?= e(url('/')) ?>"><?= partial('logo') ?><span class="brand-name">Social Connect</span></a>
    <div>
      <p class="auth-pitch">Keep up with the people you actually know.</p>
      <p class="auth-pitch-sub">Share posts and photos with friends, chat one to one and run small groups. Your feed shows your friends in the order they posted.</p>
    </div>
    <div class="auth-preview" aria-hidden="true">
<?php foreach ($people as [$person, $text, $iconName]): ?>
      <div class="preview-card">
        <?= avatar($person, 'sm') ?>
        <div><strong><?= e(full_name($person)) ?></strong><p><?= e($text) ?></p></div>
        <?= icon($iconName) ?>
      </div>
<?php endforeach; ?>
    </div>
    <p class="auth-foot">A student project built with PHP and MySQL.</p>
  </aside>
  <main class="auth-main" id="main">
    <div class="auth-box">
      <a class="brand" href="<?= e(url('/')) ?>"><?= partial('logo') ?><span class="brand-name">Social Connect</span></a>
<?= $content ?>
    </div>
  </main>
</div>
<?= partial('toasts') ?>
</body>
</html>
