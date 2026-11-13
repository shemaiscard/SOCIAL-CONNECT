<?php

use App\Core\Auth;

$myId = (int) Auth::id();
$otherId = $other ? (int) $other['id'] : 0;
?>
<div class="card chat<?= $other ? ' has-thread' : '' ?>">
  <section class="chat-list" aria-labelledby="chat-title">
    <div class="chat-list-head"><h1 id="chat-title">Messages</h1></div>
    <div class="chat-list-body">
<?php if ($conversations): ?>
      <ul>
<?php foreach ($conversations as $convo): ?>
<?php
    $unread = (int) $convo['unread'];
    $prefix = (int) $convo['sender_id'] === $myId ? 'You: ' : '';
?>
        <li>
          <a class="convo<?= $unread > 0 ? ' is-unread' : '' ?>" href="<?= e(url('/messages/' . $convo['id'])) ?>"<?= current_attr((int) $convo['id'] === $otherId) ?>>
            <span class="avatar-wrap"><?= avatar($convo) ?><?php if (is_online($convo['last_seen_at'])): ?><span class="online-dot" title="Online"></span><?php endif; ?></span>
            <span class="convo-main">
              <span class="convo-top">
                <span class="convo-name"><?= e(full_name($convo)) ?></span>
                <span class="convo-time"><?= time_tag($convo['created_at']) ?></span>
              </span>
              <span class="convo-last">
                <span><?= e($prefix . excerpt($convo['body'], 60)) ?></span>
<?php if ($unread > 0): ?>
                <span class="badge"><span class="visually-hidden">Unread: </span><?= $unread > 99 ? '99+' : $unread ?></span>
<?php endif; ?>
              </span>
            </span>
          </a>
        </li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
<?php if ($startable): ?>
      <h2 class="chat-list-label">Start a conversation</h2>
      <ul>
<?php foreach ($startable as $friend): ?>
        <li>
          <a class="convo" href="<?= e(url('/messages/' . $friend['id'])) ?>"<?= current_attr((int) $friend['id'] === $otherId) ?>>
            <?= avatar($friend) ?>
            <span class="convo-main"><span class="convo-name"><?= e(full_name($friend)) ?></span></span>
          </a>
        </li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
<?php if (!$conversations && !$startable): ?>
      <?= partial('empty', [
          'icon' => 'message-circle',
          'title' => 'No conversations',
          'text' => 'You can message anyone on your friends list.',
          'action' => '<a class="btn btn-primary" href="' . e(url('/friends?tab=suggestions')) . '">Find friends</a>',
          'level' => 2,
      ]) ?>
<?php endif; ?>
    </div>
  </section>

  <section class="thread<?= $other ? ' js-thread' : '' ?>"<?php if ($other): ?> aria-labelledby="thread-title" data-poll="<?= e(url('/messages/' . $otherId . '/poll')) ?>" data-last-id="<?= $thread ? (int) end($thread)['id'] : 0 ?>"<?php endif; ?>>
<?php if ($other): ?>
<?php $online = is_online($other['last_seen_at']); ?>
    <header class="thread-head">
      <a class="icon-btn back" href="<?= e(url('/messages')) ?>" aria-label="All conversations"><?= icon('arrow-left') ?></a>
      <a href="<?= e(url('/u/' . $otherId)) ?>" tabindex="-1" aria-hidden="true"><?= avatar($other) ?></a>
      <div class="thread-who">
        <h2 id="thread-title"><a href="<?= e(url('/u/' . $otherId)) ?>"><?= e(full_name($other)) ?></a></h2>
        <p class="thread-status<?= $online ? ' is-online' : '' ?>"><?= e(last_seen_label($other['last_seen_at'])) ?></p>
      </div>
    </header>
    <div class="thread-body">
      <div class="thread-inner" role="log" aria-live="polite" aria-label="Messages with <?= e($other['first_name']) ?>">
<?php if ($thread): ?>
<?php
    $lastDay = '';
    $count = count($thread);
    foreach ($thread as $i => $message):
        $day = gmdate('Y-m-d', db_time($message['created_at']));
        if ($day !== $lastDay):
            $lastDay = $day;
            $label = match ($day) {
                gmdate('Y-m-d') => 'Today',
                gmdate('Y-m-d', time() - 86400) => 'Yesterday',
                default => gmdate(gmdate('Y') === substr($day, 0, 4) ? 'D, M j' : 'M j, Y', db_time($message['created_at'])),
            };
?>
        <p class="day"><?= e($label) ?></p>
<?php
        endif;
        $next = $thread[$i + 1] ?? null;
        // Show the time under the last message of each run from one sender.
        $showTime = $next === null
            || (int) $next['sender_id'] !== (int) $message['sender_id']
            || db_time($next['created_at']) - db_time($message['created_at']) > 600;
?>
        <?= partial('message', ['message' => $message, 'me' => $myId, 'showTime' => $showTime]) ?>
<?php endforeach; ?>
<?php else: ?>
        <div class="thread-empty js-thread-empty">
          <?= partial('empty', [
              'icon' => 'message-circle',
              'title' => 'Say hello to ' . $other['first_name'],
              'text' => $canSend ? 'Messages are private between the two of you.' : null,
              'level' => 3,
          ]) ?>
        </div>
<?php endif; ?>
      </div>
    </div>
<?php if ($canSend): ?>
    <form class="thread-form comment-form js-message" method="post" action="<?= e(url('/messages/' . $otherId)) ?>">
      <?= csrf_field() ?>
      <div class="comment-input-wrap">
        <label class="visually-hidden" for="message-body">Message <?= e($other['first_name']) ?></label>
        <textarea class="comment-input js-autosize js-enter-sends" id="message-body" name="body" rows="1" maxlength="2000" placeholder="Message <?= e($other['first_name']) ?>" required></textarea>
      </div>
      <button class="btn btn-primary" type="submit"><?= icon('send') ?><span class="visually-hidden">Send</span></button>
    </form>
<?php else: ?>
    <p class="thread-note">You can send messages once you and <?= e($other['first_name']) ?> are friends.</p>
<?php endif; ?>
<?php else: ?>
    <div class="thread-empty">
      <?= partial('empty', [
          'icon' => 'message-square',
          'title' => 'Pick a conversation',
          'text' => 'Choose someone on the left to read and reply.',
          'level' => 2,
      ]) ?>
    </div>
<?php endif; ?>
  </section>
</div>
