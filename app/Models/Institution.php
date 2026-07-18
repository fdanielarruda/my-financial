<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

#[Fillable(['name', 'color'])]
class Institution extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $institution) {
            if (! $institution->user_id && Auth::check()) {
                $institution->user_id = Auth::id();
            }
        });

        static::addGlobalScope('visibleToUser', function (Builder $builder) {
            if (Auth::check()) {
                $builder->where(function (Builder $query) {
                    $query->whereNull('institutions.user_id')
                        ->orWhere('institutions.user_id', Auth::id());
                });
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
