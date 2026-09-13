<?php
/** Shared HTTP boundary: authenticate exactly once using all supported token stores. */
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
require_once __DIR__ . '/ticket_actions.php';

function ticketHttpReply(array $body, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}

function ticketHttp(string $action, bool $adminOnly = false): void
{
    global $pdo;
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(200);
        return;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST, OPTIONS');
        ticketHttpReply(['success'=>false,'message'=>'Method not allowed'],405);
        return;
    }
    $id = null;
    try {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        $authorization = $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer[ \t]+([^ \t\r\n]+)$/iD', $authorization, $match)) {
            ticketHttpReply(['success'=>false,'error'=>'Unauthorized for no Bearer or invalid format'],401);
            return;
        }
        $config = require __DIR__ . '/../config.php';
        $pdo = new PDO(
            'mysql:host=' . $config['db']['host'] . ';dbname=' . $config['db']['dbname'] . ';charset=utf8mb4',
            $config['db']['username'], $config['db']['password'],
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]
        );
        require_once __DIR__ . '/token.php';
        $actor = verifyToken($match[1]);
        if (!is_array($actor) || empty($actor['id'])) {
            ticketHttpReply(['success'=>false,'error'=>is_string($actor)?$actor:'invalid token'],401);
            return;
        }
        if ($adminOnly && empty($actor['is_admin'])) {
            ticketHttpReply(['success'=>false,'message'=>'Permission denied'],403);
            return;
        }
        $adminAccount = null;
        if ($adminOnly) {
            $check = $pdo->prepare('SELECT id,username,openid,role FROM fy_admins WHERE openid=?');
            $check->execute([$actor['openid']]);
            $adminAccount = $check->fetch(PDO::FETCH_ASSOC);
            if (!$adminAccount || $adminAccount['role'] !== 'super') {
                ticketHttpReply(['success'=>false,'message'=>'Permission denied'],403);
                return;
            }
        }
        $raw = file_get_contents('php://input', false, null, 0, 131073);
        if ($raw === false || strlen($raw) > 131072) {
            ticketHttpReply(['success'=>false,'message'=>'Request too large'],413);
            return;
        }
        $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        if (!is_array($data) || array_is_list($data)) {
            throw new JsonException('JSON object required');
        }
        if ($adminOnly) {
            $data['tid'] = $data['id'] ?? $data['tid'] ?? null;
            if (!in_array($data['repair_status'] ?? '', ['Canceled','Closed'], true)) {
                ticketHttpReply(['success'=>false,'message'=>'Status must be Canceled or Closed'],400);
                return;
            }
            // This existing admin route is for status changes only.
            $data = ['tid'=>$data['tid'], 'repair_status'=>$data['repair_status']];
        }
        $id = $data['order_id'] ?? $data['tid'] ?? null;
        switch ($action) {
            case 'give': $result = ticketGive($pdo, $actor, $data); break;
            case 'complete': $result = ticketComplete($pdo, $actor, $data); break;
            case 'set': $result = ticketSet($pdo, $actor, $data, max(1,(int)($config['info']['weeklyset'] ?? 5))); break;
            default: throw new LogicException('Unknown action');
        }
        if ($adminOnly && ($result['success'] ?? false)) {
            $result['message'] = 'Ticket updated successfully';
            if (($result['changed'] ?? false) === true) {
                require_once __DIR__ . '/adminlog.php';
                fyAdminLog($pdo,$adminAccount,'set_ticket','ticket',(string)$id,
                    ['to'=>$result['repair_status'] ?? $data['repair_status'],'source'=>'ticket_reliability']);
            }
        }
        ticketHttpReply($result);
    } catch (TicketActionError $e) {
        $body = $e->body;
        if ($action === 'set') {
            $body['changedFields'] = [];
        } elseif ($action === 'complete') {
            $body['status'] = $e->getMessage() === 'Ticket not found' ? 'ticket not found' : $e->getMessage();
        }
        ticketHttpReply($body, $e->httpStatus);
    } catch (JsonException $e) {
        ticketHttpReply(['success'=>false,'message'=>'Invalid JSON'],400);
    } catch (Throwable $e) {
        // Do not put SQL, access tokens, contact details or database passwords in responses/logs.
        error_log('[ticket.' . $action . '] failure class=' . get_class($e) . ' code=' . $e->getCode());
        ticketHttpReply(['success'=>false,'message'=>'Database error','status'=>'unknown_error'],500);
    }
}
