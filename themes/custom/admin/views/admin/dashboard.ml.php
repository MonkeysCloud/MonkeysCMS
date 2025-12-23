@extends('layouts.admin')

@section('content')
    <h2 style="margin-top: 0;">Dashboard</h2>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value">{{ $modules['enabled'] }} / {{ $modules['available'] }}</div>
            <div class="stat-label">Modules Active</div>
        </div>
        
        @foreach($content as $type => $count)
        <div class="stat-card">
            <div class="stat-value">{{ $count }}</div>
            <div class="stat-label">{{ $type }}</div>
        </div>
        @endforeach
    </div>

    <div class="card" style="margin-top: 40px;">
        <h3>System Info</h3>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;">PHP Version</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-weight: bold;">{{ $system['php_version'] }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;">CMS Version</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-weight: bold;">{{ $system['cms_version'] }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;">Memory Usage</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-weight: bold;">{{ $system['memory_usage'] }} (Peak: {{ $system['peak_memory'] }})</td>
            </tr>
        </table>
    </div>
@endsection
