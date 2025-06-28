<?php
// Подключаем файл конфигурации

require_once 'config.php';
require_once 'includes/header.php';

// Функция для получения всех пользователей
function getAllUsers($pdo) {
    // Получаем параметры фильтрации
    $roleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : null;
    $groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;
    
    // Базовый SQL-запрос
    $sql = "
        SELECT DISTINCT u.* 
        FROM timetrack_users u 
    ";
    
    $params = [];
    //////////////////////////////////////////////////////////
    // Добавляем JOIN для групп, если фильтр по группе активен
    if (isManager() or isEmployee()) {
        $stmt = $pdo->prepare("SELECT group_id FROM timetrack_user_groups WHERE user_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $managerGroup = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($managerGroup) {
            $groupId = $managerGroup['group_id'];
        }
    }
    //////////////////////////////////////////////////////////
    if ($groupId) {
        // Get all child group IDs
        $childGroups = [];
        $stmt = $pdo->prepare("
            WITH RECURSIVE child_groups AS (
                SELECT id FROM timetrack_groups WHERE id = ?
                UNION ALL
                SELECT g.id FROM timetrack_groups g
                JOIN child_groups cg ON g.parent_id = cg.id
            )
            SELECT id FROM child_groups
        ");
        $stmt->execute([$groupId]);
        $childGroups = array_column($stmt->fetchAll(), 'id');
        
        $sql .= " JOIN timetrack_user_groups ug ON u.id = ug.user_id ";
        $sql .= " WHERE ug.group_id IN (" . implode(',', $childGroups) . ")";
    }
    
    // Добавляем условия WHERE
    $conditions = [];
    
    if ($roleId) {
        $conditions[] = "u.role_id = ?";
        $params[] = $roleId;
    }
    
    // Добавляем WHERE, если есть условия
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
    
    // Если пользователь - менеджер, показываем только его группу
    
    //echo $sql;
    // Выполняем запрос
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



// Функция для получения событий календаря
function getCalendarEvents($pdo, $userId = null, $startDate = null, $endDate = null) {
    $sql = "
        SELECT  u.*,e.*,
        e.description as description
        FROM timetrack_time_entries e
        JOIN timetrack_users u ON e.user_id = u.id
    ";
    
    $params = [];
    
    if ($userId) {
        $sql .= " AND e.user_id = ?";
        $params[] = $userId;
    }
    
    if ($startDate) {
        $sql .= " AND e.date >= ?";
        $params[] = $startDate;
    }
    
    if ($endDate) {
        $sql .= " AND e.date <= ?";
        $params[] = $endDate;
    }
    

     
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Функция для получения всех ролей
function getAllRoles($pdo) {
    $stmt = $pdo->query("SELECT * FROM timetrack_roles ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Функция для получения всех групп
function getAllGroups($pdo) {
    // Get all groups with their hierarchy
    function getGroupsHierarchy($pdo, $parent_id = null, $level = 0) {
            // Если пользователь - менеджер, показываем только события его группы
    


        $stmt = $pdo->prepare("SELECT * FROM timetrack_groups WHERE parent_id " . ($parent_id === null ? "IS NULL" : "= ?") . " ORDER BY name");
        $stmt->execute($parent_id === null ? [] : [$parent_id]);
        $groups = $stmt->fetchAll();
        
        $result = [];
        foreach ($groups as $group) {
            $group['level'] = $level;
            $result[] = $group;
            $result = array_merge($result, getGroupsHierarchy($pdo, $group['id'], $level + 1));
        }
        //print_r($result);
        return $result;
    }

    return getGroupsHierarchy($pdo);
}

// Получаем всех пользователей
$users = getAllUsers($pdo);

// Получаем все роли и группы
//$roles = getAllRoles($pdo);
$groups = getAllGroups($pdo);

// Получаем ID выбранного пользователя (если есть)
$selectedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

// Получаем выбранные фильтры
//$selectedRoleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : null;

$selectedGroupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;

// Если пользователь - менеджер и группа не выбрана, устанавливаем его группу по умолчанию

// Получаем текущий месяц и год
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Формируем даты начала и конца месяца для выборки событий
$startDate = date('Y-m-01', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
$endDate = date('Y-m-t', mktime(0, 0, 0, $currentMonth, 1, $currentYear));

// Получаем события календаря
$events = getCalendarEvents($pdo, $selectedUserId, $startDate, $endDate);

// Формируем массив событий для FullCalendar
$calendarEvents = [];
foreach ($events as $event) {
    $calendarEvents[] = [
        'id' => $event['id'],
        'title' => $event['description'],
        'start' => $event['date'],
        'className' => isset($event['date']) ? 'workday' : 'weekend',
        'extendedProps' => [
            'userId' => $event['user_id'],
            'userName' => $event['login'],
            'type' => $event['user_id'],
            'note' => $event['description']
        ]
    ];
}

// Дни недели для отображения
$weekDays = [
    0 => 'Воскресенье',
    1 => 'Понедельник',
    2 => 'Вторник',
    3 => 'Среда',
    4 => 'Четверг',
    5 => 'Пятница',
    6 => 'Суббота'
];

// Получаем название месяца на русском
$months = [
    1 => 'Январь',
    2 => 'Февраль',
    3 => 'Март',
    4 => 'Апрель',
    5 => 'Май',
    6 => 'Июнь',
    7 => 'Июль',
    8 => 'Август',
    9 => 'Сентябрь',
    10 => 'Октябрь',
    11 => 'Ноябрь',
    12 => 'Декабрь'
];
?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Фильтры и настройки -->
        <div class="col-md-2">
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-funnel"></i> Фильтры</h5>
                </div>
                <div class="card-body">
                    <form action="" method="get" id="filterForm">
                        <div class="mb-3">
                            <label for="user_id" class="form-label">Сотрудник</label>
                            <select class="form-select" id="user_id" name="user_id">
                                <option value="">Все сотрудники</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo ($selectedUserId == $user['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['login']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                       <!--  
                        <div class="mb-3">
                            <label for="role_id" class="form-label">Роль</label>
                            <select class="form-select" id="role_id" name="role_id">
                                <option value="">Все роли</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>" <?php echo ($selectedRoleId == $role['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div> 
                        -->
                        
                        <div class="mb-3">
                            <label for="group_id" class="form-label">Группа</label>
                            <select class="form-select" id="group_id" name="group_id">
                                <option value="">Все группы</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>" <?php echo ($selectedGroupId == $group['id']) ? 'selected' : ''; ?>>
                                        <?php echo str_repeat('—', $group['level']) . ' ' . htmlspecialchars($group['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        

                        
                        <div class="mb-3">
                            <label for="month" class="form-label">Месяц</label>
                            <select class="form-select" id="month" name="month">
                                <?php foreach ($months as $num => $name): ?>
                                    <option value="<?php echo $num; ?>" <?php echo ($currentMonth == $num) ? 'selected' : ''; ?>>
                                        <?php echo $name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="year" class="form-label">Год</label>
                            <select class="form-select" id="year" name="year">
                                <?php for ($year = date('Y') - 2; $year <= date('Y') + 2; $year++): ?>
                                    <option value="<?php echo $year; ?>" <?php echo ($currentYear == $year) ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check-lg"></i> Применить
                        </button>
                    </form>
                </div>
            </div>

        </div>
        
        <!-- Календарь -->
        <div class="col-md-10">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar3"></i> 
                        Календарь: <?php echo $months[$currentMonth] . ' ' . $currentYear; ?>
                    </h5>
                    <div class="calendar-nav">
                        <!-- Кнопка массового редактирования -->
                        <button id="bulkEditBtn" class="btn btn-warning btn-sm" style="display: none;">
                            <i class="bi bi-pencil-square"></i> Редактировать выбранные
                        </button>
                        <button id="clearSelectionBtn" class="btn btn-secondary btn-sm" style="display: none;">
                            <i class="bi bi-x-circle"></i> Очистить выбор
                        </button>
                        <a href="?month=<?php echo ($currentMonth == 1) ? 12 : ($currentMonth - 1); ?>&year=<?php echo ($currentMonth == 1) ? ($currentYear - 1) : $currentYear; ?>&user_id=<?php echo $selectedUserId; ?>&group_id=<?php echo $selectedGroupId; ?>" class="btn btn-light btn-sm">
                            <i class="bi bi-chevron-left"></i> Пред. месяц
                        </a>
                        <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>&user_id=<?php echo $selectedUserId; ?>&group_id=<?php echo $selectedGroupId; ?>" class="btn btn-light btn-sm">
                            <i class="bi bi-calendar-check"></i> Сегодня
                        </a>
                        <a href="?month=<?php echo ($currentMonth == 12) ? 1 : ($currentMonth + 1); ?>&year=<?php echo ($currentMonth == 12) ? ($currentYear + 1) : $currentYear; ?>&user_id=<?php echo $selectedUserId; ?>&group_id=<?php echo $selectedGroupId; ?>" class="btn btn-light btn-sm">
                            След. месяц <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="users-calendar">
                        <?php
                        // Получаем количество дней в месяце
                        $daysInMonth = date('t', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
                        
                        // Создаем таблицу календаря
                        echo '<table class="calendar-table">';
                        
                        // Создаем заголовок таблицы
                        echo '<thead><tr><th>Сотрудник</th>';
                        
                        // Добавляем ячейки для каждого дня месяца
                        for ($day = 1; $day <= $daysInMonth; $day++) {
                            echo '<th>' . $day . '</th>';
                        }
                        
                        echo '</tr></thead>';
                        
                        // Создаем тело таблицы
                        echo '<tbody>';
                        //print_r($events);
                        // Преобразуем массив событий в более удобный формат
                        $eventsMap = [];
                        foreach ($events as $event) {
                            $day = (int)date('j', strtotime($event['date']));
                            if (!isset($eventsMap[$event['user_id']])) {
                                $eventsMap[$event['user_id']] = [];
                            }
                            $eventsMap[$event['user_id']][$day] = $event;
                        }
                        
                        // Добавляем строки для каждого пользователя
                        // Если выбран конкретный пользователь, показываем только его
                        $displayUsers = $selectedUserId ? array_filter($users, function($user) use ($selectedUserId) {
                            return $user['id'] == $selectedUserId;
                        }) : $users;
                        
                        foreach ($displayUsers as $user) {
                            echo '<tr>';
                            
                            // Первая ячейка - имя пользователя
                            echo '<td class="user-name">' . htmlspecialchars($user['login']) . '</td>';
                            
                            // Добавляем ячейки для каждого дня месяца
                            for ($day = 1; $day <= $daysInMonth; $day++) {
                                $dateStr = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                                
                                $class = 'day-cell';
                                $title = '';
                                //
                                // Проверяем, есть ли событие для этого дня и пользователя
                                if (isset($eventsMap[$user['id']][$day])) {
                                    $event = $eventsMap[$user['id']][$day];
                                    //print_r($event);
                                    //echo '<br>';
                                    //print_r($event['date'].$event['description']);
                                    $class .=$event['is_holiday'] == '0' ? ' workday' : ' holiday';
                                    $title = $event['is_holiday'] == '0' ? 'Рабочий день. '.$event['start_time'].'-'.$event['end_time'].'. '.$event['description']: 'Отгул.'.$event['description'];
                                } else {
                                    // Проверяем, является ли день выходным по умолчанию (суббота, воскресенье)
                                    $dayOfWeek = date('N', strtotime($dateStr));
                                    
                                    $title = '' ?: ($dayOfWeek < '6' ? 'Рабочий день' : 'Выходной');
                                    if ($dayOfWeek >= 6) { // Суббота (6) или воскресенье (7)
                                        $class .= ' weekend';
                                    }
                                }
                                
                                echo '<td class="' . $class . '" title="' . htmlspecialchars($title) . '" 
                                    data-user-id="' . $user['id'] . '" 
                                    data-date="' . $dateStr . '"
                                    ></td>';
                            }
                            
                            echo '</tr>';
                        }
                        
                        echo '</tbody></table>';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Легенда для календаря -->
<div class="container mt-3 mb-4">
    <div class="legend bg-light p-3 rounded shadow-sm">
        <div class="legend-item">
            <div class="legend-color workday-color"></div>
            <span class="ms-2">Рабочий день</span>
        </div>
        <div class="legend-item">
            <div class="legend-color weekend-color"></div>
            <span class="ms-2">Выходной</span>
        </div>
    </div>
</div>

<style>
    /* Стили для календаря */
    .users-calendar {
        margin: 0;
        overflow-x: auto;
        max-width: 100%;
    }
    
    .users-calendar table {
        table-layout: fixed;
        width: 100%;
        margin: 0;
    }
    
    .users-calendar th, 
    .users-calendar td {
        text-align: center;
        padding: 8px;
        width: 40px;
        height: 40px;
        border: 1px solid #dee2e6;
        transition: all 0.2s ease;
    }
    
    .users-calendar th:first-child, 
    .users-calendar td:first-child {
        width: 200px;
        text-align: left;
        position: sticky;
        left: 0;
        background-color: #f8f9fa;
        z-index: 1;
        font-weight: 600;
        padding-left: 15px;
    }
    
    .users-calendar th {
        position: sticky;
        top: 0;
        background-color: #f8f9fa;
        z-index: 2;
        font-weight: 600;
    }
    
    .users-calendar th:first-child {
        z-index: 3;
    }
    
    .day-cell {
        cursor: pointer;
        transition: all 0.2s ease;
        border-radius: 4px;
        margin: 2px;
    }
    
    .day-cell:hover {
        transform: scale(1.1);
        box-shadow: 0 0 5px rgba(0,0,0,0.1);
    }
    
    .day-cell.workday {
        background-color: #d4edda;
        border-color: #c3e6cb;
    }
    
    .day-cell.weekend {
        background-color: #fff3cd;
        border-color: #ffeeba;
    }
       
    .day-cell.holiday {
        background-color:rgb(238, 73, 13);
        border-color: #ffeeba;
    }
    
    .calendar-nav {
        display: flex;
        gap: 10px;
    }
    
    .calendar-nav .btn {
        padding: 0.25rem 0.75rem;
        font-size: 0.875rem;
    }
    
    .legend {
        display: flex;
        justify-content: center;
        gap: 20px;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        padding: 5px 10px;
        border-radius: 4px;
    }
    
    .legend-color {
        width: 20px;
        height: 20px;
        border-radius: 4px;
        border: 1px solid #dee2e6;
    }
    
    .workday-color {
        background-color: #d4edda;
        border-color: #c3e6cb;
    }
    
    .weekend-color {
        background-color: #fff3cd;
        border-color: #ffeeba;
    }
    
    /* Стили для модальных окон */
    .modal-content {
        border: none;
        border-radius: 8px;
        box-shadow: 0 0 20px rgba(0,0,0,0.1);
    }
    
    .modal-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        border-radius: 8px 8px 0 0;
    }
    
    .modal-footer {
        background-color: #f8f9fa;
        border-top: 1px solid #dee2e6;
        border-radius: 0 0 8px 8px;
    }
    
    /* Анимации */
    .card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
    }
    
    /* Адаптивность */
    @media (max-width: 768px) {
        .users-calendar th:first-child, 
        .users-calendar td:first-child {
            width: 150px;
        }
        
        .users-calendar th, 
        .users-calendar td {
            padding: 4px;
            width: 30px;
            height: 30px;
        }
        
        .calendar-nav {
            flex-direction: column;
            gap: 5px;
        }
        
        .calendar-nav .btn {
            width: 100%;
        }
    }
    
    .day-cell.selected {
        background-color: #007bff !important;
        color: white !important;
        border-color: #0056b3 !important;
    }
    
    .day-cell.selected.workday {
        background-color: #007bff !important;
        color: white !important;
    }
    
    .day-cell.selected.holiday {
        background-color: #007bff !important;
        color: white !important;
    }
    
    .day-cell.selected.weekend {
        background-color: #007bff !important;
        color: white !important;
    }
    
    /* Стили для кнопки выбора дней */
    .btn-outline-primary {
        border-color: #007bff;
        color: #007bff;
        background-color: transparent;
    }
    
    .btn-outline-primary:hover {
        background-color: #007bff;
        border-color: #007bff;
        color: white;
    }
    
    .btn-outline-primary.active,
    .btn-outline-primary:active {
        background-color: #0056b3 !important;
        border-color: #0056b3 !important;
        color: white !important;
    }
    
    /* Горизонтальный скролл для календаря */
    .users-calendar {
        margin: 0;
        overflow-x: auto;
        max-width: 100%;
    }
    
    .calendar-table {
        min-width: 800px; /* Минимальная ширина таблицы */
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let selectedCells = [];
        let isSelectionMode = false;
        
        // Проверяем права доступа
        const isAdmin = <?php echo isAdmin() ? 'true' : 'false'; ?>;
        const isManager = <?php echo isManager() ? 'true' : 'false'; ?>;
        const canEdit = isAdmin || isManager;
        
        // Переключение режима выбора
        function toggleSelectionMode() {
            if (!canEdit) {
                alert('У вас нет прав для редактирования записей времени');
                return;
            }
            
            isSelectionMode = !isSelectionMode;
            const dayCells = document.querySelectorAll('.day-cell');
            const toggleSelectionBtn = document.querySelector('.btn-outline-primary');
            
            dayCells.forEach(cell => {
                if (isSelectionMode) {
                    cell.style.cursor = 'crosshair';
                } else {
                    cell.style.cursor = 'pointer';
                    cell.classList.remove('selected');
                }
            });
            
            // Обновляем состояние кнопки
            if (isSelectionMode) {
                toggleSelectionBtn.classList.add('active');
            } else {
                toggleSelectionBtn.classList.remove('active');
            }
            
            selectedCells = [];
            updateBulkEditButton();
        }
        
        // Обновление кнопки массового редактирования
        function updateBulkEditButton() {
            const bulkEditBtn = document.getElementById('bulkEditBtn');
            const clearSelectionBtn = document.getElementById('clearSelectionBtn');
            
            if (selectedCells.length > 0 && canEdit) {
                bulkEditBtn.style.display = 'inline-block';
                clearSelectionBtn.style.display = 'inline-block';
            } else {
                bulkEditBtn.style.display = 'none';
                clearSelectionBtn.style.display = 'none';
            }
        }
        
        // Обработчик клика по ячейке календаря
        const dayCells = document.querySelectorAll('.day-cell');
        dayCells.forEach(cell => {
            cell.addEventListener('click', function(e) {
                if (isSelectionMode) {
                    // Режим выбора нескольких дней
                    if (!canEdit) {
                        alert('У вас нет прав для редактирования записей времени');
                        return;
                    }
                    
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (this.classList.contains('selected')) {
                        this.classList.remove('selected');
                        selectedCells = selectedCells.filter(c => c !== this);
                    } else {
                        this.classList.add('selected');
                        selectedCells.push(this);
                    }
                    
                    updateBulkEditButton();
                } 
                
                
                else {
                    // Обычный режим редактирования одного дня
                    /*
                    if (!canEdit) {
                        // Показываем только информацию о записи без возможности редактирования
                        const userId = this.getAttribute('data-user-id');
                        const date = this.getAttribute('data-date');
                        
                        // Получаем информацию о записи для просмотра
                        fetch('get_time_entry.php?user_id=' + userId + '&date=' + date)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success && data.entry) {
                                    alert(`Информация о записи:\nДата: ${date}\nВремя: ${data.entry.start_time || 'Не указано'} - ${data.entry.end_time || 'Не указано'}\nВыходной: ${data.entry.is_holiday == 1 ? 'Да' : 'Нет'}\nИсключено часов: ${data.entry.exclude_hours || 0}\nОписание: ${data.entry.description || 'Нет'}`);
                                } else {
                                    alert(`Нет записи для ${date}`);
                                }
                            });
                        return;
                    }
                    */
                    const userId = this.getAttribute('data-user-id');
                    const date = this.getAttribute('data-date');
                    
                    // Заполняем форму
                    document.getElementById('edit_user_id').value = userId;
                    document.getElementById('edit_date').value = date;
                    
                    // Получаем существующую запись
                    fetch('get_time_entry.php?user_id=' + userId + '&date=' + date)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.entry) {
                                document.getElementById('edit_start_time').value = data.entry.start_time || '';
                                document.getElementById('edit_end_time').value = data.entry.end_time || '';
                                document.getElementById('edit_is_holiday').checked = data.entry.is_holiday == 1;
                                document.getElementById('edit_exclude_hours').value = data.entry.exclude_hours || 0;
                                document.getElementById('edit_description').value = data.entry.description || '';
                            } else {
                                document.getElementById('edit_start_time').value = '';
                                document.getElementById('edit_end_time').value = '';
                                document.getElementById('edit_is_holiday').checked = false;
                                document.getElementById('edit_exclude_hours').value = 0;
                                document.getElementById('edit_description').value = '';
                            }
                            
                            // Показываем модальное окно
                            var editModal = new bootstrap.Modal(document.getElementById('editTimeEntryModal'));
                        });
                }
            });
        });
        
        // Обработчик кнопки массового редактирования
        document.getElementById('bulkEditBtn').addEventListener('click', function() {
            if (!canEdit) {
                alert('У вас нет прав для редактирования записей времени');
                return;
            }
            // Исправление: модальное окно открывается только если режим выбора активен и есть выбранные ячейки
            if (!isSelectionMode || selectedCells.length === 0) {
                return;
            }
            // Показываем выбранные дни
            const selectedDaysList = document.getElementById('selectedDaysList');
            const daysList = selectedCells.map(cell => {
                const date = cell.getAttribute('data-date');
                const userId = cell.getAttribute('data-user-id');
                // Получаем логин пользователя из строки таблицы
                const userRow = cell.closest('tr');
                const userName = userRow.querySelector('.user-name').textContent;
                return `${date} (${userName})`;
            }).join('<br>');
            selectedDaysList.innerHTML = daysList;
            // Показываем модальное окно
            var bulkEditModal = new bootstrap.Modal(document.getElementById('bulkEditModal'));
            bulkEditModal.show();
        });
        
        // Обработчик кнопки очистки выбора
        document.getElementById('clearSelectionBtn').addEventListener('click', function() {
            selectedCells.forEach(cell => cell.classList.remove('selected'));
            selectedCells = [];
            updateBulkEditButton();
        });
        
        // Обработчик отправки формы массового редактирования
        document.getElementById('bulkEditForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!canEdit) {
                alert('У вас нет прав для редактирования записей времени');
                return;
            }
            
            const formData = new FormData(this);
            
            // Добавляем выбранные дни
            const selectedDays = selectedCells.map(cell => ({
                user_id: cell.getAttribute('data-user-id'),
                date: cell.getAttribute('data-date')
            }));
            
            formData.append('selected_days', JSON.stringify(selectedDays));
            
            fetch('bulk_update_time_entries.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Закрываем модальное окно
                    var bulkEditModal = bootstrap.Modal.getInstance(document.getElementById('bulkEditModal'));
                    bulkEditModal.hide();
                    
                    // Очищаем выбор
                    selectedCells.forEach(cell => cell.classList.remove('selected'));
                    selectedCells = [];
                    updateBulkEditButton();
                    
                    // Перезагружаем страницу для обновления данных
                    window.location.reload();
                } else {
                    alert('Ошибка при сохранении: ' + (data.message || 'Неизвестная ошибка'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ошибка при сохранении данных');
            });
        });
        
        // Добавляем кнопку переключения режима выбора только для тех, кто может редактировать
        if (canEdit) {
            const calendarNav = document.querySelector('.calendar-nav');
            const toggleSelectionBtn = document.createElement('button');
            toggleSelectionBtn.className = 'btn btn-outline-primary btn-sm';
            toggleSelectionBtn.innerHTML = '<i class="bi bi-check2-square"></i> Выбор дней';
            toggleSelectionBtn.onclick = toggleSelectionMode;
            calendarNav.insertBefore(toggleSelectionBtn, calendarNav.firstChild);
        }
        
        // Обработчик отправки формы редактирования одного дня
        document.getElementById('editTimeEntryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!canEdit) {
                alert('У вас нет прав для редактирования записей времени');
                return;
            }
            
            const formData = new FormData(this);
            
            fetch('update_time_entry.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Закрываем модальное окно
                    var editModal = bootstrap.Modal.getInstance(document.getElementById('editTimeEntryModal'));
                    editModal.hide();
                    
                    // Перезагружаем страницу для обновления данных
                    window.location.reload();
                } else {
                    alert('Ошибка при сохранении: ' + (data.message || 'Неизвестная ошибка'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ошибка при сохранении данных');
            });
        });
        
        // Инициализация календаря
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'ru',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek'
            },
            height: 'auto',
            selectable: true,
            selectMirror: true,
            dayMaxEvents: true,
            weekends: true,
            firstDay: 1, // Понедельник - первый день недели
            
            // События календаря
            events: <?php echo json_encode($calendarEvents); ?>,
            
            // Обработчик клика по событию
            eventClick: function(info) {
                var event = info.event;
                var props = event.extendedProps;
                
                // Заполняем модальное окно данными события
                document.getElementById('eventUserName').textContent = props.userName;
                document.getElementById('eventDate').textContent = event.start.toLocaleDateString('ru-RU');
                document.getElementById('eventType').textContent = props.type === 'workday' ? 'Рабочий день' : 'Выходной';
                document.getElementById('eventTitle').textContent = event.title;
                document.getElementById('eventNote').textContent = props.note || 'Нет примечания';
                
                // Сохраняем ID события для удаления
                document.getElementById('deleteEventBtn').setAttribute('data-event-id', event.id);
                
                // Сохраняем данные для редактирования
                document.getElementById('edit_event_id').value = event.id;
                document.getElementById('edit_user_id').value = props.userId;
                document.getElementById('edit_date').value = event.start.toISOString().split('T')[0];
                document.getElementById('edit_type').value = props.type;
                document.getElementById('edit_title').value = event.title;
                document.getElementById('edit_note').value = props.note || '';
                
                // Открываем модальное окно
                var eventModal = new bootstrap.Modal(document.getElementById('eventModal'));
                eventModal.show();
            },
            
            // Обработчик клика по дате
            dateClick: function(info) {
                // Заполняем форму добавления события выбранной датой
                document.getElementById('event_date').value = info.dateStr;
                
                // Если выбран пользователь в фильтре, используем его
                var userIdSelect = document.getElementById('user_id');
                if (userIdSelect.value) {
                    document.getElementById('event_user_id').value = userIdSelect.value;
                }
                
                // Открываем модальное окно для добавления события
                var addEventForm = document.getElementById('addEventForm');
                addEventForm.scrollIntoView({ behavior: 'smooth' });
            }
        });
        
        calendar.render();
        
        // Обработчик кнопки удаления события
        document.getElementById('deleteEventBtn').addEventListener('click', function() {
            if (confirm('Вы уверены, что хотите удалить это событие?')) {
                var eventId = this.getAttribute('data-event-id');
                var form = document.createElement('form');
                form.method = 'post';
                form.action = '../controllers/event_controller.php';
                
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'event_id';
                input.value = eventId;
                
                var actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_event';
                
                form.appendChild(input);
                form.appendChild(actionInput);
                document.body.appendChild(form);
                form.submit();
            }
        });
        
        // Обработчик кнопки редактирования события
        document.getElementById('editEventBtn').addEventListener('click', function() {
            // Закрываем текущее модальное окно
            var eventModal = bootstrap.Modal.getInstance(document.getElementById('eventModal'));
            eventModal.hide();
            
            // Открываем модальное окно редактирования
            var editEventModal = new bootstrap.Modal(document.getElementById('editEventModal'));
            editEventModal.show();
        });
        
        // Обработчик отправки формы фильтров
        document.getElementById('filterForm').addEventListener('submit', function(e) {
            // Создаем URL с параметрами
            var url = new URL(window.location.href);
            
            // Удаляем пустые параметры
            ['user_id', 'role_id', 'group_id', 'month', 'year'].forEach(param => {
                if (!document.getElementById(param).value) {
                    url.searchParams.delete(param);
                }
            });
            
            // Если есть параметры, перенаправляем
            if (url.searchParams.toString()) {
                window.location.href = url.toString();
                e.preventDefault();
            }
        });
    });
