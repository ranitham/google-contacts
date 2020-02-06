<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GoogleUser extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'google_users';

    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    protected $guarded = [];
    protected $casts = [
        'expires_at' => 'datetime'
    ];
}
