<?php
session_start();
header('Content-Type: application/json');
// Return a small snapshot of session and cookies for debugging
$out = [
    'time' => date('c'),
    'session' => array_intersect_key($_SESSION ?? [], array_flip(['user_id','role','username'])),
    'cookies' => $_COOKIE ?? []
];
echo json_encode($out, JSON_PRETTY_PRINT);
?>