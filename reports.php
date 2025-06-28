<?php

require_once 'config.php';
// Check if user has permission to view reports
if (!isAdmin() && !isManager() && !isEmployee()) {
    redirect('dashboard.php');
}

require_once 'includes/header.php';


// Get filter parameters
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_role = isset($_GET['role']) ? $_GET['role'] : '';
$search_group = isset($_GET['group']) ? (int)$_GET['group'] : null;
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

// Если пользователь - менеджер и группа не выбрана, устанавливаем его группу по умолчанию
if (isManager() ) {
    $stmt = $pdo->prepare("SELECT group_id FROM timetrack_user_groups WHERE user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $managerGroup = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($managerGroup) {
        $group_id = $managerGroup['group_id'];
    }
}

if(isEmployee()){
    $user_id=$_SESSION['user_id'];
}

// Get groups for filter
function getGroupsHierarchy($pdo, $parent_id = null, $level = 0) {
    $stmt = $pdo->prepare("SELECT * FROM timetrack_groups WHERE parent_id " . ($parent_id === null ? "IS NULL" : "= ?") . " ORDER BY name");
    $stmt->execute($parent_id === null ? [] : [$parent_id]);
    $groups = $stmt->fetchAll();
    
    $result = [];
    foreach ($groups as $group) {
        $group['level'] = $level;
        $result[] = $group;
        $result = array_merge($result, getGroupsHierarchy($pdo, $group['id'], $level + 1));
    }
    return $result;
}
function countWeekdays($startDate, $endDate) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $end->modify('+1 day'); // включаем последний день

    $interval = new DateInterval('P1D');
    $dateRange = new DatePeriod($start, $interval, $end);

    $weekdays = 0;

    foreach ($dateRange as $date) {
        $dayOfWeek = $date->format('N'); // 1 = Пн, 7 = Вс
        if ($dayOfWeek < 6) {
            $weekdays++;
        }
    }

    return $weekdays;
}

$groups = getGroupsHierarchy($pdo);

// Формируем даты для фильтрации
$start_date = sprintf('%04d-%02d-01', $year, $month);
$end_date = sprintf('%04d-%02d-%02d', $year, $month, date('t', strtotime($start_date)));

// Строим SQL запрос
$query = "
    WITH user_bonuses AS (
        SELECT user_id, 
        SUM(amount) AS bonuses_amount,
        GROUP_CONCAT(
            CONCAT(type, ':', amount, ':', COALESCE(description, ''))
            SEPARATOR '|'
        ) as bonus_details
        FROM timetrack_user_bonuses
        WHERE EXISTS (
            SELECT 1 FROM timetrack_time_entries te2
            WHERE te2.user_id = timetrack_user_bonuses.user_id
              AND YEAR(te2.date) BETWEEN YEAR(timetrack_user_bonuses.start_date) AND YEAR(timetrack_user_bonuses.end_date)
              AND MONTH(te2.date) BETWEEN MONTH(timetrack_user_bonuses.start_date) AND MONTH(timetrack_user_bonuses.end_date)
        )
        GROUP BY user_id
    ),
    user_groups AS (
        SELECT user_id, GROUP_CONCAT(g.name) as group_names
        
        FROM timetrack_user_groups ug
        JOIN timetrack_groups g ON ug.group_id = g.id
        GROUP BY user_id
    ),
    working_hours AS (
        SELECT 
        count(*) as working_days,
        count(case when is_holiday = 0 then 1 end) as days_off,
            user_id,
            SUM(CASE 
                WHEN is_holiday = 0 AND TIME_TO_SEC(TIMEDIFF(end_time, start_time)) > TIME_TO_SEC(TIMEDIFF(work_end,work_start ))
                THEN TIME_TO_SEC(TIMEDIFF(end_time, start_time)) - TIME_TO_SEC(TIMEDIFF( work_end,work_start)) 
                ELSE 0 
            END) / 3600 as overtime_hours,
            SUM(CASE 
                WHEN is_holiday = 0
                THEN TIME_TO_SEC(TIMEDIFF(end_time, start_time)) 
                ELSE 0 
            END) / 3600 as total_hours,
            SUM(CASE 
                WHEN is_holiday = 1
                THEN TIME_TO_SEC(TIMEDIFF(end_time, start_time)) 
                ELSE 0 
            END) / 3600 as holiday_hours
            ,SUM(COALESCE(exclude_hours, 0)) as total_exclude_hours
            ,min(TIME_TO_SEC(TIMEDIFF( work_end,work_start)))/3600 as full_time
            ,?  as work_need_days
        FROM timetrack_users
        left join timetrack_time_entries on timetrack_time_entries.user_id=timetrack_users.id
        WHERE date between  ? AND   ?
        GROUP BY user_id
    )

