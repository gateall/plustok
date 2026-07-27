import { Link } from 'react-router-dom';
import AppBrand from '@/components/common/AppBrand';
import { Button } from '../admin-ui';
import { LANDING_SECTION_LINKS, PUBLIC_ROUTES } from '@/config/publicNav';

export default function LandingHeader() {
  return (
    <header className="landing-header">
      <div className="landing-container landing-header__inner">
        <AppBrand variant="landing" linkTo={PUBLIC_ROUTES.landing} className="landing-header__brand" />

        <nav className="landing-header__nav" aria-label="주요 메뉴">
          {LANDING_SECTION_LINKS.map(({ to, label }) => (
            <Link key={to} to={to} className="landing-header__link">
              {label}
            </Link>
          ))}
          <Link to={PUBLIC_ROUTES.adminDashboard} className="landing-header__link">
            관리자
          </Link>
        </nav>

        <Button to={PUBLIC_ROUTES.login} variant="primary" className="landing-header__cta">
          관리자 로그인
        </Button>
      </div>
    </header>
  );
}
