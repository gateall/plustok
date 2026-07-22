# 🚀 PlusTok V3.0 — Phase 4 구현 시작 명령어 (최종)

**프로젝트:** PlusTok V1.0 → V3.0 AI Customer Engagement Platform  
**Phase:** 4 (배포 & 운영)  
**기간:** 2주 (Day 36~49)  
**대상:** Cursor (또는 다른 AI 개발 도구)  
**작성일:** 2026-07-21  
**전제조건:** Phase 1~3 완료 ✅

---

## 📍 Phase 4 개요

### 목표
- ✅ Docker 컨테이너화 & 배포
- ✅ CI/CD 파이프라인 구축
- ✅ 모니터링 & 로깅 설정
- ✅ 성능 최적화 & 테스트
- ✅ 운영 가이드 작성
- ✅ **Go Live!** 🚀

### 산출물
```
DevOps:
  ├─ Docker 이미지 (Backend + Frontend)
  ├─ Docker Compose (로컬 개발)
  ├─ Kubernetes 매니페스트 (프로덕션)
  ├─ GitHub Actions CI/CD
  ├─ AWS 인프라 (ECS, RDS, ElastiCache)
  └─ 모니터링 대시보드 (Prometheus + Grafana)

운영:
  ├─ 배포 가이드
  ├─ 트러블슈팅 가이드
  ├─ 성능 튜닝 가이드
  ├─ 보안 체크리스트
  ├─ 백업 & 복구 가이드
  └─ On-Call 가이드
```

### 일정
```
Week 1 (Day 36~41):
  └─ Docker & CI/CD 구축 (3일)
  └─ 모니터링 설정 (2일)
  └─ Staging 배포 (1일)

Week 2 (Day 42~49):
  └─ QA & 성능 테스트 (3일)
  └─ 보안 검수 (1일)
  └─ 프로덕션 배포 준비 (2일)
  └─ Go Live! (1일)
```

---

## 🎯 Cursor에 전달할 명령어

### Step 1: 문서 읽기 (Day 36)

```markdown
Phase 4 (최종 단계)를 시작하겠습니다!

다음 문서들을 읽어주세요 (2.5시간):

1. www/09_DEVELOPMENT/01_개발WBS.md
   └─ Phase 1~4 전체 일정 확인

2. www/09_DEVELOPMENT/02_테스트시나리오.md
   └─ 100+ 테스트 케이스 이해

3. www/09_DEVELOPMENT/03_배포운영.md
   └─ 배포 프로세스, CI/CD, 모니터링 이해

읽고 난 후 "Phase 4 Step 1 완료" 보고해주세요.
```

---

### Step 2: Docker & CI/CD 구축 (Day 36~38)

```markdown
🎯 Task: Docker 컨테이너화 & GitHub Actions CI/CD

📚 참고: www/09_DEVELOPMENT/03_배포운영.md

Day 36: Docker 이미지 생성

Backend Dockerfile:
```dockerfile
# Node.js 베이스 이미지
FROM node:18-alpine

WORKDIR /app

# 의존성 설치
COPY package*.json ./
RUN npm ci --only=production

# 소스 복사
COPY . .

# 포트
EXPOSE 3000

# 시작 명령
CMD ["npm", "start"]
```

Frontend Dockerfile:
```dockerfile
# Build stage
FROM node:18-alpine as builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# Production stage
FROM nginx:alpine
COPY --from=builder /app/dist /usr/share/nginx/html
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
```

Docker Compose (로컬 개발):
```yaml
version: '3.8'

services:
  postgres:
    image: postgres:14
    environment:
      POSTGRES_DB: plusok
      POSTGRES_PASSWORD: dev_password
    ports:
      - "5432:5432"

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"

  backend:
    build:
      context: .
      dockerfile: Dockerfile.backend
    depends_on:
      - postgres
      - redis
    environment:
      DATABASE_URL: postgresql://user:pass@postgres:5432/plusok
      REDIS_URL: redis://redis:6379
      NODE_ENV: development
    ports:
      - "3000:3000"

  frontend:
    build:
      context: .
      dockerfile: Dockerfile.frontend
    ports:
      - "3001:80"

volumes:
  postgres_data:
