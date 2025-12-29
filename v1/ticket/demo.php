<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Content-Type: application/json; charset=UTF-8");
if ($_GET['d'] === "1"){


require '../../utils/email.php';
require '../../utils/sms.php';

$config = include('../../config.php');

// $chosenTechnician = [
//     'id' => 1123,
//     'nickname' => '技术员A',
//     'email' => 'wjlfish@qq.com'
// ];
// $workOrder = [
//     'id' => 4567,
//     'user_phone' => '18009511952',
//     'qq_number' => 'Q1234567891'
// ];

// // 创建 Email 类实例
// $email = new Email($config);

// // 发送邮件示例
// $htmlBody = "
// <html>
//   <head>
//     <style>
//       body {font-family: Arial, sans-serif; line-height: 1.6; color:#333;}
//       .container {
//         border: 1px solid #eee; 
//         padding: 20px; 
//         max-width: 600px; 
//         margin: auto; 
//         background: #fafafa;
//         border-radius: 8px;
//       }
//       h2 {color:#007bff; margin-top:0;}
//       .footer {margin-top:20px; font-size:12px; color:#888;}
//     </style>
//   </head>
//   <body>
//     <div class='container'>
//       <h2>工单分配通知</h2>
//       <p>亲爱的技术员<strong>{$chosenTechnician['nickname']}</strong>：</p>
//       <p>您有一个新的报修工单，基本信息如下：</p>
//       <ul>
//         <li>工单编号：{$workOrder['id']}</li>
//         <li>用户电话号码：{$workOrder['user_phone']}</li>
//         <li>其他联系方式：{$workOrder['qq_number']}</li>
//       </ul>
//       <p>工单的详细情况如下：</p>
//       <ul>
//         <li>工单编号：{$workOrder['id']}</li>
//         <li>用户电话号码：{$workOrder['user_phone']}</li>
//         <li>其他联系方式：{$workOrder['qq_number']}</li>
//       </ul>
//       <p>请尽快联系用户！飞扬感谢您的付出 ：）</p>
//       <div class='footer'>本邮件由飞扬俱乐部自动发送，请勿直接回复。</div>
//     </div>
//   </body>
// </html>";
// $htmlBody = "
// <html>
//   <head>
//     <style>
//       body {
//         font-family: Arial, sans-serif;
//         background-color:#f5f5f5;
//         margin:0;
//         padding:0;
//       }
//       .ticket {
//         background:#fff;
//         border:1px solid #ddd;
//         border-radius:10px;
//         max-width:600px;
//         margin:20px auto;
//         padding:20px;
//         box-shadow:0 2px 6px rgba(0,0,0,0.1);
//       }
//       .ticket-header {
//         border-bottom:1px dashed #ccc;
//         padding-bottom:10px;
//         margin-bottom:15px;
//       }
//       .ticket-header h2 {
//         margin:0;
//         color:#007bff;
//       }
//       .ticket-body {
//         font-size:14px;
//         color:#333;
//       }
//       .ticket-body .row {
//         display:flex;
//         justify-content:space-between;
//         margin-bottom:8px;
//       }
//       .label {
//         font-weight:bold;
//         width:120px;
//       }
//       .ticket-footer {
//         margin-top:15px;
//         font-size:12px;
//         color:#888;
//         border-top:1px dashed #ccc;
//         padding-top:8px;
//         text-align:center;
//       }
//     </style>
//   </head>
//   <body>
//     <div class='ticket'>
//       <div class='ticket-header'>
//         <h2>工单分配通知</h2>
//         <p>亲爱的技术员<strong>{$chosenTechnician['nickname']}</strong>：</p>
//         <p>有一个新的报修工单分配到您，工单信息如下：</p>
//       </div>
//       <div class='ticket-body'>
//         <div class='row'><span class='label'>工单号：</span><span>{$workOrder['id']}</span></div>
//         <div class='row'><span class='label'>用户电话：</span><span>{$workOrder['user_phone']}</span></div>
//         <div class='row'><span class='label'>其他联系方式：</span><span>{$workOrder['qq_number']}</span></div>
//         <div class='row'><span class='label'> </div>
//         <div class='row'><span class='label'> </div>
//         <div class='row'><span class='label'>设备类型：</span><span>{$workOrder['user_phone']}</span></div>
//         <div class='row'><span class='label'>型号：</span><span>{$workOrder['user_phone']}</span></div>
//         <div class='row'><span class='label'>保修状态：</span><span>{$workOrder['user_phone']}</span></div>
//         <div class='row'><span class='label'>品牌：</span><span>{$workOrder['user_phone']}</span></div>
//         <div class='row'><span class='label'>故障类型：</span><span>{$workOrder['user_phone']}</span></div>
//         <div class='row'><span class='label'>问题描述：</span><span>{$workOrder['user_phone']}</span></div>
//       </div>
//       <p>请尽快联系用户！飞扬感谢您的付出 ：）</p>
//       <div class='ticket-footer'>
//         本邮件由飞扬俱乐部自动发送，请勿直接回复。
//       </div>
//     </div>
//   </body>
// </html>";
// $htmlBody = "
// <html>
//   <head>
//     <style>
//       body {
//         font-family: Arial, sans-serif;
//         background-color:#f5f5f5;
//         margin:0;
//         padding:0;
//       }
//       .ticket {
//         background:#fff;
//         border:1px solid #ddd;
//         border-radius:10px;
//         max-width:600px;
//         margin:20px auto;
//         padding:20px;
//         box-shadow:0 2px 6px rgba(0,0,0,0.1);
//       }
//       .ticket-header {
//         border-bottom:1px dashed #ccc;
//         padding-bottom:10px;
//         margin-bottom:15px;
//       }
//       .ticket-header h2 {
//         margin:0;
//         color:#007bff;
//       }
//       .section {
//         margin-bottom:15px;
//       }
//       .section h3 {
//         margin:0 0 8px 0;
//         font-size:16px;
//         color:#555;
//         border-left:4px solid #007bff;
//         padding-left:6px;
//       }
//       .row {
//         display:flex;
//         justify-content:space-between;
//         margin-bottom:6px;
//         font-size:14px;
//       }
//       .label {
//         font-weight:bold;
//         width:130px;
//         color:#333;
//       }
//       .ticket-image {
//         text-align:center;
//         margin-top:10px;
//       }
//       .ticket-image img {
//         max-width:100%;
//         border-radius:6px;
//         border:1px solid #ccc;
//       }
//       .ticket-footer {
//         margin-top:15px;
//         font-size:12px;
//         color:#888;
//         border-top:1px dashed #ccc;
//         padding-top:8px;
//         text-align:center;
//       }
//     </style>
//   </head>
//   <body>
//     <div class='ticket'>
//       <div class='ticket-header'>
//         <h2>工单分配通知</h2>
//         <p>亲爱的技术员<strong>{$chosenTechnician['nickname']}</strong>：</p>
//         <p>有一个新的报修工单分配到您，工单信息如下：</p>
//       </div>
      
//       <!-- 工单信息部分 -->
//       <div class='section'>
//         <h3>工单信息</h3>
//         <div class='row'><span class='label'>技术员：</span><span>{$chosenTechnician['nickname']}</span></div>
//         <div class='row'><span class='label'>用户电话：</span><span>{$workOrder['user_phone']}</span></div>
//         <div class='row'><span class='label'>其他联系方式：</span><span>{$workOrder['qq_number']}</span></div>
//       </div>
      
//       <!-- 设备/故障信息部分 -->
//       <div class='section'>
//         <h3>设备/故障信息</h3>
//         <div class='row'><span class='label'>设备类型：</span><span>{$workOrder['device_type']}</span></div>
//         <div class='row'><span class='label'>型号：</span><span>{$workOrder['model']}</span></div>
//         <div class='row'><span class='label'>保修状态：</span><span>{$workOrder['warranty_status']}</span></div>
//         <div class='row'><span class='label'>品牌：</span><span>{$workOrder['computer_brand']}</span></div>
//         <div class='row'><span class='label'>故障类型：</span><span>{$workOrder['fault_type']}</span></div>
//         <div class='row'><span class='label'>问题描述：</span><span>{$workOrder['repair_description']}</span></div>
//         <div class='ticket-image'>
//           <img src='{$workOrder['repair_image_url']}' alt='工单图片'>
//         </div>
//       </div>
//       <h3>请尽快联系用户！飞扬感谢您的付出 ：）</h3>
//       <div class='ticket-footer'>
//         本邮件由飞扬俱乐部自动发送，请勿直接回复。
//       </div>
//     </div>
//   </body>
// </html>";


// $sent = $email->sendEmail(
//     $chosenTechnician['email'],
//     "新工单分配通知",
//     $htmlBody
// );
     

// // 创建 Sms 类实例
$sms = new Sms($config);

// // 发送短信示例
$result = $sms->sendSms('verification', '18009511952', ['code' => '1234', 'min' => '5']);

// // 输出发送结果
if ($result === true) {
    echo "短信发送成功！";
} else {
    echo "短信发送失败，原因：".json_encode($result);
}
// if ($sent === true) {
//     echo "邮件发送成功！";
// } else {
//     echo "邮件发送失败，原因：$sent";
// }
}
// $response = [
//     'success' => true,
//     'message' => 'Order transferred successfully',
//     'new_technician_id' => 1123,
//     'new_assigned_time' => "2024-09-11 11:12:36"
// ];
// echo json_encode($response);
?>
