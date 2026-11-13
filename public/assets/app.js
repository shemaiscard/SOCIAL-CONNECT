/* Social Connect front end. Every form works without JavaScript; this file adds
   in-place updates, confirmations, previews, polling and the theme switch. */
'use strict';

(() => {
  const root = document.documentElement;
  const base = root.dataset.base || '';
  const sprite = root.dataset.icons || '';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const MAX_IMAGE = 5 * 1024 * 1024;
  const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

  const $ = (selector, scope = document) => scope.querySelector(selector);
  const $$ = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));

  const icon = (name) =>
    `<svg class="icon" aria-hidden="true" focusable="false"><use href="${sprite}#${name}"></use></svg>`;

  function toNodes(html) {
    const template = document.createElement('template');
    template.innerHTML = html.trim();
    return template.content;
  }

  /* ---------- Requests ---------- */

  async function request(url, { method = 'GET', body = null, fragment = false } = {}) {
    const headers = { Accept: fragment ? 'text/html' : 'application/json' };
    if (fragment) headers['X-Fragment'] = '1';
    if (method !== 'GET') headers['X-CSRF-Token'] = csrf;

    let response;
    try {
      response = await fetch(url, { method, body, headers, credentials: 'same-origin' });
    } catch {
      throw new Error('You seem to be offline. Check your connection and try again.');
    }
    if (response.status === 401) {
      location.href = `${base}/login?next=${encodeURIComponent(location.pathname + location.search)}`;
      throw new Error('Your session ended. Log in again.');
    }
    if (fragment && response.ok) return response.text();

    let data = {};
    try {
      data = await response.json();
    } catch {
      // Non-JSON error page; fall through to the generic message.
    }
    if (!response.ok) {
      throw new Error(data.error || 'Something went wrong. Try again in a moment.');
    }
    return data;
  }

  // Reads the attribute: a field named "action" (the friend buttons have one) shadows form.action.
  const submitForm = (form) => request(form.getAttribute('action'), { method: 'POST', body: new FormData(form) });

  /** Runs one request per form at a time and marks its submit button busy meanwhile. */
  async function busy(form, task) {
    if (form.dataset.busy === '1') return;
    form.dataset.busy = '1';
    const buttons = $$('button[type="submit"], button:not([type])', form);
    buttons.forEach((button) => button.setAttribute('aria-busy', 'true'));
    try {
      await task();
    } catch (error) {
      toast(error.message, 'error');
    } finally {
      delete form.dataset.busy;
      buttons.forEach((button) => button.removeAttribute('aria-busy'));
    }
  }

  /* ---------- Toasts ---------- */

  const toastRegion = $('#toasts');

  function dismiss(el) {
    el.classList.add('is-leaving');
    setTimeout(() => el.remove(), 260);
  }

  function toast(message, type = 'ok') {
    if (!message || !toastRegion) return;
    const el = document.createElement('div');
    el.className = type === 'error' ? 'toast toast-error' : 'toast';
    el.innerHTML = icon(type === 'error' ? 'alert-circle' : 'check') + '<span></span>';
    el.lastChild.textContent = message;
    toastRegion.append(el);
    setTimeout(() => dismiss(el), type === 'error' ? 6000 : 4000);
  }

  $$('.toast', toastRegion || document).forEach((el) => setTimeout(() => dismiss(el), 5000));

  /* ---------- Confirmation dialog ---------- */

  const confirmDialog = $('#confirm-dialog');
  let pendingForm = null;

  function askConfirm(form) {
    if (!confirmDialog) {
      if (window.confirm(form.dataset.confirm)) {
        form.dataset.confirmed = '1';
        form.requestSubmit();
      }
      return;
    }
    pendingForm = form;
    $('.dialog-title', confirmDialog).textContent = form.dataset.confirm;
    const text = $('.dialog-text', confirmDialog);
    text.textContent = form.dataset.confirmText || '';
    text.hidden = !form.dataset.confirmText;
    $('.choice-list', confirmDialog).hidden = !('confirmReasons' in form.dataset);
    const ok = $('[value="ok"]', confirmDialog);
    ok.textContent = form.dataset.confirmLabel || 'Confirm';
    ok.className = form.dataset.confirmTone === 'danger' ? 'btn btn-danger' : 'btn btn-primary';
    confirmDialog.returnValue = '';
    confirmDialog.showModal();
    $('[value="cancel"]', confirmDialog).focus();
  }

  confirmDialog?.addEventListener('close', () => {
    const form = pendingForm;
    pendingForm = null;
    if (!form || confirmDialog.returnValue !== 'ok') return;
    if ('confirmReasons' in form.dataset) {
      const choice = $('input[name="reason"]:checked', confirmDialog);
      const field = $('input[name="reason"]', form);
      if (field && choice) field.value = choice.value;
    }
    form.dataset.confirmed = '1';
    form.requestSubmit();
  });

  /* ---------- Form handlers (one delegated listener) ---------- */

  const handlers = [];
  const onSubmit = (selector, handler) => handlers.push([selector, handler]);

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.matches('[data-confirm]') && form.dataset.confirmed !== '1') {
      event.preventDefault();
      askConfirm(form);
      return;
    }
    delete form.dataset.confirmed;
    for (const [selector, handler] of handlers) {
      if (form.matches(selector)) {
        event.preventDefault();
        handler(form);
        return;
      }
    }
  });

  // Likes update at once and roll back if the server says no.
  onSubmit('.js-like', (form) => {
    const button = $('button', form);
    const count = $('.count', button);
    const wasLiked = button.getAttribute('aria-pressed') === 'true';
    const before = parseInt(count.textContent, 10) || 0;
    const show = (liked, total) => {
      button.setAttribute('aria-pressed', String(liked));
      count.textContent = total > 0 ? String(total) : '';
    };
    if (form.dataset.busy === '1') return;
    show(!wasLiked, before + (wasLiked ? -1 : 1));
    busy(form, async () => {
      try {
        const data = await submitForm(form);
        show(data.liked, data.count);
      } catch (error) {
        show(wasLiked, before);
        throw error;
      }
    });
  });

  onSubmit('.js-save', (form) =>
    busy(form, async () => {
      const data = await submitForm(form);
      const button = $('button', form);
      button.setAttribute('aria-pressed', String(data.saved));
      button.setAttribute('aria-label', data.saved ? 'Remove from saved' : 'Save post');
      button.title = data.saved ? 'Remove from saved' : 'Save post';
      toast(data.message);
    })
  );

  onSubmit('.js-delete-post', (form) =>
    busy(form, async () => {
      const data = await submitForm(form);
      if (data.redirect) {
        location.href = data.redirect;
        return;
      }
      const post = form.closest('.post');
      toast(data.message);
      if (post) {
        post.classList.add('is-removing');
        setTimeout(() => post.remove(), 220);
      }
    })
  );

  onSubmit('.js-report', (form) =>
    busy(form, async () => {
      const data = await submitForm(form);
      toast(data.message);
    })
  );

  function setCommentCount(post, total) {
    const count = post && $('.js-comment-count', post);
    if (count) count.textContent = total > 0 ? String(total) : '';
  }

  onSubmit('.js-comment', (form) => {
    const field = $('textarea', form);
    if (!field.value.trim()) {
      field.focus();
      return;
    }
    busy(form, async () => {
      const data = await submitForm(form);
      const post = form.closest('.post');
      const list = $('.comment-list', post);
      const nodes = toNodes(data.html);
      nodes.firstElementChild?.classList.add('is-new');
      list.append(nodes);
      enhance(list);
      field.value = '';
      autosize(field);
      setCommentCount(post, data.count);
    });
  });

  onSubmit('.js-delete-comment', (form) =>
    busy(form, async () => {
      const data = await submitForm(form);
      const post = form.closest('.post');
      form.closest('.comment')?.remove();
      setCommentCount(post, data.count);
      toast(data.message);
    })
  );

  onSubmit('.js-friend', (form) =>
    busy(form, async () => {
      const data = await submitForm(form);
      const wrapper = form.closest('.friend-actions');
      if (wrapper) {
        const nodes = toNodes(data.html);
        const replacement = nodes.firstElementChild;
        wrapper.replaceWith(nodes);
        replacement?.querySelector('button, a')?.focus();
      }
      toast(data.message);
      refreshCounts();
    })
  );

  /* ---------- Composer ---------- */

  function clearImage(form) {
    const input = $('input[type="file"]', form);
    const preview = $('.composer-preview', form);
    if (input) input.value = '';
    if (preview) {
      const img = $('img', preview);
      if (img.src.startsWith('blob:')) URL.revokeObjectURL(img.src);
      img.removeAttribute('src');
      preview.hidden = true;
    }
    updateComposer(form);
  }

  function updateComposer(form) {
    const text = $('textarea', form);
    const file = $('input[type="file"]', form);
    const submit = $('button[type="submit"]', form);
    const over = text.value.length > Number(text.dataset.max || Infinity);
    const empty = !text.value.trim() && !(file && file.files.length);
    submit.disabled = empty || over;
  }

  onSubmit('.js-composer', (form) =>
    busy(form, async () => {
      const data = await submitForm(form);
      const list = $('#posts');
      if (list) {
        $('.js-feed-empty')?.remove();
        const nodes = toNodes(data.html);
        const post = nodes.firstElementChild;
        post?.classList.add('is-new');
        list.prepend(nodes);
        if (post) enhance(post);
      }
      form.reset();
      clearImage(form);
      const text = $('textarea', form);
      autosize(text);
      updateCounter(text);
      toast(data.message);
    })
  );

  document.addEventListener('input', (event) => {
    const form = event.target.closest?.('.js-composer');
    if (form) updateComposer(form);
  });

  document.addEventListener('change', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'file') return;
    const file = input.files[0];
    if (file && !IMAGE_TYPES.includes(file.type)) {
      toast('Choose a JPG, PNG, WebP or GIF image.', 'error');
      input.value = '';
      return;
    }
    if (file && file.size > MAX_IMAGE) {
      toast('That image is larger than 5 MB. Choose a smaller one.', 'error');
      input.value = '';
      return;
    }
    const form = input.form;
    if (form?.matches('.js-composer')) {
      const preview = $('.composer-preview', form);
      const img = $('img', preview);
      if (img.src.startsWith('blob:')) URL.revokeObjectURL(img.src);
      if (file) {
        img.src = URL.createObjectURL(file);
        preview.hidden = false;
      } else {
        preview.hidden = true;
      }
      updateComposer(form);
    } else if (file && form?.matches('.js-autosubmit')) {
      form.requestSubmit();
    }
  });

  document.addEventListener('click', (event) => {
    const remove = event.target.closest('.js-remove-image');
    if (remove) clearImage(remove.form);
  });

  /* ---------- Textareas: autosize, counters, Enter to send ---------- */

  function autosize(field) {
    if (!field.classList.contains('js-autosize')) return;
    field.style.height = 'auto';
    field.style.height = `${field.scrollHeight + 2}px`;
  }

  function updateCounter(field) {
    const counter = field.id && $(`[data-counter-for="${field.id}"]`);
    if (!counter) return;
    const max = Number(field.dataset.max);
    const left = max - field.value.length;
    counter.textContent = left <= max * 0.2 ? String(left) : '';
    counter.classList.toggle('is-over', left < 0);
  }

  document.addEventListener('input', (event) => {
    const field = event.target;
    if (field instanceof HTMLTextAreaElement) {
      autosize(field);
      updateCounter(field);
    }
  });

  document.addEventListener('keydown', (event) => {
    const field = event.target;
    if (!(field instanceof HTMLTextAreaElement) || !field.classList.contains('js-enter-sends')) return;
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
      event.preventDefault();
      field.form.requestSubmit();
    }
  });

  /* ---------- Popover menus ---------- */

  document.addEventListener(
    'beforetoggle',
    (event) => {
      const menu = event.target;
      if (!(menu instanceof HTMLElement) || !menu.matches('.menu') || event.newState !== 'open') return;
      const invoker = $(`[popovertarget="${menu.id}"]`);
      if (!invoker) return;
      const rect = invoker.getBoundingClientRect();
      menu.style.top = `${Math.round(rect.bottom + 6)}px`;
      menu.style.right = `${Math.max(8, Math.round(window.innerWidth - rect.right))}px`;
      menu.style.bottom = 'auto';
      menu.style.left = 'auto';
      requestAnimationFrame(() => {
        const box = menu.getBoundingClientRect();
        if (box.bottom > window.innerHeight - 8) {
          menu.style.top = `${Math.max(8, Math.round(rect.top - box.height - 6))}px`;
        }
      });
    },
    true
  );

  document.addEventListener('click', (event) => {
    const item = event.target.closest('.menu .menu-item');
    const menu = item?.closest('.menu');
    if (menu && menu.matches(':popover-open')) menu.hidePopover();
  });

  /* ---------- Share, comment focus, password reveal, dialogs ---------- */

  async function copyText(text) {
    try {
      await navigator.clipboard.writeText(text);
      return true;
    } catch {
      const area = document.createElement('textarea');
      area.value = text;
      area.setAttribute('readonly', '');
      area.className = 'visually-hidden';
      document.body.append(area);
      area.select();
      const copied = document.execCommand('copy');
      area.remove();
      return copied;
    }
  }

  document.addEventListener('click', async (event) => {
    const share = event.target.closest('.js-share');
    if (share) {
      event.preventDefault();
      const url = share.dataset.url;
      if (navigator.share && matchMedia('(pointer: coarse)').matches) {
        try {
          await navigator.share({ title: document.title, url });
        } catch {
          // Closing the share sheet is not an error.
        }
        return;
      }
      toast((await copyText(url)) ? 'Link copied.' : 'Could not copy the link.', 'ok');
      return;
    }

    const focusComment = event.target.closest('.js-focus-comment');
    if (focusComment) {
      const field = $('.comment-form textarea', focusComment.closest('.post'));
      if (field) {
        event.preventDefault();
        field.focus();
      }
      return;
    }

    const reveal = event.target.closest('.js-reveal');
    if (reveal) {
      const input = $('input', reveal.closest('.password-wrap'));
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      reveal.setAttribute('aria-pressed', String(show));
      reveal.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      reveal.innerHTML = icon(show ? 'eye-off' : 'eye');
      return;
    }

    const opener = event.target.closest('[data-open-dialog]');
    if (opener) {
      document.getElementById(opener.dataset.openDialog)?.showModal();
      return;
    }

    const closer = event.target.closest('[data-close-dialog]');
    if (closer) closer.closest('dialog')?.close();

    const more = event.target.closest('.more-toggle');
    if (more) {
      const body = more.closest('.post-body');
      const clamped = body.classList.toggle('is-clamped');
      more.textContent = clamped ? 'See more' : 'See less';
      more.setAttribute('aria-expanded', String(!clamped));
    }
  });

  // Clicking the dimmed area outside a dialog closes it.
  document.addEventListener('click', (event) => {
    const dialog = event.target;
    if (!(dialog instanceof HTMLDialogElement) || !dialog.open) return;
    const box = dialog.getBoundingClientRect();
    const inside = event.clientX >= box.left && event.clientX <= box.right && event.clientY >= box.top && event.clientY <= box.bottom;
    if (!inside) dialog.close('cancel');
  });

  $$('dialog[data-open-on-load]').forEach((dialog) => dialog.showModal());

  /* ---------- Theme ---------- */

  function storedTheme() {
    try {
      return localStorage.getItem('sc-theme') || 'system';
    } catch {
      return 'system';
    }
  }

  function applyTheme(choice) {
    try {
      if (choice === 'system') localStorage.removeItem('sc-theme');
      else localStorage.setItem('sc-theme', choice);
    } catch {
      // The choice still applies to this page view.
    }
    if (choice === 'system') root.removeAttribute('data-theme');
    else root.setAttribute('data-theme', choice);
    syncThemeControls();
  }

  function effectiveTheme() {
    const choice = storedTheme();
    if (choice !== 'system') return choice;
    return matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  function syncThemeControls() {
    const dark = effectiveTheme() === 'dark';
    $$('.js-theme-toggle').forEach((button) => {
      button.setAttribute('aria-pressed', String(dark));
      const label = $('.nav-label, .theme-label', button);
      if (label) label.textContent = dark ? 'Light mode' : 'Dark mode';
      const use = $('use', button);
      if (use) use.setAttribute('href', `${sprite}#${dark ? 'sun' : 'moon'}`);
    });
    $$('input[name="theme"]').forEach((input) => {
      input.checked = input.value === storedTheme();
    });
  }

  document.addEventListener('click', (event) => {
    if (event.target.closest('.js-theme-toggle')) applyTheme(effectiveTheme() === 'dark' ? 'light' : 'dark');
  });

  document.addEventListener('change', (event) => {
    if (event.target.matches?.('input[name="theme"]')) applyTheme(event.target.value);
  });

  matchMedia('(prefers-color-scheme: dark)').addEventListener('change', syncThemeControls);
  syncThemeControls();

  /* ---------- Feed paging ---------- */

  const skeletonTemplate = $('#tpl-skeleton');

  async function loadMore(link) {
    const holder = link.closest('.more');
    if (!holder || holder.dataset.loading === '1') return;
    holder.dataset.loading = '1';
    const skeleton = skeletonTemplate ? skeletonTemplate.content.cloneNode(true) : null;
    link.hidden = true;
    if (skeleton) holder.before(skeleton);
    const placeholders = $$('.skeleton-post', holder.parentElement);
    try {
      const html = await request(link.href, { fragment: true });
      placeholders.forEach((el) => el.remove());
      const nodes = toNodes(html);
      const added = Array.from(nodes.children);
      holder.replaceWith(nodes);
      added.forEach((el) => enhance(el));
    } catch (error) {
      placeholders.forEach((el) => el.remove());
      link.hidden = false;
      delete holder.dataset.loading;
      toast(error.message, 'error');
    }
  }

  const moreObserver =
    'IntersectionObserver' in window
      ? new IntersectionObserver(
          (entries) => {
            entries.forEach((entry) => {
              if (entry.isIntersecting) {
                moreObserver.unobserve(entry.target);
                const link = $('.js-more', entry.target);
                if (link) loadMore(link);
              }
            });
          },
          { rootMargin: '600px 0px' }
        )
      : null;

  document.addEventListener('click', (event) => {
    const link = event.target.closest('.js-more');
    if (link) {
      event.preventDefault();
      loadMore(link);
    }
  });

  /* ---------- Per-element setup (runs on load and on inserted HTML) ---------- */

  const timeFormat = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });

  function enhance(scope) {
    $$('time[datetime]', scope).forEach((el) => {
      const date = new Date(el.getAttribute('datetime'));
      if (!Number.isNaN(date.getTime())) el.title = timeFormat.format(date);
    });
    $$('.post-text', scope).forEach((text) => {
      const body = text.closest('.post-body');
      if (!body || body.dataset.checked) return;
      body.dataset.checked = '1';
      body.classList.add('is-clamped');
      if (text.scrollHeight <= text.clientHeight + 2) {
        body.classList.remove('is-clamped');
        return;
      }
      const toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'more-toggle';
      toggle.textContent = 'See more';
      toggle.setAttribute('aria-expanded', 'false');
      body.append(toggle);
    });
    $$('textarea.js-autosize', scope).forEach(autosize);
    $$('.more', scope).forEach((el) => moreObserver?.observe(el));
    if (scope.matches?.('.more')) moreObserver?.observe(scope);
  }

  enhance(document);
  $$('.js-composer').forEach(updateComposer);

  /* ---------- Chat ---------- */

  const thread = $('.js-thread');
  if (thread) {
    const scroller = $('.thread-body', thread);
    const inner = $('.thread-inner', thread);
    const form = $('.js-message', thread);
    const pollUrl = thread.dataset.poll;
    let lastId = Number(thread.dataset.lastId) || 0;
    let polling = false;

    const nearBottom = () => scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight < 120;
    const toBottom = () => {
      scroller.scrollTop = scroller.scrollHeight;
    };

    function addMessages(html) {
      const nodes = toNodes(html);
      let added = 0;
      Array.from(nodes.children).forEach((el) => {
        const id = Number(el.dataset.id);
        if (id && inner.querySelector(`[data-id="${id}"]`)) return;
        el.classList.add('is-new');
        inner.append(el);
        enhance(el);
        lastId = Math.max(lastId, id);
        added += 1;
      });
      $('.js-thread-empty', inner)?.remove();
      return added;
    }

    toBottom();

    if (form) {
      onSubmit('.js-message', () => {
        const field = $('textarea', form);
        if (!field.value.trim()) {
          field.focus();
          return;
        }
        busy(form, async () => {
          const data = await submitForm(form);
          addMessages(data.html);
          field.value = '';
          autosize(field);
          toBottom();
          field.focus();
        });
      });
    }

    async function poll() {
      if (polling || document.hidden || !pollUrl) return;
      polling = true;
      try {
        const data = await request(`${pollUrl}?after=${lastId}`);
        if (data.html) {
          const stick = nearBottom();
          if (addMessages(data.html) && stick) toBottom();
        }
        lastId = Math.max(lastId, Number(data.last_id) || 0);
      } catch {
        // A missed poll is retried on the next tick.
      } finally {
        polling = false;
      }
    }

    setInterval(poll, 4000);
    document.addEventListener('visibilitychange', poll);
  }

  /* ---------- Badge counts ---------- */

  const baseTitle = document.title.replace(/^\(\d+\+?\)\s*/, '');

  function setBadges(counts) {
    Object.entries(counts).forEach(([key, value]) => {
      const n = Number(value) || 0;
      $$(`[data-badge="${key}"]`).forEach((badge) => {
        badge.textContent = n > 0 ? (n > 99 ? '99+' : String(n)) : '';
      });
    });
    const total = (Number(counts.notifications) || 0) + (Number(counts.messages) || 0);
    document.title = total > 0 ? `(${total > 99 ? '99+' : total}) ${baseTitle}` : baseTitle;
  }

  async function refreshCounts() {
    if (!document.body.dataset.signedIn || document.hidden) return;
    try {
      const counts = await request(`${base}/api/counts`);
      if ($('[data-page="notifications"]')) counts.notifications = 0;
      setBadges(counts);
    } catch {
      // Badges refresh again on the next tick.
    }
  }

  if (document.body.dataset.signedIn) {
    setInterval(refreshCounts, 30000);
    document.addEventListener('visibilitychange', refreshCounts);
  }

  /* ---------- Offline support ---------- */

  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
    navigator.serviceWorker.register(`${base}/sw.js`, { scope: `${base}/` }).catch(() => {});
  }
})();
