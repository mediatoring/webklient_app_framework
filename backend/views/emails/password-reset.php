<?php $this->layout('layouts.email'); ?>

<?php $this->beginSection('title'); ?>Obnovení hesla<?php $this->endSection(); ?>

<p>Dobrý den<?= !empty($displayName) ? ', ' . $this->e($displayName) : '' ?>.</p>

<p>Obdrželi jsme žádost o obnovení hesla k vašemu účtu. Klikněte na tlačítko níže pro nastavení nového hesla:</p>

<p style="text-align: center; margin: 32px 0;">
    <a href="<?= $this->e($resetUrl) ?>" class="btn">Nastavit nové heslo</a>
</p>

<p class="muted">
    Odkaz je platný <?= (int) $expiresMinutes ?> minut. Pokud jste o obnovení hesla nežádali, tento email můžete ignorovat.
</p>

<p class="muted">
    Pokud tlačítko nefunguje, zkopírujte do prohlížeče tento odkaz:<br>
    <a href="<?= $this->e($resetUrl) ?>"><?= $this->e($resetUrl) ?></a>
</p>
