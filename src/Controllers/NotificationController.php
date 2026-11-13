<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Session;
use App\Core\View;
use App\Models\Notification;

final class NotificationController
{
    public function index(): void
    {
        $me = (int) Auth::id();
        $items = Notification::forUser($me);
        // Render first so this visit still highlights what was new, then clear the badge.
        View::render('pages/notifications', [
            'title' => 'Notifications',
            'active' => 'notifications',
            'items' => $items,
        ]);
        Notification::markAllRead($me);
    }

    public function readAll(): void
    {
        Notification::markAllRead((int) Auth::id());
        done([], 'All notifications marked as read.');
    }

    /** Badge counts, polled by app.js. */
    public function counts(): void
    {
        $me = (int) Auth::id();
        Session::release();
        json(Notification::counts($me));
    }
}
