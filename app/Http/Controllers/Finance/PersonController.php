<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StorePersonRequest;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Finance/People/Index', [
            'people' => Person::withCount('accounts')->orderBy('name')->get(),
        ]);
    }

    public function store(StorePersonRequest $request): RedirectResponse
    {
        $request->user()->people()->create($request->validated());

        return Redirect::route('finance.people.index');
    }

    public function update(StorePersonRequest $request, Person $person): RedirectResponse
    {
        $person->update($request->validated());

        return Redirect::route('finance.people.index');
    }

    public function destroy(Person $person): RedirectResponse
    {
        $person->delete();

        return Redirect::route('finance.people.index');
    }
}
