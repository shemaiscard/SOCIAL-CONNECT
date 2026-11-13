<?php

use App\Core\Auth;

/** One post card. Expects $post; optional $inGroup (hide the group name) and $full (all comments). */
$me = (int) Auth::id();
$id = (int) $post['id'];
$mine = (int) $post['user_id'] === $me;
$postUrl = url('/posts/' . $id);
$profileUrl = url('/u/' . $post['user_id']);
$comments = $post['comments'] ?? [];
$commentTotal = (int) ($post['comment_count'] ?? 0);
$likeTotal = (int) ($post['like_count'] ?? 0);
$liked = !empty($post['liked']);
$saved = !empty($post['saved']);
$full = !empty($full);
$showGroup = !empty($post['group_id']) && empty($inGroup);
$public = ($post['visibility'] ?? 'friends') === 'public';
?>
<article class="post" id="post-<?= $id ?>" aria-labelledby="post-<?= $id ?>-author">
  <header class="post-head">
    <a href="<?= e($profileUrl) ?>" tabindex="-1" aria-hidden="true"><?= avatar(author($post)) ?></a>
    <div class="post-meta">
      <div>
        <a class="post-author" id="post-<?= $id ?>-author" href="<?= e($profileUrl) ?>"><?= e(full_name($post)) ?></a>
<?php if ($showGroup): ?>
        <span class="post-group-sep" aria-hidden="true">in</span><a class="post-group" href="<?= e(url('/groups/' . $post['group_id'])) ?>"><?= e($post['group_name']) ?></a>
<?php endif; ?>
      </div>
      <div class="post-sub">
        <a href="<?= e($postUrl) ?>"><?= time_tag($post['created_at']) ?></a>
        <span aria-hidden="true">·</span>
<?php if (!empty($post['group_id'])): ?>
        <span title="Group members"><?= icon('layers') ?><span class="visually-hidden">Visible to group members</span></span>
<?php elseif ($public): ?>
        <span title="Public"><?= icon('globe') ?><span class="visually-hidden">Public</span></span>
<?php else: ?>
        <span title="Friends"><?= icon('users') ?><span class="visually-hidden">Friends only</span></span>
<?php endif; ?>
      </div>
    </div>
    <button class="icon-btn" type="button" popovertarget="post-menu-<?= $id ?>" aria-label="Post options"><?= icon('more-horizontal') ?></button>
    <div class="menu" id="post-menu-<?= $id ?>" popover>
      <button class="menu-item js-share" type="button" data-url="<?= e(absolute_url('/posts/' . $id)) ?>"><?= icon('link') ?>Copy link</button>
<?php if (!$full): ?>
      <a class="menu-item" href="<?= e($postUrl) ?>"><?= icon('message-circle') ?>Open post</a>
<?php endif; ?>
      <div class="menu-sep" role="separator"></div>
<?php if ($mine): ?>
      <form class="js-delete-post" method="post" action="<?= e(url('/posts/' . $id . '/delete')) ?>"
            data-confirm="Delete this post?" data-confirm-text="The post, its photo, likes and comments are removed for everyone. This can not be undone."
            data-confirm-label="Delete post" data-confirm-tone="danger">
        <?= csrf_field() ?>
        <button class="menu-item menu-item-danger" type="submit"><?= icon('trash-2') ?>Delete post</button>
      </form>
<?php else: ?>
      <form class="js-report" method="post" action="<?= e(url('/posts/' . $id . '/report')) ?>"
            data-confirm="Report this post" data-confirm-text="Pick the reason that fits best. The author is not told who reported it."
            data-confirm-label="Send report" data-confirm-reasons>
        <?= csrf_field() ?>
        <input type="hidden" name="reason" value="other">
        <button class="menu-item" type="submit"><?= icon('flag') ?>Report post</button>
      </form>
<?php endif; ?>
    </div>
  </header>

<?php if (($post['body'] ?? '') !== ''): ?>
  <div class="post-body"><p class="post-text"><?= format_body($post['body']) ?></p></div>
<?php endif; ?>
<?php if (!empty($post['image_path'])): ?>
  <a class="post-image" href="<?= e($postUrl) ?>">
    <img src="<?= e(media_url($post['image_path'])) ?>" alt="Photo shared by <?= e(full_name($post)) ?>"<?php if (!empty($post['image_w']) && !empty($post['image_h'])): ?> width="<?= (int) $post['image_w'] ?>" height="<?= (int) $post['image_h'] ?>"<?php endif; ?> loading="lazy" decoding="async">
  </a>
<?php endif; ?>

  <div class="post-actions">
    <form class="js-like" method="post" action="<?= e(url('/posts/' . $id . '/like')) ?>">
      <?= csrf_field() ?>
      <button class="action action-like" type="submit" aria-pressed="<?= $liked ? 'true' : 'false' ?>"><?= icon('heart') ?><span class="visually-hidden">Like</span><span class="count"><?= $likeTotal ?: '' ?></span></button>
    </form>
    <a class="action js-focus-comment" href="<?= e($postUrl) ?>#comment-form"><?= icon('message-circle') ?><span class="visually-hidden">Comments</span><span class="count js-comment-count"><?= $commentTotal ?: '' ?></span></a>
    <button class="action js-share" type="button" data-url="<?= e(absolute_url('/posts/' . $id)) ?>"><?= icon('share-2') ?><span class="visually-hidden">Share</span></button>
    <form class="js-save" method="post" action="<?= e(url('/posts/' . $id . '/save')) ?>">
      <?= csrf_field() ?>
      <button class="action action-save action-end" type="submit" aria-pressed="<?= $saved ? 'true' : 'false' ?>" aria-label="<?= $saved ? 'Remove from saved' : 'Save post' ?>" title="<?= $saved ? 'Remove from saved' : 'Save post' ?>"><?= icon('bookmark') ?></button>
    </form>
  </div>

  <section class="comments-area" aria-label="Comments"<?= $full ? ' id="comments"' : '' ?>>
<?php if (!$full && $commentTotal > count($comments)): ?>
    <a class="more-comments link-btn" href="<?= e($postUrl) ?>#comments">View all <?= $commentTotal ?> comments</a>
<?php endif; ?>
    <ul class="comment-list"><?php foreach ($comments as $comment) { echo partial('comment', ['comment' => $comment, 'postOwner' => (int) $post['user_id']]); } ?></ul>
    <form class="comment-form js-comment" method="post" action="<?= e(url('/posts/' . $id . '/comments')) ?>"<?= $full ? ' id="comment-form"' : '' ?>>
      <?= csrf_field() ?>
      <?= avatar(Auth::user(), 'sm') ?>
      <div class="comment-input-wrap">
        <label class="visually-hidden" for="comment-<?= $id ?>">Write a comment</label>
        <textarea class="comment-input js-autosize js-enter-sends" id="comment-<?= $id ?>" name="body" rows="1" maxlength="1000" placeholder="Write a comment" required></textarea>
        <button class="icon-btn comment-send" type="submit" aria-label="Post comment"><?= icon('send') ?></button>
      </div>
    </form>
  </section>
</article>
