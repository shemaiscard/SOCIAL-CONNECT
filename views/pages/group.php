<?php
$id = (int) $group['id'];
$role = $group['my_role'];
$memberCount = (int) $group['member_count'];
$shown = array_slice($members, 0, 8);
?>
<section class="card card-pad" aria-labelledby="group-title">
  <div class="group-head">
    <span class="group-tile group-tile-lg av-<?= $id % 8 ?>" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($group['name'], 0, 1))) ?></span>
    <div class="group-head-main">
      <h1 id="group-title"><?= e($group['name']) ?></h1>
      <p class="small muted"><?= e(plural($memberCount, 'member')) ?> · <?= e(plural((int) $group['post_count'], 'post')) ?> · Created <?= e(gmdate('M Y', db_time($group['created_at']))) ?></p>
<?php if (($group['description'] ?? '') !== ''): ?>
      <p class="group-desc"><?= e($group['description']) ?></p>
<?php endif; ?>
    </div>
    <div class="friend-actions">
<?php if ($role === null): ?>
      <form method="post" action="<?= e(url('/groups/' . $id . '/join')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-primary" type="submit"><?= icon('plus') ?><span>Join group</span></button>
      </form>
<?php elseif ($role === 'owner'): ?>
      <span class="role-tag">You own this group</span>
      <form method="post" action="<?= e(url('/groups/' . $id . '/delete')) ?>" data-confirm="Delete this group?" data-confirm-text="Its posts and comments are deleted for everyone. This can not be undone." data-confirm-label="Delete group" data-confirm-tone="danger">
        <?= csrf_field() ?>
        <button class="btn btn-ghost" type="submit"><?= icon('trash-2') ?><span>Delete</span></button>
      </form>
<?php else: ?>
      <form method="post" action="<?= e(url('/groups/' . $id . '/leave')) ?>" data-confirm="Leave <?= e($group['name']) ?>?" data-confirm-text="You can join again at any time." data-confirm-label="Leave group">
        <?= csrf_field() ?>
        <button class="btn btn-secondary" type="submit"><?= icon('log-out') ?><span>Leave</span></button>
      </form>
<?php endif; ?>
    </div>
  </div>
<?php if ($shown): ?>
  <div class="member-stack" aria-label="Members">
<?php foreach ($shown as $member): ?>
    <a href="<?= e(url('/u/' . $member['id'])) ?>" title="<?= e(full_name($member) . ($member['role'] === 'owner' ? ' (owner)' : '')) ?>"><?= avatar($member, 'sm') ?><span class="visually-hidden"><?= e(full_name($member)) ?></span></a>
<?php endforeach; ?>
<?php if ($memberCount > count($shown)): ?>
    <span class="more-members">+<?= number_format($memberCount - count($shown)) ?> more</span>
<?php endif; ?>
  </div>
<?php endif; ?>
</section>

<h2 class="section-title feed-head">Posts</h2>
<?php if ($role !== null): ?>
<?= partial('composer', ['groupId' => $id]) ?>
<?php endif; ?>
<div class="post-list" id="posts">
<?php if ($posts): ?>
<?= partial('post-list', ['posts' => $posts, 'next' => $next, 'inGroup' => true]) ?>
<?php else: ?>
  <div class="card js-feed-empty">
    <?= partial('empty', [
        'icon' => 'edit',
        'title' => 'No posts yet',
        'text' => $role !== null ? 'Start the conversation with the first post.' : 'Join the group to post here.',
        'level' => 3,
    ]) ?>
  </div>
<?php endif; ?>
</div>
