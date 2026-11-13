<?php

use App\Core\Auth;

/** One comment. Expects $comment; $postOwner is the post author's id (or comment.post_owner_id). */
$me = (int) Auth::id();
$owner = (int) ($postOwner ?? $comment['post_owner_id'] ?? 0);
$canDelete = $me === (int) $comment['user_id'] || $me === $owner;
$profileUrl = url('/u/' . $comment['user_id']);
?>
<li class="comment" id="comment-<?= (int) $comment['id'] ?>">
  <a href="<?= e($profileUrl) ?>" tabindex="-1" aria-hidden="true"><?= avatar(author($comment), 'sm') ?></a>
  <div class="comment-main">
    <div class="comment-bubble">
      <a class="comment-author" href="<?= e($profileUrl) ?>"><?= e(full_name($comment)) ?></a>
      <p class="comment-text"><?= format_body($comment['body']) ?></p>
    </div>
    <div class="comment-meta">
      <?= time_tag($comment['created_at']) ?>
<?php if ($canDelete): ?>
      <form class="js-delete-comment" method="post" action="<?= e(url('/comments/' . $comment['id'] . '/delete')) ?>"
            data-confirm="Delete this comment?" data-confirm-label="Delete comment" data-confirm-tone="danger">
        <?= csrf_field() ?>
        <button class="link-btn" type="submit">Delete</button>
      </form>
<?php endif; ?>
    </div>
  </div>
</li>
