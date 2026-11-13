<?php

use App\Models\Friendship;

/**
 * Friend buttons for one person. Expects $person, $status (Friendship constant) and $context:
 * profile (large buttons), list (rows on the friends and search pages) or compact (icon buttons in the sidebar).
 */
$context = $context ?? 'list';
$id = (int) $person['id'];
$name = full_name($person);
$action = e(url('/friends/' . $id));
$compact = $context === 'compact';

$button = static function (string $do, string $label, string $iconName, string $style, array $confirm = []) use ($action, $context, $compact, $name): string {
    $attrs = '';
    foreach ($confirm as $key => $value) {
        $attrs .= ' data-confirm' . ($key === '' ? '' : '-' . $key) . '="' . e($value) . '"';
    }
    $html = '<form class="js-friend" method="post" action="' . $action . '"' . $attrs . '>' . csrf_field()
        . '<input type="hidden" name="action" value="' . e($do) . '">'
        . '<input type="hidden" name="context" value="' . e($context) . '">';
    if ($compact) {
        $html .= '<button class="icon-btn" type="submit" aria-label="' . e("$label: $name") . '" title="' . e($label) . '">' . icon($iconName) . '</button>';
    } else {
        $html .= '<button class="btn ' . $style . '" type="submit">' . icon($iconName) . '<span>' . e($label) . '</span></button>';
    }
    return $html . '</form>';
};
?>
<div class="friend-actions">
<?php if ($status === Friendship::NONE): ?>
  <?= $button('add', 'Add friend', 'user-plus', $context === 'profile' ? 'btn-primary' : 'btn-secondary') ?>
<?php elseif ($status === Friendship::OUTGOING): ?>
  <?= $button('cancel', 'Cancel request', $compact ? 'x' : 'user-x', 'btn-secondary') ?>
<?php elseif ($status === Friendship::INCOMING): ?>
  <?= $button('accept', 'Accept', 'check', 'btn-primary') ?>
  <?= $button('decline', 'Decline', 'x', 'btn-secondary') ?>
<?php elseif ($status === Friendship::FRIENDS): ?>
<?php if ($compact): ?>
  <a class="icon-btn" href="<?= e(url('/messages/' . $id)) ?>" aria-label="Message <?= e($name) ?>" title="Message"><?= icon('message-square') ?></a>
<?php else: ?>
  <a class="btn <?= $context === 'profile' ? 'btn-primary' : 'btn-secondary' ?>" href="<?= e(url('/messages/' . $id)) ?>"><?= icon('message-square') ?><span>Message</span></a>
  <?= $button('remove', 'Unfriend', 'user-x', 'btn-ghost', [
      '' => "Remove $name from your friends?",
      'text' => 'You will stop seeing each other\'s friends-only posts and can not message each other.',
      'label' => 'Unfriend',
      'tone' => 'danger',
  ]) ?>
<?php endif; ?>
<?php elseif ($context === 'profile'): ?>
  <a class="btn btn-secondary" href="<?= e(url('/settings')) ?>"><?= icon('edit-2') ?><span>Edit profile</span></a>
<?php endif; ?>
</div>
