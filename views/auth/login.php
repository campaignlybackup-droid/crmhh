<div class="login-wrap">
  <div class="login-card">
    <h1><?= e(config('app')['name'] ?? 'Agency CRM') ?></h1>
    <div class="sub">Sign in to your account</div>
    <?php render_flashes(); ?>
    <form method="post" action="<?= url('login', ['action' => 'login']) ?>">
        <?= Csrf::field() ?>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= old('email') ?>" required autofocus>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">Sign In</button>
    </form>
  </div>
</div>
