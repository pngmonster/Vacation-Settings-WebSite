<?php
namespace Models;
use Illuminate\Database\Eloquent\Model as Eloquent;

class User extends Eloquent {
    protected $table = 'users';
    protected $fillable = ['username', 'password', 'role'];
    public $timestamps = false; // если у тебя нет created_at/updated_at
}