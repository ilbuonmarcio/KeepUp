<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Label extends Model
{
    protected $fillable = [
        'name',
        'normalized_name',
    ];

    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class);
    }

    public function color(): string
    {
        return '#'.substr(hash('sha256', $this->normalized_name), 0, 6);
    }

    public function textColor(): string
    {
        return '#ffffff';
    }
}
