import LandingSection from './LandingSection';
import { processSteps } from './landingData';

export default function LandingProcess() {
  return (
    <LandingSection
      id="process"
      title="PROCESS"
      description="접수부터 계약까지 5단계 자동화"
      dark
    >
      <ol className="landing-process">
        {processSteps.map((step, index) => (
          <li key={step.title} className="landing-process__step">
            <div className="landing-process__number">{step.step}</div>
            <div className="landing-process__content">
              <h3 className="landing-process__title">{step.title}</h3>
              <p className="landing-process__desc">{step.description}</p>
            </div>
            {index < processSteps.length - 1 ? (
              <div className="landing-process__connector" aria-hidden="true" />
            ) : null}
          </li>
        ))}
      </ol>
    </LandingSection>
  );
}
