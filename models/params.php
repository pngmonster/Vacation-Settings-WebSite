<?php

namespace Models;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Params extends Eloquent
{
    protected $table = "params";
    protected $primaryKey = "id";

    protected $fillable = ['year'];

    public $incrementing = false; // Если ваш ключ не автоинкрементный
    public $timestamps = false;
}

?>