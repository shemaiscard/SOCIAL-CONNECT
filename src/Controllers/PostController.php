<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Upload;
use App\Core\ValidationError;
use App\Core\View;
use App\Models\Comment;
use App\Models\Group;
use App\Models\Notification;
use App\Models\Post;

final class PostController
{
    private const MAX_POST = 5000;
    private const MAX_COMMENT = 1000;

    public function store(): void
    {
        $me = (int) Auth::id();
        $body = input('body');
        $groupId = (int) ($_POST['group_id'] ?? 0) ?: null;
        $visibility = ($_POST['visibility'] ?? '') === 'public' ? 'public' : 'friends';

        if ($groupId !== null && !Group::isMember($groupId, $me)) {
            fail('Join the group before posting in it.', 403);
        }
        if (mb_strlen($body) > self::MAX_POST) {
            fail('Posts can be up to 5,000 characters.');
        }
        try {
            $image = Upload::image($_FILES['image'] ?? null, 'posts');
        } catch (ValidationError $e) {
            fail($e->getMessage());
        }
        if ($body === '' && $image === null) {
            fail('Write something or add a photo first.');
        }

        $id = Post::create($me, $groupId, $body, $image, $visibility);
        $post = Post::find($id, $me);
        done(['html' => partial('post', ['post' => $post, 'inGroup' => $groupId !== null])], 'Your post is up.');
    }

    public function show(int $id): void
    {
        $post = $this->visiblePost($id);
        View::render('pages/post', [
            'title' => 'Post by ' . full_name($post),
            'post' => ['comments' => Comment::forPost($id)] + $post,
        ]);
    }

    public function destroy(int $id): void
    {
        $post = $this->visiblePost($id);
        if ((int) $post['user_id'] !== Auth::id()) {
            fail('You can only delete your own posts.', 403);
        }
        Post::delete($id);
        Upload::delete($post['image_path']);
        $from = (string) parse_url(back(), PHP_URL_PATH);
        $onPostPage = str_ends_with($from, '/posts/' . $id);
        done(['redirect' => $onPostPage ? url('/') : null], 'Post deleted.', $onPostPage ? '/' : null);
    }

    public function like(int $id): void
    {
        $post = $this->visiblePost($id);
        $me = (int) Auth::id();
        $result = Post::toggleLike($id, $me);
        if ($result['liked']) {
            Notification::add((int) $post['user_id'], $me, 'post_like', $id);
        } else {
            Notification::remove((int) $post['user_id'], $me, 'post_like', $id);
        }
        done($result);
    }

    public function save(int $id): void
    {
        $this->visiblePost($id);
        $saved = Post::toggleSave($id, (int) Auth::id());
        done(['saved' => $saved], $saved ? 'Saved. Find it under Saved.' : 'Removed from Saved.');
    }

    public function report(int $id): void
    {
        $post = $this->visiblePost($id);
        if ((int) $post['user_id'] === Auth::id()) {
            fail('You can not report your own post.');
        }
        $reason = in_array($_POST['reason'] ?? '', ['spam', 'harassment', 'inappropriate', 'other'], true)
            ? $_POST['reason'] : 'other';
        Post::report($id, (int) Auth::id(), $reason);
        done([], 'Thanks for telling us. The post was reported.');
    }

    public function comment(int $id): void
    {
        $post = $this->visiblePost($id);
        $me = (int) Auth::id();
        $body = input('body');
        if ($body === '') {
            fail('Write a comment first.');
        }
        if (mb_strlen($body) > self::MAX_COMMENT) {
            fail('Comments can be up to 1,000 characters.');
        }
        $commentId = Comment::create($id, $me, $body);
        Notification::add((int) $post['user_id'], $me, 'post_comment', $id);
        done([
            'html' => partial('comment', ['comment' => Comment::find($commentId)]),
            'count' => Comment::countForPost($id),
        ]);
    }

    public function destroyComment(int $id): void
    {
        $comment = Comment::find($id);
        if ($comment === null) {
            fail('That comment was already deleted.', 404);
        }
        $me = Auth::id();
        if ((int) $comment['user_id'] !== $me && (int) $comment['post_owner_id'] !== $me) {
            fail('You can only delete your own comments or comments on your posts.', 403);
        }
        Comment::delete($id);
        done(['count' => Comment::countForPost((int) $comment['post_id'])], 'Comment deleted.');
    }

    public function saved(): void
    {
        [$posts, $next] = Post::saved((int) Auth::id(), query_int('before'));
        if (is_fragment()) {
            echo partial('post-list', ['posts' => $posts, 'next' => $next]);
            return;
        }
        View::render('pages/saved', ['title' => 'Saved', 'active' => 'saved', 'posts' => $posts, 'next' => $next]);
    }

    private function visiblePost(int $id): array
    {
        $post = Post::find($id, (int) Auth::id());
        if ($post === null) {
            if (wants_json()) {
                json(['error' => 'That post is no longer available.'], 404);
            }
            abort(404);
        }
        return $post;
    }
}
