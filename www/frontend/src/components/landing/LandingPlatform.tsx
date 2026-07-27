import LandingSection from './LandingSection';

export default function LandingPlatform() {
  return (
    <LandingSection
      id="platform"
      title="Platform"
      description="모든 채널의 상담을 하나의 플랫폼으로"
    >
      <div className="landing-platform">
        <div className="landing-platform__visual" aria-hidden="true">
          <div className="landing-platform__screen">
            <div className="landing-platform__screen-header">
              <span />
              <span />
              <span />
            </div>
            <div className="landing-platform__screen-body">
              <div className="landing-platform__channel landing-platform__channel--1">Web</div>
              <div className="landing-platform__channel landing-platform__channel--2">Mobile</div>
              <div className="landing-platform__channel landing-platform__channel--3">API</div>
              <div className="landing-platform__hub">CRM Hub</div>
            </div>
          </div>
        </div>
        <div className="landing-platform__content">
          <h3 className="landing-platform__heading">멀티 사이트 · 단일 CRM</h3>
          <p className="landing-platform__text">
            SmartTokTok, LG15441644, HompyShop 등 다양한 브랜드 사이트의 상담을
            PlusTok ACEP 하나로 통합합니다. 사이트별 API Key 발급만으로 즉시 연동됩니다.
          </p>
          <ul className="landing-platform__list">
            <li>REST API · Webhook 상담 접수</li>
            <li>사이트·브랜드·사업부 계층 관리</li>
            <li>실시간 Socket.io 채팅 (V1.5+)</li>
            <li>고객·상담·통계 통합 대시보드</li>
          </ul>
        </div>
      </div>
    </LandingSection>
  );
}
