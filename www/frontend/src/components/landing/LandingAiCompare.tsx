import clsx from 'clsx';
import LandingSection from './LandingSection';
import { compareRows } from './landingData';

export default function LandingAiCompare() {
  return (
    <LandingSection
      id="compare"
      title="AI Compare"
      description="일반 CRM vs PLUS톡"
      dark
    >
      <div className="landing-compare">
        <div className="landing-compare__table-wrap">
          <table className="landing-compare__table">
            <thead>
              <tr>
                <th scope="col">기능</th>
                <th scope="col">일반 CRM</th>
                <th scope="col">PLUS톡</th>
              </tr>
            </thead>
            <tbody>
              {compareRows.map((row) => (
                <tr
                  key={row.feature}
                  className={clsx(row.highlight && 'landing-compare__row--highlight')}
                >
                  <td>{row.feature}</td>
                  <td>{row.generic}</td>
                  <td>
                    <strong>{row.plustok}</strong>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </LandingSection>
  );
}
