@php
    // Rango actual (Y-m-d)
    $pfFrom = $from ?? now()->startOfMonth()->format('Y-m-d');
    $pfTo = $to ?? now()->format('Y-m-d');

    // Rangos predefinidos
    $pfPresets = [
        'hoy' => [
            'label' => 'Hoy',
            'from' => now()->startOfDay()->format('Y-m-d'),
            'to' => now()->endOfDay()->format('Y-m-d'),
        ],
        'semana' => [
            'label' => 'Semana',
            'from' => now()->startOfWeek()->format('Y-m-d'),
            'to' => now()->endOfWeek()->format('Y-m-d'),
        ],
        'mes' => [
            'label' => 'Mes',
            'from' => now()->startOfMonth()->format('Y-m-d'),
            'to' => now()->endOfMonth()->format('Y-m-d'),
        ],
    ];

    $pfQuery = isset($preserve) ? array_filter($preserve ?? []) : [];
@endphp

<form method="GET" action="{{ route($route) }}" class="row g-2 align-items-end mb-3">
    <div class="col-6 col-sm-3">
        <label class="form-label small text-muted mb-1">Desde</label>
        <input type="date" name="from" class="form-control" value="{{ $pfFrom }}">
    </div>
    <div class="col-6 col-sm-3">
        <label class="form-label small text-muted mb-1">Hasta</label>
        <input type="date" name="to" class="form-control" value="{{ $pfTo }}">
    </div>
    @if(! empty($pfQuery))
        @foreach($pfQuery as $pk => $pv)
            @if($pv !== null && $pv !== '')
                <input type="hidden" name="{{ $pk }}" value="{{ $pv }}">
            @endif
        @endforeach
    @endif
    <div class="col-12 col-sm-2">
        <button type="submit" class="btn btn-outline-brand w-100">Filtrar</button>
    </div>
    <div class="col-12 col-sm-4 d-flex gap-1">
        @foreach($pfPresets as $key => $preset)
            @php
                $isActive = $pfFrom === $preset['from'] && $pfTo === $preset['to'];
                $presetQuery = array_merge($pfQuery, ['from' => $preset['from'], 'to' => $preset['to']]);
            @endphp
            <a href="{{ route($route, $presetQuery) }}"
               class="btn btn-sm {{ $isActive ? 'btn-brand' : 'btn-outline-brand' }}">{{ $preset['label'] }}</a>
        @endforeach
    </div>
</form>