SELECT 
    u.id,
    u.login,
    u.first_name,
    u.last_name,
    u.role,
    u.hourly_rate as monthly_rate,
    ug.group_names as group_name,
    ub.bonus_details,
    u.payer,
    u.note,
    u.work_start,
    u.work_end,
    COALESCE(wh.working_days, 0) as working_days,
    COALESCE(wh.days_off, 0) as days_off,
    (wh.total_hours) -((wh.full_time*COALESCE(wh.work_need_days, 0))-COALESCE(wh.total_exclude_hours, 0))   as overtime_hours,
    COALESCE(wh.total_hours, 0) as total_hours,
    COALESCE(wh.holiday_hours, 0) as holiday_hours,
    COALESCE(wh.total_exclude_hours, 0) as total_exclude_hours,
    COALESCE(ub.bonuses_amount, 0) as total_bonuses,

    -- Расчет базовой ставки в час
    (u.hourly_rate 
    ) / (((TIME_TO_SEC(TIMEDIFF( work_end,work_start)))/3600*COALESCE(wh.work_need_days, 0))-COALESCE(wh.total_exclude_hours, 0)) as hourly_rate_calculated,
    
    -- Часов в месяц
    (((TIME_TO_SEC(TIMEDIFF( work_end,work_start)))/3600*COALESCE(wh.work_need_days, 0))-COALESCE(wh.total_exclude_hours, 0)) as full_work_time
    FROM timetrack_users u
right JOIN user_groups ug ON u.id = ug.user_id
LEFT JOIN working_hours wh ON u.id = wh.user_id
LEFT JOIN user_bonuses ub ON u.id = ub.user_id
WHERE 1=1";

$workdays=countWeekdays($start_date, $end_date);
$params = [$workdays,$start_date, $end_date];

// Add group filter if selected
if ($group_id ) {
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
    $stmt->execute([$group_id]);
    $childGroups = array_column($stmt->fetchAll(), 'id');
    
    $query .= " AND u.id IN (SELECT user_id FROM timetrack_user_groups WHERE group_id IN (" . implode(',', $childGroups) . "))";
}
/* if(isManager())
{
    $query .= " AND u.id IN (SELECT user_id FROM timetrack_user_groups WHERE group_id in (select group_id FROM timetrack_user_groups where user_id = ?))";
    $params[] = $_SESSION['user_id'];
}
 */

// Add user filter if selected
if ($user_id) {
    $query .= " AND u.id = ?";
    $params[] = $user_id;
}

// Add search conditions
if ($search) {
    $query .= " AND (u.login LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($search_role) {
    $query .= " AND u.role = ?";
    $params[] = $search_role;
}

if ($search_group) {
    $query .= " AND ug.group_id IN (
        WITH RECURSIVE child_groups AS (
            SELECT id FROM timetrack_groups WHERE id = ?
            UNION ALL
            SELECT g.id FROM timetrack_groups g
            JOIN child_groups cg ON g.parent_id = cg.id
        )
        SELECT id FROM child_groups
    )";
    $params[] = $search_group;
}

