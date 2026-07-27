import { ApiFetchError } from '@/services/api.client';

export type AdminErrorPresentation = {
  title: string;
  description: string;
  showLogin?: boolean;
  showRetry?: boolean;
};

const DEFAULT: AdminErrorPresentation = {
  title: '목록을 불러오지 못했습니다',
  description: '잠시 후 다시 시도해 주세요.',
  showRetry: true,
};

export function adminErrorFromUnknown(
  error: unknown,
  context: 'list' | 'detail' | 'save' = 'list',
): AdminErrorPresentation {
  if (error instanceof ApiFetchError) {
    switch (error.status) {
      case 401:
        return {
          title: '로그인이 필요합니다',
          description: '로그인이 만료되었습니다. 다시 로그인해 주세요.',
          showLogin: true,
        };
      case 403:
        return {
          title: '접근 권한 없음',
          description: '이 기능에 접근할 권한이 없습니다.',
        };
      case 404:
        return {
          title: context === 'list' ? '목록을 불러오지 못했습니다' : '데이터를 찾을 수 없습니다',
          description: '요청한 API 또는 데이터를 찾을 수 없습니다.',
          showRetry: true,
        };
      case 400:
      case 422:
        return {
          title: '입력 오류',
          description: error.message || '검색 조건을 확인해 주세요.',
          showRetry: true,
        };
      case 500:
        return {
          title: '목록을 불러오지 못했습니다',
          description: '서버 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.',
          showRetry: true,
        };
      default:
        return {
          title: DEFAULT.title,
          description: error.message || DEFAULT.description,
          showRetry: true,
        };
    }
  }

  if (error instanceof Error && /session expired|401|unauthorized|로그인/i.test(error.message)) {
    return {
      title: '로그인이 필요합니다',
      description: '관리자로 로그인해 주세요.',
      showLogin: true,
    };
  }

  return {
    ...DEFAULT,
    description: error instanceof Error ? error.message : DEFAULT.description,
  };
}
