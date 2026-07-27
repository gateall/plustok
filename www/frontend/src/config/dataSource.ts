/**

 * RC1 data source flags — PM Implementation RC1 gate OPEN (2026-07-24).

 * Release 3.4 P0: production builds never resolve to mock data sources.

 * @see CRM_Enterprise_PM/SPRINT_3_2/IMPLEMENTATION_RC1_GATE_OPEN.md

 */



/** Vite production bundle — mock/sample UI must not render as live data. */

export const IS_PRODUCTION_BUILD = import.meta.env.PROD;



/** Default mock in dev; set VITE_USE_MOCK=false with VITE_CRM_API_ENABLED=true for live API. */

export const USE_MOCK_DATA = !IS_PRODUCTION_BUILD && import.meta.env.VITE_USE_MOCK !== 'false';



/** Enable when deploying against live CRM admin API. Always true in production builds. */

export const CRM_API_ENABLED = IS_PRODUCTION_BUILD || import.meta.env.VITE_CRM_API_ENABLED === 'true';



export function resolveDataSource(): 'mock' | 'api' {

  if (IS_PRODUCTION_BUILD) return 'api';

  if (CRM_API_ENABLED && !USE_MOCK_DATA) return 'api';

  return 'mock';

}



/** Dev-only sample rows (CustomerListPage, etc.) — never true in production. */

export function allowDevMockData(): boolean {

  return !IS_PRODUCTION_BUILD && USE_MOCK_DATA;

}



/** ApiSiteRepository 404 → mock fallback — dev/test only. */

export function allowMockApiFallback(): boolean {

  return !IS_PRODUCTION_BUILD;

}
