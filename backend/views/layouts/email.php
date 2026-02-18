<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->e($this->section('title', $appName ?? 'WebklientApp')) ?></title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f6f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        .wrapper { width: 100%; background-color: #f4f6f9; padding: 40px 0; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background-color: #2563eb; padding: 32px 40px; text-align: center; }
        .header h1 { margin: 0; color: #ffffff; font-size: 22px; font-weight: 600; }
        .body { padding: 40px; color: #1f2937; font-size: 15px; line-height: 1.7; }
        .body p { margin: 0 0 16px; }
        .body a { color: #2563eb; }
        .btn { display: inline-block; padding: 12px 32px; background-color: #2563eb; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; margin: 8px 0; }
        .btn:hover { background-color: #1d4ed8; }
        .footer { padding: 24px 40px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #f3f4f6; }
        .footer a { color: #9ca3af; text-decoration: underline; }
        .muted { color: #6b7280; font-size: 13px; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="container">
        <div class="header">
            <h1><?= $this->e($appName ?? 'WebklientApp') ?></h1>
        </div>
        <div class="body">
            <?= $this->content() ?>
        </div>
        <div class="footer">
            &copy; <?= date('Y') ?> <?= $this->e($appName ?? 'WebklientApp') ?>. Všechna práva vyhrazena.
            <br>
            <span class="muted">Tento email byl odeslán automaticky, neodpovídejte na něj.</span>
        </div>
    </div>
</div>
</body>
</html>
