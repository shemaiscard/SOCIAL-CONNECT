<?php
$row = static function (array $group, bool $member): string {
    $id = (int) $group['id'];
    $meta = [plural((int) $group['member_count'], 'member'), plural((int) $group['post_count'], 'post')];
    ob_start();
    ?>
    <li class="group-row">
      <span class="group-tile av-<?= $id % 8 ?>" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($group['name'], 0, 1))) ?></span>
      <div class="person-main">
        <a class="person-name" href="<?= e(url('/groups/' . $id)) ?>"><?= e($group['name']) ?></a>
<?php if (($group['description'] ?? '') !== ''): ?>
        <p class="person-sub"><?= e(excerpt($group['description'], 90)) ?></p>
<?php endif; ?>
        <p class="person-sub"><?= e(implode(' · ', $meta)) ?></p>
      </div>
<?php if ($member): ?>
<?php if ($group['my_role'] === 'owner'): ?>
      <span class="role-tag">Owner</span>
<?php endif; ?>
<?php else: ?>
      <form method="post" action="<?= e(url('/groups/' . $id . '/join')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-secondary" type="submit"><?= icon('plus') ?><span>Join</span></button>
      </form>
<?php endif; ?>
    </li>
    <?php
    return (string) ob_get_clean();
};
?>
<div class="page-head">
  <h1>Groups</h1>
  <button class="btn btn-primary" type="button" data-open-dialog="create-group"><?= icon('plus') ?><span>New group</span></button>
</div>

<section class="result-section" aria-labelledby="mine-title">
  <h2 class="section-title feed-head" id="mine-title">Your groups</h2>
  <div class="card">
<?php if ($mine): ?>
    <ul><?php foreach ($mine as $group) echo $row($group, true); ?></ul>
<?php else: ?>
    <?= partial('empty', [
        'icon' => 'layers',
        'title' => 'You are not in any groups',
        'text' => 'Groups keep posts about one topic in one place. Join one below or start your own.',
        'level' => 3,
    ]) ?>
<?php endif; ?>
  </div>
</section>

<section class="result-section" aria-labelledby="discover-title">
  <h2 class="section-title feed-head" id="discover-title">Discover</h2>
  <div class="card">
<?php if ($discover): ?>
    <ul><?php foreach ($discover as $group) echo $row($group, false); ?></ul>
<?php else: ?>
    <?= partial('empty', [
        'icon' => 'search',
        'title' => 'You have joined every group',
        'text' => 'New groups appear here when someone creates one.',
        'level' => 3,
    ]) ?>
<?php endif; ?>
  </div>
</section>

<dialog class="dialog dialog-wide" id="create-group" aria-labelledby="create-group-title"<?= flashed('open_create') ? ' data-open-on-load' : '' ?>>
  <form class="dialog-body" method="post" action="<?= e(url('/groups')) ?>" novalidate>
    <?= csrf_field() ?>
    <h2 class="dialog-title" id="create-group-title">New group</h2>
    <p class="dialog-text">Anyone can find and join it. Members can post to the group.</p>
    <div class="stack-lg">
      <div class="field">
        <label class="label" for="group-name">Name</label>
        <input class="input" id="group-name" name="name" value="<?= e(old('name')) ?>" minlength="3" maxlength="80" required autocomplete="off"<?= invalid_attrs('name', 'group-name') ?>>
        <?= error_for('name', 'group-name') ?>
      </div>
      <div class="field">
        <label class="label" for="group-description">Description <span class="label-hint">Optional</span></label>
        <textarea class="textarea js-autosize" id="group-description" name="description" rows="3" maxlength="500"<?= invalid_attrs('description', 'group-description') ?>><?= e(old('description')) ?></textarea>
        <?= error_for('description', 'group-description') ?>
      </div>
    </div>
    <div class="dialog-actions">
      <button class="btn btn-secondary" type="button" data-close-dialog>Cancel</button>
      <button class="btn btn-primary" type="submit">Create group</button>
    </div>
  </form>
</dialog>