```

[ ] Dockerfile 작성 (Backend + Frontend)
[ ] Docker Compose 작성
[ ] 이미지 빌드 테스트
[ ] 로컬에서 docker-compose up 성공

Day 37: GitHub Actions CI/CD

.github/workflows/ci-cd.yml:
```yaml
name: CI/CD

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: '18'
      
      - name: Install dependencies
        run: npm ci
      
      - name: Run linting
        run: npm run lint
      
      - name: Run tests
        run: npm test
      
      - name: Run coverage
        run: npm run coverage
      
      - name: Upload coverage
        uses: codecov/codecov-action@v3

  build:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    steps:
      - uses: actions/checkout@v3
      
      - name: Build Docker image
        run: docker build -t plusok-backend:latest .
      
      - name: Push to ECR
        env:
          AWS_ACCESS_KEY_ID: ${{ secrets.AWS_ACCESS_KEY_ID }}
          AWS_SECRET_ACCESS_KEY: ${{ secrets.AWS_SECRET_ACCESS_KEY }}
        run: |
          aws ecr get-login-password --region us-east-1 | docker login --username AWS --password-stdin $ECR_REGISTRY
          docker tag plusok-backend:latest $ECR_REGISTRY/plusok-backend:latest
          docker push $ECR_REGISTRY/plusok-backend:latest

  deploy-staging:
    needs: build
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to ECS Staging
        run: |
          aws ecs update-service --cluster staging --service plusok-backend --force-new-deployment
```

[ ] GitHub Actions 워크플로우 작성
[ ] 린팅, 테스트, 빌드 자동화
[ ] Docker 이미지를 ECR에 푸시
[ ] Staging으로 자동 배포

Day 38: Kubernetes 매니페스트 (프로덕션)

k8s/backend-deployment.yaml:
```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: plusok-backend
spec:
  replicas: 3
  selector:
    matchLabels:
      app: plusok-backend
  template:
    metadata:
      labels:
        app: plusok-backend
    spec:
      containers:
      - name: backend
        image: plusok-backend:latest
        ports:
        - containerPort: 3000
        env:
        - name: DATABASE_URL
          valueFrom:
            secretKeyRef:
              name: db-secret
              key: url
        - name: REDIS_URL
          valueFrom:
            secretKeyRef:
              name: redis-secret
              key: url
        resources:
          requests:
            memory: "256Mi"
            cpu: "250m"
          limits:
            memory: "512Mi"
            cpu: "500m"
        livenessProbe:
          httpGet:
            path: /health
            port: 3000
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          httpGet:
            path: /health
            port: 3000
          initialDelaySeconds: 10
          periodSeconds: 5
```

[ ] Kubernetes 매니페스트 작성
[ ] Health check 엔드포인트 구현
[ ] 리소스 요청/제한 설정
[ ] 오토스케일링 정책

파일 구조:
```
project/
├── Dockerfile.backend
├── Dockerfile.frontend
├── docker-compose.yml
├── .github/workflows/
│   └── ci-cd.yml
├── k8s/
│   ├── backend-deployment.yaml
│   ├── frontend-deployment.yaml
│   ├── postgres-statefulset.yaml
│   ├── redis-statefulset.yaml
│   ├── service.yaml
│   ├── ingress.yaml
│   └── secrets.yaml
```

✅ 검증:
  [ ] docker-compose up 성공?
  [ ] CI/CD 파이프라인 자동화?
  [ ] Staging 자동 배포?
  [ ] Kubernetes 매니페스트 유효?
```

---

### Step 3: 모니터링 & 로깅 (Day 39~40)

```markdown
🎯 Task: 모니터링 대시보드 & 로깅 설정

📚 참고: www/09_DEVELOPMENT/03_배포운영.md

Day 39: Prometheus + Grafana

prometheus.yml:
```yaml
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: 'plusok-backend'
    static_configs:
      - targets: ['localhost:3000']
  
  - job_name: 'postgres'
    static_configs:
      - targets: ['localhost:5432']
  
  - job_name: 'redis'
    static_configs:
      - targets: ['localhost:6379']
```

Grafana 대시보드:
  [ ] API 응답 시간 (P95, P99)
  [ ] 에러율 (4xx, 5xx)
  [ ] DB 연결 수
  [ ] WebSocket 동시 연결
  [ ] AI API 사용량 & 비용
  [ ] 메모리 & CPU 사용률

Day 40: Logging & 알림

ELK Stack (Elasticsearch + Logstash + Kibana):
  [ ] 모든 서버 로그 수집
  [ ] 구조화된 로깅 (JSON)
  [ ] 로그 검색 & 분석
  [ ] 대시보드 구성

알림 규칙:
  [ ] 에러율 > 1% → Slack
  [ ] 응답시간 P99 > 1초 → Slack
  [ ] DB CPU > 80% → PagerDuty
  [ ] 디스크 사용률 > 90% → PagerDuty

파일 구조:
```
monitoring/
├── prometheus.yml
├── docker-compose-monitoring.yml
├── grafana/
│   └── dashboards/
│       ├── api-performance.json
│       ├── infrastructure.json
│       └── ai-metrics.json
└── alerts/
    └── alert-rules.yml
