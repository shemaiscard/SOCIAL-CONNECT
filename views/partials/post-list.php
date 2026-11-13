<?php
/** Posts plus the "load more" link. Rendered inside #posts, and alone for fragment requests. */
foreach ($posts as $post) {
    echo partial('post', ['post' => $post, 'inGroup' => $inGroup ?? false]);
}
?>
<?php if (!empty($next)): ?>
<div class="more"><a class="btn btn-secondary js-more" href="<?= e(with_query(['before' => $next])) ?>">Show older posts</a></div>
<?php elseif ($posts && (is_fragment() || !empty($paged))): ?>
<p class="end-note">You have reached the first post.</p>
<?php endif; ?>
