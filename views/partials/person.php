<?php
/** One person row. Expects $person; optional $sub (text), $nameHtml and $actions (trusted HTML), $size. */
$profileUrl = url('/u/' . $person['id']);
?>
<li class="person">
  <a class="avatar-wrap" href="<?= e($profileUrl) ?>" tabindex="-1" aria-hidden="true">
    <?= avatar($person, $size ?? 'md') ?>
<?php if (is_online($person['last_seen_at'] ?? null)): ?>
    <span class="online-dot"></span>
<?php endif; ?>
  </a>
  <div class="person-main">
    <a class="person-name" href="<?= e($profileUrl) ?>"><?= $nameHtml ?? e(full_name($person)) ?></a>
<?php if (!empty($sub)): ?>
    <p class="person-sub"><?= e($sub) ?></p>
<?php endif; ?>
  </div>
<?= $actions ?? '' ?>
</li>
