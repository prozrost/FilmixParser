<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Actor extends Model
{
    protected $fillable = [
        'translated_name',
        'origin_name',
        'person_link',
        'poster_url',
        'date_of_birth',
        'place_of_birth',
        'additional_info'
    ];
}
