<?php
$kinds = [
    'friend_request' => ['user-plus', 'kind-friend', 'sent you a friend request.'],
    'friend_accept' => ['user-check', 'kind-friend', 'accepted your friend request.'],
    'post_like' => ['heart', 'kind-like', 'liked your post.'],
    'post_comment' => ['message-circle', 'kind-comment', 'commented on your post.'],
];
?>
<div data-page="notifications">
  <div class="page-head"><h1>Notifications</h1></div>
<?php if ($items): ?>
  <div class="card">
<?php foreach ($items as $item): ?>
<?php
    [$kindIcon, $kindClass, $verb] = $kinds[$item['type']] ?? ['bell', '', 'sent you an update.'];
    $href = match ($item['type']) {
        'friend_request' => url('/friends?tab=requests'),
        'friend_accept' => url('/u/' . $item['actor_id']),
        default => $item['post_id'] ? url('/posts/' . $item['post_id'] . ($item['type'] === 'post_comment' ? '#comments' : '')) : url('/'),
    };
    $unread = $item['read_at'] === null;
    $actor = author($item, 'actor_id');
?>
    <a class="note<?= $unread ? ' is-unread' : '' ?>" href="<?= e($href) ?>">
      <?= avatar($actor) ?>
      <span class="note-kind <?= $kindClass ?>" aria-hidden="true"><?= icon($kindIcon) ?></span>
      <span class="note-main">
        <span class="note-text"><strong><?= e(full_name($actor)) ?></strong> <?= e($verb) ?></span>
<?php if (!empty($item['post_body'])): ?>
        <span class="note-quote">"<?= e(excerpt($item['post_body'], 90)) ?>"</span>
<?php endif; ?>
        <span class="note-time"><?= time_tag($item['created_at']) ?><?= $unread ? '<span class="visually-hidden">, new</span>' : '' ?></span>
      </span>
<?php if ($unread): ?>
      <span class="unread-dot" aria-hidden="true"></span>
<?php endif; ?>
    </a>
<?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="card">
    <?= partial('empty', [
        'icon' => 'bell',
        'title' => 'Nothing new',
        'text' => 'Likes, comments and friend requests show up here.',
    ]) ?>
  </div>
<?php endif; ?>
</div>
