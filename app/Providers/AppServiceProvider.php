<?php

namespace App\Providers;

use App\Http\Controllers\Repositories\Branch\BranchRepository;
use App\Http\Controllers\Repositories\Branch\BranchRepositoryInterface;
use App\Http\Controllers\Repositories\Collateral\CollateralInterface;
use App\Http\Controllers\Repositories\Collateral\CollateralRepository;
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

        $bindings = [
            UsersRepositoryInterface::class => UserRepositories::class,
            BranchRepositoryInterface::class => BranchRepository::class,
            MenuRepositoryInterface::class => MenuRepository::class,
            CollateralInterface::class => CollateralRepository::class,
        ];

        foreach ($bindings as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }

    public function boot(): void
    {
        //
    }
}
