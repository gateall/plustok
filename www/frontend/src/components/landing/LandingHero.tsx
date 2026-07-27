import { Button } from '../admin-ui';
import { EXTERNAL_PHP_LINKS, PUBLIC_ROUTES } from '@/config/publicNav';

function CrmMockup() {
  return (
    <div className="landing-hero__mockup" aria-hidden="true">
      <div className="landing-hero__mockup-bar">
        <span className="landing-hero__mockup-dot" />
        <span className="landing-hero__mockup-dot" />
        <span className="landing-hero__mockup-dot" />
        <span className="landing-hero__mockup-title">PlusTok CRM</span>
      </div>
      <div className="landing-hero__mockup-body">
        <div className="landing-hero__mockup-sidebar">
          <div className="landing-hero__mockup-nav-item landing-hero__mockup-nav-item--active" />
          <div className="landing-hero__mockup-nav-item" />
          <div className="landing-hero__mockup-nav-item" />
          <div className="landing-hero__mockup-nav-item" />
        </div>
        <div className="landing-hero__mockup-main">
          <div className="landing-hero__mockup-kpi">
            <div className="landing-hero__mockup-kpi-card" />
            <div className="landing-hero__mockup-kpi-card" />
            <div className="landing-hero__mockup-kpi-card" />
          </div>
          <div className="landing-hero__mockup-list">
            <div className="landing-hero__mockup-row" />
            <div className="landing-hero__mockup-row" />
            <div className="landing-hero__mockup-row landing-hero__mockup-row--highlight" />
            <div className="landing-hero__mockup-row" />
          </div>
        </div>
      </div>
    </div>
  );
}

export default function LandingHero() {
  return (
    <section className="landing-hero" aria-labelledby="landing-hero-title">
      <div className="landing-container landing-hero__grid">
        <div className="landing-hero__content">
          <p className="landing-hero__eyebrow">통합 상담관리 플랫폼</p>
          <h1 id="landing-hero-title" className="landing-hero__title">
            여러 사이트의 상담을
            <br />
            한 곳에서 관리합니다
          </h1>
          <p className="landing-hero__desc">
            각 사이트는 상담을 접수만 하고, 모든 데이터는 하나의 CRM으로 모입니다.
            고객·상담·사이트·통계를 통합 운영하세요.
          </p>
          <div className="landing-hero__actions">
            <Button to={PUBLIC_ROUTES.login} variant="primary" className="landing-hero__cta">
              관리자 화면으로 →
            </Button>
            <Button to={PUBLIC_ROUTES.chat} variant="secondary" className="landing-hero__cta-secondary">
              AI 상담 체험
            </Button>
          </div>
          <p className="landing-hero__sub">
            서버 상태 확인:{' '}
            {/* External PHP — Codex .htaccess keeps backend health endpoint */}
            <a href={EXTERNAL_PHP_LINKS.health} target="_blank" rel="noreferrer">
              {EXTERNAL_PHP_LINKS.health}
            </a>
          </p>
        </div>
        <CrmMockup />
      </div>
    </section>
  );
}
