<?php
require_once 'config.php';

// Check if user is admin
if (!isAdmin()) {
    redirect('dashboard.php');
}

require_once 'includes/header.php';


// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $login = sanitize($_POST['login']);
    $password = $_POST['password'];
    //$first_name = sanitize($_POST['first_name']);
    //$last_name = sanitize($_POST['last_name']);
    $role = sanitize($_POST['role']);
    //$stage = sanitize($_POST['stage']);
    $description = sanitize($_POST['description']);
    $hourly_rate = (float)$_POST['hourly_rate'];

    $work_start = $_POST['work_start']; // формат HH:MM:SS
    $work_end   = $_POST['work_end'];
    
    try {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO timetrack_users (login, password,  role,  description, hourly_rate,work_start, work_end)
            VALUES (?, ?, ?, ?, ?,?,?)
        ");
        $stmt->execute([$login, $hashed_password,  $role,  $description, $hourly_rate,$work_start,$work_end]);
         // 2) Получаем ID только что созданного пользователя
         $user_id = $pdo->lastInsertId();

         // 3) Обрабатываем группы, если что-то выбрано
         if (!empty($_POST['groups']) && is_array($_POST['groups'])) {
             $assignStmt = $pdo->prepare("
                 INSERT INTO timetrack_user_groups (user_id, group_id)
                 VALUES (?, ?)
             ");
             foreach ($_POST['groups'] as $group_id) {
                 // Приводим к целому — на всякий случай
                 $assignStmt->execute([
                     $user_id,
                     (int)$group_id
                 ]);
             }
         }
 
        
        $success = "User created successfully!";
    } catch(PDOException $e) {
        $error = "Error creating user: " . $e->getMessage();
    }
}

// Handle user update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $id = (int)$_POST['user_id'];
    $first_name = '';//sanitize($_POST['first_name']);
    $last_name = '';//sanitize($_POST['last_name']);
    $role = sanitize($_POST['role']);
    $stage = '';//sanitize($_POST['stage']);
    $description = sanitize($_POST['description']);
    $hourly_rate = (float)$_POST['hourly_rate'];
    $password = $_POST['password'];
    $work_start = $_POST['work_start'];
    $work_end   = $_POST['work_end'];
    try {
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE timetrack_users 
                SET first_name = ?, last_name = ?, role = ?, stage = ?, description = ?, hourly_rate = ?, password = ?,work_start=?,work_end=?
                WHERE id = ?
            ");
            $stmt->execute([$first_name, $last_name, $role, $stage, $description, $hourly_rate, $hashed_password,$work_start,$work_end, $id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE timetrack_users 
                SET first_name = ?, last_name = ?, role = ?, stage = ?, description = ?, hourly_rate = ?, work_start=?, work_end=?
                WHERE id = ?
            ");
            $stmt->execute([$first_name, $last_name, $role, $stage, $description, $hourly_rate,$work_start,$work_end, $id]);
        }
        
       // 2) Удаляем старые связи "пользователь–группа"
       $delStmt = $pdo->prepare("DELETE FROM timetrack_user_groups WHERE user_id = ?");
       $delStmt->execute([$id]);

       // 3) Вставляем новые, если есть
       if (!empty($_POST['groups']) && is_array($_POST['groups'])) {
           $insStmt = $pdo->prepare("
               INSERT INTO timetrack_user_groups (user_id, group_id)
               VALUES (?, ?)
           ");
           foreach ($_POST['groups'] as $group_id) {
               $insStmt->execute([
                   $id,
                   (int)$group_id
               ]);
           }
       }
            
      

        $success = "User updated successfully!";
    } catch(PDOException $e) {
        $error = "Error updating user: " . $e->getMessage();
    }
}

// Handle user deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM timetrack_users WHERE id = ?");
        $stmt->execute([$id]);
        $success = "User deleted successfully!";
    } catch(PDOException $e) {
        $error = "Error deleting user: " . $e->getMessage();
    }
}

