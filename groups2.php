<?php
require_once 'config.php';
require_once 'includes/header.php';

// Check if user has permission to manage groups
if (!isAdmin() && !isManager()) {
    redirect('dashboard.php');
}

// Handle group creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    try {
        $stmt = $pdo->prepare("INSERT INTO TimeTrack_groups (name, description, parent_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description, $parent_id]);
        $success = "Group created successfully!";
    } catch(PDOException $e) {
        $error = "Error creating group: " . $e->getMessage();
    }
}

// Handle group update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_group'])) {
    $id = (int)$_POST['group_id'];
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    try {
        $stmt = $pdo->prepare("UPDATE TimeTrack_groups SET name = ?, description = ?, parent_id = ? WHERE id = ?");
        $stmt->execute([$name, $description, $parent_id, $id]);
        $success = "Group updated successfully!";
    } catch(PDOException $e) {
        $error = "Error updating group: " . $e->getMessage();
    }
}

// Handle group deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM TimeTrack_groups WHERE id = ?");
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
        $stmt = $pdo->prepare("DELETE FROM TimeTrack_user_groups WHERE group_id = ?");
        $stmt->execute([$group_id]);

        // Add selected members
        if (!empty($user_ids)) {
            $values = array_fill(0, count($user_ids), "(?, ?)");
            $params = [];
            foreach ($user_ids as $user_id) {
                $params[] = $group_id;
                $params[] = $user_id;
            }
            $stmt = $pdo->prepare("INSERT INTO TimeTrack_user_groups (group_id, user_id) VALUES " . implode(',', $values));
            $stmt->execute($params);
        }
        $success = "Group members updated successfully!";
    } catch(PDOException $e) {
        $error = "Error updating group members: " . $e->getMessage();
    }
}

// Get all groups with their hierarchy
function getGroupsHierarchy($pdo, $parent_id = null, $level = 0) {
    $stmt = $pdo->prepare("SELECT * FROM TimeTrack_groups WHERE parent_id " . ($parent_id === null ? "IS NULL" : "= ?") . " ORDER BY name");
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
$stmt = $pdo->query("SELECT id, first_name, last_name FROM TimeTrack_users");
$users = $stmt->fetchAll();

// Prepare data for the graph
$graphData = [
    'nodes' => [],
    'edges' => []
];

foreach ($groups as $group) {
    // Get member count for the group
    $stmt = $pdo->prepare("SELECT COUNT(*) as member_count FROM TimeTrack_user_groups WHERE group_id = ?");
    $stmt->execute([$group['id']]);
    $memberCount = $stmt->fetch()['member_count'];
    
    $graphData['nodes'][] = [
        'id' => $group['id'],
        'label' => $group['name'] . "\n(" . $memberCount . " members)",
        'level' => $group['level'],
        'title' => $group['description'],
        'value' => $memberCount + 1,
        'color' => $memberCount > 0 ? '#97C2FC' : '#FFB6C1'
    ];
    
    if ($group['parent_id']) {
        $graphData['edges'][] = [
            'from' => $group['parent_id'],
            'to' => $group['id'],
            'arrows' => 'to'
        ];
    }
}
?>

<style>
#groupGraph {
    height: 600px;
    width: 100%;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #fff;
}

.vis-network {
    outline: none;
}

.vis-tooltip {
    max-width: 300px;
    word-wrap: break-word;
}
</style>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Create New Group</h5>
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
                        <label for="name" class="form-label">Group Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="parent_id" class="form-label">Parent Group</label>
                        <select class="form-select" id="parent_id" name="parent_id">
                            <option value="">None (Top Level)</option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo $group['id']; ?>">
                                    <?php echo str_repeat('—', $group['level']) . ' ' . $group['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="create_group" class="btn btn-primary">Create Group</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Group Members</h5>
            </div>
            <div class="card-body">
                <div id="groupMembers" class="table-responsive">
                    <p class="text-muted">Select a group in the graph to view its members</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Group Hierarchy</h5>
            </div>
            <div class="card-body">
                <div id="groupGraph"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for managing group members -->
<div class="modal fade" id="manageMembersModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Manage Group Members</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="group_id" id="modalGroupId">
                    <div class="mb-3">
                        <label class="form-label">Select Members</label>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($users as $user): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="user_ids[]" 
                                           value="<?php echo $user['id']; ?>" id="user<?php echo $user['id']; ?>">
                                    <label class="form-check-label" for="user<?php echo $user['id']; ?>">
                                        <?php echo $user['first_name'] . ' ' . $user['last_name']; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="manage_members" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include vis.js -->
<script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Create the graph
    const container = document.getElementById('groupGraph');
    const data = {
        nodes: new vis.DataSet(<?php echo json_encode($graphData['nodes']); ?>),
        edges: new vis.DataSet(<?php echo json_encode($graphData['edges']); ?>)
    };
    
    const options = {
        layout: {
            hierarchical: {
                direction: 'UD',
                sortMethod: 'directed',
                levelSeparation: 150,
                nodeSpacing: 100
            }
        },
        physics: {
            hierarchicalRepulsion: {
                nodeDistance: 200
            }
        },
        nodes: {
            shape: 'box',
            margin: 10,
            font: {
                size: 14,
                multi: true
            }
        },
        edges: {
            smooth: {
                type: 'cubicBezier'
            }
        },
        interaction: {
            hover: true
        }
    };
    
    const network = new vis.Network(container, data, options);
    
    // Handle node click to show members
    network.on('click', function(params) {
        if (params.nodes.length > 0) {
            const groupId = params.nodes[0];
            const groupName = data.nodes.get(groupId).label.split('\n')[0];
            
            // Fetch group members
            fetch(`get_group_members.php?group_id=${groupId}`)
                .then(response => response.json())
                .then(members => {
                    let html = `
                        <h6>${groupName}</h6>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span>${members.length} members</span>
                            <button type="button" class="btn btn-sm btn-primary" 
                                    onclick="showManageMembersModal(${groupId})">
                                Manage Members
                            </button>
                        </div>
                    `;
                    
                    if (members.length > 0) {
                        html += '<table class="table table-sm">';
                        html += '<thead><tr><th>Name</th><th>Role</th></tr></thead>';
                        html += '<tbody>';
                        members.forEach(member => {
                            html += `
                                <tr>
                                    <td>${member.first_name} ${member.last_name}</td>
                                    <td>${member.role}</td>
                                </tr>
                            `;
                        });
                        html += '</tbody></table>';
                    } else {
                        html += '<p class="text-muted">No members in this group</p>';
                    }
                    
                    document.getElementById('groupMembers').innerHTML = html;
                });
        }
    });
});

function showManageMembersModal(groupId) {
    // Fetch current members
    fetch(`get_group_members.php?group_id=${groupId}&current_members=1`)
        .then(response => response.json())
        .then(data => {
            // Set group ID in the form
            document.getElementById('modalGroupId').value = groupId;
            
            // Check checkboxes for current members
            const checkboxes = document.querySelectorAll('input[name="user_ids[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = data.current_members.includes(parseInt(checkbox.value));
            });
            
            // Show modal
            new bootstrap.Modal(document.getElementById('manageMembersModal')).show();
        });
}
</script>

<?php require_once 'includes/footer.php'; ?> 