<?php

namespace Models;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Position extends Eloquent
{
    protected $table = "positions";
    protected $primaryKey = "position";

    protected $fillable = [
        'position',
        'jan', 'feb', 'mar', 'apr', 'may', 'jun',
        'jul', 'aug', 'sep', 'oct', 'nov', 'dec',
        'janEmp', 'febEmp', 'marEmp', 'aprEmp', 'mayEmp', 'junEmp',
        'julEmp', 'augEmp', 'sepEmp', 'octEmp', 'novEmp', 'decEmp'
    ];

    public $incrementing = false; // Если ваш ключ не автоинкрементный
    public $timestamps = false;
}

?>