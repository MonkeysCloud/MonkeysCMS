@extends('layouts.admin')

@section('content')
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h2 style="margin: 0;">Menus</h2>
        <a href="/admin/menus/create" class="button button-primary" style="background: #2ea44f; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none;">Create Menu</a>
    </div>

    <div class="card">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="text-align: left; background: #f6f8fa;">
                    <th style="padding: 12px; border-bottom: 1px solid #d0d7de;">Name</th>
                    <th style="padding: 12px; border-bottom: 1px solid #d0d7de;">Machine Name</th>
                    <th style="padding: 12px; border-bottom: 1px solid #d0d7de;">Location</th>
                    <th style="padding: 12px; border-bottom: 1px solid #d0d7de;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($menus as $menu)
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <a href="/admin/menus/{{ $menu->id }}/edit" style="font-weight: bold; color: #0969da; text-decoration: none;">{{ $menu->name }}</a>
                        @if($menu->description)
                            <div style="font-size: 0.85em; color: #6e7781;">{{ $menu->description }}</div>
                        @endif
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;"><span style="font-family: monospace; background: #f6f8fa; padding: 2px 4px; border-radius: 4px;">{{ $menu->machine_name }}</span></td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">{{ $menu->location }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                        <a href="/admin/menus/{{ $menu->id }}/edit" style="margin-right: 10px; color: #0969da;">Edit</a>
                        <a href="/admin/menus/{{ $menu->id }}/delete" onclick="return confirm('Are you sure?')" style="color: #cf222e;">Delete</a>
                    </td>
                </tr>
                @endforeach
                
                @if(empty($menus))
                <tr>
                    <td colspan="4" style="padding: 20px; text-align: center; color: #6e7781;">No menus found.</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
@endsection
