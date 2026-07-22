<?php
declare(strict_types=1);

require_once __DIR__ . '/../api_envelope.php';
require_once __DIR__ . '/../util/CrmSchema.php';
require_once __DIR__ . '/CrmAiPipeline.php';

require_once __DIR__ . '/../util/PiiEncryptor.php';

final class CrmCloseService
{
    private const MIN_DURATION_SEC = 300;
    private const MIN_MESSAGE_COUNT = 3;
    private const RETRY_DELAYS = [0, 1, 2, 4];

    public function __construct(
        private ChatRoomRepository $rooms,
        private ChatMessageRepository $messages,
        private CustomerRepository $customers,
        private ConsultRepository $consults,
        private CustomerBridgeRepository $bridge,
        private ScheduleRepository $schedules,
        private CrmAiPipeline $ai,
        private PDO $pdo,
    ) {
    }

    /**
     * @param array<string,mixed>|null $feedback
     * @return array<string,mixed>
     */
    public function execute(
        string $roomId,
        string $agentId,
        string $role,
        ?array $feedback = null,
        bool $force = false,
        ?string $summaryOverride = null,
        bool $closeRoomIfActive = true,
    ): array {
        if (!CrmSchema::hasCrmTables($this->pdo)) {
            acep_error('CRM_SAVE_FAILED', 'CRM 테이블이 준비되지 않았습니다.', 500);
        }

        $room = $this->rooms->findById($roomId);
        if (!$room) {
            acep_error('ROOM_NOT_FOUND', '상담방을 찾을 수 없습니다.', 404);
        }

        if (($room['crm_save_status'] ?? 'pending') === 'saved') {
            return $this->existingSavedResponse($room);
        }

        $this->validateCloseRules($room, $role, $force);

        if ($closeRoomIfActive && $room['status'] !== 'closed') {
            if ($room['status'] !== 'active') {
                acep_error('CRM_ROOM_NOT_ACTIVE', 'active 상태의 상담방만 종료할 수 있습니다.', 400);
            }
            if (!$this->rooms->close($roomId)) {
                acep_error('CRM_SAVE_FAILED', '상담방 종료에 실패했습니다.', 500);
            }
            $room = $this->rooms->findById($roomId) ?? $room;
        } elseif ($room['status'] === 'closed') {
            // retry path — already closed
        } elseif ($room['status'] !== 'active') {
            acep_error('CRM_ROOM_NOT_ACTIVE', 'active 또는 closed 상태의 상담방만 CRM 저장할 수 있습니다.', 400);
        }

        $attempt = 0;
        $lastEx = null;
        foreach (self::RETRY_DELAYS as $delay) {
            if ($delay > 0) {
                sleep($delay);
            }
            try {
                $result = $this->doClose($room, $agentId, $feedback, $summaryOverride);
                $this->rooms->markCrmSaved($roomId, (int)$result['consult_id']);
                return $result;
            } catch (Throwable $e) {
                $lastEx = $e;
                $attempt++;
            }
        }

        $this->rooms->markCrmFailed($roomId);
        if ($lastEx instanceof RuntimeException && str_contains($lastEx->getMessage(), 'AI')) {
            acep_error('CRM_AI_UNAVAILABLE', 'AI 처리에 실패했습니다.', 503);
        }
        acep_error('CRM_SAVE_FAILED', 'CRM 저장에 실패했습니다.', 500);
    }

