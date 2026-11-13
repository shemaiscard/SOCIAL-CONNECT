<div class="page-head">
  <a class="icon-btn back" href="<?= e(back('/')) ?>" aria-label="Back"><?= icon('arrow-left') ?></a>
  <h1>Post</h1>
</div>
<?= partial('post', ['post' => $post, 'full' => true]) ?>
