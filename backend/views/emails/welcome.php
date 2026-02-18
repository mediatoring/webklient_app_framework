<?php $this->layout('layouts.email'); ?>

<?php $this->beginSection('title'); ?>Vítejte<?php $this->endSection(); ?>

<p>Dobrý den<?= !empty($displayName) ? ', ' . $this->e($displayName) : '' ?>.</p>

<p>Váš účet v aplikaci <strong><?= $this->e($appName) ?></strong> byl úspěšně vytvořen.</p>

<p>Vaše přihlašovací údaje:</p>
<ul>
    <li><strong>Uživatelské jméno:</strong> <?= $this->e($username) ?></li>
</ul>

<p style="text-align: center; margin: 32px 0;">
    <a href="<?= $this->e($loginUrl) ?>" class="btn">Přihlásit se</a>
</p>

<p class="muted">Doporučujeme si po prvním přihlášení změnit heslo.</p>
