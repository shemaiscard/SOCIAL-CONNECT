<?php

use App\Models\Friendship;

$self = $status === Friendship::SELF;
$first = $user['first_name'];
?>
<section class="card profile-card" aria-labelledby="profile-name">
  <div class="cover">
<?php if (!empty($user['cover_path'])): ?>
    <img src="<?= e(media_url($user['cover_path'])) ?>" alt="" decoding="async">
<?php endif; ?>
  </div>
  <div class="profile-top">
    <?= avatar($user, 'xl') ?>
    <div class="profile-actions">
      <?= partial('friend-actions', ['person' => $user, 'status' => $status, 'context' => 'profile']) ?>
    </div>
  </div>
  <div class="profile-info">
    <h1 class="profile-name" id="profile-name"><?= e(full_name($user)) ?></h1>
<?php if (!empty($user['bio'])): ?>
    <p class="profile-bio"><?= nl2br(e($user['bio'])) ?></p>
<?php elseif ($self): ?>
    <p class="profile-bio muted">Add a short bio so friends know it is you. <a href="<?= e(url('/settings')) ?>">Edit profile</a></p>
<?php endif; ?>
    <ul class="profile-facts">
      <li><?= icon('users') ?><span><strong><?= number_format($friendCount) ?></strong> <?= $friendCount === 1 ? 'friend' : 'friends' ?></span></li>
<?php if ($mutual > 0): ?>
      <li><?= icon('user-check') ?><span><strong><?= number_format($mutual) ?></strong> mutual</span></li>
<?php endif; ?>
<?php if (!empty($user['location'])): ?>
      <li><?= icon('map-pin') ?><span><?= e($user['location']) ?></span></li>
<?php endif; ?>
      <li><?= icon('calendar') ?><span>Joined <?= e(gmdate('F Y', db_time($user['created_at']))) ?></span></li>
    </ul>
  </div>
</section>

<?php if ($friends): ?>
<section class="card card-pad" aria-labelledby="friends-title">
  <div class="section-head">
    <h2 class="section-title" id="friends-title">Friends</h2>
<?php if ($self): ?>
    <a href="<?= e(url('/friends')) ?>">See all</a>
<?php endif; ?>
  </div>
  <div class="friend-strip">
<?php foreach ($friends as $friend): ?>
    <a href="<?= e(url('/u/' . $friend['id'])) ?>"><?= avatar($friend, 'lg') ?><span><?= e($friend['first_name']) ?></span></a>
<?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<h2 class="section-title feed-head"><?= $self ? 'Your posts' : 'Posts' ?></h2>
<?php if ($self): ?>
<?= partial('composer') ?>
<?php endif; ?>
<div class="post-list" id="posts">
<?php if ($posts): ?>
<?= partial('post-list', ['posts' => $posts, 'next' => $next]) ?>
<?php else: ?>
  <div class="card js-feed-empty">
    <?= partial('empty', [
        'icon' => 'edit',
        'title' => $self ? 'You have not posted yet' : "No posts to show",
        'text' => $self
            ? 'Your posts appear here and in your friends\' feeds.'
            : ($status === Friendship::FRIENDS ? "$first has not posted yet." : "$first shares posts with friends. Public posts appear here."),
        'level' => 3,
    ]) ?>
  </div>
<?php endif; ?>
</div>
