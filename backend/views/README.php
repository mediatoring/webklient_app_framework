<?php
/**
 * =================================================================
 * PRŮVODCE VIEW SYSTÉMEM — WebklientApp Framework
 * =================================================================
 *
 * Framework obsahuje vlastní template engine (ViewRenderer), který
 * renderuje PHP šablony s podporou layoutů, sekcí a partial vkládání.
 *
 *
 * ── STRUKTURA SLOŽEK ──────────────────────────────────────────────
 *
 *   views/
 *   ├── layouts/
 *   │   ├── app.php            ← HTML layout pro webové stránky
 *   │   └── email.php          ← HTML layout pro emaily
 *   ├── emails/
 *   │   ├── password-reset.php ← Šablona pro reset hesla
 *   │   ├── welcome.php        ← Uvítací email
 *   │   └── notification.php   ← Obecná notifikace
 *   ├── examples/
 *   │   ├── dashboard.php      ← Ukázkový dashboard
 *   │   ├── users-list.php     ← Ukázkový výpis uživatelů
 *   │   └── user-form.php      ← Ukázkový formulář
 *   └── README.php             ← Tento soubor
 *
 *
 * ── POUŽITÍ V CONTROLLERU ─────────────────────────────────────────
 *
 *   use WebklientApp\Core\View\ViewRenderer;
 *   use WebklientApp\Core\Http\JsonResponse;
 *
 *   class DashboardController extends BaseController
 *   {
 *       public function index(Request $request): JsonResponse
 *       {
 *           // Pro API odpověď (JSON):
 *           return JsonResponse::success(['stats' => $stats]);
 *
 *           // Pro HTML odpověď (view):
 *           $view = new ViewRenderer();
 *           $html = $view->render('examples.dashboard', [
 *               'user' => $currentUser,
 *               'stats' => $stats,
 *               'recentActivity' => $activities,
 *           ]);
 *           // Vrátit HTML:
 *           return new JsonResponse(['html' => $html]);
 *           // Nebo přímo echo $html; exit; pro server-side rendering
 *       }
 *   }
 *
 *
 * ── SYNTAXE ŠABLON ────────────────────────────────────────────────
 *
 *   Šablony jsou běžné PHP soubory. $this odkazuje na ViewRenderer.
 *
 *   LAYOUT:
 *     <?php $this->layout('layouts.app'); ?>
 *
 *   SEKCE (definice v child šabloně):
 *     <?php $this->beginSection('title'); ?>
 *     Nadpis stránky
 *     <?php $this->endSection(); ?>
 *
 *   SEKCE (výpis v layoutu):
 *     <?= $this->section('title', 'Výchozí') ?>
 *
 *   OBSAH CHILD ŠABLONY (v layoutu):
 *     <?= $this->content() ?>
 *
 *   ESCAPOVÁNÍ:
 *     <?= $this->e($proměnná) ?>
 *
 *   RAW VÝSTUP (bez escapování, pro HTML z DB):
 *     <?= $htmlContent ?>
 *
 *   PARTIAL (vložení jiné šablony):
 *     <?= $this->partial('emails.footer', ['year' => 2025]) ?>
 *
 *   PODMÍNKY A CYKLY:
 *     <?php if (!empty($items)): ?>
 *       <?php foreach ($items as $item): ?>
 *         <p><?= $this->e($item['name']) ?></p>
 *       <?php endforeach; ?>
 *     <?php endif; ?>
 *
 *
 * ── ODESÍLÁNÍ EMAILŮ ──────────────────────────────────────────────
 *
 *   use WebklientApp\Core\Mail\MailService;
 *
 *   $mail = new MailService();
 *
 *   // Obnovení hesla:
 *   $mail->sendPasswordReset('jan@example.com', 'Jan', $token);
 *
 *   // Uvítací email:
 *   $mail->sendWelcome('jan@example.com', 'Jan Novák', 'jan');
 *
 *   // Vlastní notifikace:
 *   $mail->sendNotification(
 *       'jan@example.com',
 *       'Jan',
 *       'Nový úkol',
 *       '<p>Byl vám přidělen nový úkol.</p>',
 *       'https://app.example.com/tasks/42',
 *       'Zobrazit úkol'
 *   );
 *
 *   // Vlastní šablona:
 *   $mail->send(
 *       'jan@example.com',
 *       'Předmět emailu',
 *       'emails.moje-sablona',          // views/emails/moje-sablona.php
 *       ['klíč' => 'hodnota'],
 *       'Jan Novák'
 *   );
 *
 *
 * ── SMTP KONFIGURACE (.env) ──────────────────────────────────────
 *
 *   MAIL_HOST=smtp.example.com
 *   MAIL_PORT=587
 *   MAIL_USERNAME=user@example.com
 *   MAIL_PASSWORD=tajne-heslo
 *   MAIL_ENCRYPTION=tls           # tls | ssl | (prázdné)
 *   MAIL_FROM_ADDRESS=noreply@example.com
 *   MAIL_FROM_NAME=MojeAplikace
 *   MAIL_TIMEOUT=30
 *
 *
 * ── VYTVOŘENÍ VLASTNÍ ŠABLONY ────────────────────────────────────
 *
 *   1. Vytvořte soubor views/emails/moje-sablona.php
 *   2. Na začátku nastavte layout:
 *        <?php $this->layout('layouts.email'); ?>
 *   3. Definujte obsah s proměnnými z $data:
 *        <p>Dobrý den, <?= $this->e($jmeno) ?>.</p>
 *   4. Zavolejte z MailService:
 *        $mail->send($email, 'Předmět', 'emails.moje-sablona', ['jmeno' => 'Jan']);
 *
 */
