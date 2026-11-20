<div class="page-head">
    <div>
        <h1>Organisation</h1>
        <p>Branches, departments, shifts and the other lists employees are assigned to.</p>
    </div>
</div>
<nav class="tabs">
    @foreach ($tabs as $key => $title)
        <a href="{{ route('admin.org.index', $key) }}" class="{{ $key === $type ? 'active' : '' }}">{{ $title }}</a>
    @endforeach
</nav>
