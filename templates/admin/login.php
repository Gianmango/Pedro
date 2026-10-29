<?php $title = 'Accesso'; require base_path('templates/admin/partials/header.php'); ?>

<form method="post" action="/admin/login" class="card form form--narrow">
    <h1>Accesso amministratori</h1>

    <?php if (!empty($error)): ?>
        <p class="flash flash--error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <?= csrf_field() ?>

    <label>Utente
        <input name="username" autocomplete="username" required autofocus value="<?= e($username ?? '') ?>">
    </label>

    <label>Password
        <input type="password" name="password" autocomplete="current-password" required>
    </label>

    <div class="form__actions">
        <button class="btn btn--primary">Entra</button>
    </div>
</form>

<?php require base_path('templates/admin/partials/footer.php'); ?>
