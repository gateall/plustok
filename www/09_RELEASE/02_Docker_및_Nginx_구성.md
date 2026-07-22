# PlusTok ACEP — Docker 및 Nginx 구성

> **프로젝트**: PlusTok Enterprise (ACEP)
> **Version**: 1.0.0
> **작성일**: 2026-07-21
> **Audience**: Operator, DevOps, Release Manager, Architect
> **상위 문서**: [00_PROJECT_MASTER.md](../00_PROJECT_MASTER.md)
> **STEP**: 8 — Release & Deployment

## 문서 개요

| 항목 | 내용 |
|------|------|
| 목적 | ACEP Full Stack Docker Compose, Dockerfiles, Nginx default.conf, healthcheck, local/prod 차이 |
| 선행 | [03_시스템아키텍처 §2](../03_SYSTEM/03_시스템아키텍처.md), [01_배포_아키텍처](01_배포_아키텍처_및_환경.md) |
| 대상 | DevOps, Backend Developer |

---

## 1. Docker 아키텍처 개요

ACEP Docker 스택은 6개 서비스로 구성된다. Cafe24 PATH A와 달리 Node Chat Server, Redis, Nginx edge를 포함한다.

| Service | Role | Image Base |
|---------|------|------------|
| acep-nginx | TLS termination, routing | nginx:1.27-alpine |
| acep-static | React SPA build | node:20-alpine → nginx |
| acep-backend | PHP 8.4 REST API | php:8.4-fpm-alpine |
| acep-chat-server | Socket.io WebSocket | node:20-alpine |
| acep-mariadb | Primary database | mariadb:10.6 |
| acep-redis | Cache, rate limit, pub/sub | redis:7-alpine |

### 1.1 네트워크 분리

| Network | Attached Services | External Access |
|---------|-------------------|-----------------|
| acep-public | acep-nginx | Yes (443, 80) |
| acep-app | nginx, backend, chat, static | No |
| acep-data | backend, chat, mariadb, redis | No (internal) |

---

## 2. docker-compose.yml (Production)

아래는 ACEP Production 전체 스택 예시이다. 실제 배포 시 `.env` 값을 환경에 맞게 설정한다.

```yaml
version: "3.9"

# PlusTok ACEP — Production Docker Compose
# Ref: 03_시스템아키텍처 §2, 09_RELEASE/02_Docker_및_Nginx_구성.md

name: acep-prod

networks:
  acep-public:
    driver: bridge
  acep-app:
    driver: bridge
    internal: false
  acep-data:
    driver: bridge
    internal: true

volumes:
  acep_mariadb_data:
  acep_redis_data:
  acep_uploads:
  acep_logs:
  acep_nginx_certs:

services:
  acep-nginx:
    image: nginx:1.27-alpine
    container_name: acep-nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      - ./nginx/certs:/etc/nginx/certs:ro
      - acep_logs:/var/log/nginx
    depends_on:
      acep-backend:
        condition: service_healthy
      acep-chat-server:
        condition: service_healthy
      acep-static:
        condition: service_started
    networks:
      - acep-public
      - acep-app
    healthcheck:
      test: ["CMD", "wget", "-qO-", "http://localhost/health"]
      interval: 30s
      timeout: 5s
      retries: 3

  acep-static:
    image: acep/frontend:latest
    build:
      context: ../frontend
      dockerfile: ../docker/frontend/Dockerfile
    container_name: acep-static
    restart: unless-stopped
    networks:
      - acep-app
    expose:
      - "8080"

  acep-backend:
    image: acep/backend:php8.4
    build:
      context: ..
      dockerfile: docker/php/Dockerfile
    container_name: acep-backend
    restart: unless-stopped
    env_file:
      - ../.env
    environment:
      APP_ENV: production
      APP_DEBUG: "false"
      DB_HOST: acep-mariadb
      REDIS_HOST: acep-redis
    volumes:
      - acep_uploads:/var/acep/uploads
      - acep_logs:/var/acep/logs
    depends_on:
      acep-mariadb:
        condition: service_healthy
      acep-redis:
        condition: service_healthy
    networks:
      - acep-app
      - acep-data
    expose:
      - "8081"
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8081/api/v1/system/health"]
      interval: 30s
      timeout: 10s
      retries: 5
      start_period: 40s

  acep-chat-server:
    image: acep/chat-server:latest
    build:
      context: ../chat-server
      dockerfile: ../docker/chat-server/Dockerfile
    container_name: acep-chat-server
    restart: unless-stopped
    env_file:
      - ../.env
    environment:
      CHAT_SERVER_PORT: "3001"
      REDIS_HOST: acep-redis
      BACKEND_INTERNAL_URL: http://acep-backend:8081
    depends_on:
      acep-redis:
        condition: service_healthy
    networks:
      - acep-app
      - acep-data
    expose:
      - "3001"
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:3001/health"]
      interval: 30s
      timeout: 5s
      retries: 3

  acep-mariadb:
    image: mariadb:10.6
    container_name: acep-mariadb
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_DATABASE:-acep}
      MYSQL_USER: ${DB_USERNAME:-acep_user}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - acep_mariadb_data:/var/lib/mysql
      - ../backend/migrations:/docker-entrypoint-initdb.d:ro
    networks:
      - acep-data
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 30s
      timeout: 10s
      retries: 5
      start_period: 60s

  acep-redis:
    image: redis:7-alpine
    container_name: acep-redis
    restart: unless-stopped
    command: redis-server --appendonly yes --requirepass ${REDIS_PASSWORD}
    volumes:
      - acep_redis_data:/data
    networks:
      - acep-data
    healthcheck:
      test: ["CMD", "redis-cli", "-a", "${REDIS_PASSWORD}", "ping"]
      interval: 30s
      timeout: 5s
      retries: 3
```

