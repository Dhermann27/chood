<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Dog extends Model
{
    use HasFactory;

    protected $fillable = ['pet_id', 'firstname', 'lastname', 'gender', 'photoUri', 'size', 'cabin_id',
        'is_inhouse', 'checkout'];

    protected $casts = [ 'checkout' => 'datetime'];

    protected $appends = ['size_letter'];

    public function getSizeLetterAttribute()
    {
        switch ($this->size) {
            case 'Extra Small':
                return 'XS';
            case 'Small':
                return 'S';
            case 'Medium':
                return 'M';
            case 'Large':
                return 'L';
            case 'Extra Large':
                return 'XL';
            default:
                return '?';
        }
    }

    public function cabin(): HasOne
    {
        return $this->hasOne(Cabin::class);
    }


    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'dog_service', 'dog_id', 'service_id');
    }
}
