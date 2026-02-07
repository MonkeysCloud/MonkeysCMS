@extends('layouts/main')

@section('meta')
<?php
$pageTitle = ($title ?? 'Content') . ' | MonkeysCMS';
$description = $meta['description'] ?? '';
?>
    <meta property="og:title" content="<?= htmlspecialchars($title ?? '') ?>">
    <meta property="og:type" content="article">
    <?php if ($description): ?>
    <meta property="og:description" content="<?= htmlspecialchars($description) ?>">
    <meta name="description" content="<?= htmlspecialchars($description) ?>">
    <?php endif; ?>
@endsection

@section('content')
    <!-- Main Content -->
    <main class="flex-grow w-full max-w-3xl mx-auto px-4 py-12 sm:py-16">
        
        <!-- Article Header -->
        <header class="mb-10 text-center sm:text-left">
            <div class="flex flex-wrap items-center justify-center sm:justify-start gap-3 text-sm text-gray-500 mb-4">
                <a href="/" class="hover:text-amber-600 transition-colors">Home</a>
                <span class="text-gray-300">/</span>
                <span class="font-medium text-amber-600 uppercase tracking-wider text-xs"><?= htmlspecialchars($type['label'] ?? ucfirst($type_id)) ?></span>
                
                <?php if ($canEdit): ?>
                <span class="text-gray-300 ml-2">|</span>
                <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-1 ml-2">
                    <span class="bg-white text-indigo-600 shadow-sm px-3 py-0.5 rounded-md text-xs font-bold uppercase tracking-wide">View</span>
                    <a href="/admin/content/<?= $type_id ?>/<?= $item['id'] ?>/edit" class="text-gray-500 hover:text-indigo-600 hover:bg-white/50 px-3 py-0.5 rounded-md text-xs font-medium uppercase tracking-wide transition-all">Edit</a>
                </div>
                <?php endif; ?>
            </div>
            
            <h1 class="text-4xl sm:text-5xl font-extrabold text-slate-900 mb-6 tracking-tight tight-leading">
                <?= htmlspecialchars($title) ?>
            </h1>

            <div class="flex items-center justify-center sm:justify-start gap-4 text-sm text-gray-500 border-b border-gray-100 pb-8">
                <?php if ($created_at): ?>
                <time datetime="<?= $created_at ?>" class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <?= date('F j, Y', strtotime($created_at)) ?>
                </time>
                <?php endif; ?>
                
                <?php if ($status === 'draft'): ?>
                <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-0.5 rounded-full font-medium">Draft</span>
                <?php endif; ?>
            </div>
        </header>

        <!-- Article Body -->
        <article class="prose prose-lg prose-slate max-w-none prose-img:rounded-xl prose-img:shadow-md">
            <?php 
            // Get all fields
            $allFields = $fieldRenderer->filled();
            
            foreach ($allFields as $fieldName => $field):
                $isBodyField = str_contains($fieldName, 'body') || str_contains($fieldName, 'content');
            ?>
                <?php if ($isBodyField): ?>
                    <?= $field['value'] ?>
                <?php else: ?>
                    <div class="mb-8 not-prose p-6 bg-gray-50 rounded-lg border border-gray-100">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">
                            <?= htmlspecialchars($field['label']) ?>
                        </h3>
                        <div class="text-gray-900 font-medium">
                            <?= $field['value'] ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </article>

        <!-- Footer Meta -->
        <div class="mt-16 pt-8 border-t border-gray-100 flex justify-between items-center text-sm text-gray-400">
            <div>
                ID: <span class="font-mono"><?= $item['id'] ?></span>
            </div>
            <a href="/" class="flex items-center gap-2 hover:text-amber-600 transition-colors font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to Home
            </a>
        </div>

    </main>
@endsection
