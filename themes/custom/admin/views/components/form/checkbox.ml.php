@props(['disabled' => false, 'label' => null, 'name', 'checked' => false, 'help' => null, 'id' => null])

<div class="mb-4">
    <div class="relative flex items-start">
        <div class="flex h-5 items-center">
            <input type="checkbox"
                   name="{{ $name }}" 
                   id="{{ $id ?? $name }}"
                   @checked($checked)
                   @disabled($disabled) 
                   {{ $attributes->merge(['class' => 'h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-50']) }}>
        </div>
        <div class="ml-3 text-sm">
            @if($label)
                <label for="{{ $id ?? $name }}" class="font-medium text-gray-700">{{ $label }}</label>
            @endif
            @if($help)
                <p class="text-gray-500">{{ $help }}</p>
            @endif
        </div>
    </div>
</div>
