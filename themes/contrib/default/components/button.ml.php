@props([
    'type' => 'button',
    'variant' => 'primary',
    'size' => 'md',
    'disabled' => false,
    'href' => null,
    'class' => ''
])

@php
    $baseClasses = 'btn';
    $variantClasses = match($variant) {
        'primary' => 'btn-primary',
        'secondary' => 'btn-secondary',
        'success' => 'btn-success',
        'danger' => 'btn-danger',
        'warning' => 'btn-warning',
        'outline' => 'btn-outline',
        'ghost' => 'btn-ghost',
        default => 'btn-primary',
    };
    $sizeClasses = match($size) {
        'sm' => 'btn-sm',
        'lg' => 'btn-lg',
        default => '',
    };
    $allClasses = trim("{$baseClasses} {$variantClasses} {$sizeClasses} {$class}");
@endphp

@if($href)
    <a href="{{ $href }}" class="{{ $allClasses }}" {{ $attrs }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" class="{{ $allClasses }}" @if($disabled) disabled @endif {{ $attrs }}>
        {{ $slot }}
    </button>
@endif

<style>
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1;
    text-decoration: none;
    border: 1px solid transparent;
    border-radius: 0.375rem;
    cursor: pointer;
    transition: all 150ms ease;
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
}

.btn-lg {
    padding: 0.875rem 1.75rem;
    font-size: 1rem;
}

.btn-primary {
    background: var(--color-primary, #3b82f6);
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background: var(--color-primary-dark, #2563eb);
}

.btn-secondary {
    background: var(--color-secondary, #64748b);
    color: white;
}

.btn-secondary:hover:not(:disabled) {
    background: #475569;
}

.btn-success {
    background: var(--color-success, #22c55e);
    color: white;
}

.btn-success:hover:not(:disabled) {
    background: #16a34a;
}

.btn-danger {
    background: var(--color-danger, #ef4444);
    color: white;
}

.btn-danger:hover:not(:disabled) {
    background: #dc2626;
}

.btn-warning {
    background: var(--color-warning, #f59e0b);
    color: white;
}

.btn-warning:hover:not(:disabled) {
    background: #d97706;
}

.btn-outline {
    background: transparent;
    border-color: var(--color-primary, #3b82f6);
    color: var(--color-primary, #3b82f6);
}

.btn-outline:hover:not(:disabled) {
    background: var(--color-primary, #3b82f6);
    color: white;
}

.btn-ghost {
    background: transparent;
    color: var(--color-gray-600, #475569);
}

.btn-ghost:hover:not(:disabled) {
    background: var(--color-gray-100, #f1f5f9);
}
</style>
