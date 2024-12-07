<?php

use App\Http\Controllers\MapController;
use App\Http\Controllers\NodeController;
use App\Http\Controllers\ProfileController;
use App\Jobs\GoFetchListJob;
use App\Jobs\MarkCabinsForCleaning;
use App\Models\Cabin;
use App\Models\Dog;
use App\Models\Service;
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

Route::get('/fullmap{i}', [MapController::class, 'fullmap'])->where('i', '1|2');
Route::get('/rowmap{i}', [MapController::class, 'rowmap'])->where('i', 'first|mid|last');
Route::get('/yardmap{i}', [MapController::class, 'yardmap'])->where('i', 'small|large');

Route::prefix('api')->group(function () {
    Route::get('/fullmap/{checksum}', function (string $checksum) {
        $dogs = Dog::selectRaw('*, LEFT(name, 12) AS shortname')->whereNotNull('cabin_id')->with('services')->get()
            ->mapWithKeys(function ($dog) {
                return [$dog->cabin_id => $dog];
            });
        $new_checksum = md5($dogs->toJson());
        if ($checksum !== $new_checksum) {
            $response = [
                'dogs' => $dogs,         // The original collection of dogs
                'checksum' => $new_checksum, // The computed checksum
            ];

            return Response::json($response);
        }
        return json_encode(false);
    });
    Route::get('/yardmap{size}/{checksum}', function (string $size, string $checksum) {
        $sizes = $size === 'small' ? ['Medium', 'Small', 'Extra Small'] : ['Medium', 'Large', 'Extra Large'];
        $dogs = Dog::selectRaw('*, LEFT(name, 20) AS shortname')->whereIn('size', $sizes)->with('services')
            ->orderBy('name')->get();
        $new_checksum = md5($dogs->toJson());
        if ($checksum !== $new_checksum) {
            $response = [
                'dogs' => $dogs,         // The original collection of dogs
                'checksum' => $new_checksum, // The computed checksum
            ];

            return Response::json($response);
        }
        return json_encode(false);
    })->where([
        'size' => 'small|large',
        'checksum' => '[a-f0-9]{32}'
    ]);

});

Route::get('/current-time', function () {
    return DB::table('migrations')->select(DB::raw('NOW() AS now'))->first();
});

// TODO: REMOVE Testing only
Route::get('/loginCookie', [NodeController::class, 'loginAndStoreCookie']);


Route::get('/fetchDogList', function () {
    return GoFetchListJob::dispatchSync(new NodeController());
});

Route::get('/markForCleaning', function () {
    return MarkCabinsForCleaning::dispatchSync();
});
Route::get('/assignDaycampers', function () {
    $services = Service::where('id', '<=', '1002')->whereHas('dogs')->with('dogs')->get();
    $emptycabins = Cabin::where('id', '<', '3000')->whereDoesntHave('dogs')->get();
    foreach ($services as $service) {
        $service->dogs->each(function ($dog) use ($emptycabins) {
            if ($emptycabins->isNotEmpty()) {
                // Pick a random cabin
                $cabin = $emptycabins->random();

                // Remove the selected cabin from the collection
                $emptycabins = $emptycabins->reject(function ($c) use ($cabin) {
                    return $c->id === $cabin->id;
                });

                // Assign the random cabin to the dog
                $dog->cabin_id = $cabin->id;
                $dog->save();
            }

        });
    }
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