```

✅ 검증:
  [ ] Grafana 대시보드 표시?
  [ ] 메트릭 수집 정상?
  [ ] 알림 규칙 작동?
  [ ] 로그 검색 가능?
```

---

### Step 4: Staging 배포 (Day 41)

```markdown
🎯 Task: Staging 환경에 배포 & 검증

[ ] RDS 데이터베이스 생성 (staging)
[ ] ElastiCache Redis 생성 (staging)
[ ] ECS 서비스 생성 (staging)
[ ] ALB 로드 밸런서 설정
[ ] SSL/TLS 인증서 설정
[ ] 환경 변수 설정
[ ] 데이터베이스 마이그레이션 실행
[ ] 배포 테스트 (Blue-Green)
[ ] 스모크 테스트 (주요 기능)
[ ] 성능 테스트

스모크 테스트:
  [ ] 로그인 가능?
  [ ] 채팅방 생성 가능?
  [ ] 메시지 송수신 가능?
  [ ] AI 응답 가능?
  [ ] Admin 대시보드 접근?

성능 테스트:
  [ ] 1000명 동시 연결?
  [ ] 초당 100개 메시지?
  [ ] API 응답시간 < 200ms?
  [ ] 대시보드 로드 < 2초?
```

---

### Step 5: QA & 성능 테스트 (Day 42~44)

```markdown
🎯 Task: 전체 QA & 성능 테스트

📚 참고: www/09_DEVELOPMENT/02_테스트시나리오.md

Day 42: 전체 기능 테스트

테스트 항목:
  [ ] 회원가입 & 로그인
  [ ] 채팅 (메시지 송수신)
  [ ] 읽음표시 & 입력중 표시
  [ ] AI 응답
  [ ] CRM 자동 저장
  [ ] Admin 대시보드
  [ ] 상담원 관리
  [ ] 고객 대시보드
  [ ] 알림 설정
  [ ] 파일 업로드/다운로드

체크리스트: 100+ 테스트 케이스
  TC-END-TO-END-001: 회원가입 → 로그인 → 채팅
  TC-END-TO-END-002: 채팅 → CRM 저장 → Admin 확인
  TC-END-TO-END-003: AI 호출 → Failover → 로그 확인
  ... (100+ 더)

Day 43: 성능 테스트

부하 테스트:
  [ ] 1000명 동시 연결 (WebSocket)
  [ ] 초당 1000개 메시지 처리
  [ ] 응답시간: P99 < 500ms
  [ ] 에러율: < 0.1%
  [ ] 메모리: 안정적

메모리 누수 테스트:
  [ ] 24시간 연속 운영
  [ ] 메모리 증가 < 5%

응답시간 프로파일링:
  [ ] API 응답: < 200ms
  [ ] WebSocket: < 100ms
  [ ] 대시보드: < 2초
  [ ] 검색: < 1초

Day 44: 보안 테스트

보안 항목:
  [ ] SQL Injection 방어
  [ ] XSS 방어
  [ ] CSRF 방어
  [ ] 인증/인가
  [ ] 암호화 (TLS 1.2+)
  [ ] Rate Limiting
  [ ] DDoS 방어
  [ ] PII 마스킹

OWASP Top 10 검증:
  [ ] Injection
  [ ] Authentication
  [ ] Sensitive Data Exposure
  [ ] XML External Entities (XXE)
  [ ] Broken Access Control
  [ ] Security Misconfiguration
  [ ] Cross-Site Scripting (XSS)
  [ ] Insecure Deserialization
  [ ] Using Components with Known Vulnerabilities
  [ ] Insufficient Logging & Monitoring
```

---

### Step 6: 배포 준비 (Day 45~47)

```markdown
🎯 Task: 프로덕션 배포 준비

Day 45: 배포 가이드 작성
  [ ] 배포 체크리스트
  [ ] 배포 롤백 절차
  [ ] 배포 시간 (점검 시간)
  [ ] 응급 연락처
  [ ] 트러블슈팅 가이드

배포 체크리스트:
  [ ] DB 백업 완료?
  [ ] 환경 변수 확인?
  [ ] API 키 유효성?
  [ ] 이전 버전 정보 저장?
  [ ] 팀 공지?

Day 46: On-Call 운영 가이드 작성
  [ ] On-Call 로테이션
  [ ] 장애 대응 절차
  [ ] 에스컬레이션 정책
  [ ] 모니터링 대시보드
  [ ] 연락처 목록

Day 47: 운영 매뉴얼
  [ ] 일일 체크리스트
  [ ] 주간 백업 절차
  [ ] 월간 성능 리뷰
  [ ] 보안 업데이트 절차
  [ ] 성능 튜닝 가이드

최종 체크리스트:
  [ ] 모든 테스트 PASS
  [ ] 성능 목표 달성
  [ ] 보안 검수 완료
  [ ] 배포 계획 수립
  [ ] 팀 교육 완료
```

