<?php

use App\Models\Friendship;

?>
<h1 class="visually-hidden">Home feed</h1>
<?= partial('composer') ?>
<div class="post-list" id="posts">
<?php if ($posts): ?>
<?= partial('post-list', ['posts' => $posts, 'next' => $next, 'paged' => $paged]) ?>
<?php else: ?>
  <div class="card js-feed-empty">
<?php
    $people = '';
    foreach ($suggestions as $person) {
        $people .= partial('person', [
            'person' => $person,
            'sub' => (int) $person['mutual'] > 0 ? plural((int) $person['mutual'], 'mutual friend') : null,
            'actions' => partial('friend-actions', ['person' => $person, 'status' => Friendship::NONE, 'context' => 'list']),
        ]);
    }
?>
    <?= partial('empty', [
        'icon' => 'users',
        'title' => 'Your feed is quiet',
        'text' => 'Posts from you, your friends and your groups show up here. Write the first one above, or add a few friends.',
        'action' => $suggestions ? '' : '<a class="btn btn-primary" href="' . e(url('/friends?tab=suggestions')) . '">Find friends</a>',
    ]) ?>
<?php if ($people !== ''): ?>
    <div class="card-pad">
      <h2 class="section-title">People you may know</h2>
      <ul class="person-list"><?= $people ?></ul>
    </div>
<?php endif; ?>
  </div>
<?php endif; ?>
</div>
