<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once 'moysklad_settings.php';
require_once 'moysklad_curl.php';

$apiSettings = include('moysklad_settings.php');

try {
    $pdo = new PDO('mysql:host=localhost;dbname=test_db', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Ошибка подключения к БД: " . $e->getMessage());
    die(json_encode(["error" => "Ошибка подключения к базе данных."]));
}

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

error_log("Полученные данные: " . print_r($data, true));

try {
    if (empty($data['order_number']) || empty($data['organization']) || empty($data['agent'])) {
        throw new Exception('Необходимо заполнить все поля.');
    }

    $orderNumber = $data['order_number'];
    $organizationId = $data['organization'];
    $agentId = $data['agent'];
    
    // Получаем название организации
    $organizationName = $data['organization_name'] ?? '';
    $agentName = $data['agent_name'] ?? '';

    // Если названия не переданы, получаем их из API МойСклад
    if (empty($organizationName) || empty($agentName)) {
        $curl = setupCurl($apiSettings);
        
        // Получаем данные организации
        $curlOrg = setCurl($curl, $apiSettings['MOYSKLAD_API_URL'] . 'entity/organization/' . $organizationId, 'GET');
        $responseOrg = curlExec($curlOrg);
        $orgData = json_decode($responseOrg, true);
        if (!empty($orgData['name'])) {
            $organizationName = $orgData['name'];
        }
        
        // Получаем данные агента
        $curlAgent = setCurl($curl, $apiSettings['MOYSKLAD_API_URL'] . 'entity/counterparty/' . $agentId, 'GET');
        $responseAgent = curlExec($curlAgent);
        $agentData = json_decode($responseAgent, true);
        if (!empty($agentData['name'])) {
            $agentName = $agentData['name'];
        }
    }

    // Создаем заказ в МойСклад
    $curl = setupCurl($apiSettings);
    $curl = setCurl($curl, $apiSettings['MOYSKLAD_API_URL'] . 'entity/customerorder', 'POST');

    $orderData = [
        'name' => $orderNumber,
        'organization' => [
            'meta' => [
                'href' => $apiSettings['MOYSKLAD_API_URL'] . 'entity/organization/' . $organizationId,
                'type' => 'organization',
                'mediaType' => 'application/json'
            ]
        ],
        'agent' => [
            'meta' => [
                'href' => $apiSettings['MOYSKLAD_API_URL'] . 'entity/counterparty/' . $agentId,
                'type' => 'counterparty',
                'mediaType' => 'application/json'
            ]
        ]
    ];

    $jsonData = json_encode($orderData, JSON_UNESCAPED_SLASHES);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiSettings['MOYSKLAD_API_TOKEN'],
        'Content-Type: application/json',
        'Accept: application/json;charset=utf-8',
        'Content-Length: ' . strlen($jsonData)
    ]);

    $response = curlExec($curl);
    
    // Обработка ответа
    $orderResponse = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $response = gzdecode($response);
        $orderResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Ошибка декодирования JSON: " . json_last_error_msg());
        }
    }

    error_log("Ответ от API Мой Склад: " . print_r($orderResponse, true));

    if (isset($orderResponse['errors'])) {
        throw new Exception('Ошибка при создании заказа в Мой Склад: ' . print_r($orderResponse['errors'], true));
    }

    if (!isset($orderResponse['id'])) {
        throw new Exception('Не удалось получить ID созданного заказа');
    }

    $moyskladId = $orderResponse['id'];

    // Вставка в БД
    $stmt = $pdo->prepare("INSERT INTO orders 
        (order_number, organization, organization_id, agent, agent_id, moysklad_id) 
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $orderNumber,
        $organizationName,
        $organizationId,
        $agentName,
        $agentId,
        $moyskladId
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Заказ успешно создан!",
        "moysklad_id" => $moyskladId,
        "redirect" => "orders.php",
        "order_data" => [
            "order_number" => $orderNumber,
            "organization" => $organizationName,
            "agent" => $agentName
        ],
    ]);

} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>