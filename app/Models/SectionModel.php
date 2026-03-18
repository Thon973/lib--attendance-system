<?php
namespace App\Models;
use CodeIgniter\Model;

class SectionModel extends Model
{
    protected $table = 'sections';
    protected $primaryKey = 'section_id';
    protected $allowedFields = ['section_name', 'description', 'created_at'];
}
