import { AdminCard } from '../admin-ui';
import LandingSection from './LandingSection';
import { whyItems } from './landingData';

export default function LandingWhy() {
  return (
    <LandingSection
      id="why"
      title="WHY PLUS톡"
      description="통합 CRM + AI가 만드는 차별화된 상담 경험"
      dark
    >
      <div className="landing-why">
        {whyItems.map((item) => (
          <AdminCard key={item.title} className="landing-why__card">
            <div className="landing-why__icon" aria-hidden="true">
              {item.icon}
            </div>
            <h3 className="landing-why__title">{item.title}</h3>
            <p className="landing-why__desc">{item.description}</p>
          </AdminCard>
        ))}
      </div>
    </LandingSection>
  );
}
