import { Link } from 'react-router-dom';
import AppBrand from '@/components/common/AppBrand';
import { EXTERNAL_PHP_LINKS, PUBLIC_ROUTES } from '@/config/publicNav';
import { getAppBrandLabel } from '@/config/appBrand';

export default function LandingFooter() {
  const year = new Date().getFullYear();
  const brandLabel = getAppBrandLabel();

  return (
    <footer className="landing-footer">
      <div className="landing-container landing-footer__grid">
        <div className="landing-footer__col">
          <h3 className="landing-footer__heading">Company</h3>
          <AppBrand variant="landing" linkTo={PUBLIC_ROUTES.landing} className="landing-footer__brand" />
          <p className="landing-footer__text">{brandLabel}</p>
          <p className="landing-footer__text">© {year} All rights reserved.</p>
        </div>
        <div className="landing-footer__col">
          <h3 className="landing-footer__heading">Product</h3>
          <ul className="landing-footer__links">
            <li><Link to="/services">핵심 기능</Link></li>
            <li><Link to="/ai">AI Engine</Link></li>
            <li><Link to="/crm">CRM Features</Link></li>
            <li><Link to="/dashboard">Dashboard</Link></li>
          </ul>
        </div>
        <div className="landing-footer__col">
          <h3 className="landing-footer__heading">Support</h3>
          <ul className="landing-footer__links">
            {/* External PHP — Codex .htaccess keeps /api/v1/health.php on backend */}
            <li>
              <a href={EXTERNAL_PHP_LINKS.health} target="_blank" rel="noreferrer">
                API 상태
              </a>
            </li>
            <li><Link to="/compare">CRM 비교</Link></li>
            <li><Link to="/process">프로세스</Link></li>
          </ul>
        </div>
        <div className="landing-footer__col">
          <h3 className="landing-footer__heading">Contact</h3>
          <ul className="landing-footer__links">
            <li><a href="mailto:adfull@naver.com">adfull@naver.com</a></li>
            <li><Link to={PUBLIC_ROUTES.login}>관리자 로그인</Link></li>
            <li><Link to={PUBLIC_ROUTES.adminDashboard}>Admin Console</Link></li>
          </ul>
        </div>
      </div>
    </footer>
  );
}
