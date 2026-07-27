export type ServiceItem = {
  icon: string;
  title: string;
  description: string;
};

export type WhyItem = {
  icon: string;
  title: string;
  description: string;
};

export type AiFeature = {
  icon: string;
  title: string;
  description: string;
  tag: string;
};

export type CrmFeature = {
  icon: string;
  title: string;
  description: string;
};

export type ProcessStep = {
  step: number;
  title: string;
  description: string;
};

export type StatItem = {
  value: string;
  suffix?: string;
  label: string;
};

export type CompareRow = {
  feature: string;
  generic: string;
  plustok: string;
  highlight?: boolean;
};

export type TrustLogo = {
  division: string;
  name: string;
  domain: string;
  product: string;
};

export const services: ServiceItem[] = [
  {
    icon: '📥',
    title: '통합 상담 접수',
    description: '모든 사이트의 상담을 하나의 API로 수집하고 자동 분류합니다.',
  },
  {
    icon: '🗂️',
    title: '실시간 CRM 관리',
    description: '고객·상담·상태·담당자를 한 화면에서 처리하고 이력을 남깁니다.',
  },
  {
    icon: '🌐',
    title: '사이트 무한 확장',
    description: '새 사이트는 등록·API Key 발급만 하면 즉시 연동됩니다.',
  },
];

export const whyItems: WhyItem[] = [
  { icon: '⚡', title: '빠른 응답', description: 'AI 자동 응답으로 첫 응답 시간을 90% 단축' },
  { icon: '🔗', title: '멀티 사이트', description: '브랜드·도메인별 상담을 하나의 CRM으로 통합' },
  { icon: '🤖', title: 'AI 내장', description: '요약·추천·자동응답이 기본 탑재된 CRM' },
  { icon: '📊', title: '실시간 통계', description: 'KPI·전환율·상담원 성과를 즉시 확인' },
  { icon: '🔒', title: '권한 관리', description: '역할별 RBAC로 안전한 운영' },
  { icon: '📱', title: '모바일 퍼스트', description: '360px부터 데스크톱까지 완벽 대응' },
  { icon: '🔄', title: 'Failover AI', description: 'Claude→OpenAI→Gemini 자동 전환' },
  { icon: '📈', title: '계약 전환', description: '상담→CRM→계약까지 원스톱' },
];

export const aiFeatures: AiFeature[] = [
  {
    icon: '📝',
    title: 'AI Summary',
    description: '긴 상담 대화를 핵심만 요약해 상담원에게 전달합니다.',
    tag: '요약',
  },
  {
    icon: '💬',
    title: 'AI Reply',
    description: '고객 문의에 AI가 1차 응답하고 상담원이 검토·전송합니다.',
    tag: '자동응답',
  },
  {
    icon: '✨',
    title: 'AI Recommendation',
    description: '상담 맥락에 맞는 답변·상품을 실시간 추천합니다.',
    tag: '추천',
  },
];

export const crmFeatures: CrmFeature[] = [
  { icon: '💬', title: '상담', description: '실시간 채팅·상태·배정' },
  { icon: '👥', title: '고객', description: '고객 360° 프로필·이력' },
  { icon: '📊', title: '통계', description: 'KPI·차트·리포트' },
  { icon: '🤖', title: 'AI', description: '요약·추천·모니터링' },
  { icon: '🌐', title: '사이트', description: '멀티 도메인·API Key' },
  { icon: '🔐', title: '권한', description: 'RBAC·감사 로그' },
  { icon: '📋', title: '보고서', description: '일·주·월간 리포트' },
  { icon: '🔌', title: 'API', description: 'REST·Webhook 연동' },
];

export const processSteps: ProcessStep[] = [
  { step: 1, title: '접수', description: '사이트·채널 상담 수집' },
  { step: 2, title: 'CRM', description: '고객·상담 자동 등록' },
  { step: 3, title: 'AI', description: '요약·1차 응답·추천' },
  { step: 4, title: '상담', description: '상담원 실시간 처리' },
  { step: 5, title: '계약', description: '전환·후속 관리' },
];

export const stats: StatItem[] = [
  { value: '12', suffix: 'K+', label: '처리 상담' },
  { value: '94', suffix: '%', label: 'AI 응답률' },
  { value: '1.2', suffix: '분', label: '평균 응답시간' },
  { value: '4.8', suffix: '/5', label: '고객 만족도' },
];

export const compareRows: CompareRow[] = [
  { feature: 'AI 자동 응답', generic: '별도 구축 필요', plustok: '기본 내장', highlight: true },
  { feature: '멀티 사이트 통합', generic: '사이트별 분리', plustok: '단일 CRM', highlight: true },
  { feature: '실시간 채팅', generic: '플러그인 추가', plustok: '네이티브 지원' },
  { feature: 'AI Failover', generic: '미지원', plustok: '5-provider 체인' },
  { feature: '모바일 CRM', generic: '제한적', plustok: 'Mobile First' },
  { feature: '권한·감사', generic: '기본 수준', plustok: 'RBAC + Audit' },
];

export const trustLogos: TrustLogo[] = [
  { division: '통신사업', name: 'SmartTokTok', domain: 'smarttoktok.com', product: '대표번호·070·기업인터넷' },
  { division: '통신가입', name: 'LG15441644', domain: 'lg15441644.kr', product: '인터넷·070·IPTV·CCTV·결합' },
  { division: '웹제작', name: 'HompyShop', domain: 'hompyshop.com', product: '홈페이지·쇼핑몰·SEO' },
  { division: 'AI 플랫폼', name: 'ShowForm', domain: 'showform.kr', product: 'AI 랜딩페이지·설문' },
  { division: '광고플랫폼', name: 'CallMap', domain: 'callmap.kr', product: '플레이스·지도상위·지역광고' },
  { division: '판촉사업', name: 'HongPansa', domain: 'hongpansa.kr', product: '판촉물·체험단·상위노출' },
  { division: '중개서비스', name: 'Oncap24', domain: 'oncap24.com', product: '이사·공사·역경매' },
  { division: '플랫폼 사업', name: 'nuguupso', domain: 'nuguupso.com', product: '역경매(인테리어·청소·설비)' },
];
