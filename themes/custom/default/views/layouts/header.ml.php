<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'MonkeysCMS' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            /* Extracted Brand Colors */
            --primary: #ea8a0a;
            --primary-hover: #cf7a09;
            --secondary: #15225a;
            --text-main: #1f2937;
            --text-muted: #4b5563;
            --bg-body: #f3f4f6;
            --bg-card: #ffffff;
            --input-border: #d1d5db;
        }
        body {
            background-color: var(--bg-body);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--text-main);
        }
        .gradient-top {
            background: linear-gradient(135deg, var(--secondary) 0%, #1e3a8a 100%);
            height: 12px;
            width: 100%;
        }
        .logo-container {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .logo-img {
            height: 60px;
            width: auto;
        }
        .text-brand { color: var(--primary); }
        .text-secondary { color: var(--secondary); }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            box-shadow: 0 4px 6px -1px rgba(234, 138, 10, 0.2), 0 2px 4px -1px rgba(234, 138, 10, 0.1);
        }
        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(234, 138, 10, 0.3), 0 4px 6px -2px rgba(234, 138, 10, 0.15);
        }
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .input-group label {
            font-weight: 500;
            color: var(--text-main);
            margin-bottom: 0.375rem;
            display: block;
        }
        .input-field {
            width: 100%;
            padding: 0.625rem 1rem;
            border: 1px solid var(--input-border);
            border-radius: 0.5rem;
            outline: none;
            transition: all 0.2s;
            font-size: 0.95rem;
            color: var(--text-main);
            background-color: #fff;
        }
        .input-field:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(234, 138, 10, 0.15);
        }
        .input-field::placeholder {
            color: #9ca3af;
        }
        
        .card {
            background: var(--bg-card);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
            border-radius: 1rem;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        a.link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.15s;
        }
        a.link:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-lg card animated">
        <div class="gradient-top"></div>
        
        <div class="p-8 pb-0 text-center">
            <div class="logo-container">
                <img src="/image/monkeyscms-logo.png" alt="MonkeysCMS Logo" class="logo-img">
            </div>
            <h1 class="text-3xl font-bold mb-2 tracking-tight text-secondary"><?= $heading ?? 'Welcome' ?></h1>
            <?php if (isset($subheading)): ?>
                <p class="text-muted"><?= $subheading ?></p>
            <?php endif; ?>
        </div>

        <div class="p-8 space-y-6">
