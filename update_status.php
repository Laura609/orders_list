<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Подключаем настройки API
require_once 'moysklad_settings.php';
$apiSettings = include('moysklad_settings.php');

// Подключаем функции для работы с cURL
require_once 'moysklad_curl.php';

try {
    // Логирование GET-параметров
    file_put_contents('debug.log', "GET Parameters: " . print_r($_GET, true) . "\n", FILE_APPEND);

    // Получаем ID заказа из GET-параметра
    if (empty($_GET['id'])) {
        throw new Exception('ID заказа не указан.');
    }
    $orderId = $_GET['id'];

    // Подключение к БД
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=test_db', 'root', 'root');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        error_log("Ошибка подключения к базе данных: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к базе данных']);
        exit;
    }

    // Логирование POST-данных
    $rawData = file_get_contents('php://input');
    file_put_contents('debug.log', "Raw POST Data: " . $rawData . "\n", FILE_APPEND);

    // Очистка данных от лишних символов
    $rawData = trim($rawData);
    $rawData = preg_replace('/[\x00-\x1F\x7F]/u', '', $rawData); // Удаление управляющих символов

    // Проверка формата JSON
    $data = json_decode($rawData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Ошибка декодирования JSON: " . json_last_error_msg());
    }

    file_put_contents('debug.log', "Decoded Data: " . print_r($data, true) . "\n", FILE_APPEND);

    $newStatusName = trim($data['status']);
    $msId = trim($data['ms_id']);

    if (empty($newStatusName) || empty($msId)) {
        throw new Exception('Статус или ID заказа в МойСклад не могут быть пустыми.');
    }

    // Сопоставление названия статуса с ID
    $statuses = [
        'Новый' => '0fc6474d-059f-11f0-0a80-19740012ce51',
        'Подтвержден' => '0fc64837-059f-11f0-0a80-19740012ce52',
        'Собран' => '0fc648fd-059f-11f0-0a80-19740012ce53',
        'Отгружен' => '0fc6494d-059f-11f0-0a80-19740012ce54',
        'Доставлен' => '0fc64997-059f-11f0-0a80-19740012ce55',
        'Возврат' => '0fc649fe-059f-11f0-0a80-19740012ce56',
        'Отменен' => '0fc64a5e-059f-11f0-0a80-19740012ce57'
    ];

    if (!array_key_exists($newStatusName, $statuses)) {
        throw new Exception('Неизвестный статус: ' . $newStatusName);
    }

    $newStatusId = $statuses[$newStatusName];

    // Обновляем статус в локальной БД
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$newStatusName, $orderId]);

    // Обновляем статус в МойСклад
    $curl = setupCurl($apiSettings);
    $url = $apiSettings['MOYSKLAD_API_URL'] . 'entity/customerorder/' . $msId;
    $curl = setCurl($curl, $url, 'PUT');

    $updateData = [
        'state' => [
            'meta' => [
                'href' => $apiSettings['MOYSKLAD_API_URL'] . 'entity/customerorder/metadata/states/' . $newStatusId,
                'type' => 'state',
                'mediaType' => 'application/json'
            ]
        ]
    ];

    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($updateData));
    $response = curl_exec($curl);

    if ($response === false) {
        $error = curl_error($curl);
        throw new Exception("CURL Error: $error");
    }

    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($httpCode !== 200) {
        $responseData = json_decode($response, true);
        $errorMessage = $responseData['errors'][0]['error'] ?? 'Неизвестная ошибка';
        throw new Exception("API Error: $errorMessage");
    }

    echo json_encode([
        'success' => true,
        'message' => 'Статус успешно обновлен!',
        'new_status' => $newStatusName
    ]);
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}