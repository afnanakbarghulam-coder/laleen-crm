@extends('layouts.app')
@section('title', 'Ecommerce Ad Metrics')

@section('content')
    @include('kpi.ads._campaign-metrics-tracker', [
        'filterActionUrl' => route('ecommerce.marketing.ad-metrics'),
        'isStandaloneEcommerce' => true,
        'stayInKpis' => false,
    ])

    @if (auth()->check() && (auth()->user()->canEdit('ecommerce') || auth()->user()->canEdit('kpis')))
        @include('kpi.ads._screenshot-import-modal', [
            'stayInKpis' => false,
        ])
    @endif
@endsection
