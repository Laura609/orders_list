$(document).ready(function() {
    loadData();
});

// Загрузка организаций и агентов
function loadData() {
    $.ajax({
        url: 'api_handler.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if(response.success) {
                populateOrganizations(response.organizations);
                populateAgents(response.agents);
            } else {
                showError(response.error || 'Не удалось загрузить данные');
            }
        },
        error: function(xhr) {
            showError('Ошибка загрузки данных: ' + xhr.statusText);
        }
    });
}

// Заполнение списка организаций
function populateOrganizations(organizations) {
    const $select = $('#organization');
    $select.empty();

    if(organizations && organizations.length > 0) {
        $select.append('<option value="">Выберите организацию</option>');
        organizations.forEach(org => {
            $select.append(`<option value="${org.id}">${org.name}</option>`);
        });
    } else {
        $select.append('<option value="">Нет доступных организаций</option>');
    }
}

// Заполнение списка агентов
function populateAgents(agents) {
    const $select = $('#agent');
    $select.empty();

    if(agents && agents.length > 0) {
        $select.append('<option value="">Выберите агента</option>');
        agents.forEach(agent => {
            $select.append(`<option value="${agent.id}">${agent.name}</option>`);
        });
    } else {
        $select.append('<option value="">Нет доступных агентов</option>');
    }
}

// Отправка формы заказа
function sendOrder() {
    const orderData = {
        order_number: $('#order_number').val(),
        organization: $('#organization').val(),
        agent: $('#agent').val(),
        organization_name: $('#organization option:selected').text(),
        agent_name: $('#agent option:selected').text()
    };

    // Валидация
    if (!orderData.order_number || !orderData.organization || !orderData.agent) {
        alert('Пожалуйста, заполните все поля!');
        return;
    }

    const $btn = $('#submit-btn');
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Создание...');

    $.ajax({
        url: 'add_order.php',
        type: 'POST',
        dataType: 'json',
        contentType: 'application/json',
        data: JSON.stringify(orderData),
        success: function(data) {
            console.log('Ответ сервера:', data);
            
            if (data.success) {
                showAlert('success', 'Заказ успешно создан!');
                
                $('#order-form').fadeOut(300, function() {
                    $('#success-message').html(`
                        <h3>Заказ №${data.order_data.order_number} создан!</h3>
                        <p>Организация: ${data.order_data.organization}</p>
                        <p>Контрагент: ${data.order_data.agent}</p>
                        <button onclick="window.location.href='orders.php'" class="btn btn-primary">
                            Перейти к списку заказов
                        </button>
                    `).fadeIn();
                });
                
                setTimeout(() => {
                    window.location.href = "orders.php";
                }, 5000);
            } else {
                showAlert('danger', 'Ошибка: ' + (data.error || 'Неизвестная ошибка'));
            }
        },
        error: function(xhr) {
            let errorMsg = 'Ошибка соединения: ' + xhr.statusText;
            try {
                const response = JSON.parse(xhr.responseText);
                errorMsg = response.error || errorMsg;
            } catch (e) {}
            showAlert('danger', errorMsg);
        },
        complete: function() {
            $btn.prop('disabled', false).text('Создать заказ');
        }
    });
}

function showAlert(type, message) {
    const $alert = $('#alert-container');
    $alert.html(`<div class="alert alert-${type}">${message}</div>`);
    setTimeout(() => $alert.fadeOut(), 5000);
}

// сообщение об успехе
function showSuccess(message) {
    alert(message);
}

// сообщение об ошибке
function showError(message) {
    alert("Ошибка: " + message);
}