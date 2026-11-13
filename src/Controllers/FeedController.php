<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Post;
use App\Models\User;

final class FeedController
{
    public function index(): void
    {
        $me = (int) Auth::id();
        [$posts, $next] = Post::feed($me, query_int('before'));
        if (is_fragment()) {
            echo partial('post-list', ['posts' => $posts, 'next' => $next]);
            return;
        }
        View::render('pages/feed', [
            'title' => 'Home',
            'active' => 'home',
            'posts' => $posts,
            'next' => $next,
            'paged' => query_int('before') !== null,
            'suggestions' => $posts ? [] : User::suggestions($me, 4),
        ]);
    }
}
