<?php $title = 'Pannello'; $active = ''; require base_path('templates/admin/partials/header.php'); ?>

<h1>Ciao<?= $username !== '' ? ', ' . e($username) : '' ?></h1>

<div class="tiles">
    <a class="tile" href="/admin/tables">
        <span class="tile__icon" aria-hidden="true">🍽️</span>
        <span class="tile__title">Visualizza tavoli</span>
        <span class="tile__sub">Conti aperti e ordini di ogni tavolo</span>
    </a>
    <a class="tile" href="/admin/menu">
        <span class="tile__icon" aria-hidden="true">📋</span>
        <span class="tile__title">Modifica menù</span>
        <span class="tile__sub">Aggiungi, modifica o elimina i piatti</span>
    </a>
    <a class="tile" href="/admin/account">
        <span class="tile__icon" aria-hidden="true">👤</span>
        <span class="tile__title">Account amministratore</span>
        <span class="tile__sub">Cambia nome utente o password</span>
    </a>
</div>

<?php require base_path('templates/admin/partials/footer.php'); ?>
