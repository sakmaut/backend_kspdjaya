<?php

namespace App\Providers;

use App\Http\Controllers\Repositories\Branch\BranchRepository;
use App\Http\Controllers\Repositories\Branch\BranchRepositoryInterface;
use App\Http\Controllers\Repositories\Menu\MenuRepository;
use App\Http\Controllers\Repositories\Menu\MenuRepositoryInterface;
use App\Http\Controllers\Repositories\Users\UserRepositories;
use App\Http\Controllers\Repositories\Users\UsersRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        date_default_timezone_set('Asia/Jakarta');
        Carbon::setLocale('id');

        $this->app->bind(UsersRepositoryInterface::class, UserRepositories::class);
        $this->app->bind(BranchRepositoryInterface::class, BranchRepository::class);
        $this->app->bind(MenuRepositoryInterface::class, MenuRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
