<?php

use App\Models\Friendship;
use App\Models\Group;
use App\Models\User;

$myId = (int) $me['id'];
$incoming = array_slice(Friendship::incoming($myId), 0, 3);
$suggestions = User::suggestions($myId, 3);
$groups = array_slice(Group::mine($myId), 0, 4);
?>
<aside class="aside" aria-label="Friends and groups">
<?php if ($incoming): ?>
  <section class="aside-section">
    <div class="aside-head"><h2>Friend requests</h2><a href="<?= e(url('/friends?tab=requests')) ?>">See all</a></div>
    <ul class="person-list">
<?php foreach ($incoming as $person): ?>
      <?= partial('person', [
          'person' => $person,
          'size' => 'sm',
          'sub' => 'Sent ' . ago($person['created_at']),
          'actions' => partial('friend-actions', ['person' => $person, 'status' => Friendship::INCOMING, 'context' => 'compact']),
      ]) ?>
<?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php if ($suggestions): ?>
  <section class="aside-section">
    <div class="aside-head"><h2>People you may know</h2><a href="<?= e(url('/friends?tab=suggestions')) ?>">See all</a></div>
    <ul class="person-list">
<?php foreach ($suggestions as $person): ?>
      <?= partial('person', [
          'person' => $person,
          'size' => 'sm',
          'sub' => (int) $person['mutual'] > 0
              ? plural((int) $person['mutual'], 'mutual friend')
              : null,
          'actions' => partial('friend-actions', ['person' => $person, 'status' => Friendship::NONE, 'context' => 'compact']),
      ]) ?>
<?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

  <section class="aside-section">
    <div class="aside-head"><h2>Your groups</h2><a href="<?= e(url('/groups')) ?>"><?= $groups ? 'See all' : 'Browse' ?></a></div>
<?php if ($groups): ?>
    <ul class="person-list">
<?php foreach ($groups as $group): ?>
      <li class="person">
        <span class="group-tile av-<?= (int) $group['id'] % 8 ?>" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($group['name'], 0, 1))) ?></span>
        <div class="person-main">
          <a class="person-name" href="<?= e(url('/groups/' . $group['id'])) ?>"><?= e($group['name']) ?></a>
          <p class="person-sub"><?= e(plural((int) $group['member_count'], 'member')) ?></p>
        </div>
      </li>
<?php endforeach; ?>
    </ul>
<?php else: ?>
    <p class="small muted">Groups gather posts about one topic. Join one or start your own.</p>
<?php endif; ?>
  </section>

  <footer class="aside-foot small muted">Social Connect · <a href="<?= e(url('/settings')) ?>">Settings</a></footer>
</aside>
