<?php
require 'db.php';
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $status = $_POST['status'] ?? '';
    $mark = $_POST['mark'] ?? null;
    $regno = $_POST['regno'] ?? '';
    $module_id = $_POST['module_id'] ?? null;

    if ($id && in_array($status, ['pending', 'under review', 'resolved'])) {
        $stmt = $pdo->prepare("UPDATE appeals SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
    }

    if ($regno && $module_id !== null && $mark !== null && is_numeric($mark)) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM marks WHERE student_regno = ? AND module_id = ?");
        $check->execute([$regno, $module_id]);
        $exists = $check->fetchColumn();

        if ($exists) {
            $updateMark = $pdo->prepare("UPDATE marks SET mark = ? WHERE student_regno = ? AND module_id = ?");
            $updateMark->execute([$mark, $regno, $module_id]);
        } else {
            $insertMark = $pdo->prepare("INSERT INTO marks (student_regno, module_id, mark) VALUES (?, ?, ?)");
            $insertMark->execute([$regno, $module_id, $mark]);
        }
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?msg=" . urlencode("Marks updated successfully"));
    exit;
}

$perPage = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$startAt = ($page - 1) * $perPage;

$statusFilter = $_GET['status'] ?? '';
$searchRegno = $_GET['search'] ?? '';

$params = [];
$where = "";

if ($statusFilter && in_array($statusFilter, ['pending', 'under review', 'resolved'])) {
    $where .= " AND a.status = ?";
    $params[] = $statusFilter;
}

if ($searchRegno) {
    $where .= " AND s.regno LIKE ?";
    $params[] = "%$searchRegno%";
}

$sql = "SELECT a.id, s.name AS student_name, s.regno, a.module_id, m.module_name, a.reason, a.status, mk.mark
        FROM appeals a 
        JOIN students s ON a.student_regno = s.regno 
        JOIN modules m ON a.module_id = m.id 
        LEFT JOIN marks mk ON mk.student_regno = s.regno AND mk.module_id = m.id
        WHERE 1 $where
        LIMIT $startAt, $perPage";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appeals = $stmt->fetchAll();

$countStmt = $pdo->prepare("SELECT COUNT(*) 
                            FROM appeals a 
                            JOIN students s ON a.student_regno = s.regno 
                            WHERE 1 $where");
$countStmt->execute($params);
$totalAppeals = $countStmt->fetchColumn();
$totalPages = ceil($totalAppeals / $perPage);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Appeals</title>
    <style>
        body {
            /* font-family removed to keep original font */
            margin: 30px;
            background-color: #e0f7ff; /* light sky blue */
        }

        h2, h3 {
            color: white;
        }

        a {
            text-decoration: none;
            color: #ff69b4; /* pink links */
        }

        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-top: 2%;
        }

        input[type="text"], select, button {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        button {
            background-color: #006400; /* dark green */
            color: white;
            border: none;
            cursor: pointer;
            transition: 0.2s;
        }

        button:hover {
            background-color: #004d00; /* even darker green on hover */
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #87CEEB; /* sky blue header */
            color: #003366;
        }

        td form {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pagination {
            margin-top: 20px;
        }

        .pagination a {
            padding: 6px 12px;
            margin: 0 3px;
            background-color: #ffb6c1; /* light pink */
            color: #880044;
            border-radius: 4px;
        }

        .pagination a:hover {
            background-color: #ff69b4; /* deeper pink on hover */
            color: white;
        }

        .success {
            background: #d0f0d0;
            color: #006400;
            padding: 10px;
            border: 1px solid #a3d9a5;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>

<div style="background: #1590c1; padding: 1px;color: white;border-radius: 5px;">
    <h2><a href="logout.php" style="float: right;margin-right:2%;">
        <button style="float: right; background: #006400; color: white;">Logout</button></a></h2>
    <h1 style="text-align: center;">Student Appeals Management System</h1>
    <h4 style="margin-left: 2%;">Welcome, <?= htmlspecialchars($_SESSION['admin']) ?> </h4>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="success"><?= htmlspecialchars($_GET['msg']) ?></div>
<?php endif; ?>

<form method="get" class="filter-bar">
    <input type="text" name="search" placeholder="Search by RegNo" value="<?= htmlspecialchars($searchRegno) ?>">
    <select name="status">
        <option value="">-- Status Filter --</option>
        <option value="pending" <?= $statusFilter == 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="under review" <?= $statusFilter == 'under review' ? 'selected' : '' ?>>Under Review</option>
        <option value="resolved" <?= $statusFilter == 'resolved' ? 'selected' : '' ?>>Resolved</option>
    </select>
    <button type="submit">Filter</button>
</form>

<table>
    <tr>
        <th>#</th>
        <th>Student</th>
        <th>Module</th>
        <th>Marks</th>
        <th>Reason</th>
        <th>Status</th>
        <th>Action</th>
    </tr>
    <?php foreach ($appeals as $i => $a): ?>
    <tr>
        <td><?= $i+1 + $startAt ?></td>
        <td><?= htmlspecialchars($a['student_name']) ?> (<?= htmlspecialchars($a['regno']) ?>)</td>
        <td><?= htmlspecialchars($a['module_name']) ?></td>
        <td><?= is_numeric($a['mark']) ? $a['mark'] : 'N/A' ?></td>
        <td><?= htmlspecialchars($a['reason']) ?></td>
        <td><?= ucfirst($a['status']) ?></td>
        <td>
            <form method="post">
                <input type="hidden" name="id" value="<?= htmlspecialchars($a['id']) ?>">
                <input type="hidden" name="regno" value="<?= htmlspecialchars($a['regno']) ?>">
                <input type="hidden" name="module_id" value="<?= htmlspecialchars($a['module_id']) ?>">
                <select name="status">
                    <option <?= $a['status'] == 'pending' ? 'selected' : '' ?>>pending</option>
                    <option <?= $a['status'] == 'under review' ? 'selected' : '' ?>>under review</option>
                    <option <?= $a['status'] == 'resolved' ? 'selected' : '' ?>>resolved</option>
                </select>
                <input type="number" step="0.01" name="mark" value="<?= is_numeric($a['mark']) ? $a['mark'] : '' ?>" placeholder="Mark" style="width: 60px;">
                <button type="submit">Update</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?>&status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($searchRegno) ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>

</body>
</html>
<?php
// Close the database connection    