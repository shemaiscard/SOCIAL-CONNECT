<?php

declare(strict_types=1);

/*
 * Fills the database with fictional demo data. Every account uses the password "demo1234".
 * Usage: php bin/seed.php [--yes]   (--yes skips the prompt when the database already has users)
 */

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database as DB;

$hasUsers = (int) DB::value('SELECT COUNT(*) FROM users') > 0;
if ($hasUsers && !in_array('--yes', $argv, true)) {
    fwrite(STDOUT, 'This deletes every row in the database and adds demo data. Type "yes" to continue: ');
    if (trim((string) fgets(STDIN)) !== 'yes') {
        exit("Cancelled.\n");
    }
}

$ago = static fn (int $minutes): string => gmdate('Y-m-d H:i:s', time() - $minutes * 60);
$hour = 60;
$day = 24 * 60;

DB::run('SET FOREIGN_KEY_CHECKS = 0');
foreach (['reports', 'notifications', 'messages', 'friendships', 'saved_posts', 'comments', 'post_likes',
    'posts', 'group_members', 'social_groups', 'login_attempts', 'reset_requests', 'password_resets', 'users'] as $table) {
    DB::run("TRUNCATE TABLE $table");
}
DB::run('SET FOREIGN_KEY_CHECKS = 1');

$hash = password_hash('demo1234', PASSWORD_DEFAULT);

// [first, last, email, location, bio, joined (days ago), last seen (minutes ago, null = never)]
$people = [
    ['Maya', 'Okafor', 'maya@example.test', 'Atlanta, GA', 'Product designer. Weekend trail runner, weekday coffee snob.', 120, 1],
    ['Daniel', 'Reyes', 'daniel@example.test', 'Austin, TX', 'Backend developer. I fix bugs and break sourdough.', 110, 2],
    ['Amara', 'Osei', 'amara@example.test', 'Accra, Ghana', 'Architecture student. Sketchbook always open.', 100, 3 * 60],
    ['Lukas', 'Brandt', 'lukas@example.test', 'Berlin, Germany', 'Runs slow, talks fast. Organizer of Saturday long runs.', 95, 25],
    ['Priya', 'Raman', 'priya@example.test', 'Pune, India', 'Baker, data analyst, and an unreasonable fan of cardamom.', 90, 0],
    ['Tomás', 'Ibarra', 'tomas@example.test', 'Valencia, Spain', null, 80, 2 * $day],
    ['Hana', 'Sato', 'hana@example.test', 'Osaka, Japan', 'Film photography and too many houseplants.', 60, 4 * 60],
    ['Kwame', 'Mensah', 'kwame@example.test', 'Kumasi, Ghana', 'Math teacher. Ask me about prime numbers.', 50, 40],
    ['Elif', 'Demir', 'elif@example.test', 'Izmir, Turkey', 'Backend engineer, part-time cyclist.', 45, 6 * 60],
    ['Noah', 'Fischer', 'noah@example.test', 'Zurich, Switzerland', null, 30, 3 * $day],
    ['Sofia', 'Marino', 'sofia@example.test', 'Turin, Italy', 'Cinema club host. Popcorn opinions are welcome.', 20, 90],
    ['Jonah', 'Weiss', 'jonah@example.test', null, null, 2, null],
];

$ids = [];
foreach ($people as [$first, $last, $email, $location, $bio, $joined, $seen]) {
    $ids[$first] = DB::insert(
        'INSERT INTO users (first_name, last_name, email, password_hash, bio, location, created_at, last_seen_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$first, $last, $email, $hash, $bio, $location, $ago($joined * $day), $seen === null ? null : $ago($seen)]
    );
}

$friends = static function (string $a, string $b, string $status, int $minutes, ?string $requester = null) use ($ids, $ago): void {
    [$x, $y] = [$ids[$a], $ids[$b]];
    DB::run(
        'INSERT INTO friendships (user_a, user_b, requester_id, status, created_at, accepted_at) VALUES (?, ?, ?, ?, ?, ?)',
        [min($x, $y), max($x, $y), $ids[$requester ?? $a], $status, $ago($minutes + 60), $status === 'accepted' ? $ago($minutes) : null]
    );
};

