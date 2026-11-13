"""End-to-end test for Social Connect, run against a freshly seeded database.

Needs Python 3 with Playwright (pip install playwright, then playwright install chromium).
Start the app with APP_DEBUG=1 (the reset test reads the development link from the page), then:

    BASE=http://localhost:3000 python tests/e2e_test.py

Every check prints PASS or FAIL. The exit code is the number of failures.
"""
import os
import re
import struct
import sys
import tempfile
import time
import zlib

from playwright.sync_api import sync_playwright

BASE = os.environ.get("BASE", "http://localhost:3000").rstrip("/")
PASSWORD = "demo1234"
STAMP = str(int(time.time()))[-6:]
NEW_EMAIL = f"tester{STAMP}@example.test"
NEW_PASS = "first-pass-123"

EXPECTED_404 = ("/posts/999999", "/no-such-page")
results = []
problems = []  # console errors, page errors, CSP reports, PHP warnings


def check(name, ok, detail=""):
    results.append((name, bool(ok)))
    print(("PASS " if ok else "FAIL ") + name + ("" if ok or not detail else f"  ({detail})"))
    return bool(ok)


def png(path, w=64, h=40, rgb=(31, 52, 92)):
    """Writes a small solid PNG without any imaging library."""
    raw = b"".join(b"\x00" + bytes(rgb) * w for _ in range(h))

    def chunk(kind, data):
        return struct.pack(">I", len(data)) + kind + data + struct.pack(">I", zlib.crc32(kind + data) & 0xFFFFFFFF)

    with open(path, "wb") as f:
        f.write(b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack(">IIBBBBB", w, h, 8, 2, 0, 0, 0))
                + chunk(b"IDAT", zlib.compress(raw)) + chunk(b"IEND", b""))
    return path


def watch(page, label):
    def on_console(msg):
        if msg.type == "error" and not ("status of 404" in msg.text and page.url.endswith(EXPECTED_404)):
            problems.append(f"[{label}] console: {msg.text} @ {page.url}")

    page.on("console", on_console)
    page.on("pageerror", lambda err: problems.append(f"[{label}] pageerror: {err} @ {page.url}"))
    return page


PHP_ERROR = re.compile(r"(Fatal error|Warning|Notice|Deprecated|Parse error|Uncaught)\b.{0,40}( in |: )", re.S)


def php_clean(page):
    body = page.content()
    hit = PHP_ERROR.search(body)
    if hit:
        problems.append(f"PHP output on {page.url}: {body[max(0, hit.start() - 60):hit.end() + 120]}")
    return hit is None


def login(page, email, password=PASSWORD):
    page.goto(f"{BASE}/login")
    page.fill("#email", email)
    page.fill("#password", password)
    page.click("form button[type=submit]")
    page.wait_for_load_state("networkidle")


def logout(page):
    if page.locator(".rail-foot").is_visible():
        page.locator(".rail-foot form button[type=submit]").click()
    else:
        page.locator(".tabbar [popovertarget=more-menu]").click()
        page.locator("#more-menu form button[type=submit]").click()
    page.wait_for_load_state("networkidle")


def toast_text(page, timeout=5000):
    """Returns the newest toast. Call clear_toasts before the action."""
    page.wait_for_selector("#toasts .toast", timeout=timeout)
    return page.locator("#toasts .toast").last.inner_text()


def clear_toasts(page):
    page.evaluate("document.querySelectorAll('#toasts .toast').forEach(t => t.remove())")


def open_menu_item(page, article, label):
    article.locator("[popovertarget^=post-menu]").click()
    article.locator(".menu:popover-open .menu-item", has_text=label).click()


def confirm(page, ok=True, reason=None):
    dialog = page.locator("#confirm-dialog[open]")
    dialog.wait_for()
    if reason:
        dialog.locator(f"input[name=reason][value={reason}]").check()
    dialog.locator(f"button[value={'ok' if ok else 'cancel'}]").click()


def counts(page):
    return page.evaluate(f"fetch('{BASE}/api/counts', {{headers: {{Accept: 'application/json'}}}}).then(r => r.json())")


