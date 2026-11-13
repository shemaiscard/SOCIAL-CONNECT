<div class="error-page">
  <p class="error-code">Error <?= (int) $status ?></p>
  <h1 class="auth-title"><?= e($title) ?></h1>
  <p class="auth-lead"><?= e($message) ?></p>
  <div class="form-actions">
    <a class="btn btn-primary" href="<?= e(url('/')) ?>"><?= icon('home') ?><span>Go to the home page</span></a>
  </div>
</div>