</script>

<!-- Модальное окно для просмотра события -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventModalLabel">Информация о событии</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Сотрудник:</label>
                    <p id="eventUserName"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Дата:</label>
                    <p id="eventDate"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Тип дня:</label>
                    <p id="eventType"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Название:</label>
                    <p id="eventTitle"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Примечание:</label>
                    <p id="eventNote"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-danger" id="deleteEventBtn">Удалить</button>
                <button type="button" class="btn btn-primary" id="editEventBtn">Редактировать</button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для редактирования события -->
<div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editEventModalLabel">Редактировать событие</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="../controllers/event_controller.php" method="post" id="editEventForm">
                <input type="hidden" id="edit_event_id" name="event_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_user_id" class="form-label">Сотрудник</label>
                        <select class="form-select" id="edit_user_id" name="user_id" required>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_date" class="form-label">Дата</label>
                        <input type="date" class="form-control" id="edit_date" name="date" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_type" class="form-label">Тип дня</label>
                        <select class="form-select" id="edit_type" name="type" required>
                            <option value="workday">Рабочий день</option>
                            <option value="weekend">Выходной</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_title" class="form-label">Название</label>
                        <input type="text" class="form-control" id="edit_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_note" class="form-label">Примечание</label>
                        <textarea class="form-control" id="edit_note" name="note" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" name="action" value="update_event" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно для редактирования записи времени -->
