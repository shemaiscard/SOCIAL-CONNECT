<?php

use App\Models\Friendship;

$tabs = [
    'friends' => 'Friends',
    'requests' => 'Requests',
    'sent' => 'Sent',
    'suggestions' => 'Suggestions',
];
$statusFor = [
    'friends' => Friendship::FRIENDS,
    'requests' => Friendship::INCOMING,
    'sent' => Friendship::OUTGOING,
    'suggestions' => Friendship::NONE,
];
$empty = [
    'friends' => ['users', 'No friends yet', 'Send a few requests from the suggestions tab. Friends see each other\'s friends-only posts and can chat.'],
    'requests' => ['user-plus', 'No requests right now', 'When someone sends you a friend request, it shows up here.'],
    'sent' => ['send', 'No pending requests', 'Requests you send stay here until the other person answers.'],
    'suggestions' => ['search', 'No suggestions', 'You already know everyone here. Try searching by name instead.'],
];
$items = $lists[$tab];
?>
<div class="page-head"><h1>Friends</h1></div>

<nav class="tabs" aria-label="Friend lists">
<?php foreach ($tabs as $key => $label): ?>
  <a href="<?= e(url('/friends?tab=' . $key)) ?>"<?= current_attr($tab === $key) ?>><?= e($label) ?><?php if ($key !== 'suggestions' && $lists[$key]): ?> <span class="tab-count"><?= count($lists[$key]) ?></span><?php endif; ?></a>
<?php endforeach; ?>
</nav>

<div class="card">
<?php if ($items): ?>
  <ul class="person-list">
<?php foreach ($items as $person): ?>
<?php
    $sub = match ($tab) {
        'friends' => ($person['location'] ?? '') !== '' ? $person['location'] : 'Friends since ' . gmdate('M Y', db_time($person['accepted_at'])),
        'requests' => 'Sent you a request ' . ago($person['created_at']),
        'sent' => 'Requested ' . ago($person['created_at']),
        default => (int) ($person['mutual'] ?? 0) > 0 ? plural((int) $person['mutual'], 'mutual friend') : null,
    };
?>
    <?= partial('person', [
        'person' => $person,
        'sub' => $sub,
        'actions' => partial('friend-actions', ['person' => $person, 'status' => $statusFor[$tab], 'context' => 'list']),
    ]) ?>
<?php endforeach; ?>
  </ul>
<?php else: ?>
  <?= partial('empty', [
      'icon' => $empty[$tab][0],
      'title' => $empty[$tab][1],
      'text' => $empty[$tab][2],
      'action' => $tab === 'friends' ? '<a class="btn btn-primary" href="' . e(url('/friends?tab=suggestions')) . '">See suggestions</a>' : '',
  ]) ?>
<?php endif; ?>
</div>
