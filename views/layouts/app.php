<?php

use App\Core\Auth;
use App\Models\Notification;

$me = Auth::user();
$active = $active ?? '';
$counts = Notification::counts((int) $me['id']);
if ($active === 'notifications') {
    $counts['notifications'] = 0;
}
$unread = $counts['notifications'] + $counts['messages'];
$pageTitle = ($unread > 0 ? '(' . ($unread > 99 ? '99+' : $unread) . ') ' : '')
    . (isset($title) ? $title . ' · ' : '') . 'Social Connect';
$badge = static fn (int $n): string => $n > 0 ? ($n > 99 ? '99+' : (string) $n) : '';
$profileUrl = url('/u/' . $me['id']);

$nav = [
    ['home', '/', 'home', 'Home', null],
    ['friends', '/friends', 'users', 'Friends', 'requests'],
    ['messages', '/messages', 'message-square', 'Messages', 'messages'],
    ['notifications', '/notifications', 'bell', 'Notifications', 'notifications'],
    ['groups', '/groups', 'layers', 'Groups', null],
    ['saved', '/saved', 'bookmark', 'Saved', null],
    ['profile', '/u/' . $me['id'], 'user', 'Profile', null],
    ['settings', '/settings', 'sliders', 'Settings', null],
];
?>
<!doctype html>
<html lang="en" data-base="<?= e(base_path()) ?>" data-icons="<?= e(asset('icons.svg')) ?>">
<head>
<?= partial('head', ['pageTitle' => $pageTitle]) ?>
</head>
<body data-signed-in="1"<?= !empty($wide) ? ' class="is-wide"' : '' ?>>
<a class="skip-link" href="#main">Skip to content</a>

<header class="topbar">
  <a class="brand" href="<?= e(url('/')) ?>" aria-label="Social Connect home"><?= partial('logo') ?><span class="brand-name">Social Connect</span></a>
  <a class="icon-btn" href="<?= e(url('/search')) ?>" aria-label="Search"><?= icon('search') ?></a>
  <a class="icon-btn" href="<?= e($profileUrl) ?>" aria-label="Your profile"><?= avatar($me, 'sm') ?></a>
</header>

<div class="shell">
  <nav class="rail" aria-label="Main">
    <a class="brand" href="<?= e(url('/')) ?>" aria-label="Social Connect home"><?= partial('logo') ?><span class="brand-name">Social Connect</span></a>
    <form class="rail-search" role="search" action="<?= e(url('/search')) ?>" method="get">
      <?= icon('search') ?>
      <label class="visually-hidden" for="rail-q">Search Social Connect</label>
      <input id="rail-q" type="search" name="q" placeholder="Search" autocomplete="off" value="<?= e($active === 'search' ? ($query ?? '') : '') ?>">
    </form>
    <ul class="nav">
<?php foreach ($nav as [$key, $path, $iconName, $label, $count]): ?>
      <li>
        <a class="nav-link" href="<?= e(url($path)) ?>"<?= current_attr($active === $key) ?>>
          <?= icon($iconName) ?><span class="nav-label"><?= e($label) ?></span>
<?php if ($count !== null): ?>
          <span class="badge" data-badge="<?= e($count) ?>"><?= $badge($counts[$count]) ?></span>
<?php endif; ?>
        </a>
      </li>
<?php endforeach; ?>
    </ul>
    <div class="rail-foot">
      <button class="nav-link js-theme-toggle" type="button" aria-pressed="false" title="Switch theme">
        <?= icon('moon') ?><span class="nav-label">Dark mode</span>
      </button>
      <form method="post" action="<?= e(url('/logout')) ?>">
        <?= csrf_field() ?>
        <button class="nav-link" type="submit" title="Log out"><?= icon('log-out') ?><span class="nav-label">Log out</span></button>
      </form>
      <a class="me-chip" href="<?= e($profileUrl) ?>" title="<?= e(full_name($me)) ?>"><?= avatar($me, 'sm') ?><span><?= e(full_name($me)) ?></span></a>
    </div>
  </nav>

  <main class="main" id="main" tabindex="-1">
<?= $content ?>
  </main>

<?php if (empty($wide)): ?>
<?= partial('aside', ['me' => $me]) ?>
<?php endif; ?>
</div>

<nav class="tabbar" aria-label="Main">
<?php foreach (array_slice($nav, 0, 4) as [$key, $path, $iconName, $label, $count]): ?>
  <a class="tab" href="<?= e(url($path)) ?>"<?= current_attr($active === $key) ?>>
    <?= icon($iconName) ?><span><?= e($label === 'Notifications' ? 'Alerts' : $label) ?></span>
<?php if ($count !== null): ?>
    <span class="badge" data-badge="<?= e($count) ?>"><?= $badge($counts[$count]) ?></span>
<?php endif; ?>
  </a>
<?php endforeach; ?>
  <button class="tab" type="button" popovertarget="more-menu"<?= current_attr(in_array($active, ['groups', 'saved', 'profile', 'settings', 'search'], true)) ?>>
    <?= icon('menu') ?><span>More</span>
  </button>
</nav>
<div class="menu" id="more-menu" popover>
<?php foreach (array_slice($nav, 4) as [$key, $path, $iconName, $label]): ?>
  <a class="menu-item" href="<?= e(url($path)) ?>"<?= current_attr($active === $key) ?>><?= icon($iconName) ?><?= e($label) ?></a>
<?php endforeach; ?>
  <a class="menu-item" href="<?= e(url('/search')) ?>"><?= icon('search') ?>Search</a>
  <div class="menu-sep" role="separator"></div>
  <button class="menu-item js-theme-toggle" type="button" aria-pressed="false"><?= icon('moon') ?><span class="theme-label">Dark mode</span></button>
  <form method="post" action="<?= e(url('/logout')) ?>">
    <?= csrf_field() ?>
    <button class="menu-item" type="submit"><?= icon('log-out') ?>Log out</button>
  </form>
</div>

<?= partial('toasts') ?>

<dialog class="dialog" id="confirm-dialog" aria-labelledby="confirm-title">
  <form class="dialog-body" method="dialog">
    <h2 class="dialog-title" id="confirm-title">Are you sure?</h2>
    <p class="dialog-text" hidden></p>
    <fieldset class="choice-list" hidden>
      <legend class="visually-hidden">Reason for the report</legend>
<?php foreach (['spam' => 'Spam or scam', 'harassment' => 'Harassment or bullying', 'inappropriate' => 'Inappropriate content', 'other' => 'Something else'] as $value => $label): ?>
      <label class="choice"><input type="radio" name="reason" value="<?= e($value) ?>"<?= $value === 'spam' ? ' checked' : '' ?>><?= e($label) ?></label>
<?php endforeach; ?>
    </fieldset>
    <div class="dialog-actions">
      <button class="btn btn-secondary" value="cancel">Cancel</button>
      <button class="btn btn-primary" value="ok">Confirm</button>
    </div>
  </form>
</dialog>

<template id="tpl-skeleton">
<?php for ($i = 0; $i < 2; $i++): ?>
  <div class="card skeleton-post" aria-hidden="true">
    <div class="skeleton-row">
      <span class="skeleton skeleton-circle"></span>
      <span class="skeleton skeleton-line is-short"></span>
    </div>
    <span class="skeleton skeleton-line"></span>
    <span class="skeleton skeleton-line"></span>
    <span class="skeleton skeleton-line is-mid"></span>
  </div>
<?php endfor; ?>
</template>
</body>
</html>