// Удаление бонуса
if (isset($_GET['delete_bonus'], $_GET['user_id'])) {
    $del = (int)$_GET['delete_bonus'];
    $uid = (int)$_GET['user_id'];
    $stmt = $pdo->prepare("DELETE FROM timetrack_user_bonuses WHERE id = ?");
    $stmt->execute([$del]);
    //header("Location: users.php"); // перезагрузка, чтобы обновить список
    //exit;
}

// Добавление бонуса
/*if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bonus'])) {
    $uid         = (int)$_POST['bonus_user_id'];
    $type        = $_POST['bonus_type'];
    $amount      = (float)$_POST['bonus_amount'];
    $desc        = sanitize($_POST['bonus_description']);
    $start_date  = $_POST['bonus_start'] ?: null;
    $end_date    = $_POST['bonus_end']   ?: null;

    $stmt = $pdo->prepare("
      INSERT INTO timetrack_user_bonuses
        (user_id, type, amount, description, start_date, end_date)
      VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
      $uid, $type, $amount, $desc, $start_date, $end_date
    ]);

    // После добавления перезагружаем, чтобы увидеть новый бонус
    //header("Location: users.php");
    //exit;
}
*/
if (isset($_POST['add_bonus'])) {
    $id = $_POST['update_bonus_id'] ?? null;
  
    if ($id) {
      // Обновление
      $stmt = $pdo->prepare("UPDATE timetrack_user_bonuses SET type = ?, amount = ?, description = ?, start_date = ?, end_date = ? WHERE id = ?");
      $stmt->execute([
        $_POST['bonus_type'],
        $_POST['bonus_amount'],
        $_POST['bonus_description'],
        $_POST['bonus_start'] ?: null,
        $_POST['bonus_end'] ?: null,
        $id
      ]);
    } else {
      // Добавление
      $stmt = $pdo->prepare("INSERT INTO timetrack_user_bonuses (user_id, type, amount, description, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)");
      $stmt->execute([
        $_POST['bonus_user_id'],
        $_POST['bonus_type'],
        $_POST['bonus_amount'],
        $_POST['bonus_description'],
        $_POST['bonus_start'] ?: null,
        $_POST['bonus_end'] ?: null
      ]);
    }
  }
  


// Get all users with their groups
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$search_role = isset($_GET['role']) ? sanitize($_GET['role']) : '';
$search_group = isset($_GET['group']) ? (int)$_GET['group'] : 0;

$query = "SELECT DISTINCT u.* FROM timetrack_users u";
$params = [];

