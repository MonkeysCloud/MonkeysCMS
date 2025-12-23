@extends('layouts.admin')

@section('content')
    <div style="margin-bottom: 20px;">
        <a href="/admin/menus" style="color: #0969da; text-decoration: none;">&larr; Back to Menus</a>
    </div>

    <div class="card" style="max-width: 600px;">
        <h2 style="margin-top: 0;">{{ $isNew ? 'Create Menu' : 'Edit Menu' }}</h2>

        <form method="post" action="{{ $isNew ? '/admin/menus' : '/admin/menus/' . $menu->id }}">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Name</label>
                <input type="text" name="name" value="{{ $menu->name }}" required style="width: 100%; padding: 8px; border: 1px solid #d0d7de; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Machine Name</label>
                <input type="text" name="machine_name" value="{{ $menu->machine_name }}" placeholder="Auto-generated if empty" style="width: 100%; padding: 8px; border: 1px solid #d0d7de; border-radius: 4px; font-family: monospace;">
                <div style="font-size: 0.85em; color: #6e7781; margin-top: 4px;">Unique identifier for this menu (e.g. 'main', 'footer').</div>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Description</label>
                <textarea name="description" rows="3" style="width: 100%; padding: 8px; border: 1px solid #d0d7de; border-radius: 4px;">{{ $menu->description }}</textarea>
            </div>

            <!-- Location field, hidden/default for now as user likely wants custom menus -->
            <input type="hidden" name="location" value="{{ $menu->location ?? 'custom' }}">

            <div style="margin-top: 20px;">
                <button type="submit" class="button button-primary" style="background: #2ea44f; color: #fff; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">
                    {{ $isNew ? 'Create Menu' : 'Save Changes' }}
                </button>
            </div>
        </form>
    </div>

    @if(!$isNew)
    <div class="card" style="margin-top: 20px; max-width: 800px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Menu Items</h3>
            <a href="/admin/menus/{{ $menu->id }}/items/create" class="button" style="background: #f6f8fa; border: 1px solid #d0d7de; padding: 5px 10px; border-radius: 6px; text-decoration: none; color: #24292f; font-size: 14px;">
                + Add Item
            </a>
        </div>

        @if(empty($menu->items))
            <p style="color: #6e7781; font-style: italic;">No items found. Add one to get started.</p>
        @else
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; background: #f6f8fa; border-bottom: 1px solid #d0d7de;">
                        <th style="padding: 10px;">Item</th>
                        <th style="padding: 10px;">URL</th>
                        <th style="padding: 10px; width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($menu->items as $item)
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px;">
                            <span style="color: #999;">{{ str_repeat('— ', $item->depth) }}</span>
                            @if($item->icon)
                                {{ $item->icon }}
                            @endif
                            <strong>{{ $item->title }}</strong>
                            @if(!$item->is_published)
                                <span style="font-size: 0.8em; background: #eee; padding: 2px 5px; border-radius: 4px;">Draft</span>
                            @endif
                        </td>
                        <td style="padding: 10px; font-family: monospace; font-size: 0.9em; color: #57606a;">
                            {{ $item->url ?? '-' }}
                        </td>
                        <td style="padding: 10px;">
                            <a href="/admin/menus/{{ $menu->id }}/items/{{ $item->id }}/edit" style="margin-right: 10px; color: #0969da; text-decoration: none;">Edit</a>
                            <a href="/admin/menus/{{ $menu->id }}/items/{{ $item->id }}/delete" onclick="return confirm('Are you sure?')" style="color: #cf222e; text-decoration: none;">Delete</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    @endif
@endsection
