import { AdminCard } from '../admin-ui';
import LandingSection from './LandingSection';
import { crmFeatures } from './landingData';

export default function LandingCrmFeatures() {
  return (
    <LandingSection
      id="crm"
      title="CRM Features"
      description="상담 운영에 필요한 모든 기능을 한 곳에"
    >
      <div className="landing-crm">
        {crmFeatures.map((item) => (
          <AdminCard key={item.title} className="landing-crm__card">
            <div className="landing-crm__icon" aria-hidden="true">
              {item.icon}
            </div>
            <h3 className="landing-crm__title">{item.title}</h3>
            <p className="landing-crm__desc">{item.description}</p>
          </AdminCard>
        ))}
      </div>
    </LandingSection>
  );
}
