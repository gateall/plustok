import LandingSection from './LandingSection';
import { stats } from './landingData';

export default function LandingStats() {
  return (
    <LandingSection id="stats" title="실적 지표" description="PlusTok ACEP 운영 성과">
      <div className="landing-stats">
        {stats.map((item) => (
          <div key={item.label} className="landing-stats__item">
            <div className="landing-stats__value">
              <span className="landing-stats__count">{item.value}</span>
              {item.suffix ? <span className="landing-stats__suffix">{item.suffix}</span> : null}
            </div>
            <p className="landing-stats__label">{item.label}</p>
          </div>
        ))}
      </div>
    </LandingSection>
  );
}