foreach ([['Maya', 'Daniel', 80], ['Maya', 'Amara', 70], ['Maya', 'Lukas', 60], ['Maya', 'Priya', 55], ['Maya', 'Tomás', 2],
    ['Daniel', 'Amara', 65], ['Daniel', 'Priya', 50], ['Amara', 'Kwame', 40], ['Lukas', 'Tomás', 38], ['Lukas', 'Elif', 30],
    ['Priya', 'Hana', 28], ['Priya', 'Noah', 20], ['Hana', 'Sofia', 15], ['Kwame', 'Elif', 12], ['Sofia', 'Noah', 10]] as [$a, $b, $days]) {
    $friends($a, $b, 'accepted', $days * $day);
}
$friends('Hana', 'Maya', 'pending', 5 * $hour);
$friends('Kwame', 'Maya', 'pending', 2 * $day);
$friends('Maya', 'Elif', 'pending', 1 * $day);
$friends('Sofia', 'Daniel', 'pending', 3 * $hour);

// Groups: [owner, name, description, members, created days ago]
$groups = [
    ['Lukas', 'Saturday Long Runs', "Easy pace, no one gets dropped. Route and start time are posted by Thursday.\nBring water in summer.", ['Maya', 'Tomás', 'Elif', 'Daniel'], 40],
    ['Priya', 'Weekend Bakers', 'Bread, cakes and the occasional disaster. Share your bakes and your recipes.', ['Maya', 'Daniel', 'Hana'], 35],
    ['Kwame', 'Algorithms Study Circle', 'We work through one problem set a week: graphs, dynamic programming, greedy proofs.', ['Amara', 'Elif', 'Noah'], 25],
    ['Sofia', 'Sunday Film Club', 'One film a week, then a long argument about it.', ['Hana', 'Noah'], 12],
];
$groupIds = [];
foreach ($groups as [$owner, $name, $description, $members, $days]) {
    $gid = DB::insert('INSERT INTO social_groups (owner_id, name, description, created_at) VALUES (?, ?, ?, ?)',
        [$ids[$owner], $name, $description, $ago($days * $day)]);
    $groupIds[$name] = $gid;
    DB::run("INSERT INTO group_members (group_id, user_id, role, joined_at) VALUES (?, ?, 'owner', ?)", [$gid, $ids[$owner], $ago($days * $day)]);
    foreach ($members as $i => $member) {
        DB::run('INSERT INTO group_members (group_id, user_id, joined_at) VALUES (?, ?, ?)', [$gid, $ids[$member], $ago(($days - $i - 1) * $day)]);
    }
}

// Posts: [author, body, minutes ago, visibility, group]
$posts = [
    ['Priya', "First attempt at cardamom buns. The shaping needs work but the kitchen smells unreal.", 35, 'public', null],
    ['Daniel', "Spent the morning tracking down a bug that turned out to be a time zone. It is always a time zone.", 2 * $hour, 'friends', null],
    ['Lukas', "Route for Saturday: river loop, 14 km, start 7:30 at the north bridge. Coffee after at the usual place.", 5 * $hour, 'public', 'Saturday Long Runs'],
    ['Amara', "Studio crit went better than expected. Three weeks of foam models finally paid off.", 9 * $hour, 'friends', null],
    ['Maya', "Redesigned our team's onboarding checklist this week. Cut it from 42 items to 17 and nobody has missed anything yet.", 20 * $hour, 'friends', null],
    ['Tomás', "Paella for twelve people on a camping stove. Would recommend, would not repeat.", 26 * $hour, 'public', null],
    ['Priya', "Recipe for the buns, since a few of you asked: 500 g flour, 10 g yeast, 80 g sugar, 2 tsp ground cardamom, 250 ml warm milk, 80 g butter. Knead 10 minutes, rise 1 hour, shape, rise 30 minutes, bake at 200 °C for 12 minutes.", 30 * $hour, 'public', 'Weekend Bakers'],
    ['Kwame', "This week's problem: prove that the greedy choice is safe for interval scheduling by earliest finish time. Exchange argument, one page max.", 34 * $hour, 'public', 'Algorithms Study Circle'],
    ['Maya', "Saturday run done. 14 km, legs are gone, mood is excellent.", 2 * $day, 'public', 'Saturday Long Runs'],
    ['Hana', "Developed my first roll from the new camera. Half are blurry. The other half I love.", 2 * $day + 3 * $hour, 'public', null],
    ['Daniel', "Sourdough attempt number nine. It rose. I am not sure how to feel.", 3 * $day, 'friends', 'Weekend Bakers'],
    ['Elif', "Rode 60 km along the coast today. Headwind both ways, somehow.", 3 * $day + 5 * $hour, 'public', null],
    ['Sofia', "This Sunday: a black-and-white double feature. Vote for the second film in the comments.", 4 * $day, 'public', 'Sunday Film Club'],
    ['Amara', "Anyone have a favorite book on drawing by hand? Looking to get away from the screen for a while.", 5 * $day, 'public', null],
    ['Maya', "Moved into the new apartment. The light in the morning is worth every box I carried up four flights.", 6 * $day, 'friends', null],
    ['Lukas', "Reminder that easy runs should feel easy. If you can not hold a conversation, slow down.", 7 * $day, 'public', 'Saturday Long Runs'],
    ['Priya', "Banana bread with brown butter. Ten out of ten, no notes.", 8 * $day, 'friends', null],
    ['Noah', "Hiked up to the lake before sunrise. Cold, quiet, completely worth it.", 9 * $day, 'public', null],
    ['Daniel', "Hot take: code review comments should say what to do, not just what is wrong.", 10 * $day, 'public', null],
    ['Maya', "Trying to read one book a month this year. February pick: a history of typefaces. Suggestions for March welcome.", 12 * $day, 'public', null],
    ['Kwame', "Students asked why we learn proofs when computers can check things. Best question I heard all term.", 13 * $day, 'public', null],
    ['Amara', "Sketch from the train this morning.", 15 * $day, 'friends', null],
];
$postIds = [];
foreach ($posts as $i => [$author, $body, $minutes, $visibility, $group]) {
    $postIds[$i] = DB::insert(
        'INSERT INTO posts (user_id, group_id, body, visibility, created_at) VALUES (?, ?, ?, ?, ?)',
        [$ids[$author], $group === null ? null : $groupIds[$group], $body, $visibility, $ago($minutes)]
    );
}

