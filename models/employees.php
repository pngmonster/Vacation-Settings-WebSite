<?php

namespace Models;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Employees extends Eloquent
{
    protected $table = "employees";
    protected $primaryKey = "id";
    
    protected $fillable = [
        'fam', 
        'name', 
        'otch', 
        'position',
        'mon1',
        'lenght1',
        'day1',
        'mon2',
        'lenght2',
        'day2',
        'mon3',
        'lenght3',
        'day3',
        'isReady',
        'comment'
    ];

    public $timestamps = false;
    
    // Связь с таблицей positions
    public function position()
    {
        return $this->belongsTo('Models\Position', 'position', 'position');
    }
}

?>