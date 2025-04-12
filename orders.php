<?php
try {
    // Подключение к базе данных
    $pdo = new PDO('mysql:host=localhost;dbname=test_db', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

// Пагинация
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 3;
$offset = ($page - 1) * $limit;

// Получение общего количества заказов
$totalStmt = $pdo->query("SELECT COUNT(*) FROM orders");
$totalOrders = $totalStmt->fetchColumn();
$totalPages = ceil($totalOrders / $limit);

// Получение списка заказов
$stmt = $pdo->prepare("SELECT * FROM orders LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Статусы из МойСклад
$statuses = [
    'Новый' => '0fc6474d-059f-11f0-0a80-19740012ce51',
    'Подтвержден' => '0fc64837-059f-11f0-0a80-19740012ce52',
    'Собран' => '0fc648fd-059f-11f0-0a80-19740012ce53',
    'Отгружен' => '0fc6494d-059f-11f0-0a80-19740012ce54',
    'Доставлен' => '0fc64997-059f-11f0-0a80-19740012ce55',
    'Возврат' => '0fc649fe-059f-11f0-0a80-19740012ce56',
    'Отменен' => '0fc64a5e-059f-11f0-0a80-19740012ce57'
];

// Функция для получения цвета статуса
function getStatusColor($status) {
    $colors = [
        'Новый' => '#4a90e2',
        'Подтвержден' => '#68cc8f',
        'Собран' => '#ffd700',
        'Отгружен' => '#ff6f61',
        'Доставлен' => '#9b59b6',
        'Возврат' => '#e74c3c',
        'Отменен' => '#95a5a6'
    ];
    return $colors[$status] ?? '#ccc';
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список заказов</title>
    <!-- Подключение Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome для иконок -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- jQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <!-- Заголовок -->
        <header class="mb-4">
            <h1 class="text-primary d-flex align-items-center gap-2">
                <i class="fas fa-list"></i> Список заказов
            </h1>
        </header>

        <!-- Основное содержимое -->
        <main>
            <div class="table-responsive">
                <table class="table table-striped table-hover shadow-sm">
                    <thead class="table-light">
                        <tr>
                            <th scope="col"><i class="fas fa-hashtag"></i> Номер заказа</th>
                            <th scope="col"><i class="fas fa-building"></i> Организация</th>
                            <th scope="col"><i class="fas fa-user-tie"></i> Агент</th>
                            <th scope="col"><i class="fas fa-check-circle"></i> Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($orders)): ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?= htmlspecialchars($order['order_number']) ?></td>
                                    <td><?= htmlspecialchars($order['organization']) ?></td>
                                    <td><?= htmlspecialchars($order['agent']) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <select class="form-select form-select-sm status-select"
                                                data-order-id="<?= $order['id'] ?>"
                                                data-ms-id="<?= $order['moysklad_id'] ?>">
                                                <?php foreach ($statuses as $name => $id): ?>
                                                    <option value="<?= $name ?>" <?= $order['status'] == $name ? 'selected' : '' ?>>
                                                        <?= $name ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="status-indicator rounded-circle" 
                                                 style="width: 12px; height: 12px; background-color: <?= getStatusColor($order['status']) ?>;"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <p class="mb-0">Заказы не найдены</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>

        <!-- Пагинация -->
        <footer class="mt-4">
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= ($page === 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="#" onclick="return navigatePage(<?= $page - 1 ?>)">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
                            <a class="page-link" href="orders.php?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page === $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="#" onclick="return navigatePage(<?= $page + 1 ?>)">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </footer>
    </div>

    <!-- JavaScript -->
    <script>
        $(document).ready(function () {
            // Обработка изменения статуса
            $('.status-select').change(function () {
                const orderId = $(this).data('order-id');
                const msId = $(this).data('ms-id');
                const status = $(this).val();

                // Находим ближайший индикатор статуса
                const indicator = $(this).siblings('.status-indicator');

                // Обновляем цвет индикатора в реальном времени
                const colors = {
                    'Новый': '#4a90e2',
                    'Подтвержден': '#68cc8f',
                    'Собран': '#ffd700',
                    'Отгружен': '#ff6f61',
                    'Доставлен': '#9b59b6',
                    'Возврат': '#e74c3c',
                    'Отменен': '#95a5a6'
                };
                indicator.css('background-color', colors[status] || '#ccc');

                // Отправка запроса на сервер для обновления статуса
                $.ajax({
                    url: 'update_status.php?id=' + orderId,
                    type: 'POST',
                    dataType: 'json',
                    contentType: 'application/json',
                    data: JSON.stringify({ status: status, ms_id: msId }),
                    success: function (response) {
                        if (response.success) {
                            showToast('success', 'Статус успешно обновлен!');
                        } else {
                            showToast('error', 'Ошибка: ' + response.error);
                        }
                    },
                    error: function (xhr) {
                        showToast('error', 'Ошибка соединения с сервером: ' + xhr.statusText);
                    }
                });
            });
        });

        // Всплывающее уведомление
        function showToast(type, message) {
            const toast = document.createElement('div');
            toast.className = `toast bg-${type === 'success' ? 'success' : 'danger'} text-white p-3 rounded position-fixed bottom-0 end-0 m-3`;
            toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i> ${message}`;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        // Навигация по страницам
        function navigatePage(page) {
            if (page < 1 || page > <?= $totalPages ?>) return false;
            window.location.href = `orders.php?page=${page}`;
            return false;
        }
    </script>
</body>
</html>
