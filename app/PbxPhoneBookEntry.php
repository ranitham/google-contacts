<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Sofa\Eloquence\Eloquence;
use Sofa\Eloquence\Mappable;

class PbxPhoneBookEntry extends Model
{
    use Eloquence;
    use Mappable;

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'pbx';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'phonebook';

    protected $primaryKey = 'idphonebook';
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    protected $maps = [
        'mobile' => 'phonenumber',
        'mobile2' => 'pv_an0',
        'home' => 'pv_an1',
        'home2' => 'pv_an2',
        'business' => 'pv_an3',
        'business2'=> 'pv_an4',
        'email' => 'pv_an5',
        'other' => 'pv_an6',
        'business_fax' => 'pv_an7',
        'home_fax' => 'pv_an8',
        'pager' => 'pv_an9'
    ];

    protected $guard = [];
}
