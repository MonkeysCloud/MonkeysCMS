@props(['type' => 'info'])

@php
$colors = [
    'info' => 'bg-blue-50 border-blue-200 text-blue-700',
    'success' => 'bg-green-50 border-green-200 text-green-700',
    'warning' => 'bg-yellow-50 border-yellow-200 text-yellow-700',
    'error' => 'bg-red-50 border-red-200 text-red-700',
];

$icons = [
    'info' => '<svg class="h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    'success' => '<svg class="h-5 w-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    'warning' => '<svg class="h-5 w-5 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
    'error' => '<svg class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
];

$classes = "rounded-md p-4 border {$colors[$type]}";
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    <div class="flex">
        <div class="flex-shrink-0">
            {!! $icons[$type] !!}
        </div>
        <div class="ml-3">
            <div class="text-sm font-medium">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
