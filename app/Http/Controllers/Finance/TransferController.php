<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreTransferRequest;
use App\Models\Account;
use App\Models\Transfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Finance/Transfers/Index', [
            'transfers' => Transfer::with(['fromAccount', 'toAccount'])->orderByDesc('date')->paginate(25),
            'accounts' => Account::whereNull('archived_at')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreTransferRequest $request): RedirectResponse
    {
        Transfer::createBetween([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return Redirect::back();
    }

    public function destroy(Transfer $transfer): RedirectResponse
    {
        $transfer->delete();

        return Redirect::route('finance.transfers.index');
    }
}
