<?php $title = 'Richiesta non valida'; require base_path('templates/partials/header.php'); ?>

<?php if (!empty($tooBig)): ?>
    <p class="empty">Il file inviato è troppo grande (massimo 8 MB).<br>
        <a href="javascript:history.back()">Torna indietro</a> e scegli una foto più piccola.</p>
<?php else: ?>
    <p class="empty">La pagina è scaduta o la richiesta non è valida.<br>
        <a href="/admin">Torna al pannello</a> e riprova.</p>
<?php endif; ?>

<?php require base_path('templates/partials/footer.php'); ?>
