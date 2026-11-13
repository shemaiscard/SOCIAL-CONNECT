<?php
/** One chat message. Expects $message, $me (my id); optional $showTime. */
$mine = (int) $message['sender_id'] === (int) $me;
?>
<div class="msg <?= $mine ? 'msg-mine' : 'msg-theirs' ?>" data-id="<?= (int) $message['id'] ?>">
  <p class="bubble"><?= format_body($message['body']) ?></p>
<?php if (!empty($showTime)): ?>
  <span class="msg-time"><?= time_tag($message['created_at']) ?><?= $mine && !empty($message['read_at']) ? ' · Seen' : '' ?></span>
<?php endif; ?>
</div>
