{{-- Renders the inputs for one organisation record. Needs $def and $record. --}}
<div class="form-grid">
    @foreach ($def['fields'] as $name => $field)
        @php
            $type = $field['type'] ?? 'text';
            $value = \App\Http\Controllers\Admin\OrgController::inputValue($record, $name);
            if (! $record->exists && ($value === null || $value === '')) {
                $value = old($name, $def['defaults'][$name] ?? null);
            }
            $id = $name.'_'.($record->id ?? 'new');
        @endphp
        <div class="field {{ ! empty($field['wide']) ? 'wide' : '' }}">
            @if ($type === 'checkbox')
                <label style="visibility:hidden">.</label>
                <input type="hidden" name="{{ $name }}" value="0">
                <label class="check"><input type="checkbox" name="{{ $name }}" value="1" @checked($value)> {{ $field['label'] }}</label>
            @else
                <label for="{{ $id }}">{{ $field['label'] }}{{ in_array('required', is_array($field['rules']) ? $field['rules'] : ['required'], true) ? ' *' : '' }}</label>
                @if ($type === 'select')
                    <select id="{{ $id }}" name="{{ $name }}" class="{{ $errors->has($name) ? 'is-invalid' : '' }}">
                        @if (! empty($field['nullable']))<option value="">{{ $field['nullLabel'] ?? '—' }}</option>@endif
                        @foreach (($field['options'])() as $optValue => $optLabel)
                            <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $optLabel }}</option>
                        @endforeach
                    </select>
                @else
                    <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $value }}"
                           @isset($field['step']) step="{{ $field['step'] }}" @endisset
                           class="{{ $errors->has($name) ? 'is-invalid' : '' }}">
                @endif
            @endif
            @isset($field['hint'])<div class="hint">{{ $field['hint'] }}</div>@endisset
            @error($name)<div class="error">{{ $message }}</div>@enderror
        </div>
    @endforeach
</div>
