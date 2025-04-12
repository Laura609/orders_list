<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once 'moysklad_settings.php';
require_once 'moysklad_curl.php';

$apiSettings = include('moysklad_settings.php');

function getOrganizations($apiSettings) {
    $curl = setupCurl($apiSettings);
    $curl = setCurl(
        $curl,
        $apiSettings['MOYSKLAD_API_URL'] . 'entity/organization',
        'GET'
    );

    $response = curl_exec($curl);
    if ($response === false) {
        throw new Exception("Ошибка cURL: " . curl_error($curl));
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Ошибка декодирования JSON: " . json_last_error_msg());
    }

    $organizations = [];
    foreach ($data['rows'] as $org) {
        $organizations[] = [
            'id' => $org['id'],
            'name' => $org['name']
        ];
    }
    return $organizations;
}

function getAgents($apiSettings) {
    $curl = setupCurl($apiSettings);
    $curl = setCurl(
        $curl,
        $apiSettings['MOYSKLAD_API_URL'] . 'entity/counterparty',
        'GET'
    );

    $response = curl_exec($curl);
    if ($response === false) {
        throw new Exception("Ошибка cURL: " . curl_error($curl));
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Ошибка декодирования JSON: " . json_last_error_msg());
    }

    $agents = [];
    foreach ($data['rows'] as $agent) {
        $agents[] = [
            'id' => $agent['id'],
            'name' => $agent['name']
        ];
    }
    return $agents;
}

try {
    $organizations = getOrganizations($apiSettings);
    $agents = getAgents($apiSettings);
    
    echo json_encode([
        'success' => true,
        'organizations' => $organizations,
        'agents' => $agents
    ]);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>