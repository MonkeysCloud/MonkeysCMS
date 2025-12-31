<div {{ $attributes->merge(['class' => 'bg-white rounded-xl shadow-sm border border-gray-200/60 overflow-hidden']) }}>
    <div class="p-6">
        {{ $slot }}
    </div>
</div>
