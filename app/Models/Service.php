<?php

namespace App\Models;

use App\Enums\HousingServiceCodes;
use App\Enums\ReportCategory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = ['gingr_id', 'name', 'housing_code', 'report_category',
        'booking_category_id', 'account_code_id', 'duration', 'is_active'];

    public $timestamps = false;

    protected $casts = [
        'housing_code'    => HousingServiceCodes::class,
        'report_category' => ReportCategory::class,
    ];


}
