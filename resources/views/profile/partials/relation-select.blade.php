<select class="form-select" @if (!empty($dataAttribute)) {!! $dataAttribute !!} @endif required>
    <option value="">Select relation</option>
    @foreach (config('hrms.family_relations', []) as $relation)
        <option value="{{ $relation }}">{{ $relation }}</option>
    @endforeach
</select>