$like = static function (int $post, string $who, int $minutes) use ($ids, $postIds, $posts, $ago): void {
    DB::run('INSERT INTO post_likes (post_id, user_id, created_at) VALUES (?, ?, ?)', [$postIds[$post], $ids[$who], $ago($minutes)]);
    $owner = $posts[$post][0];
    if ($owner !== $who) {
        DB::run("INSERT INTO notifications (user_id, actor_id, type, post_id, created_at, read_at) VALUES (?, ?, 'post_like', ?, ?, ?)",
            [$ids[$owner], $ids[$who], $postIds[$post], $ago($minutes), $minutes > 12 * 60 ? $ago($minutes - 30) : null]);
    }
};
$comment = static function (int $post, string $who, string $body, int $minutes) use ($ids, $postIds, $posts, $ago): void {
    DB::run('INSERT INTO comments (post_id, user_id, body, created_at) VALUES (?, ?, ?, ?)', [$postIds[$post], $ids[$who], $body, $ago($minutes)]);
    $owner = $posts[$post][0];
    if ($owner !== $who) {
        DB::run("INSERT INTO notifications (user_id, actor_id, type, post_id, created_at, read_at) VALUES (?, ?, 'post_comment', ?, ?, ?)",
            [$ids[$owner], $ids[$who], $postIds[$post], $ago($minutes), $minutes > 12 * 60 ? $ago($minutes - 30) : null]);
    }
};

foreach ([[0, 'Maya', 30], [0, 'Daniel', 25], [0, 'Hana', 20], [0, 'Noah', 10], [1, 'Maya', 100], [1, 'Priya', 90],
    [2, 'Maya', 4 * 60], [2, 'Tomás', 3 * 60], [3, 'Maya', 8 * 60], [3, 'Daniel', 7 * 60], [3, 'Kwame', 6 * 60],
    [4, 'Daniel', 18 * 60], [4, 'Amara', 15 * 60], [4, 'Lukas', 10 * 60], [4, 'Priya', 50], [6, 'Maya', 29 * 60], [6, 'Hana', 28 * 60],
    [8, 'Lukas', 47 * 60], [8, 'Tomás', 46 * 60], [8, 'Daniel', 40 * 60], [9, 'Sofia', 48 * 60], [11, 'Lukas', 70 * 60],
    [14, 'Amara', 140 * 60], [14, 'Priya', 139 * 60], [14, 'Daniel', 130 * 60], [18, 'Maya', 230 * 60], [19, 'Amara', 280 * 60]] as [$p, $who, $minutes]) {
    $like($p, $who, $minutes);
}

