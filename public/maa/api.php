<?php
// api.php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *"); // 开发测试用，生产环境请指定域名

// 1. 数据库配置
$sqlConfig = include(__DIR__ . '/../../config.php');
$host = $sqlConfig['db']['host'] ?? 'localhost';
$db = $sqlConfig['db']['dbname'] ?? 'foc';
$user = $sqlConfig['db']['username'] ?? 'foc';      
$pass = $sqlConfig['db']['password'] ?? '123';

$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => '数据库连接失败']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'import_masters':
        handleImportMasters($pdo);
        break;
    case 'import_relationships':
        handleImportRelationships($pdo);
        break;
    case 'get_master_list':
        handleGetMasterList($pdo);
        break;
    case 'get_master_view':
        handleGetMasterView($pdo);
        break;
    case 'approve_apprentice':
        handleApprove($pdo);
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => '未知动作']);
}

// --- 功能函数 ---

function handleImportMasters($pdo) {
    $rawText = $_POST['data'] ?? '';
    $rows = explode("\n", trim($rawText));
    $count = 0;
    $stmt = $pdo->prepare("INSERT INTO fyr_masters (masterName, type, token) VALUES (?, ?, ?)");
    $check = $pdo->prepare("SELECT id FROM fyr_masters WHERE masterName = ?");

    foreach ($rows as $row) {
        $cols = explode("\t", trim($row));
        if (count($cols) >= 2) {
            $name = trim($cols[0]); 
            $type = trim($cols[1]);
            // 校验：必须是中文，且长度2-4
            if (!preg_match('/^[\x{4e00}-\x{9fa5}]{2,4}$/u', $name)) continue;
            $check->execute([$name]);
            if($check->fetch()) continue; 
            $token = md5(uniqid($name, true)); 
            $stmt->execute([$name, $type, $token]);
            $count++;
        }
    }
    echo json_encode(['status' => 'success', 'message' => "成功导入 {$count} 位师傅"]);
}

function handleImportRelationships($pdo) {
    $rawText = $_POST['data'] ?? '';
    $lines = explode("\n", $rawText);
    $parsedRecords = []; 
    $currentRecord = null; 
    $patternNewRow = '/^([\x{4e00}-\x{9fa5}]{2,4})\t.*?\t([\x{4e00}-\x{9fa5}]{2,4})/u';

    foreach ($lines as $line) {
        $cleanLine = rtrim($line); 
        if (preg_match($patternNewRow, $cleanLine, $matches)) {
            if ($currentRecord) $parsedRecords[] = $currentRecord;
            $cols = explode("\t", $cleanLine);
            $msg = isset($cols[3]) ? trim($cols[3]) : '';
            $currentRecord = ['master' => $matches[1], 'appr' => $matches[2], 'msg' => $msg];
        } else {
            if ($currentRecord && trim($cleanLine) !== '') $currentRecord['msg'] .= "\n" . trim($cleanLine);
        }
    }
    if ($currentRecord) $parsedRecords[] = $currentRecord;

    $successCount = 0;
    $failedLines = []; 
    $findMaster = $pdo->prepare("SELECT id FROM fyr_masters WHERE masterName = ? LIMIT 1");
    $checkExist = $pdo->prepare("SELECT id FROM fyr_apprentices WHERE apprenticeName = ? AND master_id = ?");
    $insertAppr = $pdo->prepare("INSERT INTO fyr_apprentices (apprenticeName, master_id, message, ifApproved) VALUES (?, ?, ?, 0)");

    foreach ($parsedRecords as $rec) {
        $masterName = $rec['master'];
        $apprName = $rec['appr'];
        $msg = $rec['msg'];
        $findMaster->execute([$masterName]);
        $master = $findMaster->fetch();
        if ($master) {
            $checkExist->execute([$apprName, $master['id']]);
            if (!$checkExist->fetch()) {
                $insertAppr->execute([$apprName, $master['id'], $msg]);
                $successCount++;
            } else {
                $failedLines[] = "{$apprName} (已存在)";
            }
        } else {
            $failedLines[] = "找不到师傅: {$masterName}";
        }
    }
    
    $msg = "导入成功 {$successCount} 条。";
    if (count($failedLines) > 0) {
        $failMsg = array_slice($failedLines, 0, 10);
        if(count($failedLines) > 10) $failMsg[] = "...等共" . count($failedLines) . "条";
        echo json_encode(['status' => 'success', 'message' => $msg, 'failed_details' => $failMsg]);
    } else {
        echo json_encode(['status' => 'success', 'message' => $msg]);
    }
}

// --- 修改的核心：只获取有徒弟的师傅 ---
function handleGetMasterList($pdo) {
    // 使用 JOIN 关联查询，只有在 fyr_apprentices 表中有记录的师傅才会被查出来
    // 同时 count(*) 计算徒弟数量
    $sql = "SELECT m.id, m.masterName, m.type, m.token, COUNT(a.id) as appr_count 
            FROM fyr_masters m 
            JOIN fyr_apprentices a ON m.id = a.master_id 
            GROUP BY m.id 
            ORDER BY m.id DESC";
    
    $stmt = $pdo->query($sql);
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
}

function handleGetMasterView($pdo) {
    $token = $_POST['token'] ?? '';
    if (!$token) { echo json_encode(['status' => 'error', 'message' => 'Token缺失']); return; }
    $stmt = $pdo->prepare("SELECT id, masterName FROM fyr_masters WHERE token = ?");
    $stmt->execute([$token]);
    $master = $stmt->fetch();
    if (!$master) { echo json_encode(['status' => 'error', 'message' => '无效的链接']); return; }
    $apprStmt = $pdo->prepare("SELECT * FROM fyr_apprentices WHERE master_id = ? ORDER BY ifApproved ASC, updateTime DESC");
    $apprStmt->execute([$master['id']]);
    echo json_encode(['status' => 'success', 'master' => $master, 'apprentices' => $apprStmt->fetchAll()]);
}

function handleApprove($pdo) {
    $id = $_POST['appr_id'] ?? 0;
    $status = $_POST['status'] ?? 1;
    $stmt = $pdo->prepare("UPDATE fyr_apprentices SET ifApproved = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    echo json_encode(['status' => 'success']);
}
?>