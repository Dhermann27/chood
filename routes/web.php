<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Jobs\GoFetchListJob;
use App\Jobs\MarkCabinsForCleaningJob;
use App\Models\Cabin;
use App\Models\Service;
use App\Services\NodeService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
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

Route::get('/fullmap{i?}', [MapController::class, 'fullmap'])->where('i', '1|2|3');
Route::get('/rowmap{i}', [MapController::class, 'rowmap'])->where('i', 'first|mid|last');
Route::get('/yardmap{i}', [MapController::class, 'yardmap'])->where('i', 'small|large');


Route::prefix('depositfinder')->group(function () {
    Route::get('/', [ReportController::class, 'report']);
    Route::post('/login', [ReportController::class, 'overall']);
    Route::get('/results/{i}', [ReportController::class, 'results']);
});

Route::prefix('api')->group(function () {
    Route::get('/fullmap/{checksum?}', [ApiController::class, 'fullmap'])
        ->where('checksum', '[a-f0-9]{32}');
    Route::get('/yardmap{size}/{checksum?}', [ApiController::class, 'yardmap'])->where([
        'size' => 'small|large',
        'checksum' => '[a-f0-9]{32}'
    ]);

    Route::post('/dog', [ApiController::class, 'storeAssignment']);
    Route::put('/dog', [ApiController::class, 'updateAssignment']);
    Route::delete('/dog', [ApiController::class, 'deleteAssignment']);
});

Route::get('/current-time', function () {
    return DB::table('migrations')->select(DB::raw('NOW() AS now'))->first();
});

// TODO: REMOVE Testing only
Route::get('/fetchDogList', function () {
    return GoFetchListJob::dispatchSync(app(NodeService::class));
});

Route::get('/markForCleaning', function () {
    return MarkCabinsForCleaningJob::dispatchSync();
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

Route::get('/note', function () {
    return view('note');
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