<div class="modal fade" id="editTimeEntryModal" tabindex="-1" aria-labelledby="editTimeEntryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTimeEntryModalLabel">Редактировать запись времени</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editTimeEntryForm" method="POST" action="update_time_entry.php">
                    <input type="hidden" id="edit_user_id" name="user_id">
                    <input type="hidden" id="edit_date" name="date">
                    
                    <div class="mb-3">
                        <label for="edit_start_time" class="form-label">Время начала:</label>
                        <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_end_time" class="form-label">Время окончания:</label>
                        <input type="time" class="form-control" id="edit_end_time" name="end_time" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="edit_is_holiday" name="is_holiday">
                                    <label class="form-check-label" for="edit_is_holiday">Выходной день</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_exclude_hours" class="form-label">Исключить часов:</label>
                                <input type="number" class="form-control" id="edit_exclude_hours" name="exclude_hours" min="0" max="24" step="0.5" placeholder="0">
                                <small class="form-text text-muted">Часы для вычета из нормы</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Описание:</label>
                        <input type="text" class="form-control" id="edit_description" name="description">
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для массового редактирования -->
<div class="modal fade" id="bulkEditModal" tabindex="-1" aria-labelledby="bulkEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkEditModalLabel">Массовое редактирование записей</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <h6>Выбранные дни:</h6>
                    <div id="selectedDaysList" class="alert alert-info"></div>
                </div>
                
                <form id="bulkEditForm" method="POST" action="bulk_update_time_entries.php">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bulk_start_time" class="form-label">Время начала:</label>
                                <input type="time" class="form-control" id="bulk_start_time" name="start_time">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bulk_end_time" class="form-label">Время окончания:</label>
                                <input type="time" class="form-control" id="bulk_end_time" name="end_time">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="bulk_is_holiday" name="is_holiday">
                                    <label class="form-check-label" for="bulk_is_holiday">Выходной день</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bulk_exclude_hours" class="form-label">Исключить часов:</label>
                                <input type="number" class="form-control" id="bulk_exclude_hours" name="exclude_hours" min="0" max="24" step="0.5" placeholder="0">
                                <small class="form-text text-muted">Количество часов, которые будут вычтены из месячной нормы</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bulk_description" class="form-label">Описание:</label>
                        <input type="text" class="form-control" id="bulk_description" name="description">
                    </div>
                    
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<?php require_once 'includes/footer.php'; ?> 

<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.17/index.global.min.js'></script>
