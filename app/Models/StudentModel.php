<?php

namespace App\Models;
use CodeIgniter\Model;

class StudentModel extends Model
{
    protected $table = 'students';
    protected $primaryKey = 'student_id';
    protected $allowedFields = [
        'student_number',
        'first_name',
        'last_name',
        'sex',
        'middle_initial',
        'password',
        'course_id',
        'college_id',
        'year_level',
        'address',
        'status',
        'qr_code',
        'profile_picture',
        'created_by',
    ];
}