$query .= " GROUP BY u.id, u.login, u.first_name, u.last_name, u.role, u.hourly_rate, ug.group_names, u.payer, u.note, u.work_start, u.work_end
,wh.full_time,wh.work_need_days";
echo '<pre>';
//print_r($query);
try {
    $stmt = $pdo->prepare($query);
    //print_r( $params);
    echo '</pre>';
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    // Get total earnings
    $total_earnings = array_sum(array_column($results, 'total_earnings'));
    $total_hours = array_sum(array_column($results, 'total_hours'));

    // Get users for filter
    $stmt = $pdo->query("SELECT id, login FROM timetrack_users");
    $users = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error generating report: " . $e->getMessage();
}


?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Финансовый отчёт</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label for="month" class="form-label">Month</label>
                        <select class="form-select" id="month" name="month">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $i == $month ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="year" class="form-label">Year</label>
                        <select class="form-select" id="year" name="year">
                            <?php for ($i = date('Y') - 1; $i <= date('Y') + 1; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $i == $year ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="group_id" class="form-label">Group</label>
                        <select class="form-select" id="group_id" name="group_id">
                            <option value="">All Groups</option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo $group['id']; ?>" <?php echo $group['id'] == $group_id ? 'selected' : ''; ?>>
                                    <?php echo str_repeat('—', $group['level']) . ' ' . htmlspecialchars($group['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="user_id" class="form-label">User</label>
                        <select class="form-select" id="user_id" name="user_id">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo $user['id'] == $user_id ? 'selected' : ''; ?>>
                                    <?php echo $user['login']  ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Generate Report</button>
                    </div>
                </form>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php else: ?>
                    <div class="container mt-4">
                        <div class="row mb-4">
                            <div class="col">
                                <h2>Отчет за <?php echo date('F', mktime(0, 0, 0, $month, 1)) . ' ' . $year; ?></h2>
                            </div>
                            <div class="col text-end">
                                <button onclick="exportTableToExcel('myTable', 'report')" class="btn btn-success">
                                    <i class="bi bi-file-earmark-excel"></i> Экспорт в Excel
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive" style="overflow-x: auto;">
                            <table id="myTable" class="table table-striped" style="min-width: 1200px;">
                                <thead>
                                    <tr>
                                        <th>Логин</th>                                      
                                        <th>Роль</th>
                                        <th>Группа</th>
                                        <th>Рабочих дней</th>
                                        <th>Отгулов</th>
    
                                        <th>Цель (часов)</th>
                                        <th>Рабочих часов</th>
                                        
                                        <th>Часы выходных</th>
                                        <th>Исключенные часы</th>
                                        <th>Переработка (часы)</th>
                                        <th>Переработка ($)</th>
                                        <th>Ставка</th>
                                        <th>$/Час</th>
                                        
                                        <th>Бонусы</th>
                                        <th>Итого</th>
                                        <?php if ($_SESSION['role'] === 'admin'): ?>
                                            <th>Кто платит</th>
                                            <th>Примечание</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $user): ?>
                                        <?php
                                        /*echo '<pre>';
                                        print_r($user);
                                        echo '</pre>';*/
                                        $total = ($user['hourly_rate_calculated'] * abs($user['total_hours'])) + 
                                        //($user['hourly_rate_calculated']  * $user['overtime_hours']) + 
                                                 $user['total_bonuses'];
                                        
                                        $bonus_details = [];
                                        if ($user['bonus_details']) {
                                            foreach (explode('|', $user['bonus_details']) as $bonus) {
                                                list($type, $amount, $description) = explode(':', $bonus);
                                                $bonus_details[] = [
                                                    'type' => $type,
                                                    'amount' => $amount,
                                                    'description' => $description
                                                ];
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['login']); ?></td>
                                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                                            <td><?php echo htmlspecialchars($user['group_name']); ?></td>
                                            <td><?php echo $user['working_days']; ?></td>
                                            
                                            <td><?php echo $user['days_off']; ?></td>
                                            
                                            <td><?php echo $user['full_work_time']; ?></td>
                                            <td>
                                            <?php
                                            $decimalHours = $user['total_hours'];

                                            $hours = floor($decimalHours); // 3
                                            $minutes = round(($decimalHours - $hours) * 60); // 17
                                            
                                            $time = sprintf('%02d:%02d', $hours, $minutes);
                                            
                                            echo $time; // 03:17
                                             
                                            //echo number_format($user['overtime_hours'], 2); 
                                            ?>
                                            </td>
                                            <td>
                                            <?php
                                            $decimalHours = $user['holiday_hours'];

                                            $hours = floor($decimalHours);
                                            $minutes = round(($decimalHours - $hours) * 60);
                                            
                                            $time = sprintf('%02d:%02d', $hours, $minutes);
                                            
                                            echo $time;
                                            ?>
                                            </td>
                                            <td>
                                            <?php
                                            $decimalHours = $user['total_exclude_hours'];

                                            $hours = floor($decimalHours);
                                            $minutes = round(($decimalHours - $hours) * 60);
                                            
                                            $time = sprintf('%02d:%02d', $hours, $minutes);
                                            
                                            echo $time;
                                            ?>
                                            </td>
                                            <td>
                                            <?php
                                            $decimalHours = $user['overtime_hours'];

                                            $hours = floor($decimalHours); // 3
                                            $minutes = round(($decimalHours - $hours) * 60); // 17
                                            
                                            $time = sprintf('%02d:%02d', $hours, $minutes);
                                            
                                            echo $time; // 03:17
                                             
                                            //echo number_format($user['overtime_hours'], 2); 
                                            ?>
                                            </td>
                                            <td><?php echo number_format($user['hourly_rate_calculated']  * $user['overtime_hours'], 2).' $'; ?></td>
                                            <td><?php echo number_format($user['monthly_rate'], 2).' $'; ?></td>
                                            <td><?php echo number_format($user['hourly_rate_calculated'], 2).' $'; ?></td>
                                            
                                            <td title="<?php 
                                                echo htmlspecialchars(implode("\n", array_map(function($b) {
                                                    return $b['type'] . ': ' . $b['amount'] . 
                                                           ($b['description'] ? ' (' . $b['description'] . ')' : '');
                                                }, $bonus_details)));
                                            ?>">
                                                <?php echo number_format($user['total_bonuses'], 2); ?>
                                            </td>
                                            <td><?php echo number_format($total, 2).' $'; ?></td>
                                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" 
                                                           name="payer[<?php echo $user['id']; ?>]" 
                                                           value="<?php echo htmlspecialchars($user['payer'] ?? ''); ?>"
                                                           onchange="updateUserField(<?php echo $user['id']; ?>, 'payer', this.value)">
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" 
                                                           name="note[<?php echo $user['id']; ?>]" 
                                                           value="<?php echo htmlspecialchars($user['note'] ?? ''); ?>"
                                                           onchange="updateUserField(<?php echo $user['id']; ?>, 'note', this.value)">
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function updateUserField(userId, field, value) {
    fetch('update_user_field.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `user_id=${userId}&field=${field}&value=${encodeURIComponent(value)}`
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert('Ошибка при сохранении данных');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Ошибка при сохранении данных');
    });
}
</script>

<script>
function exportTableToExcel(tableID, filename = '') {
    const dataType = 'application/vnd.ms-excel';
    const table = document.getElementById(tableID);
    const tableHTML = table.outerHTML.replace(/ /g, '%20');
    filename = filename ? filename + '.xls' : 'excel_data.xls';

    const downloadLink = document.createElement("a");
    document.body.appendChild(downloadLink);

    if (navigator.msSaveOrOpenBlob) {
        const blob = new Blob(['\ufeff', tableHTML], { type: dataType });
        navigator.msSaveOrOpenBlob(blob, filename);
    } else {
        downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
        downloadLink.download = filename;
        downloadLink.click();
    }
}
</script>


<?php require_once 'includes/footer.php'; ?> 