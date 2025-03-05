<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TaskController;
use App\Jobs\GoFetchHomebaseEmployeesJob;
use App\Jobs\GoFetchHomebaseShiftsJob;
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

Route::prefix('task')->group(function () {
    Route::get('/', [TaskController::class, 'index']);
    Route::get('/data/{checksum?}', [TaskController::class, 'getData'])
        ->where('checksum', '[a-f0-9]{32}');
    Route::post('/cleaned', [TaskController::class, 'markCleaned']);
});

Route::prefix('api')->group(function () {
    Route::get('/fullmap/{checksum?}', [DataController::class, 'fullmap'])
        ->where('checksum', '[a-f0-9]{32}');
    Route::get('/yardmap{size}/{checksum?}', [DataController::class, 'yardmap'])->where([
        'size' => 'small|large',
        'checksum' => '[a-f0-9]{32}'
    ]);

    Route::post('/dog', [AssignmentController::class, 'storeAssignment']);
    Route::put('/dog', [AssignmentController::class, 'updateAssignment']);
    Route::delete('/dog', [AssignmentController::class, 'deleteAssignment']);

});

Route::get('/current-time', function () {
    return DB::table('migrations')->select(DB::raw('NOW() AS now'))->first();
});

// TODO: REMOVE Testing only
//Route::get('/markForCleaning', function () {
//    $cabinIds = Cabin::inRandomOrder()->limit(12)->pluck('id');
//
//    // Use the factory to create 12 cleaning statuses
//    foreach ($cabinIds as $cabinId) {
//        CleaningStatus::factory()->create([
//            'cabin_id' => $cabinId, // Assign each unique cabin ID
//        ]);
//    }
//    return 'Cleaning finished';
//});
//Route::get('/markForCleaningJob', function () {
//    return 'Cleaning jobbed';
//});
Route::get('/fetch', function () {
    GoFetchHomebaseEmployeesJob::dispatchSync();
    GoFetchHomebaseShiftsJob::dispatchSync();
//    MarkCabinsForCleaningJob::dispatchSync();
    return 'Jobs fetched';
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

