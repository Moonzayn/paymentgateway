<?php
header('Content-Type: application/json');
$rawInput = file_get_contents('php://input');
echo json_encode([
    'raw_input' => $rawInput,
    'json_parsed' => json_decode($rawInput, true)
]);
