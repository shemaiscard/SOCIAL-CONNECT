<?php if (!$valid): ?>
<h1 class="auth-title">This link has expired</h1>
<p class="auth-lead">Reset links work once, for 60 minutes. Ask for a new one and use the newest email.</p>
<div class="form-actions"><a class="btn btn-primary btn-lg btn-block" href="<?= e(url('/forgot-password')) ?>">Get a new link</a></div>
<p class="auth-switch"><a href="<?= e(url('/login')) ?>">Back to log in</a></p>
<?php else: ?>
<h1 class="auth-title">Choose a new password</h1>
<p class="auth-lead">After this you will log in with the new password.</p>
<form method="post" action="<?= e(url('/reset-password')) ?>" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="token" value="<?= e($token) ?>">
  <div class="field">
    <label class="label" for="password">New password</label>
    <div class="password-wrap">
      <input class="input" type="password" id="password" name="password" minlength="8" maxlength="72" required autocomplete="new-password" autofocus aria-describedby="password-hint<?= field_error('password') ? ' password-error' : '' ?>"<?= field_error('password') ? ' aria-invalid="true"' : '' ?>>
      <button class="icon-btn js-reveal" type="button" aria-label="Show password"><?= icon('eye') ?></button>
    </div>
    <p class="field-hint" id="password-hint">At least 8 characters.</p>
    <?= error_for('password', 'password') ?>
  </div>
  <div class="field">
    <label class="label" for="password-confirmation">Confirm new password</label>
    <input class="input" type="password" id="password-confirmation" name="password_confirmation" maxlength="72" required autocomplete="new-password">
  </div>
  <div class="form-actions"><button class="btn btn-primary btn-lg btn-block" type="submit">Save new password</button></div>
</form>
<?php endif; ?>
