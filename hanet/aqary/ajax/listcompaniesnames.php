<?php
require_once __DIR__.'/../connectdb.hnt';
header('Content-Type: application/json; charset=utf-8');
try {
    $companies = [];
    foreach (aqaryCompanyDatabases($link, $qdb_db) as $database) {
        if ($database !== $qdb_db) $companies[] = ['databasname'=>$database];
    }
    echo json_encode($companies, JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('Company list: '.$error->getMessage());
    http_response_code(500);
    echo '[]';
}
