<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->e($this->section('title', $appName ?? 'WebklientApp')) ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f9fafb; color: #1f2937; line-height: 1.6; }
        .navbar { background: #1e293b; color: #fff; padding: 0 24px; display: flex; align-items: center; height: 56px; }
        .navbar h1 { font-size: 18px; margin: 0; font-weight: 600; }
        .navbar nav { margin-left: auto; display: flex; gap: 16px; }
        .navbar a { color: #94a3b8; text-decoration: none; font-size: 14px; }
        .navbar a:hover { color: #fff; }
        .container { max-width: 1200px; margin: 0 auto; padding: 32px 24px; }
        .card { background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 24px; }
        .card h2 { margin-top: 0; font-size: 18px; color: #111827; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
        th { background: #f9fafb; font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 12px; letter-spacing: 0.05em; }
        .btn { display: inline-block; padding: 8px 20px; background: #2563eb; color: #fff; border: none; border-radius: 6px; font-size: 14px; font-weight: 500; text-decoration: none; cursor: pointer; }
        .btn:hover { background: #1d4ed8; }
        .btn-sm { padding: 4px 12px; font-size: 13px; }
        .btn-danger { background: #dc2626; }
        .btn-danger:hover { background: #b91c1c; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .footer { text-align: center; padding: 24px; color: #9ca3af; font-size: 13px; }
    </style>
    <?= $this->section('head') ?>
</head>
<body>
    <div class="navbar">
        <h1><?= $this->e($appName ?? 'WebklientApp') ?></h1>
        <nav>
            <?= $this->section('nav', '<a href="/dashboard">Dashboard</a><a href="/users">Uživatelé</a>') ?>
        </nav>
    </div>
    <div class="container">
        <?= $this->content() ?>
    </div>
    <div class="footer">
        &copy; <?= date('Y') ?> <?= $this->e($appName ?? 'WebklientApp') ?>
    </div>
    <?= $this->section('scripts') ?>
</body>
</html>
