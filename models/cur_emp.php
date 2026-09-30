<?php

namespace Models;
use Illuminate\Database\Eloquent\Model as Eloquent;

// Список сотрудников (ФИО + должность), загружается администратором из Excel
class Cur_emp extends Eloquent
{
    protected $table = "cur_emp";
    protected $primaryKey = "id";

    protected $fillable = ['fio', 'position'];

    public $timestamps = false;
}

?>