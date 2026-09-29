@extends('adminlte::page')

@section('title', 'Email marketing')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div><h1 class="mb-0"><i class="fas fa-paper-plane text-primary mr-2"></i>Email marketing</h1><small class="text-muted">Campañas enviadas por Amazon SES</small></div>
        <a href="{{ route('marketing.campaigns.create') }}" class="btn btn-primary"><i class="fas fa-plus mr-1"></i>Nueva campaña</a>
    </div>
@stop

@section('content')
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="row">
        @foreach(['sent' => ['Enviados', 'primary'], 'delivered' => ['Entregados', 'success'], 'opened' => ['Aperturas únicas', 'info'], 'clicked' => ['Clics únicos', 'warning'], 'bounced' => ['Rebotes', 'danger'], 'unsubscribed' => ['Bajas', 'secondary']] as $key => [$label, $color])
            <div class="col-lg-2 col-md-4 col-6"><div class="small-box bg-{{ $color }}"><div class="inner"><h3>{{ number_format($metrics[$key]) }}</h3><p>{{ $label }}</p></div><div class="icon"><i class="fas fa-{{ $key === 'sent' ? 'paper-plane' : ($key === 'delivered' ? 'check-circle' : ($key === 'opened' ? 'eye' : ($key === 'clicked' ? 'mouse-pointer' : ($key === 'bounced' ? 'exclamation-triangle' : 'user-slash')))) }}"></i></div></div></div>
        @endforeach
    </div>

    <div class="card card-outline card-primary">
        <div class="card-header"><h3 class="card-title">Todas las campañas</h3><div class="card-tools"><a class="btn btn-sm btn-outline-secondary" href="{{ route('marketing.lists.index') }}">Listas</a> <a class="btn btn-sm btn-outline-secondary" href="{{ route('marketing.templates.index') }}">Plantillas</a></div></div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Campaña</th><th>Lista</th><th>Estado</th><th>Programada / enviada</th><th class="text-right">Acciones</th></tr></thead>
                <tbody>
                    @forelse($campaigns as $campaign)
                        <tr>
                            <td><strong>{{ $campaign->name }}</strong><br><small class="text-muted">{{ $campaign->subject }}</small></td>
                            <td>{{ $campaign->list?->name ?? 'Sin lista' }}</td>
                            <td><span class="badge badge-{{ ['sent' => 'success', 'sending' => 'primary', 'scheduled' => 'warning', 'failed' => 'danger'][$campaign->status] ?? 'secondary' }}">{{ ucfirst($campaign->status) }}</span></td>
                            <td>{{ optional($campaign->finished_at ?: $campaign->scheduled_at ?: $campaign->created_at)->format('d/m/Y H:i') }}</td>
                            <td class="text-right"><a href="{{ route('marketing.campaigns.show', $campaign) }}" class="btn btn-sm btn-outline-primary">Ver analíticas</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Aún no hay campañas. Crea la primera cuando hayas importado una lista.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($campaigns->hasPages())<div class="card-footer">{{ $campaigns->links() }}</div>@endif
    </div>
@stop
