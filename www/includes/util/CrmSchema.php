<?php
declare(strict_types=1);

require_once __DIR__ . '/../../migrations/lib.php';

final class CrmSchema
{
    public static function legacyCustomerTable(PDO $pdo): string
    {
        // Live legacy CRM (install.php / consult.php): customers with customer_no.
        // Must win over empty crm_customers from V3.0.1 when V0.0 rename was not run.
        if (acep_is_legacy_crm($pdo)) {
            return 'customers';
        }
        // V0.0 rename or acep profile: crm_customers = legacy BIGINT, customers = ACEP UUID.
        if (self::acepCustomerTable($pdo) !== null
            && acep_table_exists($pdo, 'crm_customers')
            && acep_column_exists($pdo, 'crm_customers', 'customer_no')) {
            return 'crm_customers';
        }
        if (acep_table_exists($pdo, 'customers')
            && acep_column_exists($pdo, 'customers', 'customer_no')) {
            return 'customers';
        }
        if (acep_table_exists($pdo, 'crm_customers')) {
            return 'crm_customers';
        }
        return 'customers';
    }

    public static function hasCrmTables(PDO $pdo): bool
    {
        return acep_table_exists($pdo, 'consults')
            && acep_table_exists($pdo, self::legacyCustomerTable($pdo));
    }

    /** ACEP UUID customers (phone_hash) — chat_rooms FK target when not legacy BIGINT chat. */
    public static function acepCustomerTable(PDO $pdo): ?string
    {
        if (acep_table_exists($pdo, 'customers')
            && acep_column_exists($pdo, 'customers', 'phone_hash')
            && !acep_column_exists($pdo, 'customers', 'customer_no')) {
            return 'customers';
        }
        return null;
    }

    public static function acepCustomersAvailable(PDO $pdo): bool
    {
        return self::acepCustomerTable($pdo) !== null;
    }
}
