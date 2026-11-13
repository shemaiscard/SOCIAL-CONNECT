<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Friendship;
use App\Models\Post;
use App\Models\User;

final class ProfileController
{
    public function show(int $id): void
    {
        $user = User::find($id);
        if ($user === null) {
            abort(404);
        }
        $me = (int) Auth::id();
        [$posts, $next] = Post::byUser($id, $me, query_int('before'));
        if (is_fragment()) {
            echo partial('post-list', ['posts' => $posts, 'next' => $next]);
            return;
        }
        View::render('pages/profile', [
            'title' => full_name($user),
            'active' => $id === $me ? 'profile' : '',
            'user' => $user,
            'status' => Friendship::status($me, $id),
            'friendCount' => Friendship::countFriends($id),
            'friends' => Friendship::friends($id, 6),
            'mutual' => $id === $me ? 0 : Friendship::mutualCount($me, $id),
            'posts' => $posts,
            'next' => $next,
        ]);
    }
}
