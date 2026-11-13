<div class="toasts" id="toasts" role="status" aria-live="polite">
<?php if ($message = flashed('toast')): ?>
  <div class="toast"><?= icon('check') ?><span><?= e($message) ?></span></div>
<?php endif; ?>
<?php if ($message = flashed('toast_error')): ?>
  <div class="toast toast-error"><?= icon('alert-circle') ?><span><?= e($message) ?></span></div>
<?php endif; ?>
</div>
