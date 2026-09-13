# 工单可靠性修复与验收（2026-09-13）

部署时间：2026-09-13 21:47:29 +08:00；生产复核：21:48 +08:00。
后端已部署。小程序源码改动已准备，须微信平台构建和发布才能影响手机上的小程序。

## 已修复

- 完成接口使用一次 verifyToken 的权威身份，兼容 fy_users 小程序令牌、fy_app_tokens 长效 App 令牌、fy_admin_tokens 后台多会话令牌；不再二次仅查询 fy_users.access_token。
- 接受 Authorization 字段大小写差异；非法/过期令牌401，缺失工单404，参数错误400/422，冲突409，未授权403，数据库故障500。响应始终是 JSON；保留客户端依赖的 success/message/status/changedFields 字段。
- give 转接在单事务中锁工单及按 ID 排序的相关用户，更新归属、技术员容量、转单码、转接记录、通知任务和请求回执。死锁/锁超时整笔事务有限重试。
- 转单码统一数字字符串/int，避免严格比较把合法字符串码误判。目标必须是技术员；后台管理员的 tid 指定目标保持可用；旧小程序/App不传 tid 时接到当前技术员。
- 旧数值码重复请求可在24小时内对照回执返回原结果；工单再次转走后旧回执失效。近期消费前后的码和待投递事件版本不复用。
- 可选 request_id 长度1–64，字符为字母数字、下划线、连字符。同 actor 同 ID 同参数不重复改派；同 ID 不同参数或当前工单已被再次转走返回409。旧客户端不传 ID 仍兼容，但永久 order_hash 的迟到重试无法和主动再次转单区分；不能对这条旧协议声称强幂等。
- 取消/关闭先锁后判断，工单状态、用户额度返还、技术员容量和通知任务一起提交。重复取消、并发取消、Closed/Canceled交叉重试不重复返还额度。
- 技术员容量计入 Repairing/UserConfirming/TechConfirming，结合 max_concurrent，不再完成一单就把仍忙碌的技术员误置空闲。
- 双方确认保留现有语义：技术员提交 UserConfirming，用户提交 TechConfirming；第二方确认落 Done、写 completion_time。延迟确认不重开 Done/Canceled/Closed。
- 恢复本人/当前技术员编辑设备、联系人、故障描述、校区、购机日期、昵称、图片等业务白名单字段。工单归属、转单凭证、主键和系统审计字段受保护；尝试变更受保护或未知字段明确报错，不再“成功但没保存”。
- 后台真实取消/关闭入口 v1/admin/setTicket.php 纳入共享事务，保留 super 权限、id 参数、仅 Canceled/Closed 的状态契约，以及 fy_admin_logs 审计。真正无变化的重试不伪造审计。

历史说明：白名单至少在9月7日的生产记录已存在；更早 GitHub 代码允许更多编辑。本次不能据此断言“编辑功能从未存在”。当前客户端界面没有新增基础资料编辑按钮，恢复的是后端编辑能力。

## 通知处理

新增两张 InnoDB 表：

- fy_ticket_notification_outbox：每个接收方/渠道一条持久化任务。
- fy_ticket_action_receipts：转接回执、版本及可选 request_id。

业务成功不再等待短信、SMTP或微信。systemd timer 每15秒触发独立 PHP CLI worker（容器 www-data 用户），不占用 FPM 请求。worker 具备互斥锁、租约恢复、渠道独立重试、最多8次及退避；过期的转接版本任务标记 superseded。外部发送成功但进程在记录成功前崩溃仍可能重复投递，不承诺外部通知严格 exactly-once。

通知传输配置存在性已核对。测试全部使用 stub，未向真实用户发送测试短信、邮件或微信消息，不能据此声称供应商实际送达已经验收。供应商拒绝会留在重试/failed队列，业务提交不受影响。

## 小程序补丁

- 转接、结束、状态变更和完成图片请求遇401时等待登录并仅重放一次。
- 并发登录合并；迟到401可复用已刷新的令牌；登录失败/超时和网络失败均结束 Promise。
- 不自动重试结果不确定的网络失败写请求。
- giveTicket 每次主动操作带新 request_id，401重放保留 ID/原参数。
- 页面使用后端最终状态刷新；双方确认已 Done 时不再错误展示等待状态。
- 只提供源码补丁，未发布微信体验版/正式版，未声称完成真机操作验收。

## 验证证据

| 检查 | 结果 |
| --- | --- |
| PHP 8.3.33 全部候选及测试脚本语法 | 通过 |
| 独立 MySQL 8.4.11：业务/权限/并发/故障注入 | 32/32 |
| 实际 PHP HTTP：三种令牌、编辑/转接/完成、后台契约、JSON错误边界 | 9/9 |
| 持久化通知 worker：渠道失败、租约恢复、并发互斥、A→B→A旧事件 | 8/8 |
| Node 小程序请求与页面联合测试 | 47/47 |
| 生产文件 SHA256 | 8个部署文件全部匹配 |
| 生产无写入探测 | 无令牌401；有效小程序/App令牌对不存在工单404，9项通过 |
| 当前有效后台会话 | 无可复用会话；已通过隔离真实HTTP后台令牌测试 |
| 系统/站点 | running、失败单元0；官网/FOC/Wiki HTTP200；MySQL8.4.11持续运行 |
| 通知 timer/service | timer active；service Result=success、ExecMainStatus=0 |

测试位于 tests/backend-tests 和 tests/client-tests。隔离MySQL容器从生产已有8.4.11镜像启动，使用独立 internal网络、tmpfs数据目录、合成用户和工单（编号>2^31），不接生产数据库。生产探测只使用不存在工单编号，不修改真实工单。原用户数据保留。

## 备份和恢复

服务器备份目录：/opt/1panel/backups/manual/ticket-reliability-20260913-final/
包含 foc 全库一致性逻辑备份、原四个入口与身份/审计源码备份、部署前后哈希清单。gzip/tar读取及SHA256均已验证。数据备份仅留服务器，不复制本地或上传GitHub。

代码回滚应先停 foc-ticket-notifications.timer，确认worker结束，再从 code-before.tar.gz 仅恢复四个入口：
v1/ticket/give.php、v1/ticket/set.php、v1/ticket/complete.php、v1/admin/setTicket.php。
核对部署后哈希未被其他维护更改再操作。新增两表是增量结构，不应在回滚时删除或清空；通知任务和回执保留供恢复。
utils/token.php、utils/adminlog.php 本次生产未修改，交付中是实际依赖的生产快照，避免GitHub旧版覆盖已存在的多令牌能力。

## 基础设施状态补记

系统与内核已于16:23重启切换到6.8.0-139；宿主机MySQL残留包导致的dpkg问题已修复，fwupd为2.0.20。生产MySQL于21:05由8.4.3升级至8.4.11，日志显示80403→80411完成；仅127.0.0.1:3306，原兼容参数/卷/资源约束保留。两个PHP为8.3.33，1Panel为1.10.34-lts。

Docker仍为27.3.1，6个Docker相关包保持hold（兼容性尚未完成评估）；OpenResty保持1.21.4.3-3-3-focal，当前1Panel商店没有其他版本；phpMyAdmin仍5.2.1，5.2.3升级未完成。原docker.1panelproxy.com对/v2/返回404，官方Docker Hub连接超时；镜像拉取曾经失败，不能当成已升级。

MySQL8.4.11通过镜像源预拉取后由1Panel使用本地镜像完成；amd64清单摘要在三个镜像源一致，但未获得直连官方独立摘要验证。相关备份留在服务器 mysql-8.4.3-to-8.4.11-20260913-1600 和 -2035 目录。
