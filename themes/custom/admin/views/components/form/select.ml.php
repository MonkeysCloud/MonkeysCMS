@props(['disabled' => false, 'label' => null, 'name', 'options' => [], 'selected' => null, 'help' => null, 'placeholder' => null, 'id' => null])

<div class="mb-4">
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 mb-1">
            {{ $label }}
        </label>
    @endif
    
    <select @disabled($disabled) 
            name="{{ $name }}" 
            id="{{ $id ?? $name }}"
            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 disabled:bg-gray-100">
        
        @if($placeholder)
             <option value="">{{ $placeholder }}</option>
        @endif
        
        @foreach($options as $value => $optLabel)
             <option value="{{ $value }}" <?= ((string)$value === (string)$selected) ? 'selected' : '' ?>>{{ $optLabel }}</option>
        @endforeach
        
        {{ $slot }}
    </select>
    
    @if($help)
        <p class="mt-2 text-sm text-gray-500">{{ $help }}</p>
    @endif
</div>
