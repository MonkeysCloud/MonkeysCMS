@props(['disabled' => false, 'label' => null, 'name', 'options' => [], 'selected' => null, 'help' => null])

<div class="mb-4">
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 mb-1">
            {{ $label }}
        </label>
    @endif
    
    <select @disabled($disabled) 
            name="{{ $name }}" 
            id="{{ $name }}"
            {{ $attributes->merge(['class' => 'block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 disabled:bg-gray-100']) }}>
        
        @foreach($options as $value => $label)
             <option value="{{ $value }}" @selected((string)$value === (string)$selected)>{{ $label }}</option>
        @endforeach
        
        {{ $slot }}
    </select>
    
    @if($help)
        <p class="mt-2 text-sm text-gray-500">{{ $help }}</p>
    @endif
</div>
