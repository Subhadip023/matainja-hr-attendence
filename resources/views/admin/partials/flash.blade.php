@if (session('success'))
    <div class="alert success" role="status">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert error" role="alert">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="alert error" role="alert">
        <div>
            <strong>Please fix the following:</strong>
            <ul>
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
