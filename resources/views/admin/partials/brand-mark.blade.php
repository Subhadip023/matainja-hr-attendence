{{-- Sidebar / sign-in mark: the organisation logo from Settings, or the default building icon. Needs $brandLogoUrl (see AppServiceProvider). --}}
@if ($brandLogoUrl)
    <span class="brand-mark has-logo">
        <img src="{{ $brandLogoUrl }}" alt="">
    </span>
@else
    <span class="brand-mark">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="#fff"><path d="M17 11V3H7v4H3v14h8v-4h2v4h8V11h-4z"/></svg>
    </span>
@endif
