import { AdminCard } from '../admin-ui';
import LandingSection from './LandingSection';
import { services } from './landingData';

export default function LandingServices() {
  return (
    <LandingSection
      id="services"
      title="핵심 기능"
      description="V1.0 — 상담 접수 · 통합 CRM · AI 엔진"
    >
      <div className="landing-services">
        {services.map((item) => (
          <AdminCard key={item.title} interactive className="landing-services__card">
            <div className="landing-services__icon" aria-hidden="true">
              {item.icon}
            </div>
            <h3 className="landing-services__title">{item.title}</h3>
            <p className="landing-services__desc">{item.description}</p>
          </AdminCard>
        ))}
      </div>
    </LandingSection>
  );
}
