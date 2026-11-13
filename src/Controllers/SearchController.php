<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;

final class SearchController
{
    public function index(): void
    {
        $me = (int) Auth::id();
        $query = trim((string) ($_GET['q'] ?? ''));
        $query = mb_substr($query, 0, 100);
        $results = ['people' => [], 'groups' => [], 'posts' => []];
        if ($query !== '') {
            $results = [
                'people' => User::search($query, $me, 20),
                'groups' => Group::search($query, $me, 8),
                'posts' => Post::search($query, $me, 10),
            ];
        }
        View::render('pages/search', [
            'title' => $query !== '' ? "Search: $query" : 'Search',
            'active' => 'search',
            'query' => $query,
            'results' => $results,
        ]);
    }
}