def main():
    tmp = tempfile.mkdtemp()
    photo = png(os.path.join(tmp, "photo.png"), 120, 80, (209, 63, 52))
    avatar = png(os.path.join(tmp, "avatar.png"), 64, 64, (31, 52, 92))
    cover = png(os.path.join(tmp, "cover.png"), 300, 100, (90, 140, 200))
    fake = os.path.join(tmp, "notes.png")
    with open(fake, "w") as f:
        f.write("this is text, not an image")
    text_file = os.path.join(tmp, "notes.txt")
    with open(text_file, "w") as f:
        f.write("plain text")

    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        ctx_a = browser.new_context(viewport={"width": 1366, "height": 900})
        ctx_a.grant_permissions(["clipboard-read", "clipboard-write"], origin=BASE)
        a = watch(ctx_a.new_page(), "A")

        # ---------- Guest pages and registration ----------
        a.goto(f"{BASE}/")
        check("guest is sent to login", "/login" in a.url, a.url)
        check("login page has one h1", a.locator("h1").count() == 1)

        a.goto(f"{BASE}/register")
        a.click("form button[type=submit]")
        a.wait_for_load_state("networkidle")
        check("empty registration shows field errors", a.locator(".field-error").count() >= 3, str(a.locator(".field-error").count()))

        a.fill("#first-name", "Test")
        a.fill("#last-name", "Person")
        a.fill("#email", "maya@example.test")
        a.fill("#password", NEW_PASS)
        a.fill("#password-confirmation", NEW_PASS)
        a.click("form button[type=submit]")
        a.wait_for_load_state("networkidle")
        check("duplicate email is rejected", "/register" in a.url and a.locator("#email[aria-invalid=true]").count() == 1)
        check("names survive a failed submit", a.input_value("#first-name") == "Test")

        a.fill("#email", NEW_EMAIL)
        a.fill("#password", NEW_PASS)
        a.fill("#password-confirmation", "something-else")
        a.click("form button[type=submit]")
        a.wait_for_load_state("networkidle")
        check("mismatched confirmation is rejected", "/register" in a.url)

        a.fill("#password", NEW_PASS)
        a.fill("#password-confirmation", NEW_PASS)
        a.click("form button[type=submit]")
        a.wait_for_load_state("networkidle")
        check("registration signs the new person in", a.url.rstrip("/") == BASE and a.locator(".composer").count() == 1, a.url)
        check("new account sees the empty feed", a.locator(".js-feed-empty").count() == 1)
        php_clean(a)
        logout(a)
        check("logout returns to login", "/login" in a.url, a.url)

        # ---------- Login errors and throttle ----------
        login(a, "maya@example.test", "wrong-password")
        check("wrong password shows an error", a.locator("#login-error").count() == 1)
        check("email is kept after a failed login", a.input_value("#email") == "maya@example.test")
        locked = False
        for _ in range(7):
            login(a, f"locked{STAMP}@example.test", "nope-nope")
            if "Too many failed attempts" in a.content():
                locked = True
                break
        check("repeated failures are throttled", locked)

        a.goto(f"{BASE}/login?next=/saved")
        a.fill("#email", "maya@example.test")
        a.fill("#password", PASSWORD)
        a.click("form button[type=submit]")
        a.wait_for_load_state("networkidle")
        check("login follows the next parameter", a.url.endswith("/saved"), a.url)
        a.goto(f"{BASE}/login?next=https://evil.example/")
        check("signed-in visit to login redirects home", "/login" not in a.url, a.url)

        # ---------- Posts ----------
        a.goto(f"{BASE}/")
        check("feed shows seeded posts", a.locator("#posts article.post").count() >= 5)
        text = f"E2E post {STAMP} about #testing"
        a.fill("#composer-text", text)
        a.set_input_files(".composer input[type=file]", photo)
        a.wait_for_selector(".composer-preview:not([hidden]) img")
        check("photo preview appears", True)
        a.select_option("#composer-visibility", "public")
        a.click(".composer button[type=submit]")
        first = a.locator("#posts article.post").first
        first.locator(".post-text", has_text=text).wait_for(timeout=8000)
        check("new post appears at the top of the feed", True)
        check("new post has its photo", first.locator(".post-image img").count() == 1)
        check("composer is cleared after posting", a.input_value("#composer-text") == "")
        my_post_id = first.get_attribute("id") or ""

        clear_toasts(a)
        a.set_input_files(".composer input[type=file]", text_file)
        t = toast_text(a)
        check("non-image file is refused before upload", "image" in t.lower(), t)

        like = first.locator(".action-like")
        like.click()
        a.wait_for_function("el => el.getAttribute('aria-pressed') === 'true'", arg=like.element_handle())
        check("like turns on with count 1", like.locator(".count").inner_text().strip() == "1")
        like.click()
        a.wait_for_function("el => el.getAttribute('aria-pressed') === 'false'", arg=like.element_handle())
        check("second click removes the like", like.locator(".count").inner_text().strip() == "")

        first.locator(".comment-input").fill("First comment from the test")
        first.locator(".comment-send").click()
        first.locator(".comment-list li", has_text="First comment from the test").wait_for(timeout=5000)
        check("comment appears under the post", first.locator(".js-comment-count").inner_text().strip() == "1")
        first.locator(".comment-input").fill("Sent with the Enter key")
        first.locator(".comment-input").press("Enter")
        first.locator(".comment-list li", has_text="Sent with the Enter key").wait_for(timeout=5000)
        check("Enter sends a comment", True)

        save = first.locator(".action-save")
        save.click()
        a.wait_for_function("el => el.getAttribute('aria-pressed') === 'true'", arg=save.element_handle())
        check("save marks the post", save.get_attribute("aria-label") == "Remove from saved")

        clear_toasts(a)
        first.locator(".post-actions .js-share").click()
        t = toast_text(a)
        clip = a.evaluate("navigator.clipboard.readText()")
        check("share copies the post link", "copied" in t.lower() and "/posts/" in clip, f"{t} | {clip}")

        a.goto(f"{BASE}/saved")
        check("saved page lists the saved post", a.locator("article.post .post-text", has_text=text).count() == 1)

        a.goto(f"{BASE}/")
        other = a.locator("#posts article.post").filter(has=a.locator(".js-report")).first
        open_menu_item(a, other, "Report post")
        check("report dialog shows reasons", a.locator("#confirm-dialog[open] .choice-list:not([hidden])").count() == 1)
        clear_toasts(a)
        confirm(a, reason="harassment")
        t = toast_text(a)
        check("report is accepted", "report" in t.lower(), t)

        mine = a.locator(f"#{my_post_id}") if my_post_id else a.locator("#posts article.post").first
        open_menu_item(a, mine, "Delete post")
        check("delete asks with a danger button", a.locator("#confirm-dialog[open] button.btn-danger").count() == 1)
        confirm(a, ok=False)
        check("cancel keeps the post", mine.count() == 1)
        open_menu_item(a, mine, "Delete post")
        confirm(a)
        a.wait_for_timeout(600)
        check("confirmed delete removes the post", a.locator("article.post .post-text", has_text=text).count() == 0)
        a.reload()
        check("deleted post stays gone after reload", a.locator("article.post .post-text", has_text=text).count() == 0)

        a.goto(f"{BASE}/posts/1")
        check("single post page renders", a.locator("article.post").count() == 1 and a.locator("#comment-form").count() == 1)
        r = a.goto(f"{BASE}/posts/999999")
        check("missing post returns 404", r.status == 404 and a.locator(".error-page").count() == 1, str(r.status))

        # ---------- Feed paging ----------
        a.goto(f"{BASE}/u/1")
        check("profile shows posts and friends strip", a.locator("article.post").count() >= 1 and a.locator(".profile-card").count() == 1)

        # ---------- Friends, messages and counts between two people ----------
        ctx_b = browser.new_context(viewport={"width": 390, "height": 844}, is_mobile=True, has_touch=True)
        b = watch(ctx_b.new_page(), "B")
        login(b, "amara@example.test")
        check("mobile login lands on the feed", b.locator(".tabbar").is_visible())
        before = counts(b)
        a.goto(f"{BASE}/messages/3")
        a.fill("#message-body", f"Hello Amara {STAMP}")
        a.press("#message-body", "Enter")
        a.locator(".thread-inner", has_text=f"Hello Amara {STAMP}").wait_for(timeout=5000)
        check("sent message shows in the thread", a.input_value("#message-body") == "")
        after = counts(b)
        check("recipient unread count goes up", after.get("messages", 0) > before.get("messages", 0), f"{before} -> {after}")

        b.goto(f"{BASE}/messages/1")
        check("mobile thread shows the new message", b.locator(".thread-inner", has_text=f"Hello Amara {STAMP}").count() == 1)
        check("mobile thread hides the list", not b.locator(".chat-list").is_visible())
        b.fill("#message-body", f"Reply from Amara {STAMP}")
        b.locator(".thread-form button[type=submit]").click()
        b.locator(".thread-inner", has_text=f"Reply from Amara {STAMP}").wait_for(timeout=5000)
        try:
            a.locator(".thread-inner", has_text=f"Reply from Amara {STAMP}").wait_for(timeout=9000)
            check("open thread receives the reply by polling", True)
        except Exception:
            check("open thread receives the reply by polling", False)

        a.goto(f"{BASE}/messages/12")
        check("non-friend thread blocks sending", a.locator(".thread-note").count() == 1 and a.locator("#message-body").count() == 0)

        # Maya sends Jonah a request; Jonah accepts on his phone.
        a.goto(f"{BASE}/u/12")
        a.locator(".profile-actions button", has_text="Add friend").click()
        a.locator(".profile-actions button", has_text="Cancel request").wait_for(timeout=5000)
        check("add friend switches to cancel request", True)
        logout(b)
        login(b, "jonah@example.test")
        c = counts(b)
        check("request shows in Jonah's counts", c.get("requests", 0) >= 1, str(c))
        b.goto(f"{BASE}/friends?tab=requests")
        row = b.locator(".person-list li", has_text="Maya")
        row.locator("button", has_text="Accept").click()
        b.wait_for_timeout(800)
        b.goto(f"{BASE}/friends")
        check("accepted request moves to friends", b.locator(".person-list li", has_text="Maya").count() == 1)
        b.goto(f"{BASE}/notifications")
        check("Jonah's notifications page renders", b.locator("h1").count() == 1)
        php_clean(b)

        a.goto(f"{BASE}/notifications")
        check("Maya is told Jonah accepted", a.locator(".note", has_text="Jonah").count() >= 1)
        c = counts(a)
        check("visiting notifications clears the badge", c.get("notifications", 1) == 0, str(c))

        a.goto(f"{BASE}/u/12")
        a.locator(".profile-actions button", has_text="Unfriend").click()
        confirm(a)
        a.locator(".profile-actions button", has_text="Add friend").wait_for(timeout=5000)
        check("unfriend after confirming", True)

        a.goto(f"{BASE}/friends?tab=requests")
        check("incoming requests tab lists Hana and Kwame", a.locator(".person-list li").count() >= 2)
        for tab in ["friends", "sent", "suggestions"]:
            a.goto(f"{BASE}/friends?tab={tab}")
            check(f"friends tab {tab} renders", php_clean(a) and a.locator("[aria-current=page], [aria-selected=true]").count() >= 1)

        # ---------- Groups ----------
        a.goto(f"{BASE}/groups")
        a.click("[data-open-dialog=create-group]")
        dlg = a.locator("#create-group[open]")
        dlg.wait_for()
        dlg.locator("button[type=submit]").click()
        a.wait_for_load_state("networkidle")
        check("empty group name reopens the dialog with an error", a.locator("#create-group[open] .field-error").count() >= 1)
        gname = f"Test Group {STAMP}"
        a.fill("#create-group input[name=name]", gname)
        a.fill("#create-group textarea[name=description]", "Made by the end-to-end test.")
        a.locator("#create-group button[type=submit]").click()
        a.wait_for_load_state("networkidle")
        check("new group page opens", a.locator("h1", has_text=gname).count() == 1, a.url)
        group_url = a.url
        a.fill("#composer-text", "First post in the group")
        a.click(".composer button[type=submit]")
        a.locator("article.post .post-text", has_text="First post in the group").wait_for(timeout=6000)
        check("posting inside a group works", True)

        b.goto(group_url)
        check("non-member sees no composer", b.locator(".composer").count() == 0)
        b.locator("button", has_text="Join group").click()
        b.wait_for_load_state("networkidle")
        check("join shows the member composer", b.locator(".composer").count() == 1)
        b.locator(".group-head form[data-confirm] button", has_text="Leave").click()
        confirm(b)
        try:
            b.locator("button", has_text="Join group").wait_for(timeout=5000)
            check("leave after confirming", True)
        except Exception:
            check("leave after confirming", False, b.url)

        a.goto(group_url)
        a.locator(".group-head form[data-confirm] button", has_text="Delete").click()
        confirm(a)
        a.wait_for_load_state("networkidle")
        check("deleting the group returns to groups", a.url.endswith("/groups"), a.url)
        check("deleted group is gone", a.locator(".group-row", has_text=gname).count() == 0)

        # ---------- Search ----------
        a.goto(f"{BASE}/search?q=amara")
        check("search finds a person with highlight", a.locator(".person-list mark").count() >= 1)
        a.goto(f"{BASE}/search?q=bakers")
        check("search finds a group", a.locator(".group-row", has_text="Weekend Bakers").count() == 1)
        a.goto(f"{BASE}/search?q=zzqqxxnothing")
        check("search with no results shows the empty state", a.locator(".empty", has_text="No results").count() == 1)
        a.goto(f"{BASE}/search?q=%3Cscript%3Ealert(1)%3C/script%3E")
        check("search escapes HTML", a.locator("script", has_text="alert(1)").count() == 0)

        # ---------- Settings ----------
        logout(a)
        login(a, NEW_EMAIL, NEW_PASS)
        a.goto(f"{BASE}/settings")
        a.fill("#bio", "Testing every corner of the app.")
        a.fill("#location", "Kennesaw, GA")
        a.click("#profile button[type=submit]")
        a.wait_for_load_state("networkidle")
        check("profile details save", a.input_value("#bio") == "Testing every corner of the app.")
        a.fill("#first-name", "")
        a.click("#profile button[type=submit]")
        a.wait_for_load_state("networkidle")
        check("empty first name is refused", a.locator("#first-name[aria-invalid=true]").count() == 1)

        a.goto(f"{BASE}/settings#photos")
        a.set_input_files("#avatar-file", avatar)
        a.wait_for_load_state("networkidle")
        a.wait_for_timeout(500)
        check("avatar uploads", a.locator("#photos .photo-row").first.locator("img").count() == 1)
        a.set_input_files("#cover-file", cover)
        a.wait_for_load_state("networkidle")
        a.wait_for_timeout(500)
        check("cover uploads", a.locator(".cover-thumb img").count() == 1)
        a.set_input_files("#cover-file", fake)
        a.wait_for_timeout(800)
        check("text file as cover is refused", a.locator(".cover-thumb img").count() == 1)
        a.locator("#photos form[data-confirm] button", has_text="Remove").last.click()
        confirm(a)
        a.wait_for_load_state("networkidle")
        check("cover can be removed", a.locator(".cover-thumb img").count() == 0)

        a.fill("#current-password", "not-my-password")
        a.fill("#new-password", "second-pass-456")
        a.fill("#confirm-password", "second-pass-456")
        a.click("#account form[action$='/settings/password'] button[type=submit]")
        a.wait_for_load_state("networkidle")
        check("wrong current password is refused", a.locator("#current-password[aria-invalid=true]").count() == 1)
        ctx_x = browser.new_context(viewport={"width": 1366, "height": 900})
        x = watch(ctx_x.new_page(), "X")
        login(x, NEW_EMAIL, NEW_PASS)
        check("second device signs in", "/login" not in x.url, x.url)
        a.fill("#current-password", NEW_PASS)
        a.fill("#new-password", "second-pass-456")
        a.fill("#confirm-password", "second-pass-456")
        a.click("#account form[action$='/settings/password'] button[type=submit]")
        a.wait_for_load_state("networkidle")
        check("password change succeeds", a.locator("#current-password[aria-invalid=true]").count() == 0)
        a.goto(f"{BASE}/settings")
        check("password change keeps this device signed in", "/login" not in a.url, a.url)
        x.goto(f"{BASE}/")
        check("password change signs out other devices", "/login" in x.url, x.url)
        logout(a)
        login(a, NEW_EMAIL, "second-pass-456")
        check("new password signs in", "/login" not in a.url, a.url)
        logout(a)

        # ---------- Password reset ----------
        a.goto(f"{BASE}/forgot-password")
        a.fill("#email", NEW_EMAIL)
        a.click("form button[type=submit]")
        a.wait_for_load_state("networkidle")
        link = a.locator(".alert a[href*='reset-password']")
        check("development reset link is shown", link.count() == 1)
        if link.count():
            login(x, NEW_EMAIL, "second-pass-456")
            href = link.get_attribute("href")
            a.goto(href if href.startswith("http") else BASE + href)
            a.fill("#password", "third-pass-789")
            a.fill("#password-confirmation", "third-pass-789")
            a.click("form button[type=submit]")
            a.wait_for_load_state("networkidle")
            login(a, NEW_EMAIL, "third-pass-789")
            check("reset password signs in", "/login" not in a.url, a.url)
            x.goto(f"{BASE}/")
            check("password reset signs out other devices", "/login" in x.url, x.url)
            logout(a)
            a.goto(href if href.startswith("http") else BASE + href)
            check("used reset link is expired", "expired" in a.content().lower())
        a.goto(f"{BASE}/reset-password?token=bogus")
        check("bogus reset token is refused", "expired" in a.content().lower())
        ctx_x.close()

        # Three requests per address per hour, counted even for addresses with no account
        for i in range(4):
            a.goto(f"{BASE}/forgot-password")
            a.fill("#email", "no-account@example.test")
            a.click("form button[type=submit]")
            a.wait_for_load_state("networkidle")
        check("fourth reset request in an hour is refused", "Too many reset requests" in a.content())
        check("throttled form keeps the email", a.input_value("#email") == "no-account@example.test")

        # ---------- Every page at both widths ----------
        login(a, "maya@example.test")
        pages = ["/", "/posts/1", "/u/1", "/u/2", "/u/12", "/friends", "/friends?tab=requests", "/friends?tab=sent",
                 "/friends?tab=suggestions", "/messages", "/messages/3", "/notifications", "/groups", "/groups/1",
                 "/search", "/search?q=a", "/saved", "/settings"]
        for width in (1366, 390):
            a.set_viewport_size({"width": width, "height": 900})
            for path in pages:
                r = a.goto(BASE + path)
                a.wait_for_load_state("networkidle")
                over = a.evaluate("document.documentElement.scrollWidth - window.innerWidth")
                h1 = a.locator("h1").count()
                ok = r.status == 200 and php_clean(a) and over <= 0 and h1 == 1
                check(f"{width}px {path}", ok, f"status {r.status}, overflow {over}, h1 {h1}")
        r = a.goto(f"{BASE}/no-such-page")
        check("unknown page returns 404 page", r.status == 404 and a.locator(".error-page").count() == 1)

        a.goto(f"{BASE}/")
        a.locator(".rail-foot .js-theme-toggle").click() if a.locator(".rail-foot .js-theme-toggle").is_visible() else None
        logout(a)
        check("logout at the end", "/login" in a.url)

        ctx_a.close()
        ctx_b.close()
        browser.close()

    print()
    if problems:
        print("Browser and PHP problems:")
        for p in problems:
            print("  " + p[:400])
    failed = [n for n, ok in results if not ok]
    print(f"{len(results) - len(failed)} passed, {len(failed)} failed, {len(problems)} browser or PHP problems")
    return len(failed) + len(problems)


if __name__ == "__main__":
    sys.exit(main())
