<?php

namespace App\Providers;

use App\Http\Controllers\Repositories\Users\UserRepositories;
use App\Http\Controllers\Repositories\Users\UsersRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        date_default_timezone_set('Asia/Jakarta');
        Carbon::setLocale('id');

        $this->app->bind(UsersRepositoryInterface::class,UserRepositories::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
