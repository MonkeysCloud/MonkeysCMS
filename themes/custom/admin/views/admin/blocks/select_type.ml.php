@extends('layouts/admin')

@section('content')
<div class="max-w-7xl mx-auto py-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Select Block Type</h1>
        <p class="mt-1 text-sm text-gray-500">Choose the type of block you want to create.</p>
    </div>

    <!-- Category Groups -->
    <?php foreach ($typesGrouped as $category => $types): ?>
        <div class="mb-8">
            <h2 class="text-lg font-medium text-gray-900 mb-4 border-b border-gray-200 pb-2"><?= htmlspecialchars($category) ?></h2>
            
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($types as $type): ?>
                    <a href="/admin/blocks/create/<?= htmlspecialchars($type['id']) ?>" 
                       class="relative group bg-white p-6 focus-within:ring-2 focus-within:ring-inset focus-within:ring-blue-500 hover:bg-gray-50 transition-colors duration-200 rounded-lg shadow-sm border border-gray-200">
                        <div class="flex items-center space-x-4">
                            <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 text-2xl">
                                <?= $type['icon'] ?? '🧱' ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="absolute inset-0" aria-hidden="true"></span>
                                <p class="text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($type['label']) ?>
                                </p>
                                <p class="text-sm text-gray-500 truncate">
                                    <?= htmlspecialchars($type['description'] ?? '') ?>
                                </p>
                            </div>
                            <div class="flex-shrink-0 self-center">
                                <svg class="h-5 w-5 text-gray-400 group-hover:text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="mt-6 flex justify-start">
        <x-ui.button href="/admin/blocks" color="secondary">
            Cancel
        </x-ui.button>
    </div>
</div>
@endsection
