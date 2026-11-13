<div class="page-head"><h1>Saved</h1></div>
<div class="post-list" id="posts">
<?php if ($posts): ?>
<?= partial('post-list', ['posts' => $posts, 'next' => $next]) ?>
<?php else: ?>
  <div class="card js-feed-empty">
    <?= partial('empty', [
        'icon' => 'bookmark',
        'title' => 'No saved posts',
        'text' => 'Tap the bookmark on any post to keep it here for later. Only you can see what you save.',
        'action' => '<a class="btn btn-secondary" href="' . e(url('/')) . '">Go to your feed</a>',
    ]) ?>
  </div>
<?php endif; ?>
</div>
