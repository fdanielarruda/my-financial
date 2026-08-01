<?php

namespace App\Http\Controllers\Finance;

use App\Enums\StatementImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ExtractStatementRequest;
use App\Http\Requests\Finance\StoreStatementImportRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Person;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Services\StatementImport\StatementExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class StatementImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Finance/StatementImports/Create', [
            'accounts' => Account::whereNull('archived_at')->where('type', 'credit_card')->orderBy('name')->get(),
        ]);
    }

    /**
     * Store the uploaded PDF and run the first extraction. The import row is
     * always persisted, even when the AI read fails, so the user can retry
     * without uploading the file again.
     */
    public function upload(ExtractStatementRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $file = $request->file('file');

        $path = $file->storeAs(
            'statement-imports/'.$request->user()->id,
            Str::ulid().'.pdf',
        );

        $import = StatementImport::create([
            'user_id' => $request->user()->id,
            'account_id' => $data['account_id'],
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'status' => StatementImportStatus::Processing,
        ]);

        $this->runExtraction($import);

        return Redirect::route('finance.statement-imports.show', $import);
    }

    public function show(StatementImport $statementImport): Response
    {
        $statementImport->load('account');

        return Inertia::render('Finance/StatementImports/Review', [
            'statementImport' => [
                'id' => $statementImport->id,
                'status' => $statementImport->status->value,
                'status_label' => $statementImport->status->label(),
                'original_filename' => $statementImport->original_filename,
                'error_message' => $statementImport->error_message,
                'imported_at' => $statementImport->imported_at,
            ],
            'account' => $statementImport->account->only(['id', 'name', 'person_id']),
            'items' => $statementImport->status === StatementImportStatus::Extracted
                ? $this->flagDuplicates($statementImport->account, $statementImport->items ?? [])
                : ($statementImport->items ?? []),
            'people' => Person::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    /**
     * Re-run the AI extraction against the PDF already stored for this
     * import, discarding the previous reading.
     */
    public function reextract(StatementImport $statementImport): RedirectResponse
    {
        $this->abortIfAlreadyImported($statementImport);

        $this->runExtraction($statementImport);

        return Redirect::route('finance.statement-imports.show', $statementImport);
    }

    public function confirm(StoreStatementImportRequest $request, StatementImport $statementImport): RedirectResponse
    {
        $this->abortIfAlreadyImported($statementImport);

        $data = $request->validated();
        $userId = $request->user()->id;

        DB::transaction(function () use ($data, $userId, $statementImport) {
            foreach ($data['items'] as $item) {
                Transaction::createPurchase([
                    'user_id' => $userId,
                    'account_id' => $statementImport->account_id,
                    'person_id' => $item['person_id'],
                    'category_id' => $item['category_id'] ?? null,
                    'type' => $item['type'],
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                    'date' => $item['date'],
                ], installments: $item['installment_total'] ?? 1);
            }

            $statementImport->update([
                'status' => StatementImportStatus::Imported,
                'imported_at' => now(),
            ]);
        });

        return Redirect::route('finance.accounts.show', $statementImport->account_id)
            ->with('success', count($data['items']).' lançamento(s) importado(s) da fatura.');
    }

    private function runExtraction(StatementImport $import): void
    {
        try {
            $contents = Storage::get($import->file_path);

            $items = (new StatementExtractor)->extract(
                pdfContents: $contents,
                filename: $import->original_filename,
                categories: Category::orderBy('name')->get(),
            );

            $import->update([
                'status' => StatementImportStatus::Extracted,
                'items' => $items,
                'error_message' => null,
                'extracted_at' => now(),
            ]);
        } catch (RuntimeException $exception) {
            $import->update([
                'status' => StatementImportStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);
        }
    }

    private function abortIfAlreadyImported(StatementImport $statementImport): void
    {
        abort_if(
            $statementImport->status === StatementImportStatus::Imported,
            HttpResponse::HTTP_CONFLICT,
            'Esta fatura já foi importada.'
        );
    }

    /**
     * Mark items that look like they've already been imported (same account,
     * date and amount), so the review screen can warn before double-creating them.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function flagDuplicates(Account $account, array $items): array
    {
        $existing = Transaction::query()
            ->where('account_id', $account->id)
            ->get(['date', 'amount', 'description'])
            ->map(fn ($t) => mb_strtolower($t->date->toDateString().'|'.$t->amount.'|'.trim($t->description)))
            ->flip();

        return array_map(function (array $item) use ($existing) {
            $key = mb_strtolower($item['purchase_date'].'|'.$item['total_amount'].'|'.$item['description']);
            $item['possible_duplicate'] = $existing->has($key);

            return $item;
        }, $items);
    }
}
