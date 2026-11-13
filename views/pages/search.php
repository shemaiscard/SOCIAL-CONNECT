<?php

use App\Core\Auth;
use App\Models\Friendship;

$myId = (int) Auth::id();
$statusOf = static function (array $person) use ($myId): string {
    if ((int) $person['id'] === $myId) {
        return Friendship::SELF;
    }
    return match ($person['friend_status']) {
        'accepted' => Friendship::FRIENDS,
        'pending' => (int) $person['requester_id'] === $myId ? Friendship::OUTGOING : Friendship::INCOMING,
        default => Friendship::NONE,
    };
};
$total = count($results['people']) + count($results['groups']) + count($results['posts']);
?>
<h1 class="visually-hidden">Search</h1>
<form class="search-big" method="get" action="<?= e(url('/search')) ?>" role="search">
  <label class="visually-hidden" for="search-q">Search people, groups and posts</label>
  <?= icon('search') ?>
  <input class="input" type="search" id="search-q" name="q" value="<?= e($query) ?>" placeholder="Search people, groups and posts" maxlength="100" autocomplete="off"<?= $query === '' ? ' autofocus' : '' ?>>
</form>

<?php if ($query === ''): ?>
<div class="card">
  <?= partial('empty', [
      'icon' => 'search',
      'title' => 'Find people and groups',
      'text' => 'Search by a person\'s name or exact email address, a group name, or words in a post.',
      'level' => 2,
  ]) ?>
</div>
<?php elseif ($total === 0): ?>
<div class="card">
  <?= partial('empty', [
      'icon' => 'search',
      'title' => 'No results for "' . $query . '"',
      'text' => 'Check the spelling, or try a shorter search such as a first name.',
      'level' => 2,
  ]) ?>
</div>
<?php else: ?>
<p class="small muted feed-head"><?= e(plural($total, 'result')) ?> for "<?= e($query) ?>"</p>

<?php if ($results['people']): ?>
<section class="result-section" aria-labelledby="people-title">
  <h2 class="section-title feed-head" id="people-title">People</h2>
  <div class="card">
    <ul class="person-list">
<?php foreach ($results['people'] as $person): ?>
<?php $status = $statusOf($person); ?>
      <?= partial('person', [
          'person' => $person,
          'nameHtml' => highlight(full_name($person), $query),
          'sub' => $status === Friendship::SELF ? 'You' : (($person['location'] ?? '') !== '' ? $person['location'] : null),
          'actions' => $status === Friendship::SELF ? '' : partial('friend-actions', ['person' => $person, 'status' => $status, 'context' => 'list']),
      ]) ?>
<?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php if ($results['groups']): ?>
<section class="result-section" aria-labelledby="groups-title">
  <h2 class="section-title feed-head" id="groups-title">Groups</h2>
  <div class="card">
    <ul>
<?php foreach ($results['groups'] as $group): ?>
<?php $gid = (int) $group['id']; ?>
      <li class="group-row">
        <span class="group-tile av-<?= $gid % 8 ?>" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($group['name'], 0, 1))) ?></span>
        <div class="person-main">
          <a class="person-name" href="<?= e(url('/groups/' . $gid)) ?>"><?= highlight($group['name'], $query) ?></a>
          <p class="person-sub"><?= e(plural((int) $group['member_count'], 'member')) ?><?= $group['my_role'] !== null ? ' · You are a member' : '' ?></p>
        </div>
<?php if ($group['my_role'] === null): ?>
        <form method="post" action="<?= e(url('/groups/' . $gid . '/join')) ?>">
          <?= csrf_field() ?>
          <button class="btn btn-secondary" type="submit"><?= icon('plus') ?><span>Join</span></button>
        </form>
<?php endif; ?>
      </li>
<?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<?php if ($results['posts']): ?>
<section class="result-section" aria-labelledby="posts-title">
  <h2 class="section-title feed-head" id="posts-title">Posts</h2>
  <div class="post-list">
<?php foreach ($results['posts'] as $post): ?>
    <?= partial('post', ['post' => $post]) ?>
<?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php endif; ?>
