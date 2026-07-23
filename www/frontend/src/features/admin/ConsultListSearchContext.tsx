import {
  createContext,
  useCallback,
  useContext,
  useMemo,
  useRef,
  type MutableRefObject,
  type ReactNode,
} from 'react';
import { useNavigate } from 'react-router-dom';

type ConsultListSearchContextValue = {
  registerSearchInput: (ref: MutableRefObject<HTMLInputElement | null>) => void;
  focusSearch: () => void;
  openConsultSearch: () => void;
};

const ConsultListSearchContext = createContext<ConsultListSearchContextValue | null>(null);

export function ConsultListSearchProvider({ children }: { children: ReactNode }) {
  const navigate = useNavigate();
  const inputHolder = useRef<MutableRefObject<HTMLInputElement | null> | null>(null);

  const registerSearchInput = useCallback((ref: MutableRefObject<HTMLInputElement | null>) => {
    inputHolder.current = ref;
  }, []);

  const focusSearch = useCallback(() => {
    window.setTimeout(() => {
      inputHolder.current?.current?.focus();
    }, 100);
  }, []);

  const openConsultSearch = useCallback(() => {
    const path = window.location.hash.replace(/^#/, '') || '/';
    if (path.startsWith('/admin/consults') && !path.match(/^\/admin\/consults\/[^/]+$/)) {
      focusSearch();
      return;
    }
    navigate('/admin/consults?focus=search');
  }, [navigate, focusSearch]);

  const value = useMemo(
    () => ({ registerSearchInput, focusSearch, openConsultSearch }),
    [registerSearchInput, focusSearch, openConsultSearch],
  );

  return (
    <ConsultListSearchContext.Provider value={value}>{children}</ConsultListSearchContext.Provider>
  );
}

export function useConsultListSearch(): ConsultListSearchContextValue {
  const ctx = useContext(ConsultListSearchContext);
  if (!ctx) {
    return {
      registerSearchInput: () => {},
      focusSearch: () => {},
      openConsultSearch: () => {},
    };
  }
  return ctx;
}
