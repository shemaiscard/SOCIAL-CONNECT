<?php
$val = static fn (string $key): string => old($key, (string) ($user[$key] ?? ''));
?>
<div class="page-head"><h1>Settings</h1></div>

<nav class="settings-nav" aria-label="Settings sections">
  <a href="#profile">Profile</a>
  <a href="#photos">Photos</a>
  <a href="#account">Account</a>
  <a href="#appearance">Appearance</a>
</nav>

<section class="card settings-section" id="profile" aria-labelledby="profile-title">
  <div class="card-pad">
    <h2 id="profile-title">Profile</h2>
    <p>Everyone who can see your profile sees these details.</p>
    <form method="post" action="<?= e(url('/settings/profile')) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="field-row">
        <div class="field">
          <label class="label" for="first-name">First name</label>
          <input class="input" id="first-name" name="first_name" value="<?= e($val('first_name')) ?>" maxlength="50" required autocomplete="given-name"<?= invalid_attrs('first_name', 'first-name') ?>>
          <?= error_for('first_name', 'first-name') ?>
        </div>
        <div class="field">
          <label class="label" for="last-name">Last name</label>
          <input class="input" id="last-name" name="last_name" value="<?= e($val('last_name')) ?>" maxlength="50" required autocomplete="family-name"<?= invalid_attrs('last_name', 'last-name') ?>>
          <?= error_for('last_name', 'last-name') ?>
        </div>
      </div>
      <div class="field">
        <div class="label-row">
          <label class="label" for="bio">Bio</label>
          <span class="counter" data-counter-for="bio"></span>
        </div>
        <textarea class="textarea js-autosize" id="bio" name="bio" rows="3" data-max="300" maxlength="400" placeholder="A line or two about you"<?= invalid_attrs('bio', 'bio') ?>><?= e($val('bio')) ?></textarea>
        <?= error_for('bio', 'bio') ?>
      </div>
      <div class="field-row">
        <div class="field">
          <label class="label" for="location">Location <span class="label-hint">Optional</span></label>
          <input class="input" id="location" name="location" value="<?= e($val('location')) ?>" maxlength="100" autocomplete="address-level2" placeholder="City, country"<?= invalid_attrs('location', 'location') ?>>
          <?= error_for('location', 'location') ?>
        </div>
        <div class="field">
          <label class="label" for="birth-date">Birthday <span class="label-hint">Optional, not shown</span></label>
          <input class="input" type="date" id="birth-date" name="birth_date" value="<?= e($val('birth_date')) ?>" min="1900-01-01" max="<?= gmdate('Y-m-d') ?>" autocomplete="bday"<?= invalid_attrs('birth_date', 'birth-date') ?>>
          <?= error_for('birth_date', 'birth-date') ?>
        </div>
      </div>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Save profile</button></div>
    </form>
  </div>
</section>

<section class="card settings-section" id="photos" aria-labelledby="photos-title">
  <div class="card-pad">
    <h2 id="photos-title">Photos</h2>
    <p>JPEG, PNG, GIF or WebP, up to 5 MB.</p>
<?php
$photos = [
    ['avatar_path', '/settings/avatar', 'Profile photo', 'Shown next to your name everywhere.'],
    ['cover_path', '/settings/cover', 'Cover photo', 'The wide image at the top of your profile.'],
];
foreach ($photos as [$column, $action, $label, $hint]):
    $has = !empty($user[$column]);
    $fieldId = str_replace('_path', '', $column) . '-file';
?>
    <div class="photo-row">
<?php if ($column === 'avatar_path'): ?>
      <?= avatar($user, 'lg') ?>
<?php else: ?>
      <div class="cover-thumb cover"><?php if ($has): ?><img src="<?= e(media_url($user[$column])) ?>" alt="" loading="lazy" decoding="async"><?php endif; ?></div>
