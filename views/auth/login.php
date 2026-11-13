<?php $formError = field_error('form'); ?>
<h1 class="auth-title">Log in</h1>
<p class="auth-lead">Welcome back. Your friends are waiting.</p>
<?php if ($status = flashed('status')): ?>
<div class="alert alert-success" role="status"><?= icon('check') ?><span><?= e($status) ?></span></div>
<?php endif; ?>
<?php if ($formError): ?>
<div class="alert alert-error" role="alert" id="login-error"><?= icon('alert-circle') ?><span><?= e($formError) ?></span></div>
<?php endif; ?>
<form method="post" action="<?= e(url('/login')) ?>" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="next" value="<?= e($next) ?>">
  <div class="field">
    <label class="label" for="email">Email</label>
    <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" maxlength="190" required autocomplete="email" autocapitalize="none" spellcheck="false"<?= $formError ? ' aria-invalid="true" aria-describedby="login-error"' : ' autofocus' ?>>
  </div>
  <div class="field">
    <div class="label-row">
      <label class="label" for="password">Password</label>
      <a href="<?= e(url('/forgot-password')) ?>">Forgot password?</a>
    </div>
    <div class="password-wrap">
      <input class="input" type="password" id="password" name="password" required autocomplete="current-password"<?= $formError ? ' aria-invalid="true" aria-describedby="login-error" autofocus' : '' ?>>
      <button class="icon-btn js-reveal" type="button" aria-label="Show password"><?= icon('eye') ?></button>
    </div>
  </div>
  <div class="form-actions"><button class="btn btn-primary btn-lg btn-block" type="submit">Log in</button></div>
</form>
<p class="auth-switch">New here? <a href="<?= e(url('/register')) ?>">Create an account</a></p>
