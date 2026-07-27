import LandingSection from './LandingSection';

export default function LandingDashboardPreview() {
  return (
    <LandingSection
      id="dashboard"
      title="Dashboard Preview"
      description="실시간 KPI · 상담 현황 · AI 모니터링"
    >
      <div className="landing-dashboard">
        <div className="landing-dashboard__monitor">
          <div className="landing-dashboard__bezel">
            <div className="landing-dashboard__screen">
              <div className="landing-dashboard__topbar">
                <span className="landing-dashboard__logo">PlusTok Admin</span>
                <div className="landing-dashboard__nav">
                  <span>Dashboard</span>
                  <span>Consults</span>
                  <span>AI Monitor</span>
                </div>
              </div>
              <div className="landing-dashboard__kpi">
                <div className="landing-dashboard__kpi-card">
                  <span className="landing-dashboard__kpi-label">오늘 상담</span>
                  <span className="landing-dashboard__kpi-value">128</span>
                </div>
                <div className="landing-dashboard__kpi-card">
                  <span className="landing-dashboard__kpi-label">AI 응답률</span>
                  <span className="landing-dashboard__kpi-value">94%</span>
                </div>
                <div className="landing-dashboard__kpi-card">
                  <span className="landing-dashboard__kpi-label">평균 응답</span>
                  <span className="landing-dashboard__kpi-value">1.2분</span>
                </div>
                <div className="landing-dashboard__kpi-card">
                  <span className="landing-dashboard__kpi-label">만족도</span>
                  <span className="landing-dashboard__kpi-value">4.8</span>
                </div>
              </div>
              <div className="landing-dashboard__chart">
                <div className="landing-dashboard__chart-bar" style={{ height: '60%' }} />
                <div className="landing-dashboard__chart-bar" style={{ height: '80%' }} />
                <div className="landing-dashboard__chart-bar" style={{ height: '45%' }} />
                <div className="landing-dashboard__chart-bar" style={{ height: '90%' }} />
                <div className="landing-dashboard__chart-bar" style={{ height: '70%' }} />
                <div className="landing-dashboard__chart-bar" style={{ height: '55%' }} />
                <div className="landing-dashboard__chart-bar" style={{ height: '85%' }} />
              </div>
            </div>
          </div>
          <div className="landing-dashboard__stand" aria-hidden="true" />
        </div>
      </div>
    </LandingSection>
  );
}
