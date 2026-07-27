import { useEffect } from 'react';

import LandingPage from './LandingPage';

type LandingSectionPageProps = {
  sectionId: string;
};

/** HashRouter route e.g. #/ai → full landing + scroll to section id */
export default function LandingSectionPage({ sectionId }: LandingSectionPageProps) {
  useEffect(() => {
    const timer = window.setTimeout(() => {
      document.getElementById(sectionId)?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
      });
    }, 0);

    return () => window.clearTimeout(timer);
  }, [sectionId]);

  return <LandingPage />;
}
