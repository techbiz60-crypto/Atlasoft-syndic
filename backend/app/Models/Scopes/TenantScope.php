<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    /**
     * Guards against infinite recursion: resolving the authenticated user
     * (Auth::user()) queries the users table, which re-triggers this same
     * scope. While that inner resolution is in progress, skip filtering.
     */
    private static bool $resolvingAuthenticatedUser = false;

    public function apply(Builder $builder, Model $model): void
    {
        if (self::$resolvingAuthenticatedUser) {
            return;
        }

        self::$resolvingAuthenticatedUser = true;

        try {
            if (Auth::check() && Auth::user()->residence_id !== null) {
                $builder->where($model->getTable().'.residence_id', Auth::user()->residence_id);
            }
        } finally {
            self::$resolvingAuthenticatedUser = false;
        }
    }
}
