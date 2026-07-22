<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\ApiTestCase;

final class CrmCloseTest extends ApiTestCase
{
    private string $agentId = '22222222-2222-4222-8222-222222222222';
    private string $customerId = '33333333-3333-4333-8333-333333333333';
    private string $roomId = '44444444-4444-4444-8444-444444444444';

    protected function setUp(): void
    {
        parent::setUp();
        $pdo = $this->ensureSchema();
        $this->seedChatFixtures($pdo);
    }

    private function seedChatFixtures(\PDO $pdo): void
    {
        require_once dirname(__DIR__, 2) . '/includes/util/PiiEncryptor.php';
        $hash = password_hash('Agent123!', PASSWORD_BCRYPT, ['cost' => 4]);
        $pdo->prepare(
            'INSERT INTO agents (id, login_id, password_hash, name, role, status)
             VALUES (:id, :login, :hash, :name, :role, :status)'
        )->execute([
            ':id' => $this->agentId, ':login' => 'agent1', ':hash' => $hash,
            ':name' => 'Test Agent', ':role' => 'agent', ':status' => 'online',
        ]);

        $phoneEnc = \PiiEncryptor::encrypt('01012345678');
        $pdo->prepare(
            'INSERT INTO customers (id, name, phone, phone_hash)
             VALUES (:id, :name, :phone, :phash)'
        )->execute([
            ':id' => $this->customerId,
            ':name' => '김테스트',
            ':phone' => $phoneEnc,
            ':phash' => \PiiEncryptor::phoneHash('01012345678'),
        ]);

        $pdo->prepare(
            'INSERT INTO chat_rooms (id, customer_id, agent_id, inquiry_type, status, channel, subject, priority_score)
             VALUES (:id, :cid, :aid, :inq, :st, :ch, :sub, 75)'
        )->execute([
            ':id' => $this->roomId,
            ':cid' => $this->customerId,
            ':aid' => $this->agentId,
            ':inq' => '인터넷',
            ':st' => 'active',
            ':ch' => 'web',
            ':sub' => '5G 결합',
        ]);

        $base = strtotime('-6 minutes');
        for ($i = 0; $i < 4; $i++) {
            $msgId = sprintf('55555555-5555-4555-8555-%012d', $i);
            $senderType = $i % 2 === 0 ? 'customer' : 'agent';
            $senderId = $senderType === 'customer' ? $this->customerId : $this->agentId;
            $pdo->prepare(
                'INSERT INTO chat_messages (id, room_id, sender_type, sender_id, content, source, created_at)
                 VALUES (:id, :rid, :st, :sid, :content, \'manual\', FROM_UNIXTIME(:ts))'
            )->execute([
                ':id' => $msgId,
                ':rid' => $this->roomId,
                ':st' => $senderType,
                ':sid' => $senderId,
                ':content' => "테스트 메시지 {$i}",
                ':ts' => $base + ($i * 90),
            ]);
        }
    }

    private function agentToken(): string
    {
        require_once dirname(__DIR__, 2) . '/includes/util/JwtHelper.php';
        return \JwtHelper::encode([
            'sub'  => $this->agentId,
            'role' => 'agent',
            'name' => 'Test Agent',
        ], 3600);
    }

    public function test_consults_close_creates_crm_and_schedules(): void
    {
        $res = $this->api('POST', '/consults/close', [
            'roomId'  => $this->roomId,
            'agentId' => $this->agentId,
            'feedback' => ['rating' => 4, 'memo' => '후속 콜 필요'],
        ], $this->agentToken());

        $this->assertTrue($res->isSuccess(), $res->body['error']['message'] ?? '');
        $data = $res->body['data'];
        $this->assertNotEmpty($data['consultNo']);
        $this->assertNotEmpty($data['scheduleIds']);
        $this->assertGreaterThanOrEqual(250, $data['ai']['summaryLength']);

        $pdo = $this->ensureSchema();
        $room = $pdo->query("SELECT crm_save_status FROM chat_rooms WHERE id = '{$this->roomId}'")->fetch();
        $this->assertSame('saved', $room['crm_save_status']);
    }

    public function test_admin_stats_overview_requires_admin_role(): void
    {
        $agentRes = $this->api('GET', '/admin/stats/overview', null, $this->agentToken());
        $this->assertFalse($agentRes->isSuccess());
        $this->assertSame(403, $agentRes->httpCode);

        $adminRes = $this->api('GET', '/admin/stats/overview', null, $this->adminToken());
        $this->assertTrue($adminRes->isSuccess());
        $this->assertArrayHasKey('kpis', $adminRes->body['data']);
    }
}