$comment(0, 'Maya', 'These look great. Save me one?', 28);
$comment(0, 'Priya', 'Already set aside. Come by after work.', 22);
$comment(1, 'Maya', 'The classic. Was it daylight saving again?', 95);
$comment(1, 'Daniel', 'Worse. A server in one zone and a database in another.', 80);
$comment(2, 'Maya', 'I will be there. Bringing the slow group.', 4 * 60);
$comment(4, 'Daniel', 'Seventeen is a good number. What did you cut first?', 17 * 60);
$comment(4, 'Maya', 'Anything that was really a reminder, not a task. Those went into one welcome email.', 16 * 60);
$comment(4, 'Priya', 'Can you share the template? We need this on my team.', 45);
$comment(7, 'Amara', 'Is it allowed to use a picture in the proof?', 33 * 60);
$comment(7, 'Kwame', 'Yes, as long as the argument does not depend on it.', 32 * 60);
$comment(8, 'Lukas', 'Strong finish on the last hill.', 46 * 60);
$comment(12, 'Hana', 'Second film should be the heist one.', 3 * $day);
$comment(13, 'Maya', 'Seconding any book that comes with exercises.', 4 * $day);
$comment(14, 'Amara', 'Morning light is the best kind. Congratulations!', 140 * 60);
$comment(19, 'Daniel', 'Try a book on the history of maps. Same nerdy joy.', 11 * $day);

foreach ([[1, 'Maya'], [6, 'Maya'], [7, 'Maya'], [3, 'Daniel']] as [$p, $who]) {
    DB::run('INSERT INTO saved_posts (user_id, post_id, created_at) VALUES (?, ?, ?)', [$ids[$who], $postIds[$p], $ago(60)]);
}

// Friend request notifications for the pending requests, and one accepted request.
foreach ([['Maya', 'Hana', 'friend_request', 5 * $hour, false], ['Maya', 'Kwame', 'friend_request', 2 * $day, true],
    ['Daniel', 'Sofia', 'friend_request', 3 * $hour, false], ['Maya', 'Tomás', 'friend_accept', 2 * $day, true]] as [$to, $from, $type, $minutes, $read]) {
    DB::run('INSERT INTO notifications (user_id, actor_id, type, created_at, read_at) VALUES (?, ?, ?, ?, ?)',
        [$ids[$to], $ids[$from], $type, $ago($minutes), $read ? $ago($minutes - 10) : null]);
}

// Conversations: [from, to, body, minutes ago, read]
$messages = [
    ['Daniel', 'Maya', 'Are you still up for the run on Saturday?', 2 * $day + 30, true],
    ['Maya', 'Daniel', 'Yes. The river loop, right?', 2 * $day + 25, true],
    ['Daniel', 'Maya', 'That is the one. 7:30 at the north bridge.', 2 * $day + 24, true],
    ['Maya', 'Daniel', 'I will bring the coffee for after.', 2 * $day + 20, true],
    ['Daniel', 'Maya', 'How did the onboarding redesign land with your team?', 19 * 60, true],
    ['Maya', 'Daniel', 'Better than I hoped. Nobody asked for the old list back.', 18 * 60, true],
    ['Maya', 'Daniel', 'I can send you the template if you want it.', 18 * 60 - 1, true],
    ['Daniel', 'Maya', 'Please do. Our new hires keep getting lost in week one.', 50, true],
    ['Maya', 'Daniel', 'Sent it to your email just now.', 12, true],
    ['Amara', 'Maya', 'Hi Maya! Could I ask you a few questions about design school?', 3 * $hour, true],
    ['Maya', 'Amara', 'Of course. What would you like to know?', 2 * $hour + 50, true],
    ['Amara', 'Maya', 'Mostly how you put your portfolio together. Do you have time this week?', 40, false],
    ['Amara', 'Maya', 'Thursday evening works best for me.', 38, false],
    ['Priya', 'Maya', 'The buns are ready. Come by whenever.', 8, false],
    ['Lukas', 'Maya', 'Thanks for helping with the slow group last week.', 5 * $day, true],
    ['Maya', 'Lukas', 'Happy to. Same again this Saturday.', 5 * $day - 20, true],
];
foreach ($messages as [$from, $to, $body, $minutes, $read]) {
    DB::run('INSERT INTO messages (sender_id, recipient_id, body, created_at, read_at) VALUES (?, ?, ?, ?, ?)',
        [$ids[$from], $ids[$to], $body, $ago($minutes), $read ? $ago(max(0, $minutes - 2)) : null]);
}

echo 'Seeded ' . count($people) . ' people, ' . count($posts) . ' posts, ' . count($groups) . ' groups and ' . count($messages) . " messages.\n";
echo "Log in as maya@example.test with the password demo1234 (every demo account uses it).\n";
