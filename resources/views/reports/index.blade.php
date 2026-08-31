@extends('layouts.app')

@section('title', 'Reportes')

@section('content')
<div class="row g-3">
    <div class="col-12 col-md-6 col-lg-3">
        <a href="{{ route('reports.sales') }}" class="text-decoration-none">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="kpi-icon success mb-2"><i class="bi bi-graph-up-arrow"></i></div>
                    <h5 class="card-title">Reporte de Ventas</h5>
                    <p class="text-muted small">Ingresos, tickets y métodos de pago</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
        <a href="{{ route('reports.products') }}" class="text-decoration-none">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="kpi-icon info mb-2"><i class="bi bi-box-seam"></i></div>
                    <h5 class="card-title">Reporte de Productos</h5>
                    <p class="text-muted small">Ventas y stock por producto en el período</p>
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
                    <h5 class="card-title">Reporte de Créditos</h5>
                    <p class="text-muted small">Estado de cuentas corrientes de clientes</p>
                </div>
            </div>
        </a>
    </div>
    @endif
</div>
@endsection
