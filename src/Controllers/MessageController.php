<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Session;
use App\Core\View;
use App\Models\Friendship;
use App\Models\Message;
use App\Models\User;

final class MessageController
{
    private const MAX_LENGTH = 2000;

    public function index(): void
    {
        $me = (int) Auth::id();
        $conversations = Message::conversations($me);
        View::render('pages/messages', [
            'title' => 'Messages',
            'active' => 'messages',
            'wide' => true,
            'conversations' => $conversations,
            'startable' => $this->friendsWithoutThread($me, $conversations),
            'other' => null,
        ]);
    }

    public function show(int $id): void
    {
        $me = (int) Auth::id();
        if ($id === $me) {
            redirect('/messages');
        }
        $other = User::find($id);
        if ($other === null) {
            abort(404);
        }
        Message::markRead($me, $id);
        $conversations = Message::conversations($me);
        View::render('pages/messages', [
            'title' => 'Chat with ' . full_name($other),
            'active' => 'messages',
            'wide' => true,
            'conversations' => $conversations,
            'startable' => $this->friendsWithoutThread($me, $conversations),
            'other' => $other,
            'thread' => Message::thread($me, $id),
            'canSend' => $this->canMessage($me, $id),
        ]);
    }

    public function send(int $id): void
    {
        $me = (int) Auth::id();
        if ($id === $me || User::find($id) === null) {
            fail('That person could not be found.', 404);
        }
        if (!$this->canMessage($me, $id)) {
            fail('You can message people once you are friends.', 403);
        }
        $body = input('body');
        if ($body === '') {
            fail('Write a message first.');
        }
        if (mb_strlen($body) > self::MAX_LENGTH) {
            fail('Messages can be up to 2,000 characters.');
        }
        $messageId = Message::send($me, $id, $body);
        done([
            'id' => $messageId,
            'html' => partial('message', ['message' => Message::find($messageId), 'me' => $me, 'showTime' => true]),
        ]);
    }

    /** Messages newer than ?after=, polled by app.js every few seconds while the chat is open. */
    public function poll(int $id): void
    {
        $me = (int) Auth::id();
        Session::release();
        $after = query_int('after') ?? 0;
        $messages = Message::thread($me, $id, $after);
        if ($messages) {
            Message::markRead($me, $id);
        }
        $html = '';
        foreach ($messages as $message) {
            $html .= partial('message', ['message' => $message, 'me' => $me, 'showTime' => true]);
        }
        json(['html' => $html, 'last_id' => $messages ? (int) end($messages)['id'] : $after]);
    }

    /** Friends can always message; an existing conversation stays open after unfriending so it can be read. */
    private function canMessage(int $me, int $other): bool
    {
        return Friendship::areFriends($me, $other);
    }

    private function friendsWithoutThread(int $me, array $conversations): array
    {
        $talked = array_column($conversations, 'id');
        return array_values(array_filter(
            Friendship::friends($me),
            static fn (array $friend) => !in_array($friend['id'], $talked, true)
        ));
    }
}