### 2.1 docker-compose.dev.yml 차이점

| Setting | dev | prod |
|---------|-----|------|
| Replicas | 1 each | 2 backend/chat (scale profile) |
| APP_DEBUG | true | false |
| TLS | self-signed or HTTP only | Let's Encrypt certs |
| Volume mounts | source code bind mount | image only |
| Ports exposed | 8080, 3001 direct | only 443/80 via nginx |
| AI keys | sandbox/mock | live |

### 2.2 Dev Compose Quick Start

```bash
cd www/docker
cp ../.env.example ../.env
# Edit .env — set DB_PASSWORD, JWT_SECRET, AI keys
docker compose -f docker-compose.dev.yml up -d --build
docker compose -f docker-compose.dev.yml ps
curl http://localhost:8080/api/v1/system/health
```

---

## 3. Dockerfile Reference

### 3.1 PHP Backend (docker/php/Dockerfile)

```dockerfile
FROM php:8.4-fpm-alpine
RUN apk add --no-cache libzip-dev icu-dev oniguruma-dev \
    && docker-php-ext-install pdo_mysql zip intl mbstring
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/acep
COPY backend/ ./
RUN composer install --no-dev --optimize-autoloader
EXPOSE 8081
CMD ["php", "-S", "0.0.0.0:8081", "-t", "public"]
```

### 3.2 Chat Server (docker/chat-server/Dockerfile)

```dockerfile
FROM node:20-alpine AS builder
WORKDIR /app
COPY chat-server/package*.json ./
RUN npm ci
COPY chat-server/ ./
RUN npm run build

FROM node:20-alpine
WORKDIR /app
COPY --from=builder /app/dist ./dist
COPY --from=builder /app/node_modules ./node_modules
COPY chat-server/package.json ./
EXPOSE 3001
HEALTHCHECK CMD curl -f http://localhost:3001/health || exit 1
CMD ["node", "dist/server.js"]
```

### 3.3 Frontend Static (docker/frontend/Dockerfile)

```dockerfile
FROM node:20-alpine AS builder
WORKDIR /app
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

FROM nginx:1.27-alpine
COPY --from=builder /app/dist /usr/share/nginx/html
COPY docker/frontend/nginx-spa.conf /etc/nginx/conf.d/default.conf
EXPOSE 8080
CMD ["nginx", "-g", "daemon off;"]
```

