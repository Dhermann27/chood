<?php

use App\Http\Controllers\ProfileController;
use App\Jobs\GoFetchListJob;
use App\Models\Cabin;
use App\Models\Dog;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'thedate' => DB::table('migrations')->select(DB::raw('NOW() AS now'))->first(),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/fullmap{i}', function () {
    $cabins = Cabin::where('row', '>', '0')->where('column', '>', '0')->get()->map(function ($cabin) {
        $cabin->cabinName = preg_replace('/Luxury Suite /', 'LS', $cabin->cabinName);
        $cabin->cabinName = preg_replace('/\dx\d - Cabin /', '', $cabin->cabinName);
        return $cabin;
    });

    $dogs = Dog::whereNotNull('cabin_id')->with('services')->get()->mapWithKeys(function ($dog) {
        return [$dog->cabin_id => $dog];
    });

    return Inertia::render('Fullmap', [
        'photoUri' => config('services.panther.uris.photo'),
        'dogs' => $dogs,
        'cabins' => $cabins,
        'checksum' => md5($dogs->toJson())
    ]);
});

Route::prefix('api')->group(function () {
    Route::get('/fullmap/{checksum}', function (string $checksum) {
        $dogs = Dog::whereNotNull('cabin_id')->with('services')->get()->mapWithKeys(function ($dog) {
            return [$dog->cabin_id => $dog];
        });
        $new_checksum = md5($dogs->toJson());
//        if($checksum !== $new_checksum) {
        $response = [
            'dogs' => $dogs,         // The original collection of dogs
            'checksum' => $new_checksum, // The computed checksum
        ];

        // Return the response as JSON
        return Response::json($response);
//        }
//        return json_encode(false);
    });
});

Route::get('/current-time', function () {
    return DB::table('migrations')->select(DB::raw('NOW() AS now'))->first();
});

// TODO: REMOVE Testing only
Route::get('/fetchDogList', function () {
    return GoFetchListJob::dispatchSync();
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

