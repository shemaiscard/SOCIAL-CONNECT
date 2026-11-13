<h1 class="auth-title">Reset your password</h1>
<p class="auth-lead">Enter the email you signed up with and we will send you a link to choose a new password.</p>
<?php if ($status = flashed('status')): ?>
<div class="alert alert-success" role="status"><?= icon('mail') ?><span><?= e($status) ?></span></div>
<?php endif; ?>
<?php if ($devLink = flashed('dev_link')): ?>
<div class="alert" role="note"><?= icon('link') ?><span>Development mode: no email is sent. <a href="<?= e($devLink) ?>">Open the reset link</a>.</span></div>
<?php endif; ?>
<form method="post" action="<?= e(url('/forgot-password')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="field">
    <label class="label" for="email">Email</label>
    <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" maxlength="190" required autocomplete="email" autocapitalize="none" spellcheck="false"<?= invalid_attrs('email', 'email') ?>>
    <?= error_for('email', 'email') ?>
  </div>
  <div class="form-actions"><button class="btn btn-primary btn-lg btn-block" type="submit">Send reset link</button></div>
</form>
<p class="auth-switch">Remembered it? <a href="<?= e(url('/login')) ?>">Back to log in</a></p>
