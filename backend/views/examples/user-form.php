<?php $this->layout('layouts.app'); ?>

<?php $this->beginSection('title'); ?>
<?= isset($editUser) ? 'Upravit uživatele' : 'Nový uživatel' ?>
<?php $this->endSection(); ?>

<div class="card">
    <h2><?= isset($editUser) ? 'Upravit uživatele' : 'Vytvořit uživatele' ?></h2>

    <?php if (!empty($errors)): ?>
    <div style="background: #fee2e2; border: 1px solid #fca5a5; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; color: #991b1b; font-size: 14px;">
        <strong>Opravte prosím následující chyby:</strong>
        <ul style="margin: 8px 0 0; padding-left: 20px;">
        <?php foreach ($errors as $field => $messages): ?>
            <?php foreach ((array) $messages as $msg): ?>
                <li><?= $this->e($msg) ?></li>
            <?php endforeach; ?>
        <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= isset($editUser) ? '/api/users/' . (int) $editUser['id'] : '/api/users' ?>" style="max-width: 500px;">
        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 14px; font-weight: 500; margin-bottom: 4px;">Uživatelské jméno</label>
            <input type="text" name="username"
                   value="<?= $this->e($editUser['username'] ?? $old['username'] ?? '') ?>"
                   style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;"
                   <?= isset($editUser) ? 'readonly' : 'required' ?>>
        </div>
        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 14px; font-weight: 500; margin-bottom: 4px;">Email</label>
            <input type="email" name="email"
                   value="<?= $this->e($editUser['email'] ?? $old['email'] ?? '') ?>"
                   style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;" required>
        </div>
        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 14px; font-weight: 500; margin-bottom: 4px;">Zobrazované jméno</label>
            <input type="text" name="display_name"
                   value="<?= $this->e($editUser['display_name'] ?? $old['display_name'] ?? '') ?>"
                   style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
        </div>
        <?php if (!isset($editUser)): ?>
        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 14px; font-weight: 500; margin-bottom: 4px;">Heslo</label>
            <input type="password" name="password"
                   style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;" required minlength="8">
        </div>
        <?php endif; ?>
        <div style="display: flex; gap: 12px; margin-top: 24px;">
            <button type="submit" class="btn"><?= isset($editUser) ? 'Uložit změny' : 'Vytvořit' ?></button>
            <a href="/users" class="btn" style="background: #6b7280;">Zpět</a>
        </div>
    </form>
</div>
