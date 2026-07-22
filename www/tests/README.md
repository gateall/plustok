# ACEP Tests

## Backend (PHPUnit)

**요구:** PHP 8.2+, Composer, MariaDB 테스트 DB

```bash
# 1. 테스트 DB 생성
mysql -e "CREATE DATABASE acep_test CHARACTER SET utf8mb4;"

# 2. 설정
cp config/database.test.php.example config/database.test.php
# host/user/pass 수정

# 3. 의존성 & 실행
composer install
composer test
# 또는: vendor/bin/phpunit
```

## Frontend (Vitest)

```bash
cd frontend
npm install
npm run test
```
