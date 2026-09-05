@extends('layouts.app')

@section('title', 'Reportes')

@section('content')
<h2 class="card-title mb-3">Reportes</h2>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card">
            <div class="card-body py-2">
                <p class="kpi-label mb-0">Ventas del Mes</p>
                <strong class="kpi-value">Bs {{ number_format($monthRevenue, 2, ',', '.') }}</strong>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card">
            <div class="card-body py-2">
                <p class="kpi-label mb-0">Productos Activos</p>
                <strong class="kpi-value">{{ $activeProducts }}</strong>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card">
            <div class="card-body py-2">
                <p class="kpi-label mb-0">Creditos Pendientes</p>
                <div class="d-flex flex-column">
                    <strong class="kpi-value text-danger">$ {{ number_format(abs($pendingCreditUsd), 2, ',', '.') }}</strong>
                    <small class="text-muted">≈ Bs {{ number_format(abs($pendingCreditBs), 2, ',', '.') }}</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card">
            <div class="card-body py-2">
                <p class="kpi-label mb-0">Mermas (unidades)</p>
                <strong class="kpi-value">{{ $totalWaste }}</strong>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-md-6 col-lg-3">
        <a href="{{ route('reports.sales') }}" class="text-decoration-none">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="kpi-icon success mb-2"><i class="bi bi-graph-up-arrow"></i></div>
                    <h5 class="card-title">Ventas</h5>
                    <p class="text-muted small">Ingresos, tickets y metodos de pago</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
        <a href="{{ route('reports.products') }}" class="text-decoration-none">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="kpi-icon info mb-2"><i class="bi bi-box-seam"></i></div>
                    <h5 class="card-title">Productos</h5>
                    <p class="text-muted small">Ventas, ganancia y stock por producto</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
        <a href="{{ route('reports.top-days') }}" class="text-decoration-none">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="kpi-icon success mb-2"><i class="bi bi-calendar-week"></i></div>
                    <h5 class="card-title">Dias Top</h5>
                    <p class="text-muted small">Dias con mas ventas</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
        <a href="{{ route('reports.profit-margin') }}" class="text-decoration-none">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="kpi-icon success mb-2"><i class="bi bi-cash-stack"></i></div>
                    <h5 class="card-title">Margen de Ganancia</h5>
                    <p class="text-muted small">Rentabilidad por producto</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
        <a href="{{ route('reports.sales-by-schedule') }}" class="text-decoration-none">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="kpi-icon info mb-2"><i class="bi bi-clock-history"></i></div>
                    <h5 class="card-title">Ventas por Horario</h5>
                    <p class="text-muted small">Manana vs noche</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
        <a href="{{ route('reports.waste') }}" class="text-decoration-none">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="kpi-icon warning mb-2"><i class="bi bi-trash3"></i></div>
                    <h5 class="card-title">Mermas</h5>
                    <p class="text-muted small">Productos desechados</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
        <a href="{{ route('reports.weekly-performance') }}" class="text-decoration-none">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="kpi-icon info mb-2"><i class="bi bi-bar-chart-line"></i></div>
                    <h5 class="card-title">Rendimiento Semanal</h5>
                    <p class="text-muted small">Por dia de la semana</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
        <a href="{{ route('reports.production-vs-sales') }}" class="text-decoration-none">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="kpi-icon warning mb-2"><i class="bi bi-arrow-left-right"></i></div>
                    <h5 class="card-title">Produccion vs Ventas</h5>
                    <p class="text-muted small">Comparativo por producto</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
        <a href="{{ route('reports.slow-movers') }}" class="text-decoration-none">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="kpi-icon warning mb-2"><i class="bi bi-hourglass-split"></i></div>
                    <h5 class="card-title">Lento Movimiento</h5>
                    <p class="text-muted small">Productos sin rotacion</p>
                </div>
            </div>
        </a>
    </div>
    @if(class_exists(\App\Models\Customer::class))
    <div class="col-12 col-md-6 col-lg-3">
        <a href="{{ route('reports.credits') }}" class="text-decoration-none">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="kpi-icon warning mb-2"><i class="bi bi-credit-card"></i></div>
                    <h5 class="card-title">Creditos</h5>
                    <p class="text-muted small">Estado de cuentas de clientes</p>
                </div>
            </div>
        </a>
    </div>
    @endif
</div>
@endsection
