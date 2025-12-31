@props(['type' => 'button', 'color' => 'primary', 'href' => null])

@php
$baseClasses = 'inline-flex items-center px-4 py-2 border text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors duration-200';

$variants = [
    'primary' => 'border-transparent text-white bg-blue-600 hover:bg-blue-700 focus:ring-blue-500',
    'secondary' => 'border-gray-300 text-gray-700 bg-white hover:bg-gray-50 focus:ring-blue-500',
    'danger' => 'border-transparent text-white bg-red-600 hover:bg-red-700 focus:ring-red-500',
    'ghost' => 'border-transparent text-gray-500 bg-transparent hover:bg-gray-100 hover:text-gray-900 focus:ring-gray-500 shadow-none',
];

$classes = $baseClasses . ' ' . ($variants[$color] ?? $variants['primary']);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
