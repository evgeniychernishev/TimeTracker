<?php
require_once 'config.php';
// Check if user has permission to manage groups
if (!isAdmin() && !isManager()) {
    redirect('dashboard.php');
}

require_once 'includes/header.php';

// Handle group creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    $name = sanitize($_POST['name']);
    //$description = sanitize($_POST['description']);
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    try {
        $stmt = $pdo->prepare("INSERT INTO timetrack_groups (name, parent_id) VALUES (?,  ?)");
        $stmt->execute([$name,  $parent_id]);
        $success = "Group created successfully!";
    } catch(PDOException $e) {
        $error = "Error creating group: " . $e->getMessage();
    }
}

// Handle group update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_group'])) {
    $id = (int)$_POST['group_id'];
    $name = sanitize($_POST['name']);
    //$description = sanitize($_POST['description']);
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    try {
        $stmt = $pdo->prepare("UPDATE timetrack_groups SET name = ?, parent_id = ? WHERE id = ?");
        $stmt->execute([$name, $parent_id, $id]);
        $success = "Group updated successfully!";
    } catch(PDOException $e) {
        $error = "Error updating group: " . $e->getMessage();
    }
}

// Handle group deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM timetrack_groups WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Group deleted successfully!";
    } catch(PDOException $e) {
        $error = "Error deleting group: " . $e->getMessage();
    }
}

// Handle member management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manage_members'])) {
    $group_id = (int)$_POST['group_id'];
    $user_ids = isset($_POST['user_ids']) ? $_POST['user_ids'] : [];

    try {
        // Remove all current members
        $stmt = $pdo->prepare("DELETE FROM timetrack_user_groups WHERE group_id = ?");
        $stmt->execute([$group_id]);

        // Add selected members
        if (!empty($user_ids)) {
            $values = array_fill(0, count($user_ids), "(?, ?)");
            $params = [];
            foreach ($user_ids as $user_id) {
                $params[] = $group_id;
                $params[] = $user_id;
            }
            $stmt = $pdo->prepare("INSERT INTO timetrack_user_groups (group_id, user_id) VALUES " . implode(',', $values));
            $stmt->execute($params);
        }
        $success = "Group members updated successfully!";
    } catch(PDOException $e) {
        $error = "Error updating group members: " . $e->getMessage();
    }
}

// Get all groups with their hierarchy
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

// Get all users
$stmt = $pdo->query("SELECT id, login FROM timetrack_users");
$users = $stmt->fetchAll();
?>

<div class="row">
<?php if (isAdmin()): ?>
<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Создание новой группы</h5>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="name" class="form-label">Название</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="parent_id" class="form-label">Родительская группа</label>
                        <select class="form-select" id="parent_id" name="parent_id">
                            <option value="">None (Top Level)</option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo $group['id']; ?>">
                                    <?php echo str_repeat('—', $group['level']) . ' ' . $group['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="create_group" class="btn btn-primary">Создать</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Группы</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Название</th>
                               
                                <th>Родительская группа</th>
                                <?php if (isAdmin()): ?>
                                <th>Действия</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groups as $group): ?>
                                <tr>
                                    <td><?php echo str_repeat('—', $group['level']) . ' ' . $group['name']; ?></td>
                                    
                                    <td>
                                        <?php
                                        if ($group['parent_id']) {
                                            $stmt = $pdo->prepare("SELECT name FROM timetrack_groups WHERE id = ?");
                                            $stmt->execute([$group['parent_id']]);
                                            $parent = $stmt->fetch();
                                            echo $parent['name'];
                                        } else {
                                            echo 'None';
                                        }
                                        ?>
                                    </td>
                                    <?php if (isAdmin()): ?>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editGroupModal<?php echo $group['id']; ?>">
                                                Редактировать
                                            </button>
                                            <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#manageMembersModal<?php echo $group['id']; ?>">
                                                Мемберы
                                            </button>
                                            <a href="?delete=<?php echo $group['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this group?')">
                                                Удалить
                                            </a>
                                        </td>
                                    <?php endif; ?>
                                </tr>

                                <!-- Edit Group Modal -->
                                <div class="modal fade" id="editGroupModal<?php echo $group['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Редактирование группы</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="">
                                                <div class="modal-body">
                                                    <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                                    <div class="mb-3">
                                                        <label for="name<?php echo $group['id']; ?>" class="form-label">Группа</label>
                                                        <input type="text" class="form-control" id="name<?php echo $group['id']; ?>" name="name" value="<?php echo $group['name']; ?>" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="parent_id<?php echo $group['id']; ?>" class="form-label">Родительская группа</label>
                                                        <select class="form-select" id="parent_id<?php echo $group['id']; ?>" name="parent_id">
                                                            <option value="">None (Top Level)</option>
                                                            <?php foreach ($groups as $g): ?>
                                                                <?php if ($g['id'] != $group['id']): ?>
                                                                    <option value="<?php echo $g['id']; ?>" <?php echo $g['id'] == $group['parent_id'] ? 'selected' : ''; ?>>
                                                                        <?php echo str_repeat('—', $g['level']) . ' ' . $g['name']; ?>
                                                                    </option>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                                                    <button type="submit" name="update_group" class="btn btn-primary">Сохранить</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Manage Members Modal -->
                                <div class="modal fade" id="manageMembersModal<?php echo $group['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Мемберы группы</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="">
                                                <div class="modal-body">
                                                    <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                                    <?php
                                                    // Get current group members
                                                    $stmt = $pdo->prepare("SELECT user_id FROM timetrack_user_groups WHERE group_id = ?");
                                                    $stmt->execute([$group['id']]);
                                                    $current_members = array_column($stmt->fetchAll(), 'user_id');
                                                    ?>
                                                    <div class="mb-3">
                                                        <label class="form-label">Выбрать мемберов</label>
                                                        <?php foreach ($users as $user): ?>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" 
                                                                    <?php echo in_array($user['id'], $current_members) ? 'checked' : ''; ?>>
                                                                <label class="form-check-label">
                                                                    <?php echo $user['login'] ; ?>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                                                    <button type="submit" name="manage_members" class="btn btn-primary">Сохранить</button>
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
</div>

<?php require_once 'includes/footer.php'; ?> 