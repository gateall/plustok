<?php

declare(strict_types=1);



namespace Tests\Feature;



use Tests\Support\ApiTestCase;



final class ConsultChatTest extends ApiTestCase

{

    protected function migrationProfile(): string

    {

        return 'acep';

    }



    public function test_customer_token_service_issues_jwt(): void

    {

        require_once dirname(__DIR__, 2) . '/includes/services/CustomerTokenService.php';

        require_once dirname(__DIR__, 2) . '/includes/util/JwtHelper.php';



        $svc = new \CustomerTokenService();

        $customerId = '42';

        $issued = $svc->issue($customerId, '테스트고객');



        $this->assertNotEmpty($issued['accessToken']);

        $this->assertSame(ACEP_CUSTOMER_JWT_TTL, $issued['expiresIn']);



        $claims = \JwtHelper::decode($issued['accessToken']);

        $this->assertIsArray($claims);

        $this->assertSame($customerId, $claims['sub']);

        $this->assertSame('customer', $claims['role']);

    }



    /** Production path: UUID chat_rooms + legacy CRM consult → customer_bridge → ACEP UUID */

    public function test_consult_chat_creates_room_via_customer_bridge(): void

    {

        $pdo = $this->ensureSchema();

        require_once dirname(__DIR__, 2) . '/includes/util/CrmSchema.php';



        $pdo->exec(

            "INSERT IGNORE INTO sites (id, site_code, site_name, brand, use_yn)

             VALUES (1, 'test-site', 'Test Site', 'PlusTok', 1)"

        );



        $legacyTable = \CrmSchema::legacyCustomerTable($pdo);

        $pdo->prepare(

            "INSERT INTO {$legacyTable} (customer_no, name, phone, email)

             VALUES (:no, :name, :phone, :email)"

        )->execute([

            ':no'    => 'M202607220001',

            ':name'  => '채팅테스트',

            ':phone' => '01055556666',

            ':email' => 'chat@test.example',

        ]);

        $legacyCustomerId = (int)$pdo->lastInsertId();



        $pdo->prepare(

            'INSERT INTO consults (consult_no, customer_id, site_id, status)

             VALUES (:no, :cid, 1, :status)'

        )->execute([

            ':no'     => 'C202607220001',

            ':cid'    => $legacyCustomerId,

            ':status' => 'new',

        ]);

        $legacyConsultId = (int)$pdo->lastInsertId();



        require_once dirname(__DIR__, 2) . '/includes/consult_chat.php';

        $chat = acep_consult_chat_service($pdo);

        $result = $chat->createRoomForConsult(

            $legacyCustomerId,

            $legacyConsultId,

            '채팅테스트',

            '01055556666',

            'chat@test.example',

            '인터넷',

            '상담 메모입니다',

            'web',

        );



        $this->assertIsArray($result);

        $this->assertNotEmpty($result['roomId']);

        $this->assertNotEmpty($result['accessToken']);

        $this->assertSame(ACEP_CUSTOMER_JWT_TTL, $result['expiresIn']);



        require_once dirname(__DIR__, 2) . '/includes/util/JwtHelper.php';

        $claims = \JwtHelper::decode($result['accessToken']);

        $this->assertNotSame((string)$legacyCustomerId, $claims['sub']);

        $acepCustomerId = (string)$claims['sub'];

        $this->assertMatchesRegularExpression(

            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',

            $acepCustomerId,

        );



        $bridge = $pdo->prepare(

            'SELECT acep_customer_id FROM customer_bridge WHERE legacy_customer_id = :lid LIMIT 1'

        );

        $bridge->execute([':lid' => $legacyCustomerId]);

        $this->assertSame($acepCustomerId, (string)$bridge->fetchColumn());



        $roomRow = $pdo->prepare('SELECT customer_id, legacy_consult_id FROM chat_rooms WHERE id = :id');

        $roomRow->execute([':id' => $result['roomId']]);

        $room = $roomRow->fetch();

        $this->assertSame($acepCustomerId, (string)$room['customer_id']);

        $this->assertSame($legacyConsultId, (int)$room['legacy_consult_id']);



        $roomRes = $this->api('GET', '/chats/' . $result['roomId'], null, $result['accessToken']);

        $this->assertTrue($roomRes->isSuccess(), $roomRes->body['error']['message'] ?? '');

        $this->assertSame($result['roomId'], $roomRes->body['data']['id']);

        $this->assertSame($acepCustomerId, $roomRes->body['data']['customer']['id']);



        $otherAcepId = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';

        require_once dirname(__DIR__, 2) . '/includes/util/PiiEncryptor.php';

        $pdo->prepare(

            'INSERT INTO customers (id, name, phone, phone_hash)

             VALUES (:id, :name, :phone, :phash)'

        )->execute([

            ':id'    => $otherAcepId,

            ':name'  => '다른고객',

            ':phone' => \PiiEncryptor::encrypt('01077778888'),

            ':phash' => \PiiEncryptor::phoneHash('01077778888'),

        ]);

        $otherRoomId = 'bbbbbbbb-bbbb-4ccc-8ddd-eeeeeeeeeeee';

        $pdo->prepare(

            'INSERT INTO chat_rooms (id, customer_id, inquiry_type, status, channel, subject)

             VALUES (:id, :cid, :inquiry, :status, :channel, :subject)'

        )->execute([

            ':id'       => $otherRoomId,

            ':cid'      => $otherAcepId,

            ':inquiry'  => '설치문의',

            ':status'   => 'new',

            ':channel'  => 'web',

            ':subject'  => '설치문의',

        ]);



        $forbidden = $this->api(

            'GET',

            '/chats/' . $otherRoomId,

            null,

            $result['accessToken'],

        );

        $this->assertFalse($forbidden->isSuccess());

        $this->assertSame(403, $forbidden->httpCode);

    }



