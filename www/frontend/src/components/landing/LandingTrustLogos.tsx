import LandingSection from './LandingSection';
import { trustLogos } from './landingData';

export default function LandingTrustLogos() {
  return (
    <LandingSection
      id="brands"
      title="연동 브랜드"
      description="사업부 → 브랜드 → 사이트(도메인) 구조로 통합 운영"
    >
      <div className="landing-trust">
        {trustLogos.map((logo) => (
          <div key={logo.name} className="landing-trust__item">
            <span className="landing-trust__division">{logo.division}</span>
            <span className="landing-trust__name">{logo.name}</span>
            <span className="landing-trust__domain">{logo.domain}</span>
            <span className="landing-trust__product">{logo.product}</span>
          </div>
        ))}
      </div>
    </LandingSection>
  );
}
