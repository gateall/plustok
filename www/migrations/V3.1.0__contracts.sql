-- ACEP V3.1.0 — Admin Contract Management
-- 계약 원장 + 결제 원장(별도 테이블 — 계약 삭제와 무관하게 결제 이력 보존).
-- 상품 카탈로그 테이블이 아직 없는 베이스라인이라 product_name은 비정규화 텍스트로 저장한다
-- (제품 테이블 도입 시 product_id FK로 마이그레이션 예정, RFC 대상).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS contracts (
    id                  VARCHAR(36)     NOT NULL,
    contract_no         VARCHAR(30)     NOT NULL,
    title               VARCHAR(200)    NOT NULL,
    customer_id         VARCHAR(36)     NOT NULL,
    site_id             BIGINT          NULL,
    product_name        VARCHAR(150)    NULL,
    manager_id          VARCHAR(36)     NULL,
    total_amount        DECIMAL(14,2)   NOT NULL DEFAULT 0,
    status              ENUM('draft','review','sent','signature_pending','signed','active','completed','on_hold','cancelled','expired','archived')
                        NOT NULL DEFAULT 'draft',
    document_status     ENUM('none','sent','signed') NOT NULL DEFAULT 'none',
    start_date          DATE            NULL,
    end_date            DATE            NULL,
    signed_at           DATETIME        NULL,
    signer_name         VARCHAR(100)    NULL,
    cancel_reason_code  VARCHAR(50)     NULL,
    cancel_reason       VARCHAR(255)    NULL,
    cancelled_at        DATETIME        NULL,
    archived_at         DATETIME        NULL,
    notes               TEXT            NULL,
    created_at          DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at          DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    deleted_at          DATETIME(3)     NULL COMMENT '소프트 삭제 — draft/무서명/무결제일 때만',
    PRIMARY KEY (id),
    UNIQUE KEY uq_contracts_no (contract_no),
    KEY idx_contracts_customer (customer_id),
    KEY idx_contracts_site (site_id),
    KEY idx_contracts_manager (manager_id),
    KEY idx_contracts_status (status),
    KEY idx_contracts_created (created_at),
    KEY idx_contracts_deleted (deleted_at),
    CONSTRAINT fk_contracts_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='계약 원장';

-- 결제/환불 원장 — 계약이 소프트 삭제되어도 별도 테이블이라 보존됨.
-- paid_amount/outstanding_amount는 이 테이블 SUM으로 서버가 매 요청 계산(비정규화 컬럼 없음 — drift 방지).
CREATE TABLE IF NOT EXISTS contract_payments (
    id             VARCHAR(36)   NOT NULL,
    contract_id    VARCHAR(36)   NOT NULL,
    amount         DECIMAL(14,2) NOT NULL,
    type           ENUM('payment','refund') NOT NULL DEFAULT 'payment',
    paid_at        DATETIME      NOT NULL,
    memo           VARCHAR(255)  NULL,
    created_by     VARCHAR(36)   NULL,
    created_at     DATETIME(3)   NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    KEY idx_contract_payments_contract (contract_id),
    CONSTRAINT fk_contract_payments_contract
        FOREIGN KEY (contract_id) REFERENCES contracts(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='계약 결제/환불 원장 — 계약 삭제와 독립 보존';