    /** Bridge reuses existing ACEP customer when same legacy id is submitted again. */

    public function test_consult_chat_reuses_bridge_mapping(): void

    {

        $pdo = $this->ensureSchema();

        require_once dirname(__DIR__, 2) . '/includes/util/CrmSchema.php';

        require_once dirname(__DIR__, 2) . '/includes/consult_chat.php';



        $legacyTable = \CrmSchema::legacyCustomerTable($pdo);

        $pdo->prepare(

            "INSERT INTO {$legacyTable} (customer_no, name, phone, email)

             VALUES (:no, :name, :phone, :email)"

        )->execute([

            ':no'    => 'M202607220002',

            ':name'  => '브릿지재사용',

            ':phone' => '01033334444',

            ':email' => 'bridge@test.example',

        ]);

        $legacyCustomerId = (int)$pdo->lastInsertId();



        $chat = acep_consult_chat_service($pdo);

        $first = $chat->createRoomForConsult(

            $legacyCustomerId,

            0,

            '브릿지재사용',

            '01033334444',

            'bridge@test.example',

            '인터넷',

            null,

            'web',

        );

        $second = $chat->createRoomForConsult(

            $legacyCustomerId,

            0,

            '브릿지재사용',

            '01033334444',

            'bridge@test.example',

            '인터넷',

            null,

            'web',

        );



        $this->assertIsArray($first);

        $this->assertIsArray($second);



        $bridgeCount = (int)$pdo->query(

            'SELECT COUNT(*) FROM customer_bridge WHERE legacy_customer_id = ' . $legacyCustomerId

        )->fetchColumn();

        $this->assertSame(1, $bridgeCount);



        require_once dirname(__DIR__, 2) . '/includes/util/JwtHelper.php';

        $firstSub = (string)(\JwtHelper::decode($first['accessToken'])['sub'] ?? '');

        $secondSub = (string)(\JwtHelper::decode($second['accessToken'])['sub'] ?? '');

        $this->assertSame($firstSub, $secondSub);

    }



    /** crm_customers exists (V3.0.1) but live data stays in legacy table — consult.php shape. */

    public function test_consult_chat_resolves_customer_when_crm_customers_table_also_exists(): void

    {

        $pdo = $this->ensureSchema();

        require_once dirname(__DIR__, 2) . '/includes/util/CrmSchema.php';



        $legacyTable = \CrmSchema::legacyCustomerTable($pdo);

        $pdo->prepare(

            "INSERT INTO {$legacyTable} (customer_no, name, phone, email)

             VALUES (:no, :name, :phone, :email)"

        )->execute([

            ':no'    => 'M202607220099',

            ':name'  => '공존테스트',

            ':phone' => '01099998888',

            ':email' => 'coexist@test.example',

        ]);

        $legacyCustomerId = (int)$pdo->lastInsertId();



        $pdo->prepare(

            'INSERT INTO consults (consult_no, customer_id, site_id, status)

             VALUES (:no, :cid, 1, :status)'

        )->execute([

            ':no'     => 'C202607220099',

            ':cid'    => $legacyCustomerId,

            ':status' => 'new',

        ]);

        $legacyConsultId = (int)$pdo->lastInsertId();



        require_once dirname(__DIR__, 2) . '/includes/consult_chat.php';

        $chat = acep_consult_chat_service($pdo);

        $result = $chat->createRoomForConsult(

            $legacyCustomerId,

            $legacyConsultId,

            '공존테스트',

            '01099998888',

            'coexist@test.example',

            '인터넷',

            null,

            'web',

        );



        $this->assertIsArray($result);

        $this->assertNotEmpty($result['roomId']);



        $roomRow = $pdo->prepare('SELECT customer_id FROM chat_rooms WHERE id = :id');

        $roomRow->execute([':id' => $result['roomId']]);

        $storedCustomerId = (string)$roomRow->fetchColumn();

        $this->assertNotSame((string)$legacyCustomerId, $storedCustomerId);

        $this->assertMatchesRegularExpression(

            '/^[0-9a-f-]{36}$/i',

            $storedCustomerId,

        );

    }

}