---

## 4. Nginx default.conf

```nginx
upstream acep_backend {
    least_conn;
    server acep-backend:8081;
}

upstream acep_chat {
    ip_hash;
    server acep-chat-server:3001;
}

upstream acep_static {
    server acep-static:8080;
}

server {
    listen 443 ssl http2;
    server_name acep.example.com;

    ssl_certificate     /etc/nginx/certs/fullchain.pem;
    ssl_certificate_key /etc/nginx/certs/privkey.pem;
    ssl_protocols       TLSv1.3;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    location /health {
        access_log off;
        return 200 'ok';
        add_header Content-Type text/plain;
    }

    location /api/v1/ {
        proxy_pass http://acep_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Request-Id $request_id;
        proxy_read_timeout 60s;
    }

    location /socket.io/ {
        proxy_pass http://acep_chat;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 86400;
    }

    location / {
        proxy_pass http://acep_static;
        proxy_set_header Host $host;
    }
}

server {
    listen 80;
    server_name acep.example.com;
    return 301 https://$host$request_uri;
}
```

---

## 5. Healthcheck Reference

| Service | Endpoint | Expected | Interval |
|---------|----------|----------|----------|
| acep-nginx | GET /health | 200 ok | 30s |
| acep-backend | GET /api/v1/system/health | JSON success:true | 30s |
| acep-chat-server | GET /health | 200 | 30s |
| acep-static | GET / | 200 HTML | on start |
| acep-mariadb | healthcheck.sh | connect OK | 30s |
| acep-redis | redis-cli ping | PONG | 30s |

### 5.1 Healthcheck Troubleshooting

| Symptom | Likely Cause | Fix |
|---------|--------------|-----|
| backend unhealthy | DB not ready | Wait mariadb healthy, check DB_* env |
| chat unhealthy | Redis auth fail | Verify REDIS_PASSWORD |
| nginx 502 | upstream down | docker compose logs acep-backend |
| WS disconnect loop | ip_hash missing | Check nginx upstream acep_chat |

---

## 6. Local vs Production

### 6.1 Environment Variables (.env)

| Variable | Local Example | Production |
|----------|---------------|------------|
| APP_ENV | local | production |
| APP_URL | http://localhost:8080 | https://acep.example.com |
| DB_HOST | acep-mariadb | acep-mariadb |
| REDIS_HOST | acep-redis | acep-redis |
| AI_CLAUDE_API_KEY | sk-ant-test... | live key |
| JWT_SECRET | dev-secret-change-me | openssl rand -base64 32 |

### 6.2 Volume & Data Persistence

| Volume | Purpose | Backup |
|--------|---------|--------|
| acep_mariadb_data | DB files | mysqldump daily |
| acep_redis_data | AOF persistence | optional |
| acep_uploads | User uploads | rsync to S3 |
| acep_logs | App/nginx logs | logrotate |

---

## 7. Docker Deploy Procedure

| Step | Command | Verify ☐ |
|------|---------|:--------:|
| 1 | `git checkout v1.0.0-mvp` | tag correct |
| 2 | `docker compose build --no-cache` | images built |
| 3 | Run DB migrations | schema current |
| 4 | `docker compose up -d` | all healthy |
| 5 | curl health endpoints | 200 OK |
| 6 | Smoke SMK-01~07 | PASS |

---

## 8. Scaling Profiles

```bash
# Scale chat + backend to 2 replicas (requires compose override)
docker compose -f docker-compose.yml -f docker-compose.scale.yml up -d --scale acep-backend=2 --scale acep-chat-server=2
```

Nginx upstream must list all replica hostnames or use Docker DNS service name with load balancing.

---

## 10. docker-compose.dev.yml (Full Example)

Local development — source bind mounts, debug enabled, ports exposed.