<?php endif; ?>
      <div class="photo-main">
        <span class="label"><?= e($label) ?></span>
        <span class="small muted"><?= e($hint) ?></span>
        <?= error_for($column, $fieldId) ?>
      </div>
      <form class="js-autosubmit" method="post" action="<?= e(url($action)) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label class="btn btn-secondary file-btn">
          <?= icon('camera') ?><span><?= $has ? 'Change' : 'Upload' ?></span>
          <input class="visually-hidden" type="file" id="<?= e($fieldId) ?>" name="photo" accept="image/jpeg,image/png,image/gif,image/webp"<?= invalid_attrs($column, $fieldId) ?>>
        </label>
        <noscript><button class="btn btn-primary" type="submit">Upload</button></noscript>
      </form>
<?php if ($has): ?>
      <form method="post" action="<?= e(url($action)) ?>" data-confirm="Remove your <?= e(strtolower($label)) ?>?" data-confirm-label="Remove" data-confirm-tone="danger">
        <?= csrf_field() ?>
        <input type="hidden" name="remove" value="1">
        <button class="btn btn-ghost" type="submit">Remove</button>
      </form>
<?php endif; ?>
    </div>
<?php endforeach; ?>
  </div>
</section>

<section class="card settings-section" id="account" aria-labelledby="account-title">
  <div class="card-pad">
    <h2 id="account-title">Account</h2>
    <p>Signed in as <?= e($user['email']) ?>.</p>

    <div class="subsection">
      <h3>Email address</h3>
      <form method="post" action="<?= e(url('/settings/email')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field-row">
          <div class="field">
            <label class="label" for="email">New email</label>
            <input class="input" type="email" id="email" name="email" value="<?= e($val('email')) ?>" maxlength="190" required autocomplete="email"<?= invalid_attrs('email', 'email') ?>>
            <?= error_for('email', 'email') ?>
          </div>
          <div class="field">
            <label class="label" for="email-password">Current password</label>
            <input class="input" type="password" id="email-password" name="current_password" required autocomplete="current-password"<?= invalid_attrs('email_password', 'email-password') ?>>
            <?= error_for('email_password', 'email-password') ?>
          </div>
        </div>
        <div class="form-actions"><button class="btn btn-secondary" type="submit">Update email</button></div>
      </form>
    </div>

    <div class="subsection">
      <h3>Password</h3>
      <form method="post" action="<?= e(url('/settings/password')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label class="label" for="current-password">Current password</label>
          <input class="input" type="password" id="current-password" name="current_password" required autocomplete="current-password"<?= invalid_attrs('current_password', 'current-password') ?>>
          <?= error_for('current_password', 'current-password') ?>
        </div>
        <div class="field-row">
          <div class="field">
            <label class="label" for="new-password">New password</label>
            <div class="password-wrap">
              <input class="input" type="password" id="new-password" name="password" minlength="8" required autocomplete="new-password" aria-describedby="new-password-hint<?= field_error('password') ? ' new-password-error' : '' ?>"<?= field_error('password') ? ' aria-invalid="true"' : '' ?>>
              <button class="icon-btn js-reveal" type="button" aria-label="Show password"><?= icon('eye') ?></button>
            </div>
            <p class="field-hint" id="new-password-hint">At least 8 characters. A short phrase is easier to remember.</p>
            <?= error_for('password', 'new-password') ?>
          </div>
          <div class="field">
            <label class="label" for="confirm-password">Confirm new password</label>
            <input class="input" type="password" id="confirm-password" name="password_confirmation" required autocomplete="new-password">
          </div>
        </div>
        <div class="form-actions"><button class="btn btn-secondary" type="submit">Change password</button></div>
      </form>
    </div>
  </div>
</section>

<section class="card settings-section" id="appearance" aria-labelledby="appearance-title">
  <div class="card-pad">
    <h2 id="appearance-title">Appearance</h2>
    <p>Saved on this device.</p>
    <fieldset>
      <legend class="visually-hidden">Theme</legend>
      <div class="theme-choices">
<?php foreach (['system' => ['sliders', 'System'], 'light' => ['sun', 'Light'], 'dark' => ['moon', 'Dark']] as $value => [$themeIcon, $themeLabel]): ?>
        <label class="choice"><input type="radio" name="theme" value="<?= $value ?>"<?= $value === 'system' ? ' checked' : '' ?>><?= icon($themeIcon) ?><span><?= $themeLabel ?></span></label>
<?php endforeach; ?>
      </div>
    </fieldset>
  </div>
</section>
