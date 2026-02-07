@props(['disabled' => false, 'label' => null, 'name', 'rows' => 3, 'help' => null, 'id' => null])

<div class="mb-4">
    @if($label)
        <label for="{{ $id ?? $name }}" class="block text-sm font-medium text-gray-700 mb-1">
            {{ $label }}
        </label>
    @endif
    
    <textarea @disabled($disabled) 
              name="{{ $name }}" 
              id="{{ $id ?? $name }}"
              rows="{{ $rows }}"
              {{ $attributes->merge(['class' => 'block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 disabled:bg-gray-100 disabled:text-gray-500']) }}>{{ $slot }}</textarea>
              
    @if($help)
        <p class="mt-1 text-sm text-gray-500">{{ $help }}</p>
    @endif
</div>
