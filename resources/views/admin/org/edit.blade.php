@extends('admin.layout')
@section('title', 'Edit '.strtolower($def['singular']))

@section('content')
    @include('admin.org._tabs')

    <div class="card" style="max-width:900px">
        <div class="card-head">
            <h2>Edit {{ strtolower($def['singular']) }}</h2>
            <a class="small" href="{{ route('admin.org.index', $type) }}">← Back to {{ strtolower($def['title']) }}</a>
        </div>
        <form method="POST" action="{{ route('admin.org.update', [$type, $record->id]) }}" class="card-body">
            @csrf @method('PUT')
            @include('admin.org._fields')
            @if ($type === 'branches' && $record->latitude !== null && $record->longitude !== null)
                <p class="small"><a target="_blank" rel="noopener" href="https://www.google.com/maps?q={{ $record->latitude }},{{ $record->longitude }}">Open the office location in Google Maps ↗</a></p>
            @elseif ($type === 'branches')
                <p class="small muted">Tip: in Google Maps, right-click the office and click the coordinates to copy latitude and longitude.</p>
            @endif
            <div class="actions" style="justify-content:flex-end">
                <a class="btn" href="{{ route('admin.org.index', $type) }}">Cancel</a>
                <button class="btn primary" type="submit">Save</button>
            </div>
        </form>
    </div>
@endsection
