@props(['disabled' => false, 'label' => null, 'name', 'error' => null, 'help' => null, 'id' => null, 'min' => null, 'max' => null, 'step' => null])

<div class="mb-4">
    @if($label)
        <label for="{{ $id ?? $name }}" class="block text-sm font-medium text-gray-700 mb-1">
            {{ $label }}
        </label>
    @endif
    
    <div class="relative rounded-md shadow-sm">
        <input type="number"
               @disabled($disabled) 
               name="{{ $name }}" 
               id="{{ $id ?? $name }}"
               @if($min !== null) min="{{ $min }}" @endif
               @if($max !== null) max="{{ $max }}" @endif
               @if($step !== null) step="{{ $step }}" @endif
               {{ $attributes->merge(['class' => 'block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 disabled:bg-gray-50 disabled:text-gray-500 transition-colors duration-200 ' . ($error ? 'border-red-300 text-red-900 placeholder-red-300 focus:border-red-500 focus:ring-red-500' : '')]) }}>
               
        @if($error)
            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            </div>
        @endif
    </div>
    
    @if($error)
        <p class="mt-1 text-sm text-red-600">{{ $error }}</p>
    @elseif($help)
        <p class="mt-1 text-sm text-gray-500">{{ $help }}</p>
    @endif
</div>
