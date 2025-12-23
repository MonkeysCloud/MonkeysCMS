<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MonkeysCMS Admin</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; background: #f4f5f7; display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #2d333b; color: #fff; padding: 20px; display: flex; flex-direction: column; }
        .sidebar h1 { font-size: 1.2rem; margin-bottom: 2rem; color: #adbac7; }
        .sidebar a { color: #adbac7; text-decoration: none; padding: 10px 0; display: block; }
        .sidebar a:hover { color: #fff; }
        .main { flex: 1; padding: 40px; }
        .card { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-value { font-size: 2rem; font-weight: bold; color: #2d333b; }
        .stat-label { color: #6b7280; font-size: 0.875rem; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 2rem;">
            <img src="/themes/custom/admin/assets/images/icon-monkey.png" alt="MonkeysCMS Logo" style="width: 40px; height: 40px; object-fit: contain;">
            <h1 style="margin: 0; font-size: 1.2rem; line-height: 1;">MonkeysCMS</h1>
        </div>
        @if(!empty($admin_menu_tree))
            @foreach($admin_menu_tree as $item)
                <a href="{{ $item->getResolvedUrl() }}">{{ $item->title }}</a>
                @if(!empty($item->children))
                    <div style="margin-left: 15px; font-size: 0.9em;">
                        @foreach($item->children as $child)
                            <a href="{{ $child->getResolvedUrl() }}">{{ $child->title }}</a>
                        @endforeach
                    </div>
                @endif
            @endforeach
        @else
            <!-- Fallback if menu is missing -->
            <a href="/admin/dashboard">Dashboard</a>
            <a href="/admin/users">Users</a>
            <a href="/admin/settings">Settings</a>
        @endif
        <a href="/" target="_blank" style="margin-top: auto;">View Site &rarr;</a>
    </div>
    <div class="main">
        @yield('content')
    </div>
</body>
</html>
