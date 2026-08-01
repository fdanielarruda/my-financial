<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreTransferRequest;
use App\Models\Account;
use App\Models\Institution;
use App\Models\Transfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function index(Request $request): Response
    {
        $accountId = $request->integer('account_id') ?: null;
        $institutionId = $request->integer('institution_id') ?: null;

        $transfers = Transfer::query()
            ->with(['fromAccount.institution', 'toAccount.institution'])
            ->when($accountId, fn ($q) => $q->where(
                fn ($q2) => $q2->where('from_account_id', $accountId)->orWhere('to_account_id', $accountId)
            ))
            ->when($institutionId, fn ($q) => $q->where(
                fn ($q2) => $q2
                    ->whereHas('fromAccount', fn ($aq) => $aq->where('institution_id', $institutionId))
                    ->orWhereHas('toAccount', fn ($aq) => $aq->where('institution_id', $institutionId))
            ))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Finance/Transfers/Index', [
            'transfers' => $transfers,
            'accounts' => Account::with('institution')->whereNull('archived_at')->orderBy('name')->get(),
            'institutions' => Institution::orderBy('name')->get(),
            'filters' => $request->only(['account_id', 'institution_id']),
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

    public function update(StoreTransferRequest $request, Transfer $transfer): RedirectResponse
    {
        $transfer->updateBetween($request->validated());

        return Redirect::back();
    }

    public function destroy(Transfer $transfer): RedirectResponse
    {
        $transfer->delete();

        return Redirect::route('finance.transfers.index');
    }
}
