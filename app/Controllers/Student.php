<?php

namespace App\Controllers;

use App\Models\StudentModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

class Student extends Controller
{
    /**
     * Serve QR code image from database
     */
    public function qrCode($studentId)
    {
        try {
            $db = \Config\Database::connect();
            
            // Direct query to retrieve LONGBLOB data
            $builder = $db->table('students');
            $builder->select('qr_code');
            $builder->where('student_id', $studentId);
            $query = $builder->get();
            
            if (!$query || $query->getNumRows() === 0) {
                return $this->response
                    ->setStatusCode(ResponseInterface::HTTP_NOT_FOUND)
                    ->setBody('Student not found');
            }
            
            $result = $query->getRowArray();
            
            if (empty($result['qr_code'])) {
                return $this->response
                    ->setStatusCode(ResponseInterface::HTTP_NOT_FOUND)
                    ->setBody('QR code not found');
            }

            // Get raw binary image data from LONGBLOB
            $imageData = $result['qr_code'];
            
            // Verify it's valid image data (PNG starts with specific bytes)
            if (empty($imageData) || strlen($imageData) < 8) {
                return $this->response
                    ->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR)
                    ->setBody('Invalid QR code data');
            }

            // Set appropriate headers for PNG image
            return $this->response
                ->setHeader('Content-Type', 'image/png')
                ->setHeader('Content-Length', strlen($imageData))
                ->setHeader('Cache-Control', 'public, max-age=3600')
                ->setBody($imageData);
                
        } catch (\Exception $e) {
            log_message('error', 'QR Code retrieval error: ' . $e->getMessage());
            return $this->response
                ->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR)
                ->setBody('Error loading QR code');
        }
    }
}

