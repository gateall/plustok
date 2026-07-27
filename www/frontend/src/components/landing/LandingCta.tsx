import { Button } from '../admin-ui';
import { PUBLIC_ROUTES } from '@/config/publicNav';

export default function LandingCta() {
  return (
    <section className="landing-cta" aria-labelledby="landing-cta-title">
      <div className="landing-container landing-cta__inner">
        <h2 id="landing-cta-title" className="landing-cta__title">
          지금 PlusTok ACEP을 체험해 보세요
        </h2>
        <p className="landing-cta__desc">
          데모 요청, 관리자 체험, AI 상담 체험 — 원하는 방식으로 시작하세요.
        </p>
        <div className="landing-cta__actions">
          <Button href="mailto:adfull@naver.com" variant="primary">
            데모 요청
          </Button>
          <Button to={PUBLIC_ROUTES.login} variant="secondary">
            관리자 체험
          </Button>
          <Button to={PUBLIC_ROUTES.chat} variant="ghost">
            AI 상담 체험
          </Button>
        </div>
      </div>
    </section>
  );
}
