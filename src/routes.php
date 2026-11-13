<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\FeedController;
use App\Controllers\FriendController;
use App\Controllers\GroupController;
use App\Controllers\MessageController;
use App\Controllers\NotificationController;
use App\Controllers\PostController;
use App\Controllers\ProfileController;
use App\Controllers\SearchController;
use App\Controllers\SettingsController;
use App\Core\Router;

// Routes are signed-in only unless marked 'guest' or 'public'.
return static function (Router $r): void {
    $r->get('/login', [AuthController::class, 'showLogin'], 'guest');
    $r->post('/login', [AuthController::class, 'login'], 'guest');
    $r->get('/register', [AuthController::class, 'showRegister'], 'guest');
    $r->post('/register', [AuthController::class, 'register'], 'guest');
    $r->get('/forgot-password', [AuthController::class, 'showForgot'], 'guest');
    $r->post('/forgot-password', [AuthController::class, 'forgot'], 'guest');
    $r->get('/reset-password', [AuthController::class, 'showReset'], 'guest');
    $r->post('/reset-password', [AuthController::class, 'reset'], 'guest');
    $r->post('/logout', [AuthController::class, 'logout'], 'public');

    $r->get('/', [FeedController::class, 'index']);

    $r->post('/posts', [PostController::class, 'store']);
    $r->get('/posts/{id}', [PostController::class, 'show']);
    $r->post('/posts/{id}/delete', [PostController::class, 'destroy']);
    $r->post('/posts/{id}/like', [PostController::class, 'like']);
    $r->post('/posts/{id}/save', [PostController::class, 'save']);
    $r->post('/posts/{id}/report', [PostController::class, 'report']);
    $r->post('/posts/{id}/comments', [PostController::class, 'comment']);
    $r->post('/comments/{id}/delete', [PostController::class, 'destroyComment']);
    $r->get('/saved', [PostController::class, 'saved']);

    $r->get('/u/{id}', [ProfileController::class, 'show']);

    $r->get('/settings', [SettingsController::class, 'show']);
    $r->post('/settings/profile', [SettingsController::class, 'profile']);
    $r->post('/settings/avatar', [SettingsController::class, 'avatar']);
    $r->post('/settings/cover', [SettingsController::class, 'cover']);
    $r->post('/settings/email', [SettingsController::class, 'email']);
    $r->post('/settings/password', [SettingsController::class, 'password']);

    $r->get('/friends', [FriendController::class, 'index']);
    $r->post('/friends/{id}', [FriendController::class, 'update']);

    $r->get('/messages', [MessageController::class, 'index']);
    $r->get('/messages/{id}', [MessageController::class, 'show']);
    $r->post('/messages/{id}', [MessageController::class, 'send']);
    $r->get('/messages/{id}/poll', [MessageController::class, 'poll']);

    $r->get('/notifications', [NotificationController::class, 'index']);
    $r->post('/notifications/read', [NotificationController::class, 'readAll']);
    $r->get('/api/counts', [NotificationController::class, 'counts']);

    $r->get('/groups', [GroupController::class, 'index']);
    $r->post('/groups', [GroupController::class, 'store']);
    $r->get('/groups/{id}', [GroupController::class, 'show']);
    $r->post('/groups/{id}/join', [GroupController::class, 'join']);
    $r->post('/groups/{id}/leave', [GroupController::class, 'leave']);
    $r->post('/groups/{id}/delete', [GroupController::class, 'destroy']);

    $r->get('/search', [SearchController::class, 'index']);
};