if ($search || $search_role || $search_group) {
    $conditions = [];
    
    if ($search) {
        $conditions[] = "(u.login LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    if ($search_role) {
        $conditions[] = "u.role = ?";
        $params[] = $search_role;
    }
    
    if ($search_group) {
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
        $stmt->execute([$search_group]);
        $childGroups = array_column($stmt->fetchAll(), 'id');
        
        $query .= " JOIN timetrack_user_groups ug ON u.id = ug.user_id";
        $query .= " WHERE ug.group_id IN (" . implode(',', $childGroups) . ")";
    } else if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get all roles for the filter
$roles = ['admin', 'manager', 'employee'];

// Get all groups with hierarchy for the filter
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

$groups = getGroupsHierarchy($pdo);

// Get user groups for display
$userGroups = [];
$stmt = $pdo->query("
    SELECT ug.user_id, g.id, g.name, g.parent_id 
    FROM timetrack_user_groups ug 
    JOIN timetrack_groups g ON ug.group_id = g.id
");
while ($row = $stmt->fetch()) {
    if (!isset($userGroups[$row['user_id']])) {
        $userGroups[$row['user_id']] = [];
    }
    $userGroups[$row['user_id']][] = $row;
}
?>
<?php
// 1. Получаем все группы из базы
$query = $pdo->query("SELECT id, name, parent_id FROM timetrack_groups ORDER BY parent_id, name");
$allGroups = $query->fetchAll(PDO::FETCH_ASSOC);

// 2. Строим дерево
function buildGroupTree($elements, $parentId = 0, $level = 0) {
    $branch = [];

    foreach ($elements as $element) {
        if ($element['parent_id'] == $parentId) {
            $element['level'] = $level;
            $children = buildGroupTree($elements, $element['id'], $level + 1);
            if ($children) {
                $element['children'] = $children;
            }
            $branch[] = $element;
        }
    }
    return $branch;
}

// 3. Плоский список для select
function flattenGroupTree($tree) {
    $flat = [];
    foreach ($tree as $node) {
        $flat[] = $node;
        if (!empty($node['children'])) {
            $flat = array_merge($flat, flattenGroupTree($node['children']));
        }
    }
    return $flat;
}

// 4. Построим дерево и превратим в список
$groupTree = buildGroupTree($allGroups);
$groups = flattenGroupTree($groupTree);
?>

<div class="row">
    <div class="col-md-3">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Создание пользователя</h5>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="login" class="form-label">Логин</label>
                            <input type="text" class="form-control" id="login" name="login" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Пароль</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    <div class="row">
                        
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Роль</label>
                        <select class="form-select" id="roleadd" name="role" required>
                            <option value="admin">Админ</option>
                            <option value="manager">Менеджер</option>
                            <option value="employee">Сотрудник</option>
                        </select>
                    </div>
                    
                    
                                                    <?php 
                                                                //print_r($groups);
                                                                ?>
                                                 <div class="mb-3">
                                                    <label for="groups" class="form-label">Группы</label>
                                                    <select class="form-select" id="groups" name="groups[]" multiple>
                                                        <?php foreach ($groups as $group): ?>
                                                            <option value="<?= $group['id']; ?>" <?= (isset($selected_groups) && in_array($group['id'], $selected_groups)) ? 'selected' : ''; ?>>
                                                                <?= str_repeat('— ', $group['level']) . htmlspecialchars($group['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>



                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Описание</label>
                        <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="hourly_rate" class="form-label">Ставка</label>
                        <input type="number" step="0.01" class="form-control" id="hourly_rate" name="hourly_rate" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="work_start" class="form-label">Начало работы (по умолчанию)</label>
                            <input
                            type="time"
                            class="form-control"
                            id="work_start"
                            name="work_start"
                            value="10:00:00"
                            required
                            >
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="work_end" class="form-label">Конец работы (по умолчанию)</label>
                            <input
                            type="time"
                            class="form-control"
                            id="work_end"
                            name="work_end"
                            value="19:00:00"
                            required
                            >
                        </div>
                    </div>

                    <button type="submit" name="create_user" class="btn btn-primary">Создать пользователя</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-9">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Существующие пользователи</h5>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="GET" action="" class="row g-3">
                                    <div class="col-md-4">
                                        <label for="search" class="form-label">Поиск</label>
                                        <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Поиск по логину, имени или фамилии">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="role" class="form-label">Роль</label>
                                        <select class="form-select" id="role" name="role">
                                            <option value="">Все роли</option>
                                            <?php foreach ($roles as $role): ?>
                                                <option value="<?php echo $role; ?>" <?php echo $search_role == $role ? 'selected' : ''; ?>>
                                                    <?php echo ucfirst($role); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="group" class="form-label">Группа</label>
                                        <select class="form-select" id="group" name="group">
                                            <option value="0">Все группы</option>
                                            <?php foreach ($groups as $group): ?>
                                                <option value="<?php echo $group['id']; ?>" <?php echo $search_group == $group['id'] ? 'selected' : ''; ?>>
                                                    <?php echo str_repeat('—', $group['level']) . ' ' . htmlspecialchars($group['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-primary w-100">Поиск</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Логин</th>
                                <th>Роль</th>
                                <th>Группы</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['login']; ?></td>
                                    <td><?php echo ucfirst($user['role']); ?></td>
                                    <td>
                                        <?php if (isset($userGroups[$user['id']])): ?>
                                            <?php 
                                            $groupNames = [];
                                            foreach ($userGroups[$user['id']] as $group) {
                                                $groupNames[] = htmlspecialchars($group['name']);
                                            }
                                            echo implode(', ', $groupNames);
                                            ?>
                                        <?php else: ?>
                                            <span class="text-muted">Нет групп</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $user['id']; ?>">
                                            Редактировать
                                        </button>
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-success" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#bonusModal<?= $user['id']; ?>"
                                        >
                                            Бонусы
                                        </button>
                                        <a href="?delete=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?')">
                                            Х
                                        </a>
                                    </td>
                                </tr>

                                <?php
                                $userGroupsStmt = $pdo->prepare("
                                    SELECT group_id 
                                    FROM timetrack_user_groups 
                                    WHERE user_id = ?
                                ");
                                $userGroupsStmt->execute([$user['id']]);
                                $user_group_ids = array_column(
                                    $userGroupsStmt->fetchAll(PDO::FETCH_ASSOC),
                                    'group_id'
                                );
                                ?>
                                <?php
                                      // Получаем существующие бонусы пользователя
                                      $bStmt = $pdo->prepare("
                                        SELECT * FROM timetrack_user_bonuses 
                                        WHERE user_id = ? 
                                        ORDER BY start_date DESC, id DESC
                                      ");
                                      $bStmt->execute([$user['id']]);
                                      $bonuses = $bStmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    
                                    ?>
                            <!-- Bonuses Modal -->
                            <div class="modal fade" id="bonusModal<?= $user['id']; ?>" tabindex="-1" aria-hidden="true">
                              <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                  <div class="modal-header">
                                    <h5 class="modal-title">Bonuses for <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                  </div>
                                  <div class="modal-body">
                                    <!-- 1. Список существующих бонусов -->
                                    <div class="mb-4">
                                      <?php if (empty($bonuses)): ?>
                                        <p class="text-muted">Пока пусто...</p>
                                      <?php else: ?>
                                        <?php foreach ($bonuses as $b): ?>
                                          <div class="d-flex justify-content-between align-items-center p-2 mb-2 border rounded">
                                            <div>
                                              <strong><?= ucfirst(str_replace('-', ' ', $b['type'])); ?></strong>
                                              — <?= number_format($b['amount'], 2); ?>
                                              <?php if ($b['description']): ?>
                                                <br><small class="text-secondary"><?= htmlspecialchars($b['description']); ?></small>
                                              <?php endif; ?>
                                              <a href="#" 
                                                class="edit-bonus-btn" 
                                                data-bonus-id="<?= $b['id'] ?>"
                                                data-bonus-type="<?= $b['type'] ?>"
                                                data-bonus-amount="<?= $b['amount'] ?>"
                                                data-bonus-description="<?= htmlspecialchars($b['description'], ENT_QUOTES) ?>"
                                                data-bonus-start="<?= $b['start_date'] ?>"
                                                data-bonus-end="<?= $b['end_date'] ?>"
                                              >✏️</a>
                                              <br>
                                              <small class="text-secondary">
                                                <?= $b['start_date'] ?: '–'; ?>  
                                                <?php if ($b['end_date']): ?>
                                                  &nbsp;—&nbsp;<?= $b['end_date']; ?>
                                                <?php endif; ?>
                                              </small>
                                            </div>
                                            <div>
                                              <a 
                                                href="?delete_bonus=<?= $b['id']; ?>&user_id=<?= $user['id']; ?>" 
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Delete this bonus?')"
                                              >
                                                Х
                                              </a>
                                            </div>
                                          </div>
                                        <?php endforeach; ?>
                                      <?php endif; ?>
                                    </div>

                                    <!-- 2. Форма добавления/редактирования бонуса -->
                                    <form method="POST" id="bonusForm<?= $user['id']; ?>">
                                      <input type="hidden" name="bonus_user_id" value="<?= $user['id']; ?>">
                                      <input type="hidden" name="update_bonus_id" id="updateBonusId<?= $user['id']; ?>">
                                      <div class="row gy-3">
                                        <div class="col-md-6">
                                          <label class="form-label">Тип</label>
                                          <select name="bonus_type" class="form-select" required>
                                            <option value="one-time">Одноразовый бонус</option>
                                            <option value="recurring">Повторяющийся бонус</option>
                                          </select>
                                        </div>
                                        <div class="col-md-6">
                                          <label class="form-label">Сумма</label>
                                          <input type="number" step="0.01" name="bonus_amount" class="form-control" required>
                                        </div>
                                        <div class="col-md-12">
                                          <label class="form-label">Описание</label>

                                        <textarea id="message" class="form-control" name="bonus_description" rows="5" cols="40" placeholder="Введите текст..."></textarea>

                                        </div>
                                        <div class="col-md-6">
                                          <label class="form-label">Дата начала</label>
                                          <input type="date" name="bonus_start" class="form-control">
                                        </div>
                                        <div class="col-md-6">
                                          <label class="form-label">Дата окончания</label>
                                          <input type="date" name="bonus_end" class="form-control">
                                        </div>
                                      </div>
                                      <div class="mt-4 text-end">
                                        <button type="button" class="btn btn-secondary me-2 d-none" id="cancelEdit<?= $user['id']; ?>">Отмена</button>
                                        <button type="submit" name="add_bonus" class="btn btn-primary" id="submitBonus<?= $user['id']; ?>">Добавить</button>
                                      </div>
                                    </form>
                                  </div>
                                </div>
                              </div>
                            </div>



                                <!-- Edit User Modal -->
                                <div class="modal fade" id="editUserModal<?php echo $user['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Редактирование</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="">
                                                <div class="modal-body">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <div class="row">
                                                       
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="role<?php echo $user['id']; ?>" class="form-label">Роль</label>
                                                        <select class="form-select" id="role<?php echo $user['id']; ?>" name="role" required>
                                                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                            <option value="manager" <?php echo $user['role'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                                            <option value="employee" <?php echo $user['role'] === 'employee' ? 'selected' : ''; ?>>Employee</option>
                                                        </select>
                                                    </div>
                                                    

                                                    <div class="mb-3">
                                                        <label for="groups<?= $user['id']; ?>" class="form-label">Группы</label>
                                                        <select 
                                                            class="form-select" 
                                                            id="groups<?= $user['id']; ?>" 
                                                            name="groups[]" 
                                                            multiple
                                                        >
                                                            <?php foreach ($groups as $group): ?>
                                                                <option 
                                                                    value="<?= $group['id']; ?>" 
                                                                    <?= in_array($group['id'], $user_group_ids) ? 'selected' : ''; ?>
                                                                >
                                                                    <?= str_repeat('— ', $group['level']) . htmlspecialchars($group['name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>



                                                   
                                                    <div class="mb-3">
                                                        <label for="description<?php echo $user['id']; ?>" class="form-label">Описание</label>
                                                        <textarea class="form-control" id="description<?php echo $user['id']; ?>" name="description" rows="4"><?php echo htmlspecialchars($user['description']); ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="hourly_rate<?php echo $user['id']; ?>" class="form-label">Ставка</label>
                                                        <input type="number" step="0.01" class="form-control" id="hourly_rate<?php echo $user['id']; ?>" name="hourly_rate" value="<?php echo $user['hourly_rate']; ?>" required>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label for="work_start<?= $user['id']; ?>" class="form-label">Начало работы</label>
                                                            <input
                                                            type="time"
                                                            class="form-control"
                                                            id="work_start<?= $user['id']; ?>"
                                                            name="work_start"
                                                            value="<?= htmlspecialchars($user['work_start']); ?>"
                                                            required
                                                            >
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label for="work_end<?= $user['id']; ?>" class="form-label">Окончание работы</label>
                                                            <input
                                                            type="time"
                                                            class="form-control"
                                                            id="work_end<?= $user['id']; ?>"
                                                            name="work_end"
                                                            value="<?= htmlspecialchars($user['work_end']); ?>"
                                                            required
                                                            >
                                                        </div>
                                                    </div>


                                                    <div class="mb-3">
                                                        <label for="password<?php echo $user['id']; ?>" class="form-label">Новый пароль (Оставить пустым, если менять не нужно)</label>
                                                        <input type="password" class="form-control" id="password<?php echo $user['id']; ?>" name="password">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                                                    <button type="submit" name="update_user" class="btn btn-primary">Сохранить</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                
                                    </div>
                            <?php endforeach; ?>
                            
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div><script>
// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    // Обработчик для всех кнопок редактирования
    document.querySelectorAll('.edit-bonus-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Находим модальное окно
            const modal = this.closest('.modal');
            if (!modal) {
                console.error('Modal not found');
                return;
            }
            
            // Находим форму и элементы управления
            const form = modal.querySelector('form');
            if (!form) {
                console.error('Form not found in modal');
                return;
            }

            const cancelBtn = modal.querySelector('.btn-secondary');
            const submitBtn = modal.querySelector('.btn-primary');
            const updateIdInput = modal.querySelector('[name="update_bonus_id"]');
            
            if (!cancelBtn || !submitBtn || !updateIdInput) {
                console.error('Required elements not found:', {
                    cancelBtn: !cancelBtn,
                    submitBtn: !submitBtn,
                    updateIdInput: !updateIdInput
                });
                return;
            }

            // Находим все поля формы по их реальным именам
            const typeSelect = form.elements['bonus_type'];
            const amountInput = form.elements['bonus_amount'];
            const descriptionInput = form.elements['bonus_description'];
            const startInput = form.elements['bonus_start'];
            const endInput = form.elements['bonus_end'];

            if (!typeSelect || !amountInput || !descriptionInput || !startInput || !endInput) {
                console.error('Form fields not found:', {
                    typeSelect: !typeSelect,
                    amountInput: !amountInput,
                    descriptionInput: !descriptionInput,
                    startInput: !startInput,
                    endInput: !endInput,
                    formElements: Array.from(form.elements).map(el => ({
                        name: el.name,
                        type: el.type,
                        id: el.id
                    }))
                });
                return;
            }

            // Заполняем форму данными
            try {
                typeSelect.value = this.dataset.bonusType || '';
                amountInput.value = this.dataset.bonusAmount || '';
                descriptionInput.value = this.dataset.bonusDescription || '';
                startInput.value = this.dataset.bonusStart || '';
                endInput.value = this.dataset.bonusEnd || '';
                updateIdInput.value = this.dataset.bonusId || '';
                
                // Показываем кнопку отмены и меняем текст кнопки отправки
                cancelBtn.classList.remove('d-none');
                submitBtn.textContent = 'Сохранить';
            } catch (error) {
                console.error('Error setting form values:', error);
            }
        });
    });
    
    // Обработчик для всех кнопок отмены
    document.querySelectorAll('.btn-secondary').forEach(btn => {
        btn.addEventListener('click', function() {
            // Находим модальное окно
            const modal = this.closest('.modal');
            if (!modal) {
                console.error('Modal not found');
                return;
            }
            
            // Находим форму и элементы управления
            const form = modal.querySelector('form');
            const submitBtn = modal.querySelector('.btn-primary');
            const updateIdInput = modal.querySelector('[name="update_bonus_id"]');
            
            if (!form || !submitBtn || !updateIdInput) {
                console.error('Required elements not found:', {
                    form: !form,
                    submitBtn: !submitBtn,
                    updateIdInput: !updateIdInput
                });
                return;
            }
            
            // Очищаем форму
            form.reset();
            updateIdInput.value = '';
            
            // Скрываем кнопку отмены и меняем текст кнопки отправки
            this.classList.add('d-none');
            submitBtn.textContent = 'Добавить';
        });
    });
});
</script>


<?php require_once 'includes/footer.php'; ?> 