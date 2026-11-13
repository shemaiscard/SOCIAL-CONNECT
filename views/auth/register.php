<h1 class="auth-title">Create your account</h1>
<p class="auth-lead">It takes a minute. Use the name your friends know you by.</p>
<form method="post" action="<?= e(url('/register')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="field-row">
    <div class="field">
      <label class="label" for="first-name">First name</label>
      <input class="input" id="first-name" name="first_name" value="<?= e(old('first_name')) ?>" maxlength="50" required autocomplete="given-name"<?= invalid_attrs('first_name', 'first-name') ?>>
      <?= error_for('first_name', 'first-name') ?>
    </div>
    <div class="field">
      <label class="label" for="last-name">Last name</label>
      <input class="input" id="last-name" name="last_name" value="<?= e(old('last_name')) ?>" maxlength="50" required autocomplete="family-name"<?= invalid_attrs('last_name', 'last-name') ?>>
      <?= error_for('last_name', 'last-name') ?>
    </div>
  </div>
  <div class="field">
    <label class="label" for="email">Email</label>
    <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" maxlength="190" required autocomplete="email" autocapitalize="none" spellcheck="false"<?= invalid_attrs('email', 'email') ?>>
    <?= error_for('email', 'email') ?>
  </div>
  <div class="field">
    <label class="label" for="password">Password</label>
    <div class="password-wrap">
      <input class="input" type="password" id="password" name="password" minlength="8" maxlength="72" required autocomplete="new-password" aria-describedby="password-hint<?= field_error('password') ? ' password-error' : '' ?>"<?= field_error('password') ? ' aria-invalid="true"' : '' ?>>
      <button class="icon-btn js-reveal" type="button" aria-label="Show password"><?= icon('eye') ?></button>
    </div>
    <p class="field-hint" id="password-hint">At least 8 characters.</p>
    <?= error_for('password', 'password') ?>
  </div>
  <div class="field">
    <label class="label" for="password-confirmation">Confirm password</label>
    <input class="input" type="password" id="password-confirmation" name="password_confirmation" maxlength="72" required autocomplete="new-password">
  </div>
  <div class="form-actions"><button class="btn btn-primary btn-lg btn-block" type="submit">Create account</button></div>
</form>
<p class="auth-switch">Already have an account? <a href="<?= e(url('/login')) ?>">Log in</a></p>
