<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once 'moysklad_settings.php';
require_once 'moysklad_curl.php';

$apiSettings = include('moysklad_settings.php');

try {
    // Получаем ID заказа из GET-параметра
    if (empty($_GET['id'])) {
        throw new Exception('ID заказа не указан.');
    }

    $orderId = $_GET['id'];

    // Подключение к БД
    $pdo = new PDO('mysql:host=localhost;dbname=test_db', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Получаем данные из POST-запроса
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Некорректный формат данных.');
    }

    $newStatusId = trim($data['status']);
    $msId = trim($data['ms_id']);
    
    if (empty($newStatusId)) {
        throw new Exception('Статус не может быть пустым.');
    }

    // Сопоставление ID статуса с названием
    $statusNames = [
        '0fc6474d-059f-11f0-0a80-19740012ce51' => 'Новый',
        '0fc64837-059f-11f0-0a80-19740012ce52' => 'Подтвержден',
        '0fc648fd-059f-11f0-0a80-19740012ce53' => 'Собран',
        '0fc6494d-059f-11f0-0a80-19740012ce54' => 'Отгружен',
        '0fc64997-059f-11f0-0a80-19740012ce55' => 'Доставлен',
        '0fc649fe-059f-11f0-0a80-19740012ce56' => 'Возврат',
        '0fc64a5e-059f-11f0-0a80-19740012ce57' => 'Отменен'
    ];

    $newStatusName = $statusNames[$newStatusId] ?? 'Новый';

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
    $response = curlExec($curl);
    $updateResponse = json_decode($response, true);

    if (isset($updateResponse['errors'])) {
        throw new Exception('Ошибка при обновлении статуса в Мой Склад: ' . 
            implode(', ', array_column($updateResponse['errors'], 'error')));
    }

    // Проверяем, что статус действительно обновился
    if (!isset($updateResponse['state']['meta']['href']) || 
        strpos($updateResponse['state']['meta']['href'], $newStatusId) === false) {
        throw new Exception('Не удалось подтвердить обновление статуса в Мой Склад');
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Статус успешно обновлен!',
        'new_status' => $newStatusName
    ]);
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>