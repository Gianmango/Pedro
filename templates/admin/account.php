<?php $title = 'Account'; $active = 'account'; require base_path('templates/admin/partials/header.php'); ?>

<h1>Account amministratore</h1>
<p class="muted">Per confermare ogni modifica inserisci la password attuale. Se non vuoi cambiare password, lascia vuoti i relativi campi.</p>

<form method="post" action="/admin/account" class="card form form--narrow">
    <?= csrf_field() ?>

    <label>Nome utente
        <input name="username" autocomplete="username" minlength="3" maxlength="50" required value="<?= e($username) ?>">
        <?php if (isset($errors['username'])): ?><span class="field-error"><?= e($errors['username']) ?></span><?php endif; ?>
    </label>

    <label>Password attuale
        <input type="password" name="current_password" autocomplete="current-password" required>
        <?php if (isset($errors['current_password'])): ?><span class="field-error"><?= e($errors['current_password']) ?></span><?php endif; ?>
    </label>

    <label>Nuova password (facoltativa)
        <input type="password" name="new_password" autocomplete="new-password" minlength="8">
        <?php if (isset($errors['new_password'])): ?><span class="field-error"><?= e($errors['new_password']) ?></span><?php endif; ?>
    </label>

    <label>Conferma nuova password
        <input type="password" name="confirm_password" autocomplete="new-password">
        <?php if (isset($errors['confirm_password'])): ?><span class="field-error"><?= e($errors['confirm_password']) ?></span><?php endif; ?>
    </label>

    <div class="form__actions">
        <button class="btn btn--primary">Salva modifiche</button>
    </div>
</form>

<?php require base_path('templates/admin/partials/footer.php'); ?>
