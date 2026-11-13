<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Friendship;
use App\Models\Notification;
use App\Models\User;

final class FriendController
{
    private const TABS = ['friends', 'requests', 'sent', 'suggestions'];

    public function index(): void
    {
        $me = (int) Auth::id();
        $incoming = Friendship::incoming($me);
        $tab = in_array($_GET['tab'] ?? '', self::TABS, true) ? $_GET['tab'] : ($incoming ? 'requests' : 'friends');
        $lists = [
            'friends' => Friendship::friends($me),
            'requests' => $incoming,
            'sent' => Friendship::outgoing($me),
            'suggestions' => User::suggestions($me, 24),
        ];
        View::render('pages/friends', [
            'title' => 'Friends',
            'active' => 'friends',
            'tab' => $tab,
            'lists' => $lists,
        ]);
    }

    /** One endpoint for add, cancel, accept, decline and remove. */
    public function update(int $id): void
    {
        $me = (int) Auth::id();
        $other = User::find($id);
        if ($other === null || $id === $me) {
            fail('That person could not be found.', 404);
        }
        $name = $other['first_name'];
        $action = (string) ($_POST['action'] ?? '');
        $current = Friendship::status($me, $id);

        switch ($action) {
            case 'add':
                $status = Friendship::send($me, $id);
                if ($current === Friendship::INCOMING) {
                    Notification::remove($me, $id, 'friend_request');
                    Notification::add($id, $me, 'friend_accept');
                    $message = "You and $name are now friends.";
                } else {
                    if ($status === Friendship::OUTGOING && $current === Friendship::NONE) {
                        Notification::add($id, $me, 'friend_request');
                    }
                    $message = "Friend request sent to $name.";
                }
                break;
            case 'accept':
                if (!Friendship::accept($me, $id)) {
                    fail('That request is no longer waiting.', 409);
                }
                Notification::remove($me, $id, 'friend_request');
                Notification::add($id, $me, 'friend_accept');
                $message = "You and $name are now friends.";
                break;
            case 'cancel':
            case 'decline':
            case 'remove':
                Friendship::remove($me, $id);
                Notification::remove($id, $me, 'friend_request');
                Notification::remove($me, $id, 'friend_request');
                $message = ['cancel' => 'Request cancelled.', 'decline' => 'Request declined.', 'remove' => "$name was removed from your friends."][$action];
                break;
            default:
                fail('Unknown action.', 400);
        }

        $status = Friendship::status($me, $id);
        $context = in_array($_POST['context'] ?? '', ['profile', 'list', 'compact'], true) ? $_POST['context'] : 'list';
        done([
            'status' => $status,
            'html' => partial('friend-actions', ['person' => $other, 'status' => $status, 'context' => $context]),
        ], $message);
    }
}
