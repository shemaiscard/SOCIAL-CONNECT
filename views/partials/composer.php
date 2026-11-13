<?php

use App\Core\Auth;

/** New post form. Optional $groupId posts into a group. */
$me = Auth::user();
$groupId = $groupId ?? null;
?>
<form class="card composer js-composer" method="post" action="<?= e(url('/posts')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
<?php if ($groupId): ?>
  <input type="hidden" name="group_id" value="<?= (int) $groupId ?>">
<?php endif; ?>
  <div class="composer-row">
    <?= avatar($me) ?>
    <label class="visually-hidden" for="composer-text"><?= $groupId ? 'Write something to the group' : 'Write a post' ?></label>
    <textarea class="composer-input js-autosize" id="composer-text" name="body" rows="2" maxlength="6000" data-max="5000"
              placeholder="<?= e($groupId ? 'Write something to the group' : 'What is new, ' . $me['first_name'] . '?') ?>"></textarea>
  </div>
  <div class="composer-preview" hidden>
    <img alt="Preview of the photo you picked">
    <button class="icon-btn js-remove-image" type="button" aria-label="Remove photo"><?= icon('x') ?></button>
  </div>
  <div class="composer-bar">
    <label class="btn btn-ghost file-btn">
      <?= icon('image') ?><span>Photo</span>
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
    </label>
<?php if (!$groupId): ?>
    <label class="visually-hidden" for="composer-visibility">Who can see this post</label>
    <select class="select select-sm" id="composer-visibility" name="visibility">
      <option value="friends">Friends</option>
      <option value="public">Public</option>
    </select>
<?php endif; ?>
    <span class="counter" data-counter-for="composer-text" aria-live="polite"></span>
    <button class="btn btn-primary" type="submit">Post</button>
  </div>
</form>
