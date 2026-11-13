<?php
/** Empty state. Expects $icon, $title; optional $text, $action (trusted HTML), $level (heading level). */
$level = $level ?? 2;
?>
<div class="empty">
  <span class="empty-icon"><?= icon($icon) ?></span>
  <h<?= (int) $level ?>><?= e($title) ?></h<?= (int) $level ?>>
<?php if (!empty($text)): ?>
  <p><?= e($text) ?></p>
<?php endif; ?>
<?= $action ?? '' ?>
</div>
