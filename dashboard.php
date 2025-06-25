<?php
require_once 'config.php';
require_once 'includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = sanitize($_POST['date']);
    
    if (isset($_POST['delete'])) {
        // Handle deletion
        try {
            $stmt = $pdo->prepare("DELETE FROM timetrack_time_entries WHERE user_id = ? AND date = ?");
            $stmt->execute([$_SESSION['user_id'], $date]);
            $success = "Time entry deleted successfully!";
        } catch(PDOException $e) {
            $error = "Error deleting time entry: " . $e->getMessage();
        }
    } else {
        // Handle creation/update
        $start_time = sanitize($_POST['start_time']);
        $end_time = sanitize($_POST['end_time']);
        $is_holiday = isset($_POST['is_holiday']) ? 1 : 0;
        $description=sanitize($_POST['description']);
        if($is_holiday)
        {
            $start_time =0;
            $end_time=0;   
        }
        try {
            // Check if entry already exists for this date
            $stmt = $pdo->prepare("SELECT id FROM timetrack_time_entries WHERE user_id = ? AND date = ?");
            $stmt->execute([$_SESSION['user_id'], $date]);
            $existing_entry = $stmt->fetch();

            if ($existing_entry) {
                // Update existing entry
                $stmt = $pdo->prepare("
                    UPDATE timetrack_time_entries 
                    SET start_time = ?, end_time = ?, is_holiday = ?,description=?
                    WHERE id = ?
                ");
                $stmt->execute([$start_time, $end_time, $is_holiday,$description, $existing_entry['id']]);
            } else {
                // Insert new entry
                $stmt = $pdo->prepare("
                    INSERT INTO timetrack_time_entries (user_id, date, start_time, end_time, is_holiday,description)
                    VALUES (?, ?, ?, ?, ?,?)
                ");
                $stmt->execute([$_SESSION['user_id'], $date, $start_time, $end_time, $is_holiday,$description]);
            }
            $success = "Time entry saved successfully!";
        } catch(PDOException $e) {
            $error = "Error saving time entry: " . $e->getMessage();
        }
    }
}

// Get current month and year
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Get first day of the month
$first_day = mktime(0, 0, 0, $month, 1, $year);
$number_days = date('t', $first_day);
$first_day_of_week = date('N', $first_day);

// Get time entries for the month
$stmt = $pdo->prepare("
    SELECT date, start_time, end_time, is_holiday ,description
    FROM timetrack_time_entries 
    WHERE user_id = ? 
    AND MONTH(date) = ? 
    AND YEAR(date) = ?
");
$stmt->execute([$_SESSION['user_id'], $month, $year]);
$time_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create entries array for easy lookup
$entries = [];
foreach ($time_entries as $entry) {
    $entries[$entry['date']] = $entry;
}

$id=$_SESSION['user_id'];
$stmt = $pdo->query("SELECT t.* FROM timetrack_users t
where t.id=$id
");
$users = $stmt->fetchAll();

?>

<style>
.calendar-day {
    position: relative;
    transition: all 0.2s ease;
    min-height: 10px;
    height: 10px;
    padding: 5px;
}

.calendar-day:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.calendar-day.selected {
    box-shadow: 0 0 0 2px #0d6efd;
}

.calendar-day.selected::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 0;
    height: 0;
    border-style: solid;
    border-width: 0 20px 20px 0;
    border-color: transparent #0d6efd transparent transparent;
}

.calendar-day.selected::before {
    content: '✓';
    position: absolute;
    top: 2px;
    right: 2px;
    color: white;
    font-size: 12px;
    z-index: 1;
}

.calendar-day.weekend {
    background-color: #fff3cd;
}

.calendar-day.weekend:hover {
    background-color: #ffeeba;
}

.calendar-day.weekend.selected {
    background-color: #ffeeba;
    box-shadow: 0 0 0 2px #0d6efd;
}

.calendar-day.bg-success {
    background-color: #198754 !important;
}

.calendar-day.bg-danger {
    background-color: #dc3545 !important;
}

.calendar-day .day-number {
    font-weight: bold;
    margin-bottom: 5px;
}

.calendar-day .time-entry {
    font-size: 0.6em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.calendar-day .delete-btn {
    position: absolute;
    bottom: 5px;
    right: 5px;
    padding: 2px 5px;
    font-size: 0.7rem;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.calendar-day:hover .delete-btn {
    opacity: 1;
}

.table-bordered td {
    vertical-align: top;
}

.table-bordered {
    table-layout: fixed;
}

.table-bordered td {
    width: 14.28%; /* 100% / 7 days */
}
</style>

<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Календарь</h5>
                <div>
                    <a href="?month=<?php echo $month-1; ?>&year=<?php echo $year; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-chevron-left"></i>
                    </a>
<?php
$months = [
    'January' => 'Январь',
    'February' => 'Февраль',
    'March' => 'Март',
    'April' => 'Апрель',
    'May' => 'Май',
    'June' => 'Июнь',
    'July' => 'Июль',
    'August' => 'Август',
    'September' => 'Сентябрь',
    'October' => 'Октябрь',
    'November' => 'Ноябрь',
    'December' => 'Декабрь'
];

$date = date('F Y', $first_day);
$translated_date = strtr($date, $months);
echo $translated_date;
/*
<span class="mx-2"><?php echo date('F Y', $first_day); ?></span>
*/
?>

                    <a href="?month=<?php echo $month+1; ?>&year=<?php echo $year; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Пн.</th>
                                <th>Вт.</th>
                                <th>Ср.</th>
                                <th>Чт.</th>
                                <th>Пт.</th>
                                <th>Сб.</th>
                                <th>Вс.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $day = 1;
                            for ($i = 0; $i < 6; $i++) {
                                echo "<tr>";
                                for ($j = 1; $j <= 7; $j++) {
                                    if ($i === 0 && $j < $first_day_of_week) {
                                        echo "<td></td>";
                                    } else if ($day > $number_days) {
                                        echo "<td></td>";
                                    } else {
                                        $current_date = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
                                        $has_entry = isset($entries[$current_date]);
                                        $is_holiday = $has_entry && $entries[$current_date]['is_holiday'];
                                        if($has_entry && $entries[$current_date]['description'])
                                        $entry_description= $entries[$current_date]['description'];
                                        else 
                                        $entry_description='';
                                        $is_weekend = $j >= 6; // Saturday (6) or Sunday (7)
                                        $class = $is_holiday ? 'bg-danger text-white' : ($has_entry ? 'bg-success text-white' : '');
                                        $class .= $is_weekend ? ' weekend' : '';
                                        echo "<td title='".$entry_description."' class='$class calendar-day' data-date='$current_date'>";
                                        echo "<div class='day-number'>$day</div>";
                                        if ($has_entry && !$is_holiday) {
                                            echo "<div class='time-entry text-white'>";
                                            echo date('H:i', strtotime($entries[$current_date]['start_time']));
                                            echo " - ";
                                            echo date('H:i', strtotime($entries[$current_date]['end_time']));
                                            echo "</div>";
                                            echo "<button class='delete-btn btn btn-sm btn-outline-light' onclick='deleteEntry(\"$current_date\")'>×</button>";
                                        } else if ($is_holiday) {
                                            echo "<div class='time-entry text-white'>Выходной</div>";
                                            echo "<button class='delete-btn btn btn-sm btn-outline-light' onclick='deleteEntry(\"$current_date\")'>×</button>";
                                        }
                                        echo "</td>";
                                        $day++;
                                    }
                                }
                                echo "</tr>";
                                if ($day > $number_days) break;
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Добавить запись</h5>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST" action="" id="timeEntryForm">
                    <div class="mb-3">
                        <label for="date" class="form-label">Выбранные даты:</label>
                        <div id="selectedDates" class="form-control" style="min-height: 50px; max-height: 100px; overflow-y: auto;"></div>
                    </div>
                    <div class="mb-3">
                        <label for="start_time" class="form-label">Время начала работы:</label>
                        <input type="time" class="form-control" id="start_time" name="start_time" value="<?php echo $users[0]['work_start']?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="end_time" class="form-label">Время окончания работы:</label>
                        <input type="time" class="form-control" id="end_time" name="end_time" value="<?php echo $users[0]['work_end']?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="end_time" class="form-label">Описание</label>
                        <input type="text" max="255"class="form-control" id="description" name="description" value="">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_holiday" name="is_holiday">
                            <label class="form-check-label" for="is_holiday">
                                Отметить как выходной
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Сохранить</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let selectedDates = new Set();
    const calendarDays = document.querySelectorAll('.calendar-day');
    const timeEntryForm = document.getElementById('timeEntryForm');
    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');
    const isHolidayInput = document.getElementById('is_holiday');
    const selectedDatesContainer = document.getElementById('selectedDates');
    
    // Function to update selected dates display
    function updateSelectedDatesDisplay() {
        const dates = Array.from(selectedDates).sort();
        selectedDatesContainer.innerHTML = dates.map(date => {
            const d = new Date(date);
            return `<span class="badge bg-primary me-1 mb-1">${d.toLocaleDateString()}</span>`;
        }).join('');
    }
    
    // Function to update form with selected date's data
    function updateFormWithDateData(date) {
        const dayElement = document.querySelector(`.calendar-day[data-date="${date}"]`);
        if (dayElement) {
            const isHoliday = dayElement.classList.contains('bg-danger');
            const hasEntry = dayElement.classList.contains('bg-success');
            
            if (hasEntry || isHoliday) {
                isHolidayInput.checked = isHoliday;
                
                if (!isHoliday) {
                    const timeText = dayElement.querySelector('.time-entry')?.textContent.trim();
                    if (timeText) {
                        // Convert 12-hour format to 24-hour format
                        const [timeRange] = timeText.split(' - ');
                        const [time, period] = timeRange.split(' ');
                        let [hours, minutes] = time.split(':');
                        
                        if (period === 'PM' && hours !== '12') {
                            hours = parseInt(hours) + 12;
                        } else if (period === 'AM' && hours === '12') {
                            hours = '00';
                        }
                        
                        // Format time for input
                        const formattedTime = `${hours.toString().padStart(2, '0')}:${minutes}`;
                        startTimeInput.value = formattedTime;
                        
                        // Do the same for end time
                        const [, endTimeRange] = timeText.split(' - ');
                        const [endTime, endPeriod] = endTimeRange.trim().split(' ');
                        let [endHours, endMinutes] = endTime.split(':');
                        
                        if (endPeriod === 'PM' && endHours !== '12') {
                            endHours = parseInt(endHours) + 12;
                        } else if (endPeriod === 'AM' && endHours === '12') {
                            endHours = '00';
                        }
                        
                        const formattedEndTime = `${endHours.toString().padStart(2, '0')}:${endMinutes}`;
                        endTimeInput.value = formattedEndTime;
                    }
                }
            } else {
                startTimeInput.value = '<?php echo $users[0]['work_start']?>';
                endTimeInput.value = '<?php echo $users[0]['work_end']?>';
                isHolidayInput.checked = false;
            }
        }
    }
    
    calendarDays.forEach(day => {
        day.addEventListener('click', function(e) {
            // Don't handle click if delete button was clicked
            if (e.target.classList.contains('delete-btn')) {
                return;
            }
            
            const date = this.dataset.date;
            
            if (e.ctrlKey || e.metaKey) {
                // Multi-select mode
                if (selectedDates.has(date)) {
                    selectedDates.delete(date);
                    this.classList.remove('selected');
                } else {
                    selectedDates.add(date);
                    this.classList.add('selected');
                }
            } else {
                // Single select mode
                selectedDates.clear();
                calendarDays.forEach(d => d.classList.remove('selected'));
                selectedDates.add(date);
                this.classList.add('selected');
            }
            
            updateSelectedDatesDisplay();
            
            // Update form with the first selected date's data
            if (selectedDates.size > 0) {
                const firstDate = Array.from(selectedDates)[0];
                updateFormWithDateData(firstDate);
            }
        });
    });
    
    timeEntryForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (selectedDates.size === 0) {
            alert('Please select at least one date');
            return;
        }
        
        const formData = new FormData(this);
        const dates = Array.from(selectedDates);
        let successCount = 0;
        let errorCount = 0;
        
        // Submit form for each selected date
        dates.forEach((date, index) => {
            formData.set('date', date);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                successCount++;
                
                // If this is the last request, show the result
                if (successCount + errorCount === dates.length) {
                    if (errorCount > 0) {
                        alert(`Successfully saved ${successCount} dates, but ${errorCount} failed.`);
                    } else {
                        alert(`Successfully saved ${successCount} dates.`);
                        window.location.reload();
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                errorCount++;
                
                // If this is the last request, show the result
                if (successCount + errorCount === dates.length) {
                    if (successCount > 0) {
                        alert(`Successfully saved ${successCount} dates, but ${errorCount} failed.`);
                    } else {
                        alert('Failed to save dates. Please try again.');
                    }
                }
            });
        });
    });
});

// Function to delete an entry
function deleteEntry(date) {
    if (confirm('Are you sure you want to delete this entry?')) {
        const formData = new FormData();
        formData.append('date', date);
        formData.append('delete', '1');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            window.location.reload();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to delete entry. Please try again.');
        });
    }
}
</script>

<?php require_once 'includes/footer.php'; ?> 