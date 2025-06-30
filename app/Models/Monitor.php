<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Monitor extends Model
{
    public $table = 'monitors';
    public $timestamps = true;

    public function authMethod() {
        switch($this->auth_method) {
            case 'password': return 'Password';
            case 'ssh_private_key': return 'SSH Private Key';
            default: return '-';
        }
    }
}