    /** @param array<string,mixed> $room */
    private function doClose(array $room, string $agentId, ?array $feedback, ?string $summaryOverride): array
    {
        $roomId = (string)$room['id'];
        $this->pdo->beginTransaction();
        try {
            $msgList = array_reverse($this->messages->listByRoom($roomId, 500, null));
            $acepCustomer = $this->customers->findById((string)$room['customer_id']);
            if (!$acepCustomer) {
                throw new RuntimeException('Customer not found');
            }

            $summary = $this->ai->summarize($room, $msgList, $summaryOverride);
            $analysis = $this->ai->analyze($room, $msgList, $summary['text']);
            $legacyCustomerId = $this->bridge->resolveLegacyCustomer($acepCustomer);
            $customerIncomplete = $this->isCustomerIncomplete($acepCustomer);

            $consultId = $this->consults->upsertFromRoom($room, $legacyCustomerId, $summary, $analysis, $feedback);
            $consultNo = $this->consults->getConsultNo($consultId);
            $scheduleIds = $this->schedules->createFollowUps($consultId, $analysis, $consultNo, $customerIncomplete);

            $this->pdo->prepare(
                'UPDATE customers SET consultation_count = consultation_count + 1,
                 updated_at = CURRENT_TIMESTAMP(3)
                 WHERE id = :id AND deleted_at IS NULL'
            )->execute([':id' => (string)$room['customer_id']]);

            $leadScore = (int)$analysis['lead_score'];
            $this->rooms->updatePriorityScore($roomId, $leadScore);

            $this->pdo->commit();

            $savedAt = date('c');
            return [
                'ok'           => true,
                'consultId'    => $consultId,
                'consultNo'    => $consultNo,
                'crmSavedAt'   => $savedAt,
                'scheduleIds'  => $scheduleIds,
                'ai'           => [
                    'summaryLength' => mb_strlen($summary['text']),
                    'leadScore'     => $leadScore,
                    'categoryAi'    => $analysis['category_ai'],
                    'sentiment'     => $analysis['sentiment'],
                ],
                'roomId'       => $roomId,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @param array<string,mixed> $room */
    private function validateCloseRules(array $room, string $role, bool $force): void
    {
        if (empty($room['agent_id'])) {
            acep_error('VALIDATION_ERROR', '배정된 상담원이 없습니다.', 422);
        }

        if ($room['status'] === 'new') {
            acep_error('CRM_ROOM_NOT_ACTIVE', 'active 상태의 상담방만 종료할 수 있습니다.', 400);
        }

        if (!$force) {
            $duration = $this->rooms->conversationDurationSec((string)$room['id']);
            if ($duration < self::MIN_DURATION_SEC) {
                acep_error('CRM_MIN_DURATION', '상담 시간이 5분 미만입니다.', 422);
            }
            $msgCount = $this->rooms->messageCount((string)$room['id']);
            if ($msgCount < self::MIN_MESSAGE_COUNT) {
                acep_error('CRM_MIN_MESSAGES', '메시지가 3건 미만입니다.', 422);
            }
        } elseif (!in_array($role, ['admin', 'operator'], true)) {
            acep_error('FORBIDDEN', 'force 옵션은 admin만 사용할 수 있습니다.', 403);
        }
    }

    /** @param array<string,mixed> $room */
    private function existingSavedResponse(array $room): array
    {
        $consultId = (int)($room['legacy_consult_id'] ?? 0);
        $consultNo = $consultId > 0 ? $this->consults->getConsultNo($consultId) : '';
        if ($consultNo === '' && $consultId > 0) {
            acep_error('CRM_ALREADY_SAVED', 'CRM이 이미 저장되었습니다.', 409);
        }
        if ($consultId <= 0) {
            $existing = $this->consults->findByRoomId((string)$room['id']);
            if (!$existing) {
                acep_error('CRM_ALREADY_SAVED', 'CRM이 이미 저장되었습니다.', 409);
            }
            $consultId = (int)$existing['id'];
            $consultNo = (string)$existing['consult_no'];
        }

        return [
            'ok'          => true,
            'consultId'   => $consultId,
            'consultNo'   => $consultNo,
            'crmSavedAt'  => date('c', strtotime((string)($room['crm_saved_at'] ?? 'now'))),
            'scheduleIds' => [],
            'ai'          => null,
            'roomId'      => (string)$room['id'],
            'idempotent'  => true,
        ];
    }

    /** @param array<string,mixed> $customer */
    private function isCustomerIncomplete(array $customer): bool
    {
        try {
            $phone = PiiEncryptor::decrypt((string)$customer['phone']);
        } catch (Throwable) {
            $phone = (string)$customer['phone'];
        }
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        return strlen($digits) < 9;
    }

    /** @return array<string,mixed> */
    public function getConsult(string $consultNo): array
    {
        $row = $this->consults->findByConsultNo($consultNo);
        if (!$row) {
            acep_error('NOT_FOUND', '상담을 찾을 수 없습니다.', 404);
        }
        return [
            'consultId'   => (int)$row['id'],
            'consultNo'   => (string)$row['consult_no'],
            'status'      => (string)$row['status'],
            'leadScore'   => $row['lead_score'] !== null ? (int)$row['lead_score'] : null,
            'categoryAi'  => $row['category_ai'],
            'sentiment'   => $row['sentiment'],
            'aiSummary'   => $row['ai_summary'],
            'createdAt'   => date('c', strtotime((string)$row['created_at'])),
        ];
    }
}
