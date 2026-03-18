<?php

namespace App\Models;

use CodeIgniter\Model;

class AttendanceModel extends Model
{
    protected $table = 'attendance';
    protected $primaryKey = 'attendance_id';
    protected $allowedFields = [
        'student_id', 'admin_id', 'section_id',
        'course_id', 'college_id', 'scan_datetime'
    ];
    
    // Add automatic timestamp fields
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    
    // Set default values
    protected $defaults = [
        'scan_datetime' => null
    ];
}