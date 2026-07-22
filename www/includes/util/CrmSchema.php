<?php
declare(strict_types=1);

require_once __DIR__ . '/../../migrations/lib.php';

final class CrmSchema
{
    public static function legacyCustomerTable(PDO $pdo): string
    {
        if (acep_table_exists($pdo, 'crm_customers')) {
            return 'crm_customers';
        }
        if (acep_is_legacy_crm($pdo)) {
            return 'customers';
        }
        return 'crm_customers';
    }

    public static function hasCrmTables(PDO $pdo): bool
    {
        return acep_table_exists($pdo, 'consults')
            && acep_table_exists($pdo, self::legacyCustomerTable($pdo));
    }
}
