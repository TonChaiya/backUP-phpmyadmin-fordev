<?php

declare(strict_types=1);

use BackupApp\Support\Security;
?>
<section class="auth-shell" aria-labelledby="login-title">
    <form class="panel auth-panel" method="post" action="index.php" autocomplete="on">
        <div class="panel-header">
            <div>
                <span class="panel-kicker">Administrator</span>
                <h2 id="login-title">เข้าสู่ระบบ</h2>
            </div>
        </div>
        <div class="panel-body">
            <input type="hidden" name="csrf_token" value="<?= Security::e((string) $csrfToken) ?>">
            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" autocomplete="username" required autofocus>
            </div>
            <div class="field">
                <label for="login-password">Password</label>
                <input id="login-password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <button class="primary-action full-width" type="submit" name="action" value="login">เข้าสู่ระบบ</button>
        </div>
    </form>
</section>

