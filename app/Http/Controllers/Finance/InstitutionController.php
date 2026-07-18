<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreInstitutionRequest;
use App\Models\Institution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class InstitutionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Finance/Institutions/Index', [
            'institutions' => Institution::orderBy('name')->get(),
        ]);
    }

    public function store(StoreInstitutionRequest $request): RedirectResponse
    {
        $request->user()->institutions()->create($request->validated());

        return Redirect::route('finance.institutions.index');
    }

    public function update(StoreInstitutionRequest $request, Institution $institution): RedirectResponse
    {
        abort_if($institution->user_id !== $request->user()->id, HttpResponse::HTTP_FORBIDDEN);

        $institution->update($request->validated());

        return Redirect::route('finance.institutions.index');
    }

    public function destroy(Institution $institution): RedirectResponse
    {
        abort_if($institution->user_id !== auth()->id(), HttpResponse::HTTP_FORBIDDEN);

        $institution->delete();

        return Redirect::route('finance.institutions.index');
    }
}
