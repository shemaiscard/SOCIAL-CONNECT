<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Group;
use App\Models\Post;

final class GroupController
{
    public function index(): void
    {
        $me = (int) Auth::id();
        View::render('pages/groups', [
            'title' => 'Groups',
            'active' => 'groups',
            'mine' => Group::mine($me),
            'discover' => Group::discover($me),
        ]);
    }

    public function store(): void
    {
        $name = input('name');
        $description = input('description');
        $errors = [];
        if (mb_strlen($name) < 3 || mb_strlen($name) > 80) {
            $errors['name'] = 'Use 3 to 80 characters for the name.';
        }
        if (mb_strlen($description) > 500) {
            $errors['description'] = 'Keep the description to 500 characters.';
        }
        if ($errors) {
            flash('open_create', true);
            back_with_errors($errors, ['name' => $name, 'description' => $description]);
        }
        $id = Group::create((int) Auth::id(), $name, $description);
        flash('toast', 'Group created. Invite people by sharing its link.');
        redirect('/groups/' . $id);
    }

    public function show(int $id): void
    {
        $me = (int) Auth::id();
        $group = Group::find($id, $me);
        if ($group === null) {
            abort(404);
        }
        [$posts, $next] = Post::byGroup($id, $me, query_int('before'));
        if (is_fragment()) {
            echo partial('post-list', ['posts' => $posts, 'next' => $next, 'inGroup' => true]);
            return;
        }
        View::render('pages/group', [
            'title' => $group['name'],
            'active' => 'groups',
            'group' => $group,
            'members' => Group::members($id, 12),
            'posts' => $posts,
            'next' => $next,
        ]);
    }

    public function join(int $id): void
    {
        $group = $this->group($id);
        Group::join($id, (int) Auth::id());
        done([], 'You joined ' . $group['name'] . '.');
    }

    public function leave(int $id): void
    {
        $group = $this->group($id);
        if ($group['my_role'] === 'owner') {
            fail('Owners can not leave their own group. Delete it instead.');
        }
        Group::leave($id, (int) Auth::id());
        done([], 'You left ' . $group['name'] . '.');
    }

    public function destroy(int $id): void
    {
        $group = $this->group($id);
        if ($group['my_role'] !== 'owner') {
            fail('Only the owner can delete this group.', 403);
        }
        Group::delete($id);
        done([], 'Group deleted.', '/groups');
    }

    private function group(int $id): array
    {
        $group = Group::find($id, (int) Auth::id());
        if ($group === null) {
            fail('That group no longer exists.', 404);
        }
        return $group;
    }
}