```yaml
version: "3.9"
name: acep-dev

services:
  acep-nginx:
    image: nginx:1.27-alpine
    ports:
      - "8080:80"
    volumes:
      - ./nginx/default.dev.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - acep-backend
      - acep-chat-server
      - acep-static

  acep-static:
    build:
      context: ../frontend
      dockerfile: ../docker/frontend/Dockerfile
    environment:
      VITE_API_BASE: http://localhost:8080/api/v1

  acep-backend:
    build:
      context: ..
      dockerfile: docker/php/Dockerfile
    env_file: ../.env
    environment:
      APP_ENV: local
      APP_DEBUG: "true"
    volumes:
      - ../backend:/var/acep:cached
    ports:
      - "8081:8081"

  acep-chat-server:
    build:
      context: ../chat-server
      dockerfile: ../docker/chat-server/Dockerfile
    environment:
      APP_ENV: local
    volumes:
      - ../chat-server/src:/app/src:cached
    ports:
      - "3001:3001"

  acep-mariadb:
    image: mariadb:10.6
    environment:
      MYSQL_ROOT_PASSWORD: devroot
      MYSQL_DATABASE: acep
      MYSQL_USER: acep
      MYSQL_PASSWORD: devpass
    ports:
      - "3306:3306"
    volumes:
      - ../backend/migrations:/docker-entrypoint-initdb.d:ro

  acep-redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
```

### 10.1 Dev vs Prod Quick Compare

| Aspect | dev | prod |
|--------|-----|------|
| Source code | bind mount | image only |
| APP_DEBUG | true | false |
| Ports | 8080, 8081, 3001, 3306 exposed | 443 only |
| TLS | none (HTTP) | TLS 1.3 |
| Secrets | .env local | .env + secrets manager |

---

## 11. SSL Certificate (Docker + certbot)

| Step | Command | ☐ |
|------|---------|:-:|
| 1 | Point DNS A record to VPS | ☐ |
| 2 | `certbot certonly --webroot -w /var/www/certbot -d acep.example.com` | ☐ |
| 3 | Copy certs to docker/nginx/certs/ | ☐ |
| 4 | `docker compose exec acep-nginx nginx -s reload` | ☐ |
| 5 | Verify SSL Labs grade | ☐ |

### 11.1 Cert Renewal Cron

```bash
0 3 * * * certbot renew --quiet && docker compose -f /opt/acep/docker-compose.yml exec acep-nginx nginx -s reload
```

## 12. Log Management

| Log | Location | Rotation |
|-----|----------|----------|
| Nginx access | acep_logs volume | daily, 14d |
| PHP application | /var/acep/logs | 100MB x 5 |
| Chat Server | stdout → docker logs | docker log driver |
| MariaDB slow | acep-mariadb config | 7d |

```bash
docker compose logs -f acep-backend --tail=100
docker compose logs -f acep-chat-server --since=1h
```

## 13. Docker Troubleshooting Matrix

| Error | Diagnosis | Fix |
|-------|-----------|-----|
| `dependency failed to start` | mariadb not healthy | Check DB_PASSWORD, logs |
| `502 Bad Gateway` | backend down | curl acep-backend:8081/health |
| WS 400 Bad Request | nginx upgrade headers | Verify default.conf socket.io block |
| Redis NOAUTH | wrong password | Match .env REDIS_PASSWORD |
| Frontend blank | static build fail | rebuild acep-static image |
| DB connection refused | wrong network | Ensure backend on acep-data network |

## 14. Resource Limits (Production Recommendation)

| Service | CPU | Memory |
|---------|-----|--------|
| acep-nginx | 0.5 | 256MB |
| acep-backend | 1.0 | 512MB |
| acep-chat-server | 1.0 | 512MB |
| acep-mariadb | 2.0 | 2GB |
| acep-redis | 0.5 | 256MB |
| acep-static | 0.25 | 128MB |

---

## 9. 관련 문서

- [01_배포_아키텍처_및_환경.md](01_배포_아키텍처_및_환경.md)
- [03_시스템아키텍처 §2](../03_SYSTEM/03_시스템아키텍처.md)
- [05_릴리스_런북.md](05_릴리스_런북.md) §4 Docker deploy
- [_RELEASE_INDEX.md](_RELEASE_INDEX.md)

**문서 끝**