---

### Step 7: Go Live! (Day 48~49)

```markdown
🎯 Task: 프로덕션 배포 & Go Live!

Day 48: 배포 실행

배포 절차 (Blue-Green):
1. Blue 서버 (구): 활성
2. Green 서버 (신): 배포 시작
3. Green 헬스 체크 (통과할 때까지)
4. 로드 밸런서 전환 (Blue → Green)
5. Blue 대기 (1시간)

[ ] Green 서버 배포
[ ] 헬스 체크 성공
[ ] 로드 밸런서 전환
[ ] 1시간 모니터링

모니터링:
  [ ] 에러율 확인
  [ ] 응답시간 확인
  [ ] DB 연결 상태
  [ ] WebSocket 연결 상태
  [ ] AI API 상태

Day 49: 최종 검증 & 종료

스모크 테스트:
  [ ] 로그인 가능?
  [ ] 채팅 가능?
  [ ] AI 응답?
  [ ] Admin 대시보드?

최종 보고:
  [ ] 배포 완료 시간
  [ ] 발생 이슈 & 해결
  [ ] 성능 지표
  [ ] 사용자 만족도

축하합니다! 🎉 Go Live!
```

---

## 📊 Phase 4 체크리스트

### 일일 체크포인트

```
Day 36:
  [ ] 문서 읽기 완료
  
Day 36~38:
  [ ] Docker 이미지 빌드
  [ ] CI/CD 파이프라인
  [ ] Kubernetes 매니페스트
  
Day 39~40:
  [ ] Prometheus + Grafana
  [ ] ELK Stack
  [ ] 알림 규칙
  
Day 41:
  [ ] Staging 배포
  [ ] 스모크 테스트 PASS
  
Day 42~44:
  [ ] 기능 테스트 100+
  [ ] 성능 테스트 PASS
  [ ] 보안 테스트 PASS
  
Day 45~47:
  [ ] 배포 가이드
  [ ] On-Call 운영 가이드
  [ ] 운영 매뉴얼
  
Day 48~49:
  [ ] 프로덕션 배포
  [ ] 최종 검증
  [ ] Go Live! 🎉
```

---

## ✅ Phase 4 완료 조건

### Go Live 조건
```
□ 모든 테스트 PASS (기능 + 성능 + 보안)
□ 성능 목표 달성
  ├─ API 응답: < 200ms
  ├─ WebSocket: < 100ms
  ├─ 대시보드: < 2초
  └─ 동시 1000명 연결
□ 모니터링 설정 완료
□ 배포 계획 수립
□ 팀 교육 완료
□ CEO/PM 승인
```

### No-Go 조건
```
❌ 테스트 실패
❌ 성능 목표 미달성
❌ 보안 이슈
❌ 배포 준비 미흡
```

---

## 🎊 전체 완료!

### 9주 여정 완료

```
Week 1: Phase 1 (DB & API) ✅
  └─ 14개 테이블 + 30개 API
  
Week 2~3: Phase 2 (Chat & AI) ✅
  └─ WebSocket + AI Router + React
  
Week 4~5: Phase 3 (CRM & Admin) ✅
  └─ CRM + Admin Dashboard + Customer Dashboard
  
Week 6~7: Phase 4 (배포) ✅
  └─ Docker + CI/CD + 모니터링 + Go Live

📊 최종 결과:
  ✅ 78개 문서 (STEP 1~8)
  ✅ 30+ REST API
  ✅ 15개 WebSocket 이벤트
  ✅ 5개 AI 프로바이더 Failover
  ✅ React Admin + Customer Dashboard
  ✅ Docker + Kubernetes 배포
  ✅ 자동화된 CI/CD
  ✅ 모니터링 & 알림
  
🚀 Go Live!
```

---

## 📞 배포 후 지원

### 운영 팀 준비사항
```
[ ] On-Call 로테이션 설정
[ ] 모니터링 대시보드 모니터링
[ ] 일일 성능 리뷰
[ ] 주간 백업 확인
[ ] 월간 보안 업데이트
[ ] 분기별 성능 최적화
```

### 지속적 개선
```
V3.1 (1개월 후):
  ├─ 사용자 피드백 반영
  ├─ 성능 튜닝
  └─ 추가 기능

V4.0 (3개월 후):
  ├─ E2E 암호화
  ├─ 다중 서버 지원
  └─ 음성/영상 채팅
```

---

**축하합니다! PlusTok V3.0이 완성되었습니다! 🎉**

*Phase 4 배포 & 운영 · 2026-07-21*
