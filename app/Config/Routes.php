<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Authentication Routes
$routes->get('/', 'Auth::login');
$routes->get('/login', 'Auth::login');
$routes->post('/login/check', 'Auth::checkLogin');
$routes->get('/logout', 'Auth::logout');

// QR Code Image Route (public access for displaying QR codes)
$routes->get('student/qrcode/(:num)', 'Student::qrCode/$1');

// Student API Routes (with CORS support)
$routes->group('api', ['filter' => 'cors'], function($routes) {
    $routes->options('student/login', 'Api\Student::login');
    $routes->post('student/login', 'Api\Student::login');
    $routes->post('student/logout', 'Api\Student::logout');
    $routes->get('student/profile/(:num)', 'Api\Student::profile/$1');
    $routes->get('student/qrcode/(:num)', 'Api\Student::getQrCode/$1');
    $routes->get('student/history/(:num)', 'Api\Student::attendanceHistory/$1');
    $routes->post('student/update', 'Api\Student::updateProfile');
    
    // Admin API Routes
    $routes->options('admin/login', 'Api\Admin::login');
    $routes->post('admin/login', 'Api\Admin::login');
    $routes->post('admin/logout', 'Api\Admin::logout');
    $routes->post('admin/scan', 'Api\Admin::scanAttendance');
});

$routes->group('superadmin', ['namespace' => 'App\Controllers\SuperAdmin', 'filter' => 'auth'], function($routes) {
    // Dashboard/Home Routes
    $routes->get('dashboard', 'Home::dashboard');
    $routes->get('getColleges', 'Home::getColleges');
    $routes->get('getCourses', 'Home::getCourses');
    $routes->get('getSections', 'Home::getSections');
    $routes->post('addCollege', 'Home::addCollege');
    $routes->post('addCourse', 'Home::addCourse');
    $routes->post('addSection', 'Home::addSection');
    $routes->post('updateCollege/(:num)', 'Home::updateCollege/$1');
    $routes->post('updateCourse/(:num)', 'Home::updateCourse/$1');
    $routes->post('updateSection/(:num)', 'Home::updateSection/$1');
    $routes->post('deleteCollege/(:num)', 'Home::deleteCollege/$1');
    $routes->post('deleteCourse/(:num)', 'Home::deleteCourse/$1');
    $routes->post('deleteSection/(:num)', 'Home::deleteSection/$1');

    // Manage Student Routes
    $routes->get('manage-student', 'ManageStudent::manageStudent');
    $routes->get('getStudents', 'ManageStudent::getStudents');
    $routes->get('get_student/(:num)', 'ManageStudent::get_student/$1');
    $routes->post('addStudent', 'ManageStudent::addStudent');
    $routes->post('update_student/(:num)', 'ManageStudent::update_student/$1');
    $routes->delete('delete_student/(:num)', 'ManageStudent::delete_student/$1');
    $routes->post('updateStudentStatus/(:num)', 'ManageStudent::updateStudentStatus/$1');
    $routes->get('stdColleges', 'ManageStudent::stdColleges');
    $routes->get('stdCourses/(:num)', 'ManageStudent::stdCourses/$1');
    $routes->post('importStudents', 'ManageStudent::importStudents');

    // Manage Admin Routes
    $routes->get('manage-admin', 'ManageAdmin::manageAdmin');
    $routes->post('addAdminAjax', 'ManageAdmin::addAdminAjax');
    $routes->post('deleteAdminAjax/(:num)', 'ManageAdmin::deleteAdminAjax/$1');
    $routes->post('updateAdminAjax/(:num)', 'ManageAdmin::updateAdminAjax/$1');
    $routes->post('updateAdminStatus/(:num)', 'ManageAdmin::updateAdminStatus/$1');
    
    // Student Attendance Routes
    $routes->get('student_attendance', 'StudentAttendance::studentAttendance');
    $routes->get('attendance-data', 'StudentAttendance::getAttendanceData');
    
    // Reports Routes
    $routes->get('reports', 'Reports::reports');
    $routes->get('reports-data', 'Reports::getReportsData');
    $routes->get('export-reports', 'Reports::exportReports');
    $routes->get('student-attendance-history/(:num)', 'Reports::getStudentAttendanceHistory/$1');
    
    // Profile Routes
    $routes->get('profile', 'Profile::profile');
    $routes->post('profile/update', 'Profile::updateProfile');
    $routes->post('profile/upload-picture', 'Profile::updateProfilePicture');
    $routes->get('profile/image/(:num)', 'Profile::getImage/$1');
    
    // Audit Logs Routes
    $routes->get('audit-logs', 'AuditLogs::auditLogs');
    $routes->get('audit-logs-data', 'AuditLogs::getAuditLogs');
    $routes->get('audit-logs-filters', 'AuditLogs::getAuditLogFilters');
});

$routes->group('admin', ['namespace' => 'App\Controllers\Admin', 'filter' => 'auth'], function($routes) {
    // Dashboard Routes
    $routes->get('ad-dashboard', 'Dashboard::dashboard');
    $routes->get('attendance-data', 'Dashboard::getAttendanceData');
    
    // Scanner Routes
    $routes->get('ad-scanner', 'Scanner::scanner');
    $routes->post('scan', 'Scanner::scanAttendance');
    
    // Reports Routes
    $routes->get('ad-reports', 'Reports::reports');
    $routes->get('reports-data', 'Reports::getReportsData');
    $routes->get('export-reports', 'Reports::exportReports');
    
    // Profile Routes
    $routes->get('ad-profile', 'Profile::profile');
    $routes->post('profile/update', 'Profile::updateProfile');
    $routes->post('profile/upload-picture', 'Profile::updateProfilePicture');
    $routes->get('profile/image/(:num)', 'Profile::getImage/$1');
});