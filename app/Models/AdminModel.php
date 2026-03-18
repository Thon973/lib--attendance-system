<?php

namespace App\Models;

use CodeIgniter\Model;

class AdminModel extends Model
{
    protected $table = 'admins';
    protected $primaryKey = 'admin_id';
    protected $allowedFields = [
        'full_name',
        'email',
        'password',
        'role',
        'section_id',
        'status',
        'profile_picture',
        'created_by',
        'last_login'
    ];

    protected $useTimestamps = true; // automatically manage created_at & updated_at
    protected $createdField  = 'created_at';
    protected $updatedField  = 'last_login'; // if you want last_login updated automatically
}
