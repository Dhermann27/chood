<?php

use App\Http\Controllers\ProfileController;
use App\Models\Cabin;
use App\Models\HouseDog;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'photoUri' => config('services.panther.uris.photo'),
        'thedate' => DB::table('migrations')->select(DB::raw('NOW() AS now'))->first(),
        'dogList' => HouseDog::all(),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/fullmap{i}', function () {
    $cabins = Cabin::all();
    $cabinsByRow = $cabins->pluck('row')->mapWithKeys(function($index, $row) use ($cabins) {
       return [$index => $cabins->where('row', $index)->mapWithKeys(function($cabin) {
           $cabin->cabinName = preg_replace('/Luxury Suite /', 'LS', $cabin->cabinName);
           $cabin->cabinName = preg_replace('/\dx\d - Cabin /', '', $cabin->cabinName);
           return [$cabin->column => $cabin];
       })];
    });

    return Inertia::render('Fullmap', [
        'photoUri' => config('services.panther.uris.photo'),
        'dogList' => HouseDog::all()->mapWithKeys(function ($dog) {
            return [$dog->cabin_id => $dog];
        }),
        'rows' => range(0, 9),
        'columns' => range(0, 26),
        'cabins' => $cabinsByRow
    ]);
});

Route::get('/current-time', function () {
    return DB::table('migrations')->select(DB::raw('NOW() AS now'))->first();
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';

