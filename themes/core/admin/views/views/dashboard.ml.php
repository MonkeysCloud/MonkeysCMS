@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')

@section('breadcrumb')
<span class="breadcrumb__item breadcrumb__item--active">Dashboard</span>
@endsection

@section('content')
<div id="dashboard-app">

  {{-- ══════════════════════════════════════════════════════════════════════
       STAT CARDS
       ══════════════════════════════════════════════════════════════════════ --}}
  @php $totalContent = 0; @endphp
  @foreach($stats['content'] ?? [] as $type => $statuses)
    @php $totalContent += array_sum($statuses); @endphp
  @endforeach

  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">

    {{-- Total Content --}}
    <div class="dash-stat dash-stat--indigo relative overflow-hidden rounded-2xl border border-white/5 bg-white/[0.03] backdrop-blur-xl p-5 transition-all duration-300 hover:-translate-y-0.5 hover:border-white/10 hover:shadow-xl">
      <div class="dash-stat__glow"></div>
      <div class="relative z-10">
        <div class="flex items-center justify-between mb-3">
          <span class="text-[0.65rem] uppercase tracking-widest font-semibold text-slate-400">Total Content</span>
          <span class="text-[0.6rem] px-2 py-0.5 rounded-full bg-white/5 text-slate-500 uppercase tracking-wide">All types</span>
        </div>
        <div class="text-4xl font-extrabold leading-none mb-1 tracking-tight text-indigo-400">{{ $totalContent }}</div>
        <div class="text-xs text-slate-500">Published & draft items</div>
      </div>
      <div class="absolute bottom-4 right-4 opacity-10 text-indigo-500">
        <i data-lucide="file-text" class="w-12 h-12"></i>
      </div>
    </div>

    {{-- Media Files --}}
    <div class="dash-stat dash-stat--purple relative overflow-hidden rounded-2xl border border-white/5 bg-white/[0.03] backdrop-blur-xl p-5 transition-all duration-300 hover:-translate-y-0.5 hover:border-white/10 hover:shadow-xl">
      <div class="dash-stat__glow"></div>
      <div class="relative z-10">
        <div class="flex items-center justify-between mb-3">
          <span class="text-[0.65rem] uppercase tracking-widest font-semibold text-slate-400">Media Files</span>
          <span class="text-[0.6rem] px-2 py-0.5 rounded-full bg-white/5 text-slate-500 uppercase tracking-wide">Library</span>
        </div>
        <div class="text-4xl font-extrabold leading-none mb-1 tracking-tight text-violet-400">{{ $stats['media'] ?? 0 }}</div>
        <div class="text-xs text-slate-500">Images & documents</div>
      </div>
      <div class="absolute bottom-4 right-4 opacity-10 text-violet-500">
        <i data-lucide="image" class="w-12 h-12"></i>
      </div>
    </div>

    {{-- Active Users --}}
    <div class="dash-stat dash-stat--emerald relative overflow-hidden rounded-2xl border border-white/5 bg-white/[0.03] backdrop-blur-xl p-5 transition-all duration-300 hover:-translate-y-0.5 hover:border-white/10 hover:shadow-xl">
      <div class="dash-stat__glow"></div>
      <div class="relative z-10">
        <div class="flex items-center justify-between mb-3">
          <span class="text-[0.65rem] uppercase tracking-widest font-semibold text-slate-400">Active Users</span>
          <span class="text-[0.6rem] px-2 py-0.5 rounded-full bg-white/5 text-slate-500 uppercase tracking-wide">Team</span>
        </div>
        <div class="text-4xl font-extrabold leading-none mb-1 tracking-tight text-emerald-400">{{ $stats['users'] ?? 0 }}</div>
        <div class="text-xs text-slate-500">Registered accounts</div>
      </div>
      <div class="absolute bottom-4 right-4 opacity-10 text-emerald-500">
        <i data-lucide="users" class="w-12 h-12"></i>
      </div>
    </div>

    {{-- Content Types --}}
    <div class="dash-stat dash-stat--blue relative overflow-hidden rounded-2xl border border-white/5 bg-white/[0.03] backdrop-blur-xl p-5 transition-all duration-300 hover:-translate-y-0.5 hover:border-white/10 hover:shadow-xl">
      <div class="dash-stat__glow"></div>
      <div class="relative z-10">
        <div class="flex items-center justify-between mb-3">
          <span class="text-[0.65rem] uppercase tracking-widest font-semibold text-slate-400">Content Types</span>
          <span class="text-[0.6rem] px-2 py-0.5 rounded-full bg-white/5 text-slate-500 uppercase tracking-wide">Schema</span>
        </div>
        <div class="text-4xl font-extrabold leading-none mb-1 tracking-tight text-blue-400">{{ count($stats['content'] ?? []) }}</div>
        <div class="text-xs text-slate-500">Defined structures</div>
      </div>
      <div class="absolute bottom-4 right-4 opacity-10 text-blue-500">
        <i data-lucide="settings" class="w-12 h-12"></i>
      </div>
    </div>
  </div>

  {{-- ══════════════════════════════════════════════════════════════════════
       MIDDLE ROW — Content Overview + Quick Actions
       ══════════════════════════════════════════════════════════════════════ --}}
  <div class="grid grid-cols-1 lg:grid-cols-[1.6fr_1fr] gap-4 mb-4">

    {{-- Content Overview --}}
    <div class="rounded-2xl border border-white/5 bg-white/[0.03] backdrop-blur-xl overflow-hidden">
      <div class="flex items-center justify-between px-5 py-4 border-b border-white/5">
        <h3 class="text-sm font-semibold text-slate-300 flex items-center gap-2">
          <i data-lucide="layout-grid" class="w-4 h-4 text-slate-500"></i>
          Content Overview
        </h3>
      </div>
      <div>
        <table class="w-full border-collapse">
          <thead>
            <tr>
              <th class="text-left px-5 py-3 text-[0.68rem] font-semibold text-slate-500 uppercase tracking-wider border-b border-white/[0.04]">Type</th>
              <th class="text-left px-5 py-3 text-[0.68rem] font-semibold text-slate-500 uppercase tracking-wider border-b border-white/[0.04]">Published</th>
              <th class="text-left px-5 py-3 text-[0.68rem] font-semibold text-slate-500 uppercase tracking-wider border-b border-white/[0.04]">Draft</th>
              <th class="text-left px-5 py-3 text-[0.68rem] font-semibold text-slate-500 uppercase tracking-wider border-b border-white/[0.04]">Total</th>
            </tr>
          </thead>
          <tbody>
            @foreach($stats['content'] ?? [] as $type => $statuses)
            <tr class="transition-colors hover:bg-indigo-500/[0.04]">
              <td class="px-5 py-3 text-sm border-b border-white/[0.03]">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-white/5 text-slate-300 capitalize">{{ $type }}</span>
              </td>
              <td class="px-5 py-3 text-sm border-b border-white/[0.03]"><span class="badge badge--success">{{ $statuses['published'] ?? 0 }}</span></td>
              <td class="px-5 py-3 text-sm border-b border-white/[0.03]"><span class="badge badge--warning">{{ $statuses['draft'] ?? 0 }}</span></td>
              <td class="px-5 py-3 text-sm border-b border-white/[0.03] font-semibold">{{ array_sum($statuses) }}</td>
            </tr>
            @endforeach
            @empty($stats['content'])
            <tr>
              <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">
                <div class="flex items-center justify-center gap-2">
                  <i data-lucide="file-x" class="w-5 h-5 opacity-40"></i>
                  No content types defined yet
                </div>
              </td>
            </tr>
            @endempty
          </tbody>
        </table>
      </div>
    </div>

    {{-- Quick Actions --}}
    <div class="rounded-2xl border border-white/5 bg-white/[0.03] backdrop-blur-xl overflow-hidden">
      <div class="flex items-center justify-between px-5 py-4 border-b border-white/5">
        <h3 class="text-sm font-semibold text-slate-300 flex items-center gap-2">
          <i data-lucide="zap" class="w-4 h-4 text-slate-500"></i>
          Quick Actions
        </h3>
      </div>
      <div class="p-4">
        <div class="grid grid-cols-2 gap-3">
          <a href="/admin/content/create/article" class="group flex flex-col items-center gap-2 p-4 rounded-xl border border-white/5 bg-white/[0.02] transition-all duration-250 hover:-translate-y-0.5 hover:border-indigo-500/30 hover:shadow-[0_4px_20px_rgba(99,102,241,0.15)] no-underline">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center bg-indigo-500/10 text-indigo-400">
              <i data-lucide="pen-line" class="w-5 h-5"></i>
            </div>
            <span class="text-xs font-medium text-slate-400">New Article</span>
          </a>
          <a href="/admin/content/create/page" class="group flex flex-col items-center gap-2 p-4 rounded-xl border border-white/5 bg-white/[0.02] transition-all duration-250 hover:-translate-y-0.5 hover:border-violet-500/30 hover:shadow-[0_4px_20px_rgba(139,92,246,0.15)] no-underline">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center bg-violet-500/10 text-violet-400">
              <i data-lucide="file-plus" class="w-5 h-5"></i>
            </div>
            <span class="text-xs font-medium text-slate-400">New Page</span>
          </a>
          <a href="/admin/media" class="group flex flex-col items-center gap-2 p-4 rounded-xl border border-white/5 bg-white/[0.02] transition-all duration-250 hover:-translate-y-0.5 hover:border-emerald-500/30 hover:shadow-[0_4px_20px_rgba(16,185,129,0.15)] no-underline">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center bg-emerald-500/10 text-emerald-400">
              <i data-lucide="image" class="w-5 h-5"></i>
            </div>
            <span class="text-xs font-medium text-slate-400">Media Library</span>
          </a>
          <a href="/admin/appearance" class="group flex flex-col items-center gap-2 p-4 rounded-xl border border-white/5 bg-white/[0.02] transition-all duration-250 hover:-translate-y-0.5 hover:border-pink-500/30 hover:shadow-[0_4px_20px_rgba(236,72,153,0.15)] no-underline">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center bg-pink-500/10 text-pink-400">
              <i data-lucide="paintbrush" class="w-5 h-5"></i>
            </div>
            <span class="text-xs font-medium text-slate-400">Appearance</span>
          </a>
          <a href="/admin/menus" class="group flex flex-col items-center gap-2 p-4 rounded-xl border border-white/5 bg-white/[0.02] transition-all duration-250 hover:-translate-y-0.5 hover:border-amber-500/30 hover:shadow-[0_4px_20px_rgba(245,158,11,0.15)] no-underline">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center bg-amber-500/10 text-amber-400">
              <i data-lucide="menu" class="w-5 h-5"></i>
            </div>
            <span class="text-xs font-medium text-slate-400">Menus</span>
          </a>
          <a href="/admin/settings" class="group flex flex-col items-center gap-2 p-4 rounded-xl border border-white/5 bg-white/[0.02] transition-all duration-250 hover:-translate-y-0.5 hover:border-blue-500/30 hover:shadow-[0_4px_20px_rgba(59,130,246,0.15)] no-underline">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center bg-blue-500/10 text-blue-400">
              <i data-lucide="settings" class="w-5 h-5"></i>
            </div>
            <span class="text-xs font-medium text-slate-400">Settings</span>
          </a>
        </div>
      </div>
    </div>
  </div>

  {{-- ══════════════════════════════════════════════════════════════════════
       RECENT CONTENT
       ══════════════════════════════════════════════════════════════════════ --}}
  <div class="rounded-2xl border border-white/5 bg-white/[0.03] backdrop-blur-xl overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-white/5">
      <h3 class="text-sm font-semibold text-slate-300 flex items-center gap-2">
        <i data-lucide="clock" class="w-4 h-4 text-slate-500"></i>
        Recent Content
      </h3>
      <a href="/admin/content" class="text-xs font-medium text-indigo-400 hover:text-indigo-300 no-underline transition-colors">View All →</a>
    </div>
    <div>
      @isset($recent)
      <table class="w-full border-collapse">
        <thead>
          <tr>
            <th class="text-left px-5 py-3 text-[0.68rem] font-semibold text-slate-500 uppercase tracking-wider border-b border-white/[0.04]">Title</th>
            <th class="text-left px-5 py-3 text-[0.68rem] font-semibold text-slate-500 uppercase tracking-wider border-b border-white/[0.04]">Type</th>
            <th class="text-left px-5 py-3 text-[0.68rem] font-semibold text-slate-500 uppercase tracking-wider border-b border-white/[0.04]">Status</th>
            <th class="text-left px-5 py-3 text-[0.68rem] font-semibold text-slate-500 uppercase tracking-wider border-b border-white/[0.04]">Updated</th>
          </tr>
        </thead>
        <tbody>
          @foreach($recent as $node)
          <tr class="transition-colors hover:bg-indigo-500/[0.04]">
            <td class="px-5 py-3 text-sm border-b border-white/[0.03]">
              <a href="/admin/content/{{ $node->id }}/edit" class="font-medium text-slate-200 hover:text-indigo-400 no-underline transition-colors">{{ $node->title }}</a>
            </td>
            <td class="px-5 py-3 text-sm border-b border-white/[0.03]">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-white/5 text-slate-300 capitalize">{{ $node->content_type ?? 'article' }}</span>
            </td>
            <td class="px-5 py-3 text-sm border-b border-white/[0.03]"><span class="badge badge--{{ $node->status ?? 'draft' }}">{{ ucfirst($node->status ?? 'draft') }}</span></td>
            <td class="px-5 py-3 text-xs text-slate-500 border-b border-white/[0.03]">{{ $node->updated_at ?? '' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @else
      <div class="text-center py-16 px-8">
        <div class="mb-4 text-slate-500">
          <i data-lucide="file-plus-2" class="w-12 h-12 mx-auto opacity-30"></i>
        </div>
        <h4 class="text-lg font-semibold text-slate-200 mb-2">No content yet</h4>
        <p class="text-sm text-slate-500 mb-5">Create your first piece of content to get started.</p>
        <a href="/admin/content/create/article" class="btn btn--primary inline-flex items-center gap-2">
          <i data-lucide="plus" class="w-3.5 h-3.5"></i>
          Create Content
        </a>
      </div>
      @endisset
    </div>
  </div>
</div>
@endsection
