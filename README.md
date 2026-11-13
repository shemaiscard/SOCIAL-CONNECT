# Social Connect

A small social network in plain PHP 8 and MySQL 8, with no framework and no build step. People sign up, add friends, post text and photos, like and comment, join groups, chat one to one and get notified when something happens.

## Features

- **Accounts:** sign-up, login with `password_hash`, a password reset link sent by email that expires after an hour, and a login throttle (5 failures per email and IP in 15 minutes)
- **Feed:** posts from you and your friends plus public posts, with photos, likes, comments, saving, sharing (the Web Share API on phones, copy link elsewhere) and reporting
- **Profiles:** avatar, cover photo, bio, location, a friends strip and the person's posts
- **Friends:** requests in both directions, suggestions ranked by mutual friends, unfriend with a confirmation
- **Messages:** conversations with friends; an open thread checks for new messages every 4 seconds
- **Notifications:** friend requests, accepted requests, likes and comments, with unread counts in the navigation
- **Groups:** create, join, leave and delete groups; members post inside the group
- **Search:** people, groups and posts, with the matching words highlighted
- **Settings:** profile details, photos, email, password and a light, dark or system theme
- **Installable:** web app manifest, icons and a service worker that shows an offline page

The interface works without JavaScript (every action is a normal form post). With JavaScript, likes, comments, friend buttons and new posts update in place.

## Security

- PDO prepared statements for every query
- CSRF token on every form and in-page request
- A strict Content Security Policy: no inline scripts or styles
- Uploads checked by content type and size, renamed, and served from a folder where PHP does not run
- Session cookies set to `HttpOnly` and `SameSite=Lax` (and `Secure` over HTTPS), and the session ID changes at login
- Changing or resetting a password signs out every other device: each session stores the account's `auth_version`, which goes up with every password change
- Reset links: at most 3 requests per email address and 10 per IP address in an hour. The limit counts addresses with no account too, so the form does not reveal who has signed up

## Run it locally

You need PHP 8.1 or newer with `pdo_mysql` and `fileinfo`, and MySQL 8.

```bash
cp .env.example .env        # then set DB_PASS (and DB_USER if you like)
./setup-database.sh         # creates the database and user, loads database/schema.sql, offers demo data
./start-server.sh           # http://localhost:3000
```

With the demo data, sign in as `maya@example.test` with the password `demo1234`. Every demo account uses the same password, and all twelve people are fictional.

`setup-database.sh` connects as the MySQL `root` account and asks for its password. Set `MYSQL_ADMIN_USER` to use another account that can create users.

Locally, emails are written to `storage/outbox` as text files.

## Email with Resend

Password reset emails go through [Resend](https://resend.com) once `RESEND_API_KEY` is set. The free plan covers 3,000 emails a month and 100 a day.

1. Create a Resend account and an API key with "Sending access".
2. Add your domain under Domains and create the DNS records Resend lists (SPF, DKIM and an optional DMARC record) at your DNS provider. Wait until Resend shows the domain as verified.
3. In `.env`, set `RESEND_API_KEY` and a `MAIL_FROM` on that domain, for example `"Social Connect <no-reply@social.example.com>"`.
4. Request a reset link for your own account and check that it arrives. Failures go to `storage/logs/app.log`.

## Going live

- Point the web server's document root at `public/`. The `.htaccess` there sends every request that is not a file to `public/index.php`.
- Serve the site over HTTPS only, so the session cookie gets the `Secure` flag.
- Set `APP_DEBUG=0`, so errors stay in `storage/logs` and reset links never show on the page.
- Give the database user rights on the `social_connect` database only, with a long random password.
- Make `storage/` and `public/uploads/` writable by the web server, and nothing else.
- Back up the database and `public/uploads/` on a schedule.
- Do not load the demo data.

## Tests

`tests/e2e_test.py` drives a real browser through sign-up, login errors and the throttle, posting with a photo, likes, comments, saving, sharing, reporting and deleting, settings and photo uploads, password change and reset, friend requests, messages between two people, notifications, groups, search, and every page at desktop and phone width. It fails on any console error, CSP violation or PHP warning.

```bash
pip install playwright && playwright install chromium
php bin/seed.php --yes      # fresh demo data
BASE=http://localhost:3000 python tests/e2e_test.py
```

Run the server with `APP_DEBUG=1` for the test, since the reset check reads the link from the page.

## Project structure

```text
public/        web root: index.php, assets (CSS, JS, icons), manifest, service worker, uploads
src/Core/      router, database, sessions, auth, CSRF, views, uploads, mailer
src/Controllers/  one class per area (auth, feed, posts, profiles, friends, messages, ...)
src/Models/    queries for users, posts, comments, friendships, groups, messages, notifications
views/         layouts, pages and partials (plain PHP templates)
database/      schema.sql (14 tables)
bin/seed.php   demo data
tests/         end-to-end browser test
```

## License

MIT License. See `LICENSE`.
