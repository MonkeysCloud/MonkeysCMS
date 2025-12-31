@extends('layouts.admin')

@section('content')
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Dashboard</h2>
            <p class="mt-1 text-sm text-gray-500">Overview of your CMS activity.</p>
        </div>
        <div>
            <x-ui.button href="/" target="_blank" color="secondary">
                View Website &rarr;
            </x-ui.button>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <x-ui.card class="bg-gradient-to-br from-blue-500 to-blue-600 border-none text-white">
            <div class="text-blue-100 text-sm font-medium mb-1">Total Users</div>
            <div class="text-3xl font-bold">{{ $stats['users'] ?? 0 }}</div>
        </x-ui.card>
        
    <x-ui.card>
        <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">System Status</h3>
        <table class="min-w-full">
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee;">Memory Usage</td>
                <td style="padding: 8px 0; border-bottom: 1px solid #eee; font-weight: bold;">{{ $system['memory_usage'] }} (Peak: {{ $system['peak_memory'] }})</td>
            </tr>
        </table>
    </x-ui.card>
    </div>
@endsection
