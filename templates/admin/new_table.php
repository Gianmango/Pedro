<?php $title = 'Nuovo ordine'; $active = 'tables'; require base_path('templates/admin/partials/header.php'); ?>

<h1>Nuovo ordine al tavolo</h1>
<form method="post" action="/admin/tables/new" class="card form table-create">
    <?= csrf_field() ?>
    <label>Nome o numero del tavolo
        <input name="identifier" maxlength="100" required autofocus placeholder="Es. Tavolo 12 o Veranda">
    </label>
    <p class="muted">Un numero libero seleziona il tavolo corrispondente; un nome crea un nuovo tavolo.</p>
    <div class="form__actions">
        <button class="btn btn--primary">Inizia ordine</button>
        <a class="btn" href="/admin/tables">Annulla</a>
    </div>
</form>

<?php require base_path('templates/admin/partials/footer.php'); ?>
