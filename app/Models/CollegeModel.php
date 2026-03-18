<?php
namespace App\Models;
use CodeIgniter\Model;

class CollegeModel extends Model
{
    protected $table = 'colleges';
    protected $primaryKey = 'college_id';
    protected $allowedFields = ['college_name', 'college_code', 'created_at'];
}
