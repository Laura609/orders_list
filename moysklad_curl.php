<?php
function setupCurl($apiSettings)
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($curl, CURLOPT_ENCODING, 'gzip');

    // Добавляем заголовки
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiSettings['MOYSKLAD_API_TOKEN'],
        'Content-Type: application/json',
        'Accept: application/json;charset=utf-8',
        'Accept-Encoding: gzip',
        'User-Agent: ' . $apiSettings['MOYSKLAD_USER_AGENT']
    ]);

    return $curl;
}

function setCurl(&$curlObject, $uri, $method)
{
    curl_setopt($curlObject, CURLOPT_URL, $uri);
    switch ($method) {
        case 'GET':
            curl_setopt($curlObject, CURLOPT_HTTPGET, true);
            break;
        case 'POST':
            curl_setopt($curlObject, CURLOPT_POST, true);
            break;
        case 'PUT':
            curl_setopt($curlObject, CURLOPT_CUSTOMREQUEST, 'PUT');
            break;
        default:
            throw new Exception("Неподдерживаемый HTTP-метод: " . $method);
    }

    error_log("Метод: $method, URL: $uri");
    return $curlObject;
}

function curlExec($curlObject)
{
    $response = curl_exec($curlObject);
    $httpCode = curl_getinfo($curlObject, CURLINFO_HTTP_CODE);

    if ($response === false) {
        $error = curl_error($curlObject);
        error_log("Ошибка cURL: $error");
        throw new Exception("Ошибка cURL: $error");
    }

    error_log("HTTP-код ответа: $httpCode");
    error_log("Полный ответ API: " . $response);

    return $response;
}
