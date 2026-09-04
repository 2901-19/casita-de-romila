<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExchangeRateRequest;
use App\Models\ExchangeRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExchangeRateController extends Controller
{
    public function index(): View
    {
        $latestRate = ExchangeRate::latest()->first();
        $history = ExchangeRate::latest()->paginate(20);

        return view('exchange-rates.index', compact('latestRate', 'history'));
    }

    public function store(StoreExchangeRateRequest $request): RedirectResponse
    {
        ExchangeRate::create([
            'rate' => $request->validated()['rate'],
            'source' => $request->validated()['source'],
            'user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('exchange-rates.index')
            ->with('success', 'Tasa actualizada exitosamente.');
    }
}
