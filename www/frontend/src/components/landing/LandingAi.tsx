import { AdminCard } from '../admin-ui';
import LandingSection from './LandingSection';
import { aiFeatures } from './landingData';

export default function LandingAi() {
  return (
    <LandingSection
      id="ai"
      title="AI Engine"
      description="Claude → OpenAI → Gemini Failover 체인 내장"
      dark
    >
      <div className="landing-ai">
        {aiFeatures.map((item) => (
          <AdminCard key={item.title} interactive className="landing-ai__card">
            <span className="landing-ai__tag">{item.tag}</span>
            <div className="landing-ai__icon" aria-hidden="true">
              {item.icon}
            </div>
            <h3 className="landing-ai__title">{item.title}</h3>
            <p className="landing-ai__desc">{item.description}</p>
          </AdminCard>
        ))}
      </div>
    </LandingSection>
  );
}
