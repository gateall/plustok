import { useEffect, useState } from 'react';

export function useMediaQuery(query: string): boolean {
  const [matches, setMatches] = useState(() => {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return false;
    return window.matchMedia(query).matches;
  });

  useEffect(() => {
    if (typeof window.matchMedia !== 'function') return;
    const mq = window.matchMedia(query);
    const update = () => setMatches(mq.matches);
    update();
    mq.addEventListener('change', update);
    return () => mq.removeEventListener('change', update);
  }, [query]);

  return matches;
}

/** PM spec — admin PC layout breakpoint (card/table, consult list). */
export const ADMIN_PC_BREAKPOINT_PX = 769;

export function useIsDesktop(breakpoint = ADMIN_PC_BREAKPOINT_PX): boolean {
  return useMediaQuery(`(min-width: ${breakpoint}px)`);
}

export function useIsAdminPc(): boolean {
  return useMediaQuery(`(min-width: ${ADMIN_PC_BREAKPOINT_PX}px)`);
}
