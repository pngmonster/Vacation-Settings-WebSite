<?php

namespace Models;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Cur_emp extends Eloquent
{
    protected $table = "cur_emp";
    protected $primaryKey = "fio";
    public $incrementing = false;
    protected $keyType = 'string';
    
    protected $fillable = ['fio'];

    public $timestamps = false;
}

?>