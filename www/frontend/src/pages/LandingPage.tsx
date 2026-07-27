import {
  LandingHeader,
  LandingHero,
  LandingServices,
  LandingWhy,
  LandingPlatform,
  LandingAi,
  LandingCrmFeatures,
  LandingProcess,
  LandingDashboardPreview,
  LandingStats,
  LandingAiCompare,
  LandingTrustLogos,
  LandingCta,
  LandingFooter,
} from '../components/landing';

export default function LandingPage() {
  return (
    <div className="landing-page">
      <LandingHeader />
      <main>
        <LandingHero />
        <LandingServices />
        <LandingWhy />
        <LandingPlatform />
        <LandingAi />
        <LandingCrmFeatures />
        <LandingProcess />
        <LandingDashboardPreview />
        <LandingStats />
        <LandingAiCompare />
        <LandingTrustLogos />
        <LandingCta />
      </main>
      <LandingFooter />
    </div>
  );
}